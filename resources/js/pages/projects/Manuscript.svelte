<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { onDestroy, untrack } from 'svelte';
    import { createWriting, type WritingSuggestion } from '@/lib/writing.svelte';
    import Icon from '@/components/Icon.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import ExportReview from '@/components/ExportReview.svelte';
    import projects from '@/routes/projects';
    import type { ProjectSummary, Segment, ExportFormat } from '@/types';

    type Part = { key: string; heading: string; label: string; keywords: string | null; hint: string; text: string; keywords_value: string; ai: boolean };
    type Chapter = { id: string; label: string; text: string | null; sections: { id: string; label: string; text: string }[] };
    type Readiness = {
        units: number;
        filled: number;
        empty: string[];
        citations: number;
        cited_incomplete: { id: number; title: string; missing: string[] }[];
        unknown: number;
        front_parts: number;
        front_missing: string[];
        words: number;
        ai_unreviewed: number;
        empty_href: string;
        review_href: string;
    };

    let {
        project,
        isBook,
        template,
        exportFormat,
        styleLabel,
        parts,
        chapters,
        bibliography,
        readiness,
        targetWords,
        aiUnits,
        references,
    }: {
        project: ProjectSummary;
        isBook: boolean;
        template: string | null;
        exportFormat: ExportFormat;
        styleLabel: string;
        parts: Part[];
        chapters: Chapter[];
        bibliography: { id: number; segments: Segment[] }[];
        readiness: Readiness;
        targetWords: number;
        aiUnits: string[];
        references: { id: number; label: string; has_notes: boolean; note_chars: number }[];
    } = $props();

    // ——— Naskah lengkap otomatis ———
    let target = $state(0);
    let mode = $state<'fill' | 'rewrite'>('fill');
    let chosen = $state<number[]>([]);
    const writing = createWriting(() => project.id);
    const run = $derived(writing.active && writing.run?.kind === 'manuscript' ? writing.run : null);
    let startError = $state('');
    const summary = $derived(startError || (writing.run?.kind === 'manuscript' && !writing.active
        ? `${writing.run.done} dari ${writing.run.total} bagian diproses. ${writing.run.error ?? ''} Tinjau hasil sebelum diajukan. ${writing.run.results.filter((r) => r.error).map((r) => `${r.label}: ${r.error}`).join('; ')}` : ''));
    const warnings = $derived((writing.run?.kind === 'manuscript' ? writing.run.results : []).filter((r) => r.limitations).map((r) => `${r.label}: ${r.limitations}`));
    const completedTarget = $derived(writing.run?.kind === 'manuscript' ? writing.run.target_words : null);

    $effect(() => {
        const initialTarget = targetWords;
        const ids = references.map((r) => r.id);
        untrack(() => {
            target ||= initialTarget;
            chosen = chosen.length ? chosen : ids;
        });
    });

    const sourceCharacters = $derived(references.filter(r => chosen.includes(r.id)).reduce((sum, r) => sum + r.note_chars, 0) + 3000);
    const writingParts = $derived(Math.max(1, chapters.reduce((sum, c) => sum + Math.max(1, c.sections.length), 0)));
    async function buildAll() {
        if (form.isDirty || Object.keys(suggestions).length) {
            startError = 'Simpan perubahan bagian awal dan pakai atau buang usulan AI sebelum membuat naskah lengkap.';
            return;
        }
        if (mode === 'rewrite' && !confirm('Semua bagian yang sudah berisi akan ditulis ulang. Lanjutkan?')) return;
        startError = '';
        try { await writing.start({ kind: 'manuscript', references: $state.snapshot(chosen), target_words: target, mode }); }
        catch (error) { startError = (error as Error).message; }
    }

    let frontBaseline = $state<Record<string, { text: string; keywords: string }>>({});
    const form = useForm({ parts: {} as Record<string, { text: string; keywords: string }> });
    const suggestions = $derived(Object.fromEntries(writing.suggestions.filter((s) => s.type === 'front').map((s) => [s.key, s])) as Record<string, WritingSuggestion>);
    let localFailures = $state<Record<string, string>>({});
    const failures = $derived({ ...Object.fromEntries((writing.run?.results ?? []).filter((r) => r.status === 'failed').map((r) => [r.key, r.error ?? 'Penulisan gagal.'])), ...localFailures });
    const generating = $derived(writing.active && writing.run?.kind === 'front' ? writing.run.key : null);

    $effect(() => {
        const saved = Object.fromEntries(parts.map((p) => [p.key, { text: p.text, keywords: p.keywords_value }]));
        untrack(() => {
            if (form.isDirty) return;
            form.parts = saved;
            frontBaseline = structuredClone(saved);
            form.defaults();
        });
    });
    let refreshedRun = 0;
    $effect(() => {
        const finished = writing.run;
        if (finished?.kind === 'manuscript' && !writing.active && !form.isDirty && refreshedRun !== finished.id) {
            refreshedRun = finished.id;
            router.reload({ only: ['project', 'parts', 'chapters', 'bibliography', 'readiness', 'aiUnits'] });
        }
    });

    const type = $derived(project.document_type.label.toLowerCase());
    const checks = $derived([
        { label: 'Kerangka tersimpan', ok: project.chapters > 0, detail: `${project.chapters} bab`, href: projects.outline(project.id).url },
        { label: 'Draf terisi', ok: readiness.units > 0 && readiness.filled === readiness.units, detail: `${readiness.filled} dari ${readiness.units} bagian`, href: readiness.empty_href },
        { label: 'Bagian awal', ok: readiness.front_missing.length === 0, detail: `${readiness.front_parts - readiness.front_missing.length} dari ${readiness.front_parts}`, href: '#bagian-awal' },
        { label: 'Sitasi lengkap', ok: !readiness.cited_incomplete.length && !readiness.unknown, detail: readiness.cited_incomplete.length ? `${readiness.cited_incomplete.length} referensi belum lengkap` : `${readiness.citations} sitasi · ${styleLabel}`, href: readiness.cited_incomplete.length ? `${projects.references.index(project.id).url}?edit=${readiness.cited_incomplete[0].id}` : projects.citations(project.id).url },
        { label: 'Hasil AI ditinjau', ok: readiness.ai_unreviewed === 0, detail: `${readiness.ai_unreviewed} bagian belum ditinjau`, href: readiness.review_href },
        { label: 'Format Word', ok: true, detail: template ?? 'Format bawaan', href: projects.show(project.id).url },
    ]);
    const offBefore = router.on('before', (event) => {
        if (event.detail.visit.method === 'get' && form.isDirty) {
            if (!confirm('Ada perubahan yang belum disimpan. Tinggalkan halaman?')) event.preventDefault();
        }
    });
    onDestroy(offBefore);

    const allOk = $derived(checks.every((c) => c.ok));

    async function generate(key: string) {
        if (form.isDirty) { localFailures[key] = 'Simpan perubahan bagian awal terlebih dahulu.'; return; }
        delete localFailures[key];
        try { await writing.start({ kind: 'front', part: key }); }
        catch (error) { localFailures[key] = (error as Error).message; }
    }

    async function accept(key: string) {
        if (form.isDirty) { localFailures[key] = 'Simpan perubahan bagian awal terlebih dahulu.'; return; }
        try {
            const result = await writing.review(suggestions[key], 'accept');
            form.parts[key] = { text: result.front_matter[key].text, keywords: result.front_matter[key].keywords };
            frontBaseline[key] = { ...form.parts[key] };
            form.defaults();
            router.reload({ only: ['parts', 'project', 'readiness'] });
        } catch (error) { localFailures[key] = (error as Error).message; }
    }

    async function discard(key: string) {
        try { await writing.review(suggestions[key], 'discard'); }
        catch (error) { localFailures[key] = (error as Error).message; }
    }

    async function stopWriting() {
        try { await writing.stop(); }
        catch (error) { startError = (error as Error).message; }
    }

    function save() {
        const changed = Object.fromEntries(Object.entries($state.snapshot(form.parts)).filter(([key, value]) => value.text !== frontBaseline[key]?.text || value.keywords !== frontBaseline[key]?.keywords));
        form.transform(() => ({ parts: changed, base: Object.fromEntries(Object.keys(changed).map((key) => [key, frontBaseline[key]])) }));
        form.submit(projects.manuscript.update(project.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                const saved = (page.props.parts as Part[]).map((part) => [part.key, { text: part.text, keywords: part.keywords_value }]);
                form.parts = Object.fromEntries(saved);
                frontBaseline = structuredClone($state.snapshot(form.parts));
                form.defaults();
            },
            onError: () => { startError = 'Gagal menyimpan bagian awal. Edit tetap tersedia di halaman ini.'; },
        });
    }

    const paragraphs = (text: string | null) => (text ?? '').split(/\n\s*\n/).filter((p) => p.trim());
    const aiLabel = (key: string) => (key === 'abstract' ? 'Terjemahkan dari abstrak' : key === 'kata_pengantar' ? 'Buat draf dengan AI' : 'Susun dari draf dengan AI');
