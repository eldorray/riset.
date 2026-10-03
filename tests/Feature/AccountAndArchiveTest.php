<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('mengarsipkan dan memulihkan proyek tanpa menghapus isi', function () {
    $project = Project::factory()->withOutline()->create(['draft' => ['s1' => 'Tulisan tetap ada.']]);
    $this->actingAs($project->user)->patch("/projects/{$project->id}/archive", ['archived' => true])->assertSessionHasNoErrors();
    $this->get('/projects')->assertInertia(fn ($page) => $page->has('projects', 0));
    $this->get('/projects?archived=1')->assertInertia(fn ($page) => $page->has('projects', 1));
    $this->patch("/projects/{$project->id}/archive", ['archived' => false])->assertSessionHasNoErrors();
    expect($project->fresh()->draft['s1'])->toBe('Tulisan tetap ada.')
        ->and($project->fresh()->archived_at)->toBeNull();
    $this->get('/projects')->assertInertia(fn ($page) => $page->has('projects', 1));
    $this->actingAs(User::factory()->create())->patch("/projects/{$project->id}/archive", ['archived' => true])->assertForbidden();
});

it('memerlukan password lama dan konfirmasi untuk mengganti password', function () {
    $user = User::factory()->create(['password' => 'password-lama']);
    $this->actingAs($user)->put('/account/password', [
        'current_password' => 'salah', 'password' => 'password-baru', 'password_confirmation' => 'password-baru',
    ])->assertSessionHasErrors('current_password');
    expect(Hash::check('password-lama', $user->fresh()->password))->toBeTrue();
    $this->put('/account/password', [
        'current_password' => 'password-lama', 'password' => 'password-baru', 'password_confirmation' => 'password-baru',
    ])->assertSessionHasNoErrors();
    expect(Hash::check('password-baru', $user->fresh()->password))->toBeTrue();
});

it('mengirim tautan reset tanpa membuka keberadaan akun dan token hanya sekali pakai', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => 'password-lama']);
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
    Notification::assertSentTo($user, ResetPassword::class);
    $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHasNoErrors();
    $token = Password::createToken($user);
    $payload = ['email' => $user->email, 'token' => $token, 'password' => 'password-baru', 'password_confirmation' => 'password-baru'];
    $this->post('/reset-password', [...$payload, 'token' => 'invalid'])->assertSessionHasErrors('email');
    expect(Hash::check('password-lama', $user->fresh()->password))->toBeTrue();
    $this->post('/reset-password', $payload)->assertRedirect('/masuk');
    expect(Hash::check('password-baru', $user->fresh()->password))->toBeTrue();
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
});
