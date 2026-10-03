<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Mencari di satu atau semua penyedia sekaligus (paralel), lalu membuang duplikat
 * berdasarkan DOI dan judul. Penyedia yang gagal dilaporkan tanpa menggagalkan yang lain.
 *
 * @phpstan-import-type Result from Provider
 */
final class ReferenceSearch
{
    public const SOURCES = ['crossref' => 'Crossref', 'openalex' => 'OpenAlex', 'semantic' => 'Semantic Scholar', 'doaj' => 'DOAJ', 'scopus' => 'Scopus'];

    /**
     * @param  array<string, Provider>  $providers
     */
    public function __construct(private readonly array $providers) {}

    public static function fromConfig(): self
    {
        return new self([
            'crossref' => Crossref::fromConfig(),
            'openalex' => OpenAlex::fromConfig(),
            'semantic' => SemanticScholar::fromConfig(),
            'doaj' => new Doaj,
            'scopus' => Scopus::fromConfig(),
        ]);
    }

    /**
     * @return array{results: list<Result>, notes: list<string>}
     *
     * @throws RuntimeException bila semua penyedia gagal
     */
    public function search(string $query, string $source, SearchFilters $filters, int $page): array
    {
        $selected = $source === 'all' ? array_diff_key($this->providers, ['scopus' => true]) : array_intersect_key($this->providers, [$source => true]);
        $notes = [];

        foreach ($selected as $key => $provider) {
            if ($reason = $provider->unsupported($filters)) {
                $notes[] = $reason;
                unset($selected[$key]);
            }
        }

        if ($selected === []) {
            throw new RuntimeException($notes[0] ?? 'Sumber pencarian tidak dikenal.');
        }

        // Mode "semua sumber": 5 per penyedia agar hasil tetap beragam. Filter nasional/internasional
        // menyaring setelah hasil datang, jadi diminta dua kali lipat agar tetap ada yang tersisa.
        $rows = (count($selected) > 1 ? 5 : 10) * ($filters->scoped() ? 2 : 1);

        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (string $key): mixed => $selected[$key]->request($pool, $key, $query, $filters, $page, $rows),
            array_keys($selected),
        ));

        $results = [];
        $failed = [];

        foreach ($selected as $key => $provider) {
            $response = $responses[$key] ?? null;

            try {
                if (! $response instanceof Response || ! $response->successful()) {
                    throw new RuntimeException($response instanceof Response ? "HTTP {$response->status()}" : 'tidak dapat dihubungi');
                }

                $parsed = $provider->parse((array) $response->json());
                $results[] = $filters->scoped() ? self::inScope($provider->withCountries($parsed), $filters->scope) : $parsed;
            } catch (Throwable $e) {
                report($e);
                $failed[] = match (true) {
                    $key === 'scopus' && $response instanceof Response && in_array($response->status(), [401, 403], true) => 'Scopus menolak akses (HTTP '.$response->status().'). Periksa akses API/jaringan Elsevier dan akses institusi bila diperlukan',
                    $response instanceof Response && $response->status() === 429 => $provider->name().' (batas permintaan penuh)',
                    default => $provider->name().' tidak merespons',
                };
            }
        }

        if (count($failed) === count($selected)) {
            throw new RuntimeException('Pencarian gagal: '.implode(', ', $failed).'. Coba lagi beberapa saat atau pilih sumber lain.');
        }

        if ($failed !== []) {
            $notes[] = 'Sebagian sumber gagal: '.implode(', ', $failed).'. Hasil dari sumber lain tetap ditampilkan.';
        }

        if (isset($selected['scopus']) && ! in_array('Scopus tidak merespons', $failed, true)) {
            $notes[] = 'Hasil Scopus menunjukkan dokumen ditemukan dalam indeks, bukan jaminan jurnal masih aktif terindeks atau memiliki kuartil tertentu. Daftar penulis lengkap tidak selalu tersedia; periksa dan lengkapi dari sumber asli sebelum menyimpan.';
        }

        return [
            // Kunci bantu internal (id anggota/sumber) tidak dikirim ke browser.
            'results' => array_map(function (array $r): array {
                unset($r['lookup']);

                return $r;
            }, self::unique(self::interleave($results))),
            'notes' => $notes,
        ];
    }

    /**
     * Saring ketat: nasional = jurnal terbitan Indonesia, internasional = terbitan luar Indonesia.
     * Negara yang tidak diketahui dibuang di kedua mode.
     *
     * @param  list<Result>  $results
     * @return list<Result>
     */
    private static function inScope(array $results, string $scope): array
    {
        return array_values(array_filter($results, fn (array $r): bool => match ($scope) {
            'national' => $r['country'] === 'ID',
            'international' => $r['country'] !== null && $r['country'] !== 'ID',
            default => true,
        }));
    }

    /**
     * Tautan pencarian manual untuk layanan tanpa API publik (dibuka di tab baru, tidak di-scrape).
     *
     * @return list<array{name: string, url: string}>
     */
    public static function externalLinks(string $query, SearchFilters $filters): array
    {
        $scholar = array_filter(['q' => $query, 'as_ylo' => $filters->yearFrom, 'as_yhi' => $filters->yearTo]);

        return [
            ['name' => 'Google Scholar', 'url' => 'https://scholar.google.com/scholar?'.http_build_query($scholar)],
            ['name' => 'ResearchGate', 'url' => 'https://www.researchgate.net/search/publication?'.http_build_query(['q' => $query])],
        ];
    }

    /**
     * Gabung bergantian antar-penyedia agar hasil teratas tidak didominasi satu sumber.
     *
     * @param  list<list<Result>>  $groups
     * @return list<Result>
     */
    private static function interleave(array $groups): array
    {
        $merged = [];

        for ($i = 0; $i < max(array_map('count', $groups) ?: [0]); $i++) {
            foreach ($groups as $group) {
                if (isset($group[$i])) {
                    $merged[] = $group[$i];
                }
            }
        }

        return $merged;
    }

    /**
     * @param  list<Result>  $results
     * @return list<Result>
     */
    private static function unique(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $keys = array_filter([
                'doi:'.mb_strtolower((string) ($result['metadata']['doi'] ?? '')),
                'title:'.Metadata::titleKey($result['title']),
            ], fn (string $key): bool => ! str_ends_with($key, ':'));

            if (array_intersect($keys, $seen) !== []) {
                continue;
            }

            $seen = [...$seen, ...$keys];
            $unique[] = $result;
        }

        return $unique;
    }
}
