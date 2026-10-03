<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import admin from '@/routes/admin';

    type Fields = {
        name: string;
        institution: string | null;
        font_family: string;
        font_size: number;
        line_spacing: number;
        margin_top: number;
        margin_bottom: number;
        margin_left: number;
        margin_right: number;
        first_line_indent: number;
        page_numbers: boolean;
        title_page: boolean;
        title_page_text: string | null;
        table_of_contents: boolean;
        chapter_uppercase: boolean;
        chapter_page_break: boolean;
    };
    type Template = Fields & { id: number; projects_count: number };

    let {
        templates,
        defaults,
        fonts,
    }: { templates: Template[]; defaults: Omit<Fields, 'name' | 'institution'>; fonts: string[] } = $props();

    let editing = $state<number | null>(null);
    const blank = (): Fields => ({ name: '', institution: '', ...structuredClone($state.snapshot(defaults)), title_page_text: defaults.title_page_text ?? '' });
    const form = useForm<Fields>(blank());

    function edit(template: Template) {
        editing = template.id;
        form.clearErrors();
        const { id: _id, projects_count: _count, ...fields } = template;
        Object.assign(form, { ...fields, institution: fields.institution ?? '', title_page_text: fields.title_page_text ?? '' });
    }

    function reset() {
        editing = null;
        form.clearErrors();
        Object.assign(form, blank());
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.submit(editing ? admin.templates.update(editing) : admin.templates.store(), { preserveScroll: true, onSuccess: reset });
    }

    function destroy(template: Template) {
        const note = template.projects_count ? ` ${template.projects_count} proyek akan kembali ke format bawaan.` : '';

        if (confirm(`Hapus template "${template.name}"?${note}`)) {
            router.delete(admin.templates.destroy(template.id).url, { preserveScroll: true, onSuccess: () => editing === template.id && reset() });
        }
    }

    const err = (key: keyof Fields) => (form.errors as Record<string, string | undefined>)[key];
    const margins: { key: 'margin_top' | 'margin_bottom' | 'margin_left' | 'margin_right'; label: string }[] = [
        { key: 'margin_top', label: 'Atas' },
        { key: 'margin_bottom', label: 'Bawah' },
        { key: 'margin_left', label: 'Kiri' },
        { key: 'margin_right', label: 'Kanan' },
    ];
    const toggles: { key: 'page_numbers' | 'title_page' | 'table_of_contents' | 'chapter_uppercase' | 'chapter_page_break'; label: string }[] = [
        { key: 'title_page', label: 'Halaman judul' },
        { key: 'table_of_contents', label: 'Daftar isi (perbarui field di Word)' },
        { key: 'page_numbers', label: 'Nomor halaman di bawah' },
        { key: 'chapter_uppercase', label: 'Judul bab huruf kapital' },
        { key: 'chapter_page_break', label: 'Setiap bab di halaman baru' },
    ];
</script>

