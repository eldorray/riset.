<?php

declare(strict_types=1);

use App\Http\Middleware\RecordPageVisit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
const ANDROID_TABLET = 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/129.0 Safari/537.36';
const DESKTOP = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/129.0 Safari/537.36';

it('mengenali jenis perangkat dari user agent', function () {
    expect(RecordPageVisit::device(IPHONE))->toBe('mobile')
        ->and(RecordPageVisit::device('Mozilla/5.0 (Linux; Android 14; Pixel 8) Chrome/129.0 Mobile Safari/537.36'))->toBe('mobile')
        ->and(RecordPageVisit::device(ANDROID_TABLET))->toBe('tablet')
        ->and(RecordPageVisit::device(DESKTOP))->toBe('desktop');
});

it('menghitung kunjungan halaman per perangkat tanpa polling, partial reload, bot, atau admin', function () {
    $project = Project::factory()->create();
    $user = $project->user;

    $this->withHeader('User-Agent', IPHONE)->get('/')->assertOk();
    $this->actingAs($user)->withHeader('User-Agent', IPHONE)->get('/projects?source=pwa')->assertOk();
    $this->withHeader('User-Agent', IPHONE)->get('/projects')->assertOk();
    $this->withHeader('User-Agent', DESKTOP)->get("/projects/{$project->id}")->assertOk();
    $this->withHeader('User-Agent', DESKTOP)->getJson("/projects/{$project->id}/writing")->assertOk();
    $this->withHeaders(['User-Agent' => DESKTOP, 'X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'references', 'X-Inertia-Partial-Component' => 'projects/Show'])->get("/projects/{$project->id}");
    $this->flushHeaders();
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/')->assertOk();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->withHeader('User-Agent', DESKTOP)->get('/projects')->assertOk();

    $rows = DB::table('page_visits')->orderBy('id')->get(['device', 'user_id', 'pwa', 'views'])->map(fn ($row) => (array) $row)->all();
    expect($rows)->toBe([
        ['device' => 'mobile', 'user_id' => 0, 'pwa' => 0, 'views' => 1],
        ['device' => 'mobile', 'user_id' => $user->id, 'pwa' => 1, 'views' => 1],
        ['device' => 'mobile', 'user_id' => $user->id, 'pwa' => 0, 'views' => 1],
        ['device' => 'desktop', 'user_id' => $user->id, 'pwa' => 0, 'views' => 1],
    ]);
});

it('menampilkan porsi perangkat 30 hari di dashboard admin', function () {
    DB::table('page_visits')->insert([
        ['date' => now()->toDateString(), 'device' => 'mobile', 'user_id' => 5, 'pwa' => true, 'views' => 3],
        ['date' => now()->toDateString(), 'device' => 'desktop', 'user_id' => 5, 'pwa' => false, 'views' => 1],
        ['date' => now()->subDays(40)->toDateString(), 'device' => 'desktop', 'user_id' => 6, 'pwa' => false, 'views' => 50],
    ]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')
        ->assertInertia(fn ($page) => $page->where('devices.views', 4)->where('devices.users', 1)->where('devices.pwa', 3)
            ->where('devices.rows.0', ['device' => 'mobile', 'label' => 'Ponsel', 'views' => 3, 'users' => 1]));
});
