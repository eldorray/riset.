import { createInertiaApp } from '@inertiajs/svelte';
import { setupPwa } from '@/lib/pwa.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    defaults: {
        // Transisi halaman lembut hanya saat pindah halaman; form, reload sebagian, dan filter tetap instan.
        visitOptions: (_href, options) => ({
            ...options,
            // Opsi di sini belum digabung default Inertia: method kosong berarti GET.
            viewTransition:
                (options.method ?? 'get') === 'get' &&
                !options.only?.length &&
                !options.preserveState,
        }),
    },
});

setupPwa();
