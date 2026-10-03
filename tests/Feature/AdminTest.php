<?php

declare(strict_types=1);

use App\Enums\CitationStyle;
use App\Enums\DocumentType;
use App\Models\DocxTemplate;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('menolak pengguna biasa dan tamu di semua halaman admin', function (string $method, string $path) {
    $this->json($method, $path)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->json($method, $path)->assertForbidden();
})->with([
    ['GET', '/admin'],
    ['GET', '/admin/users'],
    ['POST', '/admin/users'],
    ['GET', '/admin/citation-styles'],
    ['PUT', '/admin/citation-styles'],
    ['GET', '/admin/document-types'],
    ['PUT', '/admin/document-types/skripsi'],
    ['GET', '/admin/templates'],
    ['POST', '/admin/templates'],
]);

it('menampilkan ringkasan agregat tanpa isi proyek', function () {
    Project::factory()->create(['title' => 'Judul rahasia pengguna']);

    $this->actingAs($this->admin)->get('/admin')
        ->assertOk()
        ->assertDontSee('Judul rahasia pengguna')
        ->assertInertia(fn ($page) => $page->component('admin/Dashboard')->where('stats.projects', 1)->where('stats.admins', 1));
});

it('membuat akun login manual yang bisa langsung masuk', function () {
    $this->actingAs($this->admin)
        ->post('/admin/users', ['name' => 'Dosen Uji', 'email' => 'dosen@contoh.ac.id', 'password' => 'password-uji-1', 'role' => 'pengguna'])
        ->assertSessionHasNoErrors();

    auth()->logout();

    $this->post('/masuk', ['email' => 'dosen@contoh.ac.id', 'password' => 'password-uji-1'])->assertRedirect('/projects');
});

it('menolak email ganda dan password pendek', function () {
    $this->actingAs($this->admin)
        ->post('/admin/users', ['name' => 'X', 'email' => $this->admin->email, 'password' => 'pendek', 'role' => 'pengguna'])
        ->assertSessionHasErrors(['email', 'password']);
});

it('mengubah peran pengguna tetapi tidak mencabut admin diri sendiri', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => $user->name, 'role' => 'admin'])->assertSessionHasNoErrors();
    expect($user->fresh()->role)->toBe('admin');

    $this->put("/admin/users/{$this->admin->id}", ['name' => 'Saya', 'role' => 'pengguna'])->assertSessionHasErrors('role');
    expect($this->admin->fresh()->role)->toBe('admin');
});

it('mengatur gaya sitasi yang tersedia bagi pengguna', function () {
    $this->actingAs($this->admin)->put('/admin/citation-styles', ['enabled' => ['apa7', 'ieee']])->assertSessionHasNoErrors();

    expect(array_column(CitationStyle::options(), 'value'))->toBe(['apa7', 'ieee']);

    $this->put('/admin/citation-styles', ['enabled' => []])->assertSessionHasErrors('enabled');
});

it('mengubah struktur jenis tulisan yang dipakai kerangka', function () {
    $this->actingAs($this->admin)->put('/admin/document-types/artikel', [
        'numbering' => 'angka',
        'chapters' => [
            ['title' => 'Pendahuluan', 'sections' => ['Latar belakang']],
            ['title' => 'Simpulan', 'sections' => []],
        ],
    ])->assertSessionHasNoErrors();

    expect(DocumentType::Artikel->structure())->toBe([
        ['title' => 'Pendahuluan', 'sections' => ['Latar belakang']],
        ['title' => 'Simpulan', 'sections' => []],
    ]);

    $this->delete('/admin/document-types/artikel')->assertSessionHasNoErrors();
    expect(DocumentType::Artikel->structure())->toBe(config('riset.structures.artikel.chapters'));
});

it('mengelola template Word dan melepas proyek saat template dihapus', function () {
    $payload = [...DocxTemplate::DEFAULTS, 'name' => 'Universitas Contoh', 'institution' => 'Universitas Contoh', 'margin_left' => 4, 'title_page' => true];

    $this->actingAs($this->admin)->post('/admin/templates', $payload)->assertSessionHasNoErrors();
    $template = DocxTemplate::query()->firstOrFail();
    $project = Project::factory()->create(['docx_template_id' => $template->id]);

    $this->put("/admin/templates/{$template->id}", [...$payload, 'font_size' => 11])->assertSessionHasNoErrors();
    expect($template->fresh()->font_size)->toBe(11);

    $this->put("/admin/templates/{$template->id}", [...$payload, 'margin_top' => 9])->assertSessionHasErrors('margin_top');

    $this->delete("/admin/templates/{$template->id}")->assertSessionHasNoErrors();
    expect($project->fresh()->docx_template_id)->toBeNull();
});
