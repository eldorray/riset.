// PWA: daftar service worker dan simpan event pemasangan agar tombol "Pasang aplikasi" bisa memicunya.
type InstallPrompt = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export const pwa = $state({ installable: false, ios: false });
let deferred: InstallPrompt | null = null;

export function setupPwa(): void {
    if (typeof window === 'undefined') return;
    const standalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true;
    // iPhone tidak punya prompt pemasangan; tampilkan petunjuk "Tambahkan ke Layar Utama".
    pwa.ios = !standalone && /iphone|ipod/i.test(navigator.userAgent);
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event as InstallPrompt;
        pwa.installable = true;
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        pwa.installable = false;
    });
    if (import.meta.env.PROD && 'serviceWorker' in navigator) {
        void navigator.serviceWorker.register('/sw.js');
    }
}

export async function installPwa(): Promise<void> {
    if (!deferred) return;
    await deferred.prompt();
    await deferred.userChoice;
    deferred = null;
    pwa.installable = false;
}
