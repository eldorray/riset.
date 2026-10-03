<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BrandingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Branding');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']], [
            'logo.required' => 'Pilih logo terlebih dahulu.',
            'logo.image' => 'File harus berupa gambar.',
            'logo.mimes' => 'Gunakan gambar PNG, JPG, atau WebP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'logo.dimensions' => 'Dimensi logo maksimal 4096 × 4096 piksel.',
        ]);
        $old = Setting::read('app_logo');
        $path = $request->file('logo')->store('branding', 'local');
        abort_if($path === false, 500, 'Logo tidak berhasil disimpan.');
        try {
            Setting::write('app_logo', $path);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if (is_string($old)) {
            Storage::disk('local')->delete($old);
        }
        Inertia::flash('success', 'Logo aplikasi diperbarui.');

        return back();
    }

    public function destroy(): RedirectResponse
    {
        $old = Setting::read('app_logo');
        Setting::query()->whereKey('app_logo')->delete();
        if (is_string($old)) {
            Storage::disk('local')->delete($old);
        }
        Inertia::flash('success', 'Logo bawaan dipulihkan.');

        return back();
    }

    public function logo(): BinaryFileResponse
    {
        $path = Setting::read('app_logo');
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
