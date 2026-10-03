<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GenerateOutline;
use App\Ai\AiException;
use App\Http\Requests\UpdateOutlineRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OutlineController extends Controller
{
    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/Outline', [
            'project' => $project->summary(),
            'outline' => $project->outline,
            'structure' => $project->document_type->structure(),
            'numbering' => $project->document_type->usesBabNumbering() ? 'bab' : 'angka',
            // id bagian yang sudah berisi teks, supaya UI memperingatkan sebelum bagian itu dihapus
            'writtenUnits' => array_keys(array_filter($project->draft ?? [], fn (string $text): bool => trim($text) !== '')),
        ]);
    }

    /** Tidak menyimpan apa pun: gagal atau berhasil, kerangka tersimpan tetap utuh (AC F-05). */
    public function generate(Project $project, GenerateOutline $generate): JsonResponse
    {
        Gate::authorize('update', $project);

        try {
            return response()->json(['outline' => $generate($project)]);
        } catch (AiException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function update(UpdateOutlineRequest $request, Project $project): RedirectResponse
    {
        $project->update(['outline' => $request->outline()]);

        Inertia::flash('success', 'Kerangka disimpan.');

        return back();
    }
}
