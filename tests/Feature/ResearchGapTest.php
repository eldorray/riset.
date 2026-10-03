<?php

use App\Ai\AiException;
use App\Billing\Billing;
use App\Jobs\WriteProjectStep;
use App\Models\Project;
use App\Models\Reference;
use App\Models\WritingRun;
use App\References\ArticleReader;
use App\Writing\Writing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
    Queue::fake();
    $this->project = Project::factory()->withOutline()->create();
    $this->project->user->forceFill(['unlimited' => true])->save();
    $this->sources = collect([
        Reference::factory()->for($this->project)->create(['notes' => 'Latihan mandiri mendukung keberanian berbicara. Pengamatan terbatas pada siswa SMA.']),
        Reference::factory()->for($this->project)->create(['notes' => 'Siswa membutuhkan umpan balik untuk latihan berbicara. Kebutuhan siswa SMP perlu ditelaah lebih lanjut.']),
    ]);
    $this->actingAs($this->project->user);
    $this->url = "/projects/{$this->project->id}";
});

function gapReply($sources): array
{
    return [
        'matrix' => $sources->map(fn ($source) => ['reference_id' => $source->id, 'focus' => 'Latihan berbicara', 'context' => 'Pelajar', 'method' => 'Tidak disebutkan', 'findings' => $source->notes, 'limitations' => 'Konteks terbatas'])->values()->all(),
        'candidates' => [[
            'title' => 'Kebutuhan umpan balik siswa SMP', 'gap' => 'Perbandingan ini menyarankan pendalaman kebutuhan umpan balik dalam latihan mandiri siswa SMP.',
            'type' => 'synthesis', 'importance' => 'Membantu memahami kebutuhan siswa.', 'question' => 'Umpan balik apa yang dibutuhkan siswa SMP?',
            'contribution' => 'Pemetaan kebutuhan latihan berbicara.', 'verification' => 'Cari studi kebutuhan umpan balik siswa SMP untuk membandingkan hasil.',
            'source_ids' => $sources->pluck('id')->all(),
            'evidence' => $sources->map(fn ($source) => ['reference_id' => $source->id, 'quote' => $source->notes])->values()->all(),
        ]],
        'limitations' => 'Analisis berdasarkan catatan dua artikel, belum memverifikasi kebaruan melalui pencarian menyeluruh.',
    ];
}

function gapAiResponse(array $data): array
{
    return ['choices' => [['message' => ['content' => json_encode($data)]]], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 200]];
}

function runGap($test): int
{
    $id = $test->postJson($test->url.'/writing', ['kind' => 'gap', 'focus' => 'Media digital untuk speaking siswa SMP', 'references' => $test->sources->pluck('id')->all()])->assertAccepted()->json('run.id');
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));

    return $id;
}

it('menampilkan research gap hanya bagi pemilik dan memvalidasi pilihan sumber', function () {
    $this->get($this->url.'/research-gap')->assertInertia(fn ($page) => $page->component('projects/ResearchGap')->has('references', 2));
    $this->postJson($this->url.'/writing', ['kind' => 'gap', 'focus' => 'Speaking', 'references' => [$this->sources[0]->id]])->assertUnprocessable();
    $foreign = Reference::factory()->create();
    $this->postJson($this->url.'/writing', ['kind' => 'gap', 'focus' => 'Speaking', 'references' => [$this->sources[0]->id, $foreign->id]])->assertUnprocessable();
    $this->actingAs($foreign->project->user)->get($this->url.'/research-gap')->assertForbidden();
    $this->putJson($this->url.'/research-gap', [])->assertForbidden();
    $this->delete($this->url.'/research-gap')->assertForbidden();
});

