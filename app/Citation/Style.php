<?php

declare(strict_types=1);

namespace App\Citation;

use App\Models\Project;
use App\Models\Reference;
use Illuminate\Support\Collection;

/**
 * Dasar formatter gaya sitasi. Tiap gaya mengatur sitasi dalam teks dan entri daftar pustaka;
 * aturan field wajib sama untuk semua gaya agar tidak ada nilai yang ditebak.
 *
 * ponytail: judul dipakai apa adanya (tidak diubah ke sentence/title case) dan tidak ada
 * pembeda 2024a/2024b. Tambah bila panduan menuntut.
 *
 * @phpstan-type Segment array{text: string, italic: bool}
 */
abstract class Style
{
    public const TYPES = ['article' => 'Artikel jurnal', 'book' => 'Buku', 'web' => 'Halaman web / laporan'];

    /** Isi satu sitasi dalam teks, mis. "Santoso & Lestari, 2024" atau "[3]". */
    abstract public function inText(Reference $reference, ?int $number = null): string;

    /**
     * @return list<Segment>
     */
    abstract public function entry(Reference $reference, ?int $number = null): array;

    /** Gaya bernomor (IEEE) mengurutkan daftar pustaka menurut kemunculan pertama. */
    public function numbered(): bool
    {
        return false;
    }

    /**
     * Gabungkan beberapa sitasi yang berdampingan.
     *
     * @param  list<string>  $parts
     */
    public function wrap(array $parts): string
    {
        return '('.implode('; ', $parts).')';
    }

    /**
     * @return array<string, string> key metadata => label
     */
    public function missing(Reference $reference): array
    {
        $missing = [];

        if ($reference->authors() === []) {
            $missing['authors'] = 'Penulis';
        }

        if ($reference->meta('year') === '') {
            $missing['year'] = 'Tahun';
        }

        $type = $this->type($reference);

        if ($type === 'article' && $reference->meta('publication') === '') {
            $missing['publication'] = 'Nama jurnal';
        }

        if ($type === 'book' && $reference->meta('publisher') === '') {
            $missing['publisher'] = 'Penerbit';
        }

        return $missing;
    }

    public function isComplete(Reference $reference): bool
    {
        return $this->missing($reference) === [];
    }

    /** Label ringkas penulis–tahun untuk daftar di UI dan prompt AI, apa pun gayanya. */
    public function label(Reference $reference): string
    {
        $families = array_map(self::family(...), $reference->authors());

        $names = match (count($families)) {
            0 => '',
            1 => $families[0],
            2 => "{$families[0]} & {$families[1]}",
            default => "{$families[0]} dkk.",
        };

        return "{$names}, {$reference->meta('year')}";
    }

    public function entryText(Reference $reference, ?int $number = null): string
    {
        return implode('', array_column($this->entry($reference, $number), 'text'));
    }

    /**
     * Nomor tiap referensi yang dirujuk dan lengkap, menurut kemunculan pertama di draf
     * (urut kerangka). Dipakai gaya bernomor; gaya lain boleh mengabaikannya.
     *
     * @param  Collection<int, Reference>  $references  diindeks per id
     * @return array<int, int> id referensi => nomor
     */
    public function numbers(Project $project, Collection $references): array
    {
        $numbers = [];

        foreach ($project->units() as $unit) {
            foreach (Markers::ids($project->draft[$unit['id']] ?? '') as $id) {
                $reference = $references->get($id);

                if (! isset($numbers[$id]) && $reference !== null && $this->isComplete($reference)) {
                    $numbers[$id] = count($numbers) + 1;
                }
            }
        }

        return $numbers;
    }

