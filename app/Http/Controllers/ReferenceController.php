<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\AiException;
use App\Citation\Markers;
use App\Citation\Style;
use App\Http\Requests\ReferenceRequest;
use App\Models\Project;
use App\Models\Reference;
use App\References\ArticleReader;
use App\References\Metadata;
use App\References\UploadedArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F-03: referensi dari input manual atau hasil pencarian Crossref (lihat ReferenceSearchController).
 */
final class ReferenceController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);
        $style = $project->style();

        return Inertia::render('projects/References', [
            'project' => $project->summary(),
            'references' => $project->references()->latest()->get()->map(fn (Reference $reference): array => [
                'id' => $reference->id,
                'title' => $reference->title,
                'source_url' => $reference->source_url,
                'source_name' => $reference->source_name,
                'input_method' => $reference->input_method,
                'notes' => $reference->notes,
                'metadata' => (object) ($reference->metadata ?? []),
                'missing' => array_values($style->missing($reference)),
                'in_text' => $style->isComplete($reference) ? $style->label($reference) : null,
                'cited_in' => self::citedIn($project, $reference),
            ]),
            'types' => Style::TYPES,
            'editReference' => (int) $request->query('edit', 0),
            'focusNotes' => $request->boolean('notes'),
        ]);
    }

    public function import(Request $request, Project $project, UploadedArticle $reader): JsonResponse
    {
        Gate::authorize('update', $project);
        $request->validate(['article' => ['required', 'file', 'mimes:pdf,docx,zip', 'extensions:pdf,docx', 'max:15360']], [
            'article.mimes' => 'Unggah artikel PDF atau Word (.docx).',
            'article.extensions' => 'Unggah artikel PDF atau Word (.docx).',
            'article.max' => 'Ukuran artikel maksimal 15 MB.',
        ]);
        try {
            /** @var UploadedFile $file */
            $file = $request->file('article');

            return response()->json($reader->read($file));
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function read(Project $project, Reference $reference, ArticleReader $reader): JsonResponse
    {
        Gate::authorize('update', $project);
        $original = $reference->notes;
        try {
            $notes = $reader->read($reference);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
        $query = $project->references()->whereKey($reference->id);
        $original === null ? $query->whereNull('notes') : $query->where('notes', $original);
        abort_unless($query->update(['notes' => $notes]) > 0, 409, 'Catatan berubah selama AI membaca. Perubahan Anda tetap tersimpan.');
        $project->touch();

        return response()->json(['notes' => $notes]);
    }

    public function store(ReferenceRequest $request, Project $project): RedirectResponse
    {
        $this->saveReference($request, $project);

        Inertia::flash('success', 'Referensi disimpan.');

        return back();
    }

    /**
     * Hapus langsung tanpa konfirmasi (seperti keranjang). Sitasinya ikut dibuang dari draf agar
     * tidak tersisa "[sitasi tidak dikenal]"; teks sebelumnya disimpan supaya "Batalkan" bisa
     * mengembalikan referensi beserta sitasinya.
     */
    public function destroy(Project $project, Reference $reference): RedirectResponse
    {
        Gate::authorize('update', $project);

        // ponytail: hapus lunak dibersihkan permanen setelah 1 hari; pindah ke scheduler bila tabelnya besar.
        $project->references()->onlyTrashed()->where('deleted_at', '<', now()->subDay())->forceDelete();

        $draft = $project->draft ?? [];
        $snapshot = array_filter($draft, fn (string $text): bool => in_array($reference->id, Markers::ids($text), true));

        if ($snapshot !== []) {
            $project->update(['draft' => [...$draft, ...array_map(fn (string $text): string => Markers::remove($text, $reference->id), $snapshot)]]);
        }

        $reference->update(['removed_citations' => $snapshot ?: null]);
        $reference->delete();
        $project->touch();

        Inertia::flash('success', $snapshot === [] ? 'Referensi dihapus dari proyek.' : 'Referensi dan sitasinya di '.count($snapshot).' bagian dihapus.');
        Inertia::flash('undo', route('projects.references.restore', [$project, $reference]));

        return back();
    }

    /** Batalkan hapus: kembalikan referensi dan sitasi di bagian yang belum diubah sejak dihapus. */
    public function restore(Project $project, Reference $reference): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless($reference->trashed(), 404);

        $draft = $project->draft ?? [];

        foreach ($reference->removed_citations ?? [] as $unit => $original) {
            if (($draft[$unit] ?? '') === Markers::remove($original, $reference->id)) {
                $draft[$unit] = $original;
            }
        }

        $reference->restore();
        $reference->update(['removed_citations' => null]);
        $project->update(['draft' => $draft]);

        Inertia::flash('success', 'Referensi dikembalikan.');

        return back();
    }

    /**
     * Nomor bagian draf yang menyitasi referensi ini.
     *
     * @return list<string>
     */
    private static function citedIn(Project $project, Reference $reference): array
    {
        return array_values(array_map(
            fn (array $unit): string => $unit['number'],
            array_filter($project->units(), fn (array $unit): bool => in_array($reference->id, Markers::ids($project->draft[$unit['id']] ?? ''), true)),
        ));
    }

    public function update(ReferenceRequest $request, Project $project, Reference $reference): RedirectResponse
    {
        $this->saveReference($request, $project, $reference);

        Inertia::flash('success', 'Referensi diperbarui.');

        return back();
    }

    private function saveReference(ReferenceRequest $request, Project $project, ?Reference $reference = null): void
    {
        $payload = $request->payload();
        DB::transaction(function () use ($project, $reference, $payload): void {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $this->assertUnique($project, Metadata::referenceKeys($payload['title'], $payload['source_url'], $payload['metadata']['doi'] ?? ''), $reference?->id);
            if ($reference) {
                $reference->update($payload);
            } else {
                $project->references()->create($payload);
            }
            $project->touch();
        });
    }

    /** @param list<string> $keys */
    private function assertUnique(Project $project, array $keys, ?int $except = null): void
    {
        foreach ($project->references()->get() as $saved) {
            if ($saved->id !== $except && array_intersect($keys, Metadata::referenceKeys($saved->title, $saved->source_url, $saved->meta('doi'))) !== []) {
                throw ValidationException::withMessages(['title' => 'Referensi sudah ada.']);
            }
        }
    }
}
