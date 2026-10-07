<?php

declare(strict_types=1);

use App\Jobs\WriteProjectStep;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use App\Models\WritingRun;
use App\Writing\Writing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    User::created(function (User $user): void {
        $user->unlimited = true;
        $user->save();
    });
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
    $this->project = Project::factory()->create(['outline' => [
        ['id' => 'c1', 'title' => 'Pendahuluan', 'sections' => [['id' => 's1', 'title' => 'Latar Belakang']]],
        ['id' => 'c2', 'title' => 'Metode Penelitian', 'sections' => [['id' => 'm1', 'title' => 'Teknik Analisis Data']]],
        ['id' => 'c3', 'title' => 'Hasil dan Pembahasan', 'sections' => [['id' => 'h1', 'title' => 'Hasil Penelitian']]],
    ]]);
    $this->design = ['masalah' => 'Bagaimana literasi digital memengaruhi belajar mandiri?', 'pendekatan' => 'kuantitatif', 'analisis' => 'Regresi linear sederhana'];
    $this->url = "/projects/{$this->project->id}";
});

function aiJson(array $json): void
{
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($json)]]]])]);
}

it('menyimpan rancangan, data, dan judul hanya untuk pemilik proyek', function () {
    $payload = ['title' => 'Judul revisi', 'design' => [...$this->design, 'hipotesis' => '  ', 'tidak_dikenal' => 'x'], 'research_data' => ' Skor rata-rata 78,5. '];
    $this->actingAs(User::factory()->create())->put("{$this->url}/rancangan", $payload)->assertForbidden();
    $this->actingAs($this->project->user)->put("{$this->url}/rancangan", [...$payload, 'design' => ['pendekatan' => 'tebakan']])->assertSessionHasErrors('design.pendekatan');
    $this->put("{$this->url}/rancangan", $payload)->assertSessionHasNoErrors();

    $project = $this->project->fresh();
    expect($project->title)->toBe('Judul revisi')
        ->and($project->research_design)->toEqual($this->design)
        ->and($project->research_data)->toBe('Skor rata-rata 78,5.')
        ->and($project->designReady())->toBeTrue();
    $this->get("{$this->url}/rancangan")->assertInertia(fn ($page) => $page->component('projects/Design')
        ->where('empiricalUnits', ['3.1 Hasil Penelitian'])->where('project.design_ready', true));
});

it('tidak menulis bab metode tanpa rancangan atau bab hasil tanpa data penelitian', function () {
    Http::fake();
    $this->actingAs($this->project->user);
    $this->postJson("{$this->url}/draft/generate", ['unit' => 'm1', 'references' => []])
        ->assertStatus(502)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'rancangan penelitian'));
    $this->postJson("{$this->url}/draft/generate", ['unit' => 'h1', 'references' => []])
        ->assertStatus(502)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'data atau temuan penelitian'));
    Queue::fake();
    $this->postJson("{$this->url}/writing", ['kind' => 'draft', 'unit' => 'h1', 'references' => []])->assertStatus(422);
    Http::assertNothingSent();
});

it('menulis hasil hanya dari data pengguna dan menandai angka di luar data', function () {
    $this->project->update(['research_design' => $this->design, 'research_data' => 'Skor rata-rata literasi 78,5 dari 120 mahasiswa.']);
    aiJson(['text' => 'Skor rata-rata literasi 78,5 dari 120 mahasiswa, naik 15% dibanding tahun lalu.', 'evidence' => [], 'limitations' => '']);

    $response = $this->actingAs($this->project->user)->postJson("{$this->url}/draft/generate", ['unit' => 'h1', 'references' => []])->assertOk();

    expect($response->json('limitations'))->toContain('tidak ditemukan di data penelitian', '15%')
        ->not->toContain('78,5');
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Skor rata-rata literasi 78,5')
        && str_contains($request['messages'][1]['content'], 'Regresi linear sederhana'));
});

it('mengizinkan bab hasil tanpa data untuk studi literatur', function () {
    $reference = Reference::factory()->for($this->project)->create(['notes' => 'Literasi digital meningkatkan belajar mandiri.']);
    $this->project->update(['research_design' => [...$this->design, 'pendekatan' => 'studi_literatur']]);
    aiJson(['text' => "Sintesis menunjukkan literasi digital mendukung belajar mandiri [@{$reference->id}].", 'evidence' => [['id' => $reference->id, 'quote' => 'Literasi digital meningkatkan belajar mandiri.']], 'limitations' => '']);

    $this->actingAs($this->project->user)->postJson("{$this->url}/draft/generate", ['unit' => 'h1', 'references' => [$reference->id]])
        ->assertOk()->assertJsonPath('evidence.0.id', $reference->id)->assertJsonPath('limitations', '');
});

it('melewati bagian yang butuh data saat menulis naskah lengkap dan melaporkannya', function () {
    Queue::fake();
    $this->project->update(['research_design' => $this->design]);
    $response = $this->actingAs($this->project->user)
        ->postJson("{$this->url}/writing", ['kind' => 'manuscript', 'references' => [], 'mode' => 'fill', 'target_words' => 1000])
        ->assertAccepted();

    expect(array_column($response->json('run.results'), 'key'))->toBe([])
        ->and($response->json('run.skipped'))->toBe(['3.1 Hasil Penelitian'])
        ->and(array_column(WritingRun::query()->findOrFail($response->json('run.id'))->payload['steps'], 'key'))->toBe(['s1', 'm1', 'abstrak', 'kata_pengantar']);
});

