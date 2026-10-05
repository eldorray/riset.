<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateResearchDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return Gate::allows('update', $project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'research_data' => ['nullable', 'string', 'max:30000'],
            'design' => ['present', 'array'],
            'design.pendekatan' => ['nullable', Rule::in(array_keys(Project::APPROACHES))],
        ];
        foreach (array_keys(Project::DESIGN_FIELDS) as $key) {
            $rules["design.{$key}"] ??= ['nullable', 'string', 'max:3000'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'Judul', 'research_data' => 'Data dan temuan', ...array_combine(
            array_map(fn (string $key): string => "design.{$key}", array_keys(Project::DESIGN_FIELDS)),
            array_values(Project::DESIGN_FIELDS),
        )];
    }

    /** @return array<string, string> hanya field yang diisi */
    public function design(): array
    {
        $design = array_intersect_key((array) $this->validated('design'), Project::DESIGN_FIELDS);

        return array_filter(array_map(fn (mixed $value): string => trim((string) $value), $design), fn (string $value): bool => $value !== '');
    }
}
