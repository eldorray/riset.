<?php

use App\Billing\Billing;
use App\Jobs\WriteProjectStep;
use App\Models\Project;
use App\Models\Reference;
use App\Models\WritingRun;
use App\Writing\Writing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
    $this->queues = app('queue');
    Queue::fake();
    $this->project = Project::factory()->withOutline()->create();
    $this->project->user->forceFill(['unlimited' => true])->save();
    $this->reference = Reference::factory()->for($this->project)->create(['notes' => 'Literasi mendukung belajar.']);
    $this->actingAs($this->project->user);
});

it('queues once and keeps draft suggestions after navigating until accepted', function () {
    $url = "/projects/{$this->project->id}/writing";
    $data = ['kind' => 'draft', 'unit' => 's2', 'references' => [$this->reference->id]];
    $id = $this->postJson($url, $data)->assertAccepted()->json('run.id');
    $this->postJson($url, $data)->assertStatus(409);
    Queue::assertPushed(WriteProjectStep::class, 1);
    $text = "Literasi mendukung belajar [@{$this->reference->id}].";
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['text' => $text, 'limitations' => ''])]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 250]])]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    $this->getJson($url)->assertOk()->assertJsonPath('run.status', 'completed')->assertJsonPath('suggestions.0.text', $text);
    expect($this->project->fresh()->draft)->toBeNull();
    $this->postJson("$url/$id/review", ['index' => 0, 'action' => 'accept'])->assertOk();
    expect($this->project->fresh()->draft['s2'])->toBe($text);
    $this->getJson($url)->assertJsonCount(0, 'suggestions');
});

function writingResponse(string $text): array
{
    return ['choices' => [['message' => ['content' => json_encode(['text' => $text, 'keywords' => ['literasi'], 'limitations' => ''])]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 250]];
}

it('isolates job status results and controls from other users and projects', function () {
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'draft', 'unit' => 's2', 'references' => []])->assertAccepted()->json('run.id');
    $other = Project::factory()->create();
    $this->actingAs($other->user)->getJson($url)->assertForbidden();
    $this->postJson("$url/$id/stop")->assertForbidden();
    $this->postJson("$url/$id/review", ['index' => 0, 'action' => 'accept'])->assertForbidden();
    $this->postJson("/projects/{$other->id}/writing/$id/stop")->assertNotFound();
});

it('meters worker calls explicitly and ignores duplicate job delivery', function () {
    $user = $this->project->user;
    $user->forceFill(['unlimited' => false, 'subscription_until' => now()->addMonth()])->save();
    app(Billing::class)->change($user, $user, 'credits', ['amount' => 100, 'note' => 'Fixture']);
    $id = $this->postJson("/projects/{$this->project->id}/writing", ['kind' => 'draft', 'unit' => 's2', 'references' => [$this->reference->id]])->assertAccepted()->json('run.id');
    auth()->forgetGuards();
    request()->setUserResolver(fn () => null);
    Http::fake(['ai.test/*' => Http::response(writingResponse("Texte [@{$this->reference->id}]."))]);
    $job = new WriteProjectStep($id, 0);
    $job->handle(app(Writing::class));
    $job->handle(app(Writing::class));
    expect(app(Billing::class)->balance($user))->toBe(98);
    Http::assertSentCount(1);
    $this->assertDatabaseHas('credit_transactions', ['writing_run_id' => $id, 'user_id' => $user->id, 'credits' => -2, 'status' => 'charged']);
});

it('preserves manual edits when accepting a stale draft suggestion', function () {
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'draft', 'unit' => 's2', 'references' => []])->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response(writingResponse('Usulan AI.'))]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    $this->project->update(['draft' => ['s2' => 'Edit baru.']]);
    $this->postJson("$url/$id/review", ['index' => 0, 'action' => 'accept'])->assertStatus(409);
    expect($this->project->fresh()->draft['s2'])->toBe('Edit baru.');
    $this->getJson($url)->assertJsonCount(1, 'suggestions');
    $this->postJson("$url/$id/review", ['index' => 0, 'action' => 'discard'])->assertOk()->assertJsonCount(0, 'suggestions');
});

