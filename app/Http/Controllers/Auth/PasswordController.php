<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('auth/Password', ['mode' => 'change', 'hasPassword' => $request->user()?->password !== null]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate([
            'current_password' => $user->password !== null ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', PasswordRule::min(8), 'max:255', 'confirmed'],
        ]);
        $user->password = $data['password'];
        $user->remember_token = Str::random(60);
        $user->save();
        $request->session()->regenerate();
        Inertia::flash('success', 'Password diperbarui.');

        return back();
    }

    public function forgot(): Response
    {
        return Inertia::render('auth/Password', ['mode' => 'forgot']);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        try {
            Password::sendResetLink($data);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'Email reset gagal dikirim. Coba lagi nanti atau hubungi admin.']);
        }
        Inertia::flash('success', 'Jika email terdaftar, tautan reset password dikirim. Periksa kotak masuk Anda.');

        return back();
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('auth/Password', ['mode' => 'reset', 'token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::min(8), 'max:255', 'confirmed'],
        ]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->password = $password;
            $user->remember_token = Str::random(60);
            $user->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            return back()->withErrors(['email' => 'Tautan reset tidak valid atau kedaluwarsa. Minta tautan baru.']);
        }
        Inertia::flash('success', 'Password direset. Silakan masuk dengan password baru.');

        return to_route('login');
    }
}
