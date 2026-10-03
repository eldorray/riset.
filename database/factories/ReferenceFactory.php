<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Reference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reference>
 */
class ReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'Literasi digital dan kemandirian belajar mahasiswa',
            'source_url' => 'https://doi.org/10.0000/contoh.2024.001',
            'metadata' => [
                'type' => 'article',
                'authors' => ['Santoso, Budi Arief', 'Lestari, Dewi'],
                'year' => '2024',
                'publication' => 'Jurnal Contoh Pendidikan',
                'volume' => '12',
                'issue' => '3',
                'pages' => '45-60',
                'doi' => '10.0000/contoh.2024.001',
            ],
        ];
    }

    public function incomplete(): static
    {
        return $this->state(fn (): array => [
            'title' => 'Laporan survei penggunaan perpustakaan digital',
            'source_url' => 'https://contoh.ac.id/laporan',
            'metadata' => ['type' => 'web'],
        ]);
    }
}
