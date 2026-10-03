# Subscription dan kredit Riset

## Ruang lingkup

Sistem paket bulanan untuk semua fitur AI, halaman subscription pengguna, dan pengelolaan subscription per pengguna oleh admin. Pembayaran menggunakan aktivasi manual oleh admin, sesuai pilihan pengguna. Tidak mengintegrasikan payment gateway pada tahap ini.

Paket awal: Hemat Rp19.000/600 kredit; Mahasiswa Rp39.000/1.500 kredit; Riset Rp79.000/3.500 kredit. Harga dan kuota paket dapat disunting admin. Perubahan katalog tidak mengubah saldo atau masa aktif yang sudah diberikan. Paket yang dinonaktifkan tidak bisa dibeli baru, tetapi subscription yang sudah diberikan tetap berlaku hingga berakhir.

## Pengguna

Halaman Paket & Kredit menampilkan paket aktif, tanggal akhir akses dalam Asia/Jakarta, saldo kredit, status unlimited, pilihan paket beserta harga dan kuota, serta riwayat kredit. Pengguna dapat mengajukan pembelian atau perpanjangan pada alur manual, tanpa mengaktifkan paket sendiri. Permintaan mencatat harga dan kuota saat diajukan. Admin memeriksa pembayaran sebelum menyetujuinya. Persetujuan ulang tidak menambah kredit dua kali.

Semua pengguna tetap bisa membuka, mengedit manual, dan mengekspor dokumen ketika subscription habis. Hanya operasi AI yang memerlukan akses aktif dan saldo. Tidak memberikan akses berbayar otomatis kepada akun lama. Admin dapat mengaktifkan paket atau unlimited untuk akun uji yang diinginkan.

## Admin

Panel paket mengatur nama, harga, kuota, dan status tersedia. Panel pengguna menampilkan akses subscription dan menyediakan tindakan terpisah: aktivasi/perpanjangan paket, ubah tanggal berakhir, penyesuaian kredit dengan alasan, aktifkan/nonaktifkan unlimited.

Perpanjangan paket menambah 1 bulan kalender dari tanggal akhir yang masih aktif, atau dari sekarang jika sudah kedaluwarsa, dengan penanganan akhir bulan tanpa overflow. Kredit paket ditambahkan saat perpanjangan disetujui. Mengubah tanggal akhir saja tidak memberi kredit tambahan dan tidak mengubah paket. UI menjelaskan perbedaan tindakan ini.

Unlimited dapat diberikan hingga tanggal tertentu atau tanpa batas waktu. Unlimited tidak memotong saldo kredit dan tidak memberikan peran admin. Menonaktifkan unlimited mengembalikan pemeriksaan subscription normal menggunakan tanggal akhir dan saldo yang sudah ada. Admin menjalankan aturan akses yang sama untuk proyek pribadinya; role admin tidak otomatis bypass kredit.

Tindakan admin mencatat admin pelaksana, pengguna sasaran, waktu, alasan, perubahan masa aktif dan perubahan saldo. Pengguna hanya melihat riwayat miliknya; admin tidak memperoleh akses ke isi proyek.

## Akuntansi kredit

Perhitungan yang disepakati: ceil(input_token / 2000 + output_token / 250) per panggilan AI yang berhasil, termasuk reasoning output yang ditagihkan. Pecahan dibulatkan satu kali setelah dijumlahkan. Tarif model tidak dipakai sebagai saldo uang pengguna.

Akuntansi ditempatkan pada AiClient bersama agar kerangka, draf, naskah, brainstorming dan pembacaan artikel melewati pemeriksaan yang sama. Pengambilan sumber tanpa AI, catatan tersimpan, edit manual dan ekspor tidak memotong kredit.

Sebelum memanggil provider, lakukan reservasi kredit dalam transaksi menggunakan batas output yang juga dikirim ke API dan batas input konservatif. Setelah jawaban JSON valid, gunakan angka usage dari provider untuk menghitung pemakaian aktual, lalu kembalikan selisih reservasi. Kegagalan koneksi, provider atau JSON membatalkan reservasi. Validator fitur yang menolak hasil juga harus mengembalikan biaya panggilan yang ditolak. Riwayat membedakan reservasi, biaya final, dan pengembalian.

Operasi majemuk menampilkan bahwa membaca artikel dan menulis draf adalah pekerjaan berbeda. Catatan artikel yang berhasil disimpan tetap dihitung meskipun penulisan draf berikutnya gagal; draf gagal tidak dihitung. Percobaan ulang internal yang gagal tidak ditagihkan kepada pengguna. Request paralel tidak boleh membuat saldo negatif. Catat identifier operasi dan idempotensi persetujuan admin. Provider yang tidak menyertakan usage harus memberi error yang jelas dan mengembalikan reservasi, bukan diam-diam menagih perkiraan.

## Siklus subscription

Kredit bulanan merupakan kuota untuk periode akses yang dibeli. Sisa kredit subscription habis pada akhir periode; tidak ada pengisian ulang otomatis tanpa pembayaran atau aktivasi admin. Perpanjangan lebih awal dijadwalkan sebagai periode berikutnya agar kuota baru tidak diberikan sebelum waktunya. Saldo periode aktif dan kuota periode berikutnya ditampilkan terpisah.

Top-up opsional yang sudah dibahas: Rp15.000/300 kredit, berlaku 90 hari, dibukukan terpisah dari kuota subscription. Kredit periode dengan tanggal kedaluwarsa terdekat dipakai lebih dahulu. Kredit top-up tetap memerlukan subscription aktif atau unlimited untuk menggunakan AI. Masa berlaku dan syarat ditampilkan sebelum permintaan pembelian.

## Verifikasi

Tes akses kedaluwarsa, kredit kosong, saldo cukup, unlimited tanpa akhir/dengan akhir, pencabutan unlimited, perpanjangan akhir bulan, edit tanggal tanpa kredit baru, perubahan paket yang tidak retroaktif, persetujuan ganda, grant top-up kedaluwarsa, penggunaan token reasoning, kegagalan provider/usage/validasi hasil, reservasi dan refund, serta isolasi pengguna/admin. Jalankan tes AI yang sudah ada dengan entitlement uji eksplisit. Periksa tipe PHP dan Svelte serta build. Verifikasi tampilan pengguna dan admin.
