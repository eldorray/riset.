<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import { estimateCredits } from '@/lib/credits';
    let { inputCharacters = 3000, outputWords = 500, requests = 1, detail = '' }: { inputCharacters?: number; outputWords?: number; requests?: number; detail?: string } = $props();
    const estimate = $derived(estimateCredits(inputCharacters, outputWords, requests));
    const user = $derived(page.props.auth.user);
</script>

{#if requests > 0}
    <div class="rounded-lg border border-primary-line bg-primary-soft/35 px-3 py-2.5 text-xs leading-relaxed">
        {#if user?.unlimited}<p class="font-semibold text-primary">Akses Unlimited · kredit tidak dipotong</p>
        {:else}
            <p class="font-semibold text-ink">Perkiraan teks: {estimate.min === estimate.max ? estimate.min : `${estimate.min}–${estimate.max}`} kredit{requests > 1 ? ` untuk ${requests} proses AI` : ' per proses AI'}</p>
            <p class="mt-1 text-ink-2">{detail} Perkiraan belum termasuk reasoning dan pembacaan sumber tambahan. Pemakaian aktual dapat lebih tinggi; sisa cadangan dikembalikan.</p>
            <Link href="/account/subscription" class="mt-1 inline-block font-medium text-primary underline">{user?.ai_active ? `Lihat paket · ${user.credits} kredit tersedia` : 'Aktifkan paket untuk menggunakan AI'}</Link>
        {/if}
    </div>
{/if}
