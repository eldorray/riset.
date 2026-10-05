# Riset (nama sementara)

Aplikasi penyusunan skripsi, tesis, karya ilmiah, dan artikel berbasis AI — MVP dari `prd riset app.md`.
Laravel 13 + Inertia 3 + Svelte 5 + Tailwind 4.

## Research Gap

Menu proyek setelah Referensi membandingkan 2–10 sumber: matriks penelitian, maksimal tiga kandidat gap, bukti dari catatan sumber, dan tautan pencarian verifikasi. Sumber tanpa catatan dicoba dibaca; sumber gagal ditampilkan dan dikeluarkan. Minimal dua sumber yang terbaca diperlukan. Analisis memakai antrean `writing` dan meter kredit yang sama, sehingga tetap berjalan ketika pengguna pindah menu.

Pengguna meninjau dan menyunting kandidat sebelum menyimpannya sebagai arah kerangka/draf. Pilihan tidak menjadi bukti fakta atau jaminan kebaruan. Analisis ulang tidak menimpa pilihan; perubahan/penghapusan sumber membuat konteks lama diabaikan sampai ditinjau ulang. Deployment fitur ini memerlukan `php artisan migrate --force` dan restart worker.

## Alur

1. Masuk dengan Google → buat proyek (judul + jenis tulisan).
2. **Referensi** — cari di Crossref, OpenAlex, Semantic Scholar, DOAJ (atau semua sekaligus) dengan filter tahun, jenis, akses terbuka, serta indeks Scopus/DOAJ; klik "Cari" lagi untuk judul lain. Google Scholar/ResearchGate tersedia sebagai tautan pencarian manual (tidak punya API publik). Atau input manual: judul + tautan asal wajib, metadata hanya yang diketahui, dan catatan isi (satu-satunya isi sumber yang boleh dipakai AI).
3. **Kerangka** — AI mengisi subbab mengikuti struktur jenis tulisan (`config/riset.php`); pengguna memeriksa lalu menyimpan.
4. **Draf** — AI menulis per bagian (atau "Buat semua bagian kosong") dari referensi terpilih. Hasil AI tidak tersimpan sebelum diterima pengguna.
5. **Sitasi** — penanda `[@id]` di draf dirender sesuai gaya proyek: APA 7, IEEE (bernomor), Harvard, Chicago author-date, MLA 9. Daftar pustaka hanya dari referensi yang dirujuk dan lengkap.
6. **Naskah lengkap** — "Buat naskah lengkap dengan AI" dengan target minimal kata: kerangka (bila belum ada), semua bagian, lalu bagian awal sesuai jenis (skripsi: abstrak + kata pengantar; tesis: + abstract; artikel: abstrak + abstract; karya ilmiah: abstrak). Ditulis dengan parafrase + sitasi; bagian berisi dibiarkan atau ditulis ulang. Hasil AI ditandai "belum ditinjau" sampai disunting/ditandai diperiksa.
7. **Ekspor .docx** — naskah lengkap memakai template Word institusi pilihan proyek (atau format bawaan), dengan peringatan bagian kosong dan metadata yang belum lengkap.

Ringkasan menampilkan tombol **Lanjutkan** sesuai progres. Kesiapan referensi dibedakan antara metadata untuk sitasi dan catatan isi untuk AI. Draf otomatis disimpan setelah berhenti mengetik sekitar 1 detik; jika gagal atau konflik, teks tetap di editor dan tersedia tombol salin serta coba lagi/muat versi server. Usulan AI hanya masuk autosave setelah diterima. Kedua tombol ekspor membuka pemeriksaan yang sama dan tautan langsung ke bagian bermasalah.

Referensi duplikat dalam proyek ditolak berdasarkan judul, tautan, atau DOI. Semua referensi tersimpan muncul sebagai sitasi tersedia; daftar pustaka ekspor tetap hanya memuat sumber yang dirujuk. Kata kunci pencarian menjadi kategori awal dan bisa diedit (dipisahkan koma). Panel Draf mengelompokkan referensi menurut kata kunci dan menghitung kemunculan sitasi dalam teks editor. “Buat semua bagian kosong” memakai pilihan sumber yang sama untuk seluruh proses. AI diminta menempatkan sitasi dekat klaim yang didukung catatan; jawaban tanpa sitasi maupun penjelasan keterbatasan diminta diperbaiki satu kali, kemudian ditolak jika tetap tidak memenuhi aturan.

