<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import AccountMenu from '@/components/AccountMenu.svelte';
    import projects from '@/routes/projects';
</script>

<header class="flex min-h-16 items-center justify-between gap-3 border-b border-line px-4 pt-[env(safe-area-inset-top)] sm:px-6 lg:px-16">
    <Link href={projects.index().url} class="flex min-h-11 items-center font-display text-[28px] leading-none font-medium tracking-tight" aria-label="Proyek saya"><AppLogo /></Link>
    <AccountMenu showName />
</header>
