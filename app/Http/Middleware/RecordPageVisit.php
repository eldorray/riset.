<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Analitik internal tanpa cookie dan tanpa layanan pihak ketiga: menghitung kunjungan halaman
 * per hari dan jenis perangkat, untuk melihat porsi pengguna ponsel dan pemakaian PWA.
 * Polling JSON, partial reload, bot, dan akun admin tidak dihitung.
 */
final class RecordPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->countable($request, $response)) {
            try {
                DB::table('page_visits')->upsert(
                    [[
                        'date' => now()->toDateString(),
                        'device' => self::device((string) $request->userAgent()),
                        'user_id' => (int) $request->user()?->getAuthIdentifier(),
                        'pwa' => $request->query('source') === 'pwa',
                        'views' => 1,
                    ]],
                    ['date', 'device', 'user_id', 'pwa'],
                    ['views' => DB::raw('views + 1')],
                );
            } catch (Throwable $e) {
                report($e); // analitik tidak boleh menggagalkan halaman
            }
        }

        return $response;
    }

    /**
     * ponytail: deteksi dari User-Agent; iPadOS modern melapor sebagai desktop Safari, jadi masuk "desktop".
     *
     * @return 'mobile'|'tablet'|'desktop'
     */
    public static function device(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|android(?!.*mobi)/i', $agent) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }

    private function countable(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful() || $request->expectsJson() || $request->hasHeader('X-Inertia-Partial-Data')) {
            return false;
        }
        $page = $response->headers->has('X-Inertia') || str_contains((string) $response->headers->get('Content-Type'), 'text/html');

        $admin = $request->user() instanceof User && $request->user()->isAdmin();

        return $page && ! $admin && ! preg_match('/bot|crawl|spider|slurp|preview|headless/i', (string) $request->userAgent());
    }
}
