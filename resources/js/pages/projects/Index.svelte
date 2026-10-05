<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    import TitleBrainstorm from '@/components/TitleBrainstorm.svelte';
    import Icon from '@/components/Icon.svelte';
    import AppHeader from '@/components/AppHeader.svelte';
    import { relativeTime } from '@/lib/format';
    import projects from '@/routes/projects';
    import type { Option, ProjectSummary } from '@/types';

    let {
        projects: list,
        documentTypes,
        archived,
    }: { projects: ProjectSummary[]; documentTypes: Option[]; archived: boolean } = $props();

    const form = useForm({ title: '', document_type: '', idea: '' });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.submit(projects.store(), { onSuccess: () => form.reset() });
    }
</script>

<AppHead title="Proyek saya" />

<div class="flex min-h-dvh flex-col bg-paper text-ink">
    <AppHeader />

    <main class="grid grow grid-cols-1 items-start gap-8 px-4 py-6 sm:px-6 sm:py-12 lg:grid-cols-[minmax(0,1fr)_420px] lg:px-16">
        <div class="flex flex-col gap-6">
            <div class="flex items-end justify-between gap-6 border-b border-line pb-5">
                <div class="flex flex-col gap-2">
                    <h1 class="font-display text-[32px] sm:text-[44px] leading-tight font-medium">{archived ? 'Arsip proyek' : 'Proyek saya'}</h1>
                    <Link href={archived ? '/projects' : '/projects?archived=1'} class="text-sm text-primary underline">{archived ? 'Lihat proyek aktif' : 'Lihat arsip'}</Link>
                    <p class="text-[15px] text-ink-2">Buka proyek untuk melanjutkan referensi, kerangka, atau draf.</p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    {#if list.length}<span class="font-mono text-[13px] text-ink-2">{list.length} proyek</span>{/if}
                    {#if !archived}<a href="#proyek-baru" class="btn btn-primary whitespace-nowrap lg:hidden"><Icon name="plus" size={16} /> Proyek baru</a>{/if}
                </div>
            </div>

            {#if list.length}
                <ul class="flex flex-col gap-3.5">
                    {#each list as project (project.id)}
                        <li class="card card-press relative flex flex-col gap-3 px-6 pt-5 pb-2 has-[a:hover]:border-line-strong">
                            <div class="flex items-center justify-between gap-4">
                                <span class="badge">{project.document_type.label}</span>
                                <span class="text-[13px] text-ink-3">Diperbarui {relativeTime(project.updated_at)}</span>
                            </div>
                            <!-- Tautan judul menutupi seluruh kartu; tombol arsip di atasnya (z-10) agar tidak ada elemen interaktif bersarang. -->
                            <Link href={projects.show(project.id).url} class="font-display text-2xl leading-snug font-medium after:absolute after:inset-0 after:rounded-[10px]">{project.title}</Link>
                            <div class="flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-ink-2">
                                <span>{project.references_count ? `${project.references_count} referensi` : 'Belum ada referensi'}</span>
                                <span>{project.chapters ? `Kerangka tersimpan · ${project.chapters} bab` : 'Kerangka belum dibuat'}</span>
                                <span>{project.units ? `Draf ${project.filled} dari ${project.units} bagian` : 'Draf belum dimulai'}</span>
                            </div>
                            <div class="flex justify-end border-t border-sunken pt-1">
                                <button type="button" class="btn btn-ghost relative z-10 px-3 text-[13px] text-ink-2" aria-label="{archived ? 'Pulihkan' : 'Arsipkan'} {project.title}" onclick={() => router.patch(`/projects/${project.id}/archive`, { archived: !archived }, { preserveScroll: true })}><Icon name="archive" size={16} /> {archived ? 'Pulihkan' : 'Arsipkan'}</button>
                            </div>
                        </li>
                    {/each}
                </ul>
            {:else}
                <div class="flex flex-col items-start gap-3 rounded-[10px] border border-dashed border-line-strong px-10 py-12">
                    <Icon name="book" size={28} class="text-ink-3" />
                    <h2 class="font-display text-[26px] font-medium">{archived ? 'Arsip kosong' : 'Belum ada proyek'}</h2>
                    <p class="max-w-md text-[15px] leading-relaxed text-ink-2">
                        {archived ? 'Proyek yang diarsipkan muncul di sini.' : 'Mulai dengan judul dan jenis tulisan di formulir Proyek baru. Judul dapat diubah kapan saja.'}
                    </p>
                </div>
            {/if}
        </div>

        <div class="flex flex-col gap-5">
        <form id="proyek-baru" class="card flex scroll-mt-4 flex-col gap-5 p-7" onsubmit={submit} aria-labelledby="new-project" novalidate>
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
                {#if form.idea}<p class="help rounded-lg bg-paper px-3 py-2">Catatan diskusi judul ikut disimpan ke Rancangan penelitian proyek ini.</p>{/if}
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
        <TitleBrainstorm documentType={form.document_type} onselect={(title, idea) => {
            form.title = title;
            form.idea = idea;
            form.clearErrors('title');
            document.getElementById('title')?.focus();
        }} />
        </div>
    </main>
</div>

<FlashMessage />
