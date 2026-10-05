<?php

declare(strict_types=1);

use App\Ai\AiClient;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    User::created(function (User $user): void {
        $user->unlimited = true;
        $user->save();
    });
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'model-uji', 'timeout' => 5, 'json_mode' => true]]);
});

function aiSays(array $json): void
{
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($json)]]]])]);
}

it('menampilkan bagian awal sesuai jenis tulisan', function (string $type, array $keys) {
    $project = Project::factory()->withOutline()->create(['document_type' => $type]);

    $this->actingAs($project->user)->get("/projects/{$project->id}/manuscript")
        ->assertInertia(fn ($page) => $page
            ->component('projects/Manuscript')
            ->where('parts', fn ($parts) => collect($parts)->pluck('key')->all() === $keys));
})->with([
    ['skripsi', ['abstrak', 'kata_pengantar']],
    ['tesis', ['abstrak', 'abstract', 'kata_pengantar']],
    ['artikel', ['abstrak', 'abstract']],
    ['karya_ilmiah', ['abstrak']],
]);

it('menyusun abstrak dari draf tanpa menyimpan dan tanpa sitasi', function () {
    $project = Project::factory()->withOutline()->create();
    $reference = Reference::factory()->for($project)->create(['notes' => 'Ringkasan isi sumber untuk mendukung teks parafrase.']);
    $project->update(['draft' => ['s1' => "Latar belakang penelitian [@{$reference->id}]."]]);
    aiSays(['text' => 'Penelitian ini meringkas [@1] temuan.', 'keywords' => ['literasi digital', 'mahasiswa'], 'limitations' => 'Draf belum memuat hasil.']);

    $this->actingAs($project->user)
        ->postJson("/projects/{$project->id}/manuscript/generate", ['part' => 'abstrak'])
        ->assertOk()
        ->assertJson(['text' => 'Penelitian ini meringkas temuan.', 'keywords' => 'literasi digital, mahasiswa', 'limitations' => 'Draf belum memuat hasil.']);

    expect($project->fresh()->front_matter)->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], '(Santoso & Lestari, 2024)'));
});

it('menolak menyusun abstrak saat draf kosong dan abstract sebelum abstrak ada', function () {
    $project = Project::factory()->withOutline()->create(['document_type' => 'tesis']);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/generate", ['part' => 'abstrak'])
        ->assertStatus(502)->assertJsonPath('message', 'Draf masih kosong. Tulis draf lebih dulu sebelum menyusun abstrak.');

    $this->postJson("/projects/{$project->id}/manuscript/generate", ['part' => 'abstract'])
        ->assertStatus(502)->assertJsonPath('message', 'Isi dan simpan abstrak bahasa Indonesia lebih dulu.');
});

it('menolak bagian yang tidak dipakai jenis tulisan', function () {
    $project = Project::factory()->create(['document_type' => 'skripsi']);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/generate", ['part' => 'abstract'])->assertJsonValidationErrors('part');
    $this->put("/projects/{$project->id}/manuscript", ['parts' => ['abstract' => ['text' => 'x']]])->assertSessionHasErrors('parts');
});

it('menyimpan bagian awal dan memasukkannya ke naskah .docx', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Isi bab.']]);

    $this->actingAs($project->user)->put("/projects/{$project->id}/manuscript", ['parts' => [
        'abstrak' => ['text' => 'Ringkasan penelitian.', 'keywords' => 'literasi, mahasiswa'],
        'kata_pengantar' => ['text' => 'Terima kasih kepada [Nama Dosen Pembimbing].', 'keywords' => ''],
    ]])->assertSessionHasNoErrors();

    expect($project->fresh()->front_matter)->toBe([
        'abstrak' => ['text' => 'Ringkasan penelitian.', 'keywords' => 'literasi, mahasiswa'],
        'kata_pengantar' => ['text' => 'Terima kasih kepada [Nama Dosen Pembimbing].'],
    ]);

    $response = $this->post("/projects/{$project->id}/export/docx")->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $text = strip_tags((string) $zip->getFromName('word/document.xml'));
    $zip->close();

    expect($text)->toContain('ABSTRAK')
        ->toContain('Kata kunci: literasi, mahasiswa')
        ->toContain('KATA PENGANTAR')
        ->and(strpos($text, 'ABSTRAK'))->toBeLessThan(strpos($text, 'BAB I'));
});

it('menandai bagian awal yang belum diisi di ringkasan proyek', function () {
    $project = Project::factory()->withOutline()->create(['front_matter' => ['abstrak' => ['text' => 'Ada.']]]);

    $this->actingAs($project->user)->get("/projects/{$project->id}")
        ->assertInertia(fn ($page) => $page
            ->where('readiness.front_parts', 2)
            ->where('readiness.front_missing', ['Kata pengantar'])
            ->where('project.front_filled', 1));
});

it('menyiapkan kerangka otomatis bila belum ada', function () {
    $project = Project::factory()->create(['document_type' => 'artikel']);
    aiSays(['chapters' => array_fill(0, 4, ['sections' => []])]);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/prepare")
        ->assertOk()
        ->assertJsonPath('generated_outline', true)
        ->assertJsonCount(4, 'units');

    expect($project->fresh()->outline)->toHaveCount(4);
});

