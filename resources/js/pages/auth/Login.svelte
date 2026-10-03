<script lang="ts">
    import { Link, page, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';
    import Icon from '@/components/Icon.svelte';
    import auth from '@/routes/auth';
    import login from '@/routes/login';

    let { googleConfigured }: { googleConfigured: boolean } = $props();

    let redirecting = $state(false);
    const error = $derived(page.flash?.error);
    const form = useForm({ email: '', password: '', remember: true });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.submit(login.password(), { onFinish: () => form.reset('password') });
    }

    const steps = [
        'Simpan referensi lengkap dengan tautan asalnya',
        'Periksa kerangka dan draf AI bagian demi bagian',
        'Ekspor ke Word beserta sitasi dan daftar pustaka',
    ];
</script>

<AppHead title="Masuk" />

<div class="grid min-h-dvh grid-cols-1 bg-paper text-ink lg:grid-cols-[minmax(0,1fr)_560px]">
    <section class="hidden flex-col justify-between border-r border-line px-20 py-16 lg:flex">
        <Link href="/" aria-label="Riset, halaman awal" class="font-display text-[32px] leading-none font-medium tracking-tight">Riset<span class="text-primary">.</span></Link>
        <div class="flex flex-col gap-10">
            <h2 class="max-w-2xl font-display text-6xl leading-[1.08] font-medium tracking-tight">
                Susun karya ilmiah dari sumber yang bisa Anda periksa.
            </h2>
            <ol class="grid max-w-3xl grid-cols-3 gap-7">
                {#each steps as step, i (step)}
                    <li class="flex flex-col gap-2 border-t-2 border-ink pt-3.5">
                        <span class="font-mono text-xs text-ink-3">0{i + 1}</span>
                        <span class="text-[15px] leading-normal">{step}</span>
                    </li>
                {/each}
            </ol>
        </div>
        <span class="text-[13px] text-ink-3">Untuk skripsi, tesis, karya ilmiah, dan artikel.</span>
    </section>

    <section class="flex flex-col justify-center bg-surface px-6 py-16 sm:px-16">
        <div class="flex max-w-100 flex-col gap-6">
            <div class="flex flex-col gap-2.5">
                <Link href="/" aria-label="Riset, halaman awal" class="font-display text-[28px] leading-none font-medium tracking-tight lg:hidden">Riset<span class="text-primary">.</span></Link>
                <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Masuk</h1>
                <p class="text-[15px] leading-relaxed text-ink-2">
                    Gunakan akun Google, atau email dan password dari admin.
                </p>
            </div>

            {#if error}
                <div class="alert alert-danger" role="alert">
                    <Icon name="error" class="text-danger" />
                    <p>{error}</p>
                </div>
            {/if}

            {#if !googleConfigured}
                <p class="text-[13px] leading-normal text-ink-3">
                    Masuk dengan Google belum dikonfigurasi (<code class="font-mono text-xs">GOOGLE_CLIENT_ID</code> di <code class="font-mono text-xs">.env</code>).
                </p>
            {/if}

            <!-- Tautan biasa, bukan Inertia: Google butuh redirect halaman penuh. -->
            <a
                href={auth.google().url}
                class="btn btn-secondary h-13 text-[15px] {redirecting || !googleConfigured ? 'pointer-events-none opacity-60' : ''}"
                aria-disabled={redirecting || !googleConfigured}
                onclick={() => (redirecting = true)}
            >
                {#if redirecting}
                    <Icon name="spinner" /> Menghubungkan ke Google…
                {:else}
                    {error ? 'Coba masuk lagi dengan Google' : 'Masuk dengan Google'}
                {/if}
            </a>

            <div class="flex items-center gap-3 text-xs text-ink-3" aria-hidden="true">
                <span class="h-px grow bg-line"></span>atau<span class="h-px grow bg-line"></span>
            </div>

            <form class="flex flex-col gap-4" onsubmit={submit} novalidate aria-label="Masuk dengan email">
                <div class="field">
                    <label for="email" class="label">Email</label>
                    <input id="email" type="email" autocomplete="username" class="input" bind:value={form.email} aria-invalid={form.errors.email ? 'true' : undefined} aria-describedby={form.errors.email ? 'email-error' : undefined} />
                    {#if form.errors.email}<span id="email-error" class="error">{form.errors.email}</span>{/if}
                </div>
                <div class="field">
                    <label for="password" class="label">Password</label>
                    <input id="password" type="password" autocomplete="current-password" class="input" bind:value={form.password} aria-invalid={form.errors.password ? 'true' : undefined} />
                    {#if form.errors.password}<span class="error">{form.errors.password}</span>{/if}
                </div>
                <label class="flex min-h-11 items-center gap-2.5 text-sm">
                    <input type="checkbox" bind:checked={form.remember} class="size-4.5 accent-primary" /> Ingat saya
                </label>
                <button type="submit" class="btn btn-primary h-12 text-[15px]" disabled={form.processing}>
                    {#if form.processing}<Icon name="spinner" size={16} /> Memeriksa…{:else}Masuk{/if}
                </button>
                <Link href="/forgot-password" class="text-sm text-primary underline">Lupa password?</Link>
                <p class="text-[13px] leading-normal text-ink-3">Akun email dibuat oleh admin. Tidak ada pendaftaran mandiri.</p>
            </form>
        </div>
    </section>
</div>

<FlashMessage />
