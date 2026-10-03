<?php

declare(strict_types=1);

use App\Models\DocxTemplate;
use App\Models\Project;
use App\Models\Reference;

function exportXml($test, Project $project): string
{
    $response = $test->actingAs($project->user)->post("/projects/{$project->id}/export/docx")->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    // Font bawaan disimpan PhpWord di styles.xml, isi dokumen di document.xml.
    $xml = $zip->getFromName('word/document.xml').$zip->getFromName('word/styles.xml');
    $zip->close();

    return $xml;
}

it('memakai template institusi pilihan proyek saat ekspor', function () {
    $template = DocxTemplate::query()->create([
        ...DocxTemplate::DEFAULTS,
        'name' => 'Universitas Contoh',
        'institution' => 'Universitas Contoh',
        'font_family' => 'Arial',
        'margin_left' => 4,
        'title_page' => true,
        'title_page_text' => "SKRIPSI\nDiajukan untuk memenuhi syarat gelar Sarjana",
        'table_of_contents' => true,
    ]);
    $project = Project::factory()->withOutline()->create(['docx_template_id' => $template->id, 'draft' => ['s1' => 'Isi.']]);

    $this->actingAs($project->user)->patch("/projects/{$project->id}", ['docx_template_id' => $template->id])->assertSessionHasNoErrors();

    $xml = exportXml($this, $project);

    expect($xml)->toContain('w:left="2268"')            // 4 cm
        ->toContain('Arial')
        ->toContain('Diajukan untuk memenuhi syarat gelar Sarjana')
        ->toContain('UNIVERSITAS CONTOH')
        ->toContain('Disusun oleh:')
        ->toContain('DAFTAR ISI')
        ->toContain('TOC \\o');
});

it('menolak template yang tidak ada', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)->patch("/projects/{$project->id}", ['docx_template_id' => 999])->assertSessionHasErrors('docx_template_id');
});

it('mengekspor IEEE dengan nomor sesuai kemunculan pertama', function () {
    $project = Project::factory()->withOutline()->create(['citation_style' => 'ieee']);
    $a = Reference::factory()->for($project)->create();
    $b = Reference::factory()->for($project)->create(['title' => 'Sumber kedua', 'metadata' => [
        'type' => 'book', 'authors' => ['Putra, Eko'], 'year' => '2022', 'publisher' => 'Penerbit Contoh',
    ]]);
    $project->update(['draft' => ['s1' => "Pertama [@{$b->id}] lalu [@{$a->id}][@{$b->id}]."]]);

    $text = strip_tags(exportXml($this, $project));

    expect($text)->toContain('Pertama [1] lalu [2], [1].')
        ->toContain('[1] E. Putra, Sumber kedua. Penerbit Contoh, 2022.')
        ->toContain('[2] B. A. Santoso and D. Lestari,')
        ->and(strpos($text, '[1] E. Putra'))->toBeLessThan(strpos($text, '[2] B. A. Santoso'));
});

it('reports the actual selected and fallback Word options before export', function () {
    $project = Project::factory()->create();
    $this->actingAs($project->user)->get("/projects/{$project->id}")->assertInertia(fn ($page) => $page
        ->where('exportFormat.table_of_contents', false)->where('exportFormat.title_page', false)->where('exportFormat.citation_style', 'APA edisi 7'));
    $template = DocxTemplate::create(['name' => 'Format uji', ...DocxTemplate::DEFAULTS, 'table_of_contents' => true, 'title_page' => true]);
    $project->update(['docx_template_id' => $template->id]);
    $this->get("/projects/{$project->id}/manuscript")->assertInertia(fn ($page) => $page
        ->where('exportFormat.table_of_contents', true)->where('exportFormat.title_page', true));
});
