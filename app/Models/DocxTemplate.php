<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Format Word per institusi. Ukuran dalam cm; aplikasi tetap tidak menyatakan file sesuai
 * panduan resmi — kesesuaian template dengan panduan adalah tanggung jawab admin.
 *
 * @property int $id
 * @property string $name
 * @property string|null $institution
 * @property string $font_family
 * @property int $font_size
 * @property float $line_spacing
 * @property float $margin_top
 * @property float $margin_bottom
 * @property float $margin_left
 * @property float $margin_right
 * @property float $first_line_indent
 * @property bool $page_numbers
 * @property bool $title_page
 * @property string|null $title_page_text
 * @property bool $table_of_contents
 * @property bool $chapter_uppercase
 * @property bool $chapter_page_break
 */
#[Fillable([
    'name', 'institution', 'font_family', 'font_size', 'line_spacing',
    'margin_top', 'margin_bottom', 'margin_left', 'margin_right', 'first_line_indent',
    'page_numbers', 'title_page', 'title_page_text', 'table_of_contents', 'chapter_uppercase', 'chapter_page_break',
])]
class DocxTemplate extends Model
{
    public const DEFAULTS = [
        'font_family' => 'Times New Roman',
        'font_size' => 12,
        'line_spacing' => 1.5,
        'margin_top' => 3.0,
        'margin_bottom' => 3.0,
        'margin_left' => 4.0,
        'margin_right' => 3.0,
        'first_line_indent' => 1.25,
        'page_numbers' => true,
        'title_page' => false,
        'title_page_text' => null,
        'table_of_contents' => false,
        'chapter_uppercase' => true,
        'chapter_page_break' => false,
    ];

    protected function casts(): array
    {
        return [
            'font_size' => 'integer',
            'line_spacing' => 'float',
            'margin_top' => 'float',
            'margin_bottom' => 'float',
            'margin_left' => 'float',
            'margin_right' => 'float',
            'first_line_indent' => 'float',
            'page_numbers' => 'boolean',
            'title_page' => 'boolean',
            'table_of_contents' => 'boolean',
            'chapter_uppercase' => 'boolean',
            'chapter_page_break' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Template bawaan (tidak tersimpan) bila proyek belum memilih template. */
    public static function fallback(): self
    {
        return new self(['name' => 'Format bawaan', ...self::DEFAULTS]);
    }
}
