<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DocxTemplateRequest extends FormRequest
{
    public const FONTS = ['Times New Roman', 'Arial', 'Calibri', 'Cambria', 'Georgia', 'Book Antiqua'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:255'],
            'font_family' => ['required', Rule::in(self::FONTS)],
            'font_size' => ['required', 'integer', 'between:10,14'],
            'line_spacing' => ['required', 'numeric', Rule::in([1, 1.15, 1.5, 2])],
            'margin_top' => ['required', 'numeric', 'between:1,6'],
            'margin_bottom' => ['required', 'numeric', 'between:1,6'],
            'margin_left' => ['required', 'numeric', 'between:1,6'],
            'margin_right' => ['required', 'numeric', 'between:1,6'],
            'first_line_indent' => ['required', 'numeric', 'between:0,3'],
            'page_numbers' => ['required', 'boolean'],
            'title_page' => ['required', 'boolean'],
            'title_page_text' => ['nullable', 'string', 'max:1000'],
            'table_of_contents' => ['required', 'boolean'],
            'chapter_uppercase' => ['required', 'boolean'],
            'chapter_page_break' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama template wajib diisi.',
            'margin_top.between' => 'Margin antara 1 dan 6 cm.',
            'margin_bottom.between' => 'Margin antara 1 dan 6 cm.',
            'margin_left.between' => 'Margin antara 1 dan 6 cm.',
            'margin_right.between' => 'Margin antara 1 dan 6 cm.',
        ];
    }
}