Filter indeks membatasi penyedia ke Scopus atau DOAJ; kegagalan tidak diganti dengan hasil sumber umum. “Semua sumber” memakai empat penyedia umum dan tidak memanggil Scopus. Scopus menggunakan API resmi Elsevier (STANDARD) dengan filter tahun, jenis sumber, dan akses terbuka; metadata penulis pertama saja tidak dianggap sebagai daftar penulis lengkap. Negara afiliasi penulis tidak dipakai sebagai negara penerbit jurnal, sehingga cakupan nasional/internasional tidak tersedia untuk Scopus. Hasil Scopus menunjukkan dokumen ditemukan dalam indeks, bukan status jurnal aktif atau kuartil. SINTA S1–S6 belum menjadi filter otomatis karena belum ada dataset/API resmi terverifikasi yang terhubung; tersedia tautan ke direktori resmi.

Proyek dapat diarsipkan dan dipulihkan dari daftar proyek; isi tetap tersimpan. Pengguna dapat mengubah password melalui tautan **Ubah password**, atau meminta tautan **Lupa password?** dari halaman masuk. Reset memakai token sekali pakai yang berlaku 60 menit.

Generasi otomatis menolak penyimpanan jika bagian berubah selama AI berjalan. Simpan perubahan bagian awal sebelum memulai generasi. Keterbatasan AI dan perbandingan jumlah kata aktual dengan target ditampilkan setelah proses; target kata bukan jaminan hasil AI.

## Panel admin (`/admin`)

Logo aplikasi dapat diunggah melalui **Konfigurasi → Logo aplikasi** (`/admin/branding`): PNG/JPG/WebP maksimal 2 MB dan 4096 × 4096 piksel. Pratinjau tersedia sebelum menyimpan, dan logo bawaan dapat dipulihkan. Berkas disimpan di storage privat lalu disajikan melalui endpoint gambar, tanpa membutuhkan `storage:link`.

Menu Pengguna menyediakan hapus akun dengan konfirmasi. Akun beserta proyek, referensi, draf, subscription, kredit, sesi, dan token reset dihapus permanen. Admin tidak dapat menghapus akunnya sendiri; penghapusan ditunda selama penulisan AI masih menunggu/berjalan atau kredit AI masih direservasi.

Ringkasan & status layanan · Pengguna (buat akun login email, ubah peran/password) · Gaya sitasi aktif · Struktur jenis tulisan · Template Word (font, spasi, margin, halaman judul, daftar isi, nomor halaman).
Admin tidak dapat membuka isi proyek pengguna lain.

## Subscription dan kredit

Pengguna membuka **Paket & Kredit** (`/account/subscription`) untuk melihat saldo, masa aktif, riwayat dan mengajukan paket/top-up. Paket bulanan awal: Hemat Rp19.000/600 kredit, Mahasiswa Rp39.000/1.500 kredit, Riset Rp79.000/3.500 kredit. Top-up Rp15.000/300 kredit berlaku 90 hari dan memerlukan subscription aktif untuk dipakai.

Admin membuka **Subscription & Kredit** (`/admin/billing`) untuk memverifikasi pembayaran manual, menyetujui/menolak permintaan, mengubah paket, menyesuaikan saldo/masa aktif, dan memberi unlimited permanen atau hingga tanggal tertentu. Perubahan membutuhkan alasan dan dicatat. Unlimited tidak memberi hak admin. Akun lama maupun baru tidak otomatis memperoleh akses AI; admin harus mengaktifkan paket atau unlimited. Edit manual dan ekspor tetap tersedia.

Biaya setiap panggilan AI adalah pembulatan ke atas dari `input_tokens / 2000 + output_tokens / 250`; reasoning sudah termasuk output. Kredit maksimum direservasi sebelum panggilan, kemudian selisih dikembalikan berdasarkan `usage.prompt_tokens` dan `usage.completion_tokens` provider. Provider harus melaporkan keduanya sebagai bilangan bulat. Panggilan atau hasil tidak valid dikembalikan; catatan artikel yang berhasil disimpan tetap dihitung walaupun generasi draf berikutnya gagal. Reservasi tertinggal lebih dari satu jam dipulihkan saat panggilan berikutnya.

Perpanjangan lebih awal menjadwalkan periode dan kredit berikutnya setelah masa aktif sekarang. Kredit paket berakhir bersama periodenya. Mengedit tanggal tidak mengisi ulang kredit. Seluruh tanggal di antarmuka ditampilkan dalam WIB. Belum ada payment gateway atau penagihan otomatis.

## Setup lokal

