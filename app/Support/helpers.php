<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO
{
    return Database::connection();
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}

function url(string $route = 'home', array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['r' => $route], $params));
}

function redirect_to(string $route = 'home', array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Sesi berakhir</title><body style="font-family:system-ui;padding:3rem"><h1>Sesi formulir berakhir</h1><p>Muat ulang halaman lalu coba lagi.</p><a href="index.php">Kembali ke beranda</a></body></html>';
        exit;
    }
}

function current_user(): ?array
{
    static $resolved = false;
    static $user = null;
    if ($resolved) return $user;
    $resolved = true;
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) return null;
    $stmt = db()->prepare('SELECT id_pengguna, nama, email, no_telepon, username, peran FROM pengguna WHERE id_pengguna = :id AND aktif = 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch() ?: null;
    if (!$user) unset($_SESSION['user_id']);
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('info', 'Silakan masuk untuk melanjutkan.');
        redirect_to('login');
    }
    return $user;
}

function require_role(string|array $roles): array
{
    $user = require_login();
    $roles = (array) $roles;
    if (!in_array($user['peran'], $roles, true)) {
        http_response_code(403);
        render('errors/403', ['title' => 'Akses ditolak']);
        exit;
    }
    return $user;
}

function render(string $view, array $data = []): void
{
    $viewPath = dirname(__DIR__) . '/views/' . $view . '.php';
    if (!is_file($viewPath)) {
        throw new RuntimeException('Template tidak ditemukan: ' . $view);
    }
    extract($data, EXTR_SKIP);
    $pageTitle = $data['title'] ?? ($GLOBALS['config']['app_name'] ?? 'Lensa Studio');
    $currentUser = current_user();
    require dirname(__DIR__) . '/views/layout.php';
}

function money(int|float|string|null $amount): string
{
    return 'Rp ' . number_format((float) ($amount ?? 0), 0, ',', '.');
}

function date_id(?string $date, bool $withTime = false): string
{
    if (!$date) return '-';
    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return e($date);
    }
    return $dt->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
}

function status_label(string $status): string
{
    return match ($status) {
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'dikonfirmasi' => 'Dikonfirmasi',
        'sedang_foto' => 'Sedang Pemotretan',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
        'menunggu' => 'Menunggu Verifikasi',
        'disetujui' => 'Disetujui',
        'ditolak_bayar' => 'Ditolak',
        'belum_bayar' => 'Belum Dibayar',
        'dp' => 'DP Terbayar',
        'lunas' => 'Lunas',
        default => ucwords(str_replace('_', ' ', $status)),
    };
}

function status_class(string $status): string
{
    return match ($status) {
        'selesai', 'disetujui', 'lunas' => 'success',
        'dikonfirmasi', 'dp' => 'primary',
        'menunggu_pembayaran', 'menunggu_verifikasi', 'menunggu', 'belum_bayar', 'sedang_foto' => 'warning',
        'ditolak', 'ditolak_bayar', 'dibatalkan' => 'danger',
        default => 'secondary',
    };
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function audit_log(?int $actorId, string $entity, int|string|null $entityId, string $action, array $details = []): void
{
    $stmt = db()->prepare('INSERT INTO log_aktivitas (id_pengguna, entitas, id_entitas, aksi, detail_json, created_at) VALUES (:actor, :entity, :entity_id, :action, :details, NOW())');
    $stmt->execute([
        'actor' => $actorId,
        'entity' => $entity,
        'entity_id' => $entityId === null ? null : (string) $entityId,
        'action' => $action,
        'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Metode permintaan tidak diizinkan.');
    }
    verify_csrf();
}