it('menulis dan menyimpan bagian kosong dengan target kata dan parafrase', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Sudah ada.']]);
    $reference = Reference::factory()->for($project)->create(['notes' => 'Ringkasan isi sumber untuk mendukung teks parafrase.']);
    aiSays(['text' => "Teks parafrase [@{$reference->id}] tentang topik.", 'limitations' => '']);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's2', 'references' => [$reference->id], 'target_words' => 800, 'mode' => 'fill',
    ])->assertOk()->assertJson(['saved' => true, 'words' => 4]);

    $this->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's1', 'references' => [], 'target_words' => 800, 'mode' => 'fill',
    ])->assertOk()->assertJson(['skipped' => true]);

    $fresh = $project->fresh();
    expect($fresh->draft)->toBe(['s1' => 'Sudah ada.', 's2' => "Teks parafrase [@{$reference->id}] tentang topik."])
        ->and($fresh->ai_units)->toBe(['s2']);

    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'sekitar 800 kata')
        && str_contains($request['messages'][1]['content'], 'parafrase'));
});

it('tidak menyediakan mode tulis ulang massal dengan parafrase', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Teks lama.']]);
    Http::fake();

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's1', 'references' => [], 'target_words' => 300, 'mode' => 'rewrite',
    ])->assertJsonValidationErrors('mode');
    $this->postJson("/projects/{$project->id}/writing", ['kind' => 'manuscript', 'references' => [], 'mode' => 'rewrite', 'target_words' => 1000])
        ->assertJsonValidationErrors('mode');

    expect($project->fresh()->draft['s1'])->toBe('Teks lama.');
    Http::assertNothingSent();
});

it('tidak menyimpan apa pun bila penulisan bagian gagal', function () {
    $project = Project::factory()->withOutline()->create();
    Http::fake(['ai.test/*' => Http::response('down', 500)]);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's1', 'references' => [], 'target_words' => 300, 'mode' => 'fill',
    ])->assertStatus(502);

    expect($project->fresh())->draft->toBeNull()->ai_units->toBeNull();
});

it('menyimpan bagian awal hasil AI dengan tanda AI sampai disimpan ulang', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Isi.']]);
    aiSays(['text' => 'Abstrak otomatis.', 'keywords' => ['a', 'b']]);

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/generate", ['part' => 'abstrak', 'save' => 1])->assertOk();
    expect($project->fresh()->front_matter['abstrak'])->toBe(['text' => 'Abstrak otomatis.', 'keywords' => 'a, b', 'ai' => true]);

    $this->put("/projects/{$project->id}/manuscript", ['parts' => ['abstrak' => ['text' => 'Abstrak otomatis.', 'keywords' => 'a, b']]]);
    expect($project->fresh()->front_matter['abstrak'])->not->toHaveKey('ai');
});

it('menghapus tanda AI saat bagian disunting atau ditandai diperiksa', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'AI satu.', 's2' => 'AI dua.'], 'ai_units' => ['s1', 's2']]);

    $this->actingAs($project->user)->put("/projects/{$project->id}/draft", [
        'draft' => ['s1' => 'Disunting pengguna.', 's2' => 'AI dua.'],
    ])->assertSessionHasNoErrors();
    expect($project->fresh()->ai_units)->toBe(['s2']);

    $this->put("/projects/{$project->id}/draft", ['draft' => ['s2' => 'AI dua.'], 'reviewed' => ['s2']])->assertSessionHasNoErrors();
    expect($project->fresh()->ai_units)->toBe([]);
});

it('memperpanjang batas waktu PHP selama menunggu AI agar server tidak mati', function () {
    // php -S (artisan serve) dan banyak hosting memakai max_execution_time 30 detik.
    $original = ini_get('max_execution_time');
    ini_set('max_execution_time', '30');

    try {
        aiSays(['text' => 'ok']);
        AiClient::fromConfig()->json('sistem', 'prompt');

        expect((int) ini_get('max_execution_time'))->toBe(5 + 30);
    } finally {
        set_time_limit((int) $original);
    }
});

it('tidak menimpa edit yang disimpan saat AI menulis bagian', function () {
    $project = Project::factory()->withOutline()->create();
    Http::fake(function () use ($project) {
        $project->update(['draft' => ['s1' => 'Edit dari tab lain.']]);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'Hasil AI.'])]]]]);
    });

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's1', 'references' => [], 'target_words' => 300, 'mode' => 'fill',
    ])->assertStatus(409);

    expect($project->fresh()->draft['s1'])->toBe('Edit dari tab lain.');
});

it('tidak menimpa bagian awal yang disimpan saat AI berjalan', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Isi.']]);
    Http::fake(function () use ($project) {
        $project->update(['front_matter' => ['abstrak' => ['text' => 'Edit abstrak.']]]);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'Abstrak AI.'])]]]]);
    });

    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/generate", ['part' => 'abstrak', 'save' => 1])
        ->assertStatus(409);
    expect($project->fresh()->frontText('abstrak'))->toBe('Edit abstrak.');
});

it('menolak hasil AI jika referensi dihapus selama generasi', function () {
    $project = Project::factory()->withOutline()->create();
    $reference = Reference::factory()->for($project)->create(['notes' => 'Ringkasan isi sumber untuk mendukung teks parafrase.']);
    Http::fake(function () use ($reference) {
        $reference->delete();

        return Http::response(['choices' => [['message' => ['content' => json_encode(['text' => "Hasil [@{$reference->id}]."])]]]]);
    });
    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's1', 'references' => [$reference->id], 'target_words' => 300, 'mode' => 'fill',
    ])->assertStatus(409);
    expect($project->fresh()->draft)->toBeNull();
});

it('menolak hasil AI jika bagian dihapus dari kerangka selama generasi', function () {
    $project = Project::factory()->withOutline()->create();
    Http::fake(function () use ($project) {
        $project->update(['outline' => []]);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'Hasil.'])]]]]);
    });
    $this->actingAs($project->user)->postJson("/projects/{$project->id}/manuscript/section", [
        'unit' => 's1', 'references' => [], 'target_words' => 300, 'mode' => 'fill',
    ])->assertStatus(409);
    expect($project->fresh()->draft)->toBeNull();
});
