<script lang="ts">
    import { router, useForm, useHttp } from '@inertiajs/svelte';
    import { onDestroy, untrack } from 'svelte';
    import AiCost from '@/components/AiCost.svelte';
    import Icon from '@/components/Icon.svelte';
    import { errorMessage } from '@/lib/format';
    import ProjectLayout from '@/layouts/ProjectLayout.svelte';
    import type { ProjectSummary } from '@/types';

    type Field = 'masalah' | 'tujuan' | 'hipotesis' | 'pendekatan' | 'desain' | 'subjek' | 'pengumpulan' | 'analisis' | 'indikator' | 'ide';
    type SuggestField = Exclude<Field, 'ide'>;

    let {
        project,
        design,
        researchData,
        fields,
        approaches,
        selectedGap,
        empiricalUnits,
    }: {
        project: ProjectSummary;
        design: Partial<Record<Field, string>>;
        researchData: string;
        fields: Record<Field, string>;
        approaches: Record<string, string>;
        selectedGap: { title: string; question: string; contribution: string } | null;
        empiricalUnits: string[];
    } = $props();

    const empty: Record<Field, string> = { masalah: '', tujuan: '', hipotesis: '', pendekatan: '', desain: '', subjek: '', pengumpulan: '', analisis: '', indikator: '', ide: '' };
    const form = useForm(untrack(() => ({ title: project.title, design: { ...empty, ...design }, research_data: researchData })));
    const literature = $derived(form.design.pendekatan === 'studi_literatur');
    const ptk = $derived(form.design.pendekatan === 'ptk');
    const required = $derived<Field[]>(ptk ? ['masalah', 'pendekatan', 'analisis', 'indikator'] : ['masalah', 'pendekatan', 'analisis']);
    const textFields = $derived<{ key: SuggestField; rows: number; hint: string }[]>([
        { key: 'masalah', rows: 3, hint: ptk ? 'Mis. Bagaimana penerapan model … dapat meningkatkan hasil belajar … siswa kelas …?' : 'Tulis sebagai pertanyaan. Dipakai di Bab I dan menjadi acuan kesimpulan.' },
        { key: 'tujuan', rows: 3, hint: 'Sejajar dengan rumusan masalah.' },
        { key: 'hipotesis', rows: 2, hint: ptk ? 'Hipotesis tindakan, mis. Jika guru menerapkan model …, maka hasil belajar siswa meningkat.' : 'Opsional; biasanya untuk penelitian kuantitatif.' },
        { key: 'desain', rows: 2, hint: ptk ? 'Model siklus dan rencana jumlah siklus, mis. Kemmis & McTaggart, 2 siklus.' : 'Mis. survei korelasional, eksperimen semu, studi kasus, systematic review.' },
        { key: 'subjek', rows: 2, hint: ptk ? 'Kelas, jumlah siswa, sekolah, dan waktu pelaksanaan.' : 'Siapa atau apa yang diteliti, berapa, dan bagaimana dipilih.' },
        { key: 'pengumpulan', rows: 2, hint: ptk ? 'Mis. tes tiap akhir siklus, lembar observasi guru dan siswa, dokumentasi.' : 'Mis. kuesioner skala Likert, wawancara semi-terstruktur, dokumentasi.' },
        { key: 'analisis', rows: 2, hint: ptk ? 'Mis. deskriptif kuantitatif (rata-rata, persentase ketuntasan) dibandingkan antarsiklus, dan deskriptif kualitatif hasil observasi.' : 'Mis. statistik deskriptif dan regresi, analisis tematik, analisis isi.' },
        ...(ptk ? [{ key: 'indikator' as const, rows: 2, hint: 'Target yang menandai tindakan berhasil, mis. ≥ 80% siswa mencapai KKM. Dipakai untuk menilai tiap siklus.' }] : []),
    ]);
    const err = (key: string) => (form.errors as Record<string, string | undefined>)[key];
    const missing = $derived(required.filter((key) => !form.design[key].trim()).map((key) => fields[key].toLowerCase()));

    // Saran AI hanya untuk field rancangan yang kosong; Data & temuan tidak pernah disarankan.
    const suggestable = $derived<SuggestField[]>(['pendekatan', ...textFields.map((field) => field.key)]);
    const suggester = useHttp<{ title: string; fields: SuggestField[]; design: Record<Field, string> }, { suggestions: Partial<Record<SuggestField, string>>; notes: string }>({ title: '', fields: [], design: { ...empty } });
    let suggestions = $state<Partial<Record<SuggestField, string>>>({});
    let suggestNotes = $state('');
    let suggestError = $state('');
    const emptyFields = $derived(suggestable.filter((key) => !form.design[key].trim()));
    const suggestionCount = $derived(Object.keys(suggestions).length);

    async function suggest() {
        suggestError = '';
        suggester.title = form.title;
        suggester.fields = emptyFields;
        suggester.design = $state.snapshot(form.design);
        try {
            const result = await suggester.post(`/projects/${project.id}/rancangan/saran`);
            if (result) {
                suggestions = result.suggestions;
                suggestNotes = result.notes;
            }
        } catch (error) {
            suggestError = errorMessage(error, 'Saran AI gagal. Isian Anda tidak berubah.');
        }
    }

    function apply(key: SuggestField) {
        form.design[key] = suggestions[key] ?? form.design[key];
        delete suggestions[key];
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.put(`/projects/${project.id}/rancangan`, { preserveScroll: true, onSuccess: () => form.defaults() });
    }

    onDestroy(
        router.on('before', (event) => {
            if (form.isDirty && event.detail.visit.method === 'get' && !confirm('Rancangan belum disimpan. Tinggalkan halaman?')) {
                event.preventDefault();
            }
        }),
    );
