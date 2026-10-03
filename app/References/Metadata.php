<?php

declare(strict_types=1);

namespace App\References;

/**
 * Pembersih & pemeta metadata bersama untuk semua penyedia.
 */
final class Metadata
{
    public static function clean(mixed $value): string
    {
        return is_scalar($value)
            ? trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5)))
            : '';
    }

    /**
     * "Karsoni Berta Dinata" → "Dinata, Karsoni Berta". Penyedia seperti OpenAlex hanya mengirim
     * nama lengkap; kata terakhir diperlakukan sebagai nama belakang (lazim di sitasi Indonesia).
     * Nama satu kata dan nama yang sudah berkoma dibiarkan. Pengguna tetap bisa menyunting.
     */
    public static function familyFirst(string $name): string
    {
        $name = self::clean($name);

        if ($name === '' || str_contains($name, ',')) {
            return $name;
        }

        $parts = preg_split('/\s+/u', $name) ?: [];

        if (count($parts) < 2) {
            return $name;
        }

        $family = array_pop($parts);

        return $family.', '.implode(' ', $parts);
    }

    public static function doi(mixed $value): string
    {
        return preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', self::clean($value)) ?? '';
    }

    /** @return list<string> */
    public static function referenceKeys(string $title, string $url, string $doi = ''): array
    {
        if ($doi === '' && preg_match('#^https?://(dx\.)?doi\.org/#i', $url)) {
            $doi = $url;
        }

        return array_values(array_filter([
            'title:'.self::titleKey($title),
            'url:'.mb_strtolower(rtrim(explode('#', trim($url), 2)[0], '/')),
            'doi:'.mb_strtolower(self::doi($doi)),
        ], fn (string $key): bool => ! str_ends_with($key, ':')));
    }

    public static function pages(mixed $first, mixed $last): string
    {
        $first = self::clean($first);
        $last = self::clean($last);

        return $first !== '' && $last !== '' && $first !== $last ? "{$first}-{$last}" : $first;
    }

    /**
     * Kunci pembanding judul: huruf kecil, hanya huruf/angka. Dipakai untuk membuang duplikat.
     */
    public static function titleKey(string $title): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($title)));
    }

    /**
     * Buang field kosong agar "belum ada" tetap terlihat kosong, bukan ditebak.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function compact(array $metadata): array
    {
        return array_filter($metadata, fn ($value): bool => $value !== '' && $value !== [] && $value !== null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{title: string, source_url: string, source_name: string, open_access_url: string|null, country: string|null, lookup?: string, metadata: array<string, mixed>}|null
     */
    public static function result(string $title, string $url, string $source, array $metadata, ?string $openAccessUrl = null, ?string $country = null, ?string $lookup = null): ?array
    {
        $title = self::clean($title);

        if ($title === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 500),
            'source_url' => $url,
            'source_name' => $source,
            'open_access_url' => filled($openAccessUrl) && preg_match('#^https?://#i', (string) $openAccessUrl) ? $openAccessUrl : null,
            'country' => $country !== null && preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
            'metadata' => self::compact($metadata),
            ...($lookup !== null && $lookup !== '' ? ['lookup' => $lookup] : []),
        ];
    }
}
