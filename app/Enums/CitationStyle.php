<?php

declare(strict_types=1);

namespace App\Enums;

use App\Citation\Apa7;
use App\Citation\Chicago;
use App\Citation\Harvard;
use App\Citation\Ieee;
use App\Citation\Mla9;
use App\Citation\Style;
use App\Models\Setting;

/**
 * Gaya yang formatter dan tesnya sudah tersedia (PRD F-04). Admin dapat menonaktifkan gaya
 * agar tidak muncul sebagai pilihan bagi pengguna.
 */
enum CitationStyle: string
{
    case Apa7 = 'apa7';
    case Ieee = 'ieee';
    case Harvard = 'harvard';
    case Chicago = 'chicago';
    case Mla9 = 'mla9';

    public function label(): string
    {
        return match ($this) {
            self::Apa7 => 'APA edisi 7',
            self::Ieee => 'IEEE',
            self::Harvard => 'Harvard',
            self::Chicago => 'Chicago author-date',
            self::Mla9 => 'MLA edisi 9',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Apa7 => 'Penulis–tahun: (Santoso & Lestari, 2024)',
            self::Ieee => 'Bernomor sesuai urutan kemunculan: [1]',
            self::Harvard => 'Penulis–tahun: (Santoso and Lestari, 2024)',
            self::Chicago => 'Penulis tahun: (Santoso and Lestari 2024)',
            self::Mla9 => 'Penulis: (Santoso and Lestari)',
        };
    }

    public function formatter(): Style
    {
        return match ($this) {
            self::Apa7 => new Apa7,
            self::Ieee => new Ieee,
            self::Harvard => new Harvard,
            self::Chicago => new Chicago,
            self::Mla9 => new Mla9,
        };
    }

    /**
     * Gaya yang boleh dipilih pengguna (diatur admin). Default: semua gaya aktif.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        $values = Setting::read('citation_styles.enabled');

        if (! is_array($values)) {
            return self::cases();
        }

        return array_values(array_filter(self::cases(), fn (self $style): bool => in_array($style->value, $values, true)));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $style): array => ['value' => $style->value, 'label' => $style->label()],
            self::enabled(),
        );
    }
}
