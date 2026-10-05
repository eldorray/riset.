<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GenerateDraftSection;
use App\Actions\GenerateFrontMatter;
use App\Actions\GenerateOutline;
use App\Actions\ProjectReadiness;
use App\Ai\AiException;
use App\Citation\Markers;
use App\Enums\CitationStyle;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Langkah 5 "Naskah lengkap": bagian awal sesuai jenis tulisan + pratinjau naskah utuh + ekspor.
 */
final class ManuscriptController extends Controller
{
    public function show(Project $project, ProjectReadiness $readiness): Response
    {
        Gate::authorize('view', $project);

        $style = $project->style();
        $references = $project->references->keyBy('id');
        $numbers = $style->numbers($project, $references);
        $render = fn (string $id): string => Markers::render(trim($project->draft[$id] ?? ''), $references, $style, $numbers);
        $uppercase = $project->docxTemplate ? $project->docxTemplate->chapter_uppercase : $project->document_type->usesBabNumbering();

        return Inertia::render('projects/Manuscript', [
            'project' => $project->summary(),
            'exportFormat' => $project->exportFormat(),
            'isBook' => $project->document_type->isBook(),
            'template' => $project->docxTemplate?->name,
            'styleLabel' => ($project->citation_style ?? CitationStyle::Apa7)->label(),
            'parts' => array_map(fn (array $part): array => [
                ...$part,
                'text' => $project->frontText($part['key']),
                'ai' => ($project->front_matter[$part['key']]['ai'] ?? false) === true,
                'keywords_value' => (string) ($project->front_matter[$part['key']]['keywords'] ?? ''),
            ], $project->document_type->frontMatter()),
            'chapters' => array_map(fn (array $chapter, int $i): array => [
                'id' => $chapter['id'],
                'label' => $project->chapterLabel($i).' '.($uppercase ? mb_strtoupper($chapter['title']) : $chapter['title']),
                'text' => $chapter['sections'] === [] ? $render($chapter['id']) : null,
                'sections' => array_map(fn (array $section, int $j): array => [
                    'id' => $section['id'],
                    'label' => ($i + 1).'.'.($j + 1).' '.$section['title'],
                    'text' => $render($section['id']),
                ], $chapter['sections'], array_keys($chapter['sections'])),
            ], $project->outline ?? [], array_keys($project->outline ?? [])),
            'bibliography' => array_map(fn (array $row): array => [
                'id' => $row['reference']->id,
                'segments' => $style->entry($row['reference'], $row['number']),
            ], $style->bibliography($project, $references)),
            'readiness' => $readiness($project, $references),
            'targetWords' => (int) config("riset.target_words.{$project->document_type->value}", 10000),
            'aiUnits' => $project->ai_units ?? [],
            'references' => $project->references->map(fn (Reference $reference): array => [
                'id' => $reference->id,
                'label' => $style->isComplete($reference) ? $style->label($reference) : $reference->title,
                'has_notes' => $reference->notesUsable(),
                'notes_pending' => $reference->notesPending(),
                'note_chars' => mb_strlen($reference->notes ?? ''),
            ])->values(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $keys = array_column($project->document_type->frontMatter(), 'key');
        $data = $request->validate([
            'parts' => ['required', 'array:'.implode(',', $keys)],
            'parts.*.text' => ['nullable', 'string', 'max:20000'],
            'parts.*.keywords' => ['nullable', 'string', 'max:500'],
            'base' => ['sometimes', 'array:'.implode(',', $keys)],
            'base.*.text' => ['nullable', 'string'],
            'base.*.keywords' => ['nullable', 'string'],
        ], ['parts.array' => 'Bagian naskah tidak sesuai jenis tulisan.']);

        DB::transaction(function () use ($project, $data): void {
            $current = Project::query()->lockForUpdate()->findOrFail($project->id);
            $front = $current->front_matter ?? [];
            foreach ($data['parts'] as $key => $part) {
                if (array_key_exists('base', $data)) {
                    $base = $data['base'][$key] ?? null;
                    abort_unless(is_array($base), 422, 'Teks dasar bagian belum dikirim.');
                    abort_if(trim((string) ($front[$key]['text'] ?? '')) !== ($base['text'] ?? '')
                        || (string) ($front[$key]['keywords'] ?? '') !== ($base['keywords'] ?? ''),
                        409, 'Bagian awal sudah berubah selama penulisan. Edit Anda tetap tersedia; salin sebelum memuat ulang.');
                }
                $front[$key] = array_filter([
                    'text' => trim((string) ($part['text'] ?? '')),
                    'keywords' => trim((string) ($part['keywords'] ?? '')),
                ], fn (string $value): bool => $value !== '');
            }
            $current->update(['front_matter' => $front]);
        });
        Inertia::flash('success', 'Bagian naskah disimpan.');

        return back();
    }

    /**
     * Bagian awal dengan AI. Tanpa "save" hasilnya hanya usulan; dengan "save" (mode naskah lengkap)
     * langsung disimpan dan ditandai AI sampai pengguna menyimpan ulang bagian itu.
     */
    public function generate(Request $request, Project $project, GenerateFrontMatter $generate): JsonResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'part' => ['required', Rule::in(array_column($project->document_type->frontMatter(), 'key'))],
            'save' => ['nullable', 'boolean'],
        ]);

        $original = $project->front_matter[$data['part']] ?? null;

        try {
            $result = $generate($project, $data['part']);
        } catch (AiException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($request->boolean('save')) {
            DB::transaction(function () use ($project, $data, $original, $result): void {
                $current = Project::query()->lockForUpdate()->findOrFail($project->id);
                abort_if(($current->front_matter[$data['part']] ?? null) !== $original, 409, 'Bagian ini berubah selama AI berjalan. Edit terbaru tetap tersimpan.');
                $front = $current->front_matter ?? [];
                $front[$data['part']] = array_filter(['text' => $result['text'], 'keywords' => $result['keywords'], 'ai' => true], fn ($v): bool => $v !== '');
                $current->update(['front_matter' => $front]);
            });
        }

        return response()->json($result);
    }

    /** Langkah pertama naskah otomatis: pastikan kerangka ada (disusun AI bila belum). */
    public function prepare(Project $project, GenerateOutline $outline): JsonResponse
    {
        Gate::authorize('update', $project);
        $generated = false;

        if (($project->outline ?? []) === []) {
            try {
                $project->update(['outline' => $outline($project)]);
                $generated = true;
            } catch (AiException $e) {
                report($e);

                return response()->json(['message' => $e->getMessage()], 502);
            }
        }

        return response()->json(['units' => $project->units(), 'generated_outline' => $generated]);
    }

    /**
     * Tulis satu bagian kosong dengan target kata lalu langsung simpan dan tandai AI. Bagian yang
     * sudah berisi tidak disentuh (tidak ada mode tulis ulang massal). Gagal = draf utuh.
     */
    public function section(Request $request, Project $project, GenerateDraftSection $generate): JsonResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'unit' => ['required', 'string', Rule::in(array_column($project->units(), 'id'))],
            'references' => ['present', 'array', 'max:40'],
            'references.*' => ['integer', 'distinct', Rule::exists('project_references', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'target_words' => ['required', 'integer', 'between:100,4000'],
            'mode' => ['required', Rule::in(['fill'])],
        ]);

        $unit = $project->unit($data['unit']);
        abort_if($unit === null, 409, 'Kerangka berubah. Muat ulang halaman.');
        $original = $project->draft[$data['unit']] ?? '';
        $existing = trim($original);

        if ($existing !== '') {
            return response()->json(['skipped' => true]);
        }

        $references = $project->references()->whereKey($data['references'])->get();

        try {
            $result = $generate($project, $unit, $references, (int) $data['target_words']);
        } catch (AiException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }

        DB::transaction(function () use ($project, $data, $unit, $original, $result): void {
            $current = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_if(($current->draft[$data['unit']] ?? '') !== $original
                || $current->unit($data['unit']) !== $unit,
                409, 'Bagian ini berubah selama AI berjalan. Edit terbaru tetap tersimpan.');
            $cited = Markers::ids($result['text']);
            abort_if($current->references()->whereKey($cited)->count() !== count($cited), 409, 'Referensi berubah selama AI berjalan. Draf tetap tersimpan.');
            $current->update([
                'draft' => [...($current->draft ?? []), $data['unit'] => $result['text']],
                'ai_units' => array_values(array_unique([...($current->ai_units ?? []), $data['unit']])),
            ]);
        });

        return response()->json([
            ...$result,
            'words' => count(preg_split('/\s+/u', trim((string) preg_replace('/\[@\d+\]/', ' ', $result['text'])), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'saved' => true,
        ]);
    }
}
