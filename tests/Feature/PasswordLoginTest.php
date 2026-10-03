<?php

declare(strict_types=1);

use App\Models\User;

function manualUser(string $role = 'pengguna'): User
{
    $user = User::factory()->create(['google_id' => null, 'email' => "{$role}@contoh.ac.id"]);
    $user->password = 'rahasia-uji-123';
    $user->role = $role;
    $user->save();

    return $user;
}

it('memasukkan pengguna ke daftar proyek dan admin ke panel admin', function (string $role, string $target) {
    $user = manualUser($role);

    $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-uji-123'])->assertRedirect($target);
    $this->assertAuthenticatedAs($user);
})->with([
    ['pengguna', '/projects'],
    ['admin', '/admin'],
]);

it('menolak password salah dan akun Google tanpa password', function () {
    $user = manualUser();
    $google = User::factory()->create();

    $this->post('/masuk', ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors(['email' => 'Email atau password salah.']);
    $this->post('/masuk', ['email' => $google->email, 'password' => 'apa-saja'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('membatasi percobaan masuk berulang', function () {
    $user = manualUser();

    foreach (range(1, 5) as $_) {
        $this->post('/masuk', ['email' => $user->email, 'password' => 'salah']);
    }

    $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-uji-123'])
        ->assertInvalid(['email' => 'Terlalu banyak percobaan']);
    $this->assertGuest();
});
