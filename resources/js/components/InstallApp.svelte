<script lang="ts">
    import Icon from '@/components/Icon.svelte';
    import { installPwa, pwa } from '@/lib/pwa.svelte';
</script>

{#if pwa.installable}
    <button type="button" class="btn btn-ghost justify-start px-3" onclick={installPwa}><Icon name="download" /> Pasang aplikasi</button>
{:else if pwa.ios}
    <p class="flex gap-3 px-3 py-2 text-xs leading-relaxed text-ink-2"><Icon name="download" size={16} class="mt-0.5" /> <span>Pasang di iPhone: ketuk ikon Bagikan di Safari, lalu pilih <span class="font-semibold">Tambahkan ke Layar Utama</span>.</span></p>
{/if}
