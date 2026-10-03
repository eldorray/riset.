<?php

namespace App\Http\Middleware;

use App\Billing\Billing;
use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MeterAiCredits
{
    public function __construct(private readonly Billing $billing) {}

    public function handle(Request $request, Closure $next): Response
    {
        $billing = $this->billing;
        $project = $request->route('project');
        if ($project instanceof Project) {
            Gate::authorize('update', $project);
        }
        $user = $request->user()?->fresh();
        if ($user === null || ! $billing->active($user)) {
            return response()->json(['message' => 'Akses AI belum aktif atau sudah berakhir. Buka Paket & Kredit untuk mengajukan aktivasi.', 'billing_url' => '/account/subscription'], 402);
        }
        try {
            $response = $next($request);
            $billing->complete($response->getStatusCode() >= 200 && $response->getStatusCode() < 300);

            return $response;
        } catch (Throwable $e) {
            $billing->complete(false);
            throw $e;
        }
    }
}
