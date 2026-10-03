<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Reference;

beforeEach(function () {
    $this->project = Project::factory()->withOutline()->create(['title' => 'Literasi Digital & Mahasiswa', 'citation_style' => 'apa7']);
    $this->complete = Reference::factory()->for($this->project)->create();
    $this->incomplete = Reference::factory()->for($this->project)->incomplete()->create();
    Reference::factory()->for($this->project)->create(['title' => 'Tidak dirujuk']);

    $this->project->update(['draft' => [
        's1' => "Paragraf pertama [@{$this->complete->id}].\n\nParagraf kedua [@{$this->incomplete->id}].",
    ]]);
});

it('menyusun sitasi dan daftar referensi hanya dari yang dirujuk dan lengkap', function () {
    $this->actingAs($this->project->user)
        ->get("/projects/{$this->project->id}/citations")
        ->assertInertia(fn ($page) => $page
            ->component('projects/Citations')
            ->has('citations', 2)
            ->where('citations.0.text', '(Santoso & Lestari, 2024)')
            ->where('citations.1.text', null)
            ->has('bibliography', 1)
            ->where('bibliography.0.id', $this->complete->id)
            ->where('incomplete.0.missing', ['Penulis', 'Tahun']));
});

it('memperingatkan bagian kosong dan sitasi belum lengkap sebelum ekspor', function () {
    $this->actingAs($this->project->user)
        ->get("/projects/{$this->project->id}")
        ->assertInertia(fn ($page) => $page
            ->where('readiness.units', 3)
            ->where('readiness.filled', 1)
            ->where('readiness.empty', ['1.2 Rumusan Masalah', 'BAB II Penutup'])
            ->where('readiness.citations', 2)
            ->where('readiness.cited_incomplete.0.id', $this->incomplete->id));
});

it('mengekspor .docx dengan urutan kerangka, sitasi, dan daftar pustaka', function () {
    $response = $this->actingAs($this->project->user)->post("/projects/{$this->project->id}/export/docx");

    $response->assertOk()->assertDownload('literasi-digital-mahasiswa.docx');

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $xml = strip_tags((string) $zip->getFromName('word/document.xml'));
    $zip->close();

    expect($xml)->toContain('Literasi Digital &amp; Mahasiswa')
        ->toContain('BAB I PENDAHULUAN')
        ->toContain('Paragraf pertama (Santoso &amp; Lestari, 2024).')
        ->toContain('[sitasi belum lengkap: Laporan survei penggunaan perpustakaan digital]')
        ->toContain('DAFTAR PUSTAKA')
        ->toContain('Santoso, B. A., &amp; Lestari, D. (2024).')
        ->not->toContain('Tidak dirujuk')
        ->and(strpos($xml, '1.1 Latar Belakang'))->toBeLessThan(strpos($xml, '1.2 Rumusan Masalah'));
});

it('tidak mengekspor proyek tanpa kerangka', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)
        ->post("/projects/{$project->id}/export/docx")
        ->assertRedirect()
        ->assertInertiaFlash('error');
});

it('menampilkan semua referensi sebagai sitasi tersedia meskipun belum dipakai di draf', function () {
    $this->actingAs($this->project->user)->get("/projects/{$this->project->id}/citations")
        ->assertInertia(fn ($page) => $page->has('available', 3)
            ->where('available.0.id', $this->complete->id)
            ->where('available.0.text', '(Santoso & Lestari, 2024)')
            ->where('available.2.used', false));
});
