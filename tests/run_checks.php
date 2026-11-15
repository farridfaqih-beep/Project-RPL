<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/BookingRules.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[LULUS] {$name}\n";
    } else {
        $failed++;
        echo "[GAGAL] {$name}\n";
    }
}
function rejects(callable $fn): bool
{
    try { $fn(); return false; } catch (InvalidArgumentException) { return true; }
}

$tz = new DateTimeZone('Asia/Makassar');
$now = new DateTimeImmutable('2026-10-22 12:00:00', $tz);
$valid = BookingRules::validateSlot('2026-10-23', '10:00', 60, $now);
check($valid['time_sql'] === '10:00:00' && $valid['end']->format('H:i') === '11:00', 'Slot masa depan dinormalisasi dengan durasi yang benar');
check(rejects(fn() => BookingRules::validateSlot('2026-10-21', '10:00', 60, $now)), 'Tanggal lampau ditolak');
check(rejects(fn() => BookingRules::validateSlot('2026-10-23', '08:30', 60, $now)), 'Jam sebelum 09.00 ditolak');
check(rejects(fn() => BookingRules::validateSlot('2026-10-23', '17:30', 90, $now)), 'Sesi yang selesai setelah 18.00 ditolak');
check(rejects(fn() => BookingRules::validateSlot('2026-10-23', 'abc', 60, $now)), 'Format jam tidak valid ditolak');
check(rejects(fn() => BookingRules::validateSlot('2026-10-22', '11:00', 60, $now)), 'Jam hari ini yang sudah lewat ditolak');
check(BookingRules::overlaps(new DateTimeImmutable('2026-10-23 10:00', $tz), 60, new DateTimeImmutable('2026-10-23 10:30', $tz), 30), 'Rentang sesi bertumpang tindih terdeteksi');
check(!BookingRules::overlaps(new DateTimeImmutable('2026-10-23 10:00', $tz), 60, new DateTimeImmutable('2026-10-23 11:00', $tz), 60), 'Sesi berurutan tanpa tumpang tindih diperbolehkan');
check(BookingRules::minimumFirstPayment(850000) === 255000, 'DP awal minimal 30 persen dihitung benar');
check(BookingRules::paymentStatus(500000, 0, 500000) === 'lunas', 'Status lunas dihitung dari total pembayaran disetujui');
check(BookingRules::paymentStatus(100000, 0, 500000) === 'dp', 'Pembayaran sebagian terverifikasi dilabeli DP');
check(BookingRules::paymentStatus(0, 200000, 500000) === 'menunggu', 'Pembayaran belum terverifikasi tidak dihitung sebagai pemasukan');

$root = dirname(__DIR__);
$mustExist = [
    'public/index.php', 'app/bootstrap.php', 'app/Repositories.php', 'app/Services.php',
    'app/Controllers.php', 'database/schema.sql', 'README.md', 'public/assets/css/app.css',
];
foreach ($mustExist as $file) check(is_file($root . '/' . $file), 'File utama tersedia: ' . $file);
$sql = file_get_contents($root . '/database/schema.sql') ?: '';
check(str_contains($sql, 'PRIMARY KEY (id_paket, id_layanan)'), 'Relasi paket-layanan memakai kunci komposit');
check(str_contains($sql, 'status_verifikasi ENUM'), 'Status pembayaran dipisahkan dari jenis pembayaran');
check(str_contains($sql, 'hold_expires_at'), 'Skema mendukung masa berlaku slot reservasi');
check(str_contains($sql, 'FOREIGN KEY (id_pelanggan)'), 'Skema menggunakan foreign key pelanggan');
$controllers = file_get_contents($root . '/app/Controllers.php') ?: '';
check(str_contains($controllers, 'require_role(\'pelanggan\')') && str_contains($controllers, 'require_role(\'admin\')') && str_contains($controllers, 'require_role(\'fotografer\')'), 'Kontrol peran tersedia pada sisi server');
check(str_contains($controllers, 'storeImage($_FILES') && str_contains($controllers, 'absolutePath'), 'Upload dan unduhan melewati layanan penyimpanan privat');

// Check the pre-hashed local demo accounts embedded in the seed file.
$seedPasswords = [
    'admin@fotostudio.test' => 'Admin123!',
    'foto@fotostudio.test' => 'Foto123!',
    'pelanggan@fotostudio.test' => 'Pelanggan123!',
];
foreach ($seedPasswords as $email => $password) {
    $pattern = "/\\('(?:[^']*)',\\s*'" . preg_quote($email, '/') . "',(?:(?!\\n\\().)*?'(\\$2y\\$12\\$[^']+)'/s";
    // Parse the explicit INSERT row so a typo in a copied hash is caught before import.
    $row = null;
    foreach (preg_split('/\R/', $sql) as $line) {
        if (str_contains($line, "'" . $email . "'")) { $row = $line; break; }
    }
    preg_match("/'(\\$2y\\$12\\$[^']+)'/", (string) $row, $matches);
    check(isset($matches[1]) && password_verify($password, $matches[1]), 'Password demo ter-hash valid: ' . $email);
}

echo "\nHasil: {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
