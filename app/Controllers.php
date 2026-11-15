<?php
declare(strict_types=1);

final class HomeController
{
    public function home(): void
    {
        $catalog = new CatalogRepository();
        $gallery = new GalleryRepository();
        render('home', [
            'title' => 'Beranda',
            'packages' => $catalog->allPackages(true),
            'services' => $catalog->services(true),
            'photos' => $gallery->publicPhotos(6),
        ]);
    }

    public function package(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        $package = (new CatalogRepository())->package((int) $id, true);
        if (!$package) {
            http_response_code(404);
            render('errors/404', ['title' => 'Paket tidak ditemukan']);
            return;
        }
        render('package-detail', ['title' => $package['nama_paket'], 'package' => $package]);
    }

    public function availability(): void
    {
        $date = trim((string) ($_GET['date'] ?? ''));
        $packageId = filter_var($_GET['package_id'] ?? null, FILTER_VALIDATE_INT);
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date || $dateObj < new DateTimeImmutable('today') || !$packageId) {
            json_response(['ok' => false, 'message' => 'Tanggal atau paket tidak valid.', 'slots' => []], 422);
        }
        $package = (new CatalogRepository())->package((int) $packageId, true);
        if (!$package) json_response(['ok' => false, 'message' => 'Paket tidak tersedia.', 'slots' => []], 404);
        $duration = (int) $package['durasi_menit'];
        $bookingRepo = new BookingRepository();
        $slots = [];
        for ($minute = 9 * 60; $minute <= 17 * 60 + 30; $minute += 30) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            $end = (new DateTimeImmutable($date . ' ' . $time))->modify('+' . $duration . ' minutes');
            if ($end->format('Y-m-d') !== $date || $end->format('H:i') > '18:00') continue;
            if ($date === date('Y-m-d') && $time <= date('H:i')) continue;
            if (!$bookingRepo->hasConflict($date, $time, $duration)) $slots[] = ['value' => $time, 'label' => $time . ' - ' . $end->format('H:i')];
        }
        json_response(['ok' => true, 'slots' => $slots, 'duration' => $duration, 'message' => count($slots) ? '' : 'Tidak ada slot kosong pada tanggal tersebut.']);
    }
}

final class AuthController
{
    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post();
            $login = trim((string) ($_POST['login'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($login === '' || $password === '') {
                flash('error', 'Email/username dan kata sandi wajib diisi.');
                redirect_to('login');
            }
            $user = (new AuthService())->login($login, $password);
            if (!$user) {
                flash('error', 'Email/username atau kata sandi tidak cocok.');
                redirect_to('login');
            }
            flash('success', 'Selamat datang, ' . $user['nama'] . '!');
            $route = match ($user['peran']) {
                'admin' => 'admin',
                'fotografer' => 'photographer',
                default => 'home',
            };
            redirect_to($route);
        }
        if (current_user()) redirect_to('home');
        render('auth/login', ['title' => 'Masuk']);
    }

    public function register(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post();
            try {
                $id = (new AuthService())->registerCustomer($_POST);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                flash('success', 'Akun berhasil dibuat. Selamat datang di Lensa Studio!');
                redirect_to('home');
            } catch (InvalidArgumentException $e) {
                flash('error', $e->getMessage());
                redirect_to('register');
            } catch (Throwable $e) {
                error_log((string) $e);
                flash('error', 'Registrasi belum berhasil. Periksa kembali data atau coba beberapa saat lagi.');
                redirect_to('register');
            }
        }
        if (current_user()) redirect_to('home');
        render('auth/register', ['title' => 'Daftar Akun']);
    }

    public function logout(): void
    {
        require_post();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        session_start();
        flash('success', 'Anda sudah keluar dari sistem.');
        redirect_to('home');
    }

