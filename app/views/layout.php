<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#29231f">
    <title><?= e($pageTitle) ?> · <?= e($GLOBALS['config']['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<header class="site-header">
    <nav class="navbar navbar-expand-lg container py-3">
        <a class="brand-mark" href="<?= e(url('home')) ?>"><span class="brand-icon">L</span><span><strong>Lensa</strong><small>STUDIO MANAGEMENT</small></span></a>
        <button class="navbar-toggler nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Buka navigasi"><span></span><span></span><span></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <a class="nav-link" href="<?= e(url('home')) ?>#paket">Katalog Paket</a>
                <a class="nav-link" href="<?= e(url('home')) ?>#portofolio">Portofolio</a>
                <?php if ($currentUser && $currentUser['peran'] === 'pelanggan'): ?>
                    <a class="nav-link" href="<?= e(url('my-reservations')) ?>">Reservasi Saya</a>
                    <a class="nav-link" href="<?= e(url('my-gallery')) ?>">Hasil Foto</a>
                <?php elseif ($currentUser && $currentUser['peran'] === 'admin'): ?>
                    <a class="nav-link" href="<?= e(url('admin')) ?>">Dashboard</a>
                    <a class="nav-link" href="<?= e(url('admin-reservations')) ?>">Reservasi</a>
                    <a class="nav-link" href="<?= e(url('admin-payments')) ?>">Pembayaran</a>
                <?php elseif ($currentUser && $currentUser['peran'] === 'fotografer'): ?>
                    <a class="nav-link" href="<?= e(url('photographer')) ?>">Jadwal Tugas</a>
                <?php endif; ?>
                <?php if ($currentUser): ?>
                    <a class="nav-link nav-user" href="<?= e(url('profile')) ?>"><span class="avatar-mini"><?= e(strtoupper(substr($currentUser['nama'], 0, 1))) ?></span><?= e($currentUser['nama']) ?></a>
                    <form method="post" action="<?= e(url('logout')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm ms-lg-2" type="submit">Keluar</button></form>
                <?php else: ?>
                    <a class="nav-link" href="<?= e(url('login')) ?>">Masuk</a>
                    <a class="btn btn-copper btn-sm ms-lg-2" href="<?= e(url('register')) ?>">Daftar Sekarang <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<main>
    <?php
$notifications = [];

foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $key => $type) {
    if ($message = flash($key)) {
        $notifications[] = [
            'type' => $type,
            'message' => $message
        ];
    }
}
?>

<?php if (!empty($notifications)): ?>
    <div class="container flash-container">
        <?php foreach ($notifications as $notification): ?>
            <div class="alert alert-<?= e($notification['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($notification['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require $viewPath; ?>
<footer class="site-footer mt-5">
    <div class="container footer-inner">
        <div><a class="brand-mark footer-brand" href="<?= e(url('home')) ?>"><span class="brand-icon">L</span><span><strong>Lensa</strong><small>STUDIO MANAGEMENT</small></span></a><p class="footer-desc">Ruang untuk momen yang ingin selalu dikenang.</p></div>
        <div class="footer-right"><span>Sistem Informasi Manajemen Foto Studio Berbasis Web</span><span>© <?= date('Y') ?> · Project Rekayasa Perangkat Lunak</span></div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
