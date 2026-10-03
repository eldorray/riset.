<?php

declare(strict_types=1);

/*
| Struktur awal tiap jenis tulisan (PRD F-05). Ini contoh struktur umum yang
| belum disetujui sebagai panduan kampus/jurnal mana pun: ganti di sini bila
| panduan resmi sudah ditetapkan. AI hanya mengisi subbab; urutan bab tetap.
*/

return [

    'structures' => [
        'skripsi' => [
            'numbering' => 'bab',
            'chapters' => [
                ['title' => 'Pendahuluan', 'sections' => ['Latar Belakang', 'Rumusan Masalah', 'Tujuan Penelitian', 'Manfaat Penelitian']],
                ['title' => 'Tinjauan Pustaka', 'sections' => ['Landasan Teori', 'Penelitian Terdahulu', 'Kerangka Pemikiran']],
                ['title' => 'Metode Penelitian', 'sections' => ['Jenis Penelitian', 'Data dan Sumber Data', 'Teknik Analisis Data']],
                ['title' => 'Hasil dan Pembahasan', 'sections' => ['Hasil Penelitian', 'Pembahasan']],
                ['title' => 'Penutup', 'sections' => ['Kesimpulan', 'Saran']],
            ],
        ],
        'tesis' => [
            'numbering' => 'bab',
            'chapters' => [
                ['title' => 'Pendahuluan', 'sections' => ['Latar Belakang', 'Rumusan Masalah', 'Tujuan Penelitian', 'Manfaat Penelitian', 'Kebaruan Penelitian']],
                ['title' => 'Kajian Pustaka', 'sections' => ['Landasan Teori', 'Penelitian Terdahulu', 'Kerangka Konseptual', 'Hipotesis']],
                ['title' => 'Metode Penelitian', 'sections' => ['Pendekatan dan Desain Penelitian', 'Populasi dan Sampel', 'Instrumen Penelitian', 'Teknik Analisis Data']],
                ['title' => 'Hasil dan Pembahasan', 'sections' => ['Hasil Penelitian', 'Pembahasan']],
                ['title' => 'Penutup', 'sections' => ['Simpulan', 'Implikasi', 'Saran']],
            ],
        ],
        'karya_ilmiah' => [
            'numbering' => 'angka',
            'chapters' => [
                ['title' => 'Pendahuluan', 'sections' => []],
                ['title' => 'Tinjauan Pustaka', 'sections' => []],
                ['title' => 'Metode', 'sections' => []],
                ['title' => 'Hasil dan Pembahasan', 'sections' => []],
                ['title' => 'Kesimpulan', 'sections' => []],
            ],
        ],
        'artikel' => [
            'numbering' => 'angka',
            'chapters' => [
                ['title' => 'Pendahuluan', 'sections' => []],
                ['title' => 'Metode', 'sections' => []],
                ['title' => 'Hasil dan Pembahasan', 'sections' => []],
                ['title' => 'Kesimpulan', 'sections' => []],
            ],
        ],
    ],

    /*
    | Bagian awal naskah lengkap per jenis tulisan (langkah 5 "Naskah lengkap").
    | Halaman judul & daftar isi diatur template Word; bagian di sini diisi pengguna/AI.
    */
    // Target minimal kata bawaan untuk "Buat naskah lengkap dengan AI" (dapat diubah pengguna).
    'target_words' => [
        'skripsi' => 12000,
        'tesis' => 20000,
        'karya_ilmiah' => 6000,
        'artikel' => 5000,
    ],

    'front_matter' => [
        'skripsi' => ['abstrak', 'kata_pengantar'],
        'tesis' => ['abstrak', 'abstract', 'kata_pengantar'],
        'karya_ilmiah' => ['abstrak'],
        'artikel' => ['abstrak', 'abstract'],
    ],

];
