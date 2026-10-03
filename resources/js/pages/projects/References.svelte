<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { Link, router, useForm, useHttp } from '@inertiajs/svelte';
    import { errorMessage } from '@/lib/format';
    import Icon from '@/components/Icon.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import ReferenceSearch from '@/components/ReferenceSearch.svelte';
    import type { SearchResult } from '@/components/ReferenceSearch.svelte';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import projects from '@/routes/projects';
    import type { ProjectSummary, ReferenceMetadata } from '@/types';

    type Reference = {
        id: number;
        title: string;
        source_url: string;
        source_name: string | null;
        input_method: string;
        notes: string | null;
        metadata: ReferenceMetadata;
        missing: string[];
        in_text: string | null;
        cited_in: string[];
    };

    let {
        project,
        references,
        types,
        editReference,
        focusNotes,
    }: { project: ProjectSummary; references: Reference[]; types: Record<string, string>; editReference: number; focusNotes: boolean } = $props();

    const empty = {
        title: '',
        source_url: '',
        source_name: '',
        input_method: 'manual',
        notes: '',
        metadata: { open_access_url: '', type: 'article', authors: [] as string[], year: '', publication: '', volume: '', issue: '', pages: '', publisher: '', doi: '', keywords: [] as string[] },
    };

    const form = useForm(structuredClone(empty));
    let authorsText = $state('');
    let keywordsText = $state('');
    let editing = $state<number | null>(null);
    let formEl: HTMLElement | undefined = $state();
    let importing = $state(false);
    let entryMode = $state<'search' | 'upload' | 'manual'>('search');
    let initialAuthors = $state('');
    let initialKeywords = $state('');
    const unsaved = $derived(form.isDirty || authorsText !== initialAuthors || keywordsText !== initialKeywords || importing);
    const offBefore = router.on('before', (event) => {
        if (unsaved && event.detail.visit.method === 'get' && !confirm('Referensi belum disimpan. Tinggalkan halaman?')) event.preventDefault();
    });
    onDestroy(offBefore);
    const allowReplace = () => !unsaved || confirm('Ada isian referensi yang belum disimpan. Ganti isian tersebut?');
    function focusError(key: string) {
        const field = key.replace(/^metadata\./, '').split('.')[0];
        const id = field === 'title' ? 'ref-title' : field === 'source_url' ? 'url' : field === 'type' ? 'ref-type' : field;
        const target = document.getElementById(id) ?? document.getElementById('reference-errors');
        target?.focus();
        target?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }


    let articleFile = $state<File | null>(null);

    let importError = $state('');
    let importNotice = $state('');
    let uploadInput: HTMLInputElement | undefined = $state();
    async function importArticle() {
        if (!articleFile || importing) return;
        if (articleFile.size > 15 * 1024 * 1024) {
            importError = 'Ukuran artikel maksimal 15 MB.';
            return;
        }
        if (unsaved && !confirm('Ganti isian form dengan hasil pembacaan artikel?')) return;
        importing = true;
        importError = '';
        importNotice = '';
        const data = new FormData();
        data.append('article', articleFile);
        try {
            const token = decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.slice(11) ?? '');
            const response = await fetch(projects.references.import(project.id).url, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token }, body: data,
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.errors?.article?.[0] ?? result.message ?? 'Artikel gagal dibaca.');
            form.clearErrors();
            Object.assign(form, structuredClone(empty), { title: result.title, source_url: result.source_url, notes: result.notes });
            form.metadata = { ...empty.metadata, ...result.metadata };
            authorsText = (result.metadata.authors ?? []).join('\n');
            keywordsText = (result.metadata.keywords ?? []).join(', ');
            importNotice = 'Artikel berhasil dibaca. Periksa metadata dan ringkasan, lalu klik Simpan referensi.' + (!result.source_url ? ' Tautan asal tidak ditemukan; isi DOI atau tautan artikel terlebih dahulu.' : '');
        } catch (error) {
            importError = error instanceof Error ? error.message : 'Artikel gagal dibaca. Isian form tetap tersedia.';
        } finally {
            importing = false;
        }
    }

    const reader = useHttp<Record<string, never>, { notes: string }>({});
    let reading = $state<number | null>(null);
    let readError = $state('');
    async function readArticle(reference: Reference) {
        if (reference.notes?.trim() && !confirm('Ganti catatan yang ada dengan hasil pembacaan AI?')) return;
        reading = reference.id;
        readError = '';
        try {
            const result = await reader.post(projects.references.read({ project: project.id, reference: reference.id }).url);
            if (!result) {
                readError = 'Artikel tidak berhasil dibaca.';
                return;
            }
            if (editing === reference.id) form.notes = result.notes;
            router.reload({ only: ['references'] });
        } catch (error) {
            readError = errorMessage(error, 'Pembacaan artikel gagal. Catatan lama tetap tersimpan.');
        } finally {
            reading = null;
        }
    }

    let removing = $state<number | null>(null);

    // Seperti keranjang: langsung hapus tanpa dialog; notifikasinya punya tombol "Batalkan".
    function remove(reference: Reference) {
        removing = reference.id;
        router.delete(projects.references.destroy({ project: project.id, reference: reference.id }).url, {
            preserveScroll: true,
            onSuccess: () => editing === reference.id && reset(),
            onFinish: () => (removing = null),
        });
    }

    function loadResult(result: SearchResult) {
        if (importing || !allowReplace()) return;
        reset();
        entryMode = 'manual';
        form.title = result.title;
        form.source_url = result.source_url;
        form.source_name = result.source_name;
        form.input_method = 'search';
        form.metadata = { ...empty.metadata, ...result.metadata, open_access_url: result.open_access_url ?? '' };
        authorsText = (result.metadata.authors ?? []).join('\n');
        keywordsText = (result.metadata.keywords ?? []).join(', ');
        formEl?.scrollIntoView({ behavior: 'smooth' });
    }

    function edit(reference: Reference) {
        if (importing || !allowReplace()) return;
        entryMode = 'manual';
        editing = reference.id;
        form.clearErrors();
        form.title = reference.title;
        form.source_url = reference.source_url;
        form.source_name = reference.source_name ?? '';
        form.input_method = reference.input_method;
        form.notes = reference.notes ?? '';
        form.metadata = { ...empty.metadata, ...reference.metadata };
        authorsText = (reference.metadata.authors ?? []).join('\n');
        keywordsText = (reference.metadata.keywords ?? []).join(', ');
        initialAuthors = authorsText;
        initialKeywords = keywordsText;
        form.defaults();
        formEl?.scrollIntoView({ behavior: 'smooth' });
    }

    function reset() {
        if (importing) return;
        editing = null;
        articleFile = null;
        if (uploadInput) uploadInput.value = '';
        importError = '';
        importNotice = '';
        form.clearErrors();
        Object.assign(form, structuredClone(empty));
        authorsText = '';
        keywordsText = '';
        initialAuthors = '';
        initialKeywords = '';
        form.defaults(structuredClone(empty));
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        if (importing) return;
        form.metadata.authors = authorsText.split('\n');
        form.metadata.keywords = keywordsText.split(',');
        const target = editing ? projects.references.update({ project: project.id, reference: editing }) : projects.references.store(project.id);
        form.submit(target, { preserveScroll: true, onSuccess: reset, onError: (errors) => queueMicrotask(() => focusError(Object.keys(errors)[0])) });
    }

    onMount(() => {
        const reference = references.find((r) => r.id === editReference);
        if (reference) {
            edit(reference);
            queueMicrotask(() => document.getElementById(focusNotes ? 'notes' : 'ref-title')?.focus());
        }
    });
    const err = (key: string) => (form.errors as Record<string, string | undefined>)[key];
