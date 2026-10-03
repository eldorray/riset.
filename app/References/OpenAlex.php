<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAlex API (terbuka). Cakupan luas termasuk karya tanpa DOI; mendukung filter akses terbuka.
 */
final class OpenAlex implements Provider
{
    public function __construct(
        private readonly ?string $mailto = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('services.openalex.mailto') ?? config('services.crossref.mailto'), config('services.openalex.api_key'));
    }

    public function name(): string
    {
        return 'OpenAlex';
    }

    public function unsupported(SearchFilters $filters): ?string
    {
        return null;
    }

    public function request(Pool $pool, string $alias, string $query, SearchFilters $filters, int $page, int $rows): mixed
    {
        $years = match (true) {
            $filters->yearFrom && $filters->yearTo => "publication_year:{$filters->yearFrom}-{$filters->yearTo}",
            (bool) $filters->yearFrom => 'publication_year:>'.($filters->yearFrom - 1),
            (bool) $filters->yearTo => 'publication_year:<'.($filters->yearTo + 1),
            default => null,
        };

        $filter = array_filter([
            $years,
            match ($filters->scoped() ? 'article' : $filters->type) {
                'article' => 'type:article',
                'book' => 'type:book',
                default => null,
            },
            $filters->scoped() ? 'primary_location.source.type:journal' : null,
            $filters->openAccess ? 'is_oa:true' : null,
        ]);

        return $pool->as($alias)
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.openalex.org/works', array_filter([
                'search' => $query,
                'filter' => implode(',', $filter) ?: null,
                'per-page' => $rows,
                'page' => $page,
                'select' => 'id,doi,display_name,publication_year,type,authorships,primary_location,biblio,open_access',
                'mailto' => $this->mailto,
                'api_key' => $this->apiKey,
            ]));
    }

    public function parse(array $json): array
    {
        $items = $json['results'] ?? [];

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            $doi = Metadata::doi($item['doi'] ?? '');
            $landing = Metadata::clean($item['primary_location']['landing_page_url'] ?? '');
            $url = $doi !== '' ? "https://doi.org/{$doi}" : $landing;
            $type = match ($item['type'] ?? '') {
                'article' => 'article',
                'book' => 'book',
                default => 'web',
            };
            $source = $item['primary_location']['source'] ?? null;
            $container = Metadata::clean(is_array($source) ? ($source['display_name'] ?? '') : '');
            $publisher = Metadata::clean(is_array($source) ? ($source['host_organization_name'] ?? '') : '');

            $authors = array_values(array_filter(array_map(
                fn (mixed $a): string => Metadata::familyFirst(is_array($a) ? (string) ($a['author']['display_name'] ?? '') : ''),
                is_array($item['authorships'] ?? null) ? $item['authorships'] : [],
            )));

            $year = $item['publication_year'] ?? null;
            $biblio = is_array($item['biblio'] ?? null) ? $item['biblio'] : [];

            return Metadata::result(
                (string) ($item['display_name'] ?? ''),
                $url,
                $this->name(),
                [
                    'type' => $type,
                    'authors' => $authors,
                    'year' => is_int($year) ? (string) $year : '',
                    'publication' => $type === 'book' ? '' : $container,
                    'volume' => Metadata::clean($biblio['volume'] ?? ''),
                    'issue' => Metadata::clean($biblio['issue'] ?? ''),
                    'pages' => Metadata::pages($biblio['first_page'] ?? '', $biblio['last_page'] ?? ''),
                    'publisher' => $type === 'book' ? $publisher : '',
                    'doi' => $doi,
                ],
                is_array($item['open_access'] ?? null) && ($item['open_access']['is_oa'] ?? false) ? (string) ($item['open_access']['oa_url'] ?? '') : null,
                lookup: is_array($source) && is_string($source['id'] ?? null) ? basename($source['id']) : null,
            );
        }, is_array($items) ? $items : [])));
    }

    /** Negara penerbit dari entitas sumber (jurnal) OpenAlex, satu permintaan untuk semua sumber. */
    public function withCountries(array $results): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (array $r): ?string => $r['lookup'] ?? null, $results))));
        $known = [];
        $missing = [];

        foreach ($ids as $id) {
            $cached = Cache::get("openalex:source:{$id}:country");
            $cached === null ? $missing[] = $id : $known[$id] = $cached;
        }

        if ($missing !== []) {
            try {
                $response = Http::acceptJson()->timeout(10)->get('https://api.openalex.org/sources', array_filter([
                    'filter' => 'openalex:'.implode('|', array_slice($missing, 0, 50)),
                    'select' => 'id,country_code',
                    'per-page' => 50,
                    'mailto' => $this->mailto,
                    'api_key' => $this->apiKey,
                ]))->throw();

                foreach ((array) $response->json('results') as $source) {
                    $id = is_array($source) ? basename((string) ($source['id'] ?? '')) : '';
                    $country = is_array($source) ? strtoupper(Metadata::clean($source['country_code'] ?? '')) : '';
                    Cache::forever("openalex:source:{$id}:country", $country);
                    $known[$id] = $country;
                }
            } catch (Throwable $e) {
                report($e); // negara tetap tidak diketahui → dibuang oleh filter ketat
            }
        }

        return array_map(fn (array $r): array => [...$r, 'country' => ($known[$r['lookup'] ?? ''] ?? '') ?: null], $results);
    }
}
