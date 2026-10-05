<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Reference;
use App\Models\Setting;
use App\Models\User;

it('menampilkan proyek baru di daftar milik pengguna', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/projects', ['title' => 'Literasi digital mahasiswa', 'document_type' => 'skripsi']);

    $project = Project::query()->firstOrFail();
    $response->assertRedirect("/projects/{$project->id}");

    $this->get('/projects')->assertInertia(fn ($page) => $page
        ->component('projects/Index')
        ->has('projects', 1)
        ->where('projects.0.title', 'Literasi digital mahasiswa')
        ->where('projects.0.document_type.label', 'Skripsi'));
});

it('menolak judul kosong atau hanya spasi dan jenis yang belum dipilih', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', ['title' => '   ', 'document_type' => ''])
        ->assertSessionHasErrors(['title', 'document_type']);

    expect(Project::query()->count())->toBe(0);
});

it('menyimpan judul baru dan gaya sitasi yang didukung', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)
        ->patch("/projects/{$project->id}", ['title' => 'Judul baru', 'citation_style' => 'apa7'])
        ->assertSessionHasNoErrors();

    $this->get("/projects/{$project->id}")->assertInertia(fn ($page) => $page
        ->component('projects/Show')
        ->where('project.title', 'Judul baru')
        ->where('project.citation_style.value', 'apa7'));
});

it('menolak gaya sitasi yang tidak ada atau dinonaktifkan admin', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)
        ->patch("/projects/{$project->id}", ['citation_style' => 'vancouver'])
        ->assertSessionHasErrors('citation_style');

    Setting::write('citation_styles.enabled', ['apa7']);

    $this->patch("/projects/{$project->id}", ['citation_style' => 'ieee'])->assertSessionHasErrors('citation_style');
    $this->patch("/projects/{$project->id}", ['citation_style' => 'apa7'])->assertSessionHasNoErrors();
});

it('menolak akses ke proyek milik pengguna lain, termasuk admin', function (string $method, string $path) {
    $project = Project::factory()->withOutline()->create();
    $reference = Reference::factory()->for($project)->create();
    $other = User::factory()->create(['role' => 'admin']);

    $this->actingAs($other)
        ->json($method, str_replace(['{p}', '{r}'], [$project->id, $reference->id], $path), [])
        ->assertForbidden();
})->with([
    ['GET', '/projects/{p}'],
    ['PATCH', '/projects/{p}'],
    ['GET', '/projects/{p}/references'],
    ['POST', '/projects/{p}/references'],
    ['PUT', '/projects/{p}/references/{r}'],
    ['GET', '/projects/{p}/citations'],
    ['GET', '/projects/{p}/outline'],
    ['PUT', '/projects/{p}/outline'],
    ['POST', '/projects/{p}/outline/generate'],
    ['GET', '/projects/{p}/draft'],
    ['PUT', '/projects/{p}/draft'],
    ['POST', '/projects/{p}/draft/generate'],
    ['POST', '/projects/{p}/export/docx'],
]);

it('tidak menampilkan proyek orang lain di daftar', function () {
    Project::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/projects')
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

it('menawarkan langkah berikutnya ke bagian kosong pertama', function () {
    $project = Project::factory()->withOutline()->create(['citation_style' => 'apa7', 'draft' => ['s1' => 'Sudah ditulis.']]);
    Reference::factory()->for($project)->create(['notes' => 'Catatan isi sumber.']);
    $this->actingAs($project->user)->get("/projects/{$project->id}")
        ->assertInertia(fn ($page) => $page->where('readiness.next.label', 'Isi rancangan penelitian'));
    $project->update(['research_design' => ['masalah' => 'Bagaimana literasi memengaruhi belajar?', 'pendekatan' => 'kuantitatif', 'analisis' => 'Regresi']]);
    $this->get("/projects/{$project->id}")
        ->assertInertia(fn ($page) => $page->where('readiness.next.label', 'Lanjutkan draf')
            ->where('readiness.next.href', route('projects.draft', ['project' => $project, 'unit' => 's2'])));
    $this->get("/projects/{$project->id}/draft?unit=s2")->assertInertia(fn ($page) => $page->where('selectedUnit', 's2'));
});
