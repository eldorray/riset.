<script lang="ts">
    import { Link, useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Icon from '@/components/Icon.svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import projects from '@/routes/projects';
    import type { Option, ProjectSummary, Segment } from '@/types';

    let {
        project,
        styleLabel,
        citations,
        available,
        bibliography,
        incomplete,
        citationStyles,
    }: {
        project: ProjectSummary;
        styleLabel: string;
        citations: { unit_id: string; unit: string; reference_id: number; title: string; text: string | null; missing: string[] }[];
        available: { id: number; title: string; text: string | null; missing: string[]; used: boolean }[];
        bibliography: { id: number; segments: Segment[] }[];
        incomplete: { id: number; title: string; missing: string[] }[];
        citationStyles: Option[];
    } = $props();

    const styleForm = useForm({ citation_style: '' });

    $effect(() => {
        const style = project.citation_style?.value ?? '';
        untrack(() => (styleForm.citation_style = style));
    });
</script>

<ProjectLayout {project} active="sitasi" title="Sitasi">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">Proyek / Sitasi</span>
            <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Sitasi &amp; daftar pustaka</h1>
            <p class="text-[15px] text-ink-2">
                {available.length} referensi tersedia · {citations.length} sitasi dalam draf · {bibliography.length} referensi siap di daftar pustaka{incomplete.length ? ` · ${incomplete.length} perlu dilengkapi` : ''}
            </p>
        </div>
        <div class="field w-60">
            <label for="style" class="label text-[13px]">Gaya sitasi</label>
            <select id="style" class="input" bind:value={styleForm.citation_style} onchange={() => styleForm.submit(projects.update(project.id), { preserveScroll: true })}>
                <option value="" disabled>Pilih gaya sitasi</option>
                {#each citationStyles as style (style.value)}<option value={style.value}>{style.label}</option>{/each}
            </select>
        </div>
    </header>

    {#if !project.citation_style}
        <div class="alert alert-warn" role="status">
            <Icon name="warn" class="text-warn" />
            <p>Gaya sitasi proyek belum dipilih. Pratinjau di bawah memakai {styleLabel}.</p>
        </div>
    {/if}

    <section class="card flex flex-col gap-4 p-5" aria-labelledby="available-cites">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="available-cites" class="font-display text-[22px] font-medium">Sitasi tersedia ({available.length})</h2>
            <Link href={projects.draft(project.id).url} class="btn btn-secondary">Buka Draf untuk menyisipkan sitasi</Link>
        </div>
        <p class="text-sm text-ink-2">Referensi yang ditambahkan otomatis muncul di sini. Daftar pustaka ekspor berisi sumber yang benar-benar disitasi di draf. Untuk gaya bernomor, nomor ditentukan setelah sumber dipakai.</p>
        {#if !available.length}<Link href={projects.references.index(project.id).url} class="text-primary underline">Tambah referensi</Link>{/if}
        <ul class="grid gap-3 md:grid-cols-2">
            {#each available as reference (reference.id)}
                <li class="flex flex-col gap-2 rounded-lg border border-line p-3">
                    <div class="flex flex-wrap items-center gap-2"><span class="badge {reference.used ? 'badge-ok' : ''}">{reference.used ? 'Sudah dipakai' : 'Belum dipakai'}</span>{#if reference.text}<span class="cite text-[13px]">{reference.text}</span>{/if}</div>
                    <span class="text-sm">{reference.title}</span>
                    {#if reference.missing.length}<Link href={`${projects.references.index(project.id).url}?edit=${reference.id}`} class="text-sm text-primary underline">Lengkapi {reference.missing.join(', ').toLowerCase()}</Link>{/if}
                </li>
            {/each}
        </ul>
    </section>

    <div class="grid grid-cols-1 items-start gap-7 xl:grid-cols-[420px_minmax(0,1fr)]">
        <section class="card flex flex-col" aria-labelledby="cites">
            <div class="flex flex-col gap-1 px-5 pt-4.5 pb-3.5">
                <h2 id="cites" class="font-display text-[22px] font-medium">Sitasi dalam draf</h2>
                <p class="text-[13px] text-ink-2">Sisipkan sitasi dari halaman Draf. Hanya referensi proyek ini yang bisa dirujuk.</p>
            </div>
            {#if citations.length === 0}
                <p class="border-t border-sunken px-5 py-6 text-sm text-ink-2">
                    Belum ada sitasi. {project.references_count ? 'Buka Draf dan sisipkan sitasi ke teks.' : 'Simpan referensi lebih dulu.'}
                </p>
            {/if}
            <ul>
                {#each citations as citation, i (i)}
                    <li class="flex flex-col gap-1.5 border-t border-sunken px-5 py-3.5">
                        <div class="flex items-center justify-between gap-3">
                            {#if citation.text}
                                <span class="cite text-[13px]">{citation.text}</span>
                            {:else}
                                <span class="badge badge-warn">Belum lengkap</span>
                            {/if}
                            <Link href={`${projects.draft(project.id).url}?unit=${citation.unit_id}`} class="text-right font-mono text-xs text-primary underline">{citation.unit}</Link>
                        </div>
                        <span class="text-[13px] leading-snug text-ink-2">{citation.title}</span>
                    </li>
                {/each}
            </ul>
        </section>

        <section class="card flex flex-col gap-4.5 px-5 py-6 sm:px-11 sm:py-9" aria-labelledby="bib">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="bib" class="font-display text-[26px] font-medium">Daftar Pustaka</h2>
                <span class="font-mono text-xs text-ink-3">pratinjau · {styleLabel}</span>
            </div>

            {#if bibliography.length}
                <ol class="flex flex-col gap-3.5 font-display text-base leading-relaxed">
                    {#each bibliography as entry (entry.id)}
                        <li class="pl-8 -indent-8">
                            {#each entry.segments as segment, j (j)}{#if segment.italic}<em>{segment.text}</em>{:else}{segment.text}{/if}{/each}
                        </li>
                    {/each}
                </ol>
            {:else}
                <p class="text-sm text-ink-2">Daftar pustaka terisi otomatis dari referensi yang disitasi di draf dan metadatanya lengkap.</p>
            {/if}

            {#each incomplete as reference (reference.id)}
                <div class="alert alert-warn" role="status">
                    <Icon name="warn" class="text-warn" />
                    <div class="flex flex-col items-start gap-2.5">
                        <p>
                            <strong class="font-semibold">Belum dapat diformat:</strong> “{reference.title}” belum punya
                            <strong class="font-semibold">{reference.missing.join(', ').toLowerCase()}</strong>. Entri tidak ditampilkan seolah sudah sesuai panduan.
                        </p>
                        <Link href={`${projects.references.index(project.id).url}?edit=${reference.id}`} class="btn btn-secondary">Lengkapi metadata</Link>
                    </div>
                </div>
            {/each}
        </section>
    </div>
</ProjectLayout>
