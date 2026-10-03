# Research Gap Implementation Plan

**Goal:** Membandingkan referensi proyek dan menyimpan kandidat gap pilihan pengguna sebagai arah penulisan.

**Architecture:** Gunakan WritingRun dan WriteProjectStep yang sudah tersedia. Tambahkan JSON gap_analysis/research_gap pada proyek, langkah pembacaan per referensi, dan satu langkah analisis. Jangan menimpa pilihan pengguna ketika analisis ulang selesai.

**Tech Stack:** Laravel, Svelte, Inertia, antrean database writing, AiClient/Billing/ArticleReader yang sudah ada.

- [x] Tambah migrasi projects dan cast model untuk hasil analisis serta gap pilihan.
- [x] Implementasikan GenerateResearchGap: matriks setiap sumber yang dapat digunakan, maksimal tiga kandidat, ID sumber valid, cuplikan bukti harus ada dalam catatan; sumber gagal tidak dipakai, minimal dua sumber diperlukan.
- [x] Integrasikan langkah gap_source/gap pada Writing tanpa mengubah perilaku draf/naskah; snapshot sumber dan periksa ulang sebelum menyimpan hasil; gunakan meter kredit existing dan simpan catatan yang berhasil dibaca.
- [x] Tambah ResearchGapController: policy pemilik, hasil/progres, pemilihan kandidat yang dapat disunting, cegah penerimaan hasil dari analisis lama atau sumber yang sudah berubah; hapus pilihan tanpa menghapus analisis.
- [x] Tambah halaman setelah Referensi, shortcut dari Referensi, pilihan sumber/kategori, estimasi kredit, matriks, kandidat/bukti/tautan verifikasi, editor pilihan. Polling hanya memuat ulang hasil saat analisis selesai; proses tetap berjalan saat pindah halaman.
- [x] Masukkan gap terpilih sebagai konteks kerangka/draf, bukan sumber fakta atau bukti kebaruan global. Abaikan konteks bila sumbernya berubah/dihapus.
- [x] Uji otorisasi, minimal sumber, invalid ID/cuplikannya, kegagalan pembacaan parsial, billing, background, pilihan lama, perubahan sumber, serta integrasi konteks. Jalankan suite PHP, PHPStan, Svelte check, build, dan pemeriksaan browser.
- [x] Dokumentasikan batas analisis dan worker, commit/push ke main termasuk build.

Validasi: 220 pengujian / 1.146 assertions lolos; PHPStan tanpa error; Svelte tanpa error/warning; build production selesai. Browser: pilih/batal kategori, tinjau dan sunting kandidat, simpan lalu reload, shortcut Referensi, viewport mobile 390 px tanpa overflow. UI memakai data uji dan respons AI tiruan; tidak menilai kebaruan ilmiah nyata.
