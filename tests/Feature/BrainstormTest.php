<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    User::created(function (User $user): void {
        $user->unlimited = true;
        $user->save();
    });
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => 'test', 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
});

it('guides three turns then returns three titles without creating a project', function () {
    $user = User::factory()->create();
    $sequence = Http::sequence();
    Http::fake(['ai.test/*' => $sequence]);
    $turns = [];
    foreach (range(1, 3) as $turn) {
        $turns[] = ['question' => 'Pertanyaan '.$turn, 'answer' => 'Jawaban '.$turn];
        $reply = $turn < 3
            ? ['feedback' => 'Fokus ini bisa dipersempit.', 'question' => 'Siapa objek penelitian Anda?']
            : ['feedback' => 'Konteks sudah cukup.', 'titles' => array_map(fn ($i) => ['title' => 'Judul '.$i, 'reason' => 'Sesuai fokus penelitian.'], range(1, 3))];
        $sequence->push(['choices' => [['message' => ['content' => json_encode($reply)]]]]);
        $this->actingAs($user)->postJson('/projects/brainstorm', ['document_type' => 'skripsi', 'turns' => $turns])
            ->assertOk()->assertJson($reply);
    }
    expect($user->projects()->count())->toBe(0);
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
        'document_type' => 'artikel', 'turns' => array_fill(0, 3, ['question' => 'Apa?', 'answer' => 'Pendidikan.']),
    ])->assertStatus(502);
});

it('reports AI service failure and requires authentication', function () {
    $payload = ['document_type' => 'tesis', 'turns' => [['question' => 'Bidang?', 'answer' => 'Pendidikan.']]];
    $this->postJson('/projects/brainstorm', $payload)->assertUnauthorized();
    Http::fake(['ai.test/*' => Http::response('down', 500)]);
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', $payload)
        ->assertStatus(502)->assertJsonPath('message', 'Layanan AI menolak permintaan (HTTP 500).');
});

it('rejects unreadable or incomplete AI responses', function (array $reply, int $count) {
    Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);
    $this->actingAs(User::factory()->create())->postJson('/projects/brainstorm', [
        'document_type' => 'skripsi', 'turns' => array_fill(0, $count, ['question' => 'Bidang?', 'answer' => 'Pendidikan.']),
    ])->assertStatus(502);
})->with([
    'missing follow-up' => [['feedback' => 'Baik.'], 1],
    'blank question' => [['feedback' => 'Baik.', 'question' => '   '], 1],
    'wrong title count' => [['feedback' => 'Baik.', 'titles' => [['title' => 'Judul', 'reason' => 'Alasan']]], 3],
]);
