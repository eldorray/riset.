<script lang="ts">
    import { Link, page, router, useHttp } from '@inertiajs/svelte';
    import { onDestroy, untrack } from 'svelte';
    import { createWriting, type WritingSuggestion } from '@/lib/writing.svelte';
    import Icon from '@/components/Icon.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import { errorMessage } from '@/lib/format';
    import { creditLabel, estimateCredits } from '@/lib/credits';
    import { unitBlocked } from '@/lib/outline';
    import projects from '@/routes/projects';
    import type { ProjectSummary, Unit } from '@/types';

    type Reference = { id: number; title: string; source_url: string; in_text: string | null; has_notes: boolean; notes_pending: boolean; note_chars: number; keywords: string[] };

    let {
        project,
        units,
        draft,
        aiUnits,
        references,
        selectedUnit,
    }: { project: ProjectSummary; units: Unit[]; draft: Record<string, string>; aiUnits: string[]; references: Reference[]; selectedUnit: string | null } = $props();

    const user = $derived(page.props.auth.user);
    let reviewed = $state<string[]>([]);
    let localAiUnits = $state<string[]>([]);
    const unreviewed = (id: string) => localAiUnits.includes(id) && !reviewed.includes(id) && texts[id] === (baseline[id] ?? '');

    let texts = $state<Record<string, string>>({});
    let baseline = $state<Record<string, string>>({});
    const dirty = $derived(Object.keys(texts).some((id) => texts[id] !== (baseline[id] ?? '')) || reviewed.length > 0);
    let saving = $state(false);
    let saveError = $state('');
    let conflict = $state(false);
    let initializedProject = 0;
    let activeId = $state('');
    let tab = $state<'tulis' | 'pratinjau'>('tulis');
    let selected = $state<number[]>([]);
    const writing = createWriting(() => project.id);
    const suggestions = $derived(Object.fromEntries(writing.suggestions.filter((s) => s.type === 'draft').map((s) => [s.key, s])) as Record<string, WritingSuggestion>);
    let localFailures = $state<Record<string, string>>({});
    const failures = $derived({ ...Object.fromEntries((writing.run?.results ?? []).filter((r) => r.status === 'failed').map((r) => [r.key, r.error ?? 'Penulisan gagal.'])), ...localFailures });
    const generatingId = $derived(writing.active && writing.run?.kind.startsWith('draft') ? writing.run.key : null);
    const generatingSources = $derived(writing.run?.references_count ?? 0);
    let elapsedSeconds = $state(0);

    $effect(() => {
        if (generatingId === null) return;
        elapsedSeconds = 0;
        const started = Date.now();
        const timer = setInterval(() => {
            elapsedSeconds = Math.floor((Date.now() - started) / 1000);
        }, 1000);
        return () => clearInterval(timer);
    });
    const bulk = $derived(writing.active && writing.run?.kind === 'draft_all' ? writing.run : null);
    const bulkNotice = $derived(writing.error || (writing.run?.kind.startsWith('draft') ? writing.run.error ?? '' : ''));
    let editor: HTMLTextAreaElement | undefined = $state();

    $effect(() => {
        const id = project.id;
        untrack(() => {
            if (initializedProject === id) return;
            initializedProject = id;
            texts = Object.fromEntries(units.map((u) => [u.id, draft[u.id] ?? '']));
            baseline = { ...texts };
            localAiUnits = [...aiUnits];
            reviewed = [];
            saveError = '';
            conflict = false;
        });
    });

    $effect(() => {
        const changed = Object.entries(texts).filter(([id, text]) => text !== (baseline[id] ?? ''));
        const pending = reviewed.length;
        if ((!changed.length && !pending) || saving || saveError) return;
        const timer = setTimeout(() => void save(), 900);
        return () => clearTimeout(timer);
    });

    $effect(() => {
        if (!units.some((u) => u.id === activeId)) {
            activeId = units.find((u) => u.id === selectedUnit)?.id ?? (units.find((u) => !draft[u.id]?.trim()) ?? units[0])?.id ?? '';
        }
    });

    // Pilihan awal: referensi yang punya catatan isi. Tidak di-reset saat props dimuat ulang.
    let selectionReady = false;
    $effect(() => {
        if (!selectionReady) {
            selected = references.filter((r) => r.has_notes).slice(0, 20).map((r) => r.id);
            selectionReady = true;
        }
    });

    const tabs = ['tulis', 'pratinjau'] as const;

    const active = $derived(units.find((u) => u.id === activeId));
    const byId = $derived(new Map(references.map((r) => [r.id, r])));
    const emptyUnits = $derived(units.filter((u) => !texts[u.id]?.trim() && !suggestions[u.id] && !unitBlocked(u, project)));
    const blocked = $derived(active ? unitBlocked(active, project) : null);
    const sourceCharacters = $derived(references.filter(r => selected.includes(r.id)).reduce((sum, r) => sum + r.note_chars, 0) + 3000);
    const draftCost = $derived(creditLabel(estimateCredits(sourceCharacters, 500)));
    const bulkCost = $derived(creditLabel(estimateCredits(sourceCharacters, 500, emptyUnits.length)));
    const activeIndex = $derived(units.findIndex((u) => u.id === activeId));
    const activeCitations = $derived([...new Set([...(texts[activeId] ?? '').matchAll(/\[@(\d+)\]/g)].map((m) => Number(m[1])))].map((id) => ({ id, label: byId.get(id)?.in_text ?? (byId.has(id) ? 'metadata belum lengkap' : 'referensi tidak dikenal') })));
    const statusText = { isi: 'berisi', ai: 'hasil AI belum ditinjau', error: 'gagal dibuat', kosong: 'kosong', terkunci: 'butuh rancangan atau data', loading: 'sedang dibuat' } as const;
    let copyNotice = $state('');
    const words = $derived((texts[activeId] ?? '').trim().split(/\s+/).filter(Boolean).length);

    const citationCounts = $derived.by(() => {
        const counts = new Map<number, number>();
        for (const unit of units) {
            for (const match of (texts[unit.id] ?? '').matchAll(/\[@(\d+)\]/g)) {
                const id = Number(match[1]);
                counts.set(id, (counts.get(id) ?? 0) + 1);
            }
        }
        return counts;
    });
    const citationTotal = $derived([...citationCounts.values()].reduce((sum, count) => sum + count, 0));
    const referenceGroups = $derived.by(() => {
        const keywords = [...new Set(references.flatMap((r) => r.keywords))].sort((a, b) => a.localeCompare(b, 'id'));
        const groups = keywords.map((keyword) => ({ keyword, items: references.filter((r) => r.keywords.includes(keyword)) }));
        const uncategorized = references.filter((r) => !r.keywords.length);
        if (uncategorized.length) groups.push({ keyword: 'Belum diberi kata kunci', items: uncategorized });
        return groups;
    });

    const saver = useHttp<{ draft: Record<string, string>; base: Record<string, string>; reviewed: string[] }, { draft: Record<string, string>; ai_units: string[]; project: ProjectSummary }>({ draft: {}, base: {}, reviewed: [] });

    // Peringatan bila keluar halaman dengan perubahan belum disimpan.
    const offBefore = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (hasUnsaved() && visit.method === 'get' && !confirm('Ada perubahan yang belum disimpan. Tinggalkan halaman?')) {
            event.preventDefault();
        }
    });
    onDestroy(offBefore);

    function hasUnsaved(): boolean {
        return dirty || saving;
    }

    function beforeUnload(event: BeforeUnloadEvent) {
        if (hasUnsaved()) {
            event.preventDefault();
        }
    }

    async function generate(unitId: string, sourceIds: number[] = $state.snapshot(selected)): Promise<void> {
        delete localFailures[unitId];
        await save();
        if (dirty || saveError || saving) { localFailures[unitId] = 'Simpan perubahan draf terlebih dahulu.'; return; }
        try { await writing.start({ kind: 'draft', unit: unitId, references: sourceIds }); }
        catch (error) { localFailures[unitId] = (error as Error).message; }
    }

    async function generateAll() {
        await save();
        if (dirty || saveError || saving) return;
        try { await writing.start({ kind: 'draft_all', units: emptyUnits.map((u) => u.id), references: $state.snapshot(selected) }); }
        catch (error) { localFailures[activeId] = (error as Error).message; }
    }

    async function accept(unitId: string) {
        await save();
        if (dirty || saveError || saving) return false;
        try {
            const result = await writing.review(suggestions[unitId], 'accept');
            texts[unitId] = result.draft[unitId];
            baseline[unitId] = result.draft[unitId];
            localAiUnits = result.ai_units;
            delete localFailures[unitId];
            return true;
        } catch (error) { localFailures[unitId] = (error as Error).message; return false; }
    }

    async function discard(unitId: string) {
        try { await writing.review(suggestions[unitId], 'discard'); }
        catch (error) { localFailures[unitId] = (error as Error).message; }
    }

    async function stopWriting() {
        try { await writing.stop(); }
        catch (error) { localFailures[activeId] = (error as Error).message; }
    }

    async function acceptToEdit(unitId: string) {
        if (!await accept(unitId)) return;
        activeId = unitId;
        tab = 'tulis';
        queueMicrotask(() => editor?.focus());
    }

    function insertCitation(id: number) {
        const marker = `[@${id}]`;
        const text = texts[activeId] ?? '';
        const at = editor?.selectionEnd ?? text.length;
        texts[activeId] = text.slice(0, at) + marker + text.slice(at);
        tab = 'tulis';
        queueMicrotask(() => {
            editor?.focus();
            editor?.setSelectionRange(at + marker.length, at + marker.length);
        });
    }

    async function save() {
        if (saving || !dirty || conflict) return;
        const changed = Object.fromEntries(Object.entries($state.snapshot(texts)).filter(([id, text]) => text !== (baseline[id] ?? '')));
        const checked = [...reviewed];
        const ids = [...new Set([...Object.keys(changed), ...checked])];
        const projectId = project.id;
        saver.draft = changed;
        saver.base = Object.fromEntries(ids.map((id) => [id, baseline[id] ?? '']));
        saver.reviewed = checked;
        saving = true;
        saveError = '';
        try {
            const result = await saver.put(projects.draft.update(projectId).url);
            if (!result) throw new Error(Object.values(saver.errors)[0] ?? 'Permintaan tidak valid.');
            if (project.id !== projectId) return;
            baseline = { ...baseline, ...result.draft };
            localAiUnits = result.ai_units;
            project = result.project;
            reviewed = reviewed.filter((id) => !checked.includes(id));
        } catch (error) {
            if (project.id !== projectId) return;
            const status = (error as { response?: { status?: number } }).response?.status;
            conflict = status === 409;
            saveError = error instanceof Error && !status ? error.message : errorMessage(error, 'Gagal menyimpan. Teks tetap di editor.');
        } finally {
            saving = false;
        }
    }

    async function copyLocal() {
        try {
            await navigator.clipboard.writeText(units.map((u) => `${u.number} ${u.title}\n${texts[u.id] ?? ''}`).join('\n\n'));
            copyNotice = 'Tulisan lokal disalin ke clipboard.';
        } catch {
            copyNotice = 'Gagal menyalin. Salin teks langsung dari editor sebelum memuat ulang.';
        }
    }

    function selectUnit(id: string) {
        activeId = id;
        tab = 'tulis';
    }

    /** Pratinjau: penanda [@id] berdampingan → satu kurung, tanpa HTML mentah. */
    function preview(text: string): { cite: boolean; value: string; sources?: { id: number; label: string; title: string }[] }[][] {
        return text
            .split(/\n\s*\n/)
            .filter((p) => p.trim())
            .map((paragraph) =>
                paragraph.split(/((?:\[@\d+\]\s*)*\[@\d+\])/).filter(Boolean).map((part) => {
                    const ids = [...part.matchAll(/\[@(\d+)\]/g)].map((m) => Number(m[1]));

                    if (!ids.length || !/^(\[@\d+\]\s*)+$/.test(part)) {
                        return { cite: false, value: part };
                    }

                    const labels = ids.map((id) => byId.get(id)?.in_text ?? `[belum lengkap: ${byId.get(id)?.title ?? 'tidak dikenal'}]`);

                    return { cite: true, value: `(${labels.join('; ')})`, sources: ids.map((id, index) => ({ id, label: labels[index], title: byId.get(id)?.title ?? 'Referensi tidak dikenal' })) };
                }),
            );
    }

    function status(unit: Unit): 'loading' | 'error' | 'ai' | 'isi' | 'kosong' | 'terkunci' {
        if (generatingId === unit.id) return 'loading';
        if (failures[unit.id]) return 'error';
        if (suggestions[unit.id] || unreviewed(unit.id)) return 'ai';
        if (texts[unit.id]?.trim()) return 'isi';
        return unitBlocked(unit, project) ? 'terkunci' : 'kosong';
    }


