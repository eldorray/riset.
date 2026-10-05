<script lang="ts" module>
    import type { ReferenceMetadata } from '@/types';

    export type SearchResult = {
        title: string;
        source_url: string;
        source_name: string;
        open_access_url: string | null;
        country: string | null;
        metadata: ReferenceMetadata;
        saved?: boolean;
    };
</script>

<script lang="ts">
    import { router, useHttp } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';
    import { errorMessage } from '@/lib/format';
    import projects from '@/routes/projects';

    type Response = {
        results: SearchResult[];
        hidden: number;
        notes: string[];
        links: { name: string; url: string }[];
        page: number;
    };

    let { projectId, onload }: { projectId: number; onload: (result: SearchResult) => void } = $props();

    const sources = [
        { value: 'all', label: 'Semua sumber' },
        { value: 'crossref', label: 'Crossref' },
        { value: 'openalex', label: 'OpenAlex' },
        { value: 'semantic', label: 'Semantic Scholar' },
        { value: 'doaj', label: 'DOAJ (akses terbuka)' },
        { value: 'scopus', label: 'Scopus (Elsevier)' },
    ];

    const searcher = useHttp<
        { q: string; source: string; index: string; scope: string; year_from: number | ''; year_to: number | ''; type: string; open_access: 0 | 1; page: number },
        Response
    >({ q: '', source: 'all', index: 'all', scope: 'all', year_from: '', year_to: '', type: 'any', open_access: 0, page: 1 });

    const scopusSelected = $derived(searcher.index === 'scopus' || searcher.source === 'scopus');
    const activeFilters = $derived([searcher.source !== 'all', searcher.index !== 'all', searcher.scope !== 'all', searcher.year_from !== '', searcher.year_to !== '', searcher.type !== 'any', searcher.open_access === 1].filter(Boolean).length);

    function selectIndex() {
        searcher.source = searcher.index === 'all' ? 'all' : searcher.index;
        if (scopusSelected) searcher.scope = 'all';
        if (searcher.index === 'doaj') searcher.type = 'article';
    }

    let results = $state<SearchResult[] | null>(null);
    let notes = $state<string[]>([]);
    let links = $state<{ name: string; url: string }[]>([]);
    let error = $state('');
    let batch = $state(0);
    let savingUrl = $state<string | null>(null);
    // Judul yang sudah pernah ditampilkan untuk kata kunci + filter ini, agar "Cari" berikutnya memberi judul lain.
    let seen = new Set<string>();
    let lastSignature = $state('');

    const signature = $derived(
        JSON.stringify([searcher.q.trim().toLowerCase(), searcher.source, searcher.index, searcher.scope, searcher.year_from, searcher.year_to, searcher.type, searcher.open_access]),
    );
    const again = $derived(results !== null && signature === lastSignature);
    const key = (r: SearchResult) => r.title.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();

    async function search(event: SubmitEvent) {
        event.preventDefault();
        error = '';

        if (!again) {
            seen = new Set();
            batch = 0;
            searcher.page = 1;
        } else {
            searcher.page += 1;
        }

        try {
            // Lewati halaman yang isinya sudah pernah tampil semua (maks. 3 halaman berturut-turut).
            for (let attempt = 0; attempt < 3; attempt++) {
                const response = await searcher.get(projects.references.search(projectId).url);

                if (!response) {
                    return;
                }

                const fresh = response.results.filter((r) => !seen.has(key(r)));
                fresh.forEach((r) => seen.add(key(r)));
                notes = response.notes;
                links = response.links;

                if (fresh.length || response.results.length === 0) {
                    results = fresh;
                    lastSignature = signature;
                    batch++;

                    return;
                }

                searcher.page += 1;
            }

            results = [];
            lastSignature = signature;
        } catch (e) {
            results = null;
            error = errorMessage(e, 'Pencarian gagal. Referensi tersimpan tidak terpengaruh.');
            const data = (e as { response?: { data?: unknown } })?.response?.data;

            if (typeof data === 'string') {
                try {
                    links = (JSON.parse(data) as { links?: { name: string; url: string }[] }).links ?? links;
                } catch {
                    // pesan cadangan sudah ditampilkan
                }
            }
        }
    }

    function save(result: SearchResult) {
        savingUrl = result.source_url;
        router.post(
            projects.references.store(projectId).url,
            { title: result.title, source_url: result.source_url, source_name: result.source_name, input_method: 'search', metadata: { ...result.metadata, open_access_url: result.open_access_url ?? '' } },
            { preserveScroll: true, onSuccess: () => (result.saved = true), onError: (errors) => (error = Object.values(errors)[0] ?? 'Referensi gagal disimpan.'), onFinish: () => (savingUrl = null) },
        );
    }

    const meta = (r: SearchResult) =>
        [
            (r.metadata.authors?.slice(0, 3).join('; ') ?? '') + ((r.metadata.authors?.length ?? 0) > 3 ? ' dkk.' : ''),
            r.metadata.year ?? 'tahun tidak tersedia',
            r.metadata.publication ?? r.metadata.publisher,
        ]
            .filter(Boolean)
            .join(' · ');
