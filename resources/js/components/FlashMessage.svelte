<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';

    let dismissed = $state<object | null>(null);

    const flash = $derived(page.flash);
    const visible = $derived(
        dismissed !== flash && Boolean(flash?.success || flash?.error),
    );

    $effect(() => {
        if (!flash?.success) {
            return;
        }

        const current = flash;
        // Pesan dengan "Batalkan" tampil lebih lama agar sempat diklik.
        const timer = setTimeout(() => (dismissed = current), current.undo ? 8000 : 4000);

        return () => clearTimeout(timer);
    });
</script>

{#if visible}
    <div
        class="mobile-flash alert fixed right-4 bottom-4 left-4 sm:left-auto sm:right-6 sm:bottom-6 z-50 max-w-md shadow-[0_8px_24px_rgba(27,31,42,0.12)] {flash?.error
            ? 'alert-danger'
            : 'alert-ok'}"
        role={flash?.error ? 'alert' : 'status'}
    >
        <Icon
            name={flash?.error ? 'error' : 'check'}
            class={flash?.error ? 'text-danger' : 'text-ok'}
        />
        <p class="grow">{flash?.error ?? flash?.success}</p>
        {#if flash?.undo && !flash.error}
            <button
                type="button"
                class="-my-2 min-h-11 shrink-0 rounded-md px-3 text-sm font-semibold text-primary underline-offset-2 hover:underline"
                onclick={() => {
                    const url = flash?.undo;
                    dismissed = flash;

                    if (url) {
                        router.post(url, {}, { preserveScroll: true });
                    }
                }}
            >
                Batalkan
            </button>
        {/if}
        <button
            type="button"
            class="-my-3 -mr-3 flex size-11 items-center justify-center rounded-md hover:bg-black/5"
            aria-label="Tutup pesan"
            onclick={() => (dismissed = flash)}
        >
            <Icon name="x" size={16} />
        </button>
    </div>
{/if}
