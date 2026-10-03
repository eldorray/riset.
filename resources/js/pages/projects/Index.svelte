<script lang="ts">
    import AppLogo from '@/components/AppLogo.svelte';
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    import TitleBrainstorm from '@/components/TitleBrainstorm.svelte';
    import Icon from '@/components/Icon.svelte';
    import { relativeTime } from '@/lib/format';
    import { logout } from '@/routes';
    import admin from '@/routes/admin';
    import projects from '@/routes/projects';
    import type { Option, ProjectSummary } from '@/types';

    let {
        projects: list,
        documentTypes,
        archived,
    }: { projects: ProjectSummary[]; documentTypes: Option[]; archived: boolean } = $props();

    const user = $derived(page.props.auth.user);
    const form = useForm({ title: '', document_type: '' });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.submit(projects.store(), { onSuccess: () => form.reset() });
    }
</script>

<AppHead title="Proyek saya" />

<div class="flex min-h-dvh flex-col bg-paper text-ink">
    <header class="flex min-h-18 flex-wrap items-center justify-between gap-3 py-3 border-b border-line px-6 lg:px-16">
        <span class="font-display text-[28px] leading-none font-medium tracking-tight"><AppLogo /></span>
        {#if user}
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                {#if user.is_admin}
                    <Link href={admin.dashboard().url} class="btn btn-ghost"><Icon name="shield" size={16} /> Panel admin</Link>
                {/if}
                <Link href="/account/subscription" class="btn btn-ghost">Paket & Kredit · {user?.unlimited ? 'Unlimited' : `${user?.credits ?? 0} kredit`}</Link>
                <Link href="/account/password" class="btn btn-ghost">Ubah password</Link>
                <span class="hidden text-sm font-medium sm:inline">{user.name}</span>
                <button type="button" class="btn btn-secondary" onclick={() => router.post(logout().url)}>Keluar</button>
            </div>
        {/if}
    </header>

    <main class="grid grow grid-cols-1 items-start gap-8 px-4 py-6 sm:px-6 sm:py-12 lg:grid-cols-[minmax(0,1fr)_420px] lg:px-16">
        <div class="flex flex-col gap-6">
            <div class="flex items-end justify-between gap-6 border-b border-line pb-5">
                <div class="flex flex-col gap-2">
                    <h1 class="font-display text-[32px] sm:text-[44px] leading-tight font-medium">{archived ? 'Arsip proyek' : 'Proyek saya'}</h1>
                    <Link href={archived ? '/projects' : '/projects?archived=1'} class="text-sm text-primary underline">{archived ? 'Lihat proyek aktif' : 'Lihat arsip'}</Link>
                    <p class="text-[15px] text-ink-2">Buka proyek untuk melanjutkan referensi, kerangka, atau draf.</p>
                </div>
                {#if list.length}
                    <span class="font-mono text-[13px] text-ink-2">{list.length} proyek</span>
                {/if}
            </div>

            {#if list.length}
                <ul class="flex flex-col gap-3.5">
                    {#each list as project (project.id)}
                        <li>
                            <Link
                                href={projects.show(project.id).url}
                                class="card flex flex-col gap-3 px-6 py-5 hover:border-line-strong"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <span class="badge">{project.document_type.label}</span>
                                    <span class="text-[13px] text-ink-3">Diperbarui {relativeTime(project.updated_at)}</span>
                                </div>
                                <span class="font-display text-2xl leading-snug font-medium">{project.title}</span>
                                <div class="flex flex-wrap gap-5 text-[13px] text-ink-2">
                                    <span>{project.references_count ? `${project.references_count} referensi` : 'Belum ada referensi'}</span>
                                    <span>{project.chapters ? `Kerangka tersimpan · ${project.chapters} bab` : 'Kerangka belum dibuat'}</span>
                                    <span>{project.units ? `Draf ${project.filled} dari ${project.units} bagian` : 'Draf belum dimulai'}</span>
                                </div>
                            </Link>
                            <button type="button" class="btn btn-ghost mt-1" onclick={() => router.patch(`/projects/${project.id}/archive`, { archived: !archived }, { preserveScroll: true })}>{archived ? 'Pulihkan proyek' : 'Arsipkan proyek'}</button>
                        </li>
                    {/each}
                </ul>
            {:else}
                <div class="flex flex-col items-start gap-3 rounded-[10px] border border-dashed border-line-strong px-10 py-12">
                    <Icon name="book" size={28} class="text-ink-3" />
                    <h2 class="font-display text-[26px] font-medium">{archived ? 'Arsip kosong' : 'Belum ada proyek'}</h2>
                    <p class="max-w-md text-[15px] leading-relaxed text-ink-2">
                        Mulai dengan judul dan jenis tulisan di formulir samping. Judul dapat diubah kapan saja.
                    </p>
                </div>
            {/if}
        </div>

        <div class="flex flex-col gap-5">
        <form class="card flex flex-col gap-5 p-7" onsubmit={submit} aria-labelledby="new-project" novalidate>
            <div class="flex flex-col gap-1.5">
                <h2 id="new-project" class="font-display text-[26px] font-medium">Proyek baru</h2>
                <p class="text-sm text-ink-2">Semua isian wajib diisi.</p>
            </div>

            <div class="field">
                <label for="title" class="label">Judul</label>
                <input
                    id="title"
                    class="input"
                    bind:value={form.title}
                    placeholder="Mis. Pengaruh … terhadap …"
                    aria-invalid={form.errors.title ? 'true' : undefined}
                    aria-describedby={form.errors.title ? 'title-error' : undefined}
                />
                {#if form.errors.title}<span id="title-error" class="error">{form.errors.title}</span>{/if}
            </div>

            <fieldset class="flex flex-col gap-2.5" aria-describedby="type-help">
                <legend class="label mb-2">Jenis tulisan</legend>
                <div class="grid grid-cols-2 gap-2.5">
                    {#each documentTypes as type (type.value)}
                        <label
                            class="flex min-h-13 items-center gap-2.5 rounded-lg border px-3.5 text-[15px] font-medium {form.document_type === type.value
                                ? 'border-primary bg-primary-soft shadow-[0_0_0_1px_var(--color-primary)]'
                                : 'border-line-strong'}"
                        >
                            <input type="radio" name="document_type" value={type.value} bind:group={form.document_type} class="size-4.5 accent-primary" />
                            {type.label}
                        </label>
                    {/each}
                </div>
                {#if form.errors.document_type}<span class="error">{form.errors.document_type}</span>{/if}
                <span id="type-help" class="help">
                    Menentukan struktur kerangka yang disiapkan AI. Jenis tulisan tidak bisa diganti setelah proyek dibuat.
                </span>
            </fieldset>

            <button type="submit" class="btn btn-primary h-12 text-[15px]" disabled={form.processing}>
                {#if form.processing}<Icon name="spinner" size={16} /> Menyimpan…{:else}Buat proyek{/if}
            </button>
        </form>
        <TitleBrainstorm documentType={form.document_type} onselect={(title) => {
            form.title = title;
            form.clearErrors('title');
            document.getElementById('title')?.focus();
        }} />
        </div>
    </main>
</div>

<FlashMessage />
