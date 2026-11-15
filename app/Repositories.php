<?php
declare(strict_types=1);

final class UserRepository
{
    public function findByLogin(string $login): ?array
    {
        $stmt = db()->prepare('SELECT * FROM pengguna WHERE (email = :email OR username = :username) AND aktif = 1 LIMIT 1');
        $stmt->execute(['email' => $login, 'username' => $login]);
        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT id_pengguna, nama, email, no_telepon, username, peran, aktif, created_at FROM pengguna WHERE id_pengguna = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function all(?string $role = null): array
    {
        $sql = 'SELECT id_pengguna, nama, email, no_telepon, username, peran, aktif, created_at FROM pengguna';
        $params = [];
        if ($role && in_array($role, ['pelanggan', 'admin', 'fotografer'], true)) {
            $sql .= ' WHERE peran = :role';
            $params['role'] = $role;
        }
        $sql .= ' ORDER BY created_at DESC, id_pengguna DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function photographers(): array
    {
        $stmt = db()->query("SELECT id_pengguna, nama, email FROM pengguna WHERE peran = 'fotografer' AND aktif = 1 ORDER BY nama");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = db()->prepare('INSERT INTO pengguna (nama, email, no_telepon, username, password, peran, aktif, created_at, updated_at) VALUES (:nama, :email, :telepon, :username, :password, :role, 1, NOW(), NOW())');
        $stmt->execute([
            'nama' => trim($data['nama']),
            'email' => strtolower(trim($data['email'])),
            'telepon' => trim($data['no_telepon'] ?? ''),
            'username' => trim($data['username']),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['peran'],
        ]);
        return (int) db()->lastInsertId();
    }

    public function updateProfile(int $id, string $name, string $phone): void
    {
        $stmt = db()->prepare('UPDATE pengguna SET nama = :nama, no_telepon = :phone, updated_at = NOW() WHERE id_pengguna = :id');
        $stmt->execute(['nama' => trim($name), 'phone' => trim($phone), 'id' => $id]);
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = db()->prepare('UPDATE pengguna SET password = :password, updated_at = NOW() WHERE id_pengguna = :id');
        $stmt->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE pengguna SET aktif = :active, updated_at = NOW() WHERE id_pengguna = :id AND peran <> \'admin\'');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }
}

final class CatalogRepository
{
    public function services(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM layanan';
        if ($activeOnly) $sql .= ' WHERE aktif = 1';
        $sql .= ' ORDER BY nama_layanan';
        return db()->query($sql)->fetchAll();
    }

    public function allPackages(bool $activeOnly = true): array
    {
        $sql = 'SELECT p.*, GROUP_CONCAT(DISTINCT l.nama_layanan ORDER BY l.nama_layanan SEPARATOR ", ") AS layanan_list FROM paket p LEFT JOIN paket_layanan pl ON pl.id_paket = p.id_paket LEFT JOIN layanan l ON l.id_layanan = pl.id_layanan';
        if ($activeOnly) $sql .= ' WHERE p.aktif = 1';
        $sql .= ' GROUP BY p.id_paket ORDER BY p.aktif DESC, p.harga_paket ASC';
        return db()->query($sql)->fetchAll();
    }

