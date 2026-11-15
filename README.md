# Sistem Informasi Manajemen Foto Studio Berbasis Web

Aplikasi web untuk mengelola katalog paket, reservasi sesi, pembayaran transfer manual, verifikasi admin, penugasan fotografer, distribusi hasil foto privat, serta laporan operasional. Nama antarmuka demo: **Lensa Studio**.

> **Status pengujian lokal:** pada PHP 8.4.27, `php tests/run_checks.php` menghasilkan **29 lulus dan 0 gagal**. Pemeriksaan sintaks PHP juga telah dijalankan. Ekstensi `fileinfo` dan `pdo_mysql` aktif pada lingkungan Laragon. Status ini merupakan hasil pengujian lokal yang dicatat untuk tugas Praktik Pipeline CI.

## Praktik Pipeline CI (Tugas Team-Based Project)

Bagian ini mendokumentasikan penerapan *Continuous Integration* pada repository project Foto Studio sesuai tahapan **Siapkan, Otomatiskan, dan Buktikan**.

### A. Repository dan berkas workflow

- **Repository:** [Project-RPL – GitHub](https://github.com/farridfaqih-beep/Project-RPL)
- **Berkas workflow:** [`.github/workflows/ci.yml`](https://github.com/farridfaqih-beep/Project-RPL/blob/main/.github/workflows/ci.yml)
- **Platform CI:** GitHub Actions
- **Nama workflow:** `PHP CI - Foto Studio`
- **Job:** `Pemeriksaan PHP dan pengujian`

Workflow disimpan pada direktori `.github/workflows/` agar dikenali dan dijalankan oleh GitHub Actions.

### B. 1 — Siapkan: lingkungan dan pengujian lokal

Sebelum workflow dijalankan, project diperiksa dan diuji pada komputer lokal. Lingkungan pengembangan yang digunakan:

| Komponen | Konfigurasi |
|---|---|
| Sistem lokal | Windows dengan Laragon |
| PHP lokal | PHP 8.4.27 |
| Web server | Apache melalui Laragon |
| Database | MySQL/MariaDB |
| Ekstensi PHP | `fileinfo`, `pdo_mysql` |
| Pengujian otomatis | `tests/run_checks.php` |

Jalankan perintah berikut dari direktori utama project.

**Memeriksa versi PHP dan ekstensi:**

```bash
php -v
php -m
```

Pastikan `fileinfo` dan `pdo_mysql` tercantum pada daftar ekstensi.

**Menjalankan pengujian project:**

```bash
php tests/run_checks.php
```

**Memeriksa sintaks file PHP di PowerShell:**

```powershell
Get-ChildItem app,public,config,tests -Recurse -Filter *.php |
    ForEach-Object { php -l $_.FullName }
```

Pengujian otomatis memeriksa aturan slot reservasi, validasi tanggal dan waktu, tumpang tindih sesi, perhitungan DP, status pembayaran, keberadaan file utama, beberapa ketentuan skema database, kontrol peran, penyimpanan privat, dan hash kata sandi akun demo. Hasil pengujian lokal setelah perbaikan adalah **29 lulus dan 0 gagal**.

### C. 2 — Otomatiskan: alur GitHub Actions

Workflow dijalankan secara otomatis pada peristiwa berikut:

| Peristiwa | Branch yang dipantau |
|---|---|
| `push` | `main`, `develop`, `feature/**`, `fitur/**` |
| `pull_request` | Pull Request yang ditujukan ke `main` atau `develop` |

Runner yang digunakan adalah `ubuntu-latest`. Di dalam workflow, GitHub Actions melakukan tahapan berikut:

1. Mengambil source code dengan `actions/checkout`.
2. Menyiapkan PHP **8.4** menggunakan `shivammathur/setup-php@v2`.
3. Mengaktifkan ekstensi `fileinfo` dan `pdo_mysql`.
4. Menampilkan versi PHP dan daftar ekstensi untuk memeriksa lingkungan.
5. Menjalankan `php -l` pada file PHP di folder `app`, `public`, `config`, dan `tests`.
6. Menjalankan pengujian otomatis dengan `php tests/run_checks.php`.

**Menjalankan langkah pemeriksaan CI secara lokal:**

```bash
php tests/run_checks.php
```

Project ini tidak menggunakan Composer atau library PHP pihak ketiga. Karena itu, workflow tidak menjalankan `composer install`; penyiapan lingkungan dilakukan dengan memasang PHP 8.4 dan ekstensi yang diperlukan. Project PHP ini juga tidak memiliki proses kompilasi terpisah, sehingga pemeriksaan sintaks `php -l` dan pengujian otomatis digunakan sebagai pemeriksaan kode sebelum perubahan diterima.

Untuk melihat hasil workflow di GitHub, buka repository, pilih tab **Actions**, kemudian pilih workflow **PHP CI - Foto Studio**. Status hijau menunjukkan seluruh langkah selesai; status merah menunjukkan ada langkah yang gagal.

### D. 3 — Buktikan: contoh pipeline gagal dan perbaikannya

Untuk membuktikan bahwa pipeline dapat mendeteksi kesalahan, dilakukan simulasi terkontrol pada branch `fitur/demo-ci-failure`. Kesalahan hanya dibuat pada nilai harapan di file pengujian, bukan pada rumus aplikasi.

**Pengujian sebelum simulasi:** nilai DP awal minimal 30% dari Rp850.000 adalah:

```text
Rp850.000 x 30% = Rp255.000
```

**Kesalahan yang disimulasikan:** nilai harapan pada `tests/run_checks.php` diubah dari `255000` menjadi `255001`.

```php
// Nilai harapan yang sengaja dibuat salah untuk simulasi CI gagal
check(BookingRules::minimumFirstPayment(850000) === 255001, 'DP awal minimal 30 persen dihitung benar');
```

Akibatnya, pengujian menampilkan `[GAGAL] DP awal minimal 30 persen dihitung benar`, hasil lokal menjadi **28 lulus dan 1 gagal**, dan proses pengujian mengembalikan *exit code* `1`. GitHub Actions kemudian menandai run tersebut sebagai **Failed**.

**Perbaikan:** nilai harapan dikembalikan ke `255000` sesuai hasil perhitungan 30%.

```php
// Nilai harapan yang benar setelah perbaikan
check(BookingRules::minimumFirstPayment(850000) === 255000, 'DP awal minimal 30 persen dihitung benar');
```

Setelah perbaikan, pengujian lokal kembali menghasilkan **29 lulus dan 0 gagal**. Perubahan dikirim ke branch demonstrasi dan GitHub Actions menjalankan workflow kembali dengan hasil **Success**.

| Bukti | Hasil | Tautan |
|---|---|---|
| Simulasi nilai harapan salah | **Failed** | [Lihat run CI gagal](https://github.com/farridfaqih-beep/Project-RPL/actions/runs/43697927247) |
| Pengujian setelah nilai diperbaiki | **Success** | [Lihat run CI berhasil setelah perbaikan](https://github.com/farridfaqih-beep/Project-RPL/actions/runs/43698258789) |

Simulasi dilakukan pada branch `fitur/demo-ci-failure` dan tidak digabungkan ke `main`. Dengan demikian, contoh kegagalan dan perbaikannya dapat ditunjukkan tanpa meninggalkan perubahan simulasi pada branch utama.

### E. Pembagian peran tim

Kelompok terdiri dari **3 anggota**. Isi nama anggota sesuai anggota kelompok yang sebenarnya sebelum README dikumpulkan. Pembagian tanggung jawab berikut disusun untuk tiga orang dan dapat disesuaikan dengan pekerjaan yang benar-benar dilakukan.

| Anggota | Tanggung jawab | Hasil pekerjaan |
|---|---|---|
| [Rizka Dwi Zaliyanti] | Persiapan repository dan pemeriksaan lokal | Memastikan project dapat dijalankan, menyiapkan lingkungan PHP, dan menjalankan pengujian lokal. |
| [Kasih Nurlianita] | Pembuatan workflow GitHub Actions | Menyusun `ci.yml`, mengatur trigger `push` dan `pull_request`, serta konfigurasi PHP dan ekstensi. |
| [Muhammad Fariid Faqih] | Pengujian, perbaikan, dokumentasi, dan bukti | Menjalankan simulasi pipeline gagal, memperbaiki pengujian, memverifikasi pipeline berhasil, memperbarui README, dan mengumpulkan tautan serta tangkapan layar. |

---

## 1. Acuan perancangan dan keputusan implementasi

Implementasi mengikuti laporan kelompok: tiga aktor (Pelanggan, Admin, Fotografer), pola client-server, arsitektur logis tiga lapis, modul registrasi/login, katalog, reservasi, pembayaran/verifikasi, pelaksanaan dan dokumentasi foto, serta pelaporan. Tugas 1 memilih SDLC Waterfall untuk tahapan proyek; materi kuliah pertemuan 5–7 digunakan untuk menjaga keterlacakan kebutuhan, desain modular/separation of concerns, arsitektur berlapis/MVC, dan strategi pengujian.

| Acuan di laporan | Implementasi |
|---|---|
| Pengguna, layanan, paket, paket_layanan, reservasi, transaksi, galeri | Tabel inti dipertahankan dengan nama dan fungsi sepadan pada SQL. |
| Three-tier / client-server | `app/views` sebagai presentation; controllers/services/rules sebagai application; repositories/PDO dan MySQL sebagai data layer. |
| Pelanggan: katalog, booking, status pembayaran, hasil foto | Beranda/katalog, formulir reservasi, riwayat/detail, unggah bukti, dan halaman hasil foto. |
| Admin: operasional dan laporan | Dashboard, CRUD layanan/paket/pengguna, reservasi, verifikasi, penugasan fotografer, galeri, laporan. |
| Fotografer: jadwal, status tugas, unggah foto | Dashboard daftar tugas terbatas pada ID fotografer yang login, perubahan status, unggah hasil. |
| Keamanan dan pencegahan bentrok | Prepared statements PDO, hashing, CSRF, server-side role checks, penyimpanan file privat, transaksi dan baris mutex InnoDB. |

### Perubahan skema dari rancangan awal

Perubahan berikut disengaja dan terdokumentasi, bukan perubahan diam-diam:

1. **`paket_layanan`** menggunakan primary key komposit `(id_paket, id_layanan)` supaya satu relasi layanan tidak dapat dimasukkan dua kali.
2. **`paket`** ditambah `durasi_menit`, ilustrasi lokal dan `aktif`. Durasi diperlukan untuk mengecek tumpang tindih antar-sesi; status aktif memungkinkan paket dinonaktifkan tanpa menghapus riwayat.
3. **`reservasi`** ditambah `kode_reservasi`, `durasi_menit_snapshot`, `harga_paket_snapshot`, catatan, dan `hold_expires_at`. Snapshot mencegah perubahan harga paket mengubah tagihan lama; kode memberi referensi invoice; masa berlaku mencegah slot tidak dibayar terkunci selamanya.
4. **`transaksi`** memisahkan `jenis_pembayaran` (DP/pelunasan/penuh) dari `status_verifikasi` (menunggu/disetujui/ditolak), serta menambahkan path bukti, catatan admin, verifikator, dan waktu verifikasi. Ini menyelesaikan kekurangan skema awal yang mencampur jenis pembayaran dengan status verifikasi dan belum memiliki field bukti yang bisa digunakan.
5. **`galeri`** menyimpan ID fotografer pengunggah, nama asli yang dibatasi, MIME type, ukuran, dan path privat. File tidak disajikan langsung dari URL storage; akses lewat controller yang memeriksa izin.
6. **`pengguna`** ditambah status aktif dan timestamp; **`studio_mutex`** menambahkan baris kunci InnoDB untuk menserialisasi pembuatan reservasi dan penugasan; **`log_aktivitas`** mencatat perubahan penting seperti verifikasi dan penugasan.

### Asumsi teknis yang digunakan

- Model saat ini mengelola **satu ruang studio**. Bila kelak ada lebih dari satu ruang, tambahkan entitas/resource studio dan kunci per ruang.
- Jam operasional demo 09.00–18.00, slot ditawarkan per 30 menit, dengan durasi mengikuti paket.
- Reservasi yang belum membayar menahan jadwal selama 24 jam. Reservasi yang sedang diverifikasi, dikonfirmasi, atau sedang dipotret tetap memblokir jadwal sampai ditolak/dibatalkan/selesai.
- Pembayaran pertama minimal 30% harga paket. Pembayaran menggunakan **transfer manual**, bukan payment gateway. Setelah ada pembayaran yang disetujui, reservasi menunggu pembayaran/verifikasi dapat dikonfirmasi.
- Hasil foto baru disimpan sebagai **privat**. Admin hanya menjadikan foto publik jika sudah ada persetujuan yang diperlukan.
- Tidak ada proses refund otomatis pada versi ini. Jika reservasi dibatalkan setelah pembayaran disetujui, pencatatan pengembalian dana harus ditangani sebagai proses terpisah sebelum produksi.
- Notifikasi pada versi ini berupa status/kartu di dashboard; bukan push notification, email, atau WhatsApp otomatis.

## 2. Teknologi

- PHP 8.1+ (pengujian lokal dan GitHub Actions menggunakan PHP 8.4; versi lokal yang diverifikasi adalah PHP 8.4.27)
- MySQL 8+ atau MariaDB dengan InnoDB
- PDO + driver `pdo_mysql`
- Bootstrap 5.3 dan CSS khusus responsif
- PHP `fileinfo` untuk validasi unggahan
- Tidak memerlukan Composer atau library PHP pihak ketiga

## 3. Struktur direktori

```text
fotostudio-web/
├── .github/
│   └── workflows/
│       └── ci.yml           # workflow GitHub Actions
├── app/
│   ├── Core/Database.php
│   ├── Support/helpers.php
│   ├── Support/BookingRules.php
│   ├── Repositories.php
│   ├── Services.php
│   ├── Controllers.php
│   └── views/                # template presentation menurut peran
├── config/config.php
├── database/schema.sql      # skema dan data demo
├── public/
│   ├── index.php            # entry point dan route
│   └── assets/              # CSS, JS, ilustrasi lokal
├── storage/uploads/         # file privat, bukan web root
├── tests/run_checks.php
├── .env.example
└── README.md
```

## 4. Instalasi lokal dengan Laragon

1. Ekstrak folder project ke `C:\laragon\www\fotostudio-web`.
2. Buka Laragon dan jalankan Apache serta MySQL/MariaDB.
3. Buka phpMyAdmin atau klien MySQL, lalu impor `database/schema.sql`. File ini membuat database `fotostudio`, semua tabel/relasi/indeks, akun demo, katalog, dan beberapa reservasi contoh. **Jangan impor ulang ke basis data yang sudah berisi data yang ingin dipertahankan**, karena skrip pengembangan ini menghapus lalu membuat ulang tabel-tabel project.
4. Salin `.env.example` menjadi `.env`. Atur `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS` sesuai instalasi MySQL Anda. Nilai bawaan cocok untuk Laragon umum (`127.0.0.1`, port `3306`, user `root`, password kosong); konfigurasi aktual mungkin berbeda.
5. Pastikan PHP mengaktifkan `pdo_mysql` dan `fileinfo`, serta `file_uploads=On`. Atur `upload_max_filesize` dan `post_max_size` sekurang-kurangnya 13M jika ingin menggunakan batas unggah bawaan 12 MB.
6. Buka `http://localhost/fotostudio-web/public/` pada browser. Di lingkungan virtual host, arahkan document root ke folder `public/`. Jangan jadikan folder `storage/` sebagai direktori publik.
7. Pastikan folder `storage/uploads/payments` dan `storage/uploads/gallery` dapat ditulis oleh proses PHP. Folder `storage/` berisi `.htaccess` untuk menolak akses langsung jika Apache mengizinkannya.

### Menjalankan pemeriksaan kode

Di terminal pada folder project:

```bash
php tests/run_checks.php
find app public config tests -name "*.php" -print0 | xargs -0 -n1 php -l
```

Perintah `find` tersedia di Git Bash/WSL. Di PowerShell, gunakan:

```powershell
Get-ChildItem app,public,config,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## 5. Akun demo

Semua akun di bawah hanya untuk demonstrasi lokal. Segera ubah kata sandi pada menu Profil sebelum menyebarkan aplikasi ke jaringan lain.

| Peran | Email | Username | Kata sandi awal |
|---|---|---|---|
| Admin | `admin@fotostudio.test` | `adminlensa` | `Admin123!` |
| Fotografer | `foto@fotostudio.test` | `argafoto` | `Foto123!` |
| Pelanggan | `pelanggan@fotostudio.test` | `nadiademo` | `Pelanggan123!` |

Bukti transfer di reservasi demo adalah gambar contoh bertanda **BUKAN BUKTI PEMBAYARAN ASLI**. Foto hasil pelanggan tidak disemai; fotografer dapat mengunggah foto milik reservasi tugasnya.

## 6. Ringkasan cara penggunaan

### Pelanggan
1. Daftar akun atau masuk.
2. Buka katalog, pilih paket, tentukan tanggal dan slot yang tersedia.
3. Buka detail reservasi, pilih bayar, isi nominal, dan unggah bukti transfer berbentuk JPG/PNG/WebP.
4. Pantau hasil verifikasi di detail reservasi. Setelah pembayaran pertama disetujui, reservasi dikonfirmasi; fotografer ditugaskan oleh admin.
5. Setelah fotografer mengunggah hasil, buka menu **Hasil Foto Saya** dan unduh file yang diizinkan untuk reservasi milik akun tersebut.

### Admin
Buka Dashboard untuk melihat ringkasan. Gunakan menu Kelola Layanan dan Paket untuk katalog; Reservasi untuk pembatalan/penolakan dan penugasan; Pembayaran untuk memeriksa bukti, menyetujui atau menolak dengan catatan; Pengguna untuk mengatur akun; Galeri untuk mengubah visibilitas; Laporan untuk memilih rentang tanggal.

### Fotografer
Dashboard hanya memuat sesi yang ditugaskan kepada fotografer yang login. Perbarui status menjadi sedang pemotretan, tandai selesai, lalu unggah hasil foto terkait. File yang diunggah berstatus privat sampai admin mengubahnya.

## 7. Aturan laporan

Pendapatan hanya menghitung baris `transaksi` dengan `status_verifikasi = 'disetujui'`, berdasarkan tanggal verifikasinya. DP dan pelunasan dihitung satu kali per baris pembayaran yang disetujui. Pembayaran menunggu atau ditolak tidak dihitung sebagai pendapatan terkonfirmasi. Statistik reservasi menggunakan tanggal pembuatan reservasi, sedangkan statistik pendapatan menggunakan tanggal verifikasi pembayaran.

## 8. Pengujian dan verifikasi project

### Pengujian otomatis lokal

Pengujian berikut telah dijalankan pada PHP 8.4.27:

- Pemeriksaan sintaks seluruh file PHP menggunakan `php -l` — berhasil.
- `php tests/run_checks.php` — **29 lulus dan 0 gagal**.
- Ekstensi `fileinfo` dan `pdo_mysql` — aktif.

Pengujian otomatis pada `tests/run_checks.php` mencakup validasi slot dan durasi reservasi, aturan pembayaran, keberadaan file utama, pemeriksaan struktur skema, kontrol peran, penyimpanan privat, dan validitas hash akun demo.

### Verifikasi aplikasi melalui Laragon

Database `fotostudio` tersedia pada MySQL Laragon dengan sembilan tabel: `galeri`, `layanan`, `log_aktivitas`, `paket`, `paket_layanan`, `pengguna`, `reservasi`, `studio_mutex`, dan `transaksi`. Aplikasi juga berhasil dibuka melalui `http://localhost/fotostudio-web/public/`. Pengujian fungsional aplikasi telah dilakukan secara manual dan dinyatakan berhasil oleh pengembang project.

### Skenario verifikasi integrasi

Daftar berikut menjadi acuan untuk pengujian ulang atau demonstrasi fitur aplikasi. Skenario ini berbeda dari pengujian otomatis CI yang dijalankan oleh `tests/run_checks.php`.

| No. | Skenario | Hasil yang diharapkan |
|---:|---|---|
| 1 | Login dengan dan tanpa kredensial benar | Kredensial benar masuk; kredensial salah ditolak. |
| 2 | Akun pelanggan membuka route admin | Server menolak akses (403), bukan sekadar menyembunyikan menu. |
| 3 | Pelanggan membuka reservasi atau foto milik pelanggan lain | Akses ditolak atau data tidak ditemukan. |
| 4 | Reservasi pada slot kosong | Reservasi tersimpan dengan status menunggu pembayaran. |
| 5 | Reservasi pada waktu yang bertabrakan | Server menolak reservasi yang bertumpang tindih. |
| 6 | Admin menyetujui atau menolak bukti pembayaran | Status verifikasi, catatan, dan waktu verifikasi tersimpan. |
| 7 | Penugasan fotografer pada jadwal bentrok | Server menolak penugasan yang bertabrakan. |
| 8 | Unggah JPG/PNG/WebP yang valid dan di bawah batas ukuran | File disimpan pada storage privat dan metadata tersimpan. |
| 9 | Unggah file dengan format atau ukuran yang tidak diizinkan | Server menolak file. |
| 10 | Unduh foto oleh pelanggan yang berhak dan tidak berhak | Pemilik yang berhak dapat mengakses; pengguna lain ditolak. |
| 11 | Laporan dengan transaksi menunggu atau ditolak | Hanya transaksi yang disetujui dihitung sebagai pendapatan. |
| 12 | Form wajib kosong atau format tidak sesuai | Validasi sisi server menolak input yang tidak sesuai. |

Untuk bukti demonstrasi CI pada tugas ini, gunakan dua tautan run **Failed** dan **Success** pada bagian *Buktikan* di atas. Hasil tersebut menunjukkan pipeline mendeteksi pengujian yang salah dan kembali berhasil setelah pengujian diperbaiki.

## 9. Batasan versi ini

- Tidak terintegrasi ke payment gateway dan tidak mengirim email/WhatsApp otomatis.
- Laporan belum menyediakan ekspor PDF/Excel; laporan tampil dalam web.
- Satu ruang studio, tanpa kalender multi-ruang.
- Portofolio publik membutuhkan tindakan admin dan izin pemilik foto.
- Deployment produksi memerlukan HTTPS, pencadangan database/files, kebijakan retensi foto, serta kebijakan refund dan privasi usaha.
