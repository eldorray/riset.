<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';
    import { logout } from '@/routes';
    import admin from '@/routes/admin';
    import projects from '@/routes/projects';

    let { showName = false }: { showName?: boolean } = $props();

    const user = $derived(page.props.auth.user);
    const id = $props.id();
</script>

{#if user}
    <button type="button" popovertarget="{id}-account" class="btn btn-ghost shrink-0 gap-2.5 px-1.5 sm:px-2">
        <span class="flex size-8 items-center justify-center rounded-full bg-surface text-xs font-semibold shadow-[0_0_0_1px_var(--color-line)]" aria-hidden="true">{user.name.slice(0, 2).toUpperCase()}</span>
        <span class={showName ? 'hidden text-sm font-medium sm:inline' : 'sr-only'}>{user.name}</span>
        <span class="sr-only">, menu akun</span>
    </button>
    <div id="{id}-account" popover class="sheet sheet-dropdown">
        <span class="mx-auto mb-3 block h-1 w-10 rounded-full bg-line-strong sm:hidden" aria-hidden="true"></span>
        <div class="flex flex-col border-b border-line px-3 pb-3">
            <span class="truncate text-sm font-semibold">{user.name}</span>
            <span class="truncate font-mono text-xs text-ink-3">{user.email}</span>
        </div>
        <nav aria-label="Akun" class="flex flex-col gap-0.5 pt-2">
            <Link href={projects.index().url} class="btn btn-ghost justify-start px-3"><Icon name="book" /> Proyek saya</Link>
            {#if user.is_admin}<Link href={admin.dashboard().url} class="btn btn-ghost justify-start px-3"><Icon name="shield" /> Panel admin</Link>{/if}
            <Link href="/account/subscription" class="btn btn-ghost justify-start px-3"><Icon name="grid" /> <span class="grow text-left">Paket & Kredit</span><span class="font-mono text-xs font-normal text-ink-3">{user.unlimited ? 'Unlimited' : `${user.credits} kredit`}</span></Link>
            <Link href="/account/password" class="btn btn-ghost justify-start px-3"><Icon name="pen" /> Ubah password</Link>
            <button type="button" class="btn btn-ghost justify-start px-3" onclick={() => router.post(logout().url)}><Icon name="logout" /> Keluar</button>
        </nav>
    </div>
{/if}
