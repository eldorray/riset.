<?php

namespace App\Providers;

use App\Ai\AiClient;
use App\Billing\Billing;
use App\References\ReferenceSearch;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Billing::class);
        $this->app->bind(AiClient::class, fn (): AiClient => AiClient::fromConfig());
        $this->app->bind(ReferenceSearch::class, fn (): ReferenceSearch => ReferenceSearch::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        DevCommands::artisan('queue:listen writing --queue=writing --tries=1 --timeout=1800', 'writing');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // ponytail: batas permintaan AI belum ditentukan PRD; 30/menit cukup untuk "buat semua bagian" (dijalankan berurutan).
        RateLimiter::for('ai', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id));
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id));
    }
}