<AdminLayout active="template" title="Template Word">
    <header class="flex flex-col gap-2 border-b border-line pb-6">
        <span class="eyebrow">Admin / Konfigurasi / Template Word</span>
        <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Template Word</h1>
        <p class="max-w-3xl text-[15px] leading-normal text-ink-2">
            Format ekspor .docx per kampus atau jurnal. Pengguna memilih template di halaman proyek. Pastikan nilainya sesuai panduan resmi institusi — aplikasi tidak memeriksanya.
        </p>
    </header>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
        <section class="flex flex-col gap-3" aria-labelledby="list">
            <h2 id="list" class="section-label">Template tersimpan</h2>
            {#each templates as template (template.id)}
                <article class="card flex flex-col gap-2.5 px-5.5 py-5 {editing === template.id ? 'border-primary shadow-[0_0_0_1px_var(--color-primary)]' : ''}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-col gap-0.5">
                            <h3 class="font-display text-xl font-medium">{template.name}</h3>
                            {#if template.institution}<span class="text-sm text-ink-2">{template.institution}</span>{/if}
                        </div>
                        <span class="badge">{template.projects_count} proyek</span>
                    </div>
                    <p class="font-mono text-xs leading-relaxed text-ink-2">
                        {template.font_family} {template.font_size} pt · spasi {template.line_spacing} · margin {template.margin_top}/{template.margin_right}/{template.margin_bottom}/{template.margin_left} cm
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        {#each toggles.filter((t) => template[t.key]) as toggle (toggle.key)}<span class="badge">{toggle.label.replace(' (perbarui field di Word)', '')}</span>{/each}
                    </div>
                    <div class="flex justify-end gap-2 border-t border-sunken pt-2.5">
                        <button type="button" class="btn btn-ghost text-danger" onclick={() => destroy(template)}>Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick={() => edit(template)}>Ubah</button>
                    </div>
                </article>
            {:else}
                <div class="flex flex-col gap-2 rounded-[10px] border border-dashed border-line-strong p-8">
                    <h3 class="font-display text-[22px] font-medium">Belum ada template</h3>
                    <p class="text-sm text-ink-2">Tanpa template, ekspor memakai format bawaan: {defaults.font_family} {defaults.font_size} pt, spasi {defaults.line_spacing}, margin {defaults.margin_top}/{defaults.margin_right}/{defaults.margin_bottom}/{defaults.margin_left} cm.</p>
                </div>
            {/each}
        </section>

        <form class="card flex flex-col gap-4.5 p-6" onsubmit={submit} novalidate aria-labelledby="form-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="form-title" class="font-display text-[22px] font-medium">{editing ? 'Ubah template' : 'Template baru'}</h2>
                {#if editing}<button type="button" class="btn btn-ghost" onclick={reset}>Batal</button>{/if}
            </div>

            <div class="field">
                <label for="name" class="label">Nama template</label>
                <input id="name" class="input" placeholder="Mis. Skripsi FKIP Universitas …" bind:value={form.name} aria-invalid={err('name') ? 'true' : undefined} />
                {#if err('name')}<span class="error">{err('name')}</span>{/if}
            </div>
            <div class="field">
                <label for="institution" class="label">Institusi</label>
                <input id="institution" class="input" placeholder="Tampil di halaman judul" bind:value={form.institution} />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="field col-span-2">
                    <label for="font" class="label">Font</label>
                    <select id="font" class="input" bind:value={form.font_family}>
                        {#each fonts as font (font)}<option value={font}>{font}</option>{/each}
                    </select>
                </div>
                <div class="field">
                    <label for="size" class="label">Ukuran</label>
                    <select id="size" class="input" bind:value={form.font_size}>
                        {#each [10, 11, 12, 13, 14] as size (size)}<option value={size}>{size} pt</option>{/each}
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="field">
                    <label for="spacing" class="label">Spasi baris</label>
                    <select id="spacing" class="input" bind:value={form.line_spacing}>
                        {#each [1, 1.15, 1.5, 2] as spacing (spacing)}<option value={spacing}>{spacing}</option>{/each}
                    </select>
                </div>
                <div class="field">
                    <label for="indent" class="label">Indentasi paragraf (cm)</label>
                    <input id="indent" type="number" step="0.05" min="0" max="3" class="input" bind:value={form.first_line_indent} />
                </div>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="label mb-2">Margin (cm)</legend>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    {#each margins as margin (margin.key)}
                        <div class="field">
                            <label for={margin.key} class="text-xs text-ink-2">{margin.label}</label>
                            <input id={margin.key} type="number" step="0.1" min="1" max="6" class="input px-2.5" bind:value={form[margin.key]} aria-invalid={err(margin.key) ? 'true' : undefined} />
                        </div>
                    {/each}
                </div>
                {#each margins as margin (margin.key)}{#if err(margin.key)}<span class="error">{err(margin.key)}</span>{/if}{/each}
            </fieldset>

            <fieldset class="flex flex-col">
                <legend class="label mb-1.5">Susunan dokumen</legend>
                {#each toggles as toggle (toggle.key)}
                    <label class="flex min-h-10 items-center gap-2.5 text-sm">
                        <input type="checkbox" bind:checked={form[toggle.key]} class="size-4.5 accent-primary" />
                        {toggle.label}
                    </label>
                {/each}
            </fieldset>

            {#if form.title_page}
                <div class="field">
                    <label for="cover" class="label">Teks halaman judul</label>
                    <textarea id="cover" rows="3" class="input text-base" placeholder={'SKRIPSI\nDiajukan untuk memenuhi sebagian persyaratan memperoleh gelar Sarjana'} bind:value={form.title_page_text} aria-describedby="cover-help"></textarea>
                    <span id="cover-help" class="help">Tampil di atas judul. Nama penulis, institusi, dan tahun ditambahkan otomatis.</span>
                </div>
            {/if}

            <button class="btn btn-primary" disabled={form.processing}>
                {#if form.processing}<Icon name="spinner" size={16} /> Menyimpan…{:else}{editing ? 'Simpan perubahan' : 'Simpan template'}{/if}
            </button>
        </form>
    </div>
</AdminLayout>
