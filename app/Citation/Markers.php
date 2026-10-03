<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Reference;
use Illuminate\Support\Collection;

/**
 * Lokasi sitasi disimpan di teks draf sebagai penanda [@id] (id referensi proyek).
 * Penanda yang berdampingan, mis. "[@1][@2]", dirender sebagai satu kurung.
 */
final class Markers
{
    private const RUN = '/(?:\[@\d+\]\s*)*\[@\d+\]/';

    private const ONE = '/\[@(\d+)\]/';

    /**
     * @return list<int>
     */
    public static function ids(string $text): array
    {
        preg_match_all(self::ONE, $text, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    /**
     * Hapus penanda yang tidak ada di daftar id yang diizinkan.
     *
     * @param  array<int, int>  $allowed
     * @return array{0: string, 1: list<int>} teks bersih dan id yang dibuang
     */
    public static function strip(string $text, array $allowed): array
    {
        $removed = array_values(array_diff(self::ids($text), $allowed));

        // Spasi sebelum penanda ikut dibuang agar tidak tersisa "kata ." di teks.
        $clean = preg_replace_callback(
            '/\s*\[@(\d+)\]/',
            fn (array $m): string => in_array((int) $m[1], $allowed, true) ? $m[0] : '',
            $text,
        ) ?? $text;

        return [$clean, $removed];
    }

    /** Buang semua penanda satu referensi (beserta spasi di depannya). */
    public static function remove(string $text, int $id): string
    {
        return preg_replace('/\s*\[@'.$id.'\]/', '', $text) ?? $text;
    }

    /**
     * @param  Collection<int, Reference>  $references  diindeks per id
     * @param  array<int, int>  $numbers  nomor referensi untuk gaya bernomor
     */
    public static function render(string $text, Collection $references, Style $style, array $numbers = []): string
    {
        return preg_replace_callback(self::RUN, function (array $match) use ($references, $style, $numbers): string {
            $parts = array_map(function (int $id) use ($references, $style, $numbers): string {
                $reference = $references->get($id);

                if ($reference === null) {
                    return '[sitasi tidak dikenal]';
                }

                return $style->isComplete($reference)
                    ? $style->inText($reference, $numbers[$id] ?? null)
                    : '[sitasi belum lengkap: '.$reference->title.']';
            }, self::ids($match[0]));

            return $style->wrap($parts);
        }, $text) ?? $text;
    }
}
