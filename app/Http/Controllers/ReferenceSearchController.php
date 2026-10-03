<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Reference;
use App\References\Metadata;
use App\References\ReferenceSearch;
use App\References\SearchFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * F-03: cari referensi dengan filter tahun, jenis, akses terbuka, dan sumber. Tiap klik "Cari" dengan
 * kata kunci yang sama meminta halaman berikutnya; referensi yang sudah tersimpan tidak ditampilkan
 * lagi, supaya yang muncul selalu judul lain. Tidak menyimpan apa pun.
 */
final class ReferenceSearchController extends Controller
{
    public function __invoke(Request $request, Project $project, ReferenceSearch $search): JsonResponse
    {
        Gate::authorize('update', $project);

        $maxYear = (int) now()->format('Y') + 1;
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
            'index' => ['nullable', Rule::in(['all', 'scopus', 'doaj'])],
            'source' => ['nullable', Rule::in(['all', ...array_keys(ReferenceSearch::SOURCES)])],
            'year_from' => ['nullable', 'integer', 'between:1900,'.$maxYear],
            'year_to' => ['nullable', 'integer', 'between:1900,'.$maxYear, 'gte:year_from'],
            'type' => ['nullable', Rule::in(['any', 'article', 'book'])],
            'open_access' => ['nullable', 'boolean'],
            'scope' => ['nullable', Rule::in(['all', 'national', 'international'])],
            'page' => ['nullable', 'integer', 'between:1,50'],
        ], [
            'index.in' => 'Indeks belum didukung untuk verifikasi otomatis. SINTA dapat diperiksa melalui direktori resmi.',
            'q.required' => 'Masukkan kata kunci pencarian.',
            'q.min' => 'Kata kunci minimal 3 karakter.',
            'year_to.gte' => 'Tahun akhir tidak boleh sebelum tahun awal.',
            'year_from.between' => "Tahun antara 1900 dan {$maxYear}.",
            'year_to.between' => "Tahun antara 1900 dan {$maxYear}.",
        ]);

        $scope = $data['scope'] ?? 'all';
        $filters = new SearchFilters(
            isset($data['year_from']) ? (int) $data['year_from'] : null,
            isset($data['year_to']) ? (int) $data['year_to'] : null,
            // Filter nasional/internasional khusus artikel jurnal.
            $scope === 'all' ? ($data['type'] ?? 'any') : 'article',
            (bool) ($data['open_access'] ?? false),
            $scope,
        );
        $links = ReferenceSearch::externalLinks($data['q'], $filters);

        try {
            $found = $search->search($data['q'], in_array($data['index'] ?? 'all', ['scopus', 'doaj'], true) ? $data['index'] : ($data['source'] ?? 'all'), $filters, (int) ($data['page'] ?? 1));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'links' => $links], 502);
        }

        $saved = $project->references->flatMap(fn (Reference $reference): array => Metadata::referenceKeys($reference->title, $reference->source_url, $reference->meta('doi')))->all();

        $fresh = array_values(array_filter($found['results'], fn (array $result): bool => array_intersect(
            Metadata::referenceKeys($result['title'], $result['source_url'], $result['metadata']['doi'] ?? ''),
            $saved,
        ) === []));
        // Kata kunci pencarian menjadi kategori awal; pengguna bisa menyuntingnya.
        $fresh = array_map(fn (array $result): array => [...$result, 'metadata' => [...$result['metadata'], 'keywords' => [mb_strtolower($data['q'])]]], $fresh);

        return response()->json([
            'results' => $fresh,
            'hidden' => count($found['results']) - count($fresh),
            'notes' => $found['notes'],
            'links' => $links,
            'page' => (int) ($data['page'] ?? 1),
        ]);
    }
}
