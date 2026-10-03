<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;

/**
 * Semantic Scholar Graph API. Tanpa kunci, batas permintaannya dibagi bersama dan sering penuh;
 * isi SEMANTIC_SCHOLAR_API_KEY (gratis, perlu daftar) agar stabil.
 */
final class SemanticScholar implements Provider
{
    public function __construct(private readonly ?string $apiKey = null) {}

    public static function fromConfig(): self
    {
        return new self(config('services.semantic_scholar.api_key'));
    }

    public function name(): string
    {
        return 'Semantic Scholar';
    }

    public function unsupported(SearchFilters $filters): ?string
    {
        return $filters->scoped() ? 'Semantic Scholar dilewati: tidak memuat data negara jurnal.' : null;
    }

    public function request(Pool $pool, string $alias, string $query, SearchFilters $filters, int $page, int $rows): mixed
    {
        $year = match (true) {
            $filters->yearFrom && $filters->yearTo => "{$filters->yearFrom}-{$filters->yearTo}",
            (bool) $filters->yearFrom => "{$filters->yearFrom}-",
            (bool) $filters->yearTo => "-{$filters->yearTo}",
            default => null,
        };

        return $pool->as($alias)
            ->acceptJson()
            ->timeout(15)
            ->when(filled($this->apiKey), fn ($http) => $http->withHeaders(['x-api-key' => (string) $this->apiKey]))
            ->get('https://api.semanticscholar.org/graph/v1/paper/search', array_filter([
                'query' => $query,
                'year' => $year,
                'publicationTypes' => match ($filters->scoped() ? 'article' : $filters->type) {
                    'article' => 'JournalArticle',
                    'book' => 'Book',
                    default => null,
                },
                'openAccessPdf' => $filters->openAccess ? '' : null,
                'offset' => ($page - 1) * $rows,
                'limit' => $rows,
                'fields' => 'title,authors,year,venue,journal,externalIds,url,openAccessPdf,publicationTypes',
            ], fn ($value): bool => $value !== null));
    }

    public function parse(array $json): array
    {
        $items = $json['data'] ?? [];

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            $doi = Metadata::clean($item['externalIds']['DOI'] ?? '');
            $types = is_array($item['publicationTypes'] ?? null) ? $item['publicationTypes'] : [];
            $type = in_array('JournalArticle', $types, true) ? 'article' : (in_array('Book', $types, true) ? 'book' : 'web');
            $journal = is_array($item['journal'] ?? null) ? $item['journal'] : [];
            $year = $item['year'] ?? null;

            return Metadata::result(
                (string) ($item['title'] ?? ''),
                $doi !== '' ? "https://doi.org/{$doi}" : Metadata::clean($item['url'] ?? ''),
                $this->name(),
                [
                    'type' => $type,
                    'authors' => array_values(array_filter(array_map(
                        fn (mixed $a): string => Metadata::familyFirst(is_array($a) ? (string) ($a['name'] ?? '') : ''),
                        is_array($item['authors'] ?? null) ? $item['authors'] : [],
                    ))),
                    'year' => is_int($year) ? (string) $year : '',
                    'publication' => $type === 'book' ? '' : (Metadata::clean($journal['name'] ?? '') ?: Metadata::clean($item['venue'] ?? '')),
                    'volume' => Metadata::clean($journal['volume'] ?? ''),
                    'pages' => str_replace(' ', '', Metadata::clean($journal['pages'] ?? '')),
                    'doi' => $doi,
                ],
                is_array($item['openAccessPdf'] ?? null) ? (string) ($item['openAccessPdf']['url'] ?? '') : null,
            );
        }, is_array($items) ? $items : [])));
    }

    public function withCountries(array $results): array
    {
        return $results;
    }
}
