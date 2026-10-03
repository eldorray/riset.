<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

// Tautan masuk sekali pakai untuk uji lokal/darurat tanpa Google. Hanya bisa dibuat dari server.
Artisan::command('riset:login-link {email} {--create : Buat akun bila belum ada} {--name= : Nama untuk akun baru}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if ($user === null && $this->option('create')) {
        $user = User::query()->create(['email' => $email, 'name' => $this->option('name') ?: $email]);
    }

    if ($user === null) {
        $this->error("Pengguna {$email} tidak ditemukan. Tambahkan --create untuk membuatnya.");

        return 1;
    }

    $this->line(URL::temporarySignedRoute('auth.link', now()->addMinutes(15), ['user' => $user->id]));

    return 0;
})->purpose('Buat tautan masuk bertanda tangan yang berlaku 15 menit');

// Buat/perbarui akun admin dengan login manual. Password diminta tersembunyi bila --password tidak diisi.
Artisan::command('riset:admin {email} {--name= : Nama tampilan} {--password= : Password (hindari: tersimpan di riwayat shell)}', function (string $email) {
    $password = $this->option('password') ?: $this->secret('Password admin (min. 8 karakter)');

    $validator = Validator::make(['email' => $email, 'password' => $password], [
        'email' => ['required', 'email'],
        'password' => ['required', 'string', 'min:8'],
    ]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }

    $user = User::query()->firstOrNew(['email' => $email]);
    $name = $this->option('name');
    $user->name = is_string($name) && $name !== '' ? $name : ($user->name ?? $email);
    $user->password = $password;
    $user->role = 'admin';
    $user->save();

    $this->info("Akun admin {$email} siap. Masuk lewat /masuk dengan email dan password.");

    return 0;
})->purpose('Buat atau perbarui akun admin (login manual)');
