<?php
declare(strict_types=1);

final class AuthService
{
    public function __construct(private UserRepository $users = new UserRepository()) {}

    public function login(string $login, string $password): ?array
    {
        $user = $this->users->findByLogin(trim($login));
        if (!$user || !password_verify($password, $user['password'])) return null;
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id_pengguna'];
        unset($user['password']);
        return $user;
    }

    public function registerCustomer(array $data): int
    {
        $name = trim((string) ($data['nama'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($name === '' || strlen($name) > 100) throw new InvalidArgumentException('Nama wajib diisi dan maksimal 100 karakter.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) throw new InvalidArgumentException('Alamat email tidak valid.');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) throw new InvalidArgumentException('Username harus 3–50 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).');
        if (strlen((string) ($data['no_telepon'] ?? '')) > 20) throw new InvalidArgumentException('Nomor telepon maksimal 20 karakter.');
        if (strlen($password) < 8) throw new InvalidArgumentException('Kata sandi minimal 8 karakter.');
        $confirm = (string) ($data['konfirmasi_password'] ?? '');
        if (!hash_equals($password, $confirm)) throw new InvalidArgumentException('Konfirmasi kata sandi tidak sama.');
        try {
            return $this->users->create([
                'nama' => $name,
                'email' => $email,
                'no_telepon' => (string) ($data['no_telepon'] ?? ''),
                'username' => $username,
                'password' => $password,
                'peran' => 'pelanggan',
            ]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') throw new InvalidArgumentException('Email atau username sudah digunakan.');
            throw $e;
        }
    }
}

final class UploadService
{
    public function storeImage(array $file, string $category): array
    {
        $allowedCategories = ['payments', 'gallery'];
        if (!in_array($category, $allowedCategories, true)) throw new InvalidArgumentException('Kategori berkas tidak valid.');
        if (!isset($file['error']) || is_array($file['error'])) throw new InvalidArgumentException('Berkas unggahan tidak valid.');
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = match ($file['error']) {
                UPLOAD_ERR_NO_FILE => 'Pilih berkas gambar terlebih dahulu.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran berkas melebihi batas server.',
                default => 'Berkas gagal diunggah. Coba lagi.',
            };
            throw new InvalidArgumentException($message);
        }
        $maxBytes = (int) $GLOBALS['config']['upload_max_mb'] * 1024 * 1024;
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            throw new InvalidArgumentException('Ukuran gambar harus lebih dari 0 dan maksimal ' . $GLOBALS['config']['upload_max_mb'] . ' MB.');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) throw new InvalidArgumentException('Sumber berkas tidak dikenali sebagai unggahan HTTP yang sah.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) throw new InvalidArgumentException('Format gambar yang diterima hanya JPG, PNG, atau WebP.');
        $imageInfo = @getimagesize($tmp);
        if (!$imageInfo || !isset($imageInfo['mime']) || $imageInfo['mime'] !== $mime) {
            throw new InvalidArgumentException('Isi berkas tidak dikenali sebagai gambar yang valid.');
        }
        $targetDir = BASE_PATH . '/storage/uploads/' . $category;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Direktori penyimpanan berkas tidak dapat dibuat.');
        }
        $fileName = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $targetDir . '/' . $fileName)) {
            throw new RuntimeException('Gambar gagal disimpan. Periksa izin folder storage/uploads.');
        }
        return [
            'path' => $category . '/' . $fileName,
            'mime' => $mime,
            'size' => (int) $file['size'],
            'original_name' => basename((string) ($file['name'] ?? 'foto')),
        ];
    }

    public function absolutePath(string $relativePath): ?string
    {
        $normal = str_replace('\\', '/', $relativePath);
        if ($normal === '' || str_contains($normal, '..') || str_starts_with($normal, '/')) return null;
        if (!preg_match('#^(payments|gallery)/[A-Za-z0-9._-]+\.(jpg|png|webp)$#', $normal)) return null;
        $full = BASE_PATH . '/storage/uploads/' . $normal;
        return is_file($full) ? $full : null;
    }
}

final class BookingService
{
    public function __construct(
        private BookingRepository $bookings = new BookingRepository(),
        private CatalogRepository $catalog = new CatalogRepository(),
        private UserRepository $users = new UserRepository()
    ) {}

