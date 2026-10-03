<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SubscriptionController extends Controller
{
    public function index(Request $request, Billing $billing): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('account/Subscription', [
            'account' => $billing->account($user),
            'payment' => $billing->payment(),
            'plans' => DB::table('billing_plans')->where('active', true)->orderBy('price')->get(),
            'requests' => DB::table('billing_requests')->where('user_id', $user->id)->latest('id')->limit(20)->get(),
            'history' => DB::table('credit_transactions')->where('user_id', $user->id)->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request, Billing $billing): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:subscription,topup'], 'plan_id' => ['required_if:kind,subscription', 'nullable', 'integer', 'exists:billing_plans,id']]);
        $user = $request->user();
        abort_if($user === null, 403);
        $billing->purchase($user, $data['kind'] === 'subscription' ? (int) $data['plan_id'] : null);
        Inertia::flash('success', 'Permintaan dikirim. Hubungi admin untuk pembayaran dan aktivasi; paket belum aktif sampai disetujui.');

        return back();
    }
}
