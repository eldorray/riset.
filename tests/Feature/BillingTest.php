<?php

use App\Ai\AiException;
use App\Billing\Billing;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'gpt-6-luna', 'timeout' => 5, 'json_mode' => true]]);
    $this->user = User::factory()->create(['unlimited' => false]);
    $this->admin = User::factory()->create(['role' => 'admin', 'unlimited' => false]);
    $this->billing = app(Billing::class);
});

function activateBilling(Billing $billing, User $user, User $admin): int
{
    $id = $billing->purchase($user, 1);
    $billing->approve($id, $admin, 'Pembayaran terverifikasi', true);

    return $id;
}

it('snapshots a purchase and approves it exactly once', function () {
    $this->actingAs($this->user)->post('/account/subscription', ['kind' => 'subscription', 'plan_id' => 1])->assertSessionHasNoErrors();
    $id = DB::table('billing_requests')->value('id');
    expect($this->user->fresh()->subscription_until)->toBeNull();
    DB::table('billing_plans')->where('id', 1)->update(['credits' => 999, 'price' => 50000]);
    $this->actingAs($this->admin)->post("/admin/billing/requests/{$id}", ['approve' => true, 'note' => 'Dibayar'])->assertSessionHasNoErrors();
    $this->post("/admin/billing/requests/{$id}", ['approve' => true, 'note' => 'Dibayar'])->assertSessionHasNoErrors();
    expect($this->billing->balance($this->user))->toBe(600)
        ->and(DB::table('credit_grants')->count())->toBe(1)
        ->and(DB::table('billing_requests')->value('price'))->toBe(19000);
});

it('queues early renewal without credit refill and handles month ends', function () {
    $this->travelTo(now()->setDate(2026, 1, 31)->setTime(12, 0));
    activateBilling($this->billing, $this->user, $this->admin);
    expect($this->user->fresh()->subscription_until->format('Y-m-d'))->toBe('2026-02-28');
    activateBilling($this->billing, $this->user->fresh(), $this->admin);
    expect($this->billing->balance($this->user))->toBe(600)
        ->and($this->user->fresh()->subscription_until->format('Y-m-d'))->toBe('2026-03-28');
    $this->travelTo(now()->setDate(2026, 2, 28)->setTime(12, 0));
    expect($this->billing->balance($this->user))->toBe(600);
});

it('edits expiry without new credits and supports permanent and temporary unlimited', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $this->actingAs($this->admin)->put("/admin/billing/users/{$this->user->id}", ['action' => 'expiry', 'until' => '2027-01-01', 'note' => 'Perpanjangan khusus'])->assertSessionHasNoErrors();
    expect($this->billing->balance($this->user))->toBe(600)->and(DB::table('credit_grants')->count())->toBe(1);
    $this->put("/admin/billing/users/{$this->user->id}", ['action' => 'unlimited', 'enabled' => true, 'until' => '', 'note' => 'Akses khusus'])->assertSessionHasNoErrors();
    expect($this->billing->unlimited($this->user->fresh()))->toBeTrue()->and($this->user->fresh()->role)->toBe('pengguna');
    $this->put("/admin/billing/users/{$this->user->id}", ['action' => 'unlimited', 'enabled' => true, 'until' => '2026-10-03', 'note' => 'Uji coba'])->assertSessionHasNoErrors();
    $this->travelTo(now()->setDate(2026, 10, 4));
    expect($this->billing->unlimited($this->user->fresh()))->toBeFalse();
    $this->put("/admin/billing/users/{$this->user->id}", ['action' => 'unlimited', 'enabled' => false, 'note' => 'Cabut'])->assertSessionHasNoErrors();
    expect($this->billing->balance($this->user))->toBe(600);
});

it('denies unpaid AI but still allows project access and the subscription page', function () {
    Http::fake();
    $project = Project::factory()->for($this->user)->create();
    $this->actingAs($this->user)->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => [['question' => 'Topik?', 'answer' => 'Pendidikan']]])->assertStatus(402);
    Http::assertNothingSent();
    $this->get("/projects/{$project->id}")->assertOk();
    $this->get('/account/subscription')->assertOk()->assertInertia(fn ($page) => $page->component('account/Subscription')->where('account.balance', 0));
});

