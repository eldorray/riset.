<?php

declare(strict_types=1);

use App\Citation\Apa7;
use App\Citation\Markers;
use App\Models\Reference;

function reference(array $metadata, string $title = 'Literasi digital dan kemandirian belajar', int $id = 1): Reference
{
    $reference = new Reference(['title' => $title, 'source_url' => 'https://contoh.ac.id/artikel', 'metadata' => $metadata]);
    $reference->id = $id;

    return $reference;
}

$article = [
    'type' => 'article',
    'authors' => ['Santoso, Budi Arief', 'Lestari, Dewi'],
    'year' => '2024',
    'publication' => 'Jurnal Contoh Pendidikan',
    'volume' => '12',
    'issue' => '3',
    'pages' => '45-60',
    'doi' => 'https://doi.org/10.0000/contoh.001',
];

it('memformat artikel jurnal sesuai APA 7', function () use ($article) {
    $apa = new Apa7;
    $ref = reference($article);

    expect($apa->entryText($ref))->toBe(
        'Santoso, B. A., & Lestari, D. (2024). Literasi digital dan kemandirian belajar. Jurnal Contoh Pendidikan, 12(3), 45–60. https://doi.org/10.0000/contoh.001'
    )->and($apa->entry($ref))->toContain(['text' => 'Jurnal Contoh Pendidikan', 'italic' => true]);
});

it('memformat buku dan memakai tautan asal bila tidak ada DOI', function () {
    $ref = reference(['type' => 'book', 'authors' => ['Kementerian Pendidikan'], 'year' => '2022', 'publisher' => 'Penerbit Contoh'], 'Pengantar literasi informasi');

    expect((new Apa7)->entryText($ref))->toBe(
        'Kementerian Pendidikan. (2022). Pengantar literasi informasi. Penerbit Contoh. https://contoh.ac.id/artikel'
    );
});

it('menyusun sitasi dalam teks untuk 1, 2, dan 3+ penulis', function (array $authors, string $expected) {
    expect((new Apa7)->inText(reference(['authors' => $authors, 'year' => '2023'])))->toBe($expected);
})->with([
    [['Santoso, Budi'], 'Santoso, 2023'],
    [['Santoso, Budi', 'Lestari, Dewi'], 'Santoso & Lestari, 2023'],
    [['Santoso, Budi', 'Lestari, Dewi', 'Putra, Eko'], 'Santoso et al., 2023'],
]);

it('memakai elipsis untuk 21 penulis atau lebih', function () {
    $authors = array_map(fn (int $i): string => "Penulis{$i}, A", range(1, 22));
    $text = (new Apa7)->entryText(reference(['type' => 'web', 'authors' => $authors, 'year' => '2020']));

    expect($text)->toContain('Penulis19, A., . . . Penulis22, A.')->not->toContain('Penulis20');
});

it('menandai field wajib yang kosong tanpa mengarang nilainya', function () {
    $apa = new Apa7;

    expect($apa->missing(reference(['type' => 'article'])))->toBe(['authors' => 'Penulis', 'year' => 'Tahun', 'publication' => 'Nama jurnal'])
        ->and($apa->missing(reference(['type' => 'book', 'authors' => ['A, B'], 'year' => '2020'])))->toBe(['publisher' => 'Penerbit'])
        ->and($apa->isComplete(reference(['type' => 'web', 'authors' => ['A, B'], 'year' => 'n.d.'])))->toBeTrue();
});

it('merender penanda sitasi berdampingan, belum lengkap, dan tidak dikenal', function () use ($article) {
    $refs = collect([
        1 => reference($article, id: 1),
        2 => reference(['type' => 'article', 'authors' => ['Putra, Eko'], 'year' => '2021', 'publication' => 'Jurnal X'], id: 2),
        3 => reference(['type' => 'web'], 'Laporan survei', 3),
    ]);

    expect(Markers::render('Teks [@1][@2]. Lain [@3] dan [@9].', $refs, new Apa7))
        ->toBe('Teks (Santoso & Lestari, 2024; Putra, 2021). Lain ([sitasi belum lengkap: Laporan survei]) dan ([sitasi tidak dikenal]).');
});

it('membuang penanda yang tidak diizinkan', function () {
    expect(Markers::strip('A [@1] B [@7].', [1]))->toBe(['A [@1] B.', [7]]);
});
