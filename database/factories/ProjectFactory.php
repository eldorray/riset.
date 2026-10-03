<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(6),
            'document_type' => DocumentType::Skripsi,
        ];
    }

    public function withOutline(): static
    {
        return $this->state(fn (): array => [
            'outline' => [
                ['id' => 'c1', 'title' => 'Pendahuluan', 'sections' => [
                    ['id' => 's1', 'title' => 'Latar Belakang'],
                    ['id' => 's2', 'title' => 'Rumusan Masalah'],
                ]],
                ['id' => 'c2', 'title' => 'Penutup', 'sections' => []],
            ],
        ]);
    }
}
