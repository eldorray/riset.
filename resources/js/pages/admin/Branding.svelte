<script lang="ts">
    import { onDestroy } from 'svelte';
    import { page, router, useForm } from '@inertiajs/svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import Icon from '@/components/Icon.svelte';

    const form = useForm<{ logo: File | null }>({ logo: null });
    let preview = $state<string | null>(null);
    let input: HTMLInputElement;
    let removing = $state(false);
    function clear() {
        if (preview) URL.revokeObjectURL(preview);
        preview = null;
        form.reset();
        if (input) input.value = '';
    }
    function choose(event: Event) {
        if (preview) URL.revokeObjectURL(preview);
        form.logo = (event.currentTarget as HTMLInputElement).files?.[0] ?? null;
        form.clearErrors();
        preview = form.logo ? URL.createObjectURL(form.logo) : null;
    }
    function save(event: SubmitEvent) {
        event.preventDefault();
        form.post('/admin/branding', { preserveScroll: true, onSuccess: clear });
    }
    function resetLogo() {
        if (!confirm('Hapus logo unggahan dan kembali ke logo Riset bawaan?')) return;
        removing = true;
        router.delete('/admin/branding', { preserveScroll: true, onSuccess: clear, onFinish: () => (removing = false) });
    }
    onDestroy(() => { if (preview) URL.revokeObjectURL(preview); });
</script>

<AdminLayout active="logo" title="Logo aplikasi">
    <header class="border-b border-line pb-6">
        <p class="eyebrow mb-2">Admin / Konfigurasi</p>
        <h1 class="font-display text-[30px] sm:text-[40px] font-medium">Logo aplikasi</h1>
        <p class="mt-2 max-w-2xl text-sm text-ink-2">Logo tampil di landing page, halaman masuk, daftar proyek, serta navigasi pengguna dan admin.</p>
    </header>
    <form class="card flex max-w-2xl flex-col gap-6 p-6 sm:p-8" onsubmit={save}>
        <div>
            <h2 class="label mb-3">Pratinjau logo</h2>
            <div class="flex min-h-32 items-center justify-center rounded-lg border border-line bg-paper p-6 font-display text-3xl">
                {#if preview}<img src={preview} alt="Pratinjau logo baru" class="max-h-24 max-w-full object-contain" />{:else}<AppLogo />{/if}
            </div>
        </div>
        <div class="field">
            <label for="app-logo" class="label">Unggah logo</label>
            <input bind:this={input} id="app-logo" type="file" accept="image/png,image/jpeg,image/webp" class="peer sr-only" onchange={choose} aria-describedby="logo-help" aria-invalid={form.errors.logo ? 'true' : undefined} disabled={form.processing || removing} />
            <label for="app-logo" class="flex min-h-20 cursor-pointer items-center gap-4 rounded-lg border border-dashed border-line-strong bg-paper px-5 py-4 peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-disabled:cursor-not-allowed peer-disabled:opacity-50">
                <Icon name="file" class="shrink-0 text-primary" />
                <span class="min-w-0"><span class="block break-all text-sm font-semibold">{form.logo?.name ?? 'Pilih gambar logo'}</span><span class="mt-1 block text-xs text-ink-3">PNG, JPG, atau WebP · maksimal 2 MB</span></span>
            </label>
            <p id="logo-help" class="help">Disarankan logo transparan dengan bentuk horizontal. Dimensi maksimal 4096 × 4096 piksel.</p>
            {#if form.errors.logo}<p class="error" role="alert">{form.errors.logo}</p>{/if}
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary" disabled={!form.logo || form.processing || removing}>{form.processing ? 'Menyimpan…' : 'Simpan logo'}</button>
            {#if preview}<button type="button" class="btn btn-secondary" disabled={form.processing || removing} onclick={clear}>Batal</button>{/if}
            {#if page.props.logo_url}<button type="button" class="btn btn-ghost text-danger" disabled={form.processing || removing} onclick={resetLogo}>{removing ? 'Menghapus…' : 'Gunakan logo bawaan'}</button>{/if}
        </div>
    </form>
</AdminLayout>
