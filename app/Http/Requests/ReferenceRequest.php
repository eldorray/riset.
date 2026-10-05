<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Citation\Style;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Referensi (F-03) dari input manual atau hasil pencarian: judul dan tautan asal wajib, metadata lain hanya yang diketahui.
 */
final class ReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('update', $project) === true;
    }

    protected function prepareForValidation(): void
    {
        $keywords = $this->input('metadata.keywords');
        if (is_array($keywords) && count(array_filter($keywords, fn ($keyword): bool => is_string($keyword) || $keyword === null)) === count($keywords)) {
            $this->merge(['metadata' => [
                ...(array) $this->input('metadata'),
                'keywords' => array_values(array_unique(array_filter(array_map(
                    fn ($keyword) => is_string($keyword) ? mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $keyword))) : $keyword,
                    $keywords,
                ), fn ($keyword) => $keyword !== '' && $keyword !== null))),
            ]]);
        }

        $authors = $this->input('metadata.authors');

        if (is_array($authors)) {
            $this->merge(['metadata' => [
                ...(array) $this->input('metadata'),
                'authors' => array_values(array_filter(array_map(fn ($a) => is_string($a) ? trim($a) : $a, $authors), fn ($a) => $a !== '' && $a !== null)),
            ]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'source_url' => ['required', 'url:http,https', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'input_method' => ['nullable', Rule::in(['manual', 'search'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'notes_reviewed' => ['boolean'],
            'metadata' => ['required', 'array'],
            'metadata.open_access_url' => ['nullable', 'url:http,https', 'max:2048'],
            'metadata.type' => ['required', Rule::in(array_keys(Style::TYPES))],
            'metadata.authors' => ['nullable', 'array', 'max:50'],
            'metadata.authors.*' => ['string', 'max:255'],
            'metadata.year' => ['nullable', 'string', 'regex:/^(\d{4}[a-z]?|n\.d\.)$/'],
            'metadata.publication' => ['nullable', 'string', 'max:500'],
            'metadata.volume' => ['nullable', 'string', 'max:50'],
            'metadata.issue' => ['nullable', 'string', 'max:50'],
            'metadata.pages' => ['nullable', 'string', 'max:50'],
            'metadata.publisher' => ['nullable', 'string', 'max:255'],
            'metadata.keywords' => ['nullable', 'array', 'max:20'],
            'metadata.keywords.*' => ['string', 'max:200'],
            'metadata.doi' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Hanya field yang diisi yang disimpan, supaya "belum ada" tetap terlihat kosong.
     *
     * @return array{title: string, source_url: string, source_name: ?string, input_method: string, notes: ?string, metadata: array<string, mixed>}
     */
    public function payload(): array
    {
        /** @var array{title: string, source_url: string, source_name?: ?string, input_method?: ?string, notes?: ?string, metadata: array<string, mixed>} $data */
        $data = $this->validated();

        return [
            'title' => $data['title'],
            'source_url' => $data['source_url'],
            'source_name' => $data['source_name'] ?? null,
            'input_method' => $data['input_method'] ?? 'manual',
            // Catatan AI baru boleh dipakai untuk sitasi setelah pengguna menyatakan sudah memeriksanya.
            'notes' => isset($data['notes']) && $this->boolean('notes_reviewed') ? Reference::markReviewed($data['notes']) : ($data['notes'] ?? null),
            'metadata' => array_filter($data['metadata'], fn ($v) => $v !== null && $v !== '' && $v !== []),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'Judul', 'source_url' => 'Tautan asal', 'notes' => 'Catatan isi',
            'metadata.authors' => 'Daftar penulis', 'metadata.authors.*' => 'Nama penulis',
            'metadata.year' => 'Tahun', 'metadata.publication' => 'Nama jurnal atau situs',
            'metadata.volume' => 'Volume', 'metadata.issue' => 'Nomor jurnal', 'metadata.pages' => 'Halaman',
            'metadata.publisher' => 'Penerbit', 'metadata.doi' => 'DOI',
            'metadata.keywords' => 'Kata kunci', 'metadata.keywords.*' => 'Kata kunci',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul referensi wajib diisi.',
            'metadata.authors.*.max' => 'Nama setiap penulis maksimal 255 karakter.',
            'metadata.*.max' => ':attribute melewati batas maksimum (:max).',
            'source_url.required' => 'Tautan asal wajib diisi.',
            'source_url.url' => 'Tautan asal harus berupa URL http(s) yang valid.',
            'metadata.year.regex' => 'Tahun ditulis 4 angka (mis. 2024) atau n.d. bila sumber memang tidak bertanggal.',
        ];
    }
}
