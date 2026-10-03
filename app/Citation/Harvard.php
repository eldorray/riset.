<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Reference;

/**
 * Harvard (mengikuti Cite Them Right) — penulis–tahun.
 */
final class Harvard extends Style
{
    public function inText(Reference $reference, ?int $number = null): string
    {
        $families = array_map(self::family(...), $reference->authors());
        $names = count($families) > 3 ? $families[0].' et al.' : self::join($families, 'and', false);

        return "{$names}, {$reference->meta('year')}";
    }

    public function entry(Reference $reference, ?int $number = null): array
    {
        $names = array_map(
            fn (string $name): string => self::isGroup($name) ? $name : trim(self::family($name).', '.self::initials(self::given($name), false), ', '),
            $reference->authors(),
        );
        $authors = count($names) > 3 ? $names[0].' et al.' : self::join($names, 'and', false);
        $segments = [self::plain($authors.' ('.$reference->meta('year').') ')];
        $type = $this->type($reference);

        if ($type === 'article') {
            $segments[] = self::plain("'".trim($reference->title)."', ");
            $segments[] = self::italic($reference->meta('publication'));

            if ($reference->meta('volume') !== '') {
                $issue = $reference->meta('issue') !== '' ? '('.$reference->meta('issue').')' : '';
                $segments[] = self::plain(', '.$reference->meta('volume').$issue);
            }

            if ($reference->meta('pages') !== '') {
                $segments[] = self::plain(', pp. '.$this->pages($reference));
            }

            $segments[] = self::plain('. Available at: '.$this->link($reference).'.');
        } else {
            $segments[] = self::italic(self::sentence($reference->title));

            if ($type === 'book') {
                $segments[] = self::plain(' '.self::sentence($reference->meta('publisher')));

                if ($this->doi($reference) !== '') {
                    $segments[] = self::plain(' Available at: '.$this->link($reference).'.');
                }
            } else {
                $segments[] = self::plain(' Available at: '.$reference->source_url.'.');
            }
        }

        return self::merge($segments);
    }
}
