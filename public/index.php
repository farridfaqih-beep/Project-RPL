<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$route = (string) ($_GET['r'] ?? 'home');
$home = new HomeController();
$auth = new AuthController();
$customer = new CustomerController();
$admin = new AdminController();
$photographer = new PhotographerController();
$routes = [
    'home' => [$home, 'home'],
    'package' => [$home, 'package'],
    'availability' => [$home, 'availability'],
    'login' => [$auth, 'login'],
    'register' => [$auth, 'register'],
    'logout' => [$auth, 'logout'],
    'profile' => [$auth, 'profile'],
    'download' => [$auth, 'download'],
    'proof' => [$auth, 'proof'],
    'book' => [$customer, 'book'],
    'booking-store' => [$customer, 'bookingStore'],
    'my-reservations' => [$customer, 'reservations'],
    'reservation' => [$customer, 'reservation'],
    'pay' => [$customer, 'pay'],
    'payment-submit' => [$customer, 'paymentSubmit'],
    'my-gallery' => [$customer, 'gallery'],
    'admin' => [$admin, 'dashboard'],
    'admin-services' => [$admin, 'services'],
    'admin-packages' => [$admin, 'packages'],
    'admin-reservations' => [$admin, 'reservations'],
    'admin-reservation-action' => [$admin, 'reservationAction'],
    'admin-payments' => [$admin, 'payments'],
    'admin-payment-action' => [$admin, 'paymentAction'],
    'admin-users' => [$admin, 'users'],
    'admin-gallery' => [$admin, 'gallery'],
    'admin-gallery-action' => [$admin, 'galleryAction'],
    'admin-reports' => [$admin, 'reports'],
    'photographer' => [$photographer, 'dashboard'],
    'photographer-status' => [$photographer, 'updateStatus'],
    'photographer-upload' => [$photographer, 'upload'],
];

if (!isset($routes[$route])) {
    http_response_code(404);
    try {
        render('errors/404', ['title' => 'Halaman tidak ditemukan']);
    } catch (Throwable) {
        echo 'Halaman tidak ditemukan.';
    }
    exit;
}

try {
    if ($route === 'logout' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Keluar dari akun harus menggunakan formulir POST.');
    }
    call_user_func($routes[$route]);
} catch (PDOException $e) {
    error_log('[Lensa Studio DB] ' . $e->getMessage());
    http_response_code(503);
    $detail = $GLOBALS['config']['debug'] ? '<pre>' . e($e->getMessage()) . '</pre>' : '';
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Basis data belum siap</title><body style="font-family:system-ui;background:#f7f3ee;color:#29231f;padding:4rem 1.5rem"><main style="max-width:680px;margin:auto;background:white;border:1px solid #e8ded3;border-radius:18px;padding:2rem"><p style="color:#965638;font-weight:700;letter-spacing:.12em">LENSA STUDIO</p><h1>Basis data belum terhubung</h1><p>Aplikasi belum dapat membaca basis data. Buat basis data <code>fotostudio</code>, impor <code>database/schema.sql</code>, lalu sesuaikan kredensial di file <code>.env</code>.</p><p>Pastikan MySQL/MariaDB berjalan dan PHP memiliki ekstensi <code>pdo_mysql</code>.</p>' . $detail . '</main></body></html>';
} catch (Throwable $e) {
    error_log('[Lensa Studio] ' . (string) $e);
    http_response_code(500);
    if ($GLOBALS['config']['debug']) {
        echo '<pre style="white-space:pre-wrap;font-family:monospace;padding:2rem">' . e((string) $e) . '</pre>';
    } else {
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Terjadi kesalahan</title><body style="font-family:system-ui;background:#f7f3ee;color:#29231f;padding:4rem 1.5rem"><main style="max-width:650px;margin:auto;background:white;border:1px solid #e8ded3;border-radius:18px;padding:2rem"><h1>Terjadi kesalahan</h1><p>Permintaan belum dapat diproses. Coba kembali. Jika masalah berlanjut, periksa log aplikasi.</p><a href="index.php">Kembali ke beranda</a></main></body></html>';
    }
}
