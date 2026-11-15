-- Sistem Informasi Manajemen Fotostudio Berbasis Web
-- Target: MySQL 8+ / MariaDB dengan InnoDB dan utf8mb4.
CREATE DATABASE IF NOT EXISTS fotostudio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fotostudio;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS log_aktivitas;
DROP TABLE IF EXISTS galeri;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS reservasi;
DROP TABLE IF EXISTS paket_layanan;
DROP TABLE IF EXISTS layanan;
DROP TABLE IF EXISTS paket;
DROP TABLE IF EXISTS studio_mutex;
DROP TABLE IF EXISTS pengguna;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE pengguna (
    id_pengguna BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(20) NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hash dari password_hash(), bukan kata sandi asli',
    peran ENUM('pelanggan','admin','fotografer') NOT NULL DEFAULT 'pelanggan',
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pengguna),
    UNIQUE KEY uq_pengguna_email (email),
    UNIQUE KEY uq_pengguna_username (username),
    KEY idx_pengguna_role_active (peran, aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layanan (
    id_layanan BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_layanan VARCHAR(100) NOT NULL,
    deskripsi VARCHAR(500) NULL,
    harga_satuan DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_layanan),
    UNIQUE KEY uq_layanan_nama (nama_layanan),
    KEY idx_layanan_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paket (
    id_paket BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_paket VARCHAR(100) NOT NULL,
    deskripsi TEXT NOT NULL,
    harga_paket DECIMAL(12,2) NOT NULL,
    durasi_menit SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    gambar VARCHAR(80) NOT NULL DEFAULT 'studio.svg',
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_paket),
    KEY idx_paket_aktif_harga (aktif, harga_paket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paket_layanan (
    id_paket BIGINT UNSIGNED NOT NULL,
    id_layanan BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_paket, id_layanan),
    KEY idx_paket_layanan_layanan (id_layanan),
    CONSTRAINT fk_pl_paket FOREIGN KEY (id_paket) REFERENCES paket (id_paket) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pl_layanan FOREIGN KEY (id_layanan) REFERENCES layanan (id_layanan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Baris tunggal ini dikunci FOR UPDATE untuk menserialisasi transaksi booking/penugasan.
CREATE TABLE studio_mutex (
    id TINYINT UNSIGNED NOT NULL,
    deskripsi VARCHAR(80) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO studio_mutex (id, deskripsi) VALUES (1, 'Kunci transaksi jadwal studio tunggal');

CREATE TABLE reservasi (
    id_reservasi BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_reservasi VARCHAR(24) NOT NULL,
    id_pelanggan BIGINT UNSIGNED NOT NULL,
    id_fotografer BIGINT UNSIGNED NULL,
    id_paket BIGINT UNSIGNED NOT NULL,
    tanggal_sesi DATE NOT NULL,
    jam_sesi TIME NOT NULL,
    durasi_menit_snapshot SMALLINT UNSIGNED NOT NULL,
    harga_paket_snapshot DECIMAL(12,2) NOT NULL,
    status ENUM('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','sedang_foto','selesai','ditolak','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran',
    catatan VARCHAR(1000) NULL,
    hold_expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_reservasi),
    UNIQUE KEY uq_reservasi_kode (kode_reservasi),
    KEY idx_reservasi_jadwal_status (tanggal_sesi, jam_sesi, status),
    KEY idx_reservasi_pelanggan (id_pelanggan, created_at),
    KEY idx_reservasi_fotografer_jadwal (id_fotografer, tanggal_sesi, jam_sesi, status),
    KEY idx_reservasi_status_created (status, created_at),
    CONSTRAINT fk_reservasi_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservasi_fotografer FOREIGN KEY (id_fotografer) REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_reservasi_paket FOREIGN KEY (id_paket) REFERENCES paket (id_paket) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transaksi (
    id_transaksi BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_reservasi BIGINT UNSIGNED NOT NULL,
    jenis_pembayaran ENUM('dp','pelunasan','penuh') NOT NULL DEFAULT 'penuh',
    nominal DECIMAL(12,2) NOT NULL,
    metode ENUM('transfer_manual') NOT NULL DEFAULT 'transfer_manual',
    status_verifikasi ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
    bukti_file VARCHAR(100) NULL COMMENT 'Path relatif dalam storage/uploads/payments, tidak URL publik',
    catatan_verifikasi VARCHAR(500) NULL,
    id_verifikator BIGINT UNSIGNED NULL,
    tanggal_bayar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tanggal_verifikasi DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_transaksi),
    KEY idx_transaksi_reservasi_status (id_reservasi, status_verifikasi),
    KEY idx_transaksi_status_tanggal (status_verifikasi, tanggal_verifikasi),
    CONSTRAINT fk_transaksi_reservasi FOREIGN KEY (id_reservasi) REFERENCES reservasi (id_reservasi) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transaksi_verifikator FOREIGN KEY (id_verifikator) REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE galeri (
    id_foto BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_reservasi BIGINT UNSIGNED NOT NULL,
    id_fotografer BIGINT UNSIGNED NOT NULL,
    url_file VARCHAR(120) NOT NULL COMMENT 'Path relatif privat, akses melalui controller terotorisasi',
    nama_asli VARCHAR(180) NOT NULL,
    mime_type ENUM('image/jpeg','image/png','image/webp') NOT NULL,
    ukuran_byte BIGINT UNSIGNED NOT NULL,
    tipe_akses ENUM('publik','privat') NOT NULL DEFAULT 'privat',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_foto),
    KEY idx_galeri_reservasi (id_reservasi, created_at),
    KEY idx_galeri_akses (tipe_akses, created_at),
    CONSTRAINT fk_galeri_reservasi FOREIGN KEY (id_reservasi) REFERENCES reservasi (id_reservasi) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_galeri_fotografer FOREIGN KEY (id_fotografer) REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE log_aktivitas (
    id_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengguna BIGINT UNSIGNED NULL,
    entitas VARCHAR(40) NOT NULL,
    id_entitas VARCHAR(40) NULL,
    aksi VARCHAR(80) NOT NULL,
    detail_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log),
    KEY idx_log_entitas_waktu (entitas, id_entitas, created_at),
    KEY idx_log_pengguna_waktu (id_pengguna, created_at),
    CONSTRAINT fk_log_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Akun demo: kata sandi contoh hanya untuk pengembangan lokal, ganti sebelum dipakai sungguhan.
INSERT INTO pengguna (id_pengguna, nama, email, no_telepon, username, password, peran, aktif) VALUES
(1, 'Admin Lensa Studio', 'admin@fotostudio.test', '081200000001', 'adminlensa', '$2y$12$9sHrP3WLbLvECVW0KTd5ouY6nVHWKOUGokiaNp..f.oWnZ0KGZQDS', 'admin', 1),
(2, 'Nadia Pelanggan Demo', 'pelanggan@fotostudio.test', '081200000002', 'nadiademo', '$2y$12$PEciIadsL7aew7hXp8GhBOwE8akirjiWuey6bGARpR.k969xZ9jDW', 'pelanggan', 1),
(3, 'Arga Fotografer Demo', 'foto@fotostudio.test', '081200000003', 'argafoto', '$2y$12$kJMA7zA2DR6VE9cMQv/XCO8gSm2ZgUAZ5Uou9YC6njdegPvfVGCaS', 'fotografer', 1);

INSERT INTO layanan (id_layanan, nama_layanan, deskripsi, harga_satuan, aktif) VALUES
(1, 'Sesi Foto Studio', 'Sesi foto di dalam studio dengan pencahayaan terarah.', 150000, 1),
(2, 'Editing Digital', 'Koreksi warna, pencahayaan, dan retouch dasar.', 100000, 1),
(3, 'Cetak Foto', 'Cetak pilihan foto pada kertas foto.', 50000, 1),
(4, 'Backdrop Tematik', 'Penggunaan latar foto sesuai tema yang dipilih.', 75000, 1),
(5, 'Foto Produk', 'Pengambilan foto produk untuk katalog usaha.', 200000, 1),
(6, 'File Digital Resolusi Tinggi', 'Penyerahan file digital resolusi tinggi.', 100000, 1);

INSERT INTO paket (id_paket, nama_paket, deskripsi, harga_paket, durasi_menit, gambar, aktif) VALUES
(1, 'Studio Signature', 'Sesi potret personal dengan pencahayaan profesional, 5 foto pilihan, dan retouch digital dasar.', 500000, 60, 'studio.svg', 1),
(2, 'Cerita Keluarga', 'Sesi foto keluarga dengan panduan pose, variasi komposisi, dan 10 foto pilihan digital.', 850000, 90, 'keluarga.svg', 1),
(3, 'Wisuda Berkesan', 'Potret wisuda dengan backdrop tematik, 8 foto pilihan, dan file digital resolusi tinggi.', 650000, 60, 'wisuda.svg', 1),
(4, 'Produk Profesional', 'Paket pemotretan produk untuk katalog atau media sosial, termasuk koreksi warna dasar.', 950000, 120, 'produk.svg', 1);

INSERT INTO paket_layanan (id_paket, id_layanan) VALUES
(1,1),(1,2),(1,6),
(2,1),(2,2),(2,3),(2,4),
(3,1),(3,2),(3,4),(3,6),
(4,2),(4,5),(4,6);

-- Data reservasi demo menggunakan tanggal mendatang. Tidak menyertakan foto atau bukti pelanggan nyata.
INSERT INTO reservasi (id_reservasi, kode_reservasi, id_pelanggan, id_fotografer, id_paket, tanggal_sesi, jam_sesi, durasi_menit_snapshot, harga_paket_snapshot, status, catatan, hold_expires_at, created_at, updated_at) VALUES
(1, 'FS-DEMO-001', 2, 3, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), '10:00:00', 60, 500000, 'dikonfirmasi', 'Data contoh untuk presentasi.', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW()),
(2, 'FS-DEMO-002', 2, NULL, 2, DATE_ADD(CURDATE(), INTERVAL 16 DAY), '13:00:00', 90, 850000, 'menunggu_verifikasi', 'Contoh reservasi yang menunggu pemeriksaan pembayaran.', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW()),
(3, 'FS-DEMO-003', 2, 3, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '11:00:00', 60, 650000, 'selesai', 'Data contoh untuk demo riwayat.', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW());

INSERT INTO transaksi (id_transaksi, id_reservasi, jenis_pembayaran, nominal, metode, status_verifikasi, bukti_file, catatan_verifikasi, id_verifikator, tanggal_bayar, tanggal_verifikasi, created_at, updated_at) VALUES
(1, 1, 'penuh', 500000, 'transfer_manual', 'disetujui', NULL, 'Data demonstrasi: pembayaran contoh disetujui.', 1, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 2, 'dp', 255000, 'transfer_manual', 'menunggu', 'payments/demo_transfer.png', NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 3, 'penuh', 650000, 'transfer_manual', 'disetujui', NULL, 'Data demonstrasi: pembayaran contoh disetujui.', 1, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));

-- Catatan: foto demo privat sengaja tidak disemai. Fotografer mengunggah foto melalui aplikasi.
