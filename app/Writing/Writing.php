<?php

namespace App\Writing;

use App\Actions\GenerateDraftSection;
use App\Actions\GenerateFrontMatter;
use App\Actions\GenerateOutline;
use App\Actions\GenerateResearchGap;
use App\Ai\AiException;
use App\Billing\Billing;
use App\Citation\Markers;
use App\Jobs\WriteProjectStep;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use App\Models\WritingRun;
use App\References\ArticleReader;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class Writing
{
    /** @param array<string, mixed> $data */
    public function start(Project $project, array $data): WritingRun
    {
        return DB::transaction(function () use ($project, $data): WritingRun {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_if(WritingRun::query()->where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->exists(), 409, 'Penulisan proyek ini masih berjalan.');
            abort_unless(app(Billing::class)->active($project->user), 402, 'Akses AI belum aktif. Buka Paket & Kredit.');
            $payload = [...$data, 'draft' => $project->draft ?? [], 'front' => $project->front_matter ?? []];
            if ($data['kind'] === 'gap') {
                $payload['source_versions'] = $project->references()->whereKey($data['references'])->get()
                    ->mapWithKeys(fn (Reference $reference) => [$reference->id => GenerateResearchGap::fingerprint($reference, false)])->all();
                $payload['project_title'] = $project->title;
            }
            $payload['steps'] = $this->steps($project, $payload);
            $keys = array_column($payload['steps'], 'key');
            foreach (WritingRun::query()->where('project_id', $project->id)->where('has_suggestions', true)->get() as $pending) {
                foreach ($pending->results as $result) {
                    abort_if(($result['review'] ?? null) === 'pending' && ($data['kind'] === 'manuscript' || in_array($result['key'], $keys, true)), 409, 'Pakai atau buang usulan sebelumnya untuk bagian ini terlebih dahulu.');
                }
            }
            abort_if($payload['steps'] === [], 422, 'Tidak ada bagian yang perlu ditulis.');
            $run = WritingRun::query()->create([
                'project_id' => $project->id, 'user_id' => $project->user_id, 'kind' => $data['kind'],
                'payload' => $payload, 'results' => [],
            ]);
            WriteProjectStep::dispatch($run->id, 0);

            return $run->fresh();
        });
    }

    /** @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function steps(Project $project, array $data): array
    {
        if ($data['kind'] === 'gap') {
            $steps = $project->references()->whereKey($data['references'])->get()->filter(fn (Reference $reference) => blank($reference->notes))
                ->map(fn (Reference $reference) => ['type' => 'gap_source', 'key' => 'gap-source-'.$reference->id, 'reference_id' => $reference->id, 'label' => 'Membaca: '.$reference->title])->all();

            return [...$steps, ['type' => 'gap', 'key' => 'research-gap', 'label' => 'Membandingkan sumber & menyusun kandidat gap']];
        }
        if ($data['kind'] === 'front') {
            return [['type' => 'front', 'key' => $data['part'], 'label' => $data['part']]];
        }
        if ($data['kind'] === 'manuscript' && $project->units() === []) {
            return [['type' => 'outline', 'key' => 'outline', 'label' => 'Menyiapkan kerangka']];
        }
        $steps = [];
        foreach ($project->units() as $unit) {
            if ($data['kind'] === 'draft' && $unit['id'] !== $data['unit']) {
                continue;
            }
            if ($data['kind'] === 'draft_all' && ! in_array($unit['id'], $data['units'], true)) {
                continue;
            }
            if (($data['kind'] === 'draft_all' || ($data['kind'] === 'manuscript' && $data['mode'] === 'fill')) && trim($data['draft'][$unit['id']] ?? '') !== '') {
                continue;
            }
            $steps[] = ['type' => 'draft', 'key' => $unit['id'], 'label' => $unit['number'].' '.$unit['title'], 'unit' => $unit];
        }
        $bodyCount = count($steps);
        if ($data['kind'] === 'manuscript') {
            foreach ($project->document_type->frontMatter() as $part) {
                if ($data['mode'] === 'rewrite' || trim($data['front'][$part['key']]['text'] ?? '') === '') {
                    $steps[] = ['type' => 'front', 'key' => $part['key'], 'label' => $part['label']];
                }
            }
            $remaining = $data['mode'] === 'fill' ? max($data['target_words'] - $project->words(), $bodyCount * 150) : $data['target_words'];
            $words = min(4000, max(150, (int) round(($remaining - (count($steps) - $bodyCount) * 250) / max($bodyCount, 1))));
            foreach ($steps as &$step) {
                $step['target_words'] = $words;
            }
        }

        return $steps;
    }

    /** @return array<string, mixed> */
    public function state(Project $project): array
    {
        $run = WritingRun::query()->where('project_id', $project->id)->latest('id')->first();
        if ($run && $run->status === 'running' && $run->updated_at->lt(now()->subMinutes(35))) {
            $this->fail($run->id, 'Pekerja penulisan terputus. Hasil yang sudah selesai tetap tersimpan; mulai kembali untuk melanjutkan.');
            $run->refresh();
        }
        $suggestions = [];
        foreach (WritingRun::query()->where('project_id', $project->id)->where('has_suggestions', true)->orderBy('id')->get() as $pending) {
            foreach ($pending->results as $index => $result) {
                if (($result['review'] ?? null) === 'pending') {
                    $suggestions[] = [...$result, 'run_id' => $pending->id, 'index' => $index, 'kind' => $pending->kind, 'sourceIds' => $pending->payload['references'] ?? []];
                }
            }
        }

        return ['run' => $run ? $this->summary($run) : null, 'suggestions' => $suggestions];
    }

    /** @return array<string, mixed> */
    public function summary(WritingRun $run): array
    {
        $steps = $run->payload['steps'];

        return [
            'id' => $run->id, 'kind' => $run->kind, 'status' => $run->status, 'stop_requested' => $run->stop_requested,
            'done' => $run->cursor, 'total' => count($steps), 'label' => $steps[$run->cursor]['label'] ?? 'Selesai',
            'key' => $steps[$run->cursor]['key'] ?? null, 'references_count' => count($run->payload['references'] ?? []),
            'error' => $run->error, 'target_words' => $run->payload['target_words'] ?? null,
            'results' => array_map(fn (array $r): array => array_intersect_key($r, array_flip(['key', 'label', 'type', 'status', 'error', 'limitations'])), $run->results),
            'updated_at' => $run->updated_at->toIso8601String(),
        ];
    }

    public function stop(WritingRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $run = WritingRun::query()->lockForUpdate()->findOrFail($run->id);
            if (in_array($run->status, ['queued', 'running'], true)) {
                $run->update(['stop_requested' => true, 'status' => $run->status === 'queued' ? 'stopped' : 'running']);
            }
        });
    }

    public function execute(int $id, int $index): void
    {
        $run = DB::transaction(function () use ($id, $index): ?WritingRun {
            $run = WritingRun::query()->lockForUpdate()->find($id);
            if (! $run || $run->status !== 'queued' || $run->cursor !== $index) {
                return null;
            }
            $run->update(['status' => 'running']);

            return $run;
        });
        if (! $run) {
            return;
        }
        $billing = app(Billing::class);
        $billing->actor = User::query()->find($run->user_id);
        $billing->writingRunId = $run->id;
        $step = $run->payload['steps'][$index];
        try {
            $project = Project::query()->findOrFail($run->project_id);
            abort_unless($billing->actor && $project->user_id === $billing->actor->id, 403, 'Pemilik proyek berubah.');
            abort_unless($billing->active($billing->actor), 402, 'Akses AI sudah berakhir. Buka Paket & Kredit.');
            $this->checkStep($project, $run, $step);
            $result = $this->generate($project, $run, $step);
            DB::transaction(function () use ($run, $step, $result, $index, $billing): void {
                $project = Project::query()->lockForUpdate()->findOrFail($run->project_id);
                $current = WritingRun::query()->lockForUpdate()->findOrFail($run->id);
                abort_unless($current->status === 'running' && $current->cursor === $index, 409, 'Pekerjaan tidak lagi aktif.');
                $this->checkStep($project, $current, $step);
                if ($step['type'] === 'gap') {
                    foreach ($result['analysis']['sources'] as $source) {
                        $reference = $project->references()->whereKey($source['id'])->first();
                        abort_unless($reference && GenerateResearchGap::fingerprint($reference) === $source['fingerprint'], 409, 'Sumber berubah selama analisis. Hasil sebelumnya tetap tersimpan.');
                    }
                    $project->forceFill(['gap_analysis' => [...$result['analysis'], 'id' => $run->id]])->save();
                } elseif ($step['type'] === 'outline') {
                    $project->update(['outline' => $result['outline']]);
                    $payload = $current->payload;
                    $payload['steps'] = [...$payload['steps'], ...$this->steps($project, $payload)];
                    $current->payload = $payload;
                } elseif ($current->kind === 'manuscript') {
                    $this->saveResult($project, $step, $result, false);
                }
                $suggestion = ! in_array($current->kind, ['manuscript', 'gap'], true);
                $current->results = [...$current->results, [...$result, 'key' => $step['key'], 'label' => $step['label'], 'type' => $step['type'], 'status' => 'completed', 'review' => $suggestion ? 'pending' : null]];
                $current->has_suggestions = $current->has_suggestions || $suggestion;
                $current->consecutive_failures = 0;
                $billing->complete(true);
                $this->advance($current);
            });
        } catch (Throwable $e) {
            $billing->complete(false);
            $billing->refundRun($run->id);
            report($e);
            $message = $e instanceof AiException || $e instanceof HttpExceptionInterface ? $e->getMessage() : 'Penulisan gagal. Coba lagi; hasil sebelumnya tetap tersimpan.';
            DB::transaction(function () use ($run, $index, $step, $message): void {
                $current = WritingRun::query()->lockForUpdate()->find($run->id);
                if (! $current || $current->status !== 'running' || $current->cursor !== $index) {
                    return;
                }
                $current->results = [...$current->results, ['key' => $step['key'], 'label' => $step['label'], 'type' => $step['type'], 'status' => 'failed', 'error' => $message]];
                $current->consecutive_failures++;
                if ($step['type'] === 'outline' || $current->kind === 'gap' || $current->consecutive_failures >= 2) {
                    $current->error = in_array($step['type'], ['outline', 'gap', 'gap_source'], true) ? $message : 'Dihentikan setelah 2 kegagalan berturut-turut.';
                }
                $this->advance($current);
            });
        } finally {
            $billing->actor = null;
            $billing->writingRunId = null;
        }
    }

    /** @param array<string, mixed> $step */
    private function checkStep(Project $project, WritingRun $run, array $step): void
    {
        if ($run->kind === 'gap') {
            abort_unless($project->title === $run->payload['project_title'], 409, 'Judul proyek berubah selama analisis. Mulai analisis kembali.');
            $references = $project->references()->whereKey($run->payload['references'])->get();
            abort_unless($references->count() === count($run->payload['references']), 409, 'Referensi terpilih dihapus selama analisis.');
            foreach ($references as $reference) {
                abort_unless(GenerateResearchGap::fingerprint($reference, false) === $run->payload['source_versions'][$reference->id], 409, 'Metadata sumber berubah selama analisis. Mulai analisis kembali.');
            }

            return;
        }
        if ($step['type'] === 'outline') {
            abort_if(($project->outline ?? []) !== [], 409, 'Kerangka telah berubah selama penulisan.');
        } elseif ($step['type'] === 'draft') {
            abort_unless(collect($project->units())->firstWhere('id', $step['key']) === $step['unit'], 409, 'Kerangka bagian ini berubah.');
            if ($run->kind === 'manuscript') {
                abort_if(($project->draft[$step['key']] ?? '') !== ($run->payload['draft'][$step['key']] ?? ''), 409, 'Bagian ini sudah diedit. Tulisan terbaru tidak ditimpa.');
            }
        } else {
            abort_unless(in_array($step['key'], array_column($project->document_type->frontMatter(), 'key'), true), 409, 'Bagian awal berubah.');
            if ($run->kind === 'manuscript') {
                abort_if(($project->front_matter[$step['key']] ?? null) !== ($run->payload['front'][$step['key']] ?? null), 409, 'Bagian awal sudah diedit. Tulisan terbaru tidak ditimpa.');
            }
        }
    }

    /** @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function generate(Project $project, WritingRun $run, array $step): array
    {
        if ($step['type'] === 'gap_source') {
            $reference = $project->references()->whereKey($step['reference_id'])->firstOrFail();
            if (filled($reference->notes)) {
                return ['reference_id' => $reference->id];
            }
            $mark = app(Billing::class)->mark();
            try {
                $notes = app(ArticleReader::class)->read($reference);
            } catch (AiException $e) {
                app(Billing::class)->refundTo($mark);

                return ['reference_id' => $reference->id, 'source_error' => $e->getMessage(), 'limitations' => $e->getMessage()];
            }
            $query = $reference->newQuery()->whereKey($reference->id)->where('source_url', $reference->source_url);
            $reference->notes === null ? $query->whereNull('notes') : $query->where('notes', $reference->notes);
            abort_unless($query->update(['notes' => $notes]) > 0, 409, 'Catatan berubah saat artikel dibaca. Catatan Anda tetap tersimpan.');

            return ['reference_id' => $reference->id];
        }
        if ($step['type'] === 'gap') {
            $excluded = array_values(array_map(fn (array $result) => ['id' => $result['reference_id'], 'reason' => $result['source_error']], array_filter($run->results, fn (array $result) => isset($result['source_error']))));
            $references = $project->references()->whereKey(array_diff($run->payload['references'], array_column($excluded, 'id')))->get()
                ->filter(fn (Reference $reference) => filled($reference->notes));

            return ['analysis' => app(GenerateResearchGap::class)($project, $run->payload['focus'], $references, $excluded)];
        }
        if ($step['type'] === 'outline') {
            return ['outline' => app(GenerateOutline::class)($project)];
        }
        if ($step['type'] === 'front') {
            return app(GenerateFrontMatter::class)($project, $step['key']);
        }
        $ids = $run->payload['references'];
        if (($run->payload['mode'] ?? '') === 'rewrite') {
            $ids = array_values(array_unique([...$ids, ...Markers::ids($run->payload['draft'][$step['key']] ?? '')]));
        }
        $references = $project->references()->whereKey($ids)->get();
        abort_unless($references->count() === count($ids), 409, 'Referensi terpilih berubah. Periksa pilihan sumber sebelum melanjutkan.');
        $project->draft = $run->payload['draft'];

        return app(GenerateDraftSection::class)($project, $step['unit'], $references, $step['target_words'] ?? null, ($run->payload['mode'] ?? '') === 'rewrite' ? 'rewrite' : 'continue');
    }

    /** @param array<string, mixed> $step
     * @param  array<string, mixed>  $result
     */
    private function saveResult(Project $project, array $step, array $result, bool $append): void
    {
        $cited = Markers::ids($result['text']);
        abort_unless($project->references()->whereKey($cited)->count() === count($cited), 409, 'Referensi hasil AI telah berubah. Teks tetap tersedia untuk disalin.');
        $key = $step['key'];
        if ($step['type'] === 'draft') {
            $existing = $append ? trim($project->draft[$key] ?? '') : '';
            $project->update(['draft' => [...($project->draft ?? []), $key => $existing === '' ? $result['text'] : $existing."\n\n".$result['text']], 'ai_units' => array_values(array_unique([...($project->ai_units ?? []), $key]))]);
        } else {
            $project->update(['front_matter' => [...($project->front_matter ?? []), $key => ['text' => $result['text'], 'keywords' => $result['keywords'], 'ai' => true]]]);
        }
    }

    private function advance(WritingRun $run): void
    {
        $run->cursor++;
        $run->status = match (true) {
            $run->error !== null => 'failed',
            $run->stop_requested => 'stopped',
            $run->cursor >= count($run->payload['steps']) => $run->consecutive_failures > 0 ? 'failed' : 'completed',
            default => 'queued',
        };
        $run->save();
        if ($run->status === 'queued') {
            WriteProjectStep::dispatch($run->id, $run->cursor);
        }
    }

    public function fail(int $id, string $message, ?int $step = null): void
    {
        DB::transaction(function () use ($id, $message, $step): void {
            $run = WritingRun::query()->lockForUpdate()->find($id);
            if ($run && ($step === null || $run->cursor === $step) && in_array($run->status, ['queued', 'running'], true)) {
                app(Billing::class)->refundRun($id);
                $run->update(['status' => 'failed', 'error' => $message]);
            }
        });
    }

    public function review(Project $project, WritingRun $run, int $index, bool $accept): void
    {
        DB::transaction(function () use ($project, $run, $index, $accept): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $run = WritingRun::query()->lockForUpdate()->findOrFail($run->id);
            $results = $run->results;
            $result = $results[$index] ?? null;
            abort_unless($result && ($result['review'] ?? null) === 'pending', 409, 'Usulan ini sudah diproses.');
            if ($accept) {
                $step = $run->payload['steps'][$index];
                $this->checkStep($project, $run, $step);
                $same = $step['type'] === 'draft'
                    ? ($project->draft[$step['key']] ?? '') === ($run->payload['draft'][$step['key']] ?? '')
                    : ($project->front_matter[$step['key']] ?? null) === ($run->payload['front'][$step['key']] ?? null);
                abort_unless($same, 409, 'Tulisan berubah sejak AI mulai. Usulan tetap tersedia; salin bagian yang ingin digunakan ke editor.');
                $this->saveResult($project, $step, $result, true);
            }
            $hasSuggestions = collect($results)->except([$index])->contains(fn (array $r): bool => ($r['review'] ?? null) === 'pending');
            $results[$index]['review'] = $accept ? 'accepted' : 'discarded';
            $run->update(['results' => $results, 'has_suggestions' => $hasSuggestions]);
        });
    }
}