</script>

{#snippet suggestion(key: SuggestField)}
    {#if suggestions[key] !== undefined}
        <div class="rounded-lg border border-ai-line bg-ai-wash px-3.5 py-3 text-sm">
            <p class="mb-1.5 flex items-center gap-2 text-xs font-semibold text-ai"><span class="rounded-full border border-ai px-1.5 font-mono text-[10px] leading-3.5">AI</span> Saran untuk {fields[key].toLowerCase()}</p>
            <p class="whitespace-pre-line leading-relaxed">{key === 'pendekatan' ? (approaches[suggestions[key] ?? ''] ?? suggestions[key]) : suggestions[key]}</p>
            <div class="mt-2.5 flex flex-wrap gap-2">
                <button type="button" class="btn btn-secondary min-h-10" onclick={() => apply(key)}>Pakai</button>
                <button type="button" class="btn btn-ghost min-h-10" onclick={() => delete suggestions[key]}>Abaikan</button>
            </div>
        </div>
    {/if}
{/snippet}

<svelte:window onbeforeunload={(event) => { if (form.isDirty) event.preventDefault(); }} />

<ProjectLayout {project} active="rancangan" title="Rancangan penelitian">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex max-w-3xl flex-col gap-2">
            <span class="eyebrow">Proyek / Rancangan penelitian</span>
            <h1 class="font-display text-[30px] leading-tight font-medium sm:text-[40px]">Rancangan penelitian</h1>
            <p class="text-[15px] leading-normal text-ink-2">Masalah, tujuan, dan metode Anda menjadi acuan semua tulisan AI agar Bab I, metode, hasil, dan kesimpulan saling nyambung. AI tidak menebak metode dan tidak menulis hasil tanpa data Anda.</p>
        </div>
        <div class="flex flex-col items-start gap-1 sm:items-end">
            {#if missing.length}
                <span class="badge badge-warn"><Icon name="warn" size={13} /> Belum siap untuk bab metode</span>
                <span class="text-xs text-ink-2">Lengkapi: {missing.join(', ')}</span>
            {:else}
                <span class="badge badge-ok"><Icon name="check" size={13} /> Siap untuk bab metode</span>
            {/if}
        </div>
    </header>

    <form class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]" onsubmit={submit} novalidate>
        <div class="flex min-w-0 flex-col gap-6">
            <section class="card flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="arah-title">
                <h2 id="arah-title" class="font-display text-[22px] font-medium">Judul & arah</h2>
                <div class="field">
                    <label for="title" class="label">Judul</label>
                    <input id="title" class="input font-display text-lg" bind:value={form.title} aria-invalid={err('title') ? 'true' : undefined} aria-describedby="title-help" />
                    <span id="title-help" class="help">Judul final sebaiknya mengikuti rumusan masalah dan arah dari research gap.</span>
                    {#if err('title')}<span class="error">{err('title')}</span>{/if}
                </div>
                {#if selectedGap}
                    <div class="flex flex-col gap-2 rounded-lg border border-primary-line bg-primary-soft/40 p-4 text-sm">
                        <span class="section-label text-[11px]">Arah dari research gap</span>
                        <p class="font-semibold">{selectedGap.title}</p>
                        <p><span class="font-semibold">Pertanyaan:</span> {selectedGap.question}</p>
                        <button type="button" class="btn btn-secondary self-start" onclick={() => (form.design.masalah = selectedGap.question)} disabled={form.design.masalah.trim() === selectedGap.question.trim()}>Pakai sebagai rumusan masalah</button>
                    </div>
                {/if}
                {#if form.design.ide}
                    <details class="rounded-lg border border-line px-4 py-3 text-sm">
                        <summary class="cursor-pointer font-semibold">{fields.ide}</summary>
                        <label for="design-ide" class="sr-only">{fields.ide}</label>
                        <textarea id="design-ide" rows="4" class="input mt-3" bind:value={form.design.ide}></textarea>
                        <span class="help">Gagasan awal dari diskusi judul. Dipakai AI sebagai latar, bukan fakta.</span>
                    </details>
                {/if}
            </section>

            <section class="card flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="rancangan-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 id="rancangan-title" class="font-display text-[22px] font-medium">Rancangan</h2>
                    <div class="flex flex-col items-start gap-0.5 sm:items-end">
                        <button type="button" class="btn btn-secondary" onclick={suggest} disabled={suggester.processing || !emptyFields.length || !form.title.trim()}>
                            {#if suggester.processing}<Icon name="spinner" size={16} /> Menyusun saran…{:else}<span class="rounded-full border border-current px-1.5 font-mono text-[10px] leading-3.5">AI</span> {emptyFields.length ? `Saran AI untuk ${emptyFields.length} field kosong` : 'Semua field terisi'}{/if}
                        </button>
                        {#if emptyFields.length}<AiCost inputCharacters={4000} outputWords={350} detail="Perkiraan untuk satu kali saran rancangan." />{/if}
                    </div>
                </div>
                {#if suggester.processing}<p class="help" role="status">AI sedang membaca judul, arah gap, referensi, dan isian Anda untuk menyusun saran.</p>{/if}
                {#if suggestError}<p class="error" role="alert">{suggestError}</p>{/if}
                {#if suggestionCount}
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ai-line bg-ai-wash px-4 py-3 text-sm" role="status">
                        <span class="grow">{suggestionCount} saran siap ditinjau di bawah. Saran belum disimpan; periksa dan ganti [placeholder] dengan data Anda.{#if suggestNotes}<span class="mt-1 block text-ink-2">{suggestNotes}</span>{/if}</span>
                        <button type="button" class="btn btn-secondary" onclick={() => (Object.keys(suggestions) as SuggestField[]).forEach(apply)}>Pakai semua</button>
                    </div>
                {/if}
                <div class="field">
                    <label for="design-pendekatan" class="label">{fields.pendekatan} <span class="font-normal text-ink-3">(wajib untuk bab metode)</span></label>
                    <select id="design-pendekatan" class="input" bind:value={form.design.pendekatan} aria-invalid={err('design.pendekatan') ? 'true' : undefined}>
                        <option value="">Pilih pendekatan</option>
                        {#each Object.entries(approaches) as [value, label] (value)}<option {value}>{label}</option>{/each}
                    </select>
                    {#if err('design.pendekatan')}<span class="error">{err('design.pendekatan')}</span>{/if}
                    {@render suggestion('pendekatan')}
                </div>
                {#each textFields as field (field.key)}
                    <div class="field">
                        <label for="design-{field.key}" class="label">{fields[field.key]}{#if required.includes(field.key)}{' '}<span class="font-normal text-ink-3">(wajib untuk bab metode)</span>{/if}</label>
                        <textarea id="design-{field.key}" rows={field.rows} class="input" bind:value={form.design[field.key]} aria-describedby="design-{field.key}-help" aria-invalid={err(`design.${field.key}`) ? 'true' : undefined}></textarea>
                        <span id="design-{field.key}-help" class="help">{field.hint}</span>
                        {#if err(`design.${field.key}`)}<span class="error">{err(`design.${field.key}`)}</span>{/if}
                        {@render suggestion(field.key)}
                    </div>
                {/each}
            </section>

            <section id="data" class="card flex scroll-mt-20 flex-col gap-4 p-5 sm:p-6" aria-labelledby="data-title">
                <div class="flex flex-col gap-1">
                    <h2 id="data-title" class="font-display text-[22px] font-medium">Data & temuan penelitian</h2>
                    <p class="text-sm leading-relaxed text-ink-2">
                        {literature ? 'Untuk studi literatur, hasil disintesis dari referensi bercatatan; bagian ini opsional.' : ptk ? 'Satu-satunya dasar AI untuk hasil tiap siklus, pembahasan, kesimpulan, dan abstrak. Isi setelah tindakan selesai.' : 'Satu-satunya dasar AI untuk bab hasil, pembahasan, kesimpulan, dan abstrak. Isi setelah data Anda terkumpul dan dianalisis.'}
                    </p>
                </div>
                <div class="field">
                    <label for="research-data" class="label">Ringkasan hasil analisis</label>
                    <textarea id="research-data" rows="10" class="input" bind:value={form.research_data} aria-describedby="research-data-help" aria-invalid={err('research_data') ? 'true' : undefined}></textarea>
                    <span id="research-data-help" class="help">{ptk ? 'Tulis per tahap: pra-siklus, siklus I, siklus II, dan seterusnya, mis. rata-rata nilai, persentase ketuntasan, hasil observasi, dan catatan refleksi. ' : ''}Tempel angka, tabel (sebagai teks), hasil uji statistik, tema wawancara, atau kutipan responden. AI tidak menambah angka atau temuan di luar isian ini, dan angka yang tidak cocok akan ditandai.</span>
                    {#if err('research_data')}<span class="error">{err('research_data')}</span>{/if}
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary" disabled={form.processing || !form.isDirty}>
                    {#if form.processing}<Icon name="spinner" size={16} /> Menyimpan…{:else}Simpan rancangan{/if}
                </button>
                {#if form.isDirty}<span class="text-[13px] font-medium text-warn" role="status">Perubahan belum disimpan</span>{/if}
            </div>
        </div>

        <aside class="flex flex-col gap-4 xl:sticky xl:top-6">
            <section class="card flex flex-col gap-3 px-5 py-5 text-sm">
                <h2 class="section-label">Dipakai untuk</h2>
                <ul class="flex flex-col gap-2.5 leading-normal">
                    <li class="flex gap-2"><Icon name="target" size={16} class="mt-0.5 text-primary" /><span><span class="font-semibold">Kerangka & Bab I:</span> rumusan masalah, tujuan, hipotesis.</span></li>
                    <li class="flex gap-2"><Icon name="list" size={16} class="mt-0.5 text-primary" /><span><span class="font-semibold">Bab metode:</span> hanya dari rancangan; detail kosong ditulis sebagai [placeholder].</span></li>
                    <li class="flex gap-2"><Icon name="file" size={16} class="mt-0.5 text-primary" /><span><span class="font-semibold">Hasil, pembahasan, kesimpulan, abstrak:</span> hanya dari data & temuan Anda.</span></li>
                </ul>
            </section>
            {#if empiricalUnits.length}
                <section class="card flex flex-col gap-2 px-5 py-5 text-sm">
                    <h2 class="section-label">Bagian yang butuh data</h2>
                    <ul class="flex flex-col gap-1 text-ink-2">{#each empiricalUnits as unit (unit)}<li>{unit}</li>{/each}</ul>
                    <p class="text-xs text-ink-3">{project.has_data || literature ? 'AI dapat membantu menulis bagian ini.' : 'AI tidak menulis bagian ini sampai data diisi.'}</p>
                </section>
            {/if}
        </aside>
    </form>
</ProjectLayout>
