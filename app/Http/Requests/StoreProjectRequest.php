<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            // Ringkasan diskusi judul (masalah, objek, data, metode) agar tidak hilang setelah judul dipilih.
            'idea' => ['nullable', 'string', 'max:6000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul wajib diisi dan tidak boleh hanya berisi spasi.',
            'document_type.required' => 'Pilih jenis tulisan.',
        ];
    }
}
