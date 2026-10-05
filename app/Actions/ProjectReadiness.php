<?php

declare(strict_types=1);

namespace App\Actions;

use App\Citation\Markers;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Support\Collection;

/**
 * Ringkasan kesiapan proyek: dipakai di halaman detail dan sebagai peringatan sebelum ekspor (F-07).
 */
final class ProjectReadiness
{
    /**
     * @param  Collection<int, Reference>  $references  diindeks per id
     * @return array{units: int, filled: int, empty: list<string>, citations: int, cited: list<int>, cited_incomplete: list<array{id: int, title: string, missing: list<string>}>, unknown: int, references: int, references_incomplete: int, front_parts: int, front_missing: list<string>, words: int, ai_unreviewed: int, next: array{label: string, href: string}, empty_href: string, review_href: string, design_ready: bool, needs_data: bool, notes_pending: int}
     */
    public function __invoke(Project $project, Collection $references): array
    {
        $style = $project->style();
        $empty = [];
        $citations = 0;
        $cited = [];

        foreach ($project->units() as $unit) {
            $text = trim($project->draft[$unit['id']] ?? '');

            if ($text === '') {
                $empty[] = "{$unit['number']} {$unit['title']}";

                continue;
            }

            preg_match_all('/\[@\d+\]/', $text, $all);
            $citations += count($all[0]);
            $cited = [...$cited, ...Markers::ids($text)];
        }

        $cited = array_values(array_unique($cited));
        $incomplete = [];
        $unknown = 0;

        foreach ($cited as $id) {
            $reference = $references->get($id);

            if ($reference === null) {
                $unknown++;
            } elseif (! $style->isComplete($reference)) {
                $incomplete[] = ['id' => $reference->id, 'title' => $reference->title, 'missing' => array_values($style->missing($reference))];
            }
        }

        $firstEmpty = array_values(array_filter($project->units(), fn (array $unit): bool => trim($project->draft[$unit['id']] ?? '') === ''))[0] ?? null;
        $firstReview = collect($project->units())->first(fn (array $unit): bool => in_array($unit['id'], $project->ai_units ?? [], true));
        $firstFrontReview = array_key_first(array_filter($project->front_matter ?? [], fn (array $part): bool => ($part['ai'] ?? false) === true));
        $emptyHref = route('projects.draft', ['project' => $project, 'unit' => $firstEmpty['id'] ?? null]);
        $reviewHref = $firstReview
            ? route('projects.draft', ['project' => $project, 'unit' => $firstReview['id']])
            : route('projects.manuscript', $project).'#front-'.($firstFrontReview ?? 'abstrak');
        $needsNotes = $references->first(fn (Reference $r): bool => ! $r->notesUsable());
        $needsData = ! $project->isLiteratureStudy() && ! $project->hasResearchData()
            && collect($project->units())->contains(fn (array $unit): bool => $unit['kind'] === 'empiris');
        $next = match (true) {
            $references->isEmpty() => ['label' => 'Tambah referensi', 'href' => route('projects.references.index', $project)],
            $references->every(fn (Reference $r): bool => ! $r->notesUsable()) && $needsNotes !== null => ['label' => $needsNotes->notesPending() ? 'Tinjau catatan sumber' : 'Isi catatan sumber', 'href' => route('projects.references.index', ['project' => $project, 'edit' => $needsNotes->id, 'notes' => 1])],
            $project->citation_style === null => ['label' => 'Pilih gaya sitasi', 'href' => route('projects.citations', $project)],
            ! $project->designReady() => ['label' => 'Isi rancangan penelitian', 'href' => route('projects.design', $project)],
            ($project->outline ?? []) === [] => ['label' => 'Susun kerangka', 'href' => route('projects.outline', $project)],
            $firstEmpty !== null && $project->blockedReason($firstEmpty) !== null => ['label' => 'Lengkapi data penelitian', 'href' => route('projects.design', $project)],
            $firstEmpty !== null => ['label' => 'Lanjutkan draf', 'href' => $emptyHref],
            $incomplete !== [] => ['label' => 'Lengkapi referensi', 'href' => route('projects.references.index', ['project' => $project, 'edit' => $incomplete[0]['id']])],
            $unknown > 0 => ['label' => 'Periksa sitasi', 'href' => route('projects.citations', $project)],
            $firstReview !== null || $firstFrontReview !== null => ['label' => 'Tinjau hasil AI', 'href' => $reviewHref],
            $project->missingFrontMatter() !== [] => ['label' => 'Lengkapi bagian awal', 'href' => route('projects.manuscript', $project).'#bagian-awal'],
            default => ['label' => 'Tinjau dan ekspor Word', 'href' => route('projects.manuscript', $project)],
        };

        return [
            'next' => $next,
            'empty_href' => $emptyHref,
            'review_href' => $reviewHref,
            'units' => count($project->units()),
            'filled' => count($project->units()) - count($empty),
            'empty' => $empty,
            'citations' => $citations,
            'cited' => $cited,
            'cited_incomplete' => $incomplete,
            'unknown' => $unknown,
            'references' => $references->count(),
            'references_incomplete' => $references->reject(fn (Reference $r): bool => $style->isComplete($r))->count(),
            'front_parts' => count($project->document_type->frontMatter()),
            'front_missing' => $project->missingFrontMatter(),
            'words' => $project->words(),
            'design_ready' => $project->designReady(),
            'needs_data' => $needsData,
            'notes_pending' => $references->filter(fn (Reference $r): bool => $r->notesPending())->count(),
            'ai_unreviewed' => count(array_intersect($project->ai_units ?? [], array_column($project->units(), 'id')))
                + count(array_filter($project->front_matter ?? [], fn (array $part): bool => ($part['ai'] ?? false) === true)),
        ];
    }
}
