<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Reference;

/**
 * MLA edisi 9 — sitasi dalam teks memakai nama penulis (nomor halaman belum disimpan aplikasi).
 */
final class Mla9 extends Style
{
    public function inText(Reference $reference, ?int $number = null): string
    {
        $families = array_map(self::family(...), $reference->authors());

        return match (count($families)) {
            0 => '',
            1 => $families[0],
            2 => "{$families[0]} and {$families[1]}",
            default => "{$families[0]} et al.",
        };
    }

    public function entry(Reference $reference, ?int $number = null): array
    {
        $authors = $reference->authors();
        $list = match (count($authors)) {
            0 => '',
            1 => $authors[0],
            2 => $authors[0].', and '.self::natural($authors[1]),
            default => $authors[0].', et al.',
        };

        $segments = [self::plain(self::sentence($list).' ')];
        $year = $reference->meta('year');
        $type = $this->type($reference);

        if ($type === 'article') {
            $segments[] = self::plain(self::quoted($reference->title, '.').' ');
            $segments[] = self::italic($reference->meta('publication'));

            $details = [];

            if ($reference->meta('volume') !== '') {
                $details[] = 'vol. '.$reference->meta('volume');
            }

            if ($reference->meta('issue') !== '') {
                $details[] = 'no. '.$reference->meta('issue');
            }

            $details[] = $year;

            if ($reference->meta('pages') !== '') {
                $details[] = (str_contains($reference->meta('pages'), '-') ? 'pp. ' : 'p. ').$this->pages($reference);
            }

            $segments[] = self::plain(', '.implode(', ', $details).'. '.$this->link($reference).'.');
        } elseif ($type === 'book') {
            $segments[] = self::italic(self::sentence($reference->title));
            $segments[] = self::plain(' '.$reference->meta('publisher').', '.$year.'.');
        } else {
            $segments[] = self::plain(self::quoted($reference->title, '.').' ');

            if ($reference->meta('publication') !== '') {
                $segments[] = self::italic($reference->meta('publication'));
                $segments[] = self::plain(', ');
            }

            $segments[] = self::plain($year.', '.$reference->source_url.'.');
        }

        return self::merge($segments);
    }
}
