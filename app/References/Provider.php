<?php

declare(strict_types=1);

namespace App\References;

use Illuminate\Http\Client\Pool;

/**
 * Penyedia pencarian referensi berbasis API resmi. Hanya metadata yang benar-benar dikirim
 * penyedia yang dipetakan; nama penyedia bukan bukti kredibilitas atau indeksasi.
 *
 * @phpstan-type Result array{title: string, source_url: string, source_name: string, open_access_url: string|null, country: string|null, lookup?: string, metadata: array<string, mixed>}
 */
interface Provider
{
    public function name(): string;

    /** Alasan penyedia dilewati untuk filter ini (mis. tidak memuat buku), atau null bila didukung. */
    public function unsupported(SearchFilters $filters): ?string;

    /** Daftarkan permintaan ke pool HTTP (dijalankan paralel), dengan alias kunci penyedia. */
    public function request(Pool $pool, string $alias, string $query, SearchFilters $filters, int $page, int $rows): mixed;

    /**
     * @param  array<mixed>  $json
     * @return list<Result>
     */
    public function parse(array $json): array;

    /**
     * Lengkapi kode negara penerbit jurnal (ISO-2) bila penyedia butuh permintaan tambahan.
     * Negara yang tidak diketahui tetap null — filter nasional/internasional membuangnya.
     *
     * @param  list<Result>  $results
     * @return list<Result>
     */
    public function withCountries(array $results): array;
}
