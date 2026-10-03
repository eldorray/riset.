<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Icon from '@/components/Icon.svelte';
    import { postDownload } from '@/lib/download';
    import projects from '@/routes/projects';
    import type { ExportFormat } from '@/types';

    let { projectId, readiness, hasStyle, format, template = null, disabled = false, label = 'Ekspor .docx' }: {
        projectId: number;
        readiness: { units: number; empty: string[]; empty_href: string; ai_unreviewed: number; review_href: string; cited_incomplete: { id: number; title: string; missing: string[] }[]; unknown: number; front_missing: string[] };
        hasStyle: boolean;
        format: ExportFormat;
        template?: string | null;
        disabled?: boolean;
        label?: string;
    } = $props();
    let dialog: HTMLDialogElement | undefined = $state();
    let exporting = $state(false);
    let failed = $state(false);
    const warnings = $derived(readiness.empty.length || readiness.ai_unreviewed || readiness.cited_incomplete.length || readiness.unknown || readiness.front_missing.length || !hasStyle);

    async function download() {
        exporting = true;
        failed = false;
        try {
            if (await postDownload(projects.export(projectId).url)) dialog?.close();
            else { failed = true; router.reload(); }
        } catch { failed = true; }
        finally { exporting = false; }
    }
</script>

<button type="button" class="btn btn-secondary" onclick={() => { failed = false; dialog?.showModal(); }} {disabled}><Icon name="download" size={16} /> {label}</button>
<dialog bind:this={dialog} aria-label="Pemeriksaan sebelum ekspor Word" class="m-auto max-h-[90dvh] w-[560px] max-w-[calc(100vw-32px)] overflow-y-auto rounded-[14px] bg-surface p-5 text-ink shadow-xl backdrop:bg-ink/45 sm:p-7">
    <div class="flex flex-col gap-4">
        <h2 class="font-display text-[28px] font-medium">Periksa sebelum ekspor</h2>
        <p class="text-sm text-ink-2">File memakai isi yang sudah tersimpan dan format {template ?? 'bawaan'}. Periksa kembali terhadap panduan kampus atau jurnal.</p>
        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 rounded-lg bg-paper p-4 text-sm">
            <dt class="text-ink-2">Format</dt><dd class="font-semibold">{format.name}</dd>
            <dt class="text-ink-2">Daftar isi</dt><dd>{format.table_of_contents ? 'Disertakan · diperbarui melalui Word' : 'Tidak disertakan'}</dd>
            <dt class="text-ink-2">Halaman judul</dt><dd>{format.title_page ? 'Halaman tersendiri' : 'Judul di awal naskah'}</dd>
            <dt class="text-ink-2">Gaya sitasi</dt><dd>{format.citation_style}</dd>
            <dt class="text-ink-2">Font</dt><dd>{format.font}</dd>
        </dl>
        {#if format.table_of_contents}<p class="help">Jika daftar isi belum tampil di Word, klik kanan area daftar isi, pilih Update Field, lalu Update entire table.</p>{/if}
        <Link href={`${projects.show(projectId).url}#template`} onclick={() => dialog?.close()} class="self-start text-sm text-primary underline">Ubah format Word di Ringkasan</Link>
        {#if warnings}
            <ul class="flex list-disc flex-col gap-2 pl-5 text-sm">
                {#if readiness.empty.length}<li><Link onclick={() => dialog?.close()} href={readiness.empty_href} class="text-primary underline">{readiness.empty.length} dari {readiness.units} bagian kosong · buka bagian pertama</Link></li>{/if}
                {#if readiness.ai_unreviewed}<li><Link onclick={() => dialog?.close()} href={readiness.review_href} class="text-primary underline">{readiness.ai_unreviewed} bagian AI belum ditinjau · periksa</Link></li>{/if}
                {#each readiness.cited_incomplete as ref (ref.id)}<li><Link onclick={() => dialog?.close()} href={`${projects.references.index(projectId).url}?edit=${ref.id}`} class="text-primary underline">{ref.title}: lengkapi {ref.missing.join(', ').toLowerCase()}</Link></li>{/each}
                {#if readiness.unknown}<li><Link onclick={() => dialog?.close()} href={projects.citations(projectId).url} class="text-primary underline">{readiness.unknown} sitasi tidak dikenal · periksa</Link></li>{/if}
                {#if readiness.front_missing.length}<li><Link onclick={() => dialog?.close()} href={`${projects.manuscript(projectId).url}#bagian-awal`} class="text-primary underline">Lengkapi {readiness.front_missing.join(', ')}</Link></li>{/if}
                {#if !hasStyle}<li><Link onclick={() => dialog?.close()} href={projects.citations(projectId).url} class="text-primary underline">Pilih gaya sitasi; saat ini memakai APA 7</Link></li>{/if}
            </ul>
        {:else}<p class="text-sm text-ok">Daftar periksa lengkap. Tetap tinjau file Word sebelum diajukan.</p>{/if}
        {#if failed}<p class="error" role="alert">File Word gagal dibuat. Coba lagi.</p>{/if}
        <div class="flex flex-wrap justify-end gap-3"><button type="button" class="btn btn-secondary" onclick={() => dialog?.close()}>Tinjau dulu</button><button type="button" class="btn btn-primary" onclick={download} disabled={exporting}>{exporting ? 'Menyusun file…' : warnings ? 'Tetap unduh Word' : 'Unduh Word'}</button></div>
    </div>
</dialog>
