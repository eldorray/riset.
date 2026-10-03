<script lang="ts">
    import { Link, router, useForm, useHttp } from '@inertiajs/svelte';
    import { onDestroy } from 'svelte';
    import Icon from '@/components/Icon.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import { errorMessage } from '@/lib/format';
    import { chapterLabel, newId } from '@/lib/outline';
    import projects from '@/routes/projects';
    import type { Chapter, ProjectSummary } from '@/types';

    let {
        project,
        outline,
        structure,
        numbering,
        writtenUnits,
    }: {
        project: ProjectSummary;
        outline: Chapter[] | null;
        structure: { title: string; sections: string[] }[];
        numbering: 'bab' | 'angka';
        writtenUnits: string[];
    } = $props();

    // Editor bekerja pada salinan; server baru berubah saat "Simpan kerangka".
    let chapters = $state<Chapter[]>([]);
    let source = $state<'saved' | 'ai' | 'template'>('saved');
    let dirty = $state(false);
    let aiError = $state('');

    $effect(() => {
        chapters = structuredClone($state.snapshot(outline) ?? []);
        source = 'saved';
        dirty = false;
    });

    // Peringatan bila keluar halaman dengan kerangka yang belum disimpan.
    onDestroy(
        router.on('before', (event) => {
            if (dirty && event.detail.visit.method === 'get' && !confirm('Kerangka belum disimpan. Tinggalkan halaman?')) {
                event.preventDefault();
            }
        }),
    );

    const generator = useHttp<Record<string, never>, { outline: Chapter[] }>({});
    const saveForm = useForm({ outline: [] as Chapter[] });

    async function generate() {
        if (dirty && !confirm('Perubahan yang belum disimpan akan diganti hasil AI. Lanjutkan?')) {
            return;
        }

        aiError = '';

        try {
            const result = await generator.post(projects.outline.generate(project.id).url);
            chapters = result.outline;
            source = 'ai';
            dirty = true;
        } catch (error) {
            aiError = errorMessage(error, 'Generasi kerangka gagal. Kerangka tersimpan tidak berubah.');
        }
    }

    function useTemplate() {
        chapters = structure.map((c) => ({ id: newId(), title: c.title, sections: c.sections.map((title) => ({ id: newId(), title })) }));
        source = 'template';
        dirty = true;
        aiError = '';
    }

    function save() {
        saveForm.outline = $state.snapshot(chapters);
        saveForm.submit(projects.outline.update(project.id), { preserveScroll: true, onSuccess: () => {
                dirty = false;
                aiError = '';
            },
        });
    }

    function removeSection(chapter: Chapter, index: number) {
        const section = chapter.sections[index];

        if (writtenUnits.includes(section.id) && !confirm(`Bagian "${section.title}" sudah berisi teks. Teksnya tidak dihapus, tetapi tidak ikut diekspor. Hapus dari kerangka?`)) {
            return;
        }

        chapter.sections.splice(index, 1);
        dirty = true;
    }

    function move(chapter: Chapter, index: number, delta: -1 | 1) {
        const [section] = chapter.sections.splice(index, 1);
        chapter.sections.splice(index + delta, 0, section);
        dirty = true;
    }

    const err = (key: string) => (saveForm.errors as Record<string, string | undefined>)[key];
    const saveErrors = $derived(Object.values(saveForm.errors as Record<string, string>));
</script>

<svelte:window
    onbeforeunload={(event) => {
        if (dirty) {
            event.preventDefault();
        }
    }}
/>

