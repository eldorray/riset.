<script lang="ts">
    import { Link, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    type Plan = { id: number; name: string; price: number; credits: number };
    type Grant = { id: number; name: string; remaining: number; starts_at: string; expires_at: string };
    type Purchase = { id: number; name: string; price: number; status: string; note: string | null };
    type Entry = { id: number; kind: string; status: string; credits: number; reserved: number; input_tokens: number | null; output_tokens: number | null; description: string; created_at: string };
    let { account, plans, requests, history, payment }: {
        payment: { whatsapp: string; bank: string; account_number: string; account_holder: string; instructions: string };
        account: { active: boolean; unlimited: boolean; balance: number; unlimited_until: string | null; subscription_until: string | null; grants: Grant[] };
        plans: Plan[]; requests: Purchase[]; history: { data: Entry[]; links: { url: string | null; label: string; active: boolean }[] };
    } = $props();
    const form = useForm({ kind: 'subscription', plan_id: null as number | null });
    const latestPending = $derived(requests.find((request) => request.status === 'pending'));
    const pending = $derived(requests.some((request) => request.status === 'pending'));
    const money = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    const whatsappUrl = $derived(payment.whatsapp ? `https://wa.me/${payment.whatsapp}?text=${encodeURIComponent(latestPending ? `Halo Admin, saya ingin konfirmasi pembayaran permintaan #${latestPending.id} untuk ${latestPending.name}, sejumlah ${money(latestPending.price)}.` : 'Halo Admin, saya ingin informasi pembayaran Riset.')}` : '');
    const date = (value: string | null) => value ? new Date(value.endsWith('Z') || value.includes('+') ? value : value.replace(' ', 'T') + 'Z').toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short' }) : '—';
    const labels: Record<string, string> = { pending: 'Menunggu admin', approved: 'Disetujui', rejected: 'Ditolak', charged: 'Terpakai', refunded: 'Dikembalikan', reserved: 'Direservasi', posted: 'Penyesuaian', subscription: 'Subscription', topup: 'Tambahan kredit', ai: 'AI', expiry: 'Masa aktif', unlimited: 'Unlimited', credits: 'Kredit admin' };
    function request(planId: number | null) {
        form.kind = planId === null ? 'topup' : 'subscription';
        form.plan_id = planId;
        form.post('/account/subscription', { preserveScroll: true });
    }
</script>

<AppHead title="Paket & Kredit" />
<div class="min-h-dvh bg-paper px-4 py-8 text-ink sm:px-8">
    <main class="mx-auto flex max-w-5xl flex-col gap-6">
        <Link href="/projects" class="btn btn-ghost self-start">← Proyek saya</Link>
        <header class="flex flex-col gap-2"><h1 class="font-display text-4xl font-medium">Paket & Kredit</h1><p class="text-ink-2">Aktivasi dan pembayaran diperiksa manual oleh admin. Semua waktu ditampilkan dalam WIB.</p></header>
        <section class="card grid gap-5 p-6 sm:grid-cols-3" aria-label="Akses saat ini">
            <div><p class="section-label">Akses AI</p><p class="mt-2 text-xl font-semibold">{account.unlimited ? 'Unlimited' : account.active ? 'Aktif' : 'Belum aktif / berakhir'}</p></div>
            <div><p class="section-label">Kredit tersedia</p><p class="mt-2 text-xl font-semibold">{account.unlimited ? 'Tidak dipotong' : account.balance.toLocaleString('id-ID')}</p></div>
            <div><p class="section-label">Masa aktif</p><p class="mt-2 text-sm">{account.unlimited ? account.unlimited_until ? date(account.unlimited_until) : 'Unlimited tanpa batas waktu' : date(account.subscription_until)}</p></div>
        </section>
        <p class="help">Menulis manual, melihat naskah, dan ekspor Word tetap tersedia tanpa subscription. Kredit hanya untuk AI. Unlimited tidak mengubah saldo yang sudah ada.</p>
        {#if account.grants.length}
            <section class="card flex flex-col gap-3 p-5"><h2 class="section-label">Periode dan kredit Anda</h2>
                {#each account.grants as grant (grant.id)}<div class="flex flex-wrap justify-between gap-2 border-t border-line pt-3 text-sm"><span class="font-semibold">{grant.name} · {grant.remaining} kredit tersisa</span><span class="text-ink-2">{date(grant.starts_at)} sampai {date(grant.expires_at)}</span></div>{/each}
            </section>
        {/if}
        <section class="grid gap-4 md:grid-cols-3" aria-label="Pilihan paket">
            {#each plans as plan (plan.id)}
                <article class="card flex flex-col gap-3 p-6"><h2 class="font-display text-2xl">{plan.name}</h2><p class="text-2xl font-semibold">{money(plan.price)}<span class="text-sm font-normal text-ink-2"> / bulan</span></p><p>{plan.credits.toLocaleString('id-ID')} kredit setiap periode</p><p class="help">Satu bulan kalender. Kredit periode habis saat periode berakhir. Perpanjangan lebih awal dijadwalkan untuk periode berikutnya.</p><button type="button" class="btn btn-primary mt-auto" disabled={form.processing || pending} onclick={() => request(plan.id)}>Ajukan {account.active ? 'perpanjangan' : 'aktivasi'}</button></article>
            {/each}
        </section>
        <section class="card flex flex-wrap items-center justify-between gap-4 p-5"><div><h2 class="font-semibold">Tambahan 300 kredit · Rp15.000</h2><p class="help">Berlaku 90 hari sejak disetujui. Pemakaian AI tetap memerlukan subscription aktif.</p></div><button type="button" class="btn btn-secondary" disabled={form.processing || pending} onclick={() => request(null)}>Ajukan tambahan kredit</button></section>
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="payment-title">
            <h2 id="payment-title" class="font-display text-2xl">Cara pembayaran</h2>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-ink-2"><li>Pilih paket dan ajukan aktivasi atau perpanjangan.</li><li>Transfer sesuai jumlah pada permintaan Anda ke rekening di bawah.</li><li>Kirim bukti pembayaran dan nomor permintaan melalui WhatsApp admin.</li><li>Admin memverifikasi pembayaran. Status persetujuan dan masa aktif tampil di halaman ini.</li></ol>
            {#if payment.whatsapp}
                <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 rounded-lg bg-paper p-4 text-sm"><dt>Bank</dt><dd class="font-semibold">{payment.bank}</dd><dt>Rekening</dt><dd class="break-all font-mono">{payment.account_number}</dd><dt>Atas nama</dt><dd>{payment.account_holder}</dd></dl>
                {#if payment.instructions}<p class="whitespace-pre-wrap text-sm text-ink-2">{payment.instructions}</p>{/if}
                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" class="btn btn-primary self-start">{pending ? 'Konfirmasi pembayaran via WhatsApp' : 'Hubungi admin via WhatsApp'}</a>
            {:else}<p class="alert alert-info">Informasi rekening dan WhatsApp belum tersedia. Tunggu admin melengkapi instruksi sebelum melakukan pembayaran.</p>{/if}
            {#if latestPending}<p class="text-sm font-semibold" role="status">Permintaan #{latestPending.id} · {latestPending.name} · {money(latestPending.price)} · Menunggu verifikasi admin</p>{/if}
        </section>
        {#if pending}<p role="status" class="alert alert-info">Permintaan Anda menunggu admin. Gunakan instruksi pembayaran di atas; pengajuan belum mengaktifkan akses.</p>{/if}
        {#each Object.values(form.errors) as error}<p role="alert" class="error">{error}</p>{/each}
        <p class="help">Pemakaian: pembulatan ke atas dari input token ÷ 2.000 + output token ÷ 250, termasuk reasoning. Sebelum AI berjalan, sebagian kredit direservasi untuk batas maksimum lalu selisihnya dikembalikan. Kegagalan tidak ditagihkan. Membaca dan menyimpan catatan artikel adalah proses tersendiri: catatan yang berhasil disimpan tetap dihitung jika draf berikutnya gagal.</p>
        <section class="card flex flex-col gap-3 p-5"><h2 class="section-label">Permintaan Anda</h2>{#each requests as purchase (purchase.id)}<div class="border-t border-line pt-3 text-sm"><p class="font-semibold">#{purchase.id} · {purchase.name} · {money(purchase.price)} · {labels[purchase.status] ?? purchase.status}</p>{#if purchase.note}<p class="text-ink-2">{purchase.note}</p>{/if}</div>{:else}<p class="help">Belum ada permintaan.</p>{/each}</section>
        <section class="card flex flex-col gap-3 p-5"><h2 class="section-label">Riwayat kredit</h2>
            {#each history.data as entry (entry.id)}<div class="flex flex-wrap justify-between gap-3 border-t border-line pt-3 text-sm"><div><p class="font-semibold">{labels[entry.kind] ?? entry.kind} · {labels[entry.status] ?? entry.status}</p><p class="text-ink-2">{entry.description}</p><p class="text-xs text-ink-3">{date(entry.created_at)}{#if entry.input_tokens !== null} · {entry.input_tokens} input / {entry.output_tokens ?? 0} output token{/if}</p></div><span class="font-mono">{entry.status === 'reserved' ? `${entry.reserved} direservasi` : `${entry.credits > 0 ? '+' : ''}${entry.credits} kredit`}</span></div>{:else}<p class="help">Belum ada pemakaian.</p>{/each}
            <nav aria-label="Halaman riwayat" class="flex flex-wrap gap-2">{#each history.links as link, i (i)}{#if link.url}<Link href={link.url} preserveScroll class="btn btn-secondary" aria-current={link.active ? 'page' : undefined}>{link.label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya')}</Link>{/if}{/each}</nav>
        </section>
    </main>
</div>
<FlashMessage />
