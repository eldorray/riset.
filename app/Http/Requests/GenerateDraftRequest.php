<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->project()) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit' => ['required', 'string', Rule::in(array_column($this->project()->units(), 'id'))],
            'references' => ['present', 'array', 'max:20'],
            'references.*' => ['integer', 'distinct', Rule::exists('project_references', 'id')->where('project_id', $this->project()->id)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit.in' => 'Bagian tidak ada di kerangka tersimpan. Simpan kerangka lebih dulu.',
            'references.*.exists' => 'Referensi harus berasal dari proyek ini.',
        ];
    }

    public function project(): Project
    {
        $project = $this->route('project');
        abort_unless($project instanceof Project, 404);

        return $project;
    }
}
