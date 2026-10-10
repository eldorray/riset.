<?php

use App\Http\Controllers\BrainstormController;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    User::created(function (User $user): void {
        $user->unlimited = true;
        $user->save();
    });
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => 'test', 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
});

it('keeps discussing past three turns and returns titles with a summary only on request', function () {
    $user = User::factory()->create();
    $sequence = Http::sequence();
    Http::fake(['ai.test/*' => $sequence]);
    $turns = [];
    foreach (range(1, 5) as $turn) {
        $turns[] = ['question' => 'Pertanyaan '.$turn, 'answer' => 'Apa bedanya kualitatif dan kuantitatif? '.$turn];
        $reply = ['feedback' => 'Kualitatif menggali makna, kuantitatif mengukur.', 'question' => 'Data apa yang bisa Anda akses?', 'ready' => $turn >= 4];
        $sequence->push(['choices' => [['message' => ['content' => json_encode($reply)]]]]);
        $this->actingAs($user)->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => $turns])
            ->assertOk()->assertExactJson($reply);
    }
    $titles = ['feedback' => 'Fokus pada literasi digital siswa SMP.', 'titles' => array_map(fn ($i) => ['title' => 'Judul '.$i, 'reason' => 'Sesuai fokus penelitian.'], range(1, 3)), 'summary' => 'Topik: literasi digital. Masalah: belum dibahas.'];
    $sequence->push(['choices' => [['message' => ['content' => json_encode($titles)]]]]);
    $this->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => $turns, 'titles' => true])->assertOk()->assertExactJson($titles);

    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Apa bedanya kualitatif dan kuantitatif? 5'));
    expect($user->projects()->count())->toBe(0);
});

it('closes the discussion with titles at the turn limit', function () {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'feedback' => 'Fokus sudah jelas.', 'titles' => array_map(fn ($i) => ['title' => 'Judul '.$i, 'reason' => 'Alasan.'], range(1, 3)), 'summary' => 'Ringkasan.',
    ])]]]])]);
    $turns = array_fill(0, BrainstormController::MAX_TURNS, ['question' => 'Apa?', 'answer' => 'Pendidikan.']);
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', ['document_type' => 'artikel', 'turns' => $turns])
        ->assertOk()->assertJsonCount(3, 'titles')->assertJsonPath('summary', 'Ringkasan.');
    $this->postJson('/projects/brainstorm', ['document_type' => 'artikel', 'turns' => [...$turns, ['question' => 'Apa?', 'answer' => 'Lagi.']]])
        ->assertJsonValidationErrors('turns');
});

it('rejects invalid input before calling AI', function () {
    Http::fake();
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', ['document_type' => 'unknown', 'turns' => []])
        ->assertUnprocessable()->assertJsonValidationErrors(['document_type', 'turns']);
    Http::assertNothingSent();
});

it('rejects malformed or duplicate final titles', function () {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'feedback' => 'Baik.', 'titles' => array_fill(0, 3, ['title' => 'Judul sama', 'reason' => 'Alasan.']),
    ])]]]])]);
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', [
        'document_type' => 'artikel', 'turns' => array_fill(0, 3, ['question' => 'Apa?', 'answer' => 'Pendidikan.']), 'titles' => true,
    ])->assertStatus(502);
});

it('reports AI service failure and requires authentication', function () {
    $payload = ['document_type' => 'tesis', 'turns' => [['question' => 'Bidang?', 'answer' => 'Pendidikan.']]];
    $this->postJson('/projects/brainstorm', $payload)->assertUnauthorized();
    Http::fake(['ai.test/*' => Http::response('down', 500)]);
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', $payload)
        ->assertStatus(502)->assertJsonPath('message', 'Layanan AI menolak permintaan (HTTP 500).');
});

it('rejects unreadable or incomplete AI responses', function (array $reply, bool $titles) {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', [
        'document_type' => 'skripsi', 'turns' => [['question' => 'Bidang?', 'answer' => 'Pendidikan.']], 'titles' => $titles,
    ])->assertStatus(502);
})->with([
    'missing follow-up' => [['feedback' => 'Baik.'], false],
    'blank question' => [['feedback' => 'Baik.', 'question' => '   '], false],
    'wrong title count' => [['feedback' => 'Baik.', 'titles' => [['title' => 'Judul', 'reason' => 'Alasan']], 'summary' => 'Ringkasan.'], true],
    'missing summary' => [['feedback' => 'Baik.', 'titles' => array_map(fn ($i) => ['title' => 'Judul '.$i, 'reason' => 'Alasan.'], range(1, 3))], true],
]);
