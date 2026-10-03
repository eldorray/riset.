<?php

declare(strict_types=1);

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
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'model-uji', 'timeout' => 5, 'json_mode' => false]]);
    $this->project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Teks tersimpan.']]);
    $this->reference = Reference::factory()->for($this->project)->create(['notes' => 'Catatan pengguna.']);
});

it('membuat draf satu bagian hanya dengan rujukan dari referensi terpilih', function () {
    $id = $this->reference->id;
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => [
        'content' => "```json\n".json_encode(['text' => "Paragraf [@{$id}] dan [@9999].", 'limitations' => ''])."\n```",
    ]]]])]);

    $response = $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's2', 'references' => [$id]])
        ->assertOk();

    expect($response->json('text'))->toBe("Paragraf [@{$id}] dan.")
        ->and($response->json('limitations'))->toContain('telah dihapus')
        ->and($this->project->fresh()->draft)->toBe(['s1' => 'Teks tersimpan.']);

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization')
        && ! isset($request['response_format'])
        && str_contains($request['messages'][1]['content'], "[@{$id}]")
        && str_contains($request['messages'][1]['content'], 'Catatan pengguna.'));
});

it('menyatakan keterbatasan dari AI apa adanya', function () {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'Teks umum.', 'limitations' => 'Sumber tidak cukup.'])]]]])]);

    $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 'c2', 'references' => []])
        ->assertOk()
        ->assertJsonPath('limitations', 'Sumber tidak cukup.');
});

it('menolak referensi dari proyek lain untuk generasi', function () {
    $foreign = Reference::factory()->create();

    $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's1', 'references' => [$foreign->id]])
        ->assertJsonValidationErrors('references.0');
});

it('tidak mengubah draf tersimpan bila generasi gagal', function () {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => 'bukan json']]]])]);

    $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's1', 'references' => []])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Jawaban layanan AI tidak dapat dibaca.');

    expect($this->project->fresh()->draft)->toBe(['s1' => 'Teks tersimpan.']);
});

it('menyimpan draf hasil edit dan mempertahankan bagian lain', function () {
    $this->project->update(['draft' => ['s1' => 'Lama.', 'hapus' => 'Teks bagian yang sudah dihapus dari kerangka.']]);

    $this->actingAs($this->project->user)
        ->put("/projects/{$this->project->id}/draft", ['draft' => ['s2' => "Baru [@{$this->reference->id}]."]])
        ->assertSessionHasNoErrors();

    expect($this->project->fresh()->draft)->toBe([
        's1' => 'Lama.',
        'hapus' => 'Teks bagian yang sudah dihapus dari kerangka.',
        's2' => "Baru [@{$this->reference->id}].",
    ]);

    $this->get("/projects/{$this->project->id}/draft")->assertInertia(fn ($page) => $page
        ->component('projects/Draft')
        ->where('draft.s2', "Baru [@{$this->reference->id}]."));
});

it('menolak sitasi ke referensi proyek lain dan bagian di luar kerangka', function () {
    $foreign = Reference::factory()->create();

    $this->actingAs($this->project->user)
        ->put("/projects/{$this->project->id}/draft", ['draft' => ['s1' => "Teks [@{$foreign->id}]"]])
        ->assertSessionHasErrors('draft');

    $this->put("/projects/{$this->project->id}/draft", ['draft' => ['tidak-ada' => 'x']])->assertSessionHasErrors('draft');

    expect($this->project->fresh()->draft)->toBe(['s1' => 'Teks tersimpan.']);
});

it('autosave menolak draf basi dan menerima perubahan bagian lain', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Awal.', 's2' => 'Bagian dua.']]);
    $this->actingAs($project->user)->putJson("/projects/{$project->id}/draft", [
        'draft' => ['s1' => 'Edit tab pertama.'], 'base' => ['s1' => 'Awal.'],
    ])->assertOk()->assertJsonPath('draft.s1', 'Edit tab pertama.');
    $this->putJson("/projects/{$project->id}/draft", [
        'draft' => ['s1' => 'Edit tab kedua.'], 'base' => ['s1' => 'Awal.'],
    ])->assertStatus(409);
    $this->putJson("/projects/{$project->id}/draft", [
        'draft' => ['s2' => 'Edit bagian dua.'], 'base' => ['s2' => 'Bagian dua.'],
    ])->assertOk();
    expect($project->fresh()->draft)->toBe(['s1' => 'Edit tab pertama.', 's2' => 'Edit bagian dua.']);
});

