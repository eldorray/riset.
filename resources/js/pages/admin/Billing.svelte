<script lang="ts">
    import { untrack } from 'svelte';
    import { Link, router, useForm } from '@inertiajs/svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    type Plan = { id: number; name: string; price: number; credits: number; active: boolean | number };
    type Account = { active: boolean; unlimited: boolean; unlimited_enabled: boolean; balance: number; unlimited_until: string | null; subscription_until: string | null };
    type User = { id: number; name: string; email: string; account: Account };
    type Payment = { whatsapp: string; bank: string; account_number: string; account_holder: string; instructions: string };
    let { plans, requests, users, search, history, payment }: {
        payment: Payment;
        plans: Plan[]; requests: { id: number; email: string; name: string; price: number; credits: number; kind: string }[];
        users: { data: User[]; links: { url: string | null; label: string; active: boolean }[] };
        search: string;
        history: { id: number; email: string; admin_email: string; kind: string; credits: number; description: string; created_at: string; details: string | null }[];
    } = $props();
    const money = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    const date = (value: string | null) => value ? new Date(value.endsWith('Z') || value.includes('+') ? value : value.replace(' ', 'T') + 'Z').toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short' }) : '—';
    const inputDate = (value: string | null) => value ? new Date(value).toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' }) : '';
    const paymentForm = useForm(untrack(() => ({ ...payment })));
    const planForm = useForm({ name: '', price: 19000, credits: 600, active: true });
    const accessForm = useForm({ action: 'activate', plan_id: '' as number | '', until: '', enabled: false, amount: 100, note: '' });
    const decision = useForm({ approve: true, note: '' });
    let selectedPlan = $state<number | null>(null);
    let selectedUser = $state<User | null>(null);
    let purchase = $state<number | null>(null);
    let query = $state('');
    $effect(() => { query = search; });
    function editPlan(plan: Plan) {
        selectedPlan = plan.id;
        Object.assign(planForm, { name: plan.name, price: plan.price, credits: plan.credits, active: !!plan.active });
        planForm.clearErrors();
    }
    function editUser(user: User) {
        selectedUser = user;
        accessForm.reset();
        accessForm.clearErrors();
        accessForm.plan_id = plans.find((plan) => plan.active)?.id ?? '';
        accessForm.enabled = user.account.unlimited_enabled;
        accessForm.until = inputDate(user.account.subscription_until);
    }
    function changeAction() {
        accessForm.until = inputDate(accessForm.action === 'unlimited' ? selectedUser?.account.unlimited_until ?? null : selectedUser?.account.subscription_until ?? null);
    }
</script>

