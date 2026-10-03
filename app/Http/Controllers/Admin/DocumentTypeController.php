<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Once;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Struktur tiap jenis tulisan yang dipakai AI saat menyusun kerangka. Kerangka proyek yang sudah
 * tersimpan tidak ikut berubah.
 */
final class DocumentTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/DocumentTypes', [
            'types' => array_map(fn (DocumentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'custom' => Setting::read("structures.{$type->value}") !== null,
                ...$type->settings(),
            ], DocumentType::cases()),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $documentType = DocumentType::tryFrom($type) ?? abort(404);

        $data = $request->validate([
            'numbering' => ['required', Rule::in(['bab', 'angka'])],
            'chapters' => ['required', 'array', 'min:1', 'max:20'],
            'chapters.*.title' => ['required', 'string', 'max:255'],
            'chapters.*.sections' => ['present', 'array', 'max:20'],
            'chapters.*.sections.*' => ['required', 'string', 'max:255'],
        ], [
            'chapters.min' => 'Minimal satu bab.',
            'chapters.*.title.required' => 'Judul bab tidak boleh kosong.',
            'chapters.*.sections.*.required' => 'Judul subbab tidak boleh kosong.',
        ]);

        Setting::write("structures.{$documentType->value}", [
            'numbering' => $data['numbering'],
            'chapters' => array_map(fn (array $chapter): array => [
                'title' => $chapter['title'],
                'sections' => array_values($chapter['sections']),
            ], array_values($data['chapters'])),
        ]);
        Once::flush();
        Inertia::flash('success', "Struktur {$documentType->label()} disimpan.");

        return back();
    }

    public function destroy(string $type): RedirectResponse
    {
        $documentType = DocumentType::tryFrom($type) ?? abort(404);

        Setting::query()->whereKey("structures.{$documentType->value}")->delete();
        Once::flush();
        Inertia::flash('success', "Struktur {$documentType->label()} dikembalikan ke contoh bawaan.");

        return back();
    }
}
