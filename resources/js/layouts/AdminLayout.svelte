<script lang="ts">
    import AppLogo from '@/components/AppLogo.svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    import Icon from '@/components/Icon.svelte';
    import type { IconName } from '@/components/Icon.svelte';
    import { logout } from '@/routes';
    import admin from '@/routes/admin';
    import projects from '@/routes/projects';

    type Section = 'logo' | 'billing' | 'ringkasan' | 'pengguna' | 'sitasi' | 'jenis' | 'template';

    let {
        active,
        title,
        children,
    }: { active: Section; title: string; children: Snippet } = $props();

    const user = $derived(page.props.auth.user);

    const groups: { label: string; items: { id: Section; label: string; icon: IconName; href: string }[] }[] = [
        { label: 'Umum', items: [{ id: 'ringkasan', label: 'Ringkasan', icon: 'grid', href: admin.dashboard().url }] },
        { label: 'Akses', items: [{ id: 'billing', label: 'Subscription & Kredit', icon: 'users', href: '/admin/billing' }, { id: 'pengguna', label: 'Pengguna', icon: 'users', href: admin.users.index().url }] },
        {
            label: 'Konfigurasi',
            items: [
                { id: 'logo', label: 'Logo aplikasi', icon: 'file', href: '/admin/branding' },
                { id: 'sitasi', label: 'Gaya sitasi', icon: 'list', href: admin.citationStyles.index().url },
                { id: 'jenis', label: 'Jenis tulisan', icon: 'outline', href: admin.documentTypes.index().url },
                { id: 'template', label: 'Template Word', icon: 'file', href: admin.templates.index().url },
            ],
        },
    ];
</script>

<AppHead title="Admin · {title}" />

<div class="flex min-h-dvh flex-col bg-paper text-ink lg:flex-row">
    <header class="mobile-header border-b border-line bg-sunken px-4 py-3 lg:hidden">
        <div class="flex min-w-0 items-center justify-between gap-3">
            <span class="font-display text-2xl font-medium"><AppLogo /></span>
            <span class="min-w-0 truncate text-sm text-ink-2">Admin · {title}</span>
        </div>
        <details class="mt-2">
            <summary class="min-h-11 cursor-pointer rounded-md border border-line-strong bg-surface px-3 py-2.5 text-sm font-semibold">Menu admin</summary>
            <nav aria-label="Navigasi admin seluler" class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                {#each groups as group (group.label)}{#each group.items as item (item.id)}<Link href={item.href} aria-current={item.id === active ? 'page' : undefined} class="btn btn-ghost justify-start {item.id === active ? 'bg-surface text-primary' : ''}"><Icon name={item.icon} />{item.label}</Link>{/each}{/each}<Link href={projects.index().url} class="btn btn-ghost justify-start">Proyek saya</Link>
                <Link href="/account/subscription" class="btn btn-ghost">Paket & Kredit · {user?.unlimited ? 'Unlimited' : `${user?.credits ?? 0} kredit`}</Link>
                <Link href="/account/password" class="btn btn-ghost justify-start">Ubah password</Link>
                <button type="button" class="btn btn-ghost justify-start" onclick={() => router.post(logout().url)}>Keluar</button>
            </nav>
        </details>
    </header>
    <aside class="sticky top-0 hidden h-dvh w-62 shrink-0 flex-col gap-8 border-r border-line bg-sunken px-4 pt-7 pb-5 lg:flex overflow-y-auto">
        <div class="flex items-center gap-2.5 px-3">
            <span class="font-display text-[30px] leading-none font-medium tracking-tight"><AppLogo /></span>
            <span class="rounded border border-line-strong px-1.5 py-0.5 font-mono text-[11px] tracking-wider text-ink-2">ADMIN</span>
        </div>

        <nav aria-label="Navigasi admin" class="flex grow flex-col gap-6">
            {#each groups as group (group.label)}
                <div class="flex flex-col gap-1">
                    <span class="section-label px-3 pb-1.5 text-[11px] text-ink-3">{group.label}</span>
                    {#each group.items as item (item.id)}
                        <Link
                            href={item.href}
                            aria-current={item.id === active ? 'page' : undefined}
                            class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm {item.id === active
                                ? 'bg-surface font-semibold shadow-[0_0_0_1px_var(--color-line)]'
                                : 'font-medium text-ink-2 hover:bg-surface/60'}"
                        >
                            <Icon name={item.icon} />
                            {item.label}
                        </Link>
                    {/each}
                </div>
            {/each}
        </nav>

        <div class="flex flex-col gap-1.5 border-t border-line pt-4">
            {#if user}
                <div class="flex items-center gap-3 px-3 pb-1.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-ink text-[13px] font-semibold text-white" aria-hidden="true">{user.name.slice(0, 2).toUpperCase()}</span>
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-semibold">{user.name}</span>
                        <span class="truncate font-mono text-xs text-ink-3">{user.email}</span>
                    </div>
                </div>
            {/if}
            <Link href={projects.index().url} class="btn btn-ghost justify-start px-3 font-medium text-ink-2"><Icon name="book" /> Proyek saya</Link>
            <Link href="/account/subscription" class="btn btn-ghost">Paket & Kredit · {user?.unlimited ? 'Unlimited' : `${user?.credits ?? 0} kredit`}</Link>
                <Link href="/account/password" class="btn btn-ghost justify-start px-3">Ubah password</Link>
            <button type="button" class="btn btn-ghost justify-start px-3 font-medium text-ink-2" onclick={() => router.post(logout().url)}>
                <Icon name="logout" /> Keluar
            </button>
        </div>
    </aside>

    <main class="app-content flex min-w-0 grow flex-col gap-6 px-4 py-6 sm:px-6 lg:px-12 lg:py-10">
        {@render children()}
    </main>
</div>

<FlashMessage />
