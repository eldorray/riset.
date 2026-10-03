<?php

namespace App\Http\Controllers;

use App\Actions\GenerateResearchGap;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ResearchGapController extends Controller
{
    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/ResearchGap', [
            'project' => $project->summary(),
            'references' => $project->references()->oldest()->get()->map(fn (Reference $reference) => [
                ...GenerateResearchGap::source($reference, $project), 'keywords' => $reference->metadata['keywords'] ?? [],
            ]),
            'analysis' => $project->gap_analysis,
            'selectedGap' => $project->research_gap,
            'analysisStale' => $project->gap_analysis ? ! $this->sourcesCurrent($project, $project->gap_analysis['sources']) : false,
            'selectedStale' => $project->research_gap !== null && $project->researchGapContext() === '',
        ]);
    }

    /** @param list<array<string, mixed>> $sources */
    private function sourcesCurrent(Project $project, array $sources): bool
    {
        foreach ($sources as $source) {
            $reference = $project->references()->whereKey($source['id'])->first();
            if (! $reference || GenerateResearchGap::fingerprint($reference) !== $source['fingerprint']) {
                return false;
            }
        }

        return true;
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'analysis_id' => ['required', 'integer'], 'candidate' => ['required', 'integer', 'min:0', 'max:2'],
            'gap' => ['required', 'string', 'max:4000'], 'question' => ['required', 'string', 'max:2000'],
            'contribution' => ['required', 'string', 'max:2000'], 'reviewed' => ['accepted'],
        ], ['reviewed.accepted' => 'Tinjau kandidat dan bukti sumber sebelum menyimpan pilihan.']);
        DB::transaction(function () use ($project, $data): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $analysis = $project->gap_analysis;
            if (! $analysis || $analysis['id'] !== $data['analysis_id']) {
                throw ValidationException::withMessages(['analysis_id' => 'Analisis telah berubah. Pilih kandidat dari hasil terbaru sebelum menyimpan.']);
            }
            $candidate = $analysis['candidates'][$data['candidate']] ?? null;
            if (! $candidate || ! $this->sourcesCurrent($project, $analysis['sources'])) {
                throw ValidationException::withMessages(['candidate' => 'Kandidat atau sumber sudah berubah. Jalankan analisis kembali.']);
            }
            $sources = array_values(array_filter($analysis['sources'], fn (array $source) => in_array($source['id'], $candidate['source_ids'], true)));
            $project->forceFill(['research_gap' => [
                'analysis_id' => $analysis['id'], 'candidate' => $data['candidate'], 'title' => $candidate['title'],
                'gap' => $data['gap'], 'question' => $data['question'], 'contribution' => $data['contribution'],
                'sources' => $sources, 'saved_at' => now()->toIso8601String(),
            ]])->save();
        });
        Inertia::flash('success', 'Gap pilihan disimpan sebagai arah kerangka dan draf. Kebaruan tetap perlu diverifikasi.');

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        $project->forceFill(['research_gap' => null])->save();
        Inertia::flash('success', 'Gap pilihan dilepas. Hasil analisis tetap tersimpan.');

        return back();
    }
}