    public function package(int $id, bool $activeOnly = false): ?array
    {
        $sql = 'SELECT p.*, GROUP_CONCAT(DISTINCT l.nama_layanan ORDER BY l.nama_layanan SEPARATOR ", ") AS layanan_list FROM paket p LEFT JOIN paket_layanan pl ON pl.id_paket = p.id_paket LEFT JOIN layanan l ON l.id_layanan = pl.id_layanan WHERE p.id_paket = :id';
        if ($activeOnly) $sql .= ' AND p.aktif = 1';
        $sql .= ' GROUP BY p.id_paket LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function serviceIdsForPackage(int $packageId): array
    {
        $stmt = db()->prepare('SELECT id_layanan FROM paket_layanan WHERE id_paket = :id');
        $stmt->execute(['id' => $packageId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'id_layanan'));
    }

    public function saveService(array $data): int
    {
        if (!empty($data['id_layanan'])) {
            $stmt = db()->prepare('UPDATE layanan SET nama_layanan = :name, deskripsi = :description, harga_satuan = :price, updated_at = NOW() WHERE id_layanan = :id');
            $stmt->execute(['name' => trim($data['nama_layanan']), 'description' => trim($data['deskripsi'] ?? ''), 'price' => $data['harga_satuan'], 'id' => (int) $data['id_layanan']]);
            return (int) $data['id_layanan'];
        }
        $stmt = db()->prepare('INSERT INTO layanan (nama_layanan, deskripsi, harga_satuan, aktif, created_at, updated_at) VALUES (:name, :description, :price, 1, NOW(), NOW())');
        $stmt->execute(['name' => trim($data['nama_layanan']), 'description' => trim($data['deskripsi'] ?? ''), 'price' => $data['harga_satuan']]);
        return (int) db()->lastInsertId();
    }

    public function setServiceActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE layanan SET aktif = :active, updated_at = NOW() WHERE id_layanan = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function savePackage(array $data, array $serviceIds): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (!empty($data['id_paket'])) {
                $id = (int) $data['id_paket'];
                $stmt = $pdo->prepare('UPDATE paket SET nama_paket = :name, deskripsi = :description, harga_paket = :price, durasi_menit = :duration, gambar = :image, updated_at = NOW() WHERE id_paket = :id');
                $stmt->execute(['name' => trim($data['nama_paket']), 'description' => trim($data['deskripsi']), 'price' => $data['harga_paket'], 'duration' => (int) $data['durasi_menit'], 'image' => $data['gambar'], 'id' => $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO paket (nama_paket, deskripsi, harga_paket, durasi_menit, gambar, aktif, created_at, updated_at) VALUES (:name, :description, :price, :duration, :image, 1, NOW(), NOW())');
                $stmt->execute(['name' => trim($data['nama_paket']), 'description' => trim($data['deskripsi']), 'price' => $data['harga_paket'], 'duration' => (int) $data['durasi_menit'], 'image' => $data['gambar']]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM paket_layanan WHERE id_paket = :id')->execute(['id' => $id]);
            $link = $pdo->prepare('INSERT INTO paket_layanan (id_paket, id_layanan) VALUES (:package_id, :service_id)');
            foreach (array_unique(array_map('intval', $serviceIds)) as $serviceId) {
                $link->execute(['package_id' => $id, 'service_id' => $serviceId]);
            }
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function setPackageActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE paket SET aktif = :active, updated_at = NOW() WHERE id_paket = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }
}

final class BookingRepository
{
    public function lockStudio(): void
    {
        db()->query('SELECT id FROM studio_mutex WHERE id = 1 FOR UPDATE')->fetch();
    }

    public function hasConflict(string $date, string $time, int $durationMinutes, ?int $photographerId = null, ?int $excludeId = null): bool
    {
        $start = $date . ' ' . $time . ':00';
        $end = (new DateTimeImmutable($start))->modify('+' . $durationMinutes . ' minutes')->format('Y-m-d H:i:s');
        $sql = "SELECT r.id_reservasi FROM reservasi r WHERE r.tanggal_sesi = :date AND r.status IN ('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','sedang_foto') AND (r.status <> 'menunggu_pembayaran' OR r.hold_expires_at > NOW()) AND TIMESTAMP(r.tanggal_sesi, r.jam_sesi) < :end_time AND DATE_ADD(TIMESTAMP(r.tanggal_sesi, r.jam_sesi), INTERVAL r.durasi_menit_snapshot MINUTE) > :start_time";
        $params = ['date' => $date, 'end_time' => $end, 'start_time' => $start];
        if ($photographerId !== null) {
            $sql .= ' AND r.id_fotografer = :photographer_id';
            $params['photographer_id'] = $photographerId;
        }
        if ($excludeId !== null) {
            $sql .= ' AND r.id_reservasi <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $code = 'FS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = db()->prepare('INSERT INTO reservasi (kode_reservasi, id_pelanggan, id_fotografer, id_paket, tanggal_sesi, jam_sesi, durasi_menit_snapshot, harga_paket_snapshot, status, catatan, hold_expires_at, created_at, updated_at) VALUES (:code, :customer, NULL, :package, :date, :time, :duration, :price, \'menunggu_pembayaran\', :note, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())');
        $stmt->execute([
            'code' => $code,
            'customer' => (int) $data['id_pelanggan'],
            'package' => (int) $data['id_paket'],
            'date' => $data['tanggal_sesi'],
            'time' => $data['jam_sesi'],
            'duration' => (int) $data['durasi_menit'],
            'price' => $data['harga_paket'],
            'note' => trim($data['catatan'] ?? ''),
        ]);
        return (int) db()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT r.*, p.nama_paket, p.gambar AS gambar_paket, p.deskripsi AS deskripsi_paket, u.nama AS nama_pelanggan, u.email AS email_pelanggan, u.no_telepon AS telepon_pelanggan, f.nama AS nama_fotografer FROM reservasi r JOIN paket p ON p.id_paket = r.id_paket JOIN pengguna u ON u.id_pengguna = r.id_pelanggan LEFT JOIN pengguna f ON f.id_pengguna = r.id_fotografer WHERE r.id_reservasi = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function forCustomer(int $customerId): array
    {
        $stmt = db()->prepare('SELECT r.*, p.nama_paket, p.gambar AS gambar_paket, (SELECT COALESCE(SUM(t.nominal),0) FROM transaksi t WHERE t.id_reservasi = r.id_reservasi AND t.status_verifikasi = \'disetujui\') AS total_terbayar, (SELECT COUNT(*) FROM transaksi t WHERE t.id_reservasi = r.id_reservasi AND t.status_verifikasi = \'menunggu\') AS pembayaran_menunggu FROM reservasi r JOIN paket p ON p.id_paket = r.id_paket WHERE r.id_pelanggan = :customer ORDER BY r.created_at DESC');
        $stmt->execute(['customer' => $customerId]);
        return $stmt->fetchAll();
    }

    public function all(?string $status = null): array
    {
        $sql = 'SELECT r.*, p.nama_paket, u.nama AS nama_pelanggan, f.nama AS nama_fotografer, (SELECT COALESCE(SUM(t.nominal),0) FROM transaksi t WHERE t.id_reservasi = r.id_reservasi AND t.status_verifikasi = \'disetujui\') AS total_terbayar FROM reservasi r JOIN paket p ON p.id_paket = r.id_paket JOIN pengguna u ON u.id_pengguna = r.id_pelanggan LEFT JOIN pengguna f ON f.id_pengguna = r.id_fotografer';
        $params = [];
        if ($status && in_array($status, ['menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','sedang_foto','selesai','ditolak','dibatalkan'], true)) {
            $sql .= ' WHERE r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.tanggal_sesi DESC, r.jam_sesi DESC, r.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function photographerTasks(int $photographerId): array
    {
        $stmt = db()->prepare("SELECT r.*, p.nama_paket, u.nama AS nama_pelanggan, u.no_telepon AS telepon_pelanggan FROM reservasi r JOIN paket p ON p.id_paket = r.id_paket JOIN pengguna u ON u.id_pengguna = r.id_pelanggan WHERE r.id_fotografer = :photographer AND r.status IN ('dikonfirmasi','sedang_foto','selesai') ORDER BY r.tanggal_sesi DESC, r.jam_sesi DESC");
        $stmt->execute(['photographer' => $photographerId]);
        return $stmt->fetchAll();
    }

    public function assignPhotographer(int $bookingId, int $photographerId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE reservasi SET id_fotografer = :photographer, updated_at = NOW() WHERE id_reservasi = :id');
        $stmt->execute(['photographer' => $photographerId, 'id' => $bookingId]);
    }

    public function updateStatus(int $bookingId, string $status): void
    {
        $stmt = db()->prepare('UPDATE reservasi SET status = :status, updated_at = NOW() WHERE id_reservasi = :id');
        $stmt->execute(['status' => $status, 'id' => $bookingId]);
    }

    public function setStatusForPhotographer(int $bookingId, int $photographerId, string $status): bool
    {
        $allowedTransition = match ($status) {
            'sedang_foto' => 'dikonfirmasi',
            'selesai' => 'sedang_foto',
            default => null,
        };
        if ($allowedTransition === null) return false;
        $stmt = db()->prepare('UPDATE reservasi SET status = :status, updated_at = NOW() WHERE id_reservasi = :id AND id_fotografer = :photographer AND status = :current_status');
        $stmt->execute(['status' => $status, 'id' => $bookingId, 'photographer' => $photographerId, 'current_status' => $allowedTransition]);
        return $stmt->rowCount() === 1;
    }
}

final class PaymentRepository
{
    public function forReservation(int $reservationId): array
    {
        $stmt = db()->prepare('SELECT t.*, v.nama AS nama_verifikator FROM transaksi t LEFT JOIN pengguna v ON v.id_pengguna = t.id_verifikator WHERE t.id_reservasi = :id ORDER BY t.created_at DESC');
        $stmt->execute(['id' => $reservationId]);
        return $stmt->fetchAll();
    }

    public function sums(int $reservationId): array
    {
        $stmt = db()->prepare("SELECT COALESCE(SUM(CASE WHEN status_verifikasi = 'disetujui' THEN nominal ELSE 0 END),0) AS approved, COALESCE(SUM(CASE WHEN status_verifikasi = 'menunggu' THEN nominal ELSE 0 END),0) AS pending FROM transaksi WHERE id_reservasi = :id");
        $stmt->execute(['id' => $reservationId]);
        $row = $stmt->fetch() ?: ['approved' => 0, 'pending' => 0];
        return ['approved' => (float) $row['approved'], 'pending' => (float) $row['pending']];
    }

    public function create(int $reservationId, string $kind, float $amount, string $proofPath): int
    {
        $stmt = db()->prepare('INSERT INTO transaksi (id_reservasi, jenis_pembayaran, nominal, metode, status_verifikasi, bukti_file, tanggal_bayar, created_at, updated_at) VALUES (:reservation, :kind, :amount, \'transfer_manual\', \'menunggu\', :proof, NOW(), NOW(), NOW())');
        $stmt->execute(['reservation' => $reservationId, 'kind' => $kind, 'amount' => $amount, 'proof' => $proofPath]);
        return (int) db()->lastInsertId();
    }

    public function pending(): array
    {
        return db()->query("SELECT t.*, r.kode_reservasi, r.harga_paket_snapshot, r.status AS status_reservasi, p.nama_paket, u.nama AS nama_pelanggan, u.email AS email_pelanggan FROM transaksi t JOIN reservasi r ON r.id_reservasi = t.id_reservasi JOIN paket p ON p.id_paket = r.id_paket JOIN pengguna u ON u.id_pengguna = r.id_pelanggan WHERE t.status_verifikasi = 'menunggu' ORDER BY t.created_at ASC")->fetchAll();
    }

    public function all(?string $status = null): array
    {
        $sql = 'SELECT t.*, r.kode_reservasi, p.nama_paket, u.nama AS nama_pelanggan, v.nama AS nama_verifikator FROM transaksi t JOIN reservasi r ON r.id_reservasi = t.id_reservasi JOIN paket p ON p.id_paket = r.id_paket JOIN pengguna u ON u.id_pengguna = r.id_pelanggan LEFT JOIN pengguna v ON v.id_pengguna = t.id_verifikator';
        $params = [];
        if ($status && in_array($status, ['menunggu','disetujui','ditolak'], true)) {
            $sql .= ' WHERE t.status_verifikasi = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY t.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function verify(int $paymentId, int $adminId, string $decision, string $note): void
    {
        if (!in_array($decision, ['disetujui', 'ditolak'], true)) throw new InvalidArgumentException('Keputusan verifikasi tidak valid.');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT t.*, r.id_reservasi, r.harga_paket_snapshot, r.status AS status_reservasi FROM transaksi t JOIN reservasi r ON r.id_reservasi = t.id_reservasi WHERE t.id_transaksi = :id FOR UPDATE');
            $stmt->execute(['id' => $paymentId]);
            $payment = $stmt->fetch();
            if (!$payment) throw new RuntimeException('Transaksi tidak ditemukan.');
            if ($payment['status_verifikasi'] !== 'menunggu') throw new RuntimeException('Transaksi ini sudah diverifikasi.');
            if ($decision === 'disetujui' && in_array($payment['status_reservasi'], ['ditolak', 'dibatalkan', 'selesai'], true)) {
                throw new RuntimeException('Pembayaran tidak dapat disetujui karena reservasi sudah ditolak, dibatalkan, atau selesai. Periksa kebijakan pengembalian dana secara terpisah.');
            }
            if ($decision === 'disetujui') {
                $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM transaksi WHERE id_reservasi = :id AND status_verifikasi = 'disetujui'");
                $sumStmt->execute(['id' => $payment['id_reservasi']]);
                $approvedBefore = (float) $sumStmt->fetchColumn();
                if ($approvedBefore + (float) $payment['nominal'] > (float) $payment['harga_paket_snapshot'] + 0.001) {
                    throw new RuntimeException('Nominal ini melebihi sisa tagihan. Periksa pembayaran terverifikasi sebelumnya.');
                }
            }
            $update = $pdo->prepare('UPDATE transaksi SET status_verifikasi = :decision, catatan_verifikasi = :note, id_verifikator = :admin, tanggal_verifikasi = NOW(), updated_at = NOW() WHERE id_transaksi = :id');
            $update->execute(['decision' => $decision, 'note' => trim($note), 'admin' => $adminId, 'id' => $paymentId]);

            $sumAfterStmt = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM transaksi WHERE id_reservasi = :id AND status_verifikasi = 'disetujui'");
            $sumAfterStmt->execute(['id' => $payment['id_reservasi']]);
            $approvedAfter = (float) $sumAfterStmt->fetchColumn();
            $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE id_reservasi = :id AND status_verifikasi = 'menunggu'");
            $pendingStmt->execute(['id' => $payment['id_reservasi']]);
            $hasPending = (int) $pendingStmt->fetchColumn() > 0;

            $bookingStatus = $payment['status_reservasi'];
            if ($decision === 'disetujui' && $approvedAfter > 0 && in_array($bookingStatus, ['menunggu_pembayaran', 'menunggu_verifikasi'], true)) {
                $bookingStatus = 'dikonfirmasi';
            } elseif ($decision === 'ditolak' && !$hasPending && $approvedAfter <= 0 && in_array($bookingStatus, ['menunggu_verifikasi'], true)) {
                $bookingStatus = 'menunggu_pembayaran';
            }
            $upBooking = $pdo->prepare('UPDATE reservasi SET status = :status, updated_at = NOW() WHERE id_reservasi = :id');
            $upBooking->execute(['status' => $bookingStatus, 'id' => $payment['id_reservasi']]);
            audit_log($adminId, 'transaksi', $paymentId, 'verifikasi_' . $decision, ['nominal' => (float) $payment['nominal'], 'catatan' => trim($note), 'id_reservasi' => (int) $payment['id_reservasi']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

final class GalleryRepository
{
    public function publicPhotos(int $limit = 8): array
    {
        $limit = max(1, min(24, $limit));
        return db()->query("SELECT g.*, r.kode_reservasi, p.nama_paket FROM galeri g JOIN reservasi r ON r.id_reservasi = g.id_reservasi JOIN paket p ON p.id_paket = r.id_paket WHERE g.tipe_akses = 'publik' ORDER BY g.created_at DESC LIMIT " . $limit)->fetchAll();
    }

    public function forCustomer(int $customerId): array
    {
        $stmt = db()->prepare("SELECT g.*, r.kode_reservasi, p.nama_paket FROM galeri g JOIN reservasi r ON r.id_reservasi = g.id_reservasi JOIN paket p ON p.id_paket = r.id_paket WHERE r.id_pelanggan = :customer AND (g.tipe_akses = 'privat' OR g.tipe_akses = 'publik') ORDER BY g.created_at DESC");
        $stmt->execute(['customer' => $customerId]);
        return $stmt->fetchAll();
    }

    public function forAdmin(): array
    {
        return db()->query('SELECT g.*, r.kode_reservasi, p.nama_paket, u.nama AS nama_pelanggan, f.nama AS nama_fotografer FROM galeri g JOIN reservasi r ON r.id_reservasi = g.id_reservasi JOIN paket p ON p.id_paket = r.id_paket JOIN pengguna u ON u.id_pengguna = r.id_pelanggan JOIN pengguna f ON f.id_pengguna = g.id_fotografer ORDER BY g.created_at DESC')->fetchAll();
    }

    public function find(int $photoId): ?array
    {
        $stmt = db()->prepare('SELECT g.*, r.id_pelanggan, r.id_fotografer AS fotografer_tugas, r.kode_reservasi FROM galeri g JOIN reservasi r ON r.id_reservasi = g.id_reservasi WHERE g.id_foto = :id');
        $stmt->execute(['id' => $photoId]);
        return $stmt->fetch() ?: null;
    }

    public function forPhotographerReservation(int $reservationId, int $photographerId): bool
    {
        $stmt = db()->prepare('SELECT id_reservasi FROM reservasi WHERE id_reservasi = :id AND id_fotografer = :photographer');
        $stmt->execute(['id' => $reservationId, 'photographer' => $photographerId]);
        return (bool) $stmt->fetch();
    }

    public function create(int $reservationId, int $photographerId, string $path, string $mime, int $size, string $originalName): int
    {
        $stmt = db()->prepare("INSERT INTO galeri (id_reservasi, id_fotografer, url_file, nama_asli, mime_type, ukuran_byte, tipe_akses, created_at) VALUES (:reservation, :photographer, :path, :original, :mime, :size, 'privat', NOW())");
        $stmt->execute(['reservation' => $reservationId, 'photographer' => $photographerId, 'path' => $path, 'original' => substr($originalName, 0, 180), 'mime' => $mime, 'size' => $size]);
        return (int) db()->lastInsertId();
    }

    public function setAccess(int $photoId, string $access): void
    {
        if (!in_array($access, ['privat', 'publik'], true)) throw new InvalidArgumentException('Tipe akses tidak valid.');
        $stmt = db()->prepare('UPDATE galeri SET tipe_akses = :access WHERE id_foto = :id');
        $stmt->execute(['access' => $access, 'id' => $photoId]);
    }

    public function delete(int $photoId): ?string
    {
        $stmt = db()->prepare('SELECT url_file FROM galeri WHERE id_foto = :id');
        $stmt->execute(['id' => $photoId]);
        $path = $stmt->fetchColumn();
        if ($path === false) return null;
        $delete = db()->prepare('DELETE FROM galeri WHERE id_foto = :id');
        $delete->execute(['id' => $photoId]);
        return (string) $path;
    }
}

final class ReportRepository
{
    public function dashboard(): array
    {
        $counts = db()->query("SELECT COUNT(*) AS total, SUM(status IN ('menunggu_pembayaran','menunggu_verifikasi')) AS menunggu, SUM(status = 'dikonfirmasi') AS dikonfirmasi, SUM(status = 'selesai') AS selesai FROM reservasi")->fetch() ?: [];
        $payment = db()->query("SELECT COALESCE(SUM(nominal),0) AS total FROM transaksi WHERE status_verifikasi = 'disetujui'")->fetch() ?: ['total' => 0];
        $pendingPayments = (int) db()->query("SELECT COUNT(*) FROM transaksi WHERE status_verifikasi = 'menunggu'")->fetchColumn();
        $recent = db()->query('SELECT r.id_reservasi, r.kode_reservasi, r.status, r.tanggal_sesi, r.jam_sesi, u.nama AS nama_pelanggan, p.nama_paket FROM reservasi r JOIN pengguna u ON u.id_pengguna = r.id_pelanggan JOIN paket p ON p.id_paket = r.id_paket ORDER BY r.created_at DESC LIMIT 6')->fetchAll();
        return ['counts' => $counts, 'revenue' => (float) $payment['total'], 'pending_payments' => $pendingPayments, 'recent' => $recent];
    }

    public function report(string $from, string $to): array
    {
        $summary = db()->prepare("SELECT COALESCE(SUM(t.nominal),0) AS pendapatan, COUNT(DISTINCT t.id_transaksi) AS jumlah_pembayaran FROM transaksi t WHERE t.status_verifikasi = 'disetujui' AND DATE(t.tanggal_verifikasi) BETWEEN :from_date AND :to_date");
        $summary->execute(['from_date' => $from, 'to_date' => $to]);
        $sum = $summary->fetch() ?: ['pendapatan' => 0, 'jumlah_pembayaran' => 0];
        $bookings = db()->prepare('SELECT COUNT(*) AS total, SUM(status = \'selesai\') AS selesai, SUM(status = \'dibatalkan\') AS dibatalkan, SUM(status = \'ditolak\') AS ditolak FROM reservasi WHERE DATE(created_at) BETWEEN :from_date AND :to_date');
        $bookings->execute(['from_date' => $from, 'to_date' => $to]);
        $bookingStats = $bookings->fetch() ?: [];
        $monthly = db()->prepare("SELECT DATE_FORMAT(t.tanggal_verifikasi, '%Y-%m') AS bulan, SUM(t.nominal) AS pendapatan, COUNT(*) AS jumlah FROM transaksi t WHERE t.status_verifikasi = 'disetujui' AND DATE(t.tanggal_verifikasi) BETWEEN :from_date AND :to_date GROUP BY DATE_FORMAT(t.tanggal_verifikasi, '%Y-%m') ORDER BY bulan");
        $monthly->execute(['from_date' => $from, 'to_date' => $to]);
        $byPackage = db()->prepare("SELECT p.nama_paket, COUNT(r.id_reservasi) AS jumlah FROM reservasi r JOIN paket p ON p.id_paket = r.id_paket WHERE DATE(r.created_at) BETWEEN :from_date AND :to_date GROUP BY p.id_paket, p.nama_paket ORDER BY jumlah DESC");
        $byPackage->execute(['from_date' => $from, 'to_date' => $to]);
        return ['summary' => $sum, 'bookings' => $bookingStats, 'monthly' => $monthly->fetchAll(), 'packages' => $byPackage->fetchAll()];
    }
}