it('saves manuscript sections across jobs and does not overwrite a later edited section', function () {
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'manuscript', 'references' => [], 'mode' => 'fill', 'target_words' => 1000])->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response(writingResponse('Hasil bagian pertama.'))]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    $run = WritingRun::findOrFail($id);
    $first = $run->payload['steps'][0]['key'];
    $second = $run->payload['steps'][1]['key'];
    expect($this->project->fresh()->draft[$first])->toBe('Hasil bagian pertama.');
    $this->project->refresh()->update(['draft' => [...$this->project->draft, $second => 'Edit pengguna.']]);
    (new WriteProjectStep($id, 1))->handle(app(Writing::class));
    expect($this->project->fresh()->draft[$second])->toBe('Edit pengguna.');
    $this->getJson($url)->assertJsonPath('run.results.1.status', 'failed');
    Http::assertSentCount(1);
});

it('stops queued work without an AI call and preserves results when stopping a running step', function () {
    $url = "/projects/{$this->project->id}/writing";
    $data = ['kind' => 'draft_all', 'units' => ['s1', 's2'], 'references' => []];
    $id = $this->postJson($url, $data)->assertAccepted()->json('run.id');
    $this->postJson("$url/$id/stop")->assertOk()->assertJsonPath('run.status', 'stopped');
    Http::fake(function () use (&$id) {
        app(Writing::class)->stop(WritingRun::findOrFail($id));

        return Http::response(writingResponse('Usulan tersimpan.'));
    });
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    Http::assertNothingSent();
    $id = $this->postJson($url, $data)->assertAccepted()->json('run.id');
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    $this->getJson($url)->assertJsonPath('run.status', 'stopped')->assertJsonCount(1, 'suggestions');
});

it('stores front matter suggestions and accepts them without rewriting the body', function () {
    $this->project->update(['draft' => ['s1' => 'Penelitian berisi tujuan dan metode.']]);
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'front', 'part' => 'abstrak'])->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response(writingResponse('Abstrak penelitian.'))]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    expect($this->project->fresh()->front_matter)->toBeNull();
    $this->postJson("$url/$id/review", ['index' => 0, 'action' => 'accept'])->assertOk();
    expect($this->project->fresh()->frontText('abstrak'))->toBe('Abstrak penelitian.')
        ->and($this->project->fresh()->draft['s1'])->toBe('Penelitian berisi tujuan dan metode.');
});

it('recovers abandoned worker reservations without charging or resuming the job twice', function () {
    $user = $this->project->user;
    $user->forceFill(['unlimited' => false, 'subscription_until' => now()->addMonth()])->save();
    $billing = app(Billing::class);
    $billing->change($user, $user, 'credits', ['amount' => 100, 'note' => 'Fixture']);
    $id = $this->postJson("/projects/{$this->project->id}/writing", ['kind' => 'draft', 'unit' => 's2', 'references' => []])->assertAccepted()->json('run.id');
    $billing->writingRunId = $id;
    $billing->reserve($user, 40, 'test', 'Fixture');
    WritingRun::whereKey($id)->update(['status' => 'running', 'updated_at' => now()->subMinutes(36)]);
    $this->getJson("/projects/{$this->project->id}/writing")->assertJsonPath('run.status', 'failed');
    expect($billing->balance($user))->toBe(100);
    (new WriteProjectStep($id, 0))->failed(new RuntimeException('Timeout'));
    expect($billing->balance($user))->toBe(100);
});

it('rejects repeated generation for a pending suggestion instead of hiding an older result', function () {
    $url = "/projects/{$this->project->id}/writing";
    $data = ['kind' => 'draft', 'unit' => 's2', 'references' => []];
    $id = $this->postJson($url, $data)->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response(writingResponse('Usulan pertama.'))]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    $this->postJson($url, $data)->assertStatus(409);
    $this->getJson($url)->assertJsonCount(1, 'suggestions');
});

