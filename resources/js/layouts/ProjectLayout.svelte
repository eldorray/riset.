<script lang="ts">
    import AppLogo from '@/components/AppLogo.svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    import WritingProgress from '@/components/WritingProgress.svelte';
    import Icon from '@/components/Icon.svelte';
    import type { IconName } from '@/components/Icon.svelte';
    import { logout } from '@/routes';
    import admin from '@/routes/admin';
    import projects from '@/routes/projects';
    import type { ProjectSummary } from '@/types';

    type Section = 'ringkasan' | 'referensi' | 'sitasi' | 'kerangka' | 'draf' | 'naskah';

    let {
        project,
        active,
        title,
        children,
    }: {
        project: ProjectSummary;
        active: Section;
        title: string;
        children: Snippet;
    } = $props();

    const user = $derived(page.props.auth.user);

    const items = $derived<
        { id: Section; label: string; icon: IconName; href: string; meta: string }[]
    >([
        { id: 'ringkasan', label: 'Ringkasan', icon: 'grid', href: projects.show(project.id).url, meta: '' },
        { id: 'referensi', label: 'Referensi', icon: 'book', href: projects.references.index(project.id).url, meta: String(project.references_count) },
        { id: 'sitasi', label: 'Sitasi', icon: 'list', href: projects.citations(project.id).url, meta: '' },
        { id: 'kerangka', label: 'Kerangka', icon: 'outline', href: projects.outline(project.id).url, meta: project.chapters ? `${project.chapters} bab` : '' },
        { id: 'draf', label: 'Draf', icon: 'pen', href: projects.draft(project.id).url, meta: project.units ? `${project.filled}/${project.units}` : '' },
        { id: 'naskah', label: 'Naskah lengkap', icon: 'file', href: projects.manuscript(project.id).url, meta: `${project.front_filled}/${project.front_parts}` },
    ]);
</script>

<AppHead {title} />

<div class="flex min-h-dvh flex-col bg-paper text-ink lg:flex-row">
    <header class="mobile-header border-b border-line bg-sunken px-4 py-3 lg:hidden">
        <div class="flex min-w-0 items-center justify-between gap-3">
            <span class="font-display text-2xl font-medium"><AppLogo /></span>
            <span class="min-w-0 truncate text-sm text-ink-2">{project.title}</span>
        </div>
        <details class="mt-2">
            <summary class="min-h-11 cursor-pointer rounded-md border border-line-strong bg-surface px-3 py-2.5 text-sm font-semibold">Menu proyek</summary>
            <nav aria-label="Navigasi proyek seluler" class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                {#each items as item (item.id)}<Link href={item.href} aria-current={item.id === active ? 'page' : undefined} class="btn btn-ghost justify-start {item.id === active ? 'bg-surface text-primary' : ''}"><Icon name={item.icon} />{item.label}</Link>{/each}<Link href={projects.index().url} class="btn btn-ghost justify-start">Semua proyek</Link>{#if user?.is_admin}<Link href={admin.dashboard().url} class="btn btn-ghost justify-start">Panel admin</Link>{/if}
                <Link href="/account/subscription" class="btn btn-ghost">Paket & Kredit · {user?.unlimited ? 'Unlimited' : `${user?.credits ?? 0} kredit`}</Link>
                <Link href="/account/password" class="btn btn-ghost justify-start">Ubah password</Link>
                <button type="button" class="btn btn-ghost justify-start" onclick={() => router.post(logout().url)}>Keluar</button>
            </nav>
        </details>
    </header>
    <aside
        class="sticky top-0 hidden h-dvh w-62 shrink-0 flex-col gap-6 border-r border-line bg-sunken px-4 pt-6 pb-5 lg:flex overflow-y-auto"
    >
        <div class="flex flex-col gap-3">
            <Link
                href={projects.index().url}
                class="px-3 font-display text-[26px] leading-none font-medium tracking-tight"
            >
                <AppLogo />
            </Link>
            <Link href={projects.index().url} class="btn btn-ghost justify-start px-3 text-ink-2">
                <Icon name="back" size={16} /> Semua proyek
            </Link>
        </div>

        <div class="flex flex-col gap-2.5 border-y border-line px-3 py-4">
            <span class="section-label text-[11px]">Proyek</span>
            <span class="font-display text-[19px] leading-snug font-medium">{project.title}</span>
            <div class="flex flex-wrap gap-1.5">
                <span class="badge bg-surface">{project.document_type.label}</span>
                {#if project.citation_style}
                    <span class="badge bg-surface">{project.citation_style.label}</span>
                {/if}
            </div>
        </div>

        <nav aria-label="Navigasi proyek" class="flex grow flex-col gap-1">
            {#each items as item (item.id)}
                <Link
                    href={item.href}
                    aria-current={item.id === active ? 'page' : undefined}
                    class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm {item.id === active
                        ? 'bg-surface font-semibold text-ink shadow-[0_0_0_1px_var(--color-line)]'
                        : 'font-medium text-ink-2 hover:bg-surface/60'}"
                >
                    <Icon name={item.icon} />
                    <span class="grow">{item.label}</span>
                    <span class="font-mono text-xs font-normal text-ink-3">{item.meta}</span>
                </Link>
            {/each}
        </nav>

        {#if user}
            <div class="flex flex-col gap-2 border-t border-line pt-4">
                <div class="flex items-center gap-3 px-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface text-[13px] font-semibold"
                        aria-hidden="true">{user.name.slice(0, 2).toUpperCase()}</span
                    >
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-semibold">{user.name}</span>
                        <span class="truncate font-mono text-xs text-ink-3">{user.email}</span>
                    </div>
                </div>
                {#if user.is_admin}
                    <Link href={admin.dashboard().url} class="btn btn-ghost justify-start px-3 font-medium text-ink-2"><Icon name="shield" /> Panel admin</Link>
                {/if}
                <Link href="/account/subscription" class="btn btn-ghost">Paket & Kredit · {user?.unlimited ? 'Unlimited' : `${user?.credits ?? 0} kredit`}</Link>
                <Link href="/account/password" class="btn btn-ghost justify-start px-3">Ubah password</Link>
                <button
                    type="button"
                    class="btn btn-ghost justify-start px-3 font-medium text-ink-2"
                    onclick={() => router.post(logout().url)}
                >
                    <Icon name="logout" /> Keluar
                </button>
            </div>
        {/if}
    </aside>

    <main class="app-content flex min-w-0 grow flex-col gap-6 px-4 py-6 sm:px-6 lg:px-12 lg:py-10">
        <WritingProgress projectId={project.id} />
        {@render children()}
    </main>
</div>

<FlashMessage />
