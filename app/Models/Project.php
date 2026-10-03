<?php

declare(strict_types=1);

namespace App\Models;

use App\Citation\Style;
use App\Enums\CitationStyle;
use App\Enums\DocumentType;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kerangka: list bab, tiap bab punya subbab. Bab tanpa subbab ditulis langsung.
 *
 * @phpstan-type Outline list<array{id: string, title: string, sections: list<array{id: string, title: string}>}>
 * @phpstan-type Unit array{id: string, number: string, title: string, level: int}
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property DocumentType $document_type
 * @property CitationStyle|null $citation_style
 * @property int|null $docx_template_id
 * @property-read DocxTemplate|null $docxTemplate
 * @property-read int|null $references_count
 * @property Outline|null $outline
 * @property array<string, string>|null $draft teks per unit (id bab/subbab => teks dengan penanda [@id])
 * @property array<string, array{text?: string, keywords?: string, ai?: bool}>|null $front_matter bagian awal naskah (abstrak, kata pengantar, …)
 * @property list<string>|null $ai_units id bagian draf yang ditulis AI dan belum ditinjau
 * @property CarbonImmutable|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'document_type', 'citation_style', 'docx_template_id', 'outline', 'draft', 'front_matter', 'ai_units'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'citation_style' => CitationStyle::class,
            'outline' => 'array',
            'draft' => 'array',
            'front_matter' => 'array',
            'ai_units' => 'array',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Reference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(Reference::class);
    }

    /**
     * @return BelongsTo<DocxTemplate, $this>
     */
    public function docxTemplate(): BelongsTo
    {
        return $this->belongsTo(DocxTemplate::class);
    }

    /** Formatter gaya sitasi proyek; APA 7 bila belum dipilih. */
    public function style(): Style
    {
        return ($this->citation_style ?? CitationStyle::Apa7)->formatter();
    }

    /** @return array{name: string, table_of_contents: bool, title_page: bool, citation_style: string, font: string} */
    public function exportFormat(): array
    {
        $template = $this->docxTemplate ?? DocxTemplate::fallback();

        return [
            'name' => $template->name,
            'table_of_contents' => $template->table_of_contents,
            'title_page' => $template->title_page,
            'citation_style' => ($this->citation_style ?? CitationStyle::Apa7)->label(),
            'font' => $template->font_family.' '.$template->font_size.' pt',
        ];
    }

    public function chapterLabel(int $index): string
    {
        return $this->document_type->usesBabNumbering()
            ? 'BAB '.self::roman($index + 1)
            : ($index + 1).'.';
    }

    /**
     * Bagian yang bisa ditulis, urut sesuai kerangka tersimpan.
     *
     * @return list<Unit>
     */
    public function units(): array
    {
        $units = [];

        foreach ($this->outline ?? [] as $i => $chapter) {
            if ($chapter['sections'] === []) {
                $units[] = ['id' => $chapter['id'], 'number' => $this->chapterLabel($i), 'title' => $chapter['title'], 'level' => 1];

                continue;
            }

            foreach ($chapter['sections'] as $j => $section) {
                $units[] = ['id' => $section['id'], 'number' => ($i + 1).'.'.($j + 1), 'title' => $section['title'], 'level' => 2];
            }
        }

        return $units;
    }

    /**
     * Props ringkas untuk navigasi dan daftar proyek; hanya field yang perlu ke browser.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $units = $this->units();
        $filled = array_filter($units, fn (array $unit): bool => trim($this->draft[$unit['id']] ?? '') !== '');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'document_type' => ['value' => $this->document_type->value, 'label' => $this->document_type->label()],
            'citation_style' => $this->citation_style ? ['value' => $this->citation_style->value, 'label' => $this->citation_style->label()] : null,
            'docx_template_id' => $this->docx_template_id,
            'references_count' => $this->references_count ?? $this->references()->count(),
            'chapters' => count($this->outline ?? []),
            'units' => count($units),
            'filled' => count($filled),
            'front_parts' => count($this->document_type->frontMatter()),
            'front_filled' => count($this->document_type->frontMatter()) - count($this->missingFrontMatter()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Jumlah kata draf + bagian awal (penanda sitasi tidak dihitung). */
    public function words(): int
    {
        $texts = [
            ...array_map(fn (array $unit): string => $this->draft[$unit['id']] ?? '', $this->units()),
            ...array_map(fn (array $part): string => $this->frontText($part['key']), $this->document_type->frontMatter()),
        ];

        return array_sum(array_map(
            fn (string $text): int => count(preg_split('/\s+/u', trim((string) preg_replace('/\[@\d+\]/', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            $texts,
        ));
    }

    public function frontText(string $key): string
    {
        return trim((string) ($this->front_matter[$key]['text'] ?? ''));
    }

    /**
     * Bagian awal yang belum diisi, untuk peringatan sebelum ekspor.
     *
     * @return list<string> label bagian
     */
    public function missingFrontMatter(): array
    {
        return array_values(array_map(
            fn (array $part): string => $part['label'],
            array_filter($this->document_type->frontMatter(), fn (array $part): bool => $this->frontText($part['key']) === ''),
        ));
    }

    private static function roman(int $number): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $result = '';

        foreach ($map as $symbol => $value) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }

        return $result;
    }
}
