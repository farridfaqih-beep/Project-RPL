
## 10. Continuous Integration (GitHub Actions)

Project ini menggunakan GitHub Actions untuk menjalankan pemeriksaan kode dan pengujian otomatis.

### Pemicu workflow

Workflow berada pada `.github/workflows/ci.yml`.

Workflow dijalankan ketika:
- Push ke branch `main`, `develop`, `feature/**`, dan `fitur/**`.
- Pull Request yang ditujukan ke branch `main` atau `develop`.

### Proses CI

Tahapan yang dijalankan secara otomatis:
1. Mengambil source code repository.
2. Menyiapkan PHP 8.4.
3. Mengaktifkan ekstensi `fileinfo` dan `pdo_mysql`.
4. Memeriksa instalasi PHP dan ekstensi.
5. Memeriksa sintaks seluruh file PHP menggunakan `php -l`.
6. Menjalankan pengujian melalui `php tests/run_checks.php`.

Project ini tidak menggunakan Composer atau library PHP pihak ketiga, sehingga tidak memerlukan `composer install`.

### Menjalankan pengujian lokal

Jalankan perintah berikut dari direktori utama project:

```bash
php -v
php -m
php tests/run_checks.php
```

Pengujian dinyatakan berhasil apabila seluruh pemeriksaan lulus dan tidak terdapat pengujian gagal.

### Bukti demonstrasi CI

Dilakukan simulasi kegagalan dengan mengubah nilai harapan pengujian DP awal dari 255000 menjadi 255001.

- [Bukti pipeline gagal](https://github.com/farridfaqih-beep/Project-RPL/actions/runs/43697927247)
- [Bukti pipeline berhasil setelah perbaikan](https://github.com/farridfaqih-beep/Project-RPL/actions/runs/43698258789)

Setelah nilai harapan dikembalikan menjadi 255000, pengujian lokal kembali menghasilkan 29 lulus dan 0 gagal.
