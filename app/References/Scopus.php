<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;
use RuntimeException;

/** Metadata dokumen yang ditemukan di indeks Scopus, melalui API resmi Elsevier. */
final class Scopus implements Provider
{
    public function __construct(private readonly ?string $apiKey = null, private readonly ?string $insttoken = null) {}

    public static function fromConfig(): self
    {
        return new self(config('services.scopus.api_key'), config('services.scopus.insttoken'));
    }

    public function name(): string
    {
        return 'Scopus';
    }

    public function unsupported(SearchFilters $filters): ?string
    {
        if (blank($this->apiKey)) {
            return 'Scopus belum dikonfigurasi. Isi SCOPUS_API_KEY di server.';
        }

        return $filters->scoped() ? 'Scopus tidak menyediakan negara penerbit jurnal pada pencarian ini. Pilih cakupan Nasional & internasional.' : null;
    }

    public function request(Pool $pool, string $alias, string $query, SearchFilters $filters, int $page, int $rows): mixed
    {
        // Input diperlakukan sebagai kata kunci, bukan sintaks kueri yang dapat melewati filter.
        $terms = trim((string) preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $query));
        if ($terms === '') {
            throw new RuntimeException('Kata kunci Scopus harus mengandung huruf atau angka.');
        }
        $clauses = array_filter([
            'TITLE-ABS-KEY-AUTH("'.$terms.'")',
            $filters->yearFrom ? 'PUBYEAR > '.($filters->yearFrom - 1) : null,
            $filters->yearTo ? 'PUBYEAR < '.($filters->yearTo + 1) : null,
            match ($filters->type) {
                'article' => 'SRCTYPE(j)',
                'book' => 'SRCTYPE(b)',
                default => null,
            },
            $filters->openAccess ? 'OPENACCESS(1)' : null,
        ]);

        return $pool->as($alias)->acceptJson()->timeout(15)
            ->withHeaders(array_filter(['X-ELS-APIKey' => $this->apiKey, 'X-ELS-Insttoken' => $this->insttoken]))
            ->get('https://api.elsevier.com/content/search/scopus', [
                'query' => implode(' AND ', $clauses),
                'view' => 'STANDARD',
                'count' => $rows,
                'start' => ($page - 1) * $rows,
            ]);
    }

    public function parse(array $json): array
    {
        if (! is_array($json['search-results'] ?? null)) {
            throw new RuntimeException('Jawaban Scopus tidak dapat dibaca.');
        }
        $items = $json['search-results']['entry'] ?? [];

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item) || isset($item['error'])) {
                return null;
            }
            $doi = Metadata::doi($item['prism:doi'] ?? '');
            $eid = Metadata::clean($item['eid'] ?? '');
            $url = $doi !== '' ? "https://doi.org/{$doi}" : ($eid !== '' ? 'https://www.scopus.com/record/display.uri?eid='.rawurlencode($eid).'&origin=resultslist' : '');
            $date = Metadata::clean($item['prism:coverDate'] ?? '');
            $authors = [];
            // STANDARD sering hanya memuat dc:creator (penulis pertama). Jangan menganggapnya daftar lengkap.
            foreach (is_array($item['author'] ?? null) ? $item['author'] : [] as $author) {
                if (is_array($author)) {
                    $name = Metadata::clean($author['surname'] ?? '');
                    $given = Metadata::clean($author['given-name'] ?? $author['initials'] ?? '');
                    $authors[] = $name !== '' ? $name.($given !== '' ? ', '.$given : '') : Metadata::familyFirst(Metadata::clean($author['authname'] ?? ''));
                }
            }
            $type = match (mb_strtolower(Metadata::clean($item['prism:aggregationType'] ?? ''))) {
                'journal' => 'article',
                'book' => 'book',
                default => 'web',
            };

            return Metadata::result(
                Metadata::clean($item['dc:title'] ?? ''), $url, $this->name(),
                [
                    'type' => $type,
                    'authors' => array_values(array_filter($authors)),
                    'year' => preg_match('/^(\d{4})/', $date, $matches) ? $matches[1] : '',
                    'publication' => Metadata::clean($item['prism:publicationName'] ?? ''),
                    'volume' => Metadata::clean($item['prism:volume'] ?? ''),
                    'issue' => Metadata::clean($item['prism:issueIdentifier'] ?? ''),
                    'pages' => Metadata::clean($item['prism:pageRange'] ?? ''),
                    'doi' => $doi,
                ],
            ); // Status openaccess tidak menjamin API memberi URL teks lengkap.
        }, is_array($items) ? $items : [])));
    }

    public function withCountries(array $results): array
    {
        return $results; // Negara afiliasi penulis bukan negara penerbit jurnal.
    }
}
