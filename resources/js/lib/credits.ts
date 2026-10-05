// Perkiraan teks, bukan harga tetap. Token aktual dan reasoning dihitung oleh server.
export function estimateCredits(inputCharacters: number, outputWords: number, requests = 1) {
    const calls = Number.isFinite(requests) ? Math.max(0, Math.floor(requests)) : 0;
    const characters = Number.isFinite(inputCharacters) ? Math.max(0, inputCharacters) : 0;
    const words = Number.isFinite(outputWords) ? Math.max(0, outputWords) : 0;
    return {
        min: Math.max(1, Math.ceil(characters / 4 / 2000 + words * 1.5 / 250)) * calls,
        max: Math.max(1, Math.ceil(characters / 2 / 2000 + words * 2.5 / 250)) * calls,
    };
}

export function creditLabel({ min, max }: { min: number; max: number }) {
    return `≈ ${min === max ? min : `${min}–${max}`} kredit`;
}