</script>

<svelte:window onbeforeunload={(event) => { if (unsaved) event.preventDefault(); }} />

<ProjectLayout {project} active="referensi" title="Referensi">
    <header class="flex items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">Proyek / Referensi</span>
            <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Referensi</h1>
            <p class="max-w-2xl text-[15px] leading-normal text-ink-2">
                Hanya referensi tersimpan yang dapat dipakai AI dan sitasi. Isi metadata sesuai sumber aslinya — yang tidak diketahui biarkan kosong.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3"><span class="font-mono text-[13px] text-ink-2">{references.length} tersimpan</span><Link href={`/projects/${project.id}/research-gap`} class="btn btn-secondary">Analisis research gap</Link></div>
    </header>

    <section class="flex flex-col gap-3" aria-label="Cara menambah referensi">
        <h2 class="text-sm font-semibold">Tambah referensi</h2>
        <div class="grid grid-cols-3 gap-2">
            {#each [{id: 'search', label: 'Cari referensi', icon: 'search'}, {id: 'upload', label: 'Unggah artikel', icon: 'up'}, {id: 'manual', label: 'Isi manual', icon: 'pen'}] as method (method.id)}
                <button type="button" aria-pressed={entryMode === method.id} disabled={importing} class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg border px-2 py-3 text-xs font-semibold sm:flex-row sm:gap-2 sm:text-sm {entryMode === method.id ? 'border-primary bg-primary-soft text-primary' : 'border-line bg-surface text-ink-2 hover:border-primary'}" onclick={() => entryMode = method.id as typeof entryMode}><Icon name={method.icon as 'search' | 'up' | 'pen'} size={17} />{method.label}</button>
            {/each}
        </div>
    </section>
    {#if entryMode === 'search'}<ReferenceSearch projectId={project.id} onload={loadResult} />{/if}

    <div class="grid grid-cols-1 items-start gap-7 {entryMode !== 'search' ? 'xl:grid-cols-[minmax(0,1fr)_440px]' : ''}">
        <section class="flex flex-col gap-3" aria-labelledby="saved">
            <h2 id="saved" class="section-label">Tersimpan di proyek</h2>
            {#if readError}<p role="alert" class="error">{readError}</p>{/if}
            <details class="text-sm"><summary class="cursor-pointer text-primary">Perkiraan kredit pembacaan artikel</summary><div class="mt-2"><AiCost inputCharacters={30000} outputWords={350} detail="Dasar perkiraan: artikel sekitar 30.000 karakter. Artikel lebih panjang membutuhkan lebih banyak kredit." /></div></details>
            {#if reading}<p role="status" class="help">Mengunduh dan membaca artikel dengan AI… Catatan akan disimpan setelah selesai.</p>{/if}
            {#if references.length === 0}
                <div class="flex flex-col items-start gap-2.5 rounded-[10px] border border-dashed border-line-strong p-8">
                    <h3 class="font-display text-[22px] font-medium">Belum ada referensi</h3>
                    <p class="text-sm leading-normal text-ink-2">Simpan referensi lebih dulu sebelum membuat sitasi atau draf berbasis sumber.</p>
                </div>
            {/if}
            {#each references as reference (reference.id)}
                <article class="card flex flex-col gap-2.5 px-5.5 py-5 {editing === reference.id ? 'border-primary shadow-[0_0_0_1px_var(--color-primary)]' : ''}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge"><Icon name={reference.input_method === 'manual' ? 'pen' : 'search'} size={13} /> {reference.input_method === 'manual' ? 'Input manual' : `Hasil pencarian · ${reference.source_name ?? 'penyedia'}`}</span>
                        <span class="badge">{types[reference.metadata.type ?? 'article']}</span>
                        {#if reference.missing.length}
                            <span class="badge badge-warn"><Icon name="warn" size={13} /> Belum lengkap: {reference.missing.join(', ').toLowerCase()}</span>
                        {/if}
                    </div>
                    <div class="flex flex-wrap gap-2"><span class="badge {reference.missing.length ? 'badge-warn' : 'badge-ok'}">{reference.missing.length ? 'Sitasi perlu dilengkapi' : 'Siap untuk sitasi'}</span><span class="badge {reference.notes?.trim() ? 'badge-ok' : 'badge-warn'}">{reference.notes?.trim() ? 'Punya catatan isi untuk AI' : 'Catatan isi belum ada'}</span></div>
                    {#if reference.metadata.keywords?.length}<div class="flex flex-wrap gap-1.5">{#each reference.metadata.keywords as keyword (keyword)}<span class="badge">{keyword}</span>{/each}</div>{/if}
                    <h3 class="font-display text-[19px] leading-snug font-medium">{reference.title}</h3>
                    <p class="text-sm text-ink-2">
                        {reference.in_text ?? ([reference.metadata.authors?.join('; '), reference.metadata.year].filter(Boolean).join(' · ') || 'Penulis dan tahun belum ada')}
                        {#if reference.metadata.publication} · <em>{reference.metadata.publication}</em>{/if}
                    </p>
                    <button type="button" class="btn btn-secondary self-start" onclick={() => readArticle(reference)} disabled={reading !== null}><Icon name={reading === reference.id ? 'spinner' : 'book'} size={16} /> {reading === reference.id ? 'Membaca artikel…' : 'Baca artikel dengan AI'}</button>
                    {#if reference.notes}
                        <p class="line-clamp-2 text-[13px] leading-normal text-ink-2"><span class="font-semibold text-ink">Catatan:</span> {reference.notes}</p>
                    {:else}
                        <p class="text-[13px] text-ink-3">Tanpa catatan — AI tidak akan menyimpulkan isi sumber ini.</p>
                    {/if}
                    <div class="flex items-center justify-between gap-3 border-t border-sunken pt-2.5">
                        <a href={reference.source_url} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 min-w-0 items-center gap-1.5 font-mono text-[13px] text-primary">
                            <span class="truncate">{reference.source_url.replace(/^https?:\/\//, '')}</span>
                            <Icon name="external" size={14} />
                        </a>
                        <div class="flex shrink-0 gap-1.5">
                            <button type="button" class="btn btn-ghost btn-icon text-ink-2 hover:text-danger" onclick={() => remove(reference)} disabled={removing === reference.id} aria-label="Hapus {reference.title}" title="Hapus dari proyek">
                                <Icon name={removing === reference.id ? 'spinner' : 'trash'} size={17} />
                            </button>
                            <button type="button" class="btn {reference.missing.length ? 'btn-primary' : 'btn-secondary'}" onclick={() => { edit(reference); queueMicrotask(() => document.getElementById(reference.missing.length ? 'ref-title' : 'notes')?.focus()); }}>
                                {reference.missing.length ? 'Lengkapi metadata' : !reference.notes?.trim() ? 'Isi catatan sumber' : 'Ubah'}
                            </button>
                        </div>
                    </div>
                    {#if reference.cited_in.length}
                        <p class="-mt-1 text-xs text-ink-3">Disitasi di bagian {reference.cited_in.join(', ')} — sitasinya ikut terhapus bila referensi dihapus</p>
                    {/if}
                </article>
            {/each}
        </section>

        {#if entryMode !== 'search'}
        <form bind:this={formEl} class="order-first xl:order-last card flex flex-col gap-4.5 p-6" onsubmit={submit} novalidate aria-labelledby="form-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="form-title" class="font-display text-[22px] font-medium">{editing ? 'Ubah referensi' : form.input_method === 'search' ? 'Lengkapi hasil pencarian' : entryMode === 'upload' ? 'Referensi dari artikel' : 'Tambah referensi manual'}</h2>
                {#if editing || form.input_method === 'search'}<button type="button" class="btn btn-ghost" onclick={() => { if (allowReplace()) { reset(); entryMode = 'search'; } }}>Batal</button>{/if}
            </div>

            {#if Object.keys(form.errors).length}
                <div id="reference-errors" tabindex="-1" class="alert alert-danger flex-col items-start" role="alert">
                    <p class="font-semibold">Referensi belum disimpan. Perbaiki isian berikut:</p>
                    <ul class="list-disc pl-4 text-sm">{#each Object.entries(form.errors) as [key, message] (key)}<li><button type="button" class="text-left underline" onclick={() => focusError(key)}>{message}</button></li>{/each}</ul>
                </div>
            {/if}
            <fieldset disabled={importing} class="flex min-w-0 flex-col gap-4.5" aria-busy={importing}>
            {#if !editing && entryMode === 'upload'}
                <div class="rounded-xl border border-primary-line bg-primary-soft/35 p-4 sm:p-5">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><Icon name="file" size={20} /></span>
                        <div>
                            <h3 class="text-[15px] font-semibold text-ink">Isi otomatis dari artikel</h3>
                            <p class="mt-1 text-sm leading-relaxed text-ink-3">Unggah artikel, biarkan AI membantu mengisi referensinya.</p>
                        </div>
                    </div>
                    <input bind:this={uploadInput} id="article-upload" type="file" accept=".pdf,.docx" class="peer sr-only" onchange={(event) => { articleFile = event.currentTarget.files?.[0] ?? null; importError = ''; importNotice = ''; }} aria-describedby="upload-help" />
                    <label for="article-upload" class="flex min-w-0 cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed border-primary-line bg-surface px-4 py-6 text-center transition-colors hover:border-primary hover:bg-primary-soft/40 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-primary {importing ? 'pointer-events-none opacity-60' : ''}">
                        <span class="mb-1 flex size-11 items-center justify-center rounded-full bg-primary-soft text-primary"><Icon name={articleFile ? 'check' : 'up'} size={22} /></span>
                        {#if articleFile}
                            <span class="max-w-full break-all text-sm font-semibold text-ink">{articleFile.name}</span>
                            <span class="text-xs text-ink-3">{articleFile.name.split('.').pop()?.toUpperCase()} · {articleFile.size < 1024 * 1024 ? `${Math.max(1, Math.round(articleFile.size / 1024))} KB` : `${(articleFile.size / (1024 * 1024)).toFixed(1)} MB`}</span>
                            <span class="mt-1 text-xs font-medium text-primary">Klik untuk mengganti artikel</span>
                        {:else}
                            <span class="text-sm font-semibold text-primary">Pilih artikel</span>
                            <span class="text-xs text-ink-3">PDF atau Word (.docx) · Maksimal 15 MB</span>
                        {/if}
                    </label>
                    <div class="mt-4 flex flex-col gap-3">
                        <AiCost inputCharacters={30000} outputWords={500} detail="Dasar perkiraan: artikel sekitar 30.000 karakter, termasuk metadata dan ringkasan. Panjang teks sebenarnya diketahui setelah file dibaca." />
                        <button type="button" class="btn btn-primary w-full" disabled={!articleFile || importing} onclick={importArticle}>
                            {#if importing}<Icon name="spinner" size={16} /> Membaca artikel…{:else}<Icon name="file" size={16} /> Baca dan isi dengan AI{/if}
                        </button>
                        <p id="upload-help" class="text-center text-xs leading-relaxed text-ink-3">Hasil dapat diperiksa dan diedit sebelum disimpan.<br />Pembacaan menggunakan kredit paket Anda.</p>
                        {#if importing}<p class="help" role="status">AI sedang membaca artikel. Tunggu di halaman ini hingga form terisi.</p>{/if}
                        {#if importError}<p class="error" role="alert">{importError}</p>{/if}
                        {#if importNotice}<p class="help" role="status">{importNotice}</p>{/if}
                    </div>
                </div>
            {/if}

            <div class="field">
                <label for="ref-type" class="label">Jenis sumber</label>
                <select id="ref-type" class="input" bind:value={form.metadata.type}>
                    {#each Object.entries(types) as [value, label] (value)}<option {value}>{label}</option>{/each}
                </select>
            </div>

            <div class="field">
                <label for="ref-title" class="label">Judul <span class="font-normal text-ink-3">(wajib)</span></label>
                <input id="ref-title" class="input" bind:value={form.title} aria-invalid={err('title') ? 'true' : undefined} />
                {#if err('title')}<span class="error">{err('title')}</span>{/if}
            </div>

            <div class="field">
                <label for="url" class="label">Tautan asal <span class="font-normal text-ink-3">(wajib)</span></label>
                <input id="url" type="url" class="input font-mono text-base" placeholder="https://doi.org/…" bind:value={form.source_url} aria-invalid={err('source_url') ? 'true' : undefined} />
                {#if err('source_url')}<span class="error">{err('source_url')}</span>{/if}
            </div>

            <div class="field">
                <label for="authors" class="label">Penulis</label>
                <textarea id="authors" rows="3" class="input" bind:value={authorsText} placeholder={'Santoso, Budi Arief\nLestari, Dewi'} aria-describedby={Object.keys(form.errors).some(key => key.startsWith('metadata.authors')) ? 'authors-help reference-errors' : 'authors-help'} aria-invalid={Object.keys(form.errors).some(key => key.startsWith('metadata.authors')) ? 'true' : undefined}></textarea>
                <span id="authors-help" class="help">Satu penulis per baris: <span class="font-mono text-xs">Nama Belakang, Nama Depan</span>. Nama lembaga ditulis tanpa koma.</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="field">
                    <label for="year" class="label">Tahun</label>
                    <input id="year" class="input" inputmode="numeric" placeholder="2024" bind:value={form.metadata.year} aria-invalid={err('metadata.year') ? 'true' : undefined} />
                </div>
                <div class="field">
                    <label for="doi" class="label">DOI</label>
                    <input id="doi" class="input font-mono text-base" placeholder="10.xxxx/…" bind:value={form.metadata.doi} />
                </div>
            </div>
            {#if err('metadata.year')}<span class="error -mt-2">{err('metadata.year')}</span>{/if}

            {#if form.metadata.type === 'book'}
                <div class="field">
                    <label for="publisher" class="label">Penerbit</label>
                    <input id="publisher" class="input" bind:value={form.metadata.publisher} />
                </div>
            {:else}
                <div class="field">
                    <label for="publication" class="label">{form.metadata.type === 'web' ? 'Nama situs / lembaga' : 'Nama jurnal'}</label>
                    <input id="publication" class="input" bind:value={form.metadata.publication} />
                </div>
            {/if}

            {#if form.metadata.type === 'article'}
                <div class="grid grid-cols-3 gap-3">
                    <div class="field"><label for="volume" class="label">Volume</label><input id="volume" class="input" bind:value={form.metadata.volume} /></div>
                    <div class="field"><label for="issue" class="label">Nomor</label><input id="issue" class="input" bind:value={form.metadata.issue} /></div>
                    <div class="field"><label for="pages" class="label">Halaman</label><input id="pages" class="input" placeholder="45-60" bind:value={form.metadata.pages} /></div>
                </div>
            {/if}

            <div class="field">
                <label for="keywords" class="label">Kata kunci / kategori</label>
                <input id="keywords" class="input" bind:value={keywordsText} placeholder="literasi digital, belajar mandiri" aria-describedby="keywords-help" />
                <span id="keywords-help" class="help">Pisahkan dengan koma. Dipakai untuk mengelompokkan referensi di Draf.</span>
                {#if err('metadata.keywords')}<span class="error">{err('metadata.keywords')}</span>{/if}
                {#each Object.entries(form.errors).filter(([key]) => key.startsWith('metadata.keywords.')) as [key, message] (key)}<span class="error">{message}</span>{/each}
            </div>

            <div class="field">
                <label for="notes" class="label">Catatan / ringkasan isi</label>
                <textarea id="notes" rows="4" class="input" bind:value={form.notes} aria-describedby="notes-help"></textarea>
                <span id="notes-help" class="help">Tulis poin penting dari sumber ini dengan kata-kata Anda. Hanya catatan ini yang boleh dipakai AI sebagai isi sumber.</span>
                {#if err('notes')}<span class="error">{err('notes')}</span>{/if}
            </div>

            <button type="submit" class="btn btn-primary" disabled={form.processing || importing}>
                {#if form.processing}<Icon name="spinner" size={16} /> Menyimpan…{:else}{editing ? 'Simpan perubahan' : 'Simpan referensi'}{/if}
            </button>
            </fieldset>
        </form>
        {/if}
    </div>
</ProjectLayout>
