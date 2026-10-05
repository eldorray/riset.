<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import { creditLabel, estimateCredits } from '@/lib/credits';
    let { inputCharacters = 3000, outputWords = 500, requests = 1, detail = '' }: { inputCharacters?: number; outputWords?: number; requests?: number; detail?: string } = $props();
    const estimate = $derived(estimateCredits(inputCharacters, outputWords, requests));
    const user = $derived(page.props.auth.user);
</script>

<!-- Ringkas: angka menempel di aksi AI; penjelasan lengkap cukup di Paket & Kredit. -->
{#if requests > 0}
    {#if user?.unlimited}
        <p class="text-xs font-medium text-primary">Unlimited · kredit tidak dipotong</p>
    {:else}
        <details class="text-xs text-ink-2">
            <summary class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-x-1.5 gap-y-1 [&::-webkit-details-marker]:hidden">
                <span class="rounded-full bg-primary-soft px-2 py-0.5 font-semibold text-primary">{creditLabel(estimate)}</span>
                <span>{requests > 1 ? `${requests} proses AI · ` : ''}{user?.ai_active ? `sisa ${user.credits} kredit · ` : ''}<span class="text-primary underline">Cara hitung</span></span>
            </summary>
            <p class="mt-1 leading-relaxed">{detail} Perkiraan belum termasuk reasoning dan pembacaan sumber tambahan; sisa cadangan dikembalikan. <Link href="/account/subscription#cara-hitung" class="text-primary underline">Rincian di Paket & Kredit</Link></p>
        </details>
        {#if !user?.ai_active}<Link href="/account/subscription" class="inline-flex min-h-11 items-center text-xs font-medium text-primary underline">Aktifkan paket untuk memakai AI</Link>{/if}
    {/if}
{/if}
