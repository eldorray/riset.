<?php

declare(strict_types=1);

use App\Citation\Chicago;
use App\Citation\Harvard;
use App\Citation\Ieee;
use App\Citation\Markers;
use App\Citation\Mla9;
use App\Models\Reference;

function sampleArticle(array $overrides = []): Reference
{
    $reference = new Reference([
        'title' => 'Literasi digital dan kemandirian belajar',
        'source_url' => 'https://contoh.ac.id/artikel',
        'metadata' => [
            'type' => 'article',
            'authors' => ['Santoso, Budi Arief', 'Lestari, Dewi'],
            'year' => '2024',
            'publication' => 'Jurnal Contoh Pendidikan',
            'volume' => '12',
            'issue' => '3',
            'pages' => '45-60',
            'doi' => '10.0000/contoh.001',
            ...$overrides,
        ],
    ]);
    $reference->id = 1;

    return $reference;
}

it('memformat IEEE bernomor', function () {
    $ieee = new Ieee;

    expect($ieee->entryText(sampleArticle(), 3))->toBe(
        '[3] B. A. Santoso and D. Lestari, "Literasi digital dan kemandirian belajar," Jurnal Contoh Pendidikan, vol. 12, no. 3, pp. 45–60, 2024. doi: 10.0000/contoh.001.'
    )
        ->and($ieee->wrap([$ieee->inText(sampleArticle(), 1), $ieee->inText(sampleArticle(), 2)]))->toBe('[1], [2]')
        ->and($ieee->numbered())->toBeTrue();
});

it('memakai et al. di IEEE untuk 7 penulis atau lebih dan tautan bila tanpa DOI', function () {
    $authors = array_map(fn (int $i): string => "Penulis{$i}, Andi", range(1, 7));

    expect((new Ieee)->entryText(sampleArticle(['authors' => $authors, 'doi' => '']), 1))
        ->toStartWith('[1] A. Penulis1 et al., ')
        ->toEndWith('[Online]. Available: https://contoh.ac.id/artikel');
});

it('memformat Harvard', function () {
    $harvard = new Harvard;

    expect($harvard->entryText(sampleArticle()))->toBe(
        "Santoso, B.A. and Lestari, D. (2024) 'Literasi digital dan kemandirian belajar', Jurnal Contoh Pendidikan, 12(3), pp. 45–60. Available at: https://doi.org/10.0000/contoh.001."
    )->and($harvard->wrap([$harvard->inText(sampleArticle())]))->toBe('(Santoso and Lestari, 2024)');
});

it('memformat Chicago author-date', function () {
    $chicago = new Chicago;
    $three = sampleArticle(['authors' => ['Santoso, Budi', 'Lestari, Dewi', 'Putra, Eko']]);

    expect($chicago->entryText(sampleArticle()))->toBe(
        'Santoso, Budi Arief, and Dewi Lestari. 2024. "Literasi digital dan kemandirian belajar." Jurnal Contoh Pendidikan 12 (3): 45–60. https://doi.org/10.0000/contoh.001.'
    )
        ->and($chicago->inText(sampleArticle()))->toBe('Santoso and Lestari 2024')
        ->and($chicago->inText($three))->toBe('Santoso, Lestari, and Putra 2024');
});

it('memformat MLA 9', function () {
    $mla = new Mla9;
    $three = sampleArticle(['authors' => ['Santoso, Budi', 'Lestari, Dewi', 'Putra, Eko']]);

    expect($mla->entryText(sampleArticle()))->toBe(
        'Santoso, Budi Arief, and Dewi Lestari. "Literasi digital dan kemandirian belajar." Jurnal Contoh Pendidikan, vol. 12, no. 3, 2024, pp. 45–60. https://doi.org/10.0000/contoh.001.'
    )
        ->and($mla->entryText($three))->toStartWith('Santoso, Budi, et al. ')
        ->and($mla->wrap([$mla->inText(sampleArticle())]))->toBe('(Santoso and Lestari)');
});

it('memformat buku di setiap gaya tanpa field kosong', function () {
    $book = new Reference(['title' => 'Pengantar literasi informasi', 'source_url' => 'https://contoh.ac.id/buku', 'metadata' => [
        'type' => 'book', 'authors' => ['Putra, Eko'], 'year' => '2022', 'publisher' => 'Penerbit Contoh',
    ]]);

    expect((new Ieee)->entryText($book, 1))->toBe('[1] E. Putra, Pengantar literasi informasi. Penerbit Contoh, 2022. [Online]. Available: https://contoh.ac.id/buku')
        ->and((new Harvard)->entryText($book))->toBe('Putra, E. (2022) Pengantar literasi informasi. Penerbit Contoh.')
        ->and((new Chicago)->entryText($book))->toBe('Putra, Eko. 2022. Pengantar literasi informasi. Penerbit Contoh.')
        ->and((new Mla9)->entryText($book))->toBe('Putra, Eko. Pengantar literasi informasi. Penerbit Contoh, 2022.');
});

it('merender sitasi bernomor IEEE dengan nomor yang diberikan', function () {
    $refs = collect([1 => sampleArticle(), 2 => new Reference(['title' => 'Belum lengkap', 'source_url' => 'https://x.test', 'metadata' => []])]);

    expect(Markers::render('Teks [@1][@2] dan [@1].', $refs, new Ieee, [1 => 1]))
        ->toBe('Teks [1], [sitasi belum lengkap: Belum lengkap] dan [1].');
});
