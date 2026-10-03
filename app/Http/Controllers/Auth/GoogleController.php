<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * F-01: masuk hanya dengan Google. Akun dibuat atau ditemukan saat callback diverifikasi server.
 */
final class GoogleController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('auth/Login', [
            'googleConfigured' => filled(config('services.google.client_id')),
        ]);
    }

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Inertia::flash('error', 'Masuk dibatalkan. Silakan coba lagi.');

            return to_route('login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);
            Inertia::flash('error', 'Masuk dengan Google gagal. Silakan coba lagi.');

            return to_route('login');
        }

        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->where('email', $google->getEmail())->first()
            ?? new User;

        $user->fill([
            'google_id' => (string) $google->getId(),
            'name' => $google->getName() ?: (string) $google->getEmail(),
            'email' => (string) $google->getEmail(),
            'avatar' => $google->getAvatar(),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('projects.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
