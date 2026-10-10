<script lang="ts">
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import Icon from '@/components/Icon.svelte';
    import { createWriting } from '@/lib/writing.svelte';
    import type { ProjectSummary } from '@/types';

    type Source = { id: number; title: string; url: string; citation: string; basis: string; notes: string; fingerprint: string; keywords?: string[] };
    type Candidate = { title: string; gap: string; type: 'synthesis' | 'author_limitations'; importance: string; question: string; contribution: string; verification: string; source_ids: number[]; evidence: { reference_id: number; quote: string }[] };
    type Analysis = { id: number; focus: string; created_at: string; sources: Source[]; excluded: { id: number; reason: string }[]; limitations: string; matrix: { reference_id: number; focus: string; context: string; method: string; findings: string; limitations: string }[]; candidates: Candidate[] };
    type SelectedGap = { analysis_id: number; candidate: number; title: string; gap: string; question: string; contribution: string; sources: Source[]; saved_at: string };
    let { project, references, analysis, selectedGap, analysisStale, selectedStale }: { project: ProjectSummary; references: Source[]; analysis: Analysis | null; selectedGap: SelectedGap | null; analysisStale: boolean; selectedStale: boolean } = $props();

    let focus = $state('');
    let selected = $state<number[]>([]);
    let actionError = $state('');
    let starting = $state(false);
    let refreshed = $state<number | null>(null);
    let editing = $state(false);
    let removing = $state(false);
    let initialized = $state<number | null>(null);
    const writing = createWriting(() => project.id);
    const choice = useForm({ analysis_id: 0, candidate: 0, gap: '', question: '', contribution: '', reviewed: false });
    const chosen = $derived(references.filter(source => selected.includes(source.id)));
    const unread = $derived(chosen.filter(source => !source.notes.trim()).length);
    const groups = $derived(Array.from(new Set(references.map(source => source.keywords?.[0] || 'Tanpa kategori'))).map(name => ({ name, sources: references.filter(source => (source.keywords?.[0] || 'Tanpa kategori') === name) })));
    $effect(() => {
        if (initialized !== project.id) {
            initialized = project.id;
            focus = analysis?.focus ?? '';
            selected = analysis ? [...analysis.sources.map(source => source.id), ...analysis.excluded.map(source => source.id)].filter(id => references.some(source => source.id === id)) : [];
            editing = false;
        }
    });
    $effect(() => {
        const run = writing.run;
        if (run?.kind === 'gap' && ['completed', 'failed', 'stopped'].includes(run.status) && refreshed !== run.id) {
            refreshed = run.id;
            router.reload({ only: ['analysis', 'selectedGap', 'references', 'analysisStale', 'selectedStale'] });
        }
    });

    function toggle(id: number) {
        selected = selected.includes(id) ? selected.filter(value => value !== id) : [...selected, id];
    }
    function selectGroup(sources: Source[]) {
        const ids = sources.map(source => source.id);
        if (ids.every(id => selected.includes(id))) {
            selected = selected.filter(id => !ids.includes(id));
        } else {
            selected = Array.from(new Set([...selected, ...ids]));
        }
    }
    async function start(event: SubmitEvent) {
        event.preventDefault();
        if (analysis && !confirm('Analisis ulang akan mengganti hasil analisis setelah selesai. Gap pilihan Anda tetap tersimpan. Lanjutkan?')) return;
        starting = true;
        actionError = '';
        try { await writing.start({ kind: 'gap', focus: focus.trim() || project.title, references: selected }); }
        catch (error) { actionError = (error as Error).message; }
        finally { starting = false; }
    }
    function choose(index: number) {
        if (!analysis) return;
        const candidate = analysis.candidates[index];
        choice.analysis_id = analysis.id;
        choice.candidate = index;
        choice.gap = candidate.gap;
        choice.question = candidate.question;
        choice.contribution = candidate.contribution;
        choice.reviewed = false;
        choice.clearErrors();
        editing = true;
        requestAnimationFrame(() => document.getElementById('gap-choice')?.focus());
    }
    function save(event: SubmitEvent) {
        event.preventDefault();
        choice.put(`/projects/${project.id}/research-gap`, { preserveScroll: true, onSuccess: () => (editing = false) });
    }
    function remove() {
        if (!confirm('Lepas gap pilihan dari arah penelitian? Hasil analisis dan tulisan tetap tersimpan.')) return;
        removing = true;
        router.delete(`/projects/${project.id}/research-gap`, { preserveScroll: true, onFinish: () => (removing = false) });
    }
    const sourceFor = (id: number) => analysis?.sources.find(source => source.id === id);
