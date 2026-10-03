<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('mengunggah mengganti dan menghapus logo yang ditampilkan kepada pengunjung', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->from('/admin/branding')->post('/admin/branding', ['logo' => UploadedFile::fake()->image('logo.png')])->assertRedirect('/admin/branding')->assertSessionHasNoErrors();
    $first = Setting::read('app_logo');
    Storage::disk('local')->assertExists($first);
    $this->get('/')->assertInertia(fn ($page) => $page->where('logo_url', '/branding/logo?v='.basename($first)));
    $this->get('/')->assertSee('id="app-favicon" href="/branding/logo?v='.basename($first).'"', false);

    $this->post('/admin/branding', ['logo' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
    $second = Setting::read('app_logo');
    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
    auth()->logout();
    $this->get('/branding/logo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($admin)->delete('/admin/branding')->assertSessionHasNoErrors();
    expect(Setting::read('app_logo'))->toBeNull();
    Storage::disk('local')->assertMissing($second);
    $this->get('/branding/logo')->assertNotFound();
    $this->get('/')->assertInertia(fn ($page) => $page->where('logo_url', null));
    $this->get('/')->assertSee('id="app-favicon" href="/favicon.svg"', false);
});

it('menolak file yang bukan gambar serta logo terlalu besar tanpa mengubah logo lama', function () {
    Storage::fake('local');
    Setting::write('app_logo', 'branding/existing.png');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach ([UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.png')->size(2049), UploadedFile::fake()->image('wide.png', 4097, 1)] as $file) {
        $this->post('/admin/branding', ['logo' => $file])->assertSessionHasErrors('logo');
        expect(Setting::read('app_logo'))->toBe('branding/existing.png');
    }
});

it('membatasi pengaturan logo hanya untuk admin', function (string $method) {
    $this->json($method, '/admin/branding')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->json($method, '/admin/branding')->assertForbidden();
})->with(['GET', 'POST', 'DELETE']);