```bash
composer install
npm install
cp .env.example .env   # lalu isi bagian di bawah
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Lokal memakai SQLite (`DB_CONNECTION=sqlite`); produksi di shared hosting pakai MySQL (`DB_*`).

### Konfigurasi `.env`

| Kunci                                      | Keterangan                                                                                                   |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------ |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | OAuth client dari Google Cloud Console. Redirect URI: `{APP_URL}/auth/google/callback`.                      |
| `AI_BASE_URL`                              | API OpenAI-compatible, mis. `https://api.penyedia.com/v1` (dipanggil `POST {AI_BASE_URL}/chat/completions`). |
| `AI_API_KEY`, `AI_MODEL`                   | Kunci dan nama model. Kunci hanya dipakai di server.                                                         |
| `AI_TIMEOUT`                               | Detik per permintaan (default 120). Sesuaikan dengan batas waktu proses hosting.                             |
| `AI_JSON_MODE`                             | `true` mengirim `response_format: json_object`; set `false` bila API menolaknya.                             |
| `CROSSREF_MAILTO`, `OPENALEX_MAILTO`       | Email kontak untuk "polite pool" (opsional, disarankan).                                                     |
| `SCOPUS_API_KEY`                           | Kunci Elsevier untuk sumber/filter Scopus; hanya dikirim melalui header dari server.                         |
| `SCOPUS_INSTTOKEN`                         | Opsional sesuai hak akses institusi Elsevier.                                                                |
| `SEMANTIC_SCHOLAR_API_KEY`                 | Opsional; tanpa kunci Semantic Scholar sering menolak (batas bersama).                                       |

### Email reset password

Konfigurasikan `MAIL_MAILER`, kredensial SMTP, dan `MAIL_FROM_ADDRESS` agar tautan reset terkirim. Dengan mailer `log`, email hanya dicatat di log dan tidak masuk ke kotak masuk. Pastikan `APP_URL` memakai alamat aplikasi yang dapat dibuka penerima.

### Akun admin

```bash
php artisan riset:admin email@kampus.ac.id --name="Nama Admin"   # password diminta tersembunyi
```

Membuat atau memperbarui akun admin dengan login email + password. Akun pengguna lain dibuat dari panel admin.

### Masuk tanpa Google (uji lokal)

```bash
php artisan riset:login-link email@contoh.ac.id --create --name="Nama Uji"
```

Mencetak tautan masuk bertanda tangan yang berlaku 15 menit. Host tautan mengikuti `APP_URL`.

## Penulisan AI di latar belakang

Draf satu bagian, semua bagian kosong, bagian awal, dan naskah lengkap dijalankan melalui antrean server. Pengguna dapat berpindah menu, memuat ulang, atau menutup tab. Progres dan usulan tersimpan di database. Usulan Draf/bagian awal menunggu **Pakai/Buang**; naskah lengkap langsung menyimpan tiap bagian sebagai hasil AI belum ditinjau. Hanya satu penulisan aktif per proyek. **Hentikan** menyelesaikan panggilan yang sedang berjalan lalu membatalkan bagian berikutnya.

`composer dev`/`php artisan dev` kini menyertakan pekerja `writing`. Jika hanya menjalankan `php artisan serve`, jalankan juga:

```bash
php artisan queue:listen writing --queue=writing --tries=1 --timeout=1800
```

Pada produksi, jalankan pekerja dengan Supervisor atau process manager hosting:

```bash
php artisan queue:work writing --queue=writing --tries=1 --timeout=1800 --sleep=3
```

Koneksi `writing` menggunakan tabel `jobs` pada database aplikasi, dengan `retry_after=1860` agar bagian panjang tidak dijalankan ganda. Pekerja perlu hidup agar status menunggu diproses. Job gagal tidak otomatis mengulang panggilan berbayar; hasil sebelumnya tetap tersedia. Progres yang terputus lebih dari 35 menit ditandai gagal dan reservasi kredit dikembalikan saat status dibuka. Setelah deployment, restart pekerja agar memuat kode baru. Proses background tetap memakai validasi referensi, aturan sitasi dan meter kredit pemilik proyek.

Edit terbaru tidak ditimpa. Jika teks berubah setelah AI mulai, usulan tetap tersedia untuk disalin/discard; bagian naskah yang konflik tidak diganti. Bagian awal juga memakai penyimpanan hanya bagian yang berubah dan pemeriksaan teks dasar untuk mencegah edit usang menimpa hasil background.

## Kualitas

```bash
./vendor/bin/pint        # format PHP
./vendor/bin/phpstan     # analisis statis (level 7)
php artisan test         # Pest
npm run check            # lint + format frontend
npm run types:check      # svelte-check
```

## Batasan yang disengaja

