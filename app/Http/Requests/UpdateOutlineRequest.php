<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateOutlineRequest extends FormRequest
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
            'outline' => ['required', 'array', 'min:1', 'max:30'],
            'outline.*.id' => ['required', 'string', 'max:40'],
            'outline.*.title' => ['required', 'string', 'max:255'],
            'outline.*.sections' => ['present', 'array', 'max:30'],
            'outline.*.sections.*.id' => ['required', 'string', 'max:40'],
            'outline.*.sections.*.title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outline.*.title.required' => 'Judul bab tidak boleh kosong.',
            'outline.*.sections.*.title.required' => 'Judul subbab tidak boleh kosong.',
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $ids = [];

            foreach ((array) $this->input('outline') as $chapter) {
                $ids[] = $chapter['id'] ?? null;

                foreach ((array) ($chapter['sections'] ?? []) as $section) {
                    $ids[] = $section['id'] ?? null;
                }
            }

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('outline', 'Setiap bab dan subbab harus punya id unik.');
            }
        }];
    }

    /**
     * @return list<array{id: string, title: string, sections: list<array{id: string, title: string}>}>
     */
    public function outline(): array
    {
        return array_values(array_map(fn (array $chapter): array => [
            'id' => $chapter['id'],
            'title' => $chapter['title'],
            'sections' => array_values(array_map(
                fn (array $section): array => ['id' => $section['id'], 'title' => $section['title']],
                $chapter['sections'],
            )),
        ], $this->validated('outline')));
    }
}
