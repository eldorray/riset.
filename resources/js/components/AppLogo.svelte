<script lang="ts">
    import { page } from '@inertiajs/svelte';
</script>

{#if page.props.logo_url}
    <img src={page.props.logo_url} alt="Logo {page.props.name}" class="inline-block h-9 max-w-36 object-contain object-left align-middle" />
{:else}
    Riset<span class="text-primary">.</span>
{/if}
