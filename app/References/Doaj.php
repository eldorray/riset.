<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;

/**
 * DOAJ — Directory of Open Access Journals. Hanya artikel jurnal akses terbuka; banyak jurnal Indonesia.
 */
final class Doaj implements Provider
{
    public function name(): string
    {
        return 'DOAJ';
    }

    public function unsupported(SearchFilters $filters): ?string
    {
        return $filters->type === 'book' ? 'DOAJ hanya memuat artikel jurnal.' : null;
    }

    public function request(Pool $pool, string $alias, string $query, SearchFilters $filters, int $page, int $rows): mixed
    {
        // DOAJ memakai sintaks kueri Elasticsearch; tanda khusus dibuang dari kata kunci pengguna.
        $terms = trim((string) preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $query));

        if ($filters->scope === 'national') {
            $terms .= ' AND bibjson.journal.country:ID';
        } elseif ($filters->scope === 'international') {
            $terms .= ' AND NOT bibjson.journal.country:ID';
        }

        if ($filters->yearFrom || $filters->yearTo) {
            $terms .= ' AND bibjson.year:['.($filters->yearFrom ?? '*').' TO '.($filters->yearTo ?? '*').']';
        }

        return $pool->as($alias)
            ->acceptJson()
            ->timeout(15)
            ->get('https://doaj.org/api/search/articles/'.rawurlencode($terms), [
                'page' => $page,
                'pageSize' => $rows,
            ]);
    }

    public function parse(array $json): array
    {
        $items = $json['results'] ?? [];

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            $bib = is_array($item) && is_array($item['bibjson'] ?? null) ? $item['bibjson'] : null;

            if ($bib === null) {
                return null;
            }

            $doi = '';
            $link = '';

            foreach (is_array($bib['identifier'] ?? null) ? $bib['identifier'] : [] as $identifier) {
                if (($identifier['type'] ?? '') === 'doi') {
                    $doi = Metadata::doi($identifier['id'] ?? '');
                }
            }

            foreach (is_array($bib['link'] ?? null) ? $bib['link'] : [] as $candidate) {
                $link = $link ?: Metadata::clean($candidate['url'] ?? '');
            }

            $journal = is_array($bib['journal'] ?? null) ? $bib['journal'] : [];

            return Metadata::result(
                (string) ($bib['title'] ?? ''),
                $doi !== '' ? "https://doi.org/{$doi}" : $link,
                $this->name(),
                [
                    'type' => 'article',
                    'authors' => array_values(array_filter(array_map(
                        fn (mixed $a): string => Metadata::familyFirst(is_array($a) ? (string) ($a['name'] ?? '') : ''),
                        is_array($bib['author'] ?? null) ? $bib['author'] : [],
                    ))),
                    'year' => Metadata::clean($bib['year'] ?? ''),
                    'publication' => Metadata::clean($journal['title'] ?? ''),
                    'volume' => Metadata::clean($journal['volume'] ?? ''),
                    'issue' => Metadata::clean($journal['number'] ?? ''),
                    'pages' => Metadata::pages($bib['start_page'] ?? '', $bib['end_page'] ?? ''),
                    'doi' => $doi,
                ],
                $link ?: null,
                strtoupper(Metadata::clean($journal['country'] ?? '')) ?: null,
            );
        }, is_array($items) ? $items : [])));
    }

    public function withCountries(array $results): array
    {
        return $results; // negara sudah ada di bibjson.journal.country
    }
}
