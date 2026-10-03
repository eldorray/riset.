# Brainstorming judul proyek

Desain alur disetujui pengguna pada 2 Oktober 2026.

## Pengalaman pengguna

Tambahkan kartu “Bingung menentukan judul?” di bawah form “Proyek baru” pada halaman /projects. Tombol “Diskusi dengan AI” membuka diskusi di dalam kartu, tanpa pindah halaman. Tampilan memakai komponen dan warna aplikasi yang ada.

Pengguna memilih jenis tulisan di form proyek sebelum memulai. Diskusi terdiri dari tiga giliran jawaban: bidang/minat, masalah penelitian, lalu objek dan metode. Pertanyaan pertama mengajak pengguna menjelaskan bidang/minat. Sesudah tiap jawaban, AI memberi feedback singkat yang merujuk jawaban pengguna dan pertanyaan berikutnya yang sesuai konteks. Pengguna boleh menjawab belum tahu; AI membantu mempersempit pilihan tanpa mengarang fakta penelitian.

Sesudah jawaban ketiga, AI memberi feedback dan tepat tiga alternatif judul, masing-masing dengan alasan singkat. Tombol “Pakai judul” mengisi kolom judul di form proyek dan memindahkan fokus ke kolom itu. Pengguna tetap membuat proyek lewat tombol “Buat proyek”. Tombol “Mulai ulang” tersedia untuk mengulang diskusi. Jenis tulisan selama diskusi mengikuti pilihan ketika diskusi dimulai; jika pengguna mengubah jenis tulisan, diskusi direset agar rekomendasi tidak memakai konteks lama.

## Implementasi dan data

Gunakan AiClient yang sudah ada. Tambahkan endpoint POST /projects/brainstorm di dalam middleware auth dan auth.session, dengan throttle:ai. Endpoint menerima jenis tulisan serta riwayat pertanyaan/jawaban yang terbatas tiga giliran. Tidak memerlukan proyek yang sudah dibuat.

Server memvalidasi jenis tulisan, jumlah giliran, panjang teks, dan struktur input. Jumlah jawaban menentukan apakah respons berisi feedback dan pertanyaan berikutnya atau feedback dan tiga judul. Instruksi sistem memperlakukan isi diskusi sebagai data dan meminta JSON berbahasa Indonesia. Validasi respons AI sebelum dikirim ke browser, termasuk tepat tiga judul berbeda yang tidak kosong dan sesuai batas panjang judul proyek.

Gunakan useHttp dan errorMessage seperti halaman Kerangka. Riwayat hanya berada di state halaman; berpindah halaman atau memuat ulang menghapus diskusi. Tidak menambah tabel, dependensi, atau menyimpan percakapan.

## Error dan aksesibilitas

Tampilkan status menunggu AI, cegah pengiriman ganda, dan pertahankan jawaban serta riwayat ketika permintaan gagal agar bisa dicoba ulang. Tampilkan pesan kesalahan layanan AI atau validasi yang mudah dipahami. Sediakan label input, status aria-live, dan fokus yang mengikuti pertanyaan berikutnya. Tombol brainstorming tidak men-submit form proyek.

## Verifikasi

Tes fitur dengan respons HTTP AI palsu: feedback dan pertanyaan sesuai giliran, tepat tiga alternatif pada giliran akhir, input tidak valid ditolak sebelum menghubungi AI, keluaran AI rusak ditolak, kegagalan layanan memberi pesan, dan pengguna tamu tidak bisa mengakses endpoint. Jalankan tes relevan, pemeriksaan tipe Svelte, dan build frontend. Periksa UI kartu, urutan diskusi, retry, mulai ulang, dan pengisian judul.

## Batasan

Tidak menambah chat bebas atau pencarian referensi. Kualitas rekomendasi bergantung pada layanan AI yang dikonfigurasi dan jawaban pengguna. Riwayat tidak disimpan lintas kunjungan.