    public function profile(): void
    {
        $user = require_login();
        $repo = new UserRepository();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post();
            $name = trim((string) ($_POST['nama'] ?? ''));
            $phone = trim((string) ($_POST['no_telepon'] ?? ''));
            if ($name === '' || strlen($name) > 100 || strlen($phone) > 20) {
                flash('error', 'Nama wajib diisi (maksimal 100 karakter) dan nomor telepon maksimal 20 karakter.');
                redirect_to('profile');
            }
            try {
                $newPassword = (string) ($_POST['password_baru'] ?? '');
                if ($newPassword !== '') {
                    $oldPassword = (string) ($_POST['password_lama'] ?? '');
                    $stmt = db()->prepare('SELECT password FROM pengguna WHERE id_pengguna = :id');
                    $stmt->execute(['id' => $user['id_pengguna']]);
                    $hash = (string) $stmt->fetchColumn();
                    if (!password_verify($oldPassword, $hash)) throw new InvalidArgumentException('Kata sandi lama tidak benar.');
                    if (strlen($newPassword) < 8 || !hash_equals($newPassword, (string) ($_POST['konfirmasi_password'] ?? ''))) {
                        throw new InvalidArgumentException('Kata sandi baru minimal 8 karakter dan konfirmasinya harus sama.');
                    }
                }
                $repo->updateProfile((int) $user['id_pengguna'], $name, $phone);
                if ($newPassword !== '') {
                    $repo->updatePassword((int) $user['id_pengguna'], $newPassword);
                    session_regenerate_id(true);
                }
                audit_log((int) $user['id_pengguna'], 'pengguna', (int) $user['id_pengguna'], 'ubah_profil');
                flash('success', 'Profil berhasil diperbarui.');
            } catch (InvalidArgumentException $e) {
                flash('error', $e->getMessage());
            } catch (Throwable $e) {
                error_log((string) $e);
                flash('error', 'Profil belum dapat diperbarui.');
            }
            redirect_to('profile');
        }
        render('profile', ['title' => 'Profil Akun', 'profile' => $user]);
    }

    public function download(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        $photo = (new GalleryRepository())->find((int) $id);
        if (!$photo) {
            http_response_code(404);
            exit('File tidak ditemukan.');
        }
        $user = current_user();
        $authorized = $photo['tipe_akses'] === 'publik';
        if ($user) {
            $authorized = $authorized
                || $user['peran'] === 'admin'
                || ((int) $photo['id_pelanggan'] === (int) $user['id_pengguna'])
                || ($user['peran'] === 'fotografer' && (int) $photo['fotografer_tugas'] === (int) $user['id_pengguna']);
        }
        if (!$authorized) {
            http_response_code(403);
            exit('Anda tidak memiliki izin untuk mengakses hasil foto ini.');
        }
        $uploads = new UploadService();
        $path = $uploads->absolutePath((string) $photo['url_file']);
        if (!$path) {
            http_response_code(404);
            exit('File foto tidak tersedia di penyimpanan.');
        }
        header('Content-Type: ' . $photo['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . (($_GET['inline'] ?? '') === '1' ? 'inline' : 'attachment') . '; filename="hasil-foto-' . (int) $photo['id_foto'] . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        readfile($path);
        exit;
    }

    public function proof(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        $stmt = db()->prepare('SELECT t.*, r.id_pelanggan, r.kode_reservasi FROM transaksi t JOIN reservasi r ON r.id_reservasi = t.id_reservasi WHERE t.id_transaksi = :id');
        $stmt->execute(['id' => $id]);
        $payment = $stmt->fetch();
        if (!$payment) { http_response_code(404); exit('Bukti pembayaran tidak ditemukan.'); }
        $user = require_login();
        if ($user['peran'] !== 'admin' && (int) $payment['id_pelanggan'] !== (int) $user['id_pengguna']) {
            http_response_code(403); exit('Anda tidak memiliki izin untuk melihat bukti pembayaran ini.');
        }
        $path = (new UploadService())->absolutePath((string) $payment['bukti_file']);
        if (!$path) { http_response_code(404); exit('Berkas bukti pembayaran tidak tersedia.'); }
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="bukti-' . (int) $payment['id_transaksi'] . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        readfile($path);
        exit;
    }
}

final class CustomerController
{
    public function book(): void
    {
        $user = require_role('pelanggan');
        $packageId = filter_input(INPUT_GET, 'package_id', FILTER_VALIDATE_INT) ?: 0;
        $package = (new CatalogRepository())->package((int) $packageId, true);
        if (!$package) {
            flash('error', 'Pilih paket foto yang masih tersedia.');
            redirect_to('home');
        }
        render('customer/book', ['title' => 'Buat Reservasi', 'package' => $package, 'customer' => $user]);
    }

    public function bookingStore(): void
    {
        $user = require_role('pelanggan');
        require_post();
        try {
            $id = (new BookingService())->reserve((int) $user['id_pengguna'], $_POST);
            flash('success', 'Reservasi dibuat. Silakan periksa tagihan dan unggah bukti pembayaran.');
            redirect_to('reservation', ['id' => $id]);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);
            flash('error', 'Reservasi belum dapat disimpan. Periksa jadwal lalu coba kembali.');
        }
        redirect_to('book', ['package_id' => (int) ($_POST['id_paket'] ?? 0)]);
    }

    public function reservations(): void
    {
        $user = require_role('pelanggan');
        render('customer/reservations', ['title' => 'Reservasi Saya', 'reservations' => (new BookingRepository())->forCustomer((int) $user['id_pengguna'])]);
    }

    public function reservation(): void
    {
        $user = require_role('pelanggan');
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        $booking = (new BookingRepository())->find((int) $id);
        if (!$booking || (int) $booking['id_pelanggan'] !== (int) $user['id_pengguna']) {
            http_response_code(404); render('errors/404', ['title' => 'Reservasi tidak ditemukan']); return;
        }
        $payments = (new PaymentRepository())->forReservation((int) $id);
        $sums = (new PaymentRepository())->sums((int) $id);
        $outstanding = max(0, (float) $booking['harga_paket_snapshot'] - $sums['approved'] - $sums['pending']);
        render('customer/reservation-detail', ['title' => 'Detail Reservasi', 'booking' => $booking, 'payments' => $payments, 'sums' => $sums, 'outstanding' => $outstanding]);
    }

    public function pay(): void
    {
        $user = require_role('pelanggan');
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        $booking = (new BookingRepository())->find((int) $id);
        if (!$booking || (int) $booking['id_pelanggan'] !== (int) $user['id_pengguna']) {
            http_response_code(404); render('errors/404', ['title' => 'Reservasi tidak ditemukan']); return;
        }
        if (in_array($booking['status'], ['ditolak','dibatalkan','selesai'], true)) {
            flash('error', 'Pembayaran tidak tersedia untuk reservasi ini.');
            redirect_to('reservation', ['id' => $id]);
        }
        $repo = new PaymentRepository();
        $sums = $repo->sums((int) $id);
        $outstanding = max(0, (float) $booking['harga_paket_snapshot'] - $sums['approved'] - $sums['pending']);
        if ($outstanding <= 0.001) {
            flash('info', 'Tidak ada sisa tagihan yang perlu dibayar.');
            redirect_to('reservation', ['id' => $id]);
        }
        render('customer/pay', ['title' => 'Pembayaran Manual', 'booking' => $booking, 'sums' => $sums, 'outstanding' => $outstanding, 'minimumPayment' => ($sums['approved'] + $sums['pending'] <= 0 ? ceil((float) $booking['harga_paket_snapshot'] * 0.30) : 1)]);
    }

    public function paymentSubmit(): void
    {
        $user = require_role('pelanggan');
        require_post();
        $bookingId = filter_var($_POST['id_reservasi'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        try {
            (new PaymentService())->submit((int) $user['id_pengguna'], (int) $bookingId, $_POST, $_FILES['bukti'] ?? [], new UploadService());
            flash('success', 'Bukti pembayaran terkirim dan menunggu verifikasi admin.');
            redirect_to('reservation', ['id' => $bookingId]);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);
            flash('error', 'Pembayaran belum dapat dikirim. Periksa berkas, nominal, dan koneksi lalu coba kembali.');
        }
        redirect_to('pay', ['id' => $bookingId]);
    }

    public function gallery(): void
    {
        $user = require_role('pelanggan');
        render('customer/gallery', ['title' => 'Hasil Foto Saya', 'photos' => (new GalleryRepository())->forCustomer((int) $user['id_pengguna'])]);
    }
}

final class AdminController
{
    public function dashboard(): void
    {
        require_role('admin');
        render('admin/dashboard', ['title' => 'Dashboard Admin', 'dashboard' => (new ReportRepository())->dashboard()]);
    }

    public function services(): void
    {
        $admin = require_role('admin');
        $repo = new CatalogRepository();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post();
            $action = (string) ($_POST['action'] ?? 'save');
            try {
                if ($action === 'toggle') {
                    $id = filter_var($_POST['id_layanan'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                    $active = ($_POST['active'] ?? '0') === '1';
                    $repo->setServiceActive($id, $active);
                    audit_log((int) $admin['id_pengguna'], 'layanan', $id, $active ? 'aktifkan' : 'nonaktifkan');
                    flash('success', 'Status layanan diperbarui.');
                } else {
                    $name = trim((string) ($_POST['nama_layanan'] ?? ''));
                    $price = filter_var($_POST['harga_satuan'] ?? null, FILTER_VALIDATE_FLOAT);
                    if ($name === '' || strlen($name) > 100 || $price === false || $price < 0) throw new InvalidArgumentException('Nama layanan dan harga yang valid wajib diisi.');
                    $id = $repo->saveService(['id_layanan' => (int) ($_POST['id_layanan'] ?? 0), 'nama_layanan' => $name, 'deskripsi' => (string) ($_POST['deskripsi'] ?? ''), 'harga_satuan' => $price]);
                    audit_log((int) $admin['id_pengguna'], 'layanan', $id, 'simpan_layanan');
                    flash('success', 'Data layanan berhasil disimpan.');
                }
            } catch (InvalidArgumentException $e) { flash('error', $e->getMessage()); }
            catch (Throwable $e) { error_log((string) $e); flash('error', 'Layanan gagal disimpan. Pastikan nama layanan tidak duplikat.'); }
            redirect_to('admin-services');
        }
        $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
        $editing = null;
        if ($editId) {
            foreach ($repo->services(false) as $service) if ((int) $service['id_layanan'] === (int) $editId) $editing = $service;
        }
        render('admin/services', ['title' => 'Kelola Layanan', 'services' => $repo->services(false), 'editing' => $editing]);
    }

    public function packages(): void
    {
        $admin = require_role('admin');
        $repo = new CatalogRepository();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post();
            $action = (string) ($_POST['action'] ?? 'save');
            try {
                if ($action === 'toggle') {
                    $id = filter_var($_POST['id_paket'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                    $active = ($_POST['active'] ?? '0') === '1';
                    $repo->setPackageActive($id, $active);
                    audit_log((int) $admin['id_pengguna'], 'paket', $id, $active ? 'aktifkan' : 'nonaktifkan');
                    flash('success', 'Status paket diperbarui.');
                } else {
                    $name = trim((string) ($_POST['nama_paket'] ?? ''));
                    $desc = trim((string) ($_POST['deskripsi'] ?? ''));
                    $price = filter_var($_POST['harga_paket'] ?? null, FILTER_VALIDATE_FLOAT);
                    $duration = filter_var($_POST['durasi_menit'] ?? null, FILTER_VALIDATE_INT);
                    $image = (string) ($_POST['gambar'] ?? 'studio.svg');
                    if ($name === '' || strlen($name) > 100 || $desc === '' || $price === false || $price <= 0 || $duration === false || $duration < 30 || $duration > 360) throw new InvalidArgumentException('Nama, deskripsi, harga positif, dan durasi 30–360 menit wajib diisi.');
                    if (!in_array($image, ['studio.svg','keluarga.svg','wisuda.svg','produk.svg'], true)) $image = 'studio.svg';
                    $services = array_map('intval', array_filter((array) ($_POST['layanan'] ?? []), fn($v) => filter_var($v, FILTER_VALIDATE_INT) !== false));
                    $id = $repo->savePackage(['id_paket' => (int) ($_POST['id_paket'] ?? 0), 'nama_paket' => $name, 'deskripsi' => $desc, 'harga_paket' => $price, 'durasi_menit' => $duration, 'gambar' => $image], $services);
                    audit_log((int) $admin['id_pengguna'], 'paket', $id, 'simpan_paket');
                    flash('success', 'Paket berhasil disimpan.');
                }
            } catch (InvalidArgumentException $e) { flash('error', $e->getMessage()); }
            catch (Throwable $e) { error_log((string) $e); flash('error', 'Paket gagal disimpan. Periksa data dan relasi layanannya.'); }
            redirect_to('admin-packages');
        }
        $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
        $editing = $editId ? $repo->package((int) $editId, false) : null;
        render('admin/packages', [
            'title' => 'Kelola Paket Foto',
            'packages' => $repo->allPackages(false),
            'services' => $repo->services(true),
            'editing' => $editing,
            'selectedServiceIds' => $editing ? $repo->serviceIdsForPackage((int) $editing['id_paket']) : [],
        ]);
    }

    public function reservations(): void
    {
        require_role('admin');
        $status = trim((string) ($_GET['status'] ?? ''));
        render('admin/reservations', ['title' => 'Kelola Reservasi', 'reservations' => (new BookingRepository())->all($status ?: null), 'photographers' => (new UserRepository())->photographers(), 'filterStatus' => $status]);
    }

    public function reservationAction(): void
    {
        $admin = require_role('admin');
        require_post();
        $id = filter_var($_POST['id_reservasi'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $action = (string) ($_POST['action'] ?? '');
        $repo = new BookingRepository();
        try {
            if ($action === 'assign') {
                $photographerId = filter_var($_POST['id_fotografer'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                (new BookingService())->assign($id, (int) $photographerId, (int) $admin['id_pengguna']);
                flash('success', 'Fotografer berhasil ditugaskan.');
            } elseif (in_array($action, ['cancel','reject'], true)) {
                $booking = $repo->find($id);
                if (!$booking) throw new RuntimeException('Reservasi tidak ditemukan.');
                if (in_array($booking['status'], ['selesai','dibatalkan','ditolak'], true)) throw new InvalidArgumentException('Status reservasi tidak dapat diubah lagi.');
                $status = $action === 'cancel' ? 'dibatalkan' : 'ditolak';
                $repo->updateStatus($id, $status);
                audit_log((int) $admin['id_pengguna'], 'reservasi', $id, $action === 'cancel' ? 'batalkan_reservasi' : 'tolak_reservasi');
                flash('success', 'Status reservasi diperbarui.');
            } else {
                throw new InvalidArgumentException('Tindakan tidak dikenal.');
            }
        } catch (Throwable $e) {
            flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Perubahan reservasi gagal.');
            if (!($e instanceof InvalidArgumentException || $e instanceof RuntimeException)) error_log((string) $e);
        }
        redirect_to('admin-reservations');
    }

    public function payments(): void
    {
        require_role('admin');
        $status = trim((string) ($_GET['status'] ?? ''));
        render('admin/payments', ['title' => 'Verifikasi Pembayaran', 'payments' => (new PaymentRepository())->all($status ?: null), 'filterStatus' => $status]);
    }

    public function paymentAction(): void
    {
        $admin = require_role('admin');
        require_post();
        $id = filter_var($_POST['id_transaksi'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $decision = (string) ($_POST['keputusan'] ?? '');
        $note = trim((string) ($_POST['catatan_verifikasi'] ?? ''));
        if ($decision === 'ditolak' && $note === '') {
            flash('error', 'Catatan alasan wajib diisi saat menolak pembayaran.');
            redirect_to('admin-payments');
        }
        try {
            (new PaymentRepository())->verify((int) $id, (int) $admin['id_pengguna'], $decision, $note);
            flash('success', $decision === 'disetujui' ? 'Pembayaran disetujui; status reservasi diperbarui sesuai pembayaran terverifikasi.' : 'Pembayaran ditolak dan alasan tercatat.');
        } catch (Throwable $e) {
            flash('error', $e instanceof RuntimeException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Verifikasi gagal.');
            if (!($e instanceof RuntimeException || $e instanceof InvalidArgumentException)) error_log((string) $e);
        }
        redirect_to('admin-payments');
    }

    public function users(): void
    {
        $admin = require_role('admin');
        $repo = new UserRepository();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post();
            $action = (string) ($_POST['action'] ?? 'create');
            try {
                if ($action === 'toggle') {
                    $id = filter_var($_POST['id_pengguna'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                    if ($id === (int) $admin['id_pengguna']) throw new InvalidArgumentException('Akun admin yang sedang digunakan tidak dapat dinonaktifkan.');
                    $repo->setActive($id, ($_POST['active'] ?? '0') === '1');
                    audit_log((int) $admin['id_pengguna'], 'pengguna', $id, 'ubah_status_akun');
                    flash('success', 'Status akun diperbarui.');
                } elseif ($action === 'edit') {
                    $id = filter_var($_POST['id_pengguna'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                    $name = trim((string) ($_POST['nama'] ?? ''));
                    $phone = trim((string) ($_POST['no_telepon'] ?? ''));
                    if (!$id || $name === '' || strlen($name) > 100 || strlen($phone) > 20) throw new InvalidArgumentException('Nama atau nomor telepon tidak valid.');
                    $repo->updateProfile($id, $name, $phone);
                    audit_log((int) $admin['id_pengguna'], 'pengguna', $id, 'ubah_data_pengguna');
                    flash('success', 'Data pengguna diperbarui.');
                } else {
                    $name = trim((string) ($_POST['nama'] ?? ''));
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $username = trim((string) ($_POST['username'] ?? ''));
                    $password = (string) ($_POST['password'] ?? '');
                    $role = (string) ($_POST['peran'] ?? 'fotografer');
                    if (!in_array($role, ['pelanggan','fotografer'], true)) throw new InvalidArgumentException('Admin hanya dapat membuat akun pelanggan atau fotografer baru.');
                    $phone = trim((string) ($_POST['no_telepon'] ?? ''));
                    if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || strlen($password) < 8 || strlen($phone) > 20) throw new InvalidArgumentException('Lengkapi nama (maks. 100 karakter), email valid, username, kata sandi minimal 8 karakter, dan nomor telepon maksimal 20 karakter.');
                    $id = $repo->create(['nama' => $name, 'email' => $email, 'no_telepon' => $phone, 'username' => $username, 'password' => $password, 'peran' => $role]);
                    audit_log((int) $admin['id_pengguna'], 'pengguna', $id, 'buat_akun_' . $role);
                    flash('success', 'Akun pengguna berhasil dibuat.');
                }
            } catch (InvalidArgumentException $e) { flash('error', $e->getMessage()); }
            catch (Throwable $e) { error_log((string) $e); flash('error', 'Data pengguna gagal disimpan. Periksa apakah email/username sudah digunakan.'); }
            redirect_to('admin-users');
        }
        render('admin/users', ['title' => 'Kelola Pengguna', 'users' => $repo->all()]);
    }

    public function gallery(): void
    {
        require_role('admin');
        render('admin/gallery', ['title' => 'Kelola Galeri', 'photos' => (new GalleryRepository())->forAdmin()]);
    }

    public function galleryAction(): void
    {
        $admin = require_role('admin');
        require_post();
        $id = filter_var($_POST['id_foto'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $repo = new GalleryRepository();
        try {
            if (($_POST['action'] ?? '') === 'toggle') {
                $access = (string) ($_POST['tipe_akses'] ?? 'privat');
                $repo->setAccess($id, $access);
                audit_log((int) $admin['id_pengguna'], 'galeri', $id, 'ubah_akses', ['tipe_akses' => $access]);
                flash('success', 'Hak akses foto diperbarui.');
            } elseif (($_POST['action'] ?? '') === 'delete') {
                $path = $repo->delete($id);
                if ($path) {
                    $abs = (new UploadService())->absolutePath($path);
                    if ($abs) @unlink($abs);
                }
                audit_log((int) $admin['id_pengguna'], 'galeri', $id, 'hapus_foto');
                flash('success', 'Foto dihapus dari galeri.');
            }
        } catch (Throwable $e) { error_log((string) $e); flash('error', 'Perubahan galeri gagal dilakukan.'); }
        redirect_to('admin-gallery');
    }

    public function reports(): void
    {
        require_role('admin');
        $from = trim((string) ($_GET['dari'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['sampai'] ?? date('Y-m-d')));
        $fromObj = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $toObj = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$fromObj || $fromObj->format('Y-m-d') !== $from || !$toObj || $toObj->format('Y-m-d') !== $to || $from > $to) {
            flash('error', 'Rentang tanggal laporan tidak valid.');
            $from = date('Y-m-01'); $to = date('Y-m-d');
        }
        render('admin/reports', ['title' => 'Laporan', 'from' => $from, 'to' => $to, 'report' => (new ReportRepository())->report($from, $to)]);
    }
}

final class PhotographerController
{
    public function dashboard(): void
    {
        $user = require_role('fotografer');
        render('photographer/dashboard', ['title' => 'Jadwal Tugas', 'tasks' => (new BookingRepository())->photographerTasks((int) $user['id_pengguna'])]);
    }

    public function updateStatus(): void
    {
        $user = require_role('fotografer');
        require_post();
        $id = filter_var($_POST['id_reservasi'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $action = (string) ($_POST['action'] ?? '');
        $nextStatus = match ($action) { 'mulai' => 'sedang_foto', 'selesai' => 'selesai', default => '' };
        if ($nextStatus === '') { flash('error', 'Tindakan status tidak valid.'); redirect_to('photographer'); }
        try {
            $changed = (new BookingRepository())->setStatusForPhotographer((int) $id, (int) $user['id_pengguna'], $nextStatus);
            if (!$changed) throw new InvalidArgumentException('Tugas tidak ditemukan, bukan milik Anda, atau statusnya tidak sesuai.');
            audit_log((int) $user['id_pengguna'], 'reservasi', $id, 'fotografer_' . $action);
            flash('success', 'Status sesi pemotretan diperbarui.');
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect_to('photographer');
    }

    public function upload(): void
    {
        $user = require_role('fotografer');
        require_post();
        $id = filter_var($_POST['id_reservasi'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $bookingRepo = new BookingRepository();
        $galleryRepo = new GalleryRepository();
        $stored = null;
        try {
            $booking = $bookingRepo->find((int) $id);
            if (!$booking || (int) $booking['id_fotografer'] !== (int) $user['id_pengguna']) throw new InvalidArgumentException('Anda hanya dapat mengunggah hasil untuk tugas yang ditugaskan kepada Anda.');
            if (!in_array($booking['status'], ['dikonfirmasi','sedang_foto','selesai'], true)) throw new InvalidArgumentException('Hasil foto hanya dapat diunggah untuk sesi yang sudah dikonfirmasi.');
            $stored = (new UploadService())->storeImage($_FILES['foto'] ?? [], 'gallery');
            $galleryRepo->create((int) $id, (int) $user['id_pengguna'], $stored['path'], $stored['mime'], $stored['size'], $stored['original_name']);
            audit_log((int) $user['id_pengguna'], 'galeri', null, 'unggah_hasil_foto', ['id_reservasi' => (int) $id, 'nama_file' => $stored['original_name']]);
            flash('success', 'Hasil foto berhasil diunggah. Pelanggan terkait dapat mengaksesnya dari menu Hasil Foto Saya.');
        } catch (Throwable $e) {
            if ($stored) { $path = (new UploadService())->absolutePath($stored['path']); if ($path) @unlink($path); }
            flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unggahan foto gagal dilakukan.');
            if (!($e instanceof InvalidArgumentException)) error_log((string) $e);
        }
        redirect_to('photographer');
    }
}
