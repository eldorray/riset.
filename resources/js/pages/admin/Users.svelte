<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import admin from '@/routes/admin';

    type Row = {
        id: number;
        name: string;
        email: string;
        role: 'pengguna' | 'admin';
        google: boolean;
        password: boolean;
        projects: number;
        created_at: string | null;
    };
    type Paginated = {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };

    let { users, search }: { users: Paginated; search: string } = $props();

    let query = $state('');
    let editing = $state<Row | null>(null);

    $effect(() => {
        query = search;
    });

    const createForm = useForm({ name: '', email: '', password: '', role: 'pengguna' });
    const editForm = useForm({ name: '', role: 'pengguna', password: '' });

    function edit(row: Row) {
        editing = row;
        editForm.clearErrors();
        editForm.name = row.name;
        editForm.role = row.role;
        editForm.password = '';
    }

    function submitCreate(event: SubmitEvent) {
        event.preventDefault();
        createForm.submit(admin.users.store(), { preserveScroll: true, onSuccess: () => createForm.reset() });
    }

    function submitEdit(event: SubmitEvent) {
        event.preventDefault();

        if (editing) {
            editForm.submit(admin.users.update(editing.id), { preserveScroll: true, onSuccess: () => (editing = null) });
        }
    }

    function doSearch(event: SubmitEvent) {
        event.preventDefault();
        router.get(admin.users.index().url, query ? { q: query } : {}, { preserveState: true, replace: true });
    }

    const pageLabel = (label: string) => label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya');
</script>