it('menyimpan hasil background dan pilihan suntingan tanpa menimpanya saat analisis ulang', function () {
    Http::fake(['ai.test/*' => Http::response(gapAiResponse(gapReply($this->sources)))]);
    $id = runGap($this);
    Queue::assertPushed(WriteProjectStep::class);
    $this->getJson($this->url.'/writing')->assertJsonPath('run.status', 'completed')->assertJsonCount(0, 'suggestions');
    $analysis = $this->project->fresh()->gap_analysis;
    expect($analysis['id'])->toBe($id)->and($analysis['candidates'])->toHaveCount(1)->and($this->project->fresh()->research_gap)->toBeNull();
    $payload = ['analysis_id' => $id, 'candidate' => 0, 'gap' => 'Arah gap yang saya sunting.', 'question' => 'Pertanyaan pilihan saya?', 'contribution' => 'Kontribusi pilihan.', 'reviewed' => true];
    $this->put($this->url.'/research-gap', [...$payload, 'reviewed' => false])->assertSessionHasErrors('reviewed');
    $this->put($this->url.'/research-gap', $payload)->assertSessionHasNoErrors();
    $choice = $this->project->fresh()->research_gap;
    expect($this->project->fresh()->researchGapContext())->toContain('Pertanyaan pilihan saya?');
    $next = runGap($this);
    expect($this->project->fresh()->gap_analysis['id'])->toBe($next)->and($this->project->fresh()->research_gap)->toBe($choice);
    $this->putJson($this->url.'/research-gap', $payload)->assertUnprocessable();
    $this->delete($this->url.'/research-gap')->assertSessionHasNoErrors();
    expect($this->project->fresh()->researchGapContext())->toBe('')->and($this->project->fresh()->gap_analysis['id'])->toBe($next);
});

it('mengukur kredit AI pemilik di worker dan tidak memproses job ganda', function () {
    $user = $this->project->user;
    $user->forceFill(['unlimited' => false, 'subscription_until' => now()->addMonth()])->save();
    app(Billing::class)->change($user, $user, 'credits', ['amount' => 100, 'note' => 'Fixture']);
    Http::fake(['ai.test/*' => Http::response(gapAiResponse(gapReply($this->sources)))]);
    $id = runGap($this);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    Http::assertSentCount(1);
    $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'writing_run_id' => $id, 'status' => 'charged', 'credits' => -2]);
    expect(app(Billing::class)->balance($user))->toBe(98);
});

it('membaca sumber kosong per job dan melewati sumber yang gagal dengan keterangan', function () {
    $unreadable = Reference::factory()->for($this->project)->create(['notes' => null]);
    $this->mock(ArticleReader::class)->shouldReceive('read')->once()->andThrow(new AiException('Teks artikel tidak dapat dibaca.'));
    Http::fake(['ai.test/*' => Http::response(gapAiResponse(gapReply($this->sources)))]);
    $ids = [...$this->sources->pluck('id')->all(), $unreadable->id];
    $id = $this->postJson($this->url.'/writing', ['kind' => 'gap', 'focus' => 'Speaking', 'references' => $ids])->assertAccepted()->json('run.id');
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    (new WriteProjectStep($id, 1))->handle(app(Writing::class));
    $analysis = $this->project->fresh()->gap_analysis;
    expect($analysis['excluded'][0]['id'])->toBe($unreadable->id)->and($analysis['sources'])->toHaveCount(2);
    Http::assertSentCount(1);
});

it('menyimpan catatan pembacaan untuk digunakan kembali', function () {
    $this->sources[1]->update(['notes' => null]);
    $notes = 'Siswa membutuhkan umpan balik untuk latihan berbicara. Kebutuhan siswa SMP perlu ditelaah lebih lanjut.';
    $this->mock(ArticleReader::class)->shouldReceive('read')->once()->andReturn($notes);
    $this->sources[1]->notes = $notes;
    Http::fake(['ai.test/*' => Http::response(gapAiResponse(gapReply($this->sources)))]);
    $id = $this->postJson($this->url.'/writing', ['kind' => 'gap', 'focus' => 'Speaking', 'references' => $this->sources->pluck('id')->all()])->assertAccepted()->json('run.id');
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    (new WriteProjectStep($id, 1))->handle(app(Writing::class));
    expect($this->sources[1]->fresh()->notes)->toBe($notes)->and($this->project->fresh()->gap_analysis['id'])->toBe($id);
});

it('gagal tanpa mengarang gap ketika sumber yang dapat digunakan kurang dari dua', function () {
    $this->sources[1]->update(['notes' => null]);
    $this->mock(ArticleReader::class)->shouldReceive('read')->andThrow(new AiException('Tidak terbaca.'));
    Http::preventStrayRequests();
    $id = $this->postJson($this->url.'/writing', ['kind' => 'gap', 'focus' => 'Speaking', 'references' => $this->sources->pluck('id')->all()])->assertAccepted()->json('run.id');
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    (new WriteProjectStep($id, 1))->handle(app(Writing::class));
    expect(WritingRun::findOrFail($id)->status)->toBe('failed')->and($this->project->fresh()->gap_analysis)->toBeNull();
    Http::assertNothingSent();
});