</script>

<svelte:window
    onbeforeunload={(event) => {
        if (form.isDirty) {
            event.preventDefault();
        }
    }}
/>

<ProjectLayout {project} active="naskah" title="Naskah lengkap">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">Proyek / Naskah lengkap</span>
            <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Naskah lengkap {type}</h1>
            <p class="max-w-3xl text-[15px] leading-normal text-ink-2">
                {isBook ? 'Halaman judul, ' : 'Judul, '}{parts.map((p) => p.label.toLowerCase()).join(', ')}{isBook ? ', daftar isi' : ''}, {project.chapters} bab, dan daftar pustaka — tersusun sesuai kerangka tersimpan.
            </p>
        </div>
        <ExportReview projectId={project.id} {readiness} hasStyle={!!project.citation_style} {template} format={exportFormat} disabled={!project.chapters || run !== null || form.isDirty} label="Unduh naskah .docx" />
    </header>



    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="flex min-w-0 flex-col gap-8">
            <details class="card flex flex-col gap-4 border-ai-line px-6 py-5" ><summary class="cursor-pointer text-sm font-semibold">Pilihan tambahan · Buat naskah lengkap dengan AI</summary>
                <div class="flex flex-col gap-1">
                    <h2 id="auto-title" class="flex items-center gap-2.5 font-display text-2xl font-medium">
                        <span class="shrink-0 rounded-full border border-ai px-1.5 font-sans text-[11px] leading-4 text-ai">AI</span> Buat naskah lengkap dengan AI
                    </h2>
                    <p class="text-sm leading-normal text-ink-2">
                        AI menyusun kerangka bila belum ada, menulis setiap bagian, lalu abstrak{parts.length > 1 ? ' dan bagian awal lainnya' : ''}. Tulisan memakai kata-kata sendiri (parafrase) dengan sitasi dari referensi terpilih — bukan menyalin kalimat sumber.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="field">
                        <label for="target" class="label">Target minimal jumlah kata</label>
                        <input id="target" type="number" min="1000" max="80000" step="500" class="input" bind:value={target} disabled={run !== null} aria-describedby="target-help" />
                        <span id="target-help" class="help">Saat ini {readiness.words.toLocaleString('id')} kata. Dibagi rata ke setiap bagian.</span>
                    </div>
                    <fieldset class="flex flex-col gap-1" disabled={run !== null}>
                        <legend class="label mb-1.5">Bagian yang sudah berisi</legend>
                        <label class="flex min-h-10 items-center gap-2.5 text-sm"><input type="radio" value="fill" bind:group={mode} class="size-4.5 accent-primary" /> Biarkan — hanya isi yang kosong</label>
                        <label class="flex min-h-10 items-center gap-2.5 text-sm"><input type="radio" value="rewrite" bind:group={mode} class="size-4.5 accent-primary" /> Tulis ulang semua dengan parafrase</label>
                    </fieldset>
                </div>

                <details class="rounded-lg bg-paper px-4 py-3">
                    <summary class="cursor-pointer text-sm font-semibold">Referensi yang dipakai ({chosen.length} dari {references.length})</summary>
                    {#if references.length === 0}
                        <p class="pt-2 text-[13px] text-warn">Belum ada referensi — AI akan menulis tanpa sitasi dan menyatakan keterbatasannya.</p>
                    {/if}
                    <div class="flex flex-col pt-2">
                        {#each references as reference (reference.id)}
                            <label class="flex min-h-10 items-center gap-2.5 text-[13px]">
                                <input type="checkbox" value={reference.id} bind:group={chosen} class="size-4.5 accent-primary" disabled={run !== null} />
                                {reference.label}
                                {#if !reference.has_notes}<span class="text-[11px] text-ink-3">(tanpa catatan isi)</span>{/if}
                            </label>
                        {/each}
                    </div>
                </details>

                {#if run}
                    <div class="flex flex-col gap-2.5" role="status" aria-live="polite">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-ai"><Icon name="spinner" class="text-ai" /> {run.label}</span>
                            <span class="font-mono text-xs text-ink-2">{run.done}/{run.total}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-ai-soft">
                            <div class="h-full bg-ai transition-[width]" style="width: {(run.done / Math.max(run.total, 1)) * 100}%"></div>
                        </div>
                        <button type="button" class="btn btn-secondary self-start" onclick={stopWriting} disabled={run.stop_requested}>{run.stop_requested ? 'Menghentikan…' : 'Hentikan'}</button>
                    </div>
                {:else}
                    <div class="flex flex-wrap items-center gap-3">
                        <AiCost inputCharacters={sourceCharacters} outputWords={target / writingParts} requests={writingParts} detail="Perkiraan isi berdasarkan kerangka saat ini; jika kerangka dibuat AI, jumlah proses dapat berubah. Bagian awal dihitung terpisah." />
                        <button type="button" class="btn btn-primary" onclick={buildAll} disabled={writing.busy || target < 1000 || target > 80000 || !Number.isFinite(target) || form.isDirty || Object.keys(suggestions).length > 0}>
                            Buat naskah lengkap {type}
                        </button>
                        <span class="text-xs leading-normal text-ink-3">Tidak ada jaminan lolos pemeriksaan plagiarisme atau pendeteksi AI; ikuti kebijakan kampus tentang penggunaan AI.</span>
                    </div>
                {/if}

                {#if summary}
                    <div class="alert {summary.includes('gagal') || summary.includes('Dihentikan') ? 'alert-warn' : 'alert-ok'}" role="status"><p>{summary}</p></div>
                {/if}
                {#if form.isDirty || Object.keys(suggestions).length}<p class="text-sm text-warn">Simpan perubahan dan pakai atau buang usulan AI sebelum membuat naskah lengkap.</p>{/if}
                {#if completedTarget !== null && run === null}
                    <p class="text-sm {readiness.words < completedTarget ? 'text-warn' : 'text-ok'}">Hasil saat ini: {readiness.words.toLocaleString('id')} dari target {completedTarget.toLocaleString('id')} kata. {readiness.words < completedTarget ? 'Target belum tercapai; lanjutkan penulisan di halaman Draf.' : 'Target tercapai.'}</p>
                {/if}
                {#if warnings.length}
                    <div class="alert alert-warn" role="status"><div><p class="font-semibold">Keterbatasan hasil AI</p><ul class="list-disc pl-5">{#each warnings as warning, i (i)}<li>{warning}</li>{/each}</ul></div></div>
                {/if}
                {#if readiness.ai_unreviewed}
                    <p class="text-[13px] text-ai">{readiness.ai_unreviewed} bagian ditulis AI dan belum ditinjau. Sunting atau tandai "sudah diperiksa" di halaman Draf.</p>
                {/if}
            </details>

            <section id="bagian-awal" class="flex flex-col gap-4" aria-labelledby="front-title">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2 id="front-title" class="font-display text-[26px] font-medium">Bagian awal</h2>
                    <div class="flex items-center gap-3">
                        {#if form.isDirty}<span class="text-[13px] font-medium text-warn">Perubahan belum disimpan</span>{/if}
                        <button type="button" class="btn btn-primary" onclick={save} disabled={!form.isDirty || form.processing || run !== null}>Simpan bagian awal</button>
                    </div>
                </div>

                {#each parts as part (part.key)}
                    {#if form.parts[part.key]}
                        <article id={`front-${part.key}`} class="card flex flex-col gap-3.5 px-6 py-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex flex-col gap-0.5">
                                    <h3 class="text-base font-semibold">{part.label}</h3>
                                    <span class="text-[13px] text-ink-2">{part.hint}</span>
                                    {#if part.ai}<span class="text-sm text-ai">AI · belum ditinjau</span><button type="button" class="btn btn-secondary" disabled={form.isDirty || run !== null || Object.keys(suggestions).length > 0} onclick={() => router.put(projects.manuscript.update(project.id).url, { parts: { [part.key]: form.parts[part.key] }, base: { [part.key]: frontBaseline[part.key] } }, { preserveScroll: true })}>Sudah diperiksa</button>{/if}
                                </div>
                                <button type="button" class="btn btn-secondary" onclick={() => generate(part.key)} disabled={writing.busy || !!suggestions[part.key]}>
                                    {#if generating === part.key}<Icon name="spinner" size={16} class="text-ai" /> Menyusun…{:else}<span class="rounded-full border border-current px-1.5 font-mono text-[10px] leading-3.5">AI</span> {aiLabel(part.key)}{/if}
                                </button>
                            </div>

                            <AiCost inputCharacters={Math.min(30000, readiness.words * 6)} outputWords={250} detail="Perkiraan untuk satu bagian awal naskah." />
                            <label class="sr-only" for="part-{part.key}">{part.label}</label>
                            <textarea id="part-{part.key}" rows={part.key === 'kata_pengantar' ? 8 : 6} class="input font-display text-[17px] leading-7" bind:value={form.parts[part.key].text} disabled={run !== null} placeholder="Tulis {part.label.toLowerCase()} di sini, atau minta bantuan AI."></textarea>

                            {#if part.keywords}
                                <div class="field">
                                    <label for="kw-{part.key}" class="label text-[13px]">{part.keywords}</label>
                                    <input id="kw-{part.key}" class="input" placeholder="Pisahkan dengan koma" bind:value={form.parts[part.key].keywords} disabled={run !== null} />
                                </div>
                            {/if}

                            {#if failures[part.key]}
                                <div class="alert alert-danger" role="alert"><Icon name="error" class="text-danger" /><p>{failures[part.key]}</p></div>
                            {/if}

                            {#if suggestions[part.key]}
                                {@const suggestion = suggestions[part.key]}
                                <section aria-label="Usulan AI" class="flex flex-col gap-3 rounded-[10px] border border-ai-line bg-ai-wash px-4.5 py-4">
                                    <span class="flex items-center gap-2 text-[13px] font-semibold text-ai">
                                        <span class="rounded-full border border-ai px-1.5 font-mono text-[11px] leading-3.5">AI</span> Dihasilkan AI · belum diperiksa
                                    </span>
                                    {#each paragraphs(suggestion.text) as paragraph, i (i)}<p class="font-display text-[17px] leading-7">{paragraph}</p>{/each}
                                    {#if suggestion.keywords}<p class="text-sm"><span class="font-semibold">{part.keywords}:</span> {suggestion.keywords}</p>{/if}
                                    {#if suggestion.limitations}
                                        <p class="rounded-lg border border-ai-line bg-surface px-3.5 py-2.5 text-[13px]"><span class="font-semibold">Keterbatasan:</span> {suggestion.limitations}</p>
                                    {/if}
                                    <div class="flex gap-2.5">
                                        <button type="button" class="btn btn-primary" onclick={() => accept(part.key)}>Pakai teks ini</button>
                                        <button type="button" class="btn btn-ghost text-danger" onclick={() => discard(part.key)}>Buang</button>
                                    </div>
                                </section>
                            {/if}
                        </article>
                    {/if}
                {/each}
            </section>

            <section class="flex flex-col gap-3" aria-labelledby="preview-title">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="preview-title" class="font-display text-[26px] font-medium">Pratinjau naskah</h2>
                    <span class="font-mono text-xs text-ink-3">{template ?? 'format bawaan'} · {styleLabel}</span>
                </div>
                <article class="card flex flex-col gap-6 px-5 py-6 sm:px-10 sm:py-10 lg:px-14 lg:py-12 font-display text-[17px] leading-[30px]">
                    <h3 class="text-center text-[26px] leading-tight font-medium">{project.title}</h3>

                    {#each parts as part (part.key)}
                        <div class="flex flex-col gap-3 {isBook ? 'border-t border-dashed border-line-strong pt-6' : ''}">
                            <h4 class="text-center text-base font-semibold tracking-wide">{part.heading}</h4>
                            {#if form.parts[part.key]?.text.trim()}
                                {#each paragraphs(form.parts[part.key].text) as paragraph, i (i)}<p class="text-justify">{paragraph}</p>{/each}
                                {#if part.keywords && form.parts[part.key].keywords}<p><span class="font-semibold">{part.keywords}:</span> <em>{form.parts[part.key].keywords}</em></p>{/if}
                            {:else}
                                <p class="text-center text-sm font-medium text-warn">[{part.label} belum diisi]</p>
                            {/if}
                        </div>
                    {/each}

                    {#if isBook}
                        <p class="border-t border-dashed border-line-strong pt-6 text-center text-sm text-ink-3">DAFTAR ISI — dibuat otomatis di Word bila template mengaktifkannya</p>
                    {/if}

                    {#each chapters as chapter (chapter.id)}
                        <div class="flex flex-col gap-3 {isBook ? 'border-t border-dashed border-line-strong pt-6' : ''}">
                            <h4 class="text-center text-lg font-semibold">{chapter.label}</h4>
                            {#if chapter.sections.length === 0}
                                {#each paragraphs(chapter.text) as paragraph, i (i)}<p class="indent-10 text-justify">{paragraph}</p>{:else}<p class="text-sm font-medium text-warn">[Bagian ini belum ditulis]</p>{/each}
                            {/if}
                            {#each chapter.sections as section (section.id)}
                                <h5 class="mt-2 flex items-center gap-2 text-base font-semibold">
                                    {section.label}
                                    {#if aiUnits.includes(section.id)}<span class="badge badge-ai font-sans text-[11px]">AI · belum ditinjau</span>{/if}
                                </h5>
                                {#each paragraphs(section.text) as paragraph, i (i)}<p class="indent-10 text-justify">{paragraph}</p>{:else}<p class="text-sm font-medium text-warn">[Bagian ini belum ditulis]</p>{/each}
                            {/each}
                        </div>
                    {:else}
                        <p class="text-sm text-ink-2">Kerangka belum disimpan. <Link href={projects.outline(project.id).url} class="text-primary underline">Susun kerangka</Link></p>
                    {/each}

                    {#if bibliography.length}
                        <div class="flex flex-col gap-3 {isBook ? 'border-t border-dashed border-line-strong pt-6' : ''}">
                            <h4 class="text-center text-lg font-semibold">DAFTAR PUSTAKA</h4>
                            <ol class="flex flex-col gap-2.5 text-base leading-relaxed">
                                {#each bibliography as entry (entry.id)}
                                    <li class="pl-8 -indent-8">{#each entry.segments as segment, j (j)}{#if segment.italic}<em>{segment.text}</em>{:else}{segment.text}{/if}{/each}</li>
                                {/each}
                            </ol>
                        </div>
                    {/if}
                </article>
            </section>
        </div>

        <aside class="card flex flex-col gap-3 px-5 py-5 xl:sticky xl:top-6">
            <h2 class="section-label">Daftar periksa</h2>
            <ul class="flex flex-col">
                {#each checks as check (check.label)}
                    <li class="border-b border-sunken last:border-b-0">
                        <Link href={check.href} class="flex min-h-12 items-center gap-3 py-2">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full {check.ok ? 'bg-ok-soft text-ok' : 'bg-warn-soft text-warn'}">
                                <Icon name={check.ok ? 'check' : 'warn'} size={14} />
                            </span>
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold">{check.label}</span>
                                <span class="text-xs text-ink-2">{check.detail}</span>
                            </span>
                        </Link>
                    </li>
                {/each}
            </ul>
            <p class="rounded-lg px-3 py-2.5 text-[13px] leading-normal {allOk ? 'bg-ok-soft text-ok' : 'bg-warn-soft text-warn'}">
                {allOk ? `Naskah ${type} siap diekspor.` : 'Masih ada yang perlu diperiksa. Anda tetap bisa mengunduh untuk ditinjau.'}
            </p>
            <p class="text-xs leading-normal text-ink-3">Hasil AI dan format bawaan bukan jaminan sesuai panduan kampus atau jurnal. Periksa kembali sebelum diajukan.</p>
        </aside>
    </div>
</ProjectLayout>
