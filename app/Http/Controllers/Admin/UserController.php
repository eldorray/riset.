<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun login manual dibuat di sini. Admin melihat data akun dan jumlah proyek, bukan isinya.
 */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->withCount('projects')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'google' => $user->google_id !== null,
                'password' => $user->password !== null,
                'projects' => $user->projects_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User(['name' => $request->validated('name'), 'email' => $request->validated('email')]);
        $user->password = $request->validated('password');
        $user->role = $request->validated('role');
        $user->save();
        Inertia::flash('success', "Akun {$user->email} dibuat.");

        return back();
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->name = $request->validated('name');
        $user->role = $request->validated('role');

        if (filled($request->validated('password'))) {
            $user->password = $request->validated('password');
        }

        $user->save();
        Inertia::flash('success', 'Akun diperbarui.');

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Anda tidak bisa menghapus akun Anda sendiri.');

        $deleted = DB::transaction(function () use ($user): bool {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            if (DB::table('writing_runs')->where('user_id', $target->id)->whereIn('status', ['queued', 'running'])->exists()
                || DB::table('credit_transactions')->where('user_id', $target->id)->where('status', 'reserved')->exists()) {
                return false;
            }

            // Remove grants first because they also reference billing requests.
            DB::table('credit_grants')->where('user_id', $target->id)->delete();
            DB::table('sessions')->where('user_id', $target->id)->delete();
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $target->delete();

            return true;
        });
        Inertia::flash($deleted ? 'success' : 'error', $deleted
            ? 'Akun pengguna dan seluruh data terkait telah dihapus.'
            : 'Pengguna masih memiliki proses AI yang berjalan atau menunggu. Selesaikan atau hentikan proses tersebut sebelum menghapus akun.');

        return back();
    }
}