it('menolak sumber cuplikan atau klaim kebaruan yang tidak valid', function (string $invalid) {
    $data = gapReply($this->sources);
    match ($invalid) {
        'source' => $data['candidates'][0]['source_ids'][0] = 999999,
        'quote' => $data['candidates'][0]['evidence'][0]['quote'] = 'Kutipan palsu yang tidak ada dalam catatan sumber.',
        'novelty' => $data['candidates'][0]['gap'] = 'Topik ini belum pernah diteliti.',
    };
    Http::fake(['ai.test/*' => Http::response(gapAiResponse($data))]);
    $id = runGap($this);
    expect(WritingRun::findOrFail($id)->status)->toBe('failed')->and($this->project->fresh()->gap_analysis)->toBeNull();
})->with(['source', 'quote', 'novelty']);

it('tidak menimpa hasil lama jika catatan berubah saat AI menganalisis', function () {
    Http::fake(['ai.test/*' => Http::response(gapAiResponse(gapReply($this->sources)))]);
    runGap($this);
    $old = $this->project->fresh()->gap_analysis;
    Http::fake(function () {
        $data = gapReply($this->sources);
        $this->sources[0]->update(['notes' => 'Catatan baru yang diedit saat analisis berjalan.']);

        return Http::response(gapAiResponse($data));
    });
    $id = runGap($this);
    expect(WritingRun::findOrFail($id)->status)->toBe('failed')->and($this->project->fresh()->gap_analysis)->toBe($old);
});

it('mengabaikan konteks lama dan menolak memilih kandidat ketika sumber berubah', function () {
    Http::fake(['ai.test/*' => Http::response(gapAiResponse(gapReply($this->sources)))]);
    $id = runGap($this);
    $payload = ['analysis_id' => $id, 'candidate' => 0, 'gap' => 'Gap yang ditinjau.', 'question' => 'Pertanyaan riset?', 'contribution' => 'Kontribusi riset.', 'reviewed' => true];
    $this->put($this->url.'/research-gap', $payload)->assertSessionHasNoErrors();
    $this->sources[0]->delete();
    expect($this->project->fresh()->researchGapContext())->toBe('');
    $this->get($this->url.'/research-gap')->assertInertia(fn ($page) => $page->where('analysisStale', true)->where('selectedStale', true));
    $this->putJson($this->url.'/research-gap', $payload)->assertUnprocessable();
});

it('menyimpan matriks tanpa memaksakan kandidat ketika bukti tidak cukup', function () {
    $data = gapReply($this->sources);
    $data['candidates'] = [];
    Http::fake(['ai.test/*' => Http::response(gapAiResponse($data))]);
    $id = runGap($this);
    expect(WritingRun::findOrFail($id)->status)->toBe('completed')->and($this->project->fresh()->gap_analysis['candidates'])->toBe([]);
});

it('mengirim gap pilihan sebagai arahan kerangka dan draf', function () {
    Http::fake(['ai.test/*' => Http::sequence()
        ->push(gapAiResponse(gapReply($this->sources)))
        ->push(gapAiResponse(['chapters' => array_fill(0, count($this->project->document_type->structure()), ['sections' => ['Kebutuhan umpan balik']])]))
        ->push(gapAiResponse(['text' => 'Usulan arah penelitian.', 'limitations' => 'Belum memuat klaim dari sumber.']))]);
    $id = runGap($this);
    $this->put($this->url.'/research-gap', ['analysis_id' => $id, 'candidate' => 0, 'gap' => 'Gap pilihan pengguna.', 'question' => 'Pertanyaan umpan balik pilihan pengguna?', 'contribution' => 'Pemetaan kebutuhan siswa.', 'reviewed' => true])->assertSessionHasNoErrors();

    $this->postJson($this->url.'/outline/generate')->assertOk();
    $this->postJson($this->url.'/draft/generate', ['unit' => 's2', 'references' => []])->assertOk();
    expect(Http::recorded(fn ($request) => str_contains($request['messages'][1]['content'], 'Pertanyaan umpan balik pilihan pengguna?')))->toHaveCount(2);
});
