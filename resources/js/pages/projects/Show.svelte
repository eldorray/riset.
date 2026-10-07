<script lang="ts">
    import { Link, useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Icon from '@/components/Icon.svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import ExportReview from '@/components/ExportReview.svelte';
    import projects from '@/routes/projects';
    import type { Option, ProjectSummary, ExportFormat } from '@/types';

    type Readiness = {
        units: number;
        filled: number;
        empty: string[];
        citations: number;
        cited_incomplete: { id: number; title: string; missing: string[] }[];
        unknown: number;
        references: number;
        references_incomplete: number;
        front_parts: number;
        front_missing: string[];
        ai_unreviewed: number;
        next: { label: string; href: string };
        empty_href: string;
        review_href: string;
        design_ready: boolean;
        needs_data: boolean;
    };

    let {
        project,
        readiness,
        citationStyles,
        templates,
        exportFormat,
    }: {
        project: ProjectSummary;
        exportFormat: ExportFormat;
        readiness: Readiness;
        citationStyles: Option[];
        templates: { id: number; name: string; institution: string | null }[];
    } = $props();

    let editingTitle = $state(false);

    const titleForm = useForm({ title: '' });
    const styleForm = useForm({ citation_style: '' });
    const templateForm = useForm({ docx_template_id: '' as number | '' });

    $effect(() => {
        const style = project.citation_style?.value ?? '';
        const templateId = project.docx_template_id ?? '';
        untrack(() => {
            styleForm.citation_style = style;
            templateForm.docx_template_id = templateId;
        });
    });

    const template = $derived(templates.find((t) => t.id === project.docx_template_id));

    function startEditTitle() {
        titleForm.title = project.title;
        editingTitle = true;
    }

    function saveTitle(event: SubmitEvent) {
        event.preventDefault();
        titleForm.submit(projects.update(project.id), { preserveScroll: true, onSuccess: () => (editingTitle = false) });
    }

    const readyToExport = $derived(
        project.units > 0 && readiness.filled === readiness.units && !readiness.cited_incomplete.length && !readiness.unknown && !readiness.ai_unreviewed && !readiness.front_missing.length,
    );

    const steps = $derived([
        {
            title: 'Referensi',
            detail: readiness.references
                ? `${readiness.references} tersimpan${readiness.references_incomplete ? ` · ${readiness.references_incomplete} perlu dilengkapi` : ''}`
                : 'Belum ada referensi',
            status: readiness.references_incomplete ? 'warn' : readiness.references ? 'ok' : 'empty',
            badge: readiness.references_incomplete ? 'Perlu dilengkapi' : readiness.references ? 'Tersimpan' : 'Belum',
            href: projects.references.index(project.id).url,
        },
        {
            title: 'Rancangan penelitian',
            detail: readiness.design_ready ? (readiness.needs_data ? 'Rancangan terisi · data penelitian belum diisi' : project.literature_study ? 'Studi literatur · rancangan terisi' : 'Rancangan dan data terisi') : 'Isi masalah, pendekatan, dan teknik analisis (PTK: juga indikator keberhasilan)',
            status: readiness.design_ready ? (readiness.needs_data ? 'warn' : 'ok') : 'empty',
            badge: readiness.design_ready ? (readiness.needs_data ? 'Tanpa data' : 'Terisi') : 'Belum',
            href: `/projects/${project.id}/rancangan`,
        },
        {
            title: 'Gaya sitasi',
            detail: project.citation_style ? `${project.citation_style.label} · ${readiness.citations} sitasi di draf` : 'Pilih di panel samping',
            status: project.citation_style ? 'ok' : 'empty',
            badge: project.citation_style ? 'Dipilih' : 'Belum',
            href: projects.citations(project.id).url,
        },
        {
            title: 'Kerangka',
            detail: project.chapters ? `${project.chapters} bab · ${project.units} bagian` : 'Belum disusun',
            status: project.chapters ? 'ok' : 'empty',
            badge: project.chapters ? 'Tersimpan' : 'Belum',
            href: projects.outline(project.id).url,
        },
        {
            title: 'Draf',
            detail: project.units ? `${readiness.filled} dari ${readiness.units} bagian berisi` : 'Simpan kerangka lebih dulu',
            status: project.units && readiness.filled === readiness.units ? 'ok' : project.units ? 'warn' : 'empty',
            badge: project.units ? (readiness.filled === readiness.units ? 'Lengkap' : 'Berjalan') : 'Belum',
            href: readiness.empty.length ? readiness.empty_href : readiness.review_href,
        },
        {
            title: `Naskah lengkap ${project.document_type.label.toLowerCase()}`,
            detail: `${readiness.front_parts - readiness.front_missing.length} dari ${readiness.front_parts} bagian awal · ${readyToExport ? 'siap diekspor' : 'periksa sebelum ekspor'}`,
            status: readyToExport && !readiness.front_missing.length ? 'ok' : project.chapters ? 'warn' : 'empty',
            badge: readyToExport && !readiness.front_missing.length ? 'Siap' : project.chapters ? 'Berjalan' : 'Belum',
            href: projects.manuscript(project.id).url,
        },
    ]);
</script>

<ProjectLayout {project} active="ringkasan" title={project.title}>
    <header class="flex items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex min-w-0 flex-col gap-2.5">
            <span class="eyebrow">Proyek · {project.document_type.label}</span>
            {#if editingTitle}
                <form class="flex flex-wrap items-start gap-3" onsubmit={saveTitle}>
                    <div class="field min-w-0 w-full sm:min-w-80 grow">
                        <label for="title" class="sr-only">Judul proyek</label>
                        <input id="title" class="input font-display text-2xl" bind:value={titleForm.title} aria-invalid={titleForm.errors.title ? 'true' : undefined} />
                        {#if titleForm.errors.title}<span class="error">{titleForm.errors.title}</span>{/if}
                    </div>
                    <button class="btn btn-primary" disabled={titleForm.processing}>Simpan</button>
                    <button type="button" class="btn btn-ghost" onclick={() => (editingTitle = false)}>Batal</button>
                </form>
            {:else}
                <div class="flex items-center gap-2">
                    <h1 class="max-w-3xl font-display text-[30px] sm:text-[40px] leading-tight font-medium">{project.title}</h1>
                    <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Ubah judul" onclick={startEditTitle}>
                        <Icon name="pen" />
                    </button>
                </div>
            {/if}
        </div>
        <ExportReview projectId={project.id} {readiness} hasStyle={!!project.citation_style} format={exportFormat} template={template?.name} disabled={!project.chapters} />
    </header>

    <section class="card flex flex-wrap items-center justify-between gap-4 px-5 py-4" aria-label="Langkah berikutnya">
        <div><h2 class="font-semibold">Langkah berikutnya</h2><p class="text-sm text-ink-2">{readiness.next.label}. Lanjutkan sesuai progres proyek Anda.</p></div>
        <Link href={readiness.next.href} class="btn btn-primary" aria-label={`Lanjutkan: ${readiness.next.label}`}>Lanjutkan</Link>
    </section>
    <div class="grid grid-cols-1 items-start gap-7 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="flex flex-col gap-3.5">
            <h2 class="font-display text-[26px] font-medium">Alur kerja</h2>
            <ol class="card flex flex-col">
                {#each steps as step, i (step.title)}
                    <li class="grid grid-cols-[32px_minmax(0,1fr)] sm:grid-cols-[40px_minmax(0,1fr)_auto_104px] items-center gap-3 border-b border-sunken px-4 sm:px-6 py-4.5 last:border-b-0">
                        <span
                            class="flex size-8 items-center justify-center rounded-full font-mono text-[13px] {step.status === 'empty'
                                ? 'border-[1.5px] border-dashed border-line-strong text-ink-3'
                                : 'bg-ink text-white'}">{i + 1}</span
                        >
                        <div class="flex flex-col gap-0.5">
                            <span class="text-base font-semibold">{step.title}</span>
                            <span class="text-sm text-ink-2">{step.detail}</span>
                        </div>
                        <span class="col-start-2 justify-self-start sm:col-start-auto badge {step.status === 'ok' ? 'badge-ok' : step.status === 'warn' ? 'badge-warn' : 'badge-empty'}">{step.badge}</span>
                        <Link href={step.href} class="col-start-2 justify-self-start sm:col-start-auto sm:justify-self-stretch btn btn-secondary" aria-label="Buka {step.title}">Buka</Link>
                    </li>
                {/each}
            </ol>
        </section>

        <div class="flex flex-col gap-4">
            <section class="card flex flex-col gap-2.5 px-6 py-5.5">
                <label for="style" class="label">Gaya sitasi proyek</label>
                <select
                    id="style"
                    class="input"
                    bind:value={styleForm.citation_style}
                    onchange={() => styleForm.submit(projects.update(project.id), { preserveScroll: true })}
                    aria-describedby="style-help"
                >
                    <option value="" disabled>Pilih gaya sitasi</option>
                    {#each citationStyles as style (style.value)}
                        <option value={style.value}>{style.label}</option>
                    {/each}
                </select>
                <span id="style-help" class="help">{#if styleForm.processing}<span role="status">Menyimpan…</span>{:else if styleForm.recentlySuccessful}<span role="status" class="text-ok">Tersimpan.</span>{:else}Hanya gaya yang diaktifkan admin yang dapat dipilih.{/if}</span>
            </section>
            <section class="card flex flex-col gap-2.5 px-6 py-5.5">
                <label for="template" class="label">Format Word</label>
                <select
                    id="template"
                    class="input"
                    bind:value={templateForm.docx_template_id}
                    onchange={() => templateForm.submit(projects.update(project.id), { preserveScroll: true })}
                    aria-describedby="template-help"
                >
                    <option value="">Format bawaan</option>
                    {#each templates as t (t.id)}
                        <option value={t.id}>{t.name}</option>
                    {/each}
                </select>
                <span id="template-help" class="help">{#if templateForm.processing}<span role="status">Menyimpan…</span>{:else if templateForm.recentlySuccessful}<span role="status" class="text-ok">Tersimpan.</span>{:else}Template institusi diatur admin: font, spasi, margin, halaman judul, daftar isi.{/if}</span>
            </section>
            <section class="card flex flex-col gap-3 px-6 py-5.5">
                <h2 class="section-label">Rincian</h2>
                <dl class="grid grid-cols-[112px_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                    <dt class="text-ink-2">Jenis tulisan</dt><dd>{project.document_type.label}</dd>
                    <dt class="text-ink-2">Dibuat</dt><dd>{project.created_at ? new Date(project.created_at).toLocaleDateString('id', { dateStyle: 'long' }) : '—'}</dd>
                </dl>
                <p class="border-t border-sunken pt-3 text-[13px] leading-normal text-ink-3">Jenis tulisan belum dapat diganti setelah proyek dibuat.</p>
            </section>
        </div>
    </div>
</ProjectLayout>

