<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Reference;

/**
 * Chicago author-date (edisi 17) — penulis tahun, tanpa koma di antaranya.
 */
final class Chicago extends Style
{
    public function inText(Reference $reference, ?int $number = null): string
    {
        $families = array_map(self::family(...), $reference->authors());
        $names = count($families) > 3 ? $families[0].' et al.' : self::join($families, 'and', count($families) > 2);

        return "{$names} {$reference->meta('year')}";
    }

    public function entry(Reference $reference, ?int $number = null): array
    {
        $authors = $reference->authors();

        // Penulis pertama dibalik ("Santoso, Budi"), berikutnya urutan biasa; 11+ → 7 pertama + et al.
        $names = array_map(
            fn (string $name, int $i): string => $i === 0 ? $name : self::natural($name),
            $authors,
            array_keys($authors),
        );
        $list = count($names) > 10 ? implode(', ', array_slice($names, 0, 7)).', et al' : self::join($names, 'and', true);

        $segments = [self::plain(self::sentence($list).' '.$reference->meta('year').'. ')];
        $type = $this->type($reference);

        if ($type === 'article') {
            $segments[] = self::plain(self::quoted($reference->title, '.').' ');
            $segments[] = self::italic($reference->meta('publication'));
            $volume = $reference->meta('volume');
            $issue = $reference->meta('issue') !== '' ? ' ('.$reference->meta('issue').')' : '';
            $pages = $reference->meta('pages') !== '' ? ': '.$this->pages($reference) : '';
            $segments[] = self::plain(($volume !== '' ? ' '.$volume.$issue : '').$pages.'. '.$this->link($reference).'.');
        } elseif ($type === 'book') {
            $segments[] = self::italic(self::sentence($reference->title));
            $segments[] = self::plain(' '.self::sentence($reference->meta('publisher')));

            if ($this->doi($reference) !== '') {
                $segments[] = self::plain(' '.$this->link($reference).'.');
            }
        } else {
            $segments[] = self::plain(self::quoted($reference->title, '.'));
            $site = $reference->meta('publication') !== '' ? ' '.self::sentence($reference->meta('publication')) : '';
            $segments[] = self::plain($site.' '.$reference->source_url.'.');
        }

        return self::merge($segments);
    }
}
