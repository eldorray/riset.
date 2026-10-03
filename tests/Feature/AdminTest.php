<?php

declare(strict_types=1);

use App\Billing\Billing;
use App\Enums\CitationStyle;
use App\Enums\DocumentType;
use App\Models\DocxTemplate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    ['DELETE', '/admin/users/1'],
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

it('menghapus pengguna beserta proyek referensi kredit dan sesi tanpa menghapus pengguna lain', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $reference = $project->references()->create(['title' => 'Artikel', 'source_url' => 'https://example.com/article']);
    $other = Project::factory()->create();
    $billing = app(Billing::class);
    $purchase = $billing->purchase($user, DB::table('billing_plans')->value('id'));
    $billing->approve($purchase, $this->admin, 'Pembayaran diterima', true);
    DB::table('sessions')->insert(['id' => 'deleted-user-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'test-token']);

    $this->actingAs($this->admin)->from('/admin/users')->delete("/admin/users/{$user->id}")->assertRedirect('/admin/users');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseMissing('project_references', ['id' => $reference->id]);
    foreach (['billing_requests', 'credit_grants', 'credit_transactions', 'sessions'] as $table) {
        $this->assertDatabaseMissing($table, ['user_id' => $user->id]);
    }
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('projects', ['id' => $other->id]);
});

it('menolak menghapus akun admin sendiri', function () {
    $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

it('menunda penghapusan pengguna saat penulisan AI belum selesai', function (string $status) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    DB::table('writing_runs')->insert(['user_id' => $user->id, 'project_id' => $project->id, 'kind' => 'draft', 'status' => $status, 'payload' => '{}', 'results' => '[]']);

    $this->actingAs($this->admin)->from('/admin/users')->delete("/admin/users/{$user->id}")->assertRedirect('/admin/users');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
})->with(['queued', 'running']);

it('menunda penghapusan saat kredit masih direservasi untuk AI', function () {
    $user = User::factory()->create(['unlimited' => true]);
    app(Billing::class)->reserve($user, 10, 'test-model', 'Penulisan');

    $this->actingAs($this->admin)->from('/admin/users')->delete("/admin/users/{$user->id}")->assertRedirect('/admin/users');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'status' => 'reserved']);
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
