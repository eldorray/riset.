<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WritingRun;
use App\Writing\Writing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class WritingController extends Controller
{
    public function show(Project $project, Writing $writing): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json($writing->state($project));
    }

    public function store(Request $request, Project $project, Writing $writing): JsonResponse
    {
        Gate::authorize('update', $project);
        $units = array_column($project->units(), 'id');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['draft', 'draft_all', 'manuscript', 'front', 'gap'])],
            'focus' => ['required_if:kind,gap', 'string', 'min:3', 'max:2000'],
            'unit' => ['required_if:kind,draft', 'string', Rule::in($units)],
            'units' => ['required_if:kind,draft_all', 'array', 'min:1', 'max:200'],
            'units.*' => ['string', 'distinct', Rule::in($units)],
            'part' => ['required_if:kind,front', 'string', Rule::in(array_column($project->document_type->frontMatter(), 'key'))],
            'references' => ['present_if:kind,draft,draft_all,manuscript,gap', 'array', $request->input('kind') === 'gap' ? 'min:2' : 'min:0', $request->input('kind') === 'gap' ? 'max:10' : 'max:40'],
            'references.*' => ['integer', 'distinct', Rule::exists('project_references', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            // Hanya mengisi bagian kosong: menulis ulang seluruh naskah dengan parafrase tidak disediakan (integritas akademik).
            'mode' => ['required_if:kind,manuscript', Rule::in(['fill'])],
            'target_words' => ['required_if:kind,manuscript', 'integer', 'between:1000,80000'],
        ]);
        $run = $writing->start($project, $data);

        return response()->json(['run' => $writing->summary($run)], 202);
    }

    public function stop(Project $project, int $run, Writing $writing): JsonResponse
    {
        Gate::authorize('update', $project);
        $writing->stop(WritingRun::query()->where('project_id', $project->id)->findOrFail($run));

        return response()->json($writing->state($project));
    }

    public function review(Request $request, Project $project, int $run, Writing $writing): JsonResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validate(['index' => ['required', 'integer', 'min:0'], 'action' => ['required', Rule::in(['accept', 'discard'])]]);
        $writing->review($project, WritingRun::query()->where('project_id', $project->id)->findOrFail($run), $data['index'], $data['action'] === 'accept');
        $project->refresh();

        return response()->json([...$writing->state($project), 'draft' => (object) ($project->draft ?? []), 'ai_units' => $project->ai_units ?? [], 'front_matter' => (object) ($project->front_matter ?? [])]);
    }
}