- Scopus/SINTA/Google Scholar/ResearchGate tidak punya API publik bebas: tidak di-scrape, hanya tautan pencarian manual.
- Parafrase dengan sitasi bukan jaminan lolos pemeriksaan plagiarisme/AI; kebijakan kampus tentang AI tetap berlaku.
- Tidak ada pendaftaran mandiri; akun email dibuat admin.
- Judul referensi dipakai apa adanya (tidak diubah ke sentence/title case); belum ada pembeda 2024a/2024b.
- Daftar isi .docx berupa field Word: klik kanan → _Update Field_ setelah membuka file.

### Pembacaan artikel dengan AI

Tombol **Baca artikel dengan AI** pada Referensi mengambil isi HTML/PDF yang tersedia secara publik dan menyimpan ringkasan ke Catatan, beserta tautan, tanggal, dan dasar pembacaan. Draf dan naskah lengkap otomatis membaca referensi terpilih yang belum memiliki catatan. Sumber yang gagal dibaca dikecualikan dari bahan AI dan dilaporkan dalam keterbatasan; penulisan berlanjut memakai sumber yang berhasil dibaca. Jika semua sumber terpilih gagal dibaca, penulisan ditolak. Artikel dengan DOI dicari juga melalui OpenAlex untuk menemukan salinan open access. Jika hanya abstrak yang tersedia, catatan ditandai sebagai abstrak saja.

Pada **Tambah referensi manual**, unggah PDF atau Word `.docx` lalu klik **Baca dan isi dengan AI**. AI mengisi metadata karya utama dan merangkum seluruh teks yang dapat diekstrak. Periksa hasilnya sebelum **Simpan referensi**; unggahan belum membuat entri referensi. Field yang tidak ditemukan dibiarkan kosong. Jika DOI/URL karya utama tidak tercantum, tautan asal perlu dilengkapi sebelum menyimpan. Pembacaan memakai akses AI dan kredit paket yang aktif. Word `.doc` lama perlu disimpan ulang sebagai `.docx`. Metadata dan ringkasan dihasilkan dalam satu permintaan AI. Catatan maksimal 5.000 karakter termasuk identitas sumber; hasil yang melebihi batas dipotong dengan pemberitahuan agar form tetap dapat digunakan.

Ekstraksi PDF memerlukan `pdftotext` (Poppler) pada PATH server. Batas unduhan 15 MB dan teks 200.000 karakter; artikel melebihi batas ditolak tanpa pemotongan diam-diam. PDF scan memerlukan OCR dan belum didukung. Situs yang memerlukan login, menolak unduhan, atau menyajikan isi hanya melalui JavaScript dapat gagal dibaca. Catatan manual tetap dapat digunakan. Ringkasan AI perlu diperiksa terhadap artikel asli.

### Kontak pembayaran dan informasi biaya AI

Di **Admin → Subscription & Kredit → Kontak & pembayaran**, isi WhatsApp (kode negara tanpa `+`), bank, rekening, atas nama, dan instruksi tambahan. Informasi ini ditampilkan di **Paket & Kredit**. Tombol konfirmasi WhatsApp memasukkan nomor permintaan, paket, dan jumlah pembayaran. Tanpa konfigurasi, pengguna diberi pesan untuk menunggu instruksi sebelum membayar.

Tindakan AI menampilkan perkiraan kredit teks berdasarkan panjang bahan dan target tulisan. Estimasi bukan harga tetap dan belum mencakup reasoning atau pembacaan sumber tambahan; penagihan tetap memakai token aktual dari penyedia. Estimasi pembacaan artikel memakai contoh 30.000 karakter karena panjang teks belum diketahui sebelum ekstraksi.

Referensi dapat ditambahkan melalui **Cari referensi**, **Unggah artikel**, atau **Isi manual**. Error validasi tampil lengkap dan mengarahkan fokus ke isian terkait. Isian yang belum tersimpan dilindungi peringatan saat navigasi atau menutup halaman. Dialog ekspor menampilkan opsi Word yang benar-benar dipakai, termasuk daftar isi dan halaman judul.

### Landing page publik

Halaman `/` menampilkan manfaat Riset, fitur, cara kerja, paket aktif dari katalog admin, dan FAQ. Harga mengikuti perubahan paket di admin. Pengunjung diarahkan ke `/masuk`; pengguna yang sudah masuk dapat langsung membuka proyek atau mengajukan paket. Kontak WhatsApp tampil jika sudah diisi di pengaturan pembayaran admin. Ilustrasi naskah pada landing page adalah contoh tampilan, bukan hasil penelitian atau testimoni.