it('charges actual tokens including reasoning and returns unused reservation', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    Http::fake(['ai.test/*' => Http::response(['usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 500, 'completion_tokens_details' => ['reasoning_tokens' => 250]], 'choices' => [['message' => ['content' => json_encode(['feedback' => 'Baik', 'question' => 'Masalahnya apa?'])]]]])]);
    $this->actingAs($this->user)->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => [['question' => 'Topik?', 'answer' => 'Pendidikan']]])->assertOk();
    expect($this->billing->balance($this->user))->toBe(597)
        ->and(DB::table('credit_transactions')->where('kind', 'ai')->value('credits'))->toBe(-3);
    Http::assertSent(fn ($request) => $request['max_completion_tokens'] === 8192);
});

it('refunds provider failure missing usage and feature-invalid output', function (array $response, int $status) {
    activateBilling($this->billing, $this->user, $this->admin);
    Http::fake(['ai.test/*' => Http::response($response, $status)]);
    $this->actingAs($this->user)->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => [['question' => 'Topik?', 'answer' => 'Pendidikan']]])->assertStatus(502);
    expect($this->billing->balance($this->user))->toBe(600)
        ->and(DB::table('credit_transactions')->where('kind', 'ai')->value('status'))->toBe('refunded');
})->with([
    'provider unavailable' => [[], 500],
    'invalid JSON' => [['usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 250], 'choices' => [['message' => ['content' => 'not JSON']]]], 200],
    'no usage' => [['choices' => [['message' => ['content' => '{"feedback":"Baik","question":"Apa?"}']]]], 200],
    'invalid feature shape' => [['usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 250], 'choices' => [['message' => ['content' => '{"feedback":"Baik"}']]]], 200],
]);

it('prevents overspending with outstanding reservations', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $id = $this->billing->reserve($this->user, 590, 'gpt-6-luna', 'Panggilan pertama');
    expect($this->billing->balance($this->user))->toBe(10);
    expect(fn () => $this->billing->reserve($this->user, 20, 'gpt-6-luna', 'Panggilan bersamaan'))->toThrow(AiException::class);
    expect($this->billing->balance($this->user))->toBe(10);
    $this->billing->complete(false);
    expect($this->billing->balance($this->user))->toBe(600);
});

it('keeps unlimited calls free without granting admin authority', function () {
    $this->user->unlimited = true;
    $this->user->save();
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => '{"feedback":"Baik","question":"Apa?"}']]]])]);
    $this->actingAs($this->user)->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => [['question' => 'Topik?', 'answer' => 'Pendidikan']]])->assertOk();
    expect(DB::table('credit_transactions')->where('kind', 'ai')->value('credits'))->toBe(0);
    $this->get('/admin/billing')->assertForbidden();
});

it('expires topups and consumes the nearest expiration first', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $id = $this->billing->purchase($this->user, null);
    $this->billing->approve($id, $this->admin, 'Top-up dibayar', true);
    expect($this->billing->balance($this->user))->toBe(900);
    $this->billing->reserve($this->user, 20, 'model', 'test');
    $this->billing->usage(DB::table('credit_transactions')->where('kind', 'ai')->value('id'), ['prompt_tokens' => 20000, 'completion_tokens' => 0]);
    $this->billing->complete(true);
    expect(DB::table('credit_grants')->where('kind', 'subscription')->value('remaining'))->toBe(590)
        ->and(DB::table('credit_grants')->where('kind', 'topup')->value('remaining'))->toBe(300);
    $this->travel(91)->days();
    expect($this->billing->balance($this->user))->toBe(0);
});

it('isolates user history and protects all admin billing mutations', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $other = User::factory()->create(['unlimited' => false]);
    $this->actingAs($other)->get('/account/subscription')->assertInertia(fn ($page) => $page->where('history.total', 0)->where('requests', []));
    $this->put('/admin/billing/plans/1', ['name' => 'Abuse'])->assertForbidden();
    $this->put("/admin/billing/users/{$other->id}", ['action' => 'unlimited', 'enabled' => true])->assertForbidden();
    $this->post('/admin/billing/requests/1', ['approve' => true])->assertForbidden();
});

