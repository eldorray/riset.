<?php

declare(strict_types=1);

namespace App\Billing;

use App\Ai\AiException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Billing
{
    /** @var list<int> */
    private array $pending = [];

    public ?User $actor = null;

    public ?int $writingRunId = null;

    public function refundRun(int $runId): void
    {
        foreach (DB::table('credit_transactions')->where('writing_run_id', $runId)->where('status', 'reserved')->pluck('id') as $id) {
            $this->finish((int) $id, false);
        }
    }

    /** @return array{whatsapp: string, bank: string, account_number: string, account_holder: string, instructions: string} */
    public function payment(): array
    {
        $row = DB::table('billing_payment_settings')->find(1);

        return [
            'whatsapp' => $row->whatsapp ?? '', 'bank' => $row->bank ?? '',
            'account_number' => $row->account_number ?? '', 'account_holder' => $row->account_holder ?? '',
            'instructions' => $row->instructions ?? '',
        ];
    }

    public static function credits(int $input, int $output): int
    {
        return (int) ceil($input / 2000 + $output / 250);
    }

    public function unlimited(User $user): bool
    {
        return $user->unlimited && ($user->unlimited_until === null || $user->unlimited_until->isFuture());
    }

    public function active(User $user): bool
    {
        return $this->unlimited($user) || ($user->subscription_until?->isFuture() ?? false);
    }

    public function balance(User $user): int
    {
        return (int) DB::table('credit_grants')->where('user_id', $user->id)->where('starts_at', '<=', now())->where('expires_at', '>', now())->sum('remaining');
    }

    /** @return array<string, mixed> */
    public function account(User $user): array
    {
        return [
            'active' => $this->active($user), 'unlimited' => $this->unlimited($user),
            'unlimited_enabled' => $user->unlimited,
            'unlimited_until' => $user->unlimited_until?->toIso8601String(),
            'subscription_until' => $user->subscription_until?->toIso8601String(),
            'balance' => $this->balance($user),
            'grants' => DB::table('credit_grants')->where('user_id', $user->id)->where('expires_at', '>', now())->orderBy('starts_at')->get()->all(),
        ];
    }

    public function purchase(User $user, ?int $planId): int
    {
        return DB::transaction(function () use ($user, $planId): int {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $plan = $planId === null ? null : DB::table('billing_plans')->where('id', $planId)->where('active', true)->first();
            if ($planId !== null && $plan === null) {
                throw ValidationException::withMessages(['plan_id' => 'Paket tidak tersedia.']);
            }
            if (DB::table('billing_requests')->where('user_id', $user->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['plan_id' => 'Permintaan sebelumnya masih menunggu admin.']);
            }

            return DB::table('billing_requests')->insertGetId([
                'user_id' => $user->id, 'plan_id' => $planId, 'kind' => $plan ? 'subscription' : 'topup',
                'name' => $plan->name ?? 'Tambahan 300 kredit', 'price' => $plan->price ?? 15000,
                'credits' => $plan->credits ?? 300, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function approve(int $requestId, User $admin, string $note, bool $approve): void
    {
        $purchase = DB::table('billing_requests')->where('id', $requestId)->first();
        abort_if($purchase === null, 404);
        DB::transaction(function () use ($purchase, $admin, $note, $approve): void {
            $user = User::query()->whereKey($purchase->user_id)->lockForUpdate()->firstOrFail();
            $request = DB::table('billing_requests')->where('id', $purchase->id)->lockForUpdate()->first();
            if ($request === null || $request->status !== 'pending') {
                return;
            }
            DB::table('billing_requests')->where('id', $request->id)->update(['status' => $approve ? 'approved' : 'rejected', 'admin_id' => $admin->id, 'note' => $note, 'updated_at' => now()]);
            if (! $approve) {
                $this->audit($user, $admin, 'rejected', 0, $note, ['request_id' => $request->id]);

                return;
            }
            $start = $request->kind === 'subscription' && ($user->subscription_until?->isFuture() ?? false)
                ? CarbonImmutable::instance($user->subscription_until) : CarbonImmutable::now();
            $end = $request->kind === 'subscription' ? $start->addMonthNoOverflow() : $start->addDays(90);
            if ($request->kind === 'subscription') {
                $user->subscription_until = $end;
                $user->save();
            }
            DB::table('credit_grants')->insert([
                'user_id' => $user->id, 'request_id' => $request->id, 'kind' => $request->kind,
                'name' => $request->name, 'credits' => $request->credits, 'remaining' => $request->credits,
                'starts_at' => $start, 'expires_at' => $end, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($user, $admin, $request->kind, $request->credits, $note, ['request_id' => $request->id, 'starts_at' => $start->toIso8601String(), 'expires_at' => $end->toIso8601String()]);
        });
    }

    /** @param array<string, mixed> $data */
    public function change(User $target, User $admin, string $action, array $data): void
    {
        DB::transaction(function () use ($target, $admin, $action, $data): void {
            $user = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $before = $user->only(['subscription_until', 'unlimited', 'unlimited_until']);
            $amount = 0;
            if ($action === 'expiry') {
                $end = filled($data['until'] ?? null) ? CarbonImmutable::parse($data['until'], 'Asia/Jakarta')->endOfDay()->utc() : null;
                // Mengubah masa aktif tidak mengisi ulang saldo. Sesuaikan akhir periode terakhir saja.
                $last = DB::table('credit_grants')->where('user_id', $user->id)->where('kind', 'subscription')->orderByDesc('expires_at')->first();
                if ($last && $end && $end->greaterThan(CarbonImmutable::parse($last->starts_at))) {
                    DB::table('credit_grants')->where('id', $last->id)->update(['expires_at' => $end, 'updated_at' => now()]);
                }
                $user->subscription_until = $end;
                $user->save();
            } elseif ($action === 'unlimited') {
                $user->unlimited = (bool) $data['enabled'];
                $user->unlimited_until = $user->unlimited && filled($data['until'] ?? null) ? CarbonImmutable::parse($data['until'], 'Asia/Jakarta')->endOfDay()->utc() : null;
                $user->save();
            } elseif ($action === 'credits') {
                $amount = (int) $data['amount'];
                if ($amount > 0) {
                    DB::table('credit_grants')->insert(['user_id' => $user->id, 'kind' => 'admin', 'name' => 'Penyesuaian admin', 'credits' => $amount, 'remaining' => $amount, 'starts_at' => now(), 'expires_at' => now()->addDays(90), 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    if ($this->balance($user) < -$amount) {
                        throw ValidationException::withMessages(['amount' => 'Saldo tidak cukup untuk pengurangan.']);
                    }
                    $this->allocate($user, -$amount);
                }
            }
            $this->audit($user, $admin, $action, $amount, $data['note'], ['before' => $before, 'after' => $user->only(['subscription_until', 'unlimited', 'unlimited_until'])]);
        });
    }

    /** @return array<int, int> */
    private function allocate(User $user, int $amount): array
    {
        $allocations = [];
        foreach (DB::table('credit_grants')->where('user_id', $user->id)->where('starts_at', '<=', now())->where('expires_at', '>', now())->where('remaining', '>', 0)->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get() as $grant) {
            $take = min($amount, $grant->remaining);
            if ($take <= 0) {
                break;
            }
            DB::table('credit_grants')->where('id', $grant->id)->decrement('remaining', $take);
            $allocations[$grant->id] = $take;
            $amount -= $take;
        }
        if ($amount > 0) {
            throw new AiException('Kredit tidak cukup. Buka Paket & Kredit untuk menambah kredit.');
        }

        return $allocations;
    }

    public function reserve(User $actor, int $maximum, string $model, string $description): int
    {
        $id = DB::transaction(function () use ($actor, $maximum, $model, $description): int {
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            foreach (DB::table('credit_transactions')->where('user_id', $user->id)->where('status', 'reserved')->where('created_at', '<', now()->subHour())->get() as $stale) {
                $this->finishRow($stale, false);
            }
            if (! $this->active($user)) {
                throw new AiException('Akses AI belum aktif atau sudah berakhir. Buka Paket & Kredit untuk mengajukan aktivasi.');
            }
            $unlimited = $this->unlimited($user);
            $allocations = $unlimited ? [] : $this->allocate($user, $maximum);

            return DB::table('credit_transactions')->insertGetId([
                'user_id' => $user->id, 'kind' => 'ai', 'status' => 'reserved',
                'writing_run_id' => $this->writingRunId, 'reserved' => $unlimited ? 0 : $maximum, 'model' => $model, 'description' => $description,
                'allocations' => json_encode($allocations, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        $this->pending[] = $id;

        return $id;
    }

    public function usage(int $id, mixed $usage): void
    {
        $row = DB::table('credit_transactions')->where('id', $id)->first();
        if ($row === null) {
            throw new AiException('Reservasi kredit tidak ditemukan.');
        }
        if (! is_array($usage) || ! is_int($usage['prompt_tokens'] ?? null) || ! is_int($usage['completion_tokens'] ?? null) || $usage['prompt_tokens'] < 0 || $usage['completion_tokens'] < 0) {
            if ($row->reserved > 0) {
                throw new AiException('Provider AI tidak melaporkan usage token yang valid. Kredit dikembalikan.');
            }
            $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        }
        $actual = $row->reserved > 0 ? self::credits($usage['prompt_tokens'], $usage['completion_tokens']) : 0;
        if ($actual > $row->reserved) {
            throw new AiException('Pemakaian AI melebihi reservasi. Kredit dikembalikan.');
        }
        DB::table('credit_transactions')->where('id', $id)->update(['credits' => -$actual, 'input_tokens' => $usage['prompt_tokens'], 'output_tokens' => $usage['completion_tokens'], 'updated_at' => now()]);
    }

    public function mark(): int
    {
        return count($this->pending);
    }

    public function refundTo(int $mark): void
    {
        while (count($this->pending) > $mark) {
            $this->discardLast();
        }
    }

    public function discardLast(): void
    {
        $id = array_pop($this->pending);
        if ($id !== null) {
            $this->finish($id, false);
        }
    }

    public function checkpoint(): void
    {
        $this->complete(true);
    }

    public function complete(bool $success): void
    {
        foreach ($this->pending as $id) {
            $this->finish($id, $success);
        }
        $this->pending = [];
    }

    private function finish(int $id, bool $success): void
    {
        DB::transaction(function () use ($id, $success): void {
            $row = DB::table('credit_transactions')->where('id', $id)->first();
            if ($row === null) {
                return;
            }
            User::query()->whereKey($row->user_id)->lockForUpdate()->firstOrFail();
            $row = DB::table('credit_transactions')->where('id', $id)->lockForUpdate()->first();
            if ($row !== null) {
                $this->finishRow($row, $success);
            }
        });
    }

    private function finishRow(\stdClass $row, bool $success): void
    {
        if ($row->status !== 'reserved') {
            return;
        }
        $keep = $success ? -$row->credits : 0;
        foreach (json_decode($row->allocations ?? '{}', true, flags: JSON_THROW_ON_ERROR) as $grantId => $amount) {
            $charged = min($keep, $amount);
            $keep -= $charged;
            if ($amount > $charged) {
                DB::table('credit_grants')->where('id', $grantId)->increment('remaining', $amount - $charged);
            }
        }
        DB::table('credit_transactions')->where('id', $row->id)->update(['status' => $success ? 'charged' : 'refunded', 'credits' => $success ? $row->credits : 0, 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $details */
    private function audit(User $user, User $admin, string $kind, int $amount, string $note, array $details): void
    {
        DB::table('credit_transactions')->insert(['user_id' => $user->id, 'admin_id' => $admin->id, 'kind' => $kind, 'status' => 'posted', 'credits' => $amount, 'description' => $note, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }
}
