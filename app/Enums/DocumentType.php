<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Setting;

enum DocumentType: string
{
    case Skripsi = 'skripsi';
    case Tesis = 'tesis';
    case KaryaIlmiah = 'karya_ilmiah';
    case Artikel = 'artikel';

    /** Katalog bagian awal naskah; urutan dan pemakaian per jenis di config/riset.php. */
    public const FRONT_MATTER = [
        'abstrak' => ['heading' => 'ABSTRAK', 'label' => 'Abstrak', 'keywords' => 'Kata kunci', 'hint' => 'Ringkasan 150–250 kata dari isi draf.'],
        'abstract' => ['heading' => 'ABSTRACT', 'label' => 'Abstract (bahasa Inggris)', 'keywords' => 'Keywords', 'hint' => 'Terjemahan abstrak ke bahasa Inggris.'],
        'kata_pengantar' => ['heading' => 'KATA PENGANTAR', 'label' => 'Kata pengantar', 'keywords' => null, 'hint' => 'Ucapan terima kasih dan pengantar penulis.'],
    ];

    public function label(): string
    {
        return match ($this) {
            self::Skripsi => 'Skripsi',
            self::Tesis => 'Tesis',
            self::KaryaIlmiah => 'Karya ilmiah',
            self::Artikel => 'Artikel',
        };
    }

    /** Naskah skripsi & tesis berupa buku (tiap bagian awal di halaman sendiri); artikel & karya ilmiah ringkas. */
    public function isBook(): bool
    {
        return in_array($this, [self::Skripsi, self::Tesis], true);
    }

    /**
     * Bagian awal naskah lengkap untuk jenis ini.
     *
     * @return list<array{key: string, heading: string, label: string, keywords: string|null, hint: string}>
     */
    public function frontMatter(): array
    {
        /** @var list<string> $keys */
        $keys = config("riset.front_matter.{$this->value}", []);

        return array_values(array_map(
            fn (string $key): array => ['key' => $key, ...self::FRONT_MATTER[$key]],
            array_filter($keys, fn (string $key): bool => isset(self::FRONT_MATTER[$key])),
        ));
    }

    /** Skripsi & tesis memakai "BAB I"; karya ilmiah & artikel memakai "1." (dapat diubah admin). */
    public function usesBabNumbering(): bool
    {
        return $this->settings()['numbering'] === 'bab';
    }

    /**
     * @return list<array{title: string, sections: list<string>}>
     */
    public function structure(): array
    {
        return $this->settings()['chapters'];
    }

    /**
     * Struktur yang diatur admin, atau contoh bawaan dari config/riset.php.
     *
     * @return array{numbering: string, chapters: list<array{title: string, sections: list<string>}>}
     */
    public function settings(): array
    {
        // once(): dibaca sekali per request, karena units() memanggilnya untuk setiap bab.
        return once(function (): array {
            /** @var array{numbering: string, chapters: list<array{title: string, sections: list<string>}>} $default */
            $default = config("riset.structures.{$this->value}");
            $custom = Setting::read("structures.{$this->value}");

            if (! is_array($custom) || ! in_array($custom['numbering'] ?? null, ['bab', 'angka'], true) || ! is_array($custom['chapters'] ?? null)) {
                return $default;
            }

            // Normalisasi: pengaturan tersimpan tidak dipercaya mentah-mentah.
            return [
                'numbering' => $custom['numbering'],
                'chapters' => array_values(array_map(fn (mixed $chapter): array => [
                    'title' => is_array($chapter) ? (string) ($chapter['title'] ?? '') : '',
                    'sections' => is_array($chapter) ? array_values(array_map(strval(...), (array) ($chapter['sections'] ?? []))) : [],
                ], $custom['chapters'])),
            ];
        });
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
