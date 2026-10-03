<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CitationStyle;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jenis tulisan sengaja tidak bisa diubah: aturannya setelah kerangka dibuat belum ditentukan (PRD F-02).
 */
final class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('update', $project) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'citation_style' => ['sometimes', 'nullable', Rule::in(array_map(fn (CitationStyle $s): string => $s->value, CitationStyle::enabled()))],
            'docx_template_id' => ['sometimes', 'nullable', 'integer', 'exists:docx_templates,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul wajib diisi dan tidak boleh hanya berisi spasi.',
            'citation_style.in' => 'Gaya sitasi ini belum tersedia.',
        ];
    }
}
