<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Citation\Markers;
use App\Enums\CitationStyle;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F-04: sitasi diambil dari penanda [@id] di draf; daftar pustaka hanya dari referensi yang
 * dirujuk dan metadatanya cukup. Yang belum lengkap ditampilkan sebagai "perlu dilengkapi".
 */
final class CitationController extends Controller
{
    public function index(Project $project): Response
    {
        Gate::authorize('view', $project);

        $style = $project->style();
        $references = $project->references->keyBy('id');
        $numbers = $style->numbers($project, $references);
        $citations = [];

        foreach ($project->units() as $unit) {
            foreach (Markers::ids($project->draft[$unit['id']] ?? '') as $id) {
                $reference = $references->get($id);

                $citations[] = [
                    'unit_id' => $unit['id'],
                    'unit' => "{$unit['number']} {$unit['title']}",
                    'reference_id' => $id,
                    'title' => $reference->title ?? 'Referensi tidak ditemukan',
                    'text' => $reference && $style->isComplete($reference) ? $style->wrap([$style->inText($reference, $numbers[$id] ?? null)]) : null,
                    'missing' => $reference ? array_values($style->missing($reference)) : [],
                ];
            }
        }

        $cited = array_values(array_unique(array_column($citations, 'reference_id')));

        return Inertia::render('projects/Citations', [
            'project' => $project->summary(),
            'styleLabel' => ($project->citation_style ?? CitationStyle::Apa7)->label(),
            'citations' => $citations,
            'available' => $references->map(fn (Reference $reference): array => [
                'id' => $reference->id,
                'title' => $reference->title,
                'text' => $style->isComplete($reference)
                    ? ($style->numbered() && ! isset($numbers[$reference->id]) ? $style->label($reference) : $style->wrap([$style->inText($reference, $numbers[$reference->id] ?? null)]))
                    : null,
                'missing' => array_values($style->missing($reference)),
                'used' => in_array($reference->id, $cited, true),
            ])->values(),
            'bibliography' => array_map(fn (array $row): array => [
                'id' => $row['reference']->id,
                'segments' => $style->entry($row['reference'], $row['number']),
            ], $style->bibliography($project, $references)),
            'incomplete' => $references->only($cited)
                ->reject(fn (Reference $reference): bool => $style->isComplete($reference))
                ->map(fn (Reference $reference): array => [
                    'id' => $reference->id,
                    'title' => $reference->title,
                    'missing' => array_values($style->missing($reference)),
                ])->values(),
            'citationStyles' => CitationStyle::options(),
        ]);
    }
}
