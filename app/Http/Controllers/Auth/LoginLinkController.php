<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ponytail: tautan masuk bertanda tangan & berumur pendek, hanya dibuat lewat
 * `php artisan riset:login-link` (butuh akses server). Untuk uji lokal tanpa kredensial Google.
 */
final class LoginLinkController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('projects.index');
    }
}
