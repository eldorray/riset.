import type { ProjectSummary, Unit } from '@/types';

const ROMAN: [string, number][] = [
    ['M', 1000],
    ['CM', 900],
    ['D', 500],
    ['CD', 400],
    ['C', 100],
    ['XC', 90],
    ['L', 50],
    ['XL', 40],
    ['X', 10],
    ['IX', 9],
    ['V', 5],
    ['IV', 4],
    ['I', 1],
];

/** Sama dengan Project::chapterLabel() di server. */
export function chapterLabel(
    index: number,
    numbering: 'bab' | 'angka',
): string {
    if (numbering === 'angka') {
        return `${index + 1}.`;
    }

    let n = index + 1;
    let roman = '';

    for (const [symbol, value] of ROMAN) {
        while (n >= value) {
            roman += symbol;
            n -= value;
        }
    }

    return `BAB ${roman}`;
}

export function newId(): string {
    return Math.random().toString(36).slice(2, 12);
}

/** Sama dengan Project::blockedReason() di server: bab metode butuh rancangan, bab hasil butuh data. */
export function unitBlocked(
    unit: Unit,
    project: Pick<
        ProjectSummary,
        'design_ready' | 'has_data' | 'literature_study'
    >,
): string | null {
    if (unit.kind === 'metode' && !project.design_ready) {
        return 'Bagian metode ditulis dari rancangan penelitian Anda. Isi rumusan masalah, pendekatan, dan teknik analisis (PTK: juga indikator keberhasilan) di Rancangan penelitian.';
    }
    if (
        unit.kind === 'empiris' &&
        !project.literature_study &&
        !project.has_data
    ) {
        return 'Bagian hasil, pembahasan, dan kesimpulan memerlukan data atau temuan penelitian Anda. AI tidak menulis hasil tanpa data.';
    }

    return null;
}
