<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

it('meminta tamu masuk sebelum membuka proyek', function () {
    $this->get('/projects')->assertRedirect('/masuk');
    $this->get('/masuk')->assertOk()->assertInertia(fn ($page) => $page->component('auth/Login'));
});

it('membuat akun dari callback Google lalu membuka daftar proyek', function () {
    Socialite::fake('google', GoogleUser::fake(['id' => 'g-1', 'name' => 'Rahma Aulia', 'email' => 'rahma@contoh.ac.id']));

    $this->get('/auth/google/callback')->assertRedirect('/projects');

    $user = User::query()->where('google_id', 'g-1')->firstOrFail();
    expect($user->email)->toBe('rahma@contoh.ac.id')->and($user->role)->toBe('pengguna');
    $this->assertAuthenticatedAs($user);
});

it('menemukan akun yang sudah ada saat masuk lagi', function () {
    $user = User::factory()->create(['google_id' => 'g-2']);
    Socialite::fake('google', GoogleUser::fake(['id' => 'g-2', 'email' => $user->email]));

    $this->get('/auth/google/callback');

    expect(User::query()->count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

it('menampilkan pesan bila masuk dibatalkan', function () {
    $this->get('/auth/google/callback?error=access_denied')
        ->assertRedirect('/masuk')
        ->assertInertiaFlash('error');

    $this->assertGuest();
});

it('menerima tautan masuk bertanda tangan dan menolak yang tidak', function () {
    $user = User::factory()->create();

    $this->get("/auth/link/{$user->id}")->assertForbidden();
    $this->get(URL::temporarySignedRoute('auth.link', now()->addMinutes(5), ['user' => $user->id]))->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user);
});

it('keluar dari sesi', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/masuk');
    $this->assertGuest();
});
