<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Crossref REST API (gratis, tanpa kunci). Metadata DOI terlengkap untuk jurnal, termasuk OJS Indonesia.
 * Filter akses terbuka tidak didukung Crossref.
 */
final class Crossref implements Provider
{
    public function __construct(private readonly ?string $mailto = null) {}

    public static function fromConfig(): self
    {
        return new self(config('services.crossref.mailto'));
    }

    public function name(): string
    {
        return 'Crossref';
    }

    public function unsupported(SearchFilters $filters): ?string
    {
        return $filters->openAccess ? 'Crossref tidak mendukung filter akses terbuka.' : null;
    }

    public function request(Pool $pool, string $alias, string $query, SearchFilters $filters, int $page, int $rows): mixed
    {
        $filter = array_filter([
            $filters->yearFrom ? "from-pub-date:{$filters->yearFrom}" : null,
            $filters->yearTo ? "until-pub-date:{$filters->yearTo}" : null,
            match ($filters->scoped() ? 'article' : $filters->type) {
                'article' => 'type:journal-article',
                'book' => 'type:book',
                default => null,
            },
        ]);

        return $pool->as($alias)
            ->acceptJson()
            ->withUserAgent('Riset/1.0'.(filled($this->mailto) ? " (mailto:{$this->mailto})" : ''))
            ->timeout(15)
            ->get('https://api.crossref.org/works', array_filter([
                'query.bibliographic' => $query,
                'rows' => $rows,
                'offset' => ($page - 1) * $rows,
                'filter' => implode(',', $filter) ?: null,
                'select' => 'DOI,title,author,issued,container-title,volume,issue,page,publisher,type,URL,member',
                'mailto' => $this->mailto,
            ]));
    }

    public function parse(array $json): array
    {
        $items = $json['message']['items'] ?? [];

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item) || ! is_string($item['DOI'] ?? null)) {
                return null;
            }

            $type = match ($item['type'] ?? '') {
                'journal-article', 'proceedings-article' => 'article',
                'book', 'monograph', 'edited-book', 'reference-book' => 'book',
                default => 'web',
            };

            $authors = [];

            foreach (is_array($item['author'] ?? null) ? $item['author'] : [] as $author) {
                $family = Metadata::clean($author['family'] ?? '');
                $given = Metadata::clean($author['given'] ?? '');
                $name = Metadata::clean($author['name'] ?? '');

                if ($family !== '') {
                    $authors[] = $given !== '' ? "{$family}, {$given}" : $family;
                } elseif ($name !== '') {
                    $authors[] = $name;
                }
            }

            $year = $item['issued']['date-parts'][0][0] ?? null;
            $container = Metadata::clean($item['container-title'][0] ?? '');
            $publisher = Metadata::clean($item['publisher'] ?? '');

            return Metadata::result(
                (string) ($item['title'][0] ?? ''),
                'https://doi.org/'.$item['DOI'],
                $this->name(),
                [
                    'type' => $type,
                    'authors' => $authors,
                    'year' => is_int($year) ? (string) $year : '',
                    'publication' => $type === 'web' ? ($container ?: $publisher) : $container,
                    'volume' => Metadata::clean($item['volume'] ?? ''),
                    'issue' => Metadata::clean($item['issue'] ?? ''),
                    'pages' => Metadata::clean($item['page'] ?? ''),
                    'publisher' => $type === 'book' ? $publisher : '',
                    'doi' => $item['DOI'],
                ],
                lookup: is_scalar($item['member'] ?? null) ? (string) $item['member'] : null,
            );
        }, is_array($items) ? $items : [])));
    }

    /**
     * Negara dari lokasi anggota Crossref (penerbit), mis. "Tangerang Selatan, Banten, Indonesia".
     * Disimpan permanen di cache per anggota karena jarang berubah.
     */
    public function withCountries(array $results): array
    {
        $members = array_values(array_unique(array_filter(array_map(fn (array $r): ?string => $r['lookup'] ?? null, $results))));
        $known = [];
        $missing = [];

        foreach ($members as $member) {
            $cached = Cache::get("crossref:member:{$member}:country");
            $cached === null ? $missing[] = $member : $known[$member] = $cached;
        }

        if ($missing !== []) {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $member) => $pool->as($member)->acceptJson()->timeout(10)->get("https://api.crossref.org/members/{$member}", array_filter(['mailto' => $this->mailto])),
                $missing,
            ));

            foreach ($missing as $member) {
                $response = $responses[$member] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    $location = Metadata::clean($response->json('message.location'));
                    $country = str_ends_with(mb_strtolower($location), 'indonesia') ? 'ID' : ($location !== '' ? 'XX' : '');
                    Cache::forever("crossref:member:{$member}:country", $country);
                    $known[$member] = $country;
                }
            }
        }

        // "XX" = di luar Indonesia (lokasi diketahui, kode negara tidak dipetakan satu per satu).
        return array_map(fn (array $r): array => [...$r, 'country' => ($known[$r['lookup'] ?? ''] ?? '') ?: null], $results);
    }
}