<AdminLayout active="pengguna" title="Pengguna">
    <header class="flex items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">Admin / Akses / Pengguna</span>
            <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Pengguna</h1>
            <p class="max-w-2xl text-[15px] text-ink-2">Akun Google dibuat otomatis saat masuk. Akun email dan password dibuat di sini.</p>
        </div>
        <span class="font-mono text-[13px] text-ink-2">{users.total} akun</span>
    </header>

    <div class="alert alert-info" role="note">
        <Icon name="shield" class="text-primary" />
        <p>Admin melihat data akun dan jumlah proyek saja. Isi proyek, referensi, dan draf pengguna tidak dapat dibuka dari panel ini.</p>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex flex-col gap-4">
            <form role="search" class="flex items-end gap-3" onsubmit={doSearch}>
                <div class="field grow">
                    <label for="q" class="label text-[13px]">Cari pengguna</label>
                    <input id="q" type="search" class="input" placeholder="Nama atau email" bind:value={query} />
                </div>
                <button class="btn btn-secondary">Cari</button>
            </form>

            <!-- svelte-ignore a11y_no_noninteractive_tabindex (Keyboard users can scroll the table region.) -->
            <div class="card relative overflow-x-auto" role="region" aria-label="Daftar pengguna, geser untuk melihat semua kolom" tabindex="0">
                <table class="w-full min-w-[560px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-paper text-left">
                            {#each ['Pengguna', 'Peran', 'Cara masuk', 'Proyek'] as heading (heading)}
                                <th scope="col" class="section-label border-b border-line px-5 py-3 {heading === 'Proyek' ? 'text-right' : ''}">{heading}</th>
                            {/each}
                            <th scope="col" class="border-b border-line px-5 py-3"><span class="sr-only">Tindakan</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each users.data as row (row.id)}
                            <tr class="border-b border-sunken last:border-b-0 {editing?.id === row.id ? 'bg-primary-soft/50' : ''}">
                                <td class="px-5 py-2.5">
                                    <div class="flex flex-col">
                                        <span class="font-semibold">{row.name}</span>
                                        <span class="font-mono text-xs text-ink-3">{row.email}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-2.5"><span class="badge {row.role === 'admin' ? 'bg-primary-soft text-primary' : ''}">{row.role === 'admin' ? 'Admin' : 'Pengguna'}</span></td>
                                <td class="px-5 py-2.5 text-ink-2">{[row.google && 'Google', row.password && 'Email'].filter(Boolean).join(' + ') || '—'}</td>
                                <td class="px-5 py-2.5 text-right font-mono text-[13px]">{row.projects}</td>
                                <td class="px-5 py-1.5 text-right">
                                    <Link href={`/admin/billing?q=${encodeURIComponent(row.email)}`} class="btn btn-ghost">Subscription</Link>
                                    <button type="button" class="btn btn-ghost" onclick={() => edit(row)} aria-label="Ubah {row.name}">Ubah</button>
                                </td>
                            </tr>
                        {:else}
                            <tr><td colspan="5" class="px-5 py-6 text-ink-2">Tidak ada pengguna yang cocok.</td></tr>
                        {/each}
                    </tbody>
                </table>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-2.5 text-[13px] text-ink-2">
                    <span>{users.from ? `Menampilkan ${users.from}–${users.to} dari ${users.total}` : ''}</span>
                    <nav aria-label="Halaman" class="flex flex-wrap gap-1.5">
                        {#each users.links as link (link.label)}
                            {#if link.url}
                                <Link href={link.url} preserveScroll class="btn {link.active ? 'btn-primary' : 'btn-secondary'} min-w-11 px-3 text-[13px]" aria-current={link.active ? 'page' : undefined}>{pageLabel(link.label)}</Link>
                            {:else}
                                <span class="btn btn-secondary pointer-events-none min-w-11 px-3 text-[13px] opacity-50">{pageLabel(link.label)}</span>
                            {/if}
                        {/each}
                    </nav>
                </div>
            </div>
        </div>

        {#if editing}
            <form class="card flex flex-col gap-4 p-6" onsubmit={submitEdit} novalidate aria-labelledby="edit-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="edit-title" class="font-display text-[22px] font-medium">Ubah akun</h2>
                    <button type="button" class="btn btn-ghost" onclick={() => (editing = null)}>Batal</button>
                </div>
                <p class="font-mono text-xs text-ink-3">{editing.email}</p>
                <div class="field">
                    <label for="e-name" class="label">Nama</label>
                    <input id="e-name" class="input" bind:value={editForm.name} aria-invalid={editForm.errors.name ? 'true' : undefined} />
                    {#if editForm.errors.name}<span class="error">{editForm.errors.name}</span>{/if}
                </div>
                <div class="field">
                    <label for="e-role" class="label">Peran</label>
                    <select id="e-role" class="input" bind:value={editForm.role}>
                        <option value="pengguna">Pengguna</option>
                        <option value="admin">Admin</option>
                    </select>
                    {#if editForm.errors.role}<span class="error">{editForm.errors.role}</span>{/if}
                </div>
                <div class="field">
                    <label for="e-password" class="label">Password baru</label>
                    <input id="e-password" type="password" autocomplete="new-password" class="input" bind:value={editForm.password} aria-describedby="e-password-help" aria-invalid={editForm.errors.password ? 'true' : undefined} />
                    <span id="e-password-help" class="help">Kosongkan bila tidak diubah. Mengisi password juga mengaktifkan login email untuk akun Google.</span>
                    {#if editForm.errors.password}<span class="error">{editForm.errors.password}</span>{/if}
                </div>
                <button class="btn btn-primary" disabled={editForm.processing}>Simpan perubahan</button>
            </form>
        {:else}
            <form class="card flex flex-col gap-4 p-6" onsubmit={submitCreate} novalidate aria-labelledby="create-title">
                <h2 id="create-title" class="font-display text-[22px] font-medium">Buat akun login email</h2>
                <div class="field">
                    <label for="c-name" class="label">Nama</label>
                    <input id="c-name" class="input" bind:value={createForm.name} aria-invalid={createForm.errors.name ? 'true' : undefined} />
                    {#if createForm.errors.name}<span class="error">{createForm.errors.name}</span>{/if}
                </div>
                <div class="field">
                    <label for="c-email" class="label">Email</label>
                    <input id="c-email" type="email" class="input" bind:value={createForm.email} aria-invalid={createForm.errors.email ? 'true' : undefined} />
                    {#if createForm.errors.email}<span class="error">{createForm.errors.email}</span>{/if}
                </div>
                <div class="field">
                    <label for="c-password" class="label">Password awal</label>
                    <input id="c-password" type="password" autocomplete="new-password" class="input" bind:value={createForm.password} aria-describedby="c-password-help" aria-invalid={createForm.errors.password ? 'true' : undefined} />
                    <span id="c-password-help" class="help">Minimal 8 karakter. Sampaikan ke pengguna lewat jalur yang aman.</span>
                    {#if createForm.errors.password}<span class="error">{createForm.errors.password}</span>{/if}
                </div>
                <div class="field">
                    <label for="c-role" class="label">Peran</label>
                    <select id="c-role" class="input" bind:value={createForm.role}>
                        <option value="pengguna">Pengguna</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button class="btn btn-primary" disabled={createForm.processing}>
                    {#if createForm.processing}<Icon name="spinner" size={16} /> Menyimpan…{:else}Buat akun{/if}
                </button>
            </form>
        {/if}
    </div>
</AdminLayout>