it('refunds provider failure and stops after two consecutive failures', function () {
    $user = $this->project->user;
    $user->forceFill(['unlimited' => false, 'subscription_until' => now()->addMonth()])->save();
    app(Billing::class)->change($user, $user, 'credits', ['amount' => 100, 'note' => 'Fixture']);
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'draft_all', 'units' => ['s1', 's2'], 'references' => []])->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response('Unavailable', 503)]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    (new WriteProjectStep($id, 1))->handle(app(Writing::class));
    $this->getJson($url)->assertJsonPath('run.status', 'failed')->assertJsonCount(2, 'run.results');
    expect(app(Billing::class)->balance($user))->toBe(100);
    expect($this->project->fresh()->draft)->toBeNull();
});

it('rechecks subscription before queued calls and prevents stale failure callbacks from stopping later steps', function () {
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'draft_all', 'units' => ['s1', 's2'], 'references' => []])->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::response(writingResponse('Usulan.'))]);
    (new WriteProjectStep($id, 0))->handle(app(Writing::class));
    (new WriteProjectStep($id, 0))->failed(new RuntimeException('Late callback'));
    $this->getJson($url)->assertJsonPath('run.status', 'queued');
    $this->project->user->forceFill(['unlimited' => false])->save();
    (new WriteProjectStep($id, 1))->handle(app(Writing::class));
    Http::assertSentCount(1);
    $this->getJson($url)->assertJsonPath('run.results.1.status', 'failed');
});

it('rejects stale front matter saves and merges only changed parts after background writing', function () {
    $this->project->update(['front_matter' => ['abstrak' => ['text' => 'Abstrak dari AI.', 'keywords' => 'baru', 'ai' => true]]]);
    $url = "/projects/{$this->project->id}/manuscript";
    $this->putJson($url, ['parts' => ['abstrak' => ['text' => 'Edit usang.', 'keywords' => '']], 'base' => ['abstrak' => ['text' => '', 'keywords' => '']]])->assertStatus(409);
    $this->putJson($url, ['parts' => ['kata_pengantar' => ['text' => 'Terima kasih.', 'keywords' => '']], 'base' => ['kata_pengantar' => ['text' => '', 'keywords' => '']]])->assertRedirect();
    expect($this->project->fresh()->frontText('abstrak'))->toBe('Abstrak dari AI.')
        ->and($this->project->fresh()->frontText('kata_pengantar'))->toBe('Terima kasih.');
});

it('retains a usable failover queue connection', function () {
    $queue = $this->queues->connection('failover');
    $queue->pushRaw(json_encode(['displayName' => 'fixture']), 'fixture');
    $this->assertDatabaseHas('jobs', ['queue' => 'fixture']);
});

it('creates a missing outline then finishes an article across queued steps including abstracts', function () {
    $this->project->update(['document_type' => 'artikel', 'outline' => null]);
    $url = "/projects/{$this->project->id}/writing";
    $id = $this->postJson($url, ['kind' => 'manuscript', 'references' => [], 'mode' => 'fill', 'target_words' => 1000])->assertAccepted()->json('run.id');
    Http::fake(['ai.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['chapters' => [['sections' => []], ['sections' => []], ['sections' => []], ['sections' => []]]])]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 250]])
        ->push(writingResponse('Pendahuluan.'))->push(writingResponse('Metode.'))
        ->push(writingResponse('Hasil.'))->push(writingResponse('Kesimpulan.'))
        ->push(writingResponse('Abstrak.'))->push(writingResponse('Abstract.'))]);
    for ($step = 0; $step < 7; $step++) {
        (new WriteProjectStep($id, $step))->handle(app(Writing::class));
    }
    $this->getJson($url)->assertJsonPath('run.status', 'completed')->assertJsonPath('run.done', 7)->assertJsonCount(0, 'suggestions');
    expect($this->project->fresh()->units())->toHaveCount(4)
        ->and($this->project->fresh()->frontText('abstrak'))->toBe('Abstrak.')
        ->and($this->project->fresh()->frontText('abstract'))->toBe('Abstract.');
});
