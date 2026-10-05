<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GenerateDraftSection;
use App\Ai\AiException;
use App\Citation\Markers;
use App\Http\Requests\GenerateDraftRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DraftController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);
        $style = $project->style();

        return Inertia::render('projects/Draft', [
            'project' => $project->summary(),
            'units' => $project->units(),
            'draft' => (object) ($project->draft ?? []),
            'aiUnits' => $project->ai_units ?? [],
            'selectedUnit' => is_string($request->query('unit')) ? $request->query('unit') : null,
            'references' => $project->references()->oldest()->get()->map(fn (Reference $reference): array => [
                'id' => $reference->id,
                'title' => $reference->title,
                'source_url' => $reference->source_url,
                'in_text' => $style->isComplete($reference) ? $style->label($reference) : null,
                'has_notes' => $reference->notesUsable(),
                'notes_pending' => $reference->notesPending(),
                'note_chars' => mb_strlen($reference->notes ?? ''),
                'keywords' => $reference->metadata['keywords'] ?? [],
            ]),
        ]);
    }

    /** Tidak menyimpan apa pun: hasil AI ditinjau pengguna dulu, draf tersimpan tetap utuh (AC F-06). */
    public function generate(GenerateDraftRequest $request, Project $project, GenerateDraftSection $generate): JsonResponse
    {
        $unit = $project->unit((string) $request->validated('unit'));
        abort_if($unit === null, 409, 'Kerangka berubah. Muat ulang halaman.');
        $references = $project->references()->whereKey($request->validated('references'))->get();

        try {
            return response()->json($generate($project, $unit, $references));
        } catch (AiException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function update(UpdateDraftRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $new = $request->draft();
        $reviewed = (array) $request->validated('reviewed', []);
        $base = $request->validated('base');
        $current = DB::transaction(function () use ($project, $new, $reviewed, $base): Project {
            $current = Project::query()->lockForUpdate()->findOrFail($project->id);
            $old = $current->draft ?? [];
            $units = array_column($current->units(), 'id');
            foreach (array_unique([...array_keys($new), ...$reviewed]) as $id) {
                abort_unless(in_array($id, $units, true), 409, 'Kerangka berubah. Teks lokal tetap tersedia; muat ulang setelah menyalinnya.');
                if (is_array($base)) {
                    abort_if(($old[$id] ?? '') !== ($base[$id] ?? ''), 409, 'Bagian ini sudah berubah di tab lain. Teks Anda tetap di editor; salin sebelum memuat versi server.');
                }
            }
            $cited = Markers::ids(implode("\n", $new));
            abort_if($current->references()->whereKey($cited)->count() !== count($cited), 409, 'Referensi berubah. Periksa sitasi sebelum menyimpan lagi.');
            $touched = [...array_keys(array_filter($new, fn (string $text, string $id): bool => $text !== ($old[$id] ?? ''), ARRAY_FILTER_USE_BOTH)), ...$reviewed];
            $current->update([
                'draft' => [...$old, ...$new],
                'ai_units' => array_values(array_diff($current->ai_units ?? [], $touched)),
            ]);

            return $current;
        });
        if ($request->expectsJson()) {
            return response()->json(['draft' => (object) $new, 'ai_units' => $current->ai_units ?? [], 'project' => $current->summary()]);
        }

        Inertia::flash('success', 'Draf disimpan.');

        return back();
    }
}