</script>

<ProjectLayout {project} active="gap" title="Research Gap">
    <header class="border-b border-line pb-6">
        <p class="eyebrow mb-2">Dari referensi ke arah penelitian</p>
        <h1 class="font-display text-[30px] leading-tight font-medium sm:text-[40px]">Research Gap</h1>
        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-ink-2">Bandingkan penelitian yang Anda pilih, tinjau bukti, lalu tentukan pertanyaan yang layak diteliti. Hasil AI adalah kandidat gap, bukan jaminan kebaruan.</p>
    </header>

    {#if selectedGap}
        <section class="card flex flex-col gap-3 border-primary-line bg-primary-soft/30 p-5 sm:p-6" aria-labelledby="selected-title">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="selected-title" class="text-base font-semibold">Arah penelitian pilihan Anda</h2><button type="button" class="btn btn-ghost text-danger" disabled={removing} onclick={remove}>Lepas pilihan</button></div>
            <h3 class="font-display text-2xl">{selectedGap.title}</h3>
            <p class="whitespace-pre-line text-sm leading-relaxed">{selectedGap.gap}</p>
            <p class="text-sm"><strong>Pertanyaan:</strong> {selectedGap.question}</p>
            <p class="text-sm"><strong>Kontribusi yang diharapkan:</strong> {selectedGap.contribution}</p>
            {#if selectedStale}<p class="text-sm text-danger" role="status">Sumber pilihan telah berubah atau dihapus. Konteks ini tidak dipakai AI sampai Anda menganalisis dan memilih ulang.</p>{:else}<p class="help">Dipakai sebagai arah kerangka dan draf, bukan pengganti bukti atau sitasi. Verifikasi kebaruan melalui pencarian lanjutan.</p>{/if}
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-primary-line bg-surface px-4 py-3 text-sm">
                <span class="grow">Sesuaikan judul dan rumusan masalah dengan arah ini supaya Bab I sampai kesimpulan konsisten.</span>
                <Link href={`/projects/${project.id}/rancangan`} class="btn btn-primary">Buka Rancangan penelitian</Link>
            </div>
            {#if analysis?.id === selectedGap.analysis_id && !analysisStale}<button type="button" class="btn btn-secondary self-start" onclick={() => { choose(selectedGap!.candidate); choice.gap = selectedGap!.gap; choice.question = selectedGap!.question; choice.contribution = selectedGap!.contribution; }}>Sunting pilihan</button>{/if}
        </section>
    {/if}

    <form class="card flex flex-col gap-5 p-5 sm:p-6" onsubmit={start}>
        <div class="field"><label class="label" for="gap-focus">Fokus yang ingin dibandingkan</label><textarea id="gap-focus" class="input min-h-24" maxlength="2000" placeholder={project.title} bind:value={focus} disabled={starting}></textarea><p class="help">Kosongkan untuk memakai judul proyek. Sebutkan topik, konteks, atau persoalan yang ingin Anda teliti.</p></div>
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold">Pilih sumber <span class="font-mono text-sm text-ink-3">{selected.length}/{references.length}</span></h2><div class="flex flex-wrap gap-2">{#if selected.length < references.length}<button type="button" class="btn btn-ghost" disabled={starting} onclick={() => (selected = references.map(source => source.id))}>Pilih semua</button>{/if}{#if selected.length}<button type="button" class="btn btn-ghost" disabled={starting} onclick={() => (selected = [])}>Kosongkan pilihan</button>{/if}<Link class="btn btn-secondary" href={`/projects/${project.id}/references`}>Kelola referensi</Link></div></div>
        <p class="help">Minimal 2 sumber. Pilih artikel yang relevan; sumber tanpa catatan akan dicoba dibaca terlebih dahulu. Makin banyak sumber, makin lama dan makin banyak kredit yang dipakai.</p>
        {#each groups as group (group.name)}
            <details class="rounded-lg border border-line" open>
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">{group.name} ({group.sources.length})</summary>
                <div class="border-t border-line px-4 py-3"><button class="btn btn-ghost mb-2 text-xs" type="button" disabled={starting} onclick={() => selectGroup(group.sources)}>{group.sources.every(source => selected.includes(source.id)) ? 'Batal pilih kategori' : 'Pilih kategori'}</button>
                    {#each group.sources as source (source.id)}<label class="flex cursor-pointer items-start gap-3 border-t border-sunken py-3 has-disabled:cursor-not-allowed has-disabled:opacity-55"><input type="checkbox" class="mt-0.5 size-4.5 shrink-0 accent-primary" checked={selected.includes(source.id)} onchange={() => toggle(source.id)} disabled={starting} /><span class="min-w-0"><span class="block text-sm font-medium">{source.title}</span><span class="mt-1 block text-xs text-ink-3">{source.notes.trim() ? source.basis : 'Belum dibaca · akan dicoba otomatis'}</span></span></label>{/each}
                </div>
            </details>
        {:else}<div class="rounded-lg border border-dashed border-line-strong p-5 text-sm text-ink-2">Tambahkan minimal dua referensi sebelum memulai analisis. <Link href={`/projects/${project.id}/references`} class="font-semibold text-primary underline">Tambah referensi</Link></div>{/each}
        {#if selected.length >= 2}<AiCost inputCharacters={3000 + chosen.reduce((sum, source) => sum + source.notes.length, 0)} outputWords={800 + chosen.length * 150} detail="Perkiraan untuk analisis perbandingan." /><p class="help">{unread ? `${unread} sumber belum dibaca. Pembacaan sumber memakai kredit tambahan di luar perkiraan analisis di atas.` : 'Catatan sumber yang sudah tersimpan digunakan kembali.'}</p>{/if}
        {#if actionError || writing.error}<p role="alert" class="error">{actionError || writing.error}</p>{/if}
        <button class="btn btn-primary self-start" disabled={selected.length < 2 || starting || writing.busy || !page.props.auth.user?.ai_active}>{starting ? 'Menyiapkan analisis…' : analysis ? 'Analisis ulang dengan AI' : 'Analisis research gap dengan AI'}<Icon name="gap" size={16} /></button>
        {#if !page.props.auth.user?.ai_active}<p class="help">Analisis memerlukan paket AI aktif. <Link href="/account/subscription" class="font-medium text-primary underline">Aktifkan di Paket & Kredit</Link></p>{:else if selected.length < 2}<p class="help">Pilih minimal 2 sumber untuk memulai.</p>{/if}
        <p class="help">Analisis tetap berjalan saat Anda pindah menu. Pembacaan dan hasil AI perlu diperiksa terhadap artikel asli.</p>
    </form>

    {#if writing.run?.kind === 'gap' && !writing.active}
        {#if writing.run.status === 'failed'}<div class="alert alert-danger" role="alert"><p>{writing.run.error || writing.run.results.find(result => result.error)?.error || 'Analisis gagal. Hasil sebelumnya tetap tersimpan.'}</p></div>
        {:else if writing.run.status === 'stopped'}<p class="help" role="status">Analisis dihentikan. Catatan yang sudah berhasil dibaca tetap tersimpan.</p>{/if}
        {#each writing.run.results.filter(result => result.type === 'gap_source' && result.limitations) as result (result.key)}<p class="text-sm text-ink-2">{result.label}: {result.limitations}</p>{/each}
    {/if}

    {#if analysis}
        <section class="flex flex-col gap-4" aria-labelledby="analysis-title">
            <div><p class="eyebrow mb-2 text-ai">Usulan AI · belum diverifikasi</p><h2 id="analysis-title" class="font-display text-2xl sm:text-3xl">Perbandingan penelitian</h2><p class="mt-2 text-sm text-ink-2">Fokus: {analysis.focus}</p></div>
            {#if analysisStale}<div class="alert alert-warn" role="status"><p>Sumber analisis telah berubah atau dihapus. Hasil ini adalah snapshot lama; analisis ulang sebelum memilih kandidat.</p></div>{/if}
            <p class="text-sm leading-relaxed text-ink-2">{analysis.limitations}</p>
            {#each analysis.excluded as source (source.id)}<p class="text-sm text-danger">Sumber tidak dipakai: {references.find(reference => reference.id === source.id)?.title ?? `Referensi #${source.id}`} — {source.reason}</p>{/each}
            <ul class="flex flex-col gap-3 md:hidden" aria-label="Matriks penelitian">
                {#each analysis.matrix as row (row.reference_id)}
                    {@const source = sourceFor(row.reference_id)}
                    <li class="card flex flex-col gap-3 p-4 text-sm">
                        <div><a href={source?.url} target="_blank" rel="noopener noreferrer" class="font-semibold text-primary underline">{source?.title}</a><p class="mt-1 text-xs text-ink-3">{source?.citation} · {source?.basis}</p></div>
                        <dl class="flex flex-col gap-2.5">
                            <div><dt class="section-label text-[11px]">Fokus & konteks</dt><dd class="mt-0.5 whitespace-pre-line">{row.focus}{#if row.context}<span class="mt-1 block text-ink-2">{row.context}</span>{/if}</dd></div>
                            <div><dt class="section-label text-[11px]">Metode</dt><dd class="mt-0.5 whitespace-pre-line">{row.method}</dd></div>
                            <div><dt class="section-label text-[11px]">Temuan utama</dt><dd class="mt-0.5 whitespace-pre-line">{row.findings}</dd></div>
                            <div><dt class="section-label text-[11px]">Keterbatasan</dt><dd class="mt-0.5 whitespace-pre-line">{row.limitations}</dd></div>
                        </dl>
                    </li>
                {/each}
            </ul>
            <p class="text-xs text-ink-3 md:hidden">“Tidak disebutkan” berarti informasi tidak tersedia dalam bahan, bukan tidak pernah diteliti.</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (Keyboard users can scroll the comparison table.) -->
            <div class="card hidden overflow-x-auto md:block" role="region" aria-label="Matriks penelitian, geser untuk melihat semua kolom" tabindex="0">
                <table class="w-full min-w-[980px] border-collapse text-left text-sm"><caption class="px-5 py-3 text-left text-xs text-ink-3">Ringkasan catatan sumber · “Tidak disebutkan” berarti informasi tidak tersedia dalam bahan, bukan tidak pernah diteliti.</caption><thead><tr class="bg-paper">{#each ['Artikel / dasar pembacaan', 'Fokus & konteks', 'Metode', 'Temuan utama', 'Keterbatasan'] as heading}<th scope="col" class="border-y border-line px-4 py-3 font-semibold">{heading}</th>{/each}</tr></thead><tbody>{#each analysis.matrix as row (row.reference_id)}{@const source = sourceFor(row.reference_id)}<tr class="border-b border-line last:border-b-0"><th scope="row" class="max-w-64 px-4 py-4 align-top font-normal"><a href={source?.url} target="_blank" rel="noopener noreferrer" class="font-semibold text-primary underline">{source?.title}</a><p class="mt-2 text-xs text-ink-3">{source?.citation}</p><p class="mt-2 text-xs text-ink-3">{source?.basis}</p></th><td class="max-w-64 whitespace-pre-line px-4 py-4 align-top">{row.focus}<p class="mt-2 text-ink-2">{row.context}</p></td><td class="max-w-56 whitespace-pre-line px-4 py-4 align-top">{row.method}</td><td class="max-w-64 whitespace-pre-line px-4 py-4 align-top">{row.findings}</td><td class="max-w-64 whitespace-pre-line px-4 py-4 align-top">{row.limitations}</td></tr>{/each}</tbody></table>
            </div>
            {#each analysis.sources as source (source.id)}<details class="rounded-lg border border-line px-4 py-3"><summary class="cursor-pointer text-sm font-medium">Periksa catatan: {source.title}</summary><p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink-2">{source.notes}</p></details>{/each}
        </section>
        <section class="flex flex-col gap-5" aria-labelledby="candidates-title">
            <h2 id="candidates-title" class="font-display text-2xl sm:text-3xl">Kandidat gap untuk ditinjau</h2>
            {#each analysis.candidates as candidate, index (index)}
                <article class="card flex flex-col gap-4 p-5 sm:p-6">
                    <div><span class="badge bg-ai-wash text-ai">{candidate.type === 'author_limitations' ? 'Berangkat dari keterbatasan penulis' : 'Interpretasi perbandingan AI'}</span><h3 class="mt-3 font-display text-2xl">{index + 1}. {candidate.title}</h3></div>
                    <p class="whitespace-pre-line text-sm leading-relaxed">{candidate.gap} {#each candidate.source_ids as id (id)}<a href={sourceFor(id)?.url} target="_blank" rel="noopener noreferrer" class="text-primary underline">({sourceFor(id)?.citation})</a>{/each}</p>
                    <dl class="grid gap-3 text-sm"><div><dt class="font-semibold">Mengapa penting</dt><dd class="mt-1 text-ink-2">{candidate.importance}</dd></div><div><dt class="font-semibold">Usulan pertanyaan penelitian</dt><dd class="mt-1 text-ink-2">{candidate.question}</dd></div><div><dt class="font-semibold">Kontribusi yang diharapkan</dt><dd class="mt-1 text-ink-2">{candidate.contribution}</dd></div><div><dt class="font-semibold">Verifikasi lanjutan</dt><dd class="mt-1 text-ink-2">{candidate.verification}</dd></div></dl>
                    <details class="rounded-lg border border-ai-line bg-ai-wash px-4 py-3"><summary class="cursor-pointer text-sm font-semibold text-ai">Lihat bukti dari {candidate.source_ids.length} sumber</summary><p class="mt-3 text-xs text-ink-3">Cuplikan berikut berasal dari catatan tersimpan, bukan kutipan langsung artikel. Periksa artikel asli.</p>{#each candidate.evidence as evidence, evidenceIndex (evidenceIndex)}<blockquote class="mt-4 border-l-2 border-ai-line pl-3 text-sm"><p class="whitespace-pre-line">{evidence.quote}</p><cite class="mt-2 block text-xs not-italic text-ink-3">{sourceFor(evidence.reference_id)?.citation}</cite></blockquote>{/each}</details>
                    <div class="flex flex-wrap gap-3"><button type="button" class="btn btn-primary" disabled={analysisStale || choice.processing} onclick={() => choose(index)}>Tinjau & pilih gap ini</button><a class="btn btn-secondary" href={`https://scholar.google.com/scholar?q=${encodeURIComponent(candidate.question)}`} target="_blank" rel="noopener noreferrer">Telusuri di Google Scholar<Icon name="external" size={14} /></a></div>
                </article>
            {:else}<div class="alert alert-info" role="status"><p>AI belum menemukan kandidat yang cukup didukung bahan ini. Lengkapi catatan atau tambahkan sumber relevan, lalu analisis kembali.</p></div>{/each}
        </section>
    {/if}

    {#if editing}
        <form class="card flex flex-col gap-5 p-5 sm:p-6" onsubmit={save}>
            <h2 id="gap-choice" tabindex="-1" class="font-display text-2xl">Tinjau arah penelitian</h2>
            <p class="help">Sunting rumusan sesuai kebutuhan Anda. Memilih kandidat tidak berarti kebaruannya sudah terbukti.</p>
            {#if analysis && choice.analysis_id !== analysis.id}<p class="error" role="status">Analisis baru sudah tersedia. Teks suntingan tetap di sini untuk disalin; pilih kandidat dari hasil terbaru untuk menyimpan.</p>{/if}
            {#if Object.keys(choice.errors).length}<div class="alert alert-danger" role="alert"><ul>{#each Object.values(choice.errors) as message}<li>{message}</li>{/each}</ul></div>{/if}
            <div class="field"><label for="chosen-gap" class="label">Rumusan gap</label><textarea id="chosen-gap" class="input min-h-32" bind:value={choice.gap} maxlength="4000" required></textarea></div>
            <div class="field"><label for="chosen-question" class="label">Pertanyaan penelitian</label><textarea id="chosen-question" class="input min-h-24" bind:value={choice.question} maxlength="2000" required></textarea></div>
            <div class="field"><label for="chosen-contribution" class="label">Kontribusi yang diharapkan</label><textarea id="chosen-contribution" class="input min-h-24" bind:value={choice.contribution} maxlength="2000" required></textarea></div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="mt-0.5 size-4.5 shrink-0 accent-primary" bind:checked={choice.reviewed} /><span>Saya sudah meninjau rumusan dan bukti sumber. Kebaruan masih perlu diperiksa melalui pencarian lanjutan.</span></label>
            <div class="flex flex-wrap gap-3"><button class="btn btn-primary" disabled={choice.processing || !choice.reviewed || analysisStale || choice.analysis_id !== analysis?.id}>{choice.processing ? 'Menyimpan…' : 'Simpan sebagai arah penelitian'}</button><button type="button" class="btn btn-secondary" disabled={choice.processing} onclick={() => (editing = false)}>Batal</button></div>
        </form>
    {/if}
</ProjectLayout>
