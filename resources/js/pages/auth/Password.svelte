<script lang="ts">
    import { untrack } from 'svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FlashMessage from '@/components/FlashMessage.svelte';

    let { mode, hasPassword = false, token = '', email = '' }: { mode: 'change' | 'forgot' | 'reset'; hasPassword?: boolean; token?: string; email?: string } = $props();
    const title = $derived(mode === 'change' ? 'Ubah password' : mode === 'forgot' ? 'Lupa password' : 'Reset password');
    const form = useForm({ current_password: '', password: '', password_confirmation: '', email: '', token: '' });
    $effect(() => {
        const defaults = { email, token };
        untrack(() => { Object.assign(form, defaults); form.reset('current_password', 'password', 'password_confirmation'); });
    });

    // Error ditampilkan di bawah field-nya; error lain (mis. token) tetap di bawah form.
    const fields = ['email', 'current_password', 'password', 'password_confirmation'];
    const errors = $derived(form.errors as Record<string, string | undefined>);
    const invalid = (key: string) => (errors[key] ? { 'aria-invalid': 'true' as const, 'aria-describedby': `${key}-error` } : {});

    function submit(event: SubmitEvent) {
        event.preventDefault();
        const options = { onSuccess: () => form.reset('current_password', 'password', 'password_confirmation') };
        if (mode === 'change') form.put('/account/password', options);
        else form.post(mode === 'forgot' ? '/forgot-password' : '/reset-password', options);
    }
</script>

{#snippet error(key: string)}
    {#if errors[key]}<span id="{key}-error" class="error">{errors[key]}</span>{/if}
{/snippet}

<AppHead {title} />
<main class="flex min-h-dvh items-center justify-center bg-paper px-6 py-12 text-ink">
    <form class="card flex w-full max-w-md flex-col gap-5 p-7" onsubmit={submit}>
        <h1 class="font-display text-3xl font-medium">{title}</h1>
        {#if mode !== 'change'}
            <div class="field"><label for="email" class="label">Email</label><input id="email" type="email" autocomplete="email" class="input" bind:value={form.email} required {...invalid('email')} />{@render error('email')}</div>
        {:else if hasPassword}
            <div class="field"><label for="current_password" class="label">Password saat ini</label><input id="current_password" type="password" autocomplete="current-password" class="input" bind:value={form.current_password} required {...invalid('current_password')} />{@render error('current_password')}</div>
        {:else}
            <p class="text-sm text-ink-2">Anda masuk melalui Google. Buat password untuk masuk menggunakan email juga.</p>
        {/if}
        {#if mode !== 'forgot'}
            <div class="field"><label for="password" class="label">Password baru</label><input id="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" class="input" bind:value={form.password} required {...invalid('password')} />{@render error('password')}</div>
            <div class="field"><label for="password_confirmation" class="label">Konfirmasi password baru</label><input id="password_confirmation" type="password" autocomplete="new-password" class="input" bind:value={form.password_confirmation} required {...invalid('password_confirmation')} />{@render error('password_confirmation')}</div>
        {/if}
        {#each Object.entries(form.errors).filter(([key]) => !fields.includes(key)) as [key, message] (key)}<p class="error" role="alert">{message}</p>{/each}
        <button type="submit" class="btn btn-primary" disabled={form.processing}>{form.processing ? 'Memproses…' : mode === 'forgot' ? 'Kirim tautan reset' : 'Simpan password'}</button>
        <Link href={mode === 'change' ? '/projects' : '/masuk'} class="text-sm text-primary underline">{mode === 'change' ? 'Kembali ke proyek' : 'Kembali ke halaman masuk'}</Link>
    </form>
</main>
<FlashMessage />
