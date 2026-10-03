<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { createWriting } from '@/lib/writing.svelte';
    let { projectId }: { projectId: number } = $props();
    const writing = createWriting(() => projectId);
    let actionError = $state('');
</script>

{#if writing.run && (writing.active || writing.suggestions.length)}
    <aside class="rounded-lg border border-ai-line bg-ai-wash px-4 py-3 text-sm" aria-label="Penulisan latar belakang">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div role="status">
                <p class="font-semibold text-ai">{writing.active ? (writing.run.status === 'queued' ? 'AI menunggu giliran' : writing.run.kind === 'gap' ? 'AI sedang menganalisis sumber' : 'AI sedang menulis') : 'Usulan AI siap diperiksa'} · {writing.run.done}/{writing.run.total}</p>
                <p class="text-ink-2">{writing.active ? `${writing.run.label}. Anda boleh berpindah menu.` : `${writing.suggestions.length} usulan tersimpan.`}</p>
            </div>
            <Link class="btn btn-secondary" href={`/projects/${projectId}/${writing.run.kind === 'gap' ? 'research-gap' : writing.run.kind.startsWith('draft') ? 'draft' : 'manuscript'}`}>Lihat hasil</Link>
            {#if writing.active}<button class="btn btn-ghost" type="button" disabled={writing.run.stop_requested} onclick={async () => { try { await writing.stop(); } catch(e) { actionError = (e as Error).message; } }}>{writing.run.stop_requested ? 'Berhenti setelah bagian ini' : 'Hentikan'}</button>{/if}
        </div>
        {#if writing.run.status === 'queued'}<p class="mt-1 text-xs text-ink-2">Jika lama menunggu, hubungi admin untuk memeriksa layanan penulisan.</p>{/if}
        {#if actionError}<p class="text-danger" role="alert">{actionError}</p>{/if}
    </aside>
{/if}
