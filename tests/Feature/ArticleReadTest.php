<?php

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use App\References\ArticleReader;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    User::created(function (User $user): void {
        $user->unlimited = true;
        $user->save();
    });
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
});

it('reads all HTML content and saves notes with provenance through the authorized endpoint', function () {
    $reference = Reference::factory()->create(['source_url' => 'https://example.org/article', 'metadata' => ['type' => 'article']]);
    $body = '<article><h1>Methods</h1><p>'.str_repeat('Isi penelitian. ', 1300).'</p><h2>References</h2></article>';
    $reader = Mockery::mock(ArticleReader::class, [AiClient::fromConfig()])->makePartial()->shouldAllowMockingProtectedMethods();
    $reader->shouldReceive('download')->once()->with($reference->source_url)->andReturn([$body, $reference->source_url]);
    $this->app->instance(ArticleReader::class, $reader);
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['notes' => 'Metode penelitian dijelaskan pada artikel.'])]]]])]);
    $this->actingAs($reference->project->user)->postJson("/projects/{$reference->project_id}/references/{$reference->id}/read")
        ->assertOk();
    expect($reference->fresh()->notes)->toContain('Catatan AI · belum diperiksa', 'Isi artikel HTML', 'https://example.org/article');
    Http::assertSentCount(1);
});

it('labels abstract-only content without claiming full text access', function () {
    $reference = Reference::factory()->make(['source_url' => 'https://example.org/article', 'metadata' => ['type' => 'article']]);
    $reader = Mockery::mock(ArticleReader::class, [AiClient::fromConfig()])->makePartial()->shouldAllowMockingProtectedMethods();
    $reader->shouldReceive('download')->andReturn(['<div id="abstract">'.str_repeat('Abstrak penelitian. ', 20).'</div>', $reference->source_url]);
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['notes' => 'Ringkasan abstrak.'])]]]])]);
    expect($reader->read($reference))->toContain('Abstrak saja', 'Ringkasan abstrak.');
});

it('blocks local URLs without calling AI and preserves old notes', function () {
    $reference = Reference::factory()->create(['source_url' => 'http://127.0.0.1/private', 'metadata' => ['type' => 'article'], 'notes' => 'Catatan lama.']);
    Http::fake();
    $this->actingAs($reference->project->user)->postJson("/projects/{$reference->project_id}/references/{$reference->id}/read")
        ->assertStatus(502);
    expect($reference->fresh()->notes)->toBe('Catatan lama.');
    Http::assertNothingSent();
});

it('does not auto-read articles while drafting and refuses sources without reviewed notes', function () {
    $project = Project::factory()->withOutline()->create();
    $missing = Reference::factory()->for($project)->create();
    $pending = Reference::factory()->for($project)->create(['title' => 'Sumber catatan AI', 'notes' => Reference::AI_NOTES_PENDING."\nDasar: Teks lengkap\n\nLiterasi mendukung belajar."]);
    $reader = Mockery::mock(ArticleReader::class);
    $reader->shouldNotReceive('read');
    $this->app->instance(ArticleReader::class, $reader);
    Http::fake();
    $this->actingAs($project->user)->postJson("/projects/{$project->id}/draft/generate", ['unit' => 's2', 'references' => [$missing->id, $pending->id]])
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'belum ada catatan') && str_contains($message, 'catatan AI belum ditinjau'));
    expect($missing->fresh()->notes)->toBeNull();
    Http::assertNothingSent();
});

it('does not let another user read a project reference', function () {
    $reference = Reference::factory()->create();
    $other = Project::factory()->create();
    $this->actingAs($other->user)->postJson("/projects/{$reference->project_id}/references/{$reference->id}/read")->assertForbidden();
    $this->actingAs($reference->project->user)->postJson("/projects/{$other->id}/references/{$reference->id}/read")->assertNotFound();
});

it('continues drafts and manuscript sections with readable sources and reports excluded sources', function (string $endpoint) {
    $project = Project::factory()->withOutline()->create();
    $unreadable = Reference::factory()->for($project)->create(['notes' => null]);
    $readable = Reference::factory()->for($project)->create(['notes' => 'Literasi mendukung pembelajaran.']);
    $reader = Mockery::mock(ArticleReader::class);
    $reader->shouldNotReceive('read');
    $this->app->instance(ArticleReader::class, $reader);
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'text' => "Literasi mendukung pembelajaran [@{$readable->id}].",
        'evidence' => [['id' => $readable->id, 'quote' => 'Literasi mendukung pembelajaran.']],
        'limitations' => '',
    ])]]]])]);

    $response = $this->actingAs($project->user)->postJson("/projects/{$project->id}/{$endpoint}", [
        'unit' => 's2', 'references' => [$unreadable->id, $readable->id], 'target_words' => 300, 'mode' => 'fill',
    ])->assertOk()->assertJsonPath('text', "Literasi mendukung pembelajaran [@{$readable->id}].");

    expect($response->json('limitations'))->toContain($unreadable->title, 'tidak dipakai', 'belum ada catatan')
        ->and($response->json('evidence'))->toBe([['id' => $readable->id, 'quote' => 'Literasi mendukung pembelajaran.']]);
    expect($unreadable->fresh()->notes)->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], "[@{$readable->id}]")
        && ! str_contains($request['messages'][1]['content'], "[@{$unreadable->id}]"));
    if ($endpoint === 'manuscript/section') {
        expect($project->fresh()->draft['s2'])->toBe("Literasi mendukung pembelajaran [@{$readable->id}].");
    }
})->with(['draft/generate', 'manuscript/section']);

it('keeps overlong AI notes usable within the form limit without another AI call', function () {
    $reader = app(ArticleReader::class);
    $reference = Reference::factory()->make();
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['notes' => str_repeat('Temuan penelitian mendukung latihan interaktif. ', 150)])]]]])]);
    $notes = $reader->summarize($reference, str_repeat('Isi artikel. ', 3000).'TEMUAN TERAKHIR', 'Teks lengkap', $reference->source_url);
    expect(mb_strlen($notes))->toBeLessThanOrEqual(5000)
        ->and($notes)->toContain('Temuan penelitian', 'Ringkasan dibatasi');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'TEMUAN TERAKHIR'));
});

it('preserves source provenance and the total notes limit for a long source URL', function () {
    $reader = app(ArticleReader::class);
    $url = 'https://example.org/'.str_repeat('a', 2000);
    $notes = $reader->formatNotes(str_repeat('Temuan penelitian. ', 500), 'Teks PDF seluruh halaman', $url);
    expect(mb_strlen($notes))->toBeLessThanOrEqual(5000)
        ->and($notes)->toContain($url, 'Ringkasan dibatasi');
});

it('still rejects missing notes instead of saving fabricated source content', function () {
    expect(fn () => app(ArticleReader::class)->formatNotes(['invalid'], 'Teks lengkap', 'https://example.org/article'))
        ->toThrow(AiException::class);
});
