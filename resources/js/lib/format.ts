const rtf = new Intl.RelativeTimeFormat('id', { numeric: 'auto' });

/** "2 jam yang lalu", "kemarin", dst. dari tanggal ISO. */
export function relativeTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const steps: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of steps) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return 'baru saja';
}

/**
 * Pesan dari respons JSON galat server (mis. 502 layanan AI), dengan cadangan umum.
 * Koneksi putus dan respons non-JSON (mis. halaman 404 dari server lain di port
 * yang sama) disebut terang agar penyebabnya bisa dilacak.
 */
export function errorMessage(error: unknown, fallback: string): string {
    const { code, response } = (error ?? {}) as {
        code?: string;
        response?: { status?: number; data?: unknown };
    };

    if (code === 'ERR_NETWORK') {
        return 'Koneksi ke server terputus. Pastikan server berjalan, lalu coba lagi.';
    }

    if (response?.status === 429) {
        return 'Terlalu banyak permintaan AI. Tunggu sebentar lalu coba lagi.';
    }

    if (typeof response?.data === 'string') {
        try {
            const message = (JSON.parse(response.data) as { message?: unknown })
                .message;

            if (typeof message === 'string' && message) {
                return message;
            }
        } catch {
            // bukan JSON — pakai pesan cadangan
        }
    }

    return response?.status
        ? `${fallback} (HTTP ${response.status})`
        : fallback;
}