it('autosave memerlukan teks dasar dan menolak sitasi asing', function () {
    $project = Project::factory()->withOutline()->create();
    $this->actingAs($project->user)->putJson("/projects/{$project->id}/draft", ['draft' => ['s1' => 'Teks.']])
        ->assertJsonValidationErrors('base');
    $this->putJson("/projects/{$project->id}/draft", ['draft' => ['s1' => 'Teks [@9999].'], 'base' => ['s1' => '']])
        ->assertJsonValidationErrors('draft');
    expect($project->fresh()->draft)->toBeNull();
});

it('autosave mempertahankan spasi dan baris baru serta mendeteksi perubahan berikutnya', function () {
    $project = Project::factory()->withOutline()->create();
    $text = "  Paragraf.\n\n";
    $this->actingAs($project->user)->putJson("/projects/{$project->id}/draft", ['draft' => ['s1' => $text], 'base' => ['s1' => '']])
        ->assertOk()->assertJsonPath('draft.s1', $text);
    $this->putJson("/projects/{$project->id}/draft", ['draft' => ['s1' => $text.'Lanjutan.'], 'base' => ['s1' => $text]])->assertOk();
    expect($project->fresh()->draft['s1'])->toBe($text.'Lanjutan.');
});

it('meminta AI memperbaiki teks tanpa sitasi sebelum mengembalikan draf bersumber', function () {
    $id = $this->reference->id;
    Http::fake(['ai.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['text' => 'Klaim tanpa sitasi.', 'limitations' => ''])]]]])
        ->push(['choices' => [['message' => ['content' => json_encode(['text' => "Klaim berdasarkan catatan [@{$id}].", 'limitations' => ''])]]]])]);
    $this->actingAs($this->project->user)->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's2', 'references' => [$id]])
        ->assertOk()->assertJsonPath('text', "Klaim berdasarkan catatan [@{$id}].");
    Http::assertSentCount(2);
});

it('tidak menerima teks tanpa sitasi atau keterbatasan ketika catatan sumber dipilih', function () {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'Klaim tanpa sitasi.', 'limitations' => ''])]]]])]);
    $this->actingAs($this->project->user)->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's2', 'references' => [$this->reference->id]])
        ->assertStatus(502);
    expect($this->project->fresh()->draft)->toBe(['s1' => 'Teks tersimpan.']);
});

it('menempatkan sitasi AI sebelum titik dan mempertahankan sumber tiap kalimat', function () {
    $id = $this->reference->id;
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'text' => "Temuan dari catatan. [@{$id}] Kalimat berikutnya [@{$id}].\n\nRencana penelitian sendiri.",
        'limitations' => '',
    ])]]]])]);
    $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's2', 'references' => [$id]])
        ->assertOk()->assertJsonPath('text', "Temuan dari catatan [@{$id}]. Kalimat berikutnya [@{$id}].\n\nRencana penelitian sendiri.");
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Setiap kalimat yang memparafrasekan'));
});

it('menolak generasi dari metadata saja dan menjelaskan kebutuhan catatan sumber', function () {
    $this->reference->update(['notes' => null]);
    $reader = Mockery::mock(ArticleReader::class);
    $reader->shouldReceive('read')->once()->andThrow(new AiException('Isi artikel tidak berhasil dibaca.'));
    $this->app->instance(ArticleReader::class, $reader);
    Http::fake();
    $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's2', 'references' => [$this->reference->id]])
        ->assertStatus(502)->assertJsonPath('message', 'Referensi “'.$this->reference->title.'”: Isi artikel tidak berhasil dibaca.');
    Http::assertNothingSent();
});

it('tidak membiarkan pesan keterbatasan meloloskan draf tanpa sitasi sumber', function () {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'text' => 'Uraian umum tanpa sumber.', 'limitations' => 'Catatan kurang relevan.',
    ])]]]])]);
    $this->actingAs($this->project->user)
        ->postJson("/projects/{$this->project->id}/draft/generate", ['unit' => 's2', 'references' => [$this->reference->id]])
        ->assertStatus(502);
    expect($this->project->fresh()->draft)->toBe(['s1' => 'Teks tersimpan.']);
});
