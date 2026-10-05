<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Metadata hanya berisi yang benar-benar diketahui pengguna; field kosong tidak ditebak.
 *
 * @phpstan-type Metadata array{open_access_url?: string, type?: string, authors?: list<string>, year?: string, publication?: string, volume?: string, issue?: string, pages?: string, publisher?: string, doi?: string, keywords?: list<string>}
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string $source_url
 * @property string|null $source_name
 * @property string $input_method search|manual
 * @property Metadata|null $metadata
 * @property string|null $notes ringkasan/kutipan dari pengguna — satu-satunya isi sumber yang boleh dipakai AI
 * @property array<string, string>|null $removed_citations teks bagian draf sebelum sitasinya dibuang saat dihapus (untuk Batalkan)
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'source_url', 'source_name', 'input_method', 'metadata', 'notes', 'removed_citations'])]
class Reference extends Model
{
    /** @use HasFactory<ReferenceFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'project_references';

    /** Baris pertama catatan hasil baca AI; diganti REVIEWED setelah pengguna memeriksanya. */
    public const AI_NOTES_PENDING = 'Catatan AI · belum diperiksa';

    public const AI_NOTES_REVIEWED = 'Catatan AI · ditinjau pengguna';

    /** Catatan AI yang belum diperiksa pengguna tidak boleh menjadi dasar sitasi. */
    public function notesPending(): bool
    {
        return str_starts_with((string) $this->notes, self::AI_NOTES_PENDING);
    }

    public function notesUsable(): bool
    {
        return filled($this->notes) && ! $this->notesPending();
    }

    /** Catatan AI yang hanya berdasar abstrak: cukup untuk gambaran umum, bukan detail metode atau angka. */
    public function abstractOnly(): bool
    {
        return str_contains((string) $this->notes, "\nDasar: Abstrak saja");
    }

    public static function markReviewed(string $notes): string
    {
        return str_starts_with($notes, self::AI_NOTES_PENDING) ? self::AI_NOTES_REVIEWED.substr($notes, strlen(self::AI_NOTES_PENDING)) : $notes;
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'removed_citations' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function meta(string $key): string
    {
        $value = $this->metadata[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    public function authors(): array
    {
        $authors = $this->metadata['authors'] ?? [];

        return array_values(array_filter(array_map('trim', $authors), fn (string $a): bool => $a !== ''));
    }
}
