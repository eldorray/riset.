<?php

namespace App\Http\Middleware;

use App\Billing\Billing;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $logo = Setting::read('app_logo');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'logo_url' => is_string($logo) ? '/branding/logo?v='.rawurlencode(basename($logo)) : null,
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only(['id', 'name', 'email', 'avatar']),
                    'is_admin' => $request->user()->isAdmin(),
                    'credits' => app(Billing::class)->balance($request->user()),
                    'ai_active' => app(Billing::class)->active($request->user()),
                    'unlimited' => app(Billing::class)->unlimited($request->user()),
                ] : null,
            ],
        ];
    }
}
