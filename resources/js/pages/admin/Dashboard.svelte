<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import admin from '@/routes/admin';

    let {
        stats,
        services,
        styles,
        types,
        templates,
    }: {
        stats: { users: number; admins: number; projects: number; references: number; searched: number };
        services: { name: string; ok: boolean; detail: string }[];
        styles: string[];
        types: { label: string; chapters: number; custom: boolean }[];
        templates: number;
    } = $props();

    const tiles = $derived([
        { label: 'Pengguna terdaftar', value: stats.users, note: `${stats.admins} admin` },
        { label: 'Proyek dibuat', value: stats.projects, note: 'Jumlah saja — isi proyek tidak terlihat' },
        { label: 'Referensi tersimpan', value: stats.references, note: `${stats.searched} dari pencarian Crossref` },
    ]);
</script>

<AdminLayout active="ringkasan" title="Ringkasan">
    <header class="flex flex-col gap-2 border-b border-line pb-6">
        <span class="eyebrow">Admin / Ringkasan</span>
        <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Ringkasan</h1>
        <p class="max-w-2xl text-[15px] text-ink-2">Status layanan dan konfigurasi yang dipakai pengguna.</p>
    </header>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        {#each tiles as tile (tile.label)}
            <div class="card flex flex-col gap-1.5 px-6 py-5">
                <span class="text-[13px] font-medium text-ink-2">{tile.label}</span>
                <span class="font-display text-[30px] sm:text-[40px] leading-tight">{tile.value.toLocaleString('id')}</span>
                <span class="text-xs text-ink-3">{tile.note}</span>
            </div>
        {/each}
    </div>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="card flex flex-col" aria-labelledby="services">
            <h2 id="services" class="px-6 pt-5 pb-3 font-display text-2xl font-medium">Status layanan</h2>
            <ul>
                {#each services as service (service.name)}
                    <li class="flex items-center justify-between gap-4 border-t border-sunken px-6 py-3.5">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[15px] font-semibold">{service.name}</span>
                            <span class="text-[13px] text-ink-2">{service.detail}</span>
                        </div>
                        {#if service.ok}
                            <span class="badge badge-ok"><span class="size-1.5 rounded-full bg-ok"></span>Aktif</span>
                        {:else}
                            <span class="badge badge-empty">Belum dikonfigurasi</span>
                        {/if}
                    </li>
                {/each}
            </ul>
            <p class="border-t border-sunken px-6 py-3.5 text-[13px] text-ink-3">
                Kunci layanan hanya disimpan di <code class="font-mono text-xs">.env</code> server dan tidak pernah dikirim ke browser.
            </p>
        </section>

        <div class="flex flex-col gap-4">
            <section class="card flex flex-col gap-3 px-6 py-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="section-label">Gaya sitasi aktif</h2>
                    <Link href={admin.citationStyles.index().url} class="text-[13px] font-semibold text-primary">Atur</Link>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    {#each styles as style (style)}<span class="badge">{style}</span>{/each}
                </div>
            </section>
            <section class="card flex flex-col gap-3 px-6 py-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="section-label">Jenis tulisan</h2>
                    <Link href={admin.documentTypes.index().url} class="text-[13px] font-semibold text-primary">Atur</Link>
                </div>
                <ul class="flex flex-col gap-2 text-sm">
                    {#each types as type (type.label)}
                        <li class="flex items-center justify-between gap-3">
                            <span>{type.label} <span class="text-ink-3">· {type.chapters} bab</span></span>
                            <span class="badge {type.custom ? 'badge-ok' : 'badge-empty'}">{type.custom ? 'Diatur admin' : 'Contoh bawaan'}</span>
                        </li>
                    {/each}
                </ul>
            </section>
            <section class="card flex items-center justify-between gap-3 px-6 py-5">
                <div class="flex flex-col gap-0.5">
                    <h2 class="section-label">Template Word</h2>
                    <span class="text-sm">{templates ? `${templates} template institusi` : 'Belum ada template — ekspor memakai format bawaan'}</span>
                </div>
                <Link href={admin.templates.index().url} class="btn btn-secondary">Kelola</Link>
            </section>
            <p class="flex items-start gap-2 px-1 text-[13px] leading-normal text-ink-3">
                <Icon name="shield" size={16} class="mt-0.5" /> Admin tidak dapat membuka isi proyek, referensi, atau draf pengguna.
            </p>
        </div>
    </div>
</AdminLayout>
