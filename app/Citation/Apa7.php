<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Reference;

/**
 * APA edisi 7 — penulis–tahun.
 */
final class Apa7 extends Style
{
    public function inText(Reference $reference, ?int $number = null): string
    {
        $families = array_map(self::family(...), $reference->authors());

        $names = match (count($families)) {
            0 => '',
            1 => $families[0],
            2 => "{$families[0]} & {$families[1]}",
            default => "{$families[0]} et al.",
        };

        return "{$names}, {$reference->meta('year')}";
    }

    public function entry(Reference $reference, ?int $number = null): array
    {
        $segments = [self::plain(rtrim($this->authorList($reference->authors()), '.').'. ('.$reference->meta('year').'). ')];
        $title = self::sentence($reference->title);

        if ($this->type($reference) === 'article') {
            $segments[] = self::plain($title.' ');
            $segments[] = self::italic($reference->meta('publication'));

            if ($reference->meta('volume') !== '') {
                $segments[] = self::plain(', ');
                $segments[] = self::italic($reference->meta('volume'));
            }

            if ($reference->meta('issue') !== '') {
                $segments[] = self::plain('('.$reference->meta('issue').')');
            }

            if ($reference->meta('pages') !== '') {
                $segments[] = self::plain(', '.$this->pages($reference));
            }

            $segments[] = self::plain('.');
        } else {
            $segments[] = self::italic($title);
            $container = $this->type($reference) === 'book' ? $reference->meta('publisher') : $reference->meta('publication');

            if ($container !== '') {
                $segments[] = self::plain(' '.self::sentence($container));
            }
        }

        $segments[] = self::plain(' '.$this->link($reference));

        return self::merge($segments);
    }

    /**
     * @param  list<string>  $authors
     */
    private function authorList(array $authors): string
    {
        $names = array_map(
            fn (string $name): string => self::isGroup($name) ? $name : trim(self::family($name).', '.self::initials(self::given($name)), ', '),
            $authors,
        );
        $count = count($names);

        if ($count <= 1) {
            return $names[0] ?? '';
        }

        if ($count <= 20) {
            return implode(', ', array_slice($names, 0, -1)).', & '.$names[$count - 1];
        }

        return implode(', ', array_slice($names, 0, 19)).', . . . '.$names[$count - 1];
    }
}
