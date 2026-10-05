<script lang="ts">
    import AppLogo from '@/components/AppLogo.svelte';
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
        <Link href="/" aria-label="Riset, halaman awal" class="font-display text-[32px] leading-none font-medium tracking-tight"><AppLogo /></Link>
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
                <Link href="/" aria-label="Riset, halaman awal" class="font-display text-[28px] leading-none font-medium tracking-tight lg:hidden"><AppLogo /></Link>
                <h1 class="font-display text-[30px] sm:text-[40px] leading-tight font-medium">Masuk</h1>
                <p class="text-[15px] leading-relaxed text-ink-2">
                    {googleConfigured ? 'Gunakan akun Google, atau email dan password dari admin.' : 'Gunakan email dan password dari admin.'}
                </p>
            </div>

            {#if error}
                <div class="alert alert-danger" role="alert">
                    <Icon name="error" class="text-danger" />
                    <p>{error}</p>
                </div>
            {/if}

            {#if googleConfigured}
            <!-- Tautan biasa, bukan Inertia: Google butuh redirect halaman penuh. -->
            <a
                href={auth.google().url}
                class="btn btn-secondary h-13 text-[15px] {redirecting ? 'pointer-events-none opacity-60' : ''}"
                aria-disabled={redirecting}
                onclick={() => (redirecting = true)}
            >
                {#if redirecting}
                    <Icon name="spinner" /> Menghubungkan ke Google…
                {:else}
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M22.56 12.25c0-.73-.06-1.42-.19-2.09H12v3.96h5.92c-.26 1.28-1.03 2.37-2.18 3.1v2.58h3.52c2.06-1.9 3.3-4.7 3.3-8.03Z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.52-2.58c-.98.66-2.24 1.06-3.76 1.06-2.87 0-5.31-1.94-6.18-4.54H2.18v2.66A11 11 0 0 0 12 23Z" />
                        <path fill="#FBBC05" d="M5.82 14.28a6.6 6.6 0 0 1 0-4.56V7.06H2.18a11 11 0 0 0 0 9.88l3.64-2.66Z" />
                        <path fill="#EA4335" d="M12 5.18c1.62 0 3.07.56 4.21 1.66l3.16-3.16A10.53 10.53 0 0 0 12 1a11 11 0 0 0-9.82 6.06l3.64 2.66c.87-2.6 3.31-4.54 6.18-4.54Z" />
                    </svg>
                    {error ? 'Coba masuk lagi dengan Google' : 'Masuk dengan Google'}
                {/if}
            </a>

            <div class="flex items-center gap-3 text-xs text-ink-3" aria-hidden="true">
                <span class="h-px grow bg-line"></span>atau<span class="h-px grow bg-line"></span>
            </div>
            {/if}

            <form class="flex flex-col gap-4" onsubmit={submit} novalidate aria-label="Masuk dengan email">
                <div class="field">
                    <label for="email" class="label">Email</label>
                    <input id="email" type="email" autocomplete="username" class="input" bind:value={form.email} aria-invalid={form.errors.email ? 'true' : undefined} aria-describedby={form.errors.email ? 'email-error' : undefined} />
                    {#if form.errors.email}<span id="email-error" class="error">{form.errors.email}</span>{/if}
                </div>
                <div class="field">
                    <label for="password" class="label">Password</label>
                    <input id="password" type="password" autocomplete="current-password" class="input" bind:value={form.password} aria-invalid={form.errors.password ? 'true' : undefined} aria-describedby={form.errors.password ? 'password-error' : undefined} />
                    {#if form.errors.password}<span id="password-error" class="error">{form.errors.password}</span>{/if}
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
