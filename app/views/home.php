<section class="hero">
    <div class="container"><div class="hero-inner">
        <span class="eyebrow">Setiap cerita punya bingkainya</span>
        <h1>Abadikan momen.<br><em>Kelola dengan mudah.</em></h1>
        <p>Lihat paket foto, pilih jadwal yang tersedia, pantau pembayaran, dan akses hasil pemotretan dalam satu ruang digital yang terorganisasi.</p>
        <div class="hero-actions"><a class="btn btn-copper btn-lg" href="#paket">Jelajahi Paket <span aria-hidden="true">↗</span></a><a class="btn-ghost" href="#cara-kerja">Cara Kerja Studio</a></div>
        <div class="hero-note"><span><b>01</b> Reservasi online</span><span><b>02</b> Pembayaran terpantau</span><span><b>03</b> Hasil foto terjaga</span></div>
    </div></div>
</section>
<section class="process-band" id="cara-kerja"><div class="container"><div class="row g-3">
    <div class="col-md-4"><div class="process-item"><span class="process-number">01</span><div><strong>Pilih sesi favoritmu</strong><small>Katalog harga dan fasilitas yang jelas</small></div></div></div>
    <div class="col-md-4"><div class="process-item"><span class="process-number">02</span><div><strong>Pesan tanpa bentrok</strong><small>Slot jadwal diperiksa oleh sistem</small></div></div></div>
    <div class="col-md-4"><div class="process-item"><span class="process-number">03</span><div><strong>Terima hasil fotomu</strong><small>Akses privat dari akun pelanggan</small></div></div></div>
</div></div></section>
<section class="section-pad" id="paket"><div class="container">
    <div class="section-head"><div><span class="section-kicker">Katalog Lensa Studio</span><h2>Paket untuk setiap cerita</h2></div><p>Bandingkan fasilitas, durasi, dan harga sebelum memilih sesi. Jadwal akan diperiksa ulang saat reservasi diajukan.</p></div>
    <div class="row g-4">
        <?php foreach ($packages as $package): ?>
            <div class="col-md-6 col-xl-3"><article class="package-card">
                <div class="package-image"><img src="<?= e(asset('images/' . $package['gambar'])) ?>" alt="Ilustrasi <?= e($package['nama_paket']) ?>"><span class="package-duration"><?= (int) $package['durasi_menit'] ?> menit</span></div>
                <div class="package-body"><h3><?= e($package['nama_paket']) ?></h3><p><?= e($package['deskripsi']) ?></p>
                    <?php if (!empty($package['layanan_list'])): ?><div class="service-tags"><?php foreach (array_slice(explode(', ', $package['layanan_list']), 0, 3) as $service): ?><span class="service-tag"><?= e($service) ?></span><?php endforeach; ?></div><?php endif; ?>
                    <div class="package-price"><div><small>Mulai dari</small><strong><?= money($package['harga_paket']) ?></strong></div><a class="arrow text-decoration-none" href="<?= e(url('package', ['id' => $package['id_paket']])) ?>" aria-label="Detail <?= e($package['nama_paket']) ?>">↗</a></div>
                </div>
            </article></div>
        <?php endforeach; ?>
        <?php if (!$packages): ?><div class="col-12"><div class="empty-state"><div class="empty-symbol">✦</div><h3>Perbarui katalog segera</h3><p>Belum ada paket yang ditampilkan. Admin dapat menambahkan paket dari dashboard.</p></div></div><?php endif; ?>
    </div>
</div></section>
<section class="section-pad pt-0" id="portofolio"><div class="container">
    <div class="section-head"><div><span class="section-kicker">Cerita dalam bingkai</span><h2>Portofolio studio</h2></div><p>Galeri publik hanya menampilkan foto yang aksesnya telah ditandai publik oleh admin. Hasil foto pelanggan tetap privat secara default.</p></div>
    <div class="row g-3">
        <?php foreach ($photos as $photo): ?><div class="col-md-4"><a class="portfolio-card d-block text-decoration-none" href="<?= e(url('download', ['id' => $photo['id_foto'], 'inline' => 1])) ?>"><img src="<?= e(url('download', ['id' => $photo['id_foto'], 'inline' => 1])) ?>" alt="Portofolio <?= e($photo['nama_paket']) ?>"><div class="portfolio-caption"><strong><?= e($photo['nama_paket']) ?></strong><small>Portofolio Lensa Studio · Lihat foto ↗</small></div></a></div><?php endforeach; ?>
        <?php if (!$photos): ?><div class="col-12"><div class="empty-state"><div class="empty-symbol">▧</div><h3>Portofolio akan hadir di sini</h3><p>Admin dapat menerbitkan foto pilihan dari menu Kelola Galeri. Hasil foto baru bersifat privat sampai diizinkan menjadi publik.</p></div></div><?php endif; ?>
    </div>
</div></section>
<section class="pb-5"><div class="container"><div class="surface surface-pad d-md-flex align-items-center justify-content-between gap-4"><div><span class="section-kicker">Mulai dari sini</span><h2 class="mt-2 mb-2" style="font:500 2rem Georgia,serif">Momen berikutnya layak direncanakan.</h2><p class="muted mb-md-0">Buat akun untuk melakukan reservasi dan memantau prosesnya.</p></div><div class="mt-3 mt-md-0"><?php if ($currentUser && $currentUser['peran'] === 'pelanggan'): ?><a class="btn btn-copper" href="<?= e(url('my-reservations')) ?>">Buka Reservasi Saya ↗</a><?php else: ?><a class="btn btn-copper" href="<?= e(url('register')) ?>">Buat Akun Pelanggan ↗</a><?php endif; ?></div></div></div></section>
