<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Billing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function index(Request $request, Billing $billing): Response
    {
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('admin/Billing', [
            'payment' => $billing->payment(),
            'plans' => DB::table('billing_plans')->orderBy('price')->get(),
            'requests' => DB::table('billing_requests')->join('users', 'users.id', '=', 'billing_requests.user_id')
                ->where('status', 'pending')->select('billing_requests.*', 'users.email')->orderBy('billing_requests.id')->get(),
            'users' => User::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->orderBy('name')->paginate(20)->withQueryString()->through(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'account' => $billing->account($user)]),
            'search' => $search,
            'history' => DB::table('credit_transactions')->leftJoin('users as target', 'target.id', '=', 'credit_transactions.user_id')
                ->leftJoin('users as actor', 'actor.id', '=', 'credit_transactions.admin_id')->whereNotNull('credit_transactions.admin_id')
                ->select('credit_transactions.*', 'target.email as email', 'actor.email as admin_email')->latest('credit_transactions.id')->limit(50)->get(),
        ]);
    }

    public function payment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp' => ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'bank' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9 -]+$/'],
            'account_holder' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ], ['whatsapp.regex' => 'Gunakan kode negara tanpa +, spasi, atau angka 0 di depan (contoh: 6281234567890).']);
        DB::table('billing_payment_settings')->updateOrInsert(['id' => 1], [...$data, 'created_at' => now(), 'updated_at' => now()]);
        Inertia::flash('success', 'Kontak admin dan instruksi pembayaran diperbarui.');

        return back();
    }

    public function plan(Request $request, int $plan): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'price' => ['required', 'integer', 'min:1000', 'max:10000000'], 'credits' => ['required', 'integer', 'min:1', 'max:1000000'], 'active' => ['required', 'boolean']]);
        abort_unless(DB::table('billing_plans')->where('id', $plan)->exists(), 404);
        $admin = $request->user();
        abort_if($admin === null, 403);
        DB::transaction(function () use ($plan, $data, $admin): void {
            $before = DB::table('billing_plans')->where('id', $plan)->lockForUpdate()->first();
            DB::table('billing_plans')->where('id', $plan)->update($data + ['updated_at' => now()]);
            DB::table('credit_transactions')->insert(['user_id' => $admin->id, 'admin_id' => $admin->id, 'kind' => 'plan', 'status' => 'posted', 'description' => 'Katalog paket diperbarui', 'details' => json_encode(['plan_id' => $plan, 'before' => $before, 'after' => $data], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        });
        Inertia::flash('success', 'Paket diperbarui. Permintaan dan periode yang sudah diberikan tidak berubah.');

        return back();
    }

    public function decide(Request $request, int $purchase, Billing $billing): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['required', 'string', 'max:1000']]);
        $admin = $request->user();
        abort_if($admin === null, 403);
        $billing->approve($purchase, $admin, $data['note'], (bool) $data['approve']);
        Inertia::flash('success', 'Keputusan permintaan disimpan.');

        return back();
    }

    public function user(Request $request, User $user, Billing $billing): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:activate,expiry,unlimited,credits'], 'note' => ['required', 'string', 'max:1000'],
            'plan_id' => ['required_if:action,activate', 'nullable', 'integer', 'exists:billing_plans,id'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:2100-01-01'],
            'enabled' => ['required_if:action,unlimited', 'boolean'],
            'amount' => ['required_if:action,credits', 'integer', 'between:-1000000,1000000', 'not_in:0'],
        ]);
        $admin = $request->user();
        abort_if($admin === null, 403);
        if ($data['action'] === 'activate') {
            $id = $billing->purchase($user, (int) $data['plan_id']);
            $billing->approve($id, $admin, $data['note'], true);
        } else {
            $billing->change($user, $admin, $data['action'], $data);
        }
        Inertia::flash('success', 'Akses pengguna diperbarui.');

        return back();
    }
}
