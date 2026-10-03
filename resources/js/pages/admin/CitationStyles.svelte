<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import admin from '@/routes/admin';
    import type { Segment } from '@/types';

    type Style = { value: string; label: string; description: string; enabled: boolean; in_text: string; entry: Segment[] };

    let { styles }: { styles: Style[] } = $props();

    const form = useForm({ enabled: [] as string[] });

    $effect(() => {
        const enabled = styles.filter((s) => s.enabled).map((s) => s.value);
        untrack(() => {
            form.enabled = enabled;
            form.defaults();
        });
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.submit(admin.citationStyles.update(), { preserveScroll: true });
    }
</script>

<AdminLayout active="sitasi" title="Gaya sitasi">
    <header class="flex items-end justify-between gap-6 border-b border-line pb-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">Admin / Konfigurasi / Gaya sitasi</span>
            <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Gaya sitasi</h1>
            <p class="max-w-2xl text-[15px] text-ink-2">
                Pilih gaya yang boleh dipilih pengguna. Proyek yang sudah memakai gaya nonaktif tetap bisa mengekspor dengan gaya itu.
            </p>
        </div>
    </header>

    <form class="flex flex-col gap-4" onsubmit={submit}>
        {#if form.errors.enabled}
            <div class="alert alert-danger" role="alert"><p>{form.errors.enabled}</p></div>
        {/if}

        <fieldset class="flex flex-col gap-3">
            <legend class="sr-only">Gaya sitasi aktif</legend>
            {#each styles as style (style.value)}
                <label class="card flex cursor-pointer items-start gap-4 px-6 py-5 {form.enabled.includes(style.value) ? 'border-primary shadow-[0_0_0_1px_var(--color-primary)]' : ''}">
                    <input type="checkbox" value={style.value} bind:group={form.enabled} class="mt-1 size-5 shrink-0 accent-primary" />
                    <span class="flex min-w-0 grow flex-col gap-2">
                        <span class="flex flex-wrap items-center gap-3">
                            <span class="text-base font-semibold">{style.label}</span>
                            <span class="badge {form.enabled.includes(style.value) ? 'badge-ok' : 'badge-empty'}">{form.enabled.includes(style.value) ? 'Aktif' : 'Nonaktif'}</span>
                        </span>
                        <span class="text-sm text-ink-2">{style.description}</span>
                        <span class="flex flex-col gap-1.5 rounded-lg bg-paper px-4 py-3">
                            <span class="text-xs font-semibold text-ink-3">Contoh (metadata contoh, bukan referensi sungguhan)</span>
                            <span class="font-display text-[15px] leading-relaxed">Dalam teks: <span class="cite">{style.in_text}</span></span>
                            <span class="pl-6 -indent-6 font-display text-[15px] leading-relaxed">
                                {#each style.entry as segment, j (j)}{#if segment.italic}<em>{segment.text}</em>{:else}{segment.text}{/if}{/each}
                            </span>
                        </span>
                    </span>
                </label>
            {/each}
        </fieldset>

        <div class="flex items-center justify-end gap-3">
            <span class="text-[13px] text-ink-2">{form.enabled.length} dari {styles.length} gaya aktif</span>
            <button class="btn btn-primary" disabled={form.processing || !form.isDirty}>Simpan</button>
        </div>
    </form>
</AdminLayout>