it('recovers stale reservations once without creating extra credit', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $id = $this->billing->reserve($this->user, 500, 'model', 'Proses terputus');
    DB::table('credit_transactions')->where('id', $id)->update(['created_at' => now()->subHours(2)]);
    $this->billing->reserve($this->user, 20, 'model', 'Proses baru');
    expect($this->billing->balance($this->user))->toBe(580);
    $this->billing->complete(false);
    $this->billing->complete(false);
    expect($this->billing->balance($this->user))->toBe(600);
});

it('does not debit a rejected retry and keeps successfully stored source notes paid', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $first = $this->billing->reserve($this->user, 50, 'model', 'Baca artikel');
    $this->billing->usage($first, ['prompt_tokens' => 2000, 'completion_tokens' => 250]);
    $this->billing->checkpoint();
    $rejected = $this->billing->reserve($this->user, 50, 'model', 'Draf tanpa sitasi');
    $this->billing->usage($rejected, ['prompt_tokens' => 2000, 'completion_tokens' => 250]);
    $this->billing->discardLast();
    $this->billing->reserve($this->user, 50, 'model', 'Draf gagal');
    $this->billing->complete(false);
    expect($this->billing->balance($this->user))->toBe(598)
        ->and(DB::table('credit_transactions')->where('id', $first)->value('status'))->toBe('charged')
        ->and(DB::table('credit_transactions')->where('id', $rejected)->value('status'))->toBe('refunded');
});

it('requires reason for adjustments and refuses a negative balance', function () {
    activateBilling($this->billing, $this->user, $this->admin);
    $this->actingAs($this->admin)->put("/admin/billing/users/{$this->user->id}", ['action' => 'credits', 'amount' => -601, 'note' => 'Koreksi'])
        ->assertSessionHasErrors('amount');
    $this->put("/admin/billing/users/{$this->user->id}", ['action' => 'credits', 'amount' => 100])->assertSessionHasErrors('note');
    expect($this->billing->balance($this->user))->toBe(600);
    $this->put("/admin/billing/users/{$this->user->id}", ['action' => 'credits', 'amount' => -50, 'note' => 'Koreksi saldo'])->assertSessionHasNoErrors();
    expect($this->billing->balance($this->user))->toBe(550);
});

it('rejects duplicate pending requests and disables new purchases of inactive plans', function () {
    $this->actingAs($this->user)->post('/account/subscription', ['kind' => 'subscription', 'plan_id' => 1])->assertSessionHasNoErrors();
    $this->post('/account/subscription', ['kind' => 'subscription', 'plan_id' => 1])->assertSessionHasErrors('plan_id');
    $request = DB::table('billing_requests')->value('id');
    $this->billing->approve($request, $this->admin, 'Ditolak', false);
    DB::table('billing_plans')->where('id', 1)->update(['active' => false]);
    $this->post('/account/subscription', ['kind' => 'subscription', 'plan_id' => 1])->assertSessionHasErrors('plan_id');
    expect(DB::table('credit_grants')->count())->toBe(0);
});

it('lets only admins publish WhatsApp and bank payment instructions', function () {
    $data = ['whatsapp' => '6281234567890', 'bank' => 'Bank Contoh', 'account_number' => '123456789', 'account_holder' => 'Admin Riset', 'instructions' => 'Cantumkan nomor permintaan pada konfirmasi pembayaran.'];
    $this->actingAs($this->user)->put('/admin/billing/payment', $data)->assertForbidden();
    $this->actingAs($this->admin)->put('/admin/billing/payment', $data)->assertSessionHasNoErrors();
    $this->actingAs($this->user)->get('/account/subscription')->assertInertia(fn ($page) => $page
        ->where('payment.whatsapp', $data['whatsapp'])
        ->where('payment.bank', $data['bank'])
        ->where('payment.account_number', $data['account_number']));
    $this->actingAs($this->admin)->put('/admin/billing/payment', [...$data, 'whatsapp' => 'javascript:alert(1)'])->assertSessionHasErrors('whatsapp');
});
