# Penulisan AI di latar belakang

## Tujuan

Pengguna bisa berpindah menu selama AI menulis Draf atau Naskah lengkap. Pekerjaan dan hasil tersimpan di server sehingga kembali ke halaman, memuat ulang, atau menutup tab tidak membatalkan pekerjaan.

## Pilihan pendekatan

1. **Antrean database Laravel (dipilih).** Sudah tersedia dalam aplikasi. Menyimpan pekerjaan, progres, dan hasil; membutuhkan queue worker aktif.
2. State global browser. Lebih kecil, tetapi pekerjaan bergantung pada tab dan hilang saat reload/ditutup.
3. Proses setelah respons HTTP. Tidak membutuhkan worker terpisah, tetapi kurang cocok untuk naskah banyak bagian, pemulihan kegagalan, dan batas waktu hosting.

## Perilaku pengguna

- Tombol penulisan mengirim pekerjaan dan segera menampilkan status menunggu/berjalan, bagian aktif, jumlah selesai, serta kegagalan/peringatan.
- Berlaku untuk satu bagian draf, semua bagian kosong, naskah lengkap, dan usulan bagian awal naskah.
- Indikator penulisan pada navigasi proyek menyediakan tautan kembali ke hasil. Halaman memeriksa progres secara berkala; tidak memerlukan WebSocket.
- Usulan draf dan usulan bagian awal disimpan terpisah di database sampai pengguna memilih pakai/buang. Navigasi tidak menghapus usulan. Tulisan manual yang belum tersimpan tetap mendapat perlindungan keluar halaman.
- Naskah lengkap tetap menyimpan hasil per bagian dan menandainya sebagai hasil AI belum diperiksa.
- Hanya satu pekerjaan penulisan aktif per proyek, termasuk lintas tab. Klik berulang tidak membuat pekerjaan atau biaya ganda.
- Tombol hentikan mencegah bagian berikutnya dimulai. Panggilan AI yang sedang berlangsung diselesaikan dan hasilnya tetap ditangani.

## Eksekusi dan penyimpanan

- Simpan pekerjaan dengan pemilik, proyek, jenis penulisan, pilihan referensi, mode, target kata, status, progres, peringatan, dan hasil usulan.
- Antrean menjalankan satu langkah/bagian setiap job, kemudian menjadwalkan langkah berikutnya. Susunan pekerjaan dan status persisten menggantikan loop browser.
- Gunakan action generasi yang ada untuk pembacaan artikel, sitasi, parafrase, dan bagian awal. Jangan mengubah aturan sumber.
- Pilihan referensi disalin saat mulai; sebelum tiap langkah periksa kembali keberadaan proyek, referensi dan bagian. Perubahan kerangka yang mengubah target menghentikan langkah dengan pesan jelas.
- Naskah lengkap hanya mengganti isi bila sama dengan isi dasar saat pekerjaan dimulai. Edit pengguna yang lebih baru tidak ditimpa. Perubahan pada bagian lain tetap boleh dilakukan.
- Penerimaan usulan disertai pemeriksaan isi dasar dan validitas sitasi; hasil konflik tetap tersedia untuk ditinjau/disalin.
- Pertahankan batas dua kegagalan berurutan. Hasil yang sudah selesai tetap tersedia. Job gagal tidak otomatis mengulang panggilan berbayar.

## Akses, kredit, dan kegagalan

- Semua endpoint pekerjaan memeriksa kepemilikan proyek; pekerja server memeriksa akses pemilik sebelum panggilan AI.
- Identitas penagihan harus diteruskan secara eksplisit dalam konteks job, karena job tidak mempunyai pengguna HTTP yang login. Jangan membiarkan panggilan worker berjalan tanpa meter kredit.
- Gunakan reservasi, pelunasan penggunaan aktual, pengembalian kegagalan, dan checkpoint catatan artikel yang ada. Usulan yang berhasil disimpan dihitung sebagai operasi berhasil.
- Atur timeout worker dan retry_after agar job belum selesai tidak dijalankan paralel. Reservasi dan status job yang terputus harus bisa dipulihkan tanpa menagih atau menyimpan dua kali.
- Queue worker menjadi bagian setup lokal dan deployment. Jika worker tidak tersedia, status tetap menunggu; tampilkan petunjuk keterlambatan alih-alih mengaku sedang menulis.

## Verifikasi penerimaan

- Mulai pekerjaan, pindah menu, kembali: progres/hasil tetap tersedia; ulangi dengan reload/tab ditutup.
- Periksa usulan satu bagian, beberapa bagian, naskah lengkap dan bagian awal.
- Periksa persetujuan/penolakan usulan, penyimpanan naskah, konflik edit, penghentian dan kegagalan provider.
- Pastikan akses akun lain ditolak, klik ganda tidak menggandakan pekerjaan, dan penggunaan/refund kredit worker benar.
- Jalankan tes backend, pemeriksaan tipe frontend/PHP, build, serta uji browser dengan provider uji agar tidak memakai kredit pengguna.

## Status

Rancangan disetujui dan diimplementasikan. Repository ini tidak mempunyai Git sehingga dokumen disimpan tanpa commit.
