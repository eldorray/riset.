<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    User::created(function (User $user): void {
        $user->unlimited = true;
        $user->save();
    });
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => 'test-key', 'model' => 'model-uji', 'timeout' => 5, 'json_mode' => true]]);
});

function aiReplies(array $json): void
{
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($json)]]]])]);
}

it('menyusun kerangka mengikuti struktur jenis tulisan tanpa menyimpannya', function () {
    $project = Project::factory()->create(['document_type' => 'skripsi']);
    aiReplies(['chapters' => [
        ['sections' => ['Latar Belakang', 'Rumusan Masalah']],
        ['sections' => ['Landasan Teori']],
        ['sections' => ['Desain Penelitian']],
        ['sections' => ['Hasil', 'Pembahasan']],
        ['sections' => ['Kesimpulan', 'Saran']],
    ]]);

    $response = $this->actingAs($project->user)->postJson("/projects/{$project->id}/outline/generate")->assertOk();

    expect($response->json('outline'))->toHaveCount(5)
        ->and($response->json('outline.0.title'))->toBe('Pendahuluan')
        ->and($response->json('outline.3.sections.1.title'))->toBe('Pembahasan')
        ->and($project->fresh()->outline)->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://ai.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'model-uji');
});

it('menolak kerangka AI yang jumlah babnya tidak sesuai struktur', function () {
    $project = Project::factory()->create(['document_type' => 'artikel']);
    aiReplies(['chapters' => [['sections' => []]]]);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/outline/generate")->assertStatus(502);
});

it('tidak mengubah kerangka tersimpan bila generasi gagal', function () {
    $project = Project::factory()->withOutline()->create();
    $saved = $project->outline;
    Http::fake(['ai.test/*' => Http::response('down', 500)]);

    $this->actingAs($project->user)
        ->postJson("/projects/{$project->id}/outline/generate")
        ->assertStatus(502)
        ->assertJsonPath('message', 'Layanan AI menolak permintaan (HTTP 500).');

    expect($project->fresh()->outline)->toBe($saved);
});

it('memberi pesan jelas bila layanan AI belum dikonfigurasi', function () {
    config(['services.ai.base_url' => null]);
    $project = Project::factory()->create();

    $this->actingAs($project->user)
        ->postJson("/projects/{$project->id}/outline/generate")
        ->assertStatus(502)
        ->assertJsonPath('message', 'Layanan AI belum dikonfigurasi. Isi AI_BASE_URL dan AI_MODEL di .env.');
});

it('menyimpan kerangka yang diubah pengguna', function () {
    $project = Project::factory()->create();
    $outline = [
        ['id' => 'a', 'title' => 'Pendahuluan', 'sections' => [['id' => 'a1', 'title' => 'Latar Belakang']]],
        ['id' => 'b', 'title' => 'Penutup', 'sections' => []],
    ];

    $this->actingAs($project->user)->put("/projects/{$project->id}/outline", ['outline' => $outline])->assertSessionHasNoErrors();

    expect($project->fresh()->outline)->toBe($outline);
});

it('menolak kerangka dengan id ganda atau judul kosong', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)->put("/projects/{$project->id}/outline", ['outline' => [
        ['id' => 'a', 'title' => 'Bab', 'sections' => [['id' => 'a', 'title' => '']]],
    ]])->assertSessionHasErrors(['outline', 'outline.0.sections.0.title']);
});
