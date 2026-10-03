<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CitationStyle;
use App\Http\Controllers\Controller;
use App\Models\Reference;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin memilih gaya yang boleh dipakai pengguna. Proyek yang sudah memakai gaya nonaktif tetap
 * bisa mengekspor dengan gaya itu; gaya itu hanya tidak muncul lagi sebagai pilihan.
 */
final class CitationStyleController extends Controller
{
    public function index(): Response
    {
        $enabled = array_map(fn (CitationStyle $style): string => $style->value, CitationStyle::enabled());
        $sample = $this->sample();

        return Inertia::render('admin/CitationStyles', [
            'styles' => array_map(fn (CitationStyle $style): array => [
                'value' => $style->value,
                'label' => $style->label(),
                'description' => $style->description(),
                'enabled' => in_array($style->value, $enabled, true),
                'in_text' => $style->formatter()->wrap([$style->formatter()->inText($sample, 1)]),
                'entry' => $style->formatter()->entry($sample, 1),
            ], CitationStyle::cases()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'array', 'min:1'],
            'enabled.*' => [Rule::enum(CitationStyle::class)],
        ], [
            'enabled.required' => 'Minimal satu gaya sitasi harus aktif.',
            'enabled.min' => 'Minimal satu gaya sitasi harus aktif.',
        ]);

        Setting::write('citation_styles.enabled', array_values(array_unique($data['enabled'])));
        Inertia::flash('success', 'Gaya sitasi disimpan.');

        return back();
    }

    /** Contoh metadata untuk pratinjau — bukan referensi sungguhan. */
    private function sample(): Reference
    {
        return new Reference([
            'title' => 'Literasi digital dan kemandirian belajar mahasiswa',
            'source_url' => 'https://doi.org/10.0000/contoh.2024.001',
            'metadata' => [
                'type' => 'article',
                'authors' => ['Santoso, Budi Arief', 'Lestari, Dewi'],
                'year' => '2024',
                'publication' => 'Jurnal Contoh Pendidikan',
                'volume' => '12',
                'issue' => '3',
                'pages' => '45-60',
                'doi' => '10.0000/contoh.2024.001',
            ],
        ]);
    }
}