    /**
     * Daftar pustaka: referensi yang dirujuk di draf dan dapat diformat.
     *
     * @param  Collection<int, Reference>  $references  diindeks per id
     * @return list<array{reference: Reference, number: int|null}>
     */
    public function bibliography(Project $project, Collection $references): array
    {
        $numbers = $this->numbers($project, $references);

        if ($this->numbered()) {
            return array_map(
                fn (int $id): array => ['reference' => $references->get($id) ?? throw new \LogicException, 'number' => $numbers[$id]],
                array_keys($numbers),
            );
        }

        return array_values($references->only(array_keys($numbers))
            ->sortBy(fn (Reference $r): string => mb_strtolower($this->entryText($r)))
            ->map(fn (Reference $r): array => ['reference' => $r, 'number' => null])
            ->all());
    }

    protected function type(Reference $reference): string
    {
        $type = $reference->meta('type');

        return array_key_exists($type, self::TYPES) ? $type : 'article';
    }

    protected function doi(Reference $reference): string
    {
        return preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $reference->meta('doi')) ?? '';
    }

    /** Tautan DOI bila ada, selain itu tautan asal. */
    protected function link(Reference $reference): string
    {
        $doi = $this->doi($reference);

        return $doi !== '' ? "https://doi.org/{$doi}" : $reference->source_url;
    }

    protected function pages(Reference $reference): string
    {
        return str_replace('-', '–', $reference->meta('pages'));
    }

    protected static function isGroup(string $name): bool
    {
        return ! str_contains($name, ',');
    }

    protected static function family(string $name): string
    {
        return trim(explode(',', $name, 2)[0]);
    }

    protected static function given(string $name): string
    {
        return trim(explode(',', $name, 2)[1] ?? '');
    }

    /** "Budi Arief" → "B. A." (atau "B.A." tanpa spasi). Nama bertanda hubung: "J.-P.". */
    protected static function initials(string $given, bool $spaced = true): string
    {
        $parts = preg_split('/\s+/u', $given, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = array_map(
            fn (string $part): string => implode('-', array_map(
                fn (string $piece): string => mb_strtoupper(mb_substr($piece, 0, 1)).'.',
                array_filter(explode('-', $part), fn (string $p): bool => $p !== ''),
            )),
            $parts,
        );

        return implode($spaced ? ' ' : '', $initials);
    }

    /** "Nama Belakang, Nama Depan" → "Nama Depan Nama Belakang"; lembaga apa adanya. */
    protected static function natural(string $name): string
    {
        return self::isGroup($name) ? $name : trim(self::given($name).' '.self::family($name));
    }

    /**
     * Gabung daftar dengan koma dan kata sambung sebelum item terakhir.
     *
     * @param  list<string>  $items
     */
    protected static function join(array $items, string $and, bool $serialComma): string
    {
        $count = count($items);

        return match (true) {
            $count === 0 => '',
            $count === 1 => $items[0],
            $count === 2 => $items[0].($serialComma ? ',' : '')." {$and} ".$items[1],
            default => implode(', ', array_slice($items, 0, -1)).($serialComma ? ',' : '')." {$and} ".$items[$count - 1],
        };
    }

    /** Akhiri dengan tanda titik kecuali sudah berakhir tanda baca. */
    protected static function sentence(string $text): string
    {
        $text = trim($text);

        return preg_match('/[.?!]$/u', $text) === 1 ? $text : $text.'.';
    }

    /** Judul dalam tanda kutip dengan tanda baca di dalamnya: “Judul,” / “Judul.” */
    protected static function quoted(string $title, string $punctuation): string
    {
        $title = trim($title);

        return '"'.(preg_match('/[.?!]$/u', $title) === 1 ? $title : $title.$punctuation).'"';
    }

    /** @return Segment */
    protected static function plain(string $text): array
    {
        return ['text' => $text, 'italic' => false];
    }

    /** @return Segment */
    protected static function italic(string $text): array
    {
        return ['text' => $text, 'italic' => true];
    }

    /**
     * @param  list<Segment>  $segments
     * @return list<Segment>
     */
    protected static function merge(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            if ($segment['text'] === '') {
                continue;
            }

            $last = array_key_last($merged);

            if ($last !== null && $merged[$last]['italic'] === $segment['italic']) {
                $merged[$last]['text'] .= $segment['text'];
            } else {
                $merged[] = $segment;
            }
        }

        return $merged;
    }
}