<ProjectLayout {project} active="kerangka" title="Kerangka">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">Proyek / Kerangka</span>
            <div class="flex flex-wrap items-center gap-3.5">
                <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Kerangka</h1>
                {#if source === 'ai'}
                    <span class="badge badge-ai"><span class="rounded-full border border-ai px-1.5 font-mono text-[10px] leading-3.5">AI</span> Dihasilkan AI · perlu diperiksa</span>
                {:else if chapters.length && source === 'saved'}
                    <span class="badge"><Icon name="check" size={13} /> Tersimpan</span>
                {/if}
            </div>
            {#if dirty}<p class="text-sm font-medium text-warn">Perubahan belum disimpan</p>{/if}
        </div>
        <div class="flex gap-3">
            <button type="button" class="btn btn-secondary" onclick={generate} disabled={generator.processing}>
                <Icon name={generator.processing ? 'spinner' : 'refresh'} size={16} />
                {chapters.length ? 'Susun ulang dengan AI' : 'Susun dengan AI'}
            </button>
            <button type="button" class="btn btn-primary" onclick={save} disabled={!dirty || saveForm.processing || !chapters.length}>
                {#if saveForm.processing}<Icon name="spinner" size={16} /> Menyimpan…{:else}Simpan kerangka{/if}
            </button>
        </div>
    </header>

    <AiCost inputCharacters={project.title.length + 2000} outputWords={600} detail="Perkiraan untuk menyusun kerangka tulisan." />
    {#if generator.processing}
        <div class="card flex items-center gap-2.5 px-5 py-4" role="status" aria-live="polite">
            <Icon name="spinner" class="text-primary" />
            <span class="font-semibold">Menyusun kerangka {project.document_type.label.toLowerCase()}…</span>
            <span class="text-[13px] text-ink-2">Kerangka di bawah tetap yang lama sampai hasil baru datang.</span>
        </div>
    {/if}
    {#if aiError}
        <div class="alert alert-danger" role="alert"><Icon name="error" class="text-danger" /><p>{aiError}</p></div>
    {/if}
    {#if saveErrors.length}
        <div class="alert alert-danger" role="alert"><Icon name="error" class="text-danger" /><p>{saveErrors[0]}</p></div>
    {/if}

    {#if chapters.length === 0}
        <div class="flex flex-col items-start gap-3 rounded-[10px] border border-dashed border-line-strong p-10">
            <h2 class="font-display text-[26px] font-medium">Belum ada kerangka</h2>
            <p class="max-w-xl text-[15px] leading-relaxed text-ink-2">
                AI menyusun subbab berdasarkan judul Anda dan struktur {project.document_type.label.toLowerCase()}. Anda juga bisa mulai dari struktur contoh lalu mengubahnya sendiri.
            </p>
            <div class="flex gap-3">
                <button type="button" class="btn btn-primary" onclick={generate} disabled={generator.processing}>Susun dengan AI</button>
                <button type="button" class="btn btn-secondary" onclick={useTemplate}>Mulai dari struktur contoh</button>
            </div>
        </div>
    {:else}
        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
            <ol aria-label="Bab" class="flex flex-col gap-2.5">
                {#each chapters as chapter, i (chapter.id)}
                    <li class="card">
                        <div class="grid grid-cols-[40px_minmax(0,1fr)] sm:grid-cols-[88px_minmax(0,1fr)_auto] items-center gap-2 px-4 py-2">
                            <span class="font-mono text-xs text-ink-3">{chapterLabel(i, numbering)}</span>
                            <label class="sr-only" for="c-{chapter.id}">Judul {chapterLabel(i, numbering)}</label>
                            <input
                                id="c-{chapter.id}"
                                class="input border-transparent px-2 font-semibold hover:border-line focus:border-line-strong"
                                bind:value={chapter.title}
                                oninput={() => (dirty = true)}
                                aria-invalid={err(`outline.${i}.title`) ? 'true' : undefined}
                            />
                            <span class="col-start-2 px-2 font-mono text-xs text-ink-3 sm:col-start-auto">{chapter.sections.length ? `${chapter.sections.length} subbab` : 'tanpa subbab'}</span>
                        </div>
                        <div class="flex flex-col pr-4 pb-3 pl-4 sm:pl-24">
                            <ol>
                                {#each chapter.sections as section, j (section.id)}
                                    <li class="grid grid-cols-[40px_minmax(0,1fr)] sm:grid-cols-[48px_minmax(0,1fr)_auto] items-center gap-1 border-t border-sunken">
                                        <span class="font-mono text-xs text-ink-3">{i + 1}.{j + 1}</span>
                                        <label class="sr-only" for="s-{section.id}">Judul subbab {i + 1}.{j + 1}</label>
                                        <input
                                            id="s-{section.id}"
                                            class="input min-h-11 border-transparent px-2 text-base hover:border-line focus:border-line-strong"
                                            bind:value={section.title}
                                            oninput={() => (dirty = true)}
                                            aria-invalid={err(`outline.${i}.sections.${j}.title`) ? 'true' : undefined}
                                        />
                                        <div class="col-span-2 flex justify-end sm:col-span-1">
                                            <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Naikkan {section.title}" disabled={j === 0} onclick={() => move(chapter, j, -1)}><Icon name="up" size={16} /></button>
                                            <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Turunkan {section.title}" disabled={j === chapter.sections.length - 1} onclick={() => move(chapter, j, 1)}><Icon name="down" size={16} /></button>
                                            <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Hapus {section.title}" onclick={() => removeSection(chapter, j)}><Icon name="trash" size={16} /></button>
                                        </div>
                                    </li>
                                {/each}
                            </ol>
                            <button
                                type="button"
                                class="btn btn-ghost self-start px-3 text-primary"
                                onclick={() => {
                                    chapter.sections.push({ id: newId(), title: 'Subbab baru' });
                                    dirty = true;
                                }}
                            >
                                <Icon name="plus" size={16} /> Tambah subbab
                            </button>
                        </div>
                    </li>
                {/each}
            </ol>

            <aside class="flex flex-col gap-4">
                <section class="card flex flex-col gap-3 px-5.5 py-5">
                    <h2 class="section-label">Tentang kerangka ini</h2>
                    <dl class="grid grid-cols-[96px_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                        <dt class="text-ink-2">Jenis</dt><dd>{project.document_type.label}</dd>
                        <dt class="text-ink-2">Bab</dt><dd>{chapters.length}, urutan mengikuti struktur jenis tulisan</dd>
                    </dl>
                    <p class="border-t border-sunken pt-3 text-[13px] leading-normal text-ink-2">
                        Ini kerangka yang perlu Anda periksa, bukan format resmi kampus atau jurnal. Bab tanpa subbab ditulis langsung di halaman Draf.
                    </p>
                </section>
                <section class="flex flex-col gap-2.5 rounded-[10px] border border-dashed border-line-strong px-5.5 py-5">
                    <h2 class="text-[15px] font-semibold">Langkah berikutnya</h2>
                    <p class="text-[13px] leading-normal text-ink-2">Setelah disimpan, tulis draf per bagian dari referensi yang Anda pilih.</p>
                    <Link href={projects.draft(project.id).url} class="btn btn-secondary self-start">Buka draf</Link>
                </section>
            </aside>
        </div>
    {/if}
</ProjectLayout>
