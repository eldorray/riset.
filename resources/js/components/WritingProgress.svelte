<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { createWriting } from '@/lib/writing.svelte';
    let { projectId }: { projectId: number } = $props();
    const writing = createWriting(() => projectId);
    let actionError = $state('');
</script>

{#if writing.run && (writing.active || writing.suggestions.length)}
    {@const run = writing.run}
    {@const detail = writing.active ? `${run.label}. Anda boleh berpindah menu.` : `${writing.suggestions.length} usulan tersimpan.`}
    <aside class="writing-banner relative overflow-hidden rounded-lg border border-ai-line bg-ai-wash px-4 py-3 text-sm" aria-label="Penulisan latar belakang">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <div role="status" class="min-w-0 grow basis-72">
                <p class="flex items-center gap-2 font-semibold text-ai">
                    {#if writing.active}<span class="live-dot" aria-hidden="true"></span>{/if}
                    <span>{writing.active ? (run.status === 'queued' ? 'AI menunggu giliran' : run.kind === 'gap' ? 'AI sedang menganalisis sumber' : 'AI sedang menulis') : 'Usulan AI siap ditinjau'} · <span class="tabular-nums">{run.done}/{run.total}</span></span>
                </p>
                <!-- Kunci per label: langkah baru masuk lembut, bukan meloncat. -->
                {#key detail}<p class="writing-banner-line text-ink-2">{detail}</p>{/key}
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <Link class="btn btn-secondary" href={`/projects/${projectId}/${run.kind === 'gap' ? 'research-gap' : run.kind.startsWith('draft') ? 'draft' : 'manuscript'}`}>Lihat hasil</Link>
                {#if writing.active}<button class="btn btn-ghost" type="button" disabled={run.stop_requested} onclick={async () => { try { await writing.stop(); } catch(e) { actionError = (e as Error).message; } }}>{run.stop_requested ? 'Berhenti setelah bagian ini' : 'Hentikan'}</button>{/if}
            </div>
        </div>
        {#if run.status === 'queued'}<p class="mt-1 text-xs text-ink-2">Jika lama menunggu, hubungi admin untuk memeriksa layanan penulisan.</p>{/if}
        {#if actionError}<p class="text-danger" role="alert">{actionError}</p>{/if}
        {#if writing.active}
            <div class="absolute inset-x-0 bottom-0 h-0.5 bg-ai-line/60" aria-hidden="true">
                <div class="writing-progress h-full bg-ai" style="transform: scaleX({run.total ? run.done / run.total : 0})"></div>
            </div>
        {/if}
    </aside>
{/if}