<AdminLayout active="billing" title="Subscription & Kredit">
    <header class="flex flex-col gap-2"><h1 class="font-display text-4xl font-medium">Subscription & Kredit</h1><p class="text-ink-2">Aktivasi manual setelah pembayaran diverifikasi. Unlimited tidak memberi hak admin. Waktu dalam WIB.</p></header>
    <form class="card flex flex-col gap-4 p-5" onsubmit={(event) => { event.preventDefault(); paymentForm.put('/admin/billing/payment', { preserveScroll: true }); }} aria-labelledby="payment-settings">
        <div><h2 id="payment-settings" class="font-display text-2xl">Kontak & pembayaran</h2><p class="help">Informasi ini ditampilkan kepada pengguna di Paket & Kredit. Gunakan kontak dan rekening untuk pembayaran aplikasi.</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="field">WhatsApp admin<input class="input" type="tel" inputmode="tel" placeholder="6281234567890" bind:value={paymentForm.whatsapp} /><span class="help">Kode negara tanpa + atau spasi. Contoh: 62 untuk Indonesia.</span></label>
            <label class="field">Nama bank<input class="input" bind:value={paymentForm.bank} placeholder="Nama bank" /></label>
            <label class="field">Nomor rekening<input class="input" inputmode="numeric" bind:value={paymentForm.account_number} /></label>
            <label class="field">Atas nama<input class="input" bind:value={paymentForm.account_holder} /></label>
        </div>
        <label class="field">Instruksi tambahan<textarea class="input" rows="3" bind:value={paymentForm.instructions} placeholder="Cara transfer dan informasi yang perlu dikirim saat konfirmasi"></textarea></label>
        {#each Object.values(paymentForm.errors) as error}<p class="error" role="alert">{error}</p>{/each}
        <button class="btn btn-primary self-start" disabled={paymentForm.processing}>{paymentForm.processing ? 'Menyimpan…' : 'Simpan instruksi pembayaran'}</button>
    </form>
    <section class="grid gap-4 md:grid-cols-3" aria-label="Katalog paket">{#each plans as plan (plan.id)}<article class="card flex flex-col gap-2 p-5"><h2 class="font-display text-2xl">{plan.name}</h2><p>{money(plan.price)} · {plan.credits} kredit / bulan</p><p class="help">{plan.active ? 'Tersedia' : 'Dinonaktifkan'}</p><button type="button" class="btn btn-secondary self-start" onclick={() => editPlan(plan)}>Ubah paket</button></article>{/each}</section>
    {#if selectedPlan}
        <form class="card flex flex-col gap-3 p-5" onsubmit={(event) => { event.preventDefault(); planForm.put(`/admin/billing/plans/${selectedPlan}`, { preserveScroll: true, onSuccess: () => selectedPlan = null }); }}>
            <h2 class="section-label">Ubah paket</h2><label class="field">Nama<input class="input" bind:value={planForm.name} required /></label><label class="field">Harga rupiah<input class="input" type="number" min="1000" bind:value={planForm.price} required /></label><label class="field">Kredit setiap periode<input class="input" type="number" min="1" bind:value={planForm.credits} required /></label><label class="flex items-center gap-2"><input type="checkbox" bind:checked={planForm.active} /> Paket tersedia</label><p class="help">Perubahan hanya untuk pengajuan baru; saldo dan pengajuan yang sudah ada tidak berubah.</p>{#each Object.values(planForm.errors) as error}<p class="error">{error}</p>{/each}<div class="flex gap-2"><button class="btn btn-primary" disabled={planForm.processing}>Simpan paket</button><button type="button" class="btn btn-ghost" onclick={() => selectedPlan = null}>Batal</button></div>
        </form>
    {/if}
    <section class="card flex flex-col gap-3 p-5"><h2 class="section-label">Pengajuan menunggu pembayaran / aktivasi</h2>
        {#each requests as request (request.id)}<div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3"><div><p class="font-semibold">#{request.id} · {request.email}</p><p class="text-sm">{request.name} · {money(request.price)} · {request.credits} kredit</p></div><button type="button" class="btn btn-secondary" onclick={() => { purchase = request.id; decision.reset(); decision.clearErrors(); }}>Periksa pengajuan</button></div>{:else}<p class="help">Tidak ada pengajuan menunggu.</p>{/each}
        {#if purchase}<form class="flex flex-col gap-3 border-t border-line pt-4" onsubmit={(event) => { event.preventDefault(); decision.post(`/admin/billing/requests/${purchase}`, { preserveScroll: true, onSuccess: () => purchase = null }); }}><h3 class="font-semibold">Keputusan pengajuan #{purchase}</h3><label class="field">Keputusan<select class="input" bind:value={decision.approve}><option value={true}>Setujui: pembayaran sudah diverifikasi</option><option value={false}>Tolak</option></select></label><label class="field">Catatan verifikasi / alasan<textarea class="input" rows="2" bind:value={decision.note} required maxlength="1000"></textarea></label>{#each Object.values(decision.errors) as error}<p class="error">{error}</p>{/each}<div class="flex gap-2"><button class="btn btn-primary" disabled={decision.processing}>Simpan keputusan</button><button type="button" class="btn btn-ghost" onclick={() => purchase = null}>Batal</button></div></form>{/if}
    </section>
    <form class="flex items-end gap-3" onsubmit={(event) => { event.preventDefault(); router.get('/admin/billing', { q: query }, { preserveState: true, replace: true }); }}><label class="field grow">Cari nama atau email<input class="input" type="search" bind:value={query} /></label><button class="btn btn-secondary">Cari</button></form>
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="flex flex-col gap-3" aria-label="Akses pengguna">{#each users.data as user (user.id)}<article class="card flex flex-wrap items-center justify-between gap-3 p-5"><div><h2 class="font-semibold">{user.name}</h2><p class="text-sm text-ink-2">{user.email}</p><p class="mt-2 text-sm">{user.account.unlimited ? 'Unlimited' : user.account.active ? 'Subscription aktif' : 'Akses AI tidak aktif'} · {user.account.balance} kredit</p><p class="help">Subscription sampai {date(user.account.subscription_until)}</p>{#if user.account.unlimited_enabled}<p class="help">Unlimited: {user.account.unlimited_until ? date(user.account.unlimited_until) : 'tanpa batas waktu'}</p>{/if}</div><button type="button" class="btn btn-secondary" onclick={() => editUser(user)}>Atur akses</button></article>{/each}
            <nav class="flex flex-wrap gap-2" aria-label="Halaman pengguna">{#each users.links as link, i (i)}{#if link.url}<Link href={link.url} preserveScroll class="btn {link.active ? 'btn-primary' : 'btn-secondary'}" aria-current={link.active ? 'page' : undefined}>{link.label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya')}</Link>{/if}{/each}</nav>
        </section>
        {#if selectedUser}<form class="card flex flex-col gap-3 p-5" onsubmit={(event) => { event.preventDefault(); accessForm.put(`/admin/billing/users/${selectedUser?.id}`, { preserveScroll: true, onSuccess: () => selectedUser = null }); }}>
            <h2 class="font-display text-2xl">Atur akses</h2><p class="text-sm">{selectedUser.email}</p><label class="field">Tindakan<select class="input" bind:value={accessForm.action} onchange={changeAction}><option value="activate">Aktivasi / perpanjang paket 1 bulan</option><option value="expiry">Ubah tanggal berakhir saja</option><option value="credits">Tambah / kurangi kredit</option><option value="unlimited">Atur unlimited</option></select></label>
            {#if accessForm.action === 'activate'}<label class="field">Paket<select class="input" bind:value={accessForm.plan_id}>{#each plans.filter((plan) => plan.active) as plan (plan.id)}<option value={plan.id}>{plan.name} · {money(plan.price)}</option>{/each}</select></label><p class="help">Aktif: periode berikutnya dijadwalkan setelah masa aktif sekarang. Kedaluwarsa: mulai sekarang. Verifikasi pembayaran sebelum menyimpan.</p>
            {:else if accessForm.action === 'credits'}<label class="field">Jumlah kredit (negatif untuk mengurangi)<input class="input" type="number" bind:value={accessForm.amount} required /></label><p class="help">Tambahan admin berlaku 90 hari. Tidak mengaktifkan subscription.</p>
            {:else}{#if accessForm.action === 'unlimited'}<label class="flex items-center gap-2"><input type="checkbox" bind:checked={accessForm.enabled} /> Aktifkan unlimited</label>{/if}<label class="field">Tanggal berakhir (WIB)<input class="input" type="date" bind:value={accessForm.until} /></label><p class="help">{accessForm.action === 'unlimited' ? 'Kosongkan tanggal untuk unlimited permanen. Menonaktifkan unlimited mengembalikan aturan subscription dan saldo sebelumnya.' : 'Kosongkan untuk menonaktifkan subscription. Mengubah tanggal tidak memberi kredit baru.'}</p>{/if}
            <label class="field">Alasan / catatan admin<textarea class="input" rows="3" bind:value={accessForm.note} required maxlength="1000"></textarea></label>{#each Object.values(accessForm.errors) as error}<p class="error" role="alert">{error}</p>{/each}<div class="flex gap-2"><button class="btn btn-primary" disabled={accessForm.processing}>Simpan akses</button><button type="button" class="btn btn-ghost" onclick={() => selectedUser = null}>Batal</button></div>
        </form>{/if}
    </div>
    <section class="card flex flex-col gap-3 p-5"><h2 class="section-label">50 perubahan akses terbaru</h2>{#each history as entry (entry.id)}<details class="border-t border-line pt-3 text-sm"><summary class="cursor-pointer">{entry.email} · {entry.kind} · {entry.credits > 0 ? '+' : ''}{entry.credits} kredit · {date(entry.created_at)}</summary><p class="mt-2 text-ink-2">Oleh {entry.admin_email}: {entry.description}</p>{#if entry.details}<pre class="mt-2 whitespace-pre-wrap break-all text-xs">{JSON.stringify(JSON.parse(entry.details), null, 2)}</pre>{/if}</details>{:else}<p class="help">Belum ada perubahan akses.</p>{/each}</section>
</AdminLayout>