it('tidak memakai catatan AI yang belum ditinjau sampai pengguna menandainya', function () {
    $notes = Reference::AI_NOTES_PENDING."\nDasar: Teks lengkap\n\nLiterasi digital meningkatkan belajar mandiri.";
    $reference = Reference::factory()->for($this->project)->create(['notes' => $notes]);
    expect($reference->notesUsable())->toBeFalse();

    $this->actingAs($this->project->user)->put("{$this->url}/references/{$reference->id}", [
        'title' => $reference->title, 'source_url' => $reference->source_url, 'notes' => $notes, 'notes_reviewed' => true,
        'metadata' => $reference->metadata,
    ])->assertSessionHasNoErrors();

    $reference->refresh();
    expect($reference->notesUsable())->toBeTrue()
        ->and($reference->notes)->toStartWith(Reference::AI_NOTES_REVIEWED)->toContain('Dasar: Teks lengkap');
});

it('menandai sitasi yang cuplikan buktinya tidak ada di catatan sumber', function () {
    $reference = Reference::factory()->for($this->project)->create(['notes' => 'Literasi digital meningkatkan belajar mandiri.']);
    aiJson(['text' => "Literasi mendukung belajar [@{$reference->id}].", 'evidence' => [['id' => $reference->id, 'quote' => 'Kalimat yang tidak ada di catatan sama sekali.']], 'limitations' => '']);

    $response = $this->actingAs($this->project->user)->postJson("{$this->url}/draft/generate", ['unit' => 's1', 'references' => [$reference->id]])->assertOk();

    expect($response->json('evidence'))->toBe([])
        ->and($response->json('limitations'))->toContain("Sitasi tanpa cuplikan catatan yang cocok — periksa kembali: [@{$reference->id}]");
});

it('menyimpan catatan diskusi judul ke rancangan proyek baru dan memakainya sebagai konteks kerangka', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/projects', ['title' => 'Literasi digital', 'document_type' => 'artikel', 'idea' => 'Masalah: mahasiswa sulit belajar mandiri.'])->assertRedirect();
    $project = $user->projects()->firstOrFail();
    expect($project->design('ide'))->toBe('Masalah: mahasiswa sulit belajar mandiri.');

    aiJson(['chapters' => [['sections' => []], ['sections' => []], ['sections' => []], ['sections' => []]]]);
    $this->postJson("/projects/{$project->id}/outline/generate")->assertOk();
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'mahasiswa sulit belajar mandiri'));
});

it('menjalankan langkah naskah tanpa menulis ulang bagian yang sudah berisi', function () {
    Queue::fake();
    $this->project->update(['research_design' => $this->design, 'research_data' => 'Data 1.', 'draft' => ['s1' => 'Sudah ada.']]);
    $id = $this->actingAs($this->project->user)
        ->postJson("{$this->url}/writing", ['kind' => 'manuscript', 'references' => [], 'mode' => 'fill', 'target_words' => 1000])
        ->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'Metode.', 'keywords' => [], 'limitations' => ''])]]]])]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));

    expect($this->project->fresh()->draft)->toBe(['s1' => 'Sudah ada.', 'm1' => 'Metode.']);
});

it('menyarankan isi hanya untuk field rancangan kosong tanpa menyimpan dan tanpa data penelitian', function () {
    aiJson(['suggestions' => [
        'tujuan' => 'Mengetahui pengaruh literasi digital terhadap belajar mandiri.',
        'pendekatan' => 'eksperimen-bebas',
        'analisis' => 'Regresi linear sederhana.',
        'masalah' => 'Tidak diminta.',
        'research_data' => 'Skor rata-rata 90.',
    ], 'notes' => 'Periksa jumlah responden.']);

    $response = $this->actingAs($this->project->user)->postJson("{$this->url}/rancangan/saran", [
        'title' => 'Judul belum disimpan', 'fields' => ['tujuan', 'pendekatan', 'analisis'], 'design' => ['masalah' => 'Bagaimana pengaruh literasi digital?', 'ide' => null],
    ])->assertOk();

    expect($response->json('suggestions'))->toBe(['tujuan' => 'Mengetahui pengaruh literasi digital terhadap belajar mandiri.', 'analisis' => 'Regresi linear sederhana.'])
        ->and($response->json('notes'))->toBe('Periksa jumlah responden.')
        ->and($this->project->fresh()->research_design)->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Judul belum disimpan')
        && str_contains($request['messages'][1]['content'], 'Bagaimana pengaruh literasi digital?'));
});

it('menolak saran AI untuk data penelitian dan untuk pengguna lain', function () {
    Http::fake();
    $payload = ['title' => 'Judul', 'fields' => ['research_data'], 'design' => []];
    $this->actingAs($this->project->user)->postJson("{$this->url}/rancangan/saran", $payload)->assertJsonValidationErrors('fields.0');
    $this->actingAs(User::factory()->create())->postJson("{$this->url}/rancangan/saran", [...$payload, 'fields' => ['tujuan']])->assertForbidden();
    Http::assertNothingSent();
});

it('mewajibkan indikator keberhasilan untuk PTK dan menyertakan alur siklus di prompt', function () {
    $ptk = [...$this->design, 'pendekatan' => 'ptk', 'analisis' => 'Deskriptif komparatif antarsiklus'];
    $this->actingAs($this->project->user)->put("{$this->url}/rancangan", ['title' => 'Judul', 'design' => $ptk, 'research_data' => ''])->assertSessionHasNoErrors();
    expect($this->project->fresh()->designReady())->toBeFalse();

    $this->project->update(['research_design' => [...$ptk, 'indikator' => 'Minimal 80% siswa mencapai KKM']]);
    aiJson(['text' => 'Penelitian dilaksanakan dalam [jumlah] siklus.', 'evidence' => [], 'limitations' => '']);
    $this->postJson("{$this->url}/draft/generate", ['unit' => 'm1', 'references' => []])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'pra-siklus')
        && str_contains($request['messages'][1]['content'], 'Minimal 80% siswa mencapai KKM'));
});
