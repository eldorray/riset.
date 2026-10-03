<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Icon from '@/components/Icon.svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { chapterLabel } from '@/lib/outline';
    import admin from '@/routes/admin';

    type Chapter = { title: string; sections: string[] };
    type Type = { value: string; label: string; custom: boolean; numbering: 'bab' | 'angka'; chapters: Chapter[] };

    let { types }: { types: Type[] } = $props();

    let activeValue = $state('');
    // Subbab diedit sebagai teks (satu per baris) agar cepat diketik.
    let chapters = $state<{ title: string; sections: string }[]>([]);
    const form = useForm({ numbering: 'bab' as 'bab' | 'angka', chapters: [] as Chapter[] });

    const active = $derived(types.find((t) => t.value === activeValue) ?? types[0]);

    function load(type: Type | undefined) {
        if (!type) {
            return;
        }

        activeValue = type.value;
        form.clearErrors();
        form.numbering = type.numbering;
        chapters = type.chapters.map((c) => ({ title: c.title, sections: c.sections.join('\n') }));
    }

    // Muat ulang tab aktif saat data dari server berubah (mis. setelah simpan). Penulisan
    // state di-untrack agar efek tidak memicu dirinya sendiri.
    $effect(() => {
        const current = types;
        untrack(() => load(current.find((t) => t.value === activeValue) ?? current[0]));
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.chapters = chapters.map((c) => ({
            title: c.title.trim(),
            sections: c.sections.split('\n').map((s) => s.trim()).filter(Boolean),
        }));
        form.submit(admin.documentTypes.update(active.value), { preserveScroll: true });
    }

    function resetDefault() {
        if (confirm(`Kembalikan struktur ${active.label} ke contoh bawaan?`)) {
            router.delete(admin.documentTypes.destroy(active.value).url, { preserveScroll: true });
        }
    }

    const firstError = $derived(Object.values(form.errors as Record<string, string>)[0]);
</script>

<AdminLayout active="jenis" title="Jenis tulisan">
    <header class="flex flex-col gap-2 border-b border-line pb-6">
        <span class="eyebrow">Admin / Konfigurasi / Jenis tulisan</span>
        <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Jenis tulisan &amp; struktur</h1>
        <p class="max-w-3xl text-[15px] leading-normal text-ink-2">
            Struktur ini dipakai AI saat menyusun kerangka baru. Kerangka proyek yang sudah tersimpan tidak ikut berubah. Hasil AI tetap tampil sebagai kerangka yang perlu diperiksa, bukan format resmi institusi.
        </p>
    </header>

    <div role="tablist" aria-label="Jenis tulisan" class="flex gap-1 border-b border-line">
        {#each types as type (type.value)}
            <button
                type="button"
                role="tab"
                aria-selected={type.value === active?.value}
                onclick={() => load(type)}
                class="-mb-px flex min-h-12 items-center gap-2.5 border-b-2 px-4 text-[15px] {type.value === active?.value ? 'border-ink font-semibold' : 'border-transparent font-medium text-ink-2'}"
            >
                {type.label}
                <span class="font-mono text-[11px] font-normal text-ink-3">{type.custom ? 'diatur' : 'bawaan'}</span>
            </button>
        {/each}
    </div>

    {#if active}
        <form class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" onsubmit={submit} novalidate>
            <section class="card flex flex-col" aria-label="Struktur {active.label}">
                <div class="flex items-center justify-between gap-4 border-b border-sunken px-3 sm:px-6 py-4">
                    <h2 class="font-display text-2xl font-medium">Struktur {active.label.toLowerCase()}</h2>
                    <button type="button" class="btn btn-ghost text-primary" onclick={() => chapters.push({ title: '', sections: '' })}>
                        <Icon name="plus" size={16} /> Tambah bab
                    </button>
                </div>
                {#if firstError}
                    <div class="alert alert-danger mx-6 mt-4" role="alert"><p>{firstError}</p></div>
                {/if}
                <ol>
                    {#each chapters as chapter, i (i)}
                        <li class="grid grid-cols-[40px_minmax(0,1fr)_auto] sm:grid-cols-[88px_minmax(0,1fr)_auto] items-start gap-3 border-b border-sunken px-3 sm:px-6 py-4 last:border-b-0">
                            <span class="pt-3 font-mono text-xs text-ink-3">{chapterLabel(i, form.numbering)}</span>
                            <div class="flex flex-col gap-2.5">
                                <label class="sr-only" for="t-{i}">Judul {chapterLabel(i, form.numbering)}</label>
                                <input id="t-{i}" class="input font-semibold" placeholder="Judul bab" bind:value={chapter.title} />
                                <label class="help" for="s-{i}">Contoh subbab — satu per baris (boleh kosong)</label>
                                <textarea id="s-{i}" rows={Math.max(2, chapter.sections.split('\n').length)} class="input text-base" bind:value={chapter.sections}></textarea>
                            </div>
                            <div class="flex flex-col">
                                <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Naikkan bab {i + 1}" disabled={i === 0} onclick={() => chapters.splice(i - 1, 0, ...chapters.splice(i, 1))}><Icon name="up" size={16} /></button>
                                <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Turunkan bab {i + 1}" disabled={i === chapters.length - 1} onclick={() => chapters.splice(i + 1, 0, ...chapters.splice(i, 1))}><Icon name="down" size={16} /></button>
                                <button type="button" class="btn btn-ghost btn-icon text-ink-2" aria-label="Hapus bab {i + 1}" disabled={chapters.length === 1} onclick={() => chapters.splice(i, 1)}><Icon name="trash" size={16} /></button>
                            </div>
                        </li>
                    {/each}
                </ol>
            </section>

            <aside class="card flex flex-col gap-5 px-6 py-5">
                <h2 class="section-label">Aturan jenis ini</h2>
                <div class="field">
                    <label for="numbering" class="label">Penomoran bab</label>
                    <select id="numbering" class="input" bind:value={form.numbering}>
                        <option value="bab">BAB I, BAB II, …</option>
                        <option value="angka">1., 2., …</option>
                    </select>
                </div>
                <p class="text-[13px] leading-normal text-ink-2">
                    {active.custom ? 'Struktur ini diatur admin.' : 'Masih memakai contoh bawaan dari config/riset.php, yang belum disetujui sebagai panduan resmi.'}
                </p>
                <div class="flex flex-col gap-2.5">
                    <button class="btn btn-primary" disabled={form.processing}>Simpan struktur</button>
                    {#if active.custom}
                        <button type="button" class="btn btn-secondary" onclick={resetDefault}>Kembalikan ke bawaan</button>
                    {/if}
                </div>
            </aside>
        </form>
    {/if}
</AdminLayout>