</script>

<svelte:window onbeforeunload={beforeUnload} />

<ProjectLayout {project} active="draf" title="Draf">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-5">
        <div class="flex flex-col gap-1.5">
            <span class="eyebrow">Proyek / Draf</span>
            <h1 class="font-display text-4xl leading-tight font-medium">Draf</h1>
            <p class="text-sm text-ink-2">Pilih satu bagian, pilih sumber, lalu tinjau usulan AI. Edit dan teks yang diterima tersimpan otomatis.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-1.5 text-[13px] {dirty ? 'font-medium text-warn' : 'text-ink-2'}" role="status">
                {#if saving}Menyimpan…{:else if saveError}Gagal menyimpan{:else if dirty}Menunggu autosave…{:else}<Icon name="check" size={14} class="text-ok" /> Tersimpan{/if}
            </span>
    {#if bulk}
                <button type="button" class="btn btn-secondary" onclick={stopWriting} disabled={bulk.stop_requested}>
                    {bulk.stop_requested ? 'Menghentikan…' : 'Hentikan'}
                </button>
            {:else if emptyUnits.length}
                <button type="button" class="btn btn-secondary" onclick={generateAll} disabled={writing.busy}>
                    <span class="rounded-full border border-current px-1.5 font-mono text-[10px] leading-3.5">AI</span>
                    Buat semua bagian kosong ({emptyUnits.length})
                    {#if !user?.unlimited}<span class="rounded-full bg-primary-soft px-2 py-0.5 text-xs font-medium text-primary">{bulkCost}</span>{/if}
                </button>
            {/if}
        </div>
    </header>

    {#if bulk}
        <div class="card flex flex-col gap-2.5 px-5 py-4" role="status" aria-live="polite">
            <div class="flex items-center gap-2.5">
                <Icon name="spinner" class="text-ai" />
                <span class="font-semibold text-ai">Menulis bagian {Math.min(bulk.done + 1, bulk.total)} dari {bulk.total}…</span>
                <span class="text-[13px] text-ink-2">Dengan {generatingSources} referensi terpilih. Hasil tiap bagian perlu Anda tinjau sebelum disimpan.</span>
            </div>
            <progress class="h-2 w-full accent-ai" value={bulk.done} max={Math.max(bulk.total, 1)} aria-label="Progres penulisan seluruh bagian"></progress>
            <p class="text-xs text-ink-2">{bulk.done} dari {bulk.total} bagian diproses · {Math.round((bulk.done / Math.max(bulk.total, 1)) * 100)}%</p>
        </div>
    {/if}
    {#if bulkNotice}
        <div class="alert alert-danger" role="alert">
            <Icon name="error" class="text-danger" />
            <p class="grow">{bulkNotice}</p>

        </div>
    {/if}
    {#if saveError}
        <div class="alert alert-danger" role="alert"><Icon name="error" class="text-danger" /><div class="flex flex-col gap-2"><p>{saveError}</p><div class="flex flex-wrap gap-2"><button class="btn btn-secondary" type="button" onclick={copyLocal}>Salin tulisan lokal</button>{#if conflict}<button type="button" class="btn btn-secondary" onclick={() => { if (confirm('Muat versi server? Perubahan lokal akan dibuang. Salin tulisan Anda lebih dulu.')) location.reload(); }}>Muat versi server</button>{:else}<button type="button" class="btn btn-secondary" onclick={() => void save()}>Coba simpan lagi</button>{/if}</div>{#if copyNotice}<p class="text-sm" role="status">{copyNotice}</p>{/if}</div></div>
    {/if}

    {#if units.length === 0}
        <div class="flex flex-col items-start gap-3 rounded-[10px] border border-dashed border-line-strong p-10">
            <h2 class="font-display text-[26px] font-medium">Kerangka belum disimpan</h2>
            <p class="text-[15px] text-ink-2">Draf ditulis per bagian kerangka. Susun dan simpan kerangka lebih dulu.</p>
            <a href={projects.outline(project.id).url} class="btn btn-primary">Buka kerangka</a>
        </div>
    {:else}
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[210px_minmax(0,1fr)] xl:grid-cols-[210px_minmax(0,1fr)_300px]">
            <div class="sticky top-(--mobile-header-h) z-20 -mx-4 flex items-center gap-2 border-b border-line bg-paper px-4 py-2 sm:-mx-6 sm:px-6 lg:hidden">
                <button type="button" class="btn btn-secondary btn-icon shrink-0" aria-label="Bagian sebelumnya" disabled={activeIndex <= 0} onclick={() => selectUnit(units[activeIndex - 1].id)}><Icon name="back" /></button>
                <label for="unit-select" class="sr-only">Pilih bagian</label>
                <select id="unit-select" class="input min-w-0 grow font-semibold" value={activeId} onchange={(event) => selectUnit(event.currentTarget.value)}>
                    {#each units as unit (unit.id)}<option value={unit.id}>{unit.number} {unit.title} · {statusText[status(unit)]}</option>{/each}
                </select>
                <button type="button" class="btn btn-secondary btn-icon shrink-0" aria-label="Bagian berikutnya" disabled={activeIndex >= units.length - 1} onclick={() => selectUnit(units[activeIndex + 1].id)}><Icon name="next" /></button>
            </div>
            <nav aria-label="Bagian dokumen" class="hidden flex-col gap-0.5 lg:sticky lg:top-6 lg:flex">
                {#each units as unit (unit.id)}
                    {@const s = status(unit)}
                    <button
                        type="button"
                        aria-current={unit.id === activeId ? 'location' : undefined}
                        onclick={() => selectUnit(unit.id)}
                        class="flex min-h-11 items-center gap-2.5 rounded-md px-2 text-left text-[13px] leading-tight {unit.id === activeId
                            ? 'bg-surface font-semibold shadow-[0_0_0_1px_var(--color-line)]'
                            : 'text-ink-2 hover:bg-surface/60'} {unit.level === 1 ? 'mt-2' : ''}"
                    >
                        {#if s === 'loading'}
                            <Icon name="spinner" size={10} class="text-ai" />
                        {:else}
                            <span
                                aria-hidden="true"
                                class="size-2 shrink-0 rounded-full border-[1.5px] {s === 'isi' ? 'border-ink bg-ink' : s === 'ai' ? 'border-ai bg-ai' : s === 'error' ? 'border-danger bg-danger' : s === 'terkunci' ? 'border-dashed border-warn' : 'border-line-strong'}"
                            ></span>
                        {/if}
                        <span class="grow"><span class="font-mono text-[11px] text-ink-3">{unit.number}</span> {unit.title}</span>
                        <span class="sr-only">{statusText[s]}</span>
                    </button>
                {/each}
                <div class="mt-3 flex flex-col gap-1.5 border-t border-line px-2 pt-3 text-xs text-ink-2">
                    <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-ink"></span>Berisi</span>
                    <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-ai"></span>Hasil AI belum ditinjau</span>
                    <span class="flex items-center gap-2"><span class="size-2 rounded-full border-[1.5px] border-line-strong"></span>Kosong</span>
                    {#if units.some((u) => unitBlocked(u, project))}<span class="flex items-center gap-2"><span class="size-2 rounded-full border-[1.5px] border-dashed border-warn"></span>Butuh rancangan/data</span>{/if}
                    {#if Object.keys(failures).length}<span class="flex items-center gap-2"><span class="size-2 rounded-full bg-danger"></span>Gagal dibuat</span>{/if}
                </div>
            </nav>

            {#if active}
                <article class="card flex min-w-0 flex-col">
                    <div role="toolbar" aria-label="Alat penulisan" class="flex flex-wrap items-center gap-1 border-b border-sunken px-3 py-1.5">
                        <div class="flex rounded-md bg-sunken p-0.5">
                            {#each tabs as t (t)}
                                <button type="button" class="min-h-11 rounded px-3 text-[13px] font-semibold {tab === t ? 'bg-surface shadow-sm' : 'text-ink-2'}" aria-pressed={tab === t} onclick={() => (tab = t)}>
                                    {t === 'tulis' ? 'Tulis' : 'Pratinjau'}
                                </button>
                            {/each}
                        </div>
                        <span class="ml-auto font-mono text-xs text-ink-3">{active.number}</span>
                    </div>

                    <div class="flex flex-col gap-5 px-4 py-5 sm:px-8 sm:py-7">
                        <h2 class="font-display text-[28px] leading-tight font-medium">{active.number} {active.title}</h2>

                        {#if blocked && !texts[activeId]?.trim()}
                            <div class="alert alert-warn" role="note">
                                <Icon name="warn" class="text-warn" />
                                <div class="flex flex-col items-start gap-2">
                                    <p>{blocked} Anda tetap bisa menulis sendiri di sini.</p>
                                    <Link href={`/projects/${project.id}/rancangan${active?.kind === 'empiris' ? '#data' : ''}`} class="btn btn-secondary">Buka Rancangan penelitian</Link>
                                </div>
                            </div>
                        {/if}

                        {#if unreviewed(activeId)}
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ai-line bg-ai-wash px-4 py-2.5" role="status">
                                <span class="flex items-center gap-2 text-[13px] font-semibold text-ai">
                                    <span class="rounded-full border border-ai px-1.5 font-mono text-[10px] leading-3.5">AI</span> Ditulis AI · belum ditinjau — periksa isi dan sitasinya
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick={() => {
                                        reviewed.push(activeId);
                                        save();
                                    }}>Tandai sudah ditinjau</button
                                >
                            </div>
                        {/if}

                        {#if tab === 'tulis'}
                            <label for="editor" class="sr-only">Teks bagian {active.number} {active.title}</label>
                            <textarea
                                id="editor"
                                bind:this={editor}
                                bind:value={texts[activeId]}
                                
                                rows="14"
                                class="input min-h-80 resize-y border-line font-display text-lg leading-[30px]"
                                placeholder="Tulis di sini, atau minta AI membuat draf bagian ini. Pisahkan paragraf dengan baris kosong."
                            ></textarea>
                            {#if activeCitations.length}
                                <p class="help -mt-2"><span class="font-semibold text-ink">Sitasi di bagian ini:</span> {#each activeCitations as citation, i (citation.id)}{#if i}{'; '}{/if}<span class="font-mono text-xs">[@{citation.id}]</span> {citation.label}{/each}</p>
                            {:else}
                                <p class="help -mt-2">Sitasi tertulis sebagai <span class="font-mono text-xs">[@id]</span> dan dirender sesuai gaya sitasi saat pratinjau dan ekspor.</p>
                            {/if}
                        {:else if texts[activeId]?.trim()}
                            <div class="flex flex-col gap-4 font-display text-lg leading-[30px]">
                                {#each preview(texts[activeId]) as paragraph, p (p)}
                                    <p>{#each paragraph as part, k (k)}{#if part.cite}<span class="cite">({#each part.sources ?? [] as source, index}{#if index}; {/if}<a href={`${projects.references.index(project.id).url}?edit=${source.id}`} title={source.title} class="underline decoration-dotted underline-offset-2">{source.label}</a>{/each})</span>{:else}{part.value}{/if}{/each}</p>
                                {/each}
                            </div>
                        {:else}
                            <p class="text-sm text-ink-2">Bagian ini masih kosong.</p>
                        {/if}

                        {#if generatingId === activeId}
                            <div class="flex flex-col gap-3 rounded-[10px] border border-dashed border-ai-line bg-ai-wash p-4.5" role="status" aria-live="polite">
                                <span class="flex items-center gap-2.5 text-sm font-semibold text-ai"><Icon name="spinner" /> Menulis bagian {active.number} · {active.title}</span>
                                <progress class="h-2 w-full accent-ai" aria-label="AI sedang menyusun draf bagian {active.number}"></progress>
                                <p class="text-[13px] text-ink-2">Menggunakan {generatingSources} referensi terpilih. Menunggu hasil pembacaan sumber dan penulisan draf.</p>
                                <p class="font-mono text-xs text-ink-2" aria-live="off">Sudah berjalan {Math.floor(elapsedSeconds / 60)} menit {elapsedSeconds % 60} detik.</p>
                                <p class="text-[13px] text-ink-2">Teks di atas tetap bisa Anda edit selama proses berjalan.</p>
                            </div>
                        {/if}

                        {#if failures[activeId]}
                            <div class="alert alert-danger" role="alert">
                                <Icon name="error" class="text-danger" />
                                <div class="flex flex-col items-start gap-2.5">
                                    <p><strong class="font-semibold">Generasi draf gagal.</strong> {failures[activeId]} Anda tetap dapat menulis manual.</p>
                                    <button type="button" class="btn btn-secondary" onclick={() => generate(activeId)} disabled={writing.busy}>Coba lagi</button>
                                </div>
                            </div>
                        {/if}

                        {#if suggestions[activeId]}
                            {@const suggestion = suggestions[activeId]}
                            <section aria-label="Teks dihasilkan AI" class="flex flex-col rounded-[10px] border border-ai-line bg-ai-wash">
                                <div class="flex items-center gap-2 border-b border-ai-line/60 px-3.5 py-2.5">
                                    <span class="rounded-full border border-ai px-1.5 font-mono text-[11px] leading-3.5 text-ai">AI</span>
                                    <span class="text-[13px] font-semibold text-ai">Usulan AI · belum ditinjau</span>
                                </div>
                                <div class="flex flex-col gap-3.5 px-4.5 py-4">
                                    {#each preview(suggestion.text) as paragraph, p (p)}
                                        <p class="font-display text-lg leading-[30px]">{#each paragraph as part, k (k)}{#if part.cite}<span class="cite">({#each part.sources ?? [] as source, index}{#if index}; {/if}<a href={`${projects.references.index(project.id).url}?edit=${source.id}`} title={source.title} class="underline decoration-dotted underline-offset-2">{source.label}</a>{/each})</span>{:else}{part.value}{/if}{/each}</p>
                                    {/each}
                                    {#if suggestion.evidence?.length}
                                        <details class="rounded-lg border border-ai-line bg-surface px-3.5 py-2.5 text-sm">
                                            <summary class="cursor-pointer font-semibold">Bukti sitasi dari catatan ({suggestion.evidence.length})</summary>
                                            <ul class="mt-2 flex flex-col gap-2">
                                                {#each suggestion.evidence as item, i (i)}<li><span class="font-mono text-xs">[@{item.id}]</span> {byId.get(item.id)?.in_text ?? byId.get(item.id)?.title ?? 'Referensi'} — <span class="text-ink-2">“{item.quote}”</span></li>{/each}
                                            </ul>
                                        </details>
                                    {/if}
                                    <div class="flex flex-col gap-1 text-sm"><span class="font-semibold">Referensi dikirim ke AI</span>{#each suggestion.sourceIds ?? [] as id (id)}<a href={`${projects.references.index(project.id).url}?edit=${id}`} class="text-primary underline">{byId.get(id)?.title ?? `Referensi ${id}`}</a>{:else}<span class="text-warn">Tanpa referensi · periksa klaim dan tambahkan sumber.</span>{/each}</div>
                                    {#if suggestion.limitations}
                                        <div class="flex items-start gap-2.5 rounded-lg border border-ai-line bg-surface px-3.5 py-3" role="note">
                                            <Icon name="info" class="mt-px text-ai" />
                                            <p class="text-[13px] leading-normal"><strong class="font-semibold">Keterbatasan sumber:</strong> {suggestion.limitations}</p>
                                        </div>
                                    {/if}
                                    <div class="flex flex-wrap gap-2.5">
                                        <button type="button" class="btn btn-primary" onclick={() => accept(activeId)}>Pakai usulan</button>
                                        <button type="button" class="btn btn-secondary" onclick={() => acceptToEdit(activeId)}>Pakai lalu edit</button>
                                        <button type="button" class="btn btn-ghost text-danger" onclick={() => discard(activeId)}>Buang</button>
                                    </div>
                                </div>
                            </section>
                        {/if}
                    </div>

                    <div class="mt-auto flex justify-between gap-3 border-t border-sunken px-4 py-3 sm:px-8 font-mono text-xs text-ink-3">
                        <span>{words} kata di bagian ini</span>
                        <span>{(texts[activeId]?.match(/\[@\d+\]/g) ?? []).length} sitasi</span>
                    </div>
                </article>
            {/if}

            <aside id="sumber-draf" class="flex scroll-mt-36 flex-col gap-4 lg:col-span-2 xl:sticky xl:top-6 xl:col-span-1">
                <section class="card flex flex-col gap-3 px-4.5 py-4">
                    <fieldset class="flex flex-col">
                        <legend class="section-label mb-1.5">Referensi untuk AI <span class="font-mono text-xs text-ink-2">· {citationTotal} sitasi</span></legend>
                        <p class="mb-3 text-xs text-ink-2">{selected.length}/20 referensi dipilih · Hanya sumber dengan catatan yang sudah ditinjau yang bisa dipakai AI. Jumlah sitasi dihitung dari seluruh draf di editor.</p>
                        <a href={projects.references.index(project.id).url} class="mb-3 text-xs text-primary underline">Tambah referensi / atur kata kunci</a>
                        {#if references.length === 0}
                            <p class="text-[13px] leading-normal text-ink-2">Belum ada referensi. AI tetap bisa menulis, tetapi tanpa sitasi dan akan menyatakan keterbatasannya.</p>
                        {/if}
                        {#each referenceGroups as group (group.keyword)}
                            <details class="mt-2 border-t border-sunken pt-3">
                                <summary class="cursor-pointer rounded py-2 text-xs font-semibold text-ink-2 focus-visible:outline-2 focus-visible:outline-primary">{group.keyword} ({group.items.length}) <span class="font-normal">· {group.items.filter((r) => selected.includes(r.id)).length} dipilih</span></summary>
                                <div class="flex flex-wrap gap-2 pb-2">
                                    <button type="button" class="btn btn-ghost min-h-10 px-2 text-xs" aria-label="Pilih semua referensi kategori {group.keyword}" disabled={writing.busy || selected.length >= 20 || group.items.every((r) => !r.has_notes || selected.includes(r.id))} onclick={() => selected = [...new Set([...selected, ...group.items.filter((r) => r.has_notes).map((r) => r.id)])].slice(0, 20)}>Pilih semua</button>
                                    <button type="button" class="btn btn-ghost min-h-10 px-2 text-xs" aria-label="Batalkan pilihan kategori {group.keyword}" disabled={writing.busy || !group.items.some((r) => selected.includes(r.id))} onclick={() => selected = selected.filter((id) => !group.items.some((r) => r.id === id))}>Batalkan pilihan</button>
                                </div>
                                {#each group.items as reference (reference.id)}
                                    <div class="flex items-center gap-1">
                                        <label class="flex min-h-11 min-w-0 grow items-center gap-2.5 py-2 text-[13px] leading-snug">
                                            <input type="checkbox" value={reference.id} bind:group={selected} disabled={writing.busy || !reference.has_notes || (selected.length >= 20 && !selected.includes(reference.id))} class="size-4.5 shrink-0 accent-primary" />
                                            <span class="flex min-w-0 flex-col gap-0.5">
                                                <span>{reference.in_text ?? reference.title}</span>
                                                {#if reference.in_text}<span class="text-xs text-ink-2">{reference.title}</span>{/if}
                                                <span class="font-mono text-[11px] text-ink-2">{citationCounts.get(reference.id) ?? 0} sitasi</span>
                                                {#if !reference.in_text}<span class="text-[11px] font-medium text-warn">metadata belum lengkap</span>{/if}
                                                {#if !reference.has_notes}<a href={`${projects.references.index(project.id).url}?edit=${reference.id}&notes=1`} class="text-[11px] text-primary underline">{reference.notes_pending ? 'Tinjau catatan AI agar bisa dipakai' : 'Isi catatan agar AI bisa menyitasi'}</a>{/if}
                                            </span>
                                        </label>
                                        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-2" aria-label="Sisipkan sitasi {reference.in_text ?? reference.title}" title="Sisipkan sitasi di posisi kursor" onclick={() => insertCitation(reference.id)}>
                                            <Icon name="plus" size={16} />
                                        </button>
                                    </div>
                                {/each}
                            </details>
                        {/each}
                    </fieldset>
                    <AiCost inputCharacters={sourceCharacters} outputWords={500} detail="Perkiraan untuk satu bagian draf sekitar 500 kata." />
                    <button type="button" class="btn btn-primary hidden lg:inline-flex" onclick={() => generate(activeId)} disabled={writing.busy || !active || !!suggestions[activeId] || !!blocked}>
                        <span class="rounded-full border border-white px-1.5 font-mono text-[10px] leading-3.5">AI</span>
                        Buat draf {active?.number ?? ''}
                    </button>
                    <p class="text-xs leading-normal text-ink-2">
                        AI menempatkan sitasi dari sumber terpilih pada kalimat yang didukung catatan Anda, beserta cuplikan buktinya. Bab metode mengikuti rancangan; bab hasil hanya memakai data penelitian Anda.
                    </p>
                </section>
                <p class="px-1 text-xs leading-normal text-ink-3">Tautan sumber tidak membuktikan setiap pernyataan benar. Periksa isi sumbernya.</p>
            </aside>

            <div class="sticky bottom-0 z-20 -mx-4 flex gap-2 border-t border-line bg-surface px-4 pt-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom))] sm:-mx-6 sm:px-6 lg:hidden">
                <a href="#sumber-draf" class="btn btn-secondary shrink-0 px-3" aria-label="Sumber untuk AI, {selected.length} dipilih"><Icon name="book" size={17} /> {selected.length}</a>
                <button type="button" class="btn btn-primary min-w-0 grow px-3" onclick={() => generate(activeId)} disabled={writing.busy || !active || !!suggestions[activeId] || !!blocked}>
                    <span class="whitespace-nowrap">Buat draf {active?.number ?? ''}</span><span class="sr-only"> dengan AI</span>
                    {#if !user?.unlimited}<span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium whitespace-nowrap">{draftCost}</span>{/if}
                </button>
            </div>
        </div>
    {/if}
</ProjectLayout>
