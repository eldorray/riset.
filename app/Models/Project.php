<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\GenerateResearchGap;
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
 * @phpstan-type Unit array{id: string, number: string, title: string, level: int, kind: 'literatur'|'metode'|'empiris'}
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
 * @property array<string, mixed>|null $gap_analysis
 * @property array<string, mixed>|null $research_gap
 * @property array<string, string>|null $research_design rancangan penelitian (lihat DESIGN_FIELDS)
 * @property string|null $research_data data/temuan penelitian milik pengguna — satu-satunya dasar bab hasil
 */
#[Fillable(['title', 'document_type', 'citation_style', 'docx_template_id', 'outline', 'draft', 'front_matter', 'ai_units', 'research_design', 'research_data'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const DESIGN_FIELDS = [
        'masalah' => 'Rumusan masalah / pertanyaan penelitian',
        'tujuan' => 'Tujuan penelitian',
        'hipotesis' => 'Hipotesis',
        'pendekatan' => 'Pendekatan',
        'desain' => 'Jenis atau desain penelitian',
        'subjek' => 'Subjek, populasi, sampel, atau objek',
        'pengumpulan' => 'Teknik pengumpulan data dan instrumen',
        'analisis' => 'Teknik analisis data',
        'ide' => 'Catatan dari diskusi judul',
    ];

    public const APPROACHES = [
        'kuantitatif' => 'Kuantitatif',
        'kualitatif' => 'Kualitatif',
        'campuran' => 'Campuran (mixed methods)',
        'studi_literatur' => 'Studi literatur',
    ];

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
            'gap_analysis' => 'array',
            'research_gap' => 'array',
            'research_design' => 'array',
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

    public function design(string $key): string
    {
        return trim((string) ($this->research_design[$key] ?? ''));
    }

    public function isLiteratureStudy(): bool
    {
        return $this->design('pendekatan') === 'studi_literatur';
    }

    /** Minimal untuk menulis bab metode tanpa menebak: masalah, pendekatan, dan teknik analisis. */
    public function designReady(): bool
    {
        return $this->design('masalah') !== '' && $this->design('pendekatan') !== '' && $this->design('analisis') !== '';
    }

    public function hasResearchData(): bool
    {
        return trim((string) $this->research_data) !== '';
    }

    /**
     * Jenis bab dari judulnya: bab hasil/penutup bergantung pada data pengguna, bab metode pada rancangan.
     * ponytail: pencocokan kata kunci judul bab; judul bab di luar pola ini dianggap bab literatur.
     * Simpan jenis per bab di kerangka bila admin memakai judul bab yang jauh berbeda.
     *
     * @return 'literatur'|'metode'|'empiris'
     */
    public static function chapterKind(string $title): string
    {
        $title = mb_strtolower($title);

        return match (true) {
            (bool) preg_match('/metode|metodologi/u', $title) => 'metode',
            (bool) preg_match('/hasil|pembahasan|temuan|diskusi|kesimpulan|simpulan|penutup/u', $title) => 'empiris',
            default => 'literatur',
        };
    }

    /**
     * Alasan AI tidak boleh menulis bagian ini, atau null bila boleh.
     *
     * @param  Unit  $unit
     */
    public function blockedReason(array $unit): ?string
    {
        if ($unit['kind'] === 'metode' && ! $this->designReady()) {
            return 'Bagian metode memerlukan rancangan penelitian (rumusan masalah, pendekatan, dan teknik analisis). Isi di Rancangan penelitian.';
        }
        if ($unit['kind'] === 'empiris' && ! $this->isLiteratureStudy() && ! $this->hasResearchData()) {
            return 'Bagian hasil, pembahasan, dan kesimpulan memerlukan data atau temuan penelitian Anda. Isi di Rancangan penelitian; AI tidak menulis hasil tanpa data.';
        }

        return null;
    }

    /** Rancangan yang diisi pengguna, untuk konteks prompt AI. */
    public function designContext(): string
    {
        $lines = [];
        foreach (self::DESIGN_FIELDS as $key => $label) {
            $value = $this->design($key);
            if ($value === '') {
                continue;
            }
            $lines[] = match ($key) {
                'pendekatan' => "- {$label}: ".(self::APPROACHES[$value] ?? $value),
                'ide' => "- {$label} (gagasan awal, bukan fakta): {$value}",
                default => "- {$label}: {$value}",
            };
        }

        return $lines === [] ? '' : "Rancangan penelitian dari pengguna (acuan wajib; jangan mengubah maknanya, jangan menambah detail yang tidak ada):\n".implode("\n", $lines);
    }

    /** Arah penelitian yang ditinjau pengguna, bukan sumber bukti untuk sitasi. */
    public function researchGapContext(): string
    {
        if (! $this->research_gap) {
            return '';
        }
        foreach ($this->research_gap['sources'] as $source) {
            $reference = $this->references()->whereKey($source['id'])->first();
            if (! $reference || GenerateResearchGap::fingerprint($reference) !== $source['fingerprint']) {
                return '';
            }
        }

        return 'Arah penelitian pilihan pengguna (bukan bukti kebaruan terverifikasi; klaim tetap harus didukung sumber yang diizinkan): '
            .json_encode(array_intersect_key($this->research_gap, array_flip(['gap', 'question', 'contribution'])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
            $kind = self::chapterKind($chapter['title']);
            if ($chapter['sections'] === []) {
                $units[] = ['id' => $chapter['id'], 'number' => $this->chapterLabel($i), 'title' => $chapter['title'], 'level' => 1, 'kind' => $kind];

                continue;
            }

            foreach ($chapter['sections'] as $j => $section) {
                $units[] = ['id' => $section['id'], 'number' => ($i + 1).'.'.($j + 1), 'title' => $section['title'], 'level' => 2, 'kind' => $kind];
            }
        }

        return $units;
    }

    /** @return Unit|null */
    public function unit(string $id): ?array
    {
        foreach ($this->units() as $unit) {
            if ($unit['id'] === $id) {
                return $unit;
            }
        }

        return null;
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
            'design_ready' => $this->designReady(),
            'has_data' => $this->hasResearchData(),
            'literature_study' => $this->isLiteratureStudy(),
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
