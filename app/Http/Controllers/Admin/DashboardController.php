<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CitationStyle;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\DocxTemplate;
use App\Models\Project;
use App\Models\Reference;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ringkasan dan status integrasi. Hanya angka agregat — isi proyek pengguna tidak ditampilkan.
 */
final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $ai = filled(config('services.ai.base_url')) && filled(config('services.ai.model'));

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('role', 'admin')->count(),
                'projects' => Project::query()->count(),
                'references' => Reference::query()->count(),
                'searched' => Reference::query()->where('input_method', 'search')->count(),
            ],
            'services' => [
                [
                    'name' => 'Masuk dengan Google',
                    'ok' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
                    'detail' => 'GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET di .env',
                ],
                [
                    'name' => 'Login manual',
                    'ok' => true,
                    'detail' => User::query()->whereNotNull('password')->count().' akun dengan password (dibuat admin)',
                ],
                [
                    'name' => 'Layanan AI',
                    'ok' => $ai,
                    'detail' => $ai ? 'Model: '.config('services.ai.model') : 'AI_BASE_URL dan AI_MODEL di .env',
                ],
                [
                    'name' => 'Pencarian referensi',
                    'ok' => true,
                    'detail' => 'Crossref, OpenAlex, DOAJ'.(filled(config('services.semantic_scholar.api_key')) ? ', Semantic Scholar' : ' · Semantic Scholar tanpa kunci (sering dibatasi)').(filled(config('services.crossref.mailto')) ? '' : ' · isi CROSSREF_MAILTO agar lebih stabil'),
                ],
            ],
            'styles' => array_map(fn (CitationStyle $style): string => $style->label(), CitationStyle::enabled()),
            'types' => array_map(fn (DocumentType $type): array => [
                'label' => $type->label(),
                'chapters' => count($type->structure()),
                'custom' => Setting::read("structures.{$type->value}") !== null,
            ], DocumentType::cases()),
            'templates' => DocxTemplate::query()->count(),
            'devices' => $this->devices(),
        ]);
    }

    /**
     * Porsi perangkat 30 hari terakhir dari analitik internal (lihat RecordPageVisit).
     *
     * @return array{rows: list<array{device: string, label: string, views: int, users: int}>, views: int, users: int, pwa: int, since: string}
     */
    private function devices(): array
    {
        $since = now()->subDays(29)->toDateString();
        $stats = DB::table('page_visits')->where('date', '>=', $since)
            ->selectRaw('device, sum(views) as views, count(distinct case when user_id > 0 then user_id end) as users')
            ->groupBy('device')->get()->keyBy('device');
        $labels = ['mobile' => 'Ponsel', 'tablet' => 'Tablet', 'desktop' => 'Desktop / laptop'];

        return [
            'rows' => array_map(fn (string $device, string $label): array => [
                'device' => $device,
                'label' => $label,
                'views' => (int) ($stats[$device]->views ?? 0),
                'users' => (int) ($stats[$device]->users ?? 0),
            ], array_keys($labels), $labels),
            'views' => (int) $stats->sum('views'),
            'users' => (int) DB::table('page_visits')->where('date', '>=', $since)->where('user_id', '>', 0)->distinct()->count('user_id'),
            'pwa' => (int) DB::table('page_visits')->where('date', '>=', $since)->where('pwa', true)->sum('views'),
            'since' => $since,
        ];
    }
}