</script>

<section class="flex flex-col gap-3" aria-labelledby="search-title">
    <form role="search" class="card flex flex-col gap-4 p-5" onsubmit={search} novalidate>
        <div class="flex flex-col gap-2">
            <label id="search-title" for="q" class="label">Cari referensi</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative min-w-0 grow">
                    <Icon name="search" class="pointer-events-none absolute top-3.5 left-3.5 text-ink-3" />
                    <input id="q" type="search" class="input pl-11" placeholder="Judul, topik, atau nama penulis" bind:value={searcher.q} aria-invalid={searcher.errors.q ? 'true' : undefined} />
                </div>
                <button class="btn btn-primary sm:min-w-40" disabled={searcher.processing}>
                    {#if searcher.processing}<Icon name="spinner" size={16} /> Mencari…{:else if again}<Icon name="refresh" size={16} /> Cari referensi lain{:else}Cari{/if}
                </button>
            </div>
            {#if searcher.errors.q}<span class="error">{searcher.errors.q}</span>{/if}
        </div>

        <p class="help -mt-2">Cari lagi dengan kata kunci yang sama untuk judul lain; referensi tersimpan tidak ditampilkan ulang. Nama penyedia bukan bukti kredibilitas — periksa tautan asal.</p>
        <details class="group rounded-lg border border-line">
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 px-3.5 text-sm font-semibold [&::-webkit-details-marker]:hidden"><Icon name="caret" size={16} class="-rotate-90 transition-transform group-open:rotate-0 motion-reduce:transition-none" /> Filter lanjutan{#if activeFilters}<span class="badge badge-ai">{activeFilters} aktif</span>{/if}</summary>
            <div class="flex flex-col gap-3 border-t border-line p-3.5">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4 md:items-end">
            <div class="field col-span-2 md:col-span-1">
                <label for="source" class="text-[13px] font-semibold">Sumber</label>
                <select id="source" class="input" bind:value={searcher.source} disabled={searcher.index !== 'all'} onchange={() => { if (scopusSelected) searcher.scope = 'all'; }}>
                    {#each sources as source (source.value)}<option value={source.value}>{source.label}</option>{/each}
                </select>
            </div>
            <div class="field col-span-2 md:col-span-1">
                <label for="index" class="text-[13px] font-semibold">Filter indeks</label>
                <select id="index" class="input" bind:value={searcher.index} onchange={selectIndex} aria-describedby="index-help">
                    <option value="all">Tanpa filter indeks</option>
                    <option value="scopus">Ditemukan di Scopus</option>
                    <option value="doaj">Terdaftar di DOAJ</option>
                    <option value="sinta" disabled>SINTA S1–S6 · belum tersedia</option>
                </select>
            </div>
            <div class="field col-span-2 md:col-span-1">
                <label for="scope" class="text-[13px] font-semibold">Cakupan jurnal</label>
                <select id="scope" class="input" bind:value={searcher.scope} disabled={scopusSelected} aria-describedby="scope-help">
                    <option value="all">Nasional & internasional</option>
                    <option value="national">Jurnal nasional saja</option>
                    <option value="international">Jurnal internasional saja</option>
                </select>
            </div>
            <div class="field">
                <label for="year_from" class="text-[13px] font-semibold">Tahun dari</label>
                <input id="year_from" type="number" inputmode="numeric" min="1900" max="2100" placeholder="2019" class="input" bind:value={searcher.year_from} aria-invalid={searcher.errors.year_from ? 'true' : undefined} />
            </div>
            <div class="field">
                <label for="year_to" class="text-[13px] font-semibold">sampai</label>
                <input id="year_to" type="number" inputmode="numeric" min="1900" max="2100" placeholder="2026" class="input" bind:value={searcher.year_to} aria-invalid={searcher.errors.year_to ? 'true' : undefined} />
            </div>
            <div class="field">
                <label for="search-type" class="text-[13px] font-semibold">Jenis</label>
                <select id="search-type" class="input" bind:value={searcher.type} disabled={searcher.scope !== 'all' || searcher.index === 'doaj'} title={searcher.scope !== 'all' ? 'Filter cakupan jurnal hanya untuk artikel jurnal' : undefined}>
                    <option value="any">Semua jenis</option>
                    <option value="article">Artikel jurnal</option>
                    <option value="book">Buku</option>
                </select>
            </div>
            <label class="flex min-h-11 items-center gap-2.5 text-sm">
                <input type="checkbox" class="size-4.5 accent-primary" checked={searcher.open_access === 1} onchange={(e) => (searcher.open_access = e.currentTarget.checked ? 1 : 0)} />
                Akses terbuka
            </label>
        </div>
        {#if searcher.scope !== 'all'}
            <p id="scope-help" class="help rounded-lg bg-paper px-3 py-2">
                <span class="font-semibold text-ink">Filter ketat:</span>
                {searcher.scope === 'national' ? 'hanya artikel dari jurnal yang diterbitkan di Indonesia' : 'hanya artikel dari jurnal yang diterbitkan di luar Indonesia'}, berdasarkan negara penerbit jurnal. Hasil yang negaranya tidak diketahui dibuang, dan Semantic Scholar dilewati. Ini bukan status akreditasi SINTA/Scopus.
            </p>
        {/if}
        <p id="index-help" class="help">
            Filter indeks membatasi pencarian ke penyedia tersebut. SINTA S1–S6 memerlukan data akreditasi terverifikasi;
            <a href="https://sinta.kemdiktisaintek.go.id/journals/index/" target="_blank" rel="noopener noreferrer" class="text-primary underline">periksa jurnal di SINTA</a>.
        </p>
        {#if scopusSelected}<p id="scope-help" class="help">Scopus memakai akses API Elsevier. Cakupan negara penerbit jurnal tidak tersedia pada pencarian ini. Hasil dokumen tidak menjamin jurnal masih aktif terindeks atau memiliki kuartil tertentu.</p>{/if}
        <p class="help">Semua sumber umum: Crossref, OpenAlex, Semantic Scholar, DOAJ. Scopus dipakai saat sumber atau filter Scopus dipilih.</p>
            </div>
        </details>
        {#if searcher.errors.index}<span class="error">{searcher.errors.index}</span>{/if}
        {#if searcher.errors.year_from || searcher.errors.year_to}
            <span class="error">{searcher.errors.year_to ?? searcher.errors.year_from}</span>
        {/if}
    </form>

    {#each notes as note (note)}
        <div class="alert alert-warn" role="status"><Icon name="warn" class="text-warn" /><p>{note}</p></div>
    {/each}

    {#if error}
        <div class="alert alert-danger" role="alert"><Icon name="error" class="text-danger" /><p>{error}</p></div>
    {:else if results && results.length === 0}
        <div class="rounded-[10px] border border-dashed border-line-strong p-6 text-sm text-ink-2">
            {batch ? 'Tidak ada judul lain untuk kata kunci dan filter ini.' : 'Tidak ada hasil.'} Coba kata kunci atau filter lain, atau tambahkan secara manual.
        </div>
    {:else if results}
        <p class="text-[13px] text-ink-2">Kumpulan {batch} · {results.length} judul baru</p>
        <ul class="flex flex-col gap-2.5" aria-label="Hasil pencarian">
            {#each results as result (result.source_url)}
                <li class="card flex flex-col gap-2 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="badge"><Icon name="search" size={12} /> {result.source_name}</span>
                        {#if result.country === 'ID'}<span class="badge bg-primary-soft text-primary">Jurnal nasional</span>{:else if result.country}<span class="badge bg-primary-soft text-primary">Jurnal internasional</span>{/if}
                        {#if result.source_name === 'Scopus'}<span class="badge">Ditemukan di Scopus</span>{/if}
                        {#if result.open_access_url}<span class="badge badge-ok">Akses terbuka</span>{/if}
                        {#if !result.metadata.year || !result.metadata.authors?.length}<span class="badge badge-warn">Metadata belum lengkap</span>{/if}
                    </div>
                    <h3 class="font-display text-[17px] leading-snug font-medium">{result.title}</h3>
                    <p class="text-[13px] text-ink-2">{meta(result)}</p>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap gap-4">
                            <a href={result.source_url} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1.5 font-mono text-[13px] text-primary">
                                {result.source_url.replace(/^https?:\/\//, '').slice(0, 48)} <Icon name="external" size={14} />
                            </a>
                            {#if result.open_access_url && result.open_access_url !== result.source_url}
                                <a href={result.open_access_url} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-semibold text-ok">Teks lengkap <Icon name="external" size={14} /></a>
                            {/if}
                        </div>
                        {#if result.saved}
                            <span class="inline-flex min-h-11 items-center gap-2 px-3 text-sm font-semibold text-ok"><Icon name="check" size={16} /> Tersimpan</span>
                        {:else}
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-ghost" onclick={() => onload(result)}>Lengkapi dulu</button>
                                <button type="button" class="btn btn-secondary" onclick={() => save(result)} disabled={savingUrl === result.source_url}>Simpan ke proyek</button>
                            </div>
                        {/if}
                    </div>
                </li>
            {/each}
        </ul>
    {/if}

    {#if links.length}
        <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-ink-2">
            Cari juga secara manual di:
            {#each links as link (link.name)}
                <a href={link.url} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1 font-semibold text-primary">{link.name} <Icon name="external" size={13} /></a>
            {/each}
            <span class="text-ink-3">(tidak punya API publik — tambahkan hasilnya lewat input manual)</span>
        </p>
    {/if}
</section>
