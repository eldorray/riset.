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
