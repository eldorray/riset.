<script lang="ts">
    import AppLogo from '@/components/AppLogo.svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    import WritingProgress from '@/components/WritingProgress.svelte';
    import Icon from '@/components/Icon.svelte';
    import type { IconName } from '@/components/Icon.svelte';
    import AccountMenu from '@/components/AccountMenu.svelte';
    import { logout } from '@/routes';
    import admin from '@/routes/admin';
    import projects from '@/routes/projects';
    import type { ProjectSummary } from '@/types';

    type Section = 'ringkasan' | 'gap' | 'rancangan' | 'referensi' | 'sitasi' | 'kerangka' | 'draf' | 'naskah';

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
        { id: 'gap', label: 'Research Gap', icon: 'gap', href: `/projects/${project.id}/research-gap`, meta: '' },
        { id: 'rancangan', label: 'Rancangan penelitian', icon: 'target', href: `/projects/${project.id}/rancangan`, meta: project.design_ready ? (project.has_data || project.literature_study ? 'lengkap' : 'tanpa data') : '' },
        { id: 'kerangka', label: 'Kerangka', icon: 'outline', href: projects.outline(project.id).url, meta: project.chapters ? `${project.chapters} bab` : '' },
        { id: 'draf', label: 'Draf', icon: 'pen', href: projects.draft(project.id).url, meta: project.units ? `${project.filled}/${project.units}` : '' },
        { id: 'sitasi', label: 'Sitasi', icon: 'list', href: projects.citations(project.id).url, meta: '' },
        { id: 'naskah', label: 'Naskah lengkap', icon: 'file', href: projects.manuscript(project.id).url, meta: `${project.front_filled}/${project.front_parts}` },
    ]);
    const current = $derived(items.find((item) => item.id === active) ?? items[0]);
</script>

<AppHead {title} />

<div class="flex min-h-dvh flex-col bg-paper text-ink lg:flex-row">
    <header class="mobile-header flex items-center gap-1 border-b border-line bg-sunken px-1.5 pb-2 lg:hidden">
        <Link href={projects.index().url} class="btn btn-ghost btn-icon shrink-0" aria-label="Semua proyek"><Icon name="back" size={20} /></Link>
        <button type="button" popovertarget="project-menu" class="flex min-h-11 min-w-0 grow flex-col items-center justify-center rounded-md px-2 hover:bg-surface/60">
            <span class="sr-only">Menu proyek:</span>
            <span class="max-w-full truncate text-[11px] leading-tight text-ink-3">{project.title}</span>
            <span class="flex items-center gap-1 text-base leading-tight font-semibold">{current.label}<Icon name="caret" size={16} /></span>
        </button>
        <AccountMenu />
    </header>
    <div id="project-menu" popover class="sheet">
        <span class="mx-auto mb-3 block h-1 w-10 rounded-full bg-line-strong sm:hidden" aria-hidden="true"></span>
        <div class="flex flex-col gap-2 border-b border-line px-1 pb-3">
            <span class="font-display text-xl leading-snug font-medium">{project.title}</span>
            <div class="flex flex-wrap gap-1.5">
                <span class="badge">{project.document_type.label}</span>
                {#if project.citation_style}<span class="badge">{project.citation_style.label}</span>{/if}
            </div>
        </div>
        <nav aria-label="Navigasi proyek seluler" class="flex flex-col gap-0.5 pt-2">
            {#each items as item (item.id)}
                <Link href={item.href} aria-current={item.id === active ? 'page' : undefined} class="flex min-h-12 items-center gap-3 rounded-md px-3 text-[15px] {item.id === active ? 'bg-primary-soft font-semibold text-primary' : 'font-medium text-ink'}">
                    <Icon name={item.icon} />
                    <span class="grow">{item.label}</span>
                    <span class="font-mono text-xs font-normal text-ink-3">{item.meta}</span>
                </Link>
            {/each}
            <Link href={projects.index().url} class="mt-1 flex min-h-12 items-center gap-3 rounded-md border-t border-line px-3 text-[15px] font-medium text-ink-2"><Icon name="back" /> Semua proyek</Link>
        </nav>
    </div>
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
                <Link href="/account/subscription" class="btn btn-ghost justify-start px-3 font-medium text-ink-2"><Icon name="grid" /> <span class="grow text-left">Paket & Kredit</span><span class="font-mono text-xs font-normal text-ink-3">{user.unlimited ? 'Unlimited' : `${user.credits} kredit`}</span></Link>
                <Link href="/account/password" class="btn btn-ghost justify-start px-3 font-medium text-ink-2"><Icon name="pen" /> Ubah password</Link>
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