    public function reserve(int $customerId, array $input): int
    {
        $packageId = filter_var($input['id_paket'] ?? null, FILTER_VALIDATE_INT);
        $date = trim((string) ($input['tanggal_sesi'] ?? ''));
        $time = trim((string) ($input['jam_sesi'] ?? ''));
        $note = trim((string) ($input['catatan'] ?? ''));
        if (!$packageId) throw new InvalidArgumentException('Paket foto wajib dipilih.');
        $package = $this->catalog->package((int) $packageId, true);
        if (!$package) throw new InvalidArgumentException('Paket tidak ditemukan atau sedang tidak tersedia.');
        $duration = (int) $package['durasi_menit'];
        $slot = BookingRules::validateSlot($date, $time, $duration);
        if (strlen($note) > 1000) throw new InvalidArgumentException('Catatan maksimal 1.000 karakter.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // A singleton InnoDB row serializes new bookings, including requests that arrive at the same time.
            $this->bookings->lockStudio();
            if ($this->bookings->hasConflict($slot['date'], $slot['time'], $duration)) {
                throw new InvalidArgumentException('Slot tersebut bertabrakan dengan reservasi lain. Silakan pilih jam berbeda.');
            }
            $id = $this->bookings->create([
                'id_pelanggan' => $customerId,
                'id_paket' => (int) $packageId,
                'tanggal_sesi' => $slot['date'],
                'jam_sesi' => $slot['time_sql'],
                'durasi_menit' => $duration,
                'harga_paket' => $package['harga_paket'],
                'catatan' => $note,
            ]);
            audit_log($customerId, 'reservasi', $id, 'pengajuan_reservasi', ['tanggal' => $slot['date'], 'jam' => $slot['time'], 'paket' => $package['nama_paket']]);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function assign(int $bookingId, int $photographerId, int $adminId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $this->bookings->lockStudio();
            $booking = $this->bookings->find($bookingId);
            if (!$booking) throw new RuntimeException('Reservasi tidak ditemukan.');
            if ($booking['status'] !== 'dikonfirmasi') throw new InvalidArgumentException('Fotografer hanya dapat ditugaskan pada reservasi yang sudah dikonfirmasi setelah pembayaran diverifikasi.');
            $photographer = $this->users->find($photographerId);
            if (!$photographer || $photographer['peran'] !== 'fotografer' || (int) $photographer['aktif'] !== 1) {
                throw new InvalidArgumentException('Akun fotografer tidak valid atau tidak aktif.');
            }
            $date = $booking['tanggal_sesi'];
            $time = substr((string) $booking['jam_sesi'], 0, 5);
            $duration = (int) $booking['durasi_menit_snapshot'];
            if ($this->bookings->hasConflict($date, $time, $duration, null, $bookingId)) {
                throw new InvalidArgumentException('Studio sedang memiliki jadwal lain yang bertabrakan.');
            }
            if ($this->bookings->hasConflict($date, $time, $duration, $photographerId, $bookingId)) {
                throw new InvalidArgumentException('Fotografer tersebut sudah memiliki tugas pada waktu yang bertabrakan.');
            }
            $this->bookings->assignPhotographer($bookingId, $photographerId);
            audit_log($adminId, 'reservasi', $bookingId, 'penugasan_fotografer', ['id_fotografer' => $photographerId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

final class PaymentService
{
    public function __construct(
        private PaymentRepository $payments = new PaymentRepository(),
        private BookingRepository $bookings = new BookingRepository()
    ) {}

    public function submit(int $customerId, int $bookingId, array $input, array $file, UploadService $uploads): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        $stored = null;
        try {
            $this->bookings->lockStudio();
            $stmt = $pdo->prepare('SELECT * FROM reservasi WHERE id_reservasi = :id AND id_pelanggan = :customer FOR UPDATE');
            $stmt->execute(['id' => $bookingId, 'customer' => $customerId]);
            $booking = $stmt->fetch();
            if (!$booking) throw new RuntimeException('Reservasi tidak ditemukan atau bukan milik akun Anda.');
            if (in_array($booking['status'], ['ditolak', 'dibatalkan', 'selesai'], true)) throw new InvalidArgumentException('Pembayaran tidak dapat ditambahkan untuk reservasi ini.');
            $sums = $this->payments->sums($bookingId);
            $outstanding = max(0, (float) $booking['harga_paket_snapshot'] - $sums['approved'] - $sums['pending']);
            $amount = filter_var($input['nominal'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($amount === false || $amount <= 0 || $amount > $outstanding + 0.001) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih dari Rp 0 dan tidak melebihi sisa tagihan (' . money($outstanding) . ').');
            }
            if ($outstanding <= 0.001) throw new InvalidArgumentException('Tagihan reservasi ini sudah lunas atau sedang menunggu verifikasi pembayaran terakhir.');
            $kind = in_array($input['jenis_pembayaran'] ?? '', ['dp','pelunasan','penuh'], true) ? $input['jenis_pembayaran'] : 'dp';
            if (($sums['approved'] + $sums['pending']) <= 0 && $amount < BookingRules::minimumFirstPayment((float) $booking['harga_paket_snapshot'])) {
                throw new InvalidArgumentException('Pembayaran pertama minimal 30% dari harga paket sebagai DP.');
            }
            if ($amount + 0.001 >= $outstanding) $kind = ($sums['approved'] <= 0 ? 'penuh' : 'pelunasan');
            $stored = $uploads->storeImage($file, 'payments');
            $paymentId = $this->payments->create($bookingId, $kind, (float) $amount, $stored['path']);
            $update = $pdo->prepare("UPDATE reservasi SET status = 'menunggu_verifikasi', updated_at = NOW() WHERE id_reservasi = :id AND status IN ('menunggu_pembayaran','menunggu_verifikasi')");
            $update->execute(['id' => $bookingId]);
            audit_log($customerId, 'transaksi', $paymentId, 'unggah_bukti_pembayaran', ['nominal' => $amount, 'jenis' => $kind]);
            $pdo->commit();
            return $paymentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($stored) {
                $path = (new UploadService())->absolutePath($stored['path']);
                if ($path) @unlink($path);
            }
            throw $e;
        }
    }
}
