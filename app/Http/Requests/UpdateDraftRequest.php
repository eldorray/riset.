<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Citation\Markers;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sitasi di draf harus merujuk referensi di proyek yang sama (AC F-04, F-06).
 */
final class UpdateDraftRequest extends FormRequest
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
        $units = array_column($this->project()->units(), 'id');

        if ($units === []) {
            return ['draft' => [fn (string $attribute, mixed $value, \Closure $fail) => $fail('Simpan kerangka lebih dulu sebelum menulis draf.')]];
        }

        return [
            'draft' => ['present', 'array:'.implode(',', $units)],
            'base' => [$this->expectsJson() ? 'required' : 'sometimes', 'array:'.implode(',', $units)],
            'base.*' => ['nullable', 'string', 'max:100000'],
            'draft.*' => ['nullable', 'string', 'max:100000'],
            'reviewed' => ['nullable', 'array'],
            'reviewed.*' => ['string', Rule::in($units)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'draft.array' => 'Draf berisi bagian yang tidak ada di kerangka tersimpan.',
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->expectsJson() || $this->has('base')) {
                $keys = array_unique([...array_keys((array) $this->input('draft')), ...(array) $this->input('reviewed', [])]);
                foreach ($keys as $id) {
                    if (! array_key_exists($id, (array) $this->input('base'))) {
                        $validator->errors()->add('base', 'Teks dasar bagian wajib dikirim untuk mencegah konflik.');
                        break;
                    }
                }
            }
            $cited = Markers::ids(implode("\n", array_filter((array) $this->input('draft'), 'is_string')));
            $known = $this->project()->references()->whereKey($cited)->count();

            if ($known !== count($cited)) {
                $validator->errors()->add('draft', 'Sitasi merujuk referensi yang tidak ada di proyek ini.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function draft(): array
    {
        /** @var array<string, string|null> $draft */
        $draft = $this->validated('draft');

        return array_map(fn (?string $text): string => $text ?? '', $draft);
    }

    private function project(): Project
    {
        $project = $this->route('project');
        abort_unless($project instanceof Project, 404);

        return $project;
    }
}
