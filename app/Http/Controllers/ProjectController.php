<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProjectReadiness;
use App\Enums\CitationStyle;
use App\Enums\DocumentType;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\DocxTemplate;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        // ponytail: tanpa paginasi — satu pengguna jarang punya puluhan proyek. Pakai paginate() bila perlu.
        $projects = $request->user()?->projects()->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'))
            ->withCount('references')->latest('updated_at')->get() ?? collect();

        return Inertia::render('projects/Index', [
            'projects' => $projects->map(fn (Project $project): array => $project->summary()),
            'documentTypes' => DocumentType::options(),
            'archived' => $request->boolean('archived'),
        ]);
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        $project->archived_at = $data['archived'] ? now() : null;
        $project->save();
        Inertia::flash('success', $data['archived'] ? 'Proyek diarsipkan. Isi tetap tersimpan.' : 'Proyek dipulihkan.');

        return back();
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()?->projects()->create($request->validated());
        abort_if($project === null, 403);

        Inertia::flash('success', 'Proyek dibuat.');

        return to_route('projects.show', $project);
    }

    public function show(Project $project, ProjectReadiness $readiness): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/Show', [
            'project' => $project->summary(),
            'exportFormat' => $project->exportFormat(),
            'readiness' => $readiness($project, $project->references->keyBy('id')),
            'citationStyles' => CitationStyle::options(),
            'templates' => DocxTemplate::query()->orderBy('name')->get(['id', 'name', 'institution']),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        Inertia::flash('success', 'Perubahan disimpan.');

        return back();
    }
}
