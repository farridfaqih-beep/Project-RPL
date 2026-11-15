# Sistem Informasi Manajemen Foto Studio Berbasis Web

Aplikasi web untuk mengelola katalog paket, reservasi sesi, pembayaran transfer manual, verifikasi admin, penugasan fotografer, distribusi hasil foto privat, serta laporan operasional. Nama antarmuka demo: **Lensa Studio**.

> **Status validasi saat ini:** seluruh berkas PHP telah melewati `php -l`, dan pengujian aturan bisnis tanpa basis data tersedia melalui `php tests/run_checks.php`. Integrasi MySQL dan alur di browser belum dapat dinyatakan lulus pada workspace pembuat karena runtime ini tidak memiliki driver PDO MySQL atau server MySQL/MariaDB. Jalankan langkah pengujian integrasi pada lingkungan lokal setelah impor SQL.

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

- PHP 8.1+ (direkomendasikan PHP 8.2 atau lebih baru)
- MySQL 8+ atau MariaDB dengan InnoDB
- PDO + driver `pdo_mysql`
- Bootstrap 5.3 dan CSS khusus responsif
- PHP `fileinfo` untuk validasi unggahan
- Tidak memerlukan Composer atau library PHP pihak ketiga

## 3. Struktur direktori

```text
fotostudio-web/
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

## 8. Matriks pengujian dan status

### Yang telah benar-benar dijalankan pada workspace pembuat

- **Pemeriksaan sintaks PHP:** seluruh file `.php` yang ada telah dijalankan melalui `php -l` dan lolos.
- **Pengujian aturan bisnis tanpa DB:** `tests/run_checks.php` menguji validasi tanggal/jam, batas jam studio, tumpang tindih durasi, ambang DP, status pembayaran, keberadaan komponen utama, aspek kunci skema, serta hash akun demo.
- **Belum dijalankan:** query terhadap MySQL, pengujian konkurensi InnoDB sesungguhnya, dan pengujian alur melalui browser karena runtime yang digunakan tidak menyediakan driver PDO MySQL maupun layanan MySQL/MariaDB.

### Skenario integrasi yang harus dijalankan setelah database disiapkan

| No. | Skenario | Hasil yang diharapkan |
|---:|---|---|
| 1 | Login dengan dan tanpa kredensial benar | Kredensial benar masuk; salah ditolak dengan pesan umum. |
| 2 | Akun pelanggan membuka route admin | Server menjawab akses ditolak (403), bukan sekadar menyembunyikan menu. |
| 3 | Pelanggan mengubah ID ke reservasi/foto pelanggan lain | Detail tidak ditemukan atau unduhan ditolak. |
| 4 | Reservasi pada slot kosong | Reservasi tersimpan berstatus menunggu pembayaran. |
| 5 | Reservasi di waktu yang bertabrakan, termasuk sesi yang tumpang tindih sebagian | Server menolak; saat permintaan serentak, baris `studio_mutex` menyerialisasi transaksi. |
| 6 | Admin menyetujui atau menolak bukti pembayaran | Status verifikasi, catatan dan waktu verifikasi tersimpan; reservasi berubah sesuai aturan. |
| 7 | Menugaskan fotografer dengan jadwal bentrok | Server menolak penugasan. |
| 8 | Unggah JPG/PNG/WebP yang valid di bawah batas ukuran | File berada di storage privat dan metadata tersimpan. |
| 9 | Unggah PDF, SVG, file palsu bermime gambar, atau file di atas batas | Server menolak. |
| 10 | Unduh foto sebagai pelanggan yang berhak vs tidak berhak | Pemilik dapat mengakses; pengguna lain menerima 403. |
| 11 | Membuka laporan dengan transaksi menunggu/ditolak | Nominal tersebut tidak dihitung sebagai pendapatan; hanya transaksi disetujui yang masuk. |
| 12 | Form wajib kosong / format tidak sesuai | Validasi sisi server menolak dan pesan ditampilkan. |

Catat hasil aktual dari setiap skenario di komputer pengembangan sebelum laporan akhir menyebutnya sebagai lulus. Untuk menguji bentrok serentak secara bermakna, gunakan dua sesi browser atau permintaan yang dikirim hampir bersamaan ke tanggal/jam yang sama.

## 9. Batasan versi ini

- Tidak terintegrasi ke payment gateway dan tidak mengirim email/WhatsApp otomatis.
- Laporan belum menyediakan ekspor PDF/Excel; laporan tampil dalam web.
- Satu ruang studio, tanpa kalender multi-ruang.
- Portofolio publik membutuhkan tindakan admin dan izin pemilik foto.
- Deployment produksi memerlukan HTTPS, pencadangan database/files, kebijakan retensi foto, serta kebijakan refund dan privasi usaha.
