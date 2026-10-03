<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Reference;

/**
 * IEEE — bernomor [n] sesuai kemunculan pertama di draf.
 */
final class Ieee extends Style
{
    public function numbered(): bool
    {
        return true;
    }

    public function inText(Reference $reference, ?int $number = null): string
    {
        return '['.($number ?? '?').']';
    }

    public function wrap(array $parts): string
    {
        return implode(', ', $parts);
    }

    public function entry(Reference $reference, ?int $number = null): array
    {
        $authors = $this->authorList($reference->authors());
        $year = $reference->meta('year');
        $type = $this->type($reference);
        $segments = [self::plain('['.($number ?? '?').'] '.$authors.', ')];

        if ($type === 'book') {
            $segments[] = self::italic(trim($reference->title));
            $segments[] = self::plain('. '.$reference->meta('publisher').', '.$year.'.');
        } else {
            $segments[] = self::plain(self::quoted($reference->title, ',').' ');

            $details = [];

            if ($reference->meta('volume') !== '') {
                $details[] = 'vol. '.$reference->meta('volume');
            }

            if ($reference->meta('issue') !== '') {
                $details[] = 'no. '.$reference->meta('issue');
            }

            if ($reference->meta('pages') !== '') {
                $details[] = (str_contains($reference->meta('pages'), '-') ? 'pp. ' : 'p. ').$this->pages($reference);
            }

            $details[] = $year;

            if ($type === 'article') {
                $segments[] = self::italic($reference->meta('publication'));
            } elseif ($reference->meta('publication') !== '') {
                $segments[] = self::plain($reference->meta('publication'));
            }

            $segments[] = self::plain(', '.implode(', ', $details).'.');
        }

        $doi = $this->doi($reference);
        $segments[] = self::plain($doi !== '' ? " doi: {$doi}." : ' [Online]. Available: '.$reference->source_url);

        return self::merge($segments);
    }

    /**
     * "Santoso, Budi Arief" → "B. A. Santoso"; 7+ penulis → "B. A. Santoso et al.".
     *
     * @param  list<string>  $authors
     */
    private function authorList(array $authors): string
    {
        $names = array_map(
            fn (string $name): string => self::isGroup($name) ? $name : trim(self::initials(self::given($name)).' '.self::family($name)),
            $authors,
        );

        return count($names) > 6 ? $names[0].' et al.' : self::join($names, 'and', count($names) > 2);
    }
}
