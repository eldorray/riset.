<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SuggestResearchDesign;
use App\Ai\AiException;
use App\Http\Requests\UpdateResearchDesignRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rancangan penelitian (masalah, tujuan, metode) dan data/temuan milik pengguna.
 * Menjadi acuan semua prompt AI: bab metode butuh rancangan, bab hasil butuh data.
 */
final class ResearchDesignController extends Controller
{
    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/Design', [
            'project' => $project->summary(),
            'design' => (object) ($project->research_design ?? []),
            'researchData' => (string) $project->research_data,
            'fields' => Project::DESIGN_FIELDS,
            'approaches' => Project::APPROACHES,
            'selectedGap' => $project->research_gap && $project->researchGapContext() !== ''
                ? array_intersect_key($project->research_gap, array_flip(['title', 'question', 'contribution']))
                : null,
            'empiricalUnits' => array_values(array_map(
                fn (array $unit): string => $unit['number'].' '.$unit['title'],
                array_filter($project->units(), fn (array $unit): bool => $unit['kind'] === 'empiris'),
            )),
        ]);
    }

    /** Saran AI untuk field rancangan yang masih kosong; tidak menyimpan apa pun. */
    public function suggest(Request $request, Project $project, SuggestResearchDesign $suggest): JsonResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'distinct', Rule::in(SuggestResearchDesign::FIELDS)],
            'design' => ['present', 'array'],
            'design.*' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            return response()->json($suggest($project, $data['title'], $data['fields'], array_map(strval(...), array_intersect_key($data['design'], Project::DESIGN_FIELDS))));
        } catch (AiException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function update(UpdateResearchDesignRequest $request, Project $project): RedirectResponse
    {
        $project->update([
            'title' => trim((string) $request->validated('title')),
            'research_design' => $request->design() ?: null,
            'research_data' => trim((string) $request->validated('research_data')) ?: null,
        ]);

        Inertia::flash('success', 'Rancangan penelitian disimpan.');

        return back();
    }
}
