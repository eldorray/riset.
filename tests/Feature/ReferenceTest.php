<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Reference;

function manualReference(array $overrides = []): array
{
    return array_replace_recursive([
        'title' => 'Literasi digital dan kemandirian belajar mahasiswa',
        'source_url' => 'https://doi.org/10.0000/contoh.001',
        'notes' => 'Membahas hubungan literasi digital dengan pengaturan waktu belajar.',
        'metadata' => [
            'type' => 'article',
            'authors' => ['Santoso, Budi', '', 'Lestari, Dewi'],
            'year' => '2024',
            'publication' => 'Jurnal Contoh',
            'volume' => '',
        ],
    ], $overrides);
}

it('menyimpan referensi manual dengan asal entri yang jelas', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)
        ->post("/projects/{$project->id}/references", manualReference())
        ->assertSessionHasNoErrors();

    $reference = $project->references()->firstOrFail();
    expect($reference->input_method)->toBe('manual')
        ->and($reference->authors())->toBe(['Santoso, Budi', 'Lestari, Dewi'])
        ->and($reference->metadata)->not->toHaveKey('volume');

    $this->get("/projects/{$project->id}/references")->assertInertia(fn ($page) => $page
        ->component('projects/References')
        ->where('references.0.input_method', 'manual')
        ->where('references.0.in_text', 'Santoso & Lestari, 2024')
        ->where('references.0.missing', []));
});

it('menolak tautan asal yang tidak valid dan judul kosong', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)
        ->post("/projects/{$project->id}/references", manualReference(['title' => '', 'source_url' => 'bukan-url']))
        ->assertSessionHasErrors(['title', 'source_url']);

    $this->post("/projects/{$project->id}/references", manualReference(['source_url' => 'javascript:alert(1)']))
        ->assertSessionHasErrors('source_url');

    expect($project->references()->count())->toBe(0);
});

it('menandai metadata yang perlu dilengkapi', function () {
    $project = Project::factory()->create();
    Reference::factory()->for($project)->incomplete()->create();

    $this->actingAs($project->user)
        ->get("/projects/{$project->id}/references")
        ->assertInertia(fn ($page) => $page
            ->where('references.0.missing', ['Penulis', 'Tahun'])
            ->where('references.0.in_text', null));
});

it('tidak bisa mengubah referensi dari proyek lain lewat URL proyek sendiri', function () {
    $project = Project::factory()->create();
    $foreign = Reference::factory()->create();

    $this->actingAs($project->user)
        ->put("/projects/{$project->id}/references/{$foreign->id}", manualReference())
        ->assertNotFound();
});

it('menolak referensi duplikat berdasarkan judul tautan atau DOI di proyek yang sama', function () {
    $project = Project::factory()->create();
    $this->actingAs($project->user)->post("/projects/{$project->id}/references", manualReference())->assertSessionHasNoErrors();
    foreach ([
        ['title' => 'LITERASI DIGITAL DAN KEMANDIRIAN BELAJAR MAHASISWA!', 'source_url' => 'https://example.test/other'],
        ['title' => 'Judul lain'],
        ['title' => 'Judul berbeda', 'source_url' => 'https://example.test/doi', 'metadata' => ['doi' => 'https://doi.org/10.0000/contoh.001']],
    ] as $override) {
        $this->post("/projects/{$project->id}/references", manualReference($override))
            ->assertSessionHasErrors(['title' => 'Referensi sudah ada.']);
    }
    expect($project->references()->count())->toBe(1);
    $reference = $project->references()->firstOrFail();
    $this->put("/projects/{$project->id}/references/{$reference->id}", manualReference())->assertSessionHasNoErrors();
    $other = Project::factory()->for($project->user)->create();
    $this->post("/projects/{$other->id}/references", manualReference())->assertSessionHasNoErrors();
});

it('menyimpan kata kunci kategori referensi tanpa duplikat', function () {
    $project = Project::factory()->withOutline()->create();
    $this->actingAs($project->user)->post("/projects/{$project->id}/references", manualReference([
        'metadata' => ['keywords' => ['Literasi Digital', ' literasi digital ', '', 'Belajar']],
    ]))->assertSessionHasNoErrors();
    expect($project->references()->firstOrFail()->metadata['keywords'])->toBe(['literasi digital', 'belajar']);
    $this->get("/projects/{$project->id}/draft")->assertInertia(fn ($page) => $page
        ->where('references.0.keywords', ['literasi digital', 'belajar']));
});

it('menolak perubahan menjadi referensi lain yang sudah ada serta kata kunci tidak valid', function () {
    $project = Project::factory()->create();
    $a = Reference::factory()->for($project)->create();
    $b = Reference::factory()->for($project)->create(['title' => 'Sumber kedua', 'source_url' => 'https://example.test/b', 'metadata' => ['type' => 'web']]);
    $this->actingAs($project->user)->put("/projects/{$project->id}/references/{$b->id}", manualReference(['title' => $a->title]))
        ->assertSessionHasErrors(['title' => 'Referensi sudah ada.']);
    expect($b->fresh()->title)->toBe('Sumber kedua');
    $this->postJson("/projects/{$project->id}/references", manualReference(['metadata' => ['keywords' => [['invalid']]]]))
        ->assertJsonValidationErrors('metadata.keywords.0');
});
