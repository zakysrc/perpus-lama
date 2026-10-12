<?php
/* =========================================================
   PUSAT LAPORAN (admin)
   Filter & query ada di inc/laporan_data.php, dipakai juga
   oleh download_laporan.php sehingga layar = cetak = CSV.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";
require_once "inc/laporan_data.php";

$jenis = strtolower(trim((string) ($_GET['jenis'] ?? 'ringkasan')));
if (!isset(LAPORAN_JENIS[$jenis])) {
    $jenis = 'ringkasan';
}

$f = laporan_filter($_GET);
[$judul_jenis, $ikon_jenis, $kolom_tanggal] = LAPORAN_JENIS[$jenis];
$pakai_filter = $jenis !== 'terlambat';

if ($jenis === 'ringkasan') {
    $ringkasan = laporan_ringkasan($koneksi, $f);
    $sekarang  = laporan_kondisi_sekarang($koneksi);
} else {
    $data = laporan_data($koneksi, $jenis, $f);
    $uang = laporan_kolom_uang($jenis);
}

$daftar_tahun = laporan_daftar_tahun($koneksi, $f['tahun']);

$page_title = "Laporan";
include "inc/header.php";
?>

<style>
    /* Kartu angka laporan: navy, teks putih, garis bawah emas. Hanya berlaku di halaman laporan. */
    .laporan-page .pz-stat {
        background: var(--pz-navy-800) !important;
        border: 0 !important;
        border-bottom: 3px solid var(--pz-gold-500) !important;
        color: #fff !important;
    }
    .laporan-page .pz-stat .pz-stat-value {
        color: #fff !important;
    }
    .laporan-page .pz-stat .pz-stat-label {
        color: #fff !important;
    }
    .laporan-page .pz-stat .text-gold {
        color: var(--pz-gold-500) !important;
    }
    .lap-jenis { display: flex; flex-wrap: wrap; gap: 8px; }
    .lap-jenis a { padding: 7px 14px; border-radius: 999px; border: 1px solid var(--pz-line); background: #fff; color: var(--pz-ink); font-weight: 600; font-size: 14px; }
    .lap-jenis a:hover { border-color: var(--pz-gold-500); text-decoration: none; }
    .lap-jenis a.aktif { background: var(--pz-navy-800); border-color: var(--pz-navy-800); color: #fff; }
    .lap-periode { display: inline-block; background: var(--pz-gold-100); color: var(--pz-gold-700); font-weight: 700; padding: 4px 12px; border-radius: 999px; }
    .lap-cetak-kop { display: none; }
    @media print {
        .lap-jenis, .lap-filter, .lap-aksi, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info { display: none !important; }
        .lap-cetak-kop { display: block; margin-bottom: 12px; }
        .card { box-shadow: none !important; border: 0 !important; }
        .pz-stat { border: 1px solid #999 !important; }
    }
</style>

<section class="content laporan-page">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Laporan <?php echo $jenis === 'ringkasan' ? 'perpustakaan' : e(strtolower($judul_jenis)); ?></h1>
            <p>Pilih jenis laporan dan periode. Hasil yang sama bisa dicetak atau diunduh sebagai CSV (dibuka di Excel).</p>
        </div>
        <div class="lap-aksi">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
            <?php if ($jenis !== 'ringkasan') { ?>
                <a class="btn btn-primary" href="download_laporan.php?<?php echo e(laporan_query_string($jenis, $f)); ?>"><i class="fas fa-file-csv mr-1"></i> Unduh CSV</a>
            <?php } ?>
        </div>
    </div>

    <div class="lap-cetak-kop">
        <h2 class="mb-0"><?php echo e(nama_perpustakaan()); ?></h2>
        <div>Laporan <?php echo e($judul_jenis); ?> &middot; Periode: <?php echo e($pakai_filter ? $f['label'] : 'Per hari ini'); ?> &middot; Dicetak <?php echo tanggal_indo(date('Y-m-d')) . ' ' . date('H:i'); ?> WIB</div>
    </div>

    <nav class="lap-jenis mb-3" aria-label="Jenis laporan">
        <?php foreach (LAPORAN_JENIS as $k => [$lbl, $ik]) { ?>
            <a href="laporan.php?<?php echo e(laporan_query_string($k, $f)); ?>" class="<?php echo $k === $jenis ? 'aktif' : ''; ?>">
                <i class="fas <?php echo $ik; ?> mr-1"></i><?php echo e($lbl); ?>
            </a>
        <?php } ?>
    </nav>

    <?php if ($pakai_filter) { ?>
    <form method="get" class="card card-body py-3 lap-filter" id="formFilter" autocomplete="off">
        <input type="hidden" name="jenis" value="<?php echo e($jenis); ?>">
        <div class="form-row align-items-end">
            <div class="col-md-3 mb-2">
                <label for="mode" class="small">Periode</label>
                <select id="mode" name="mode" class="custom-select">
                    <?php foreach (LAPORAN_MODE as $k => $lbl) { ?>
                        <option value="<?php echo $k; ?>" <?php echo (string) $k === $f['mode'] ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 mb-2" data-untuk="bulan">
                <label for="bulan" class="small">Bulan</label>
                <select id="bulan" name="bulan" class="custom-select">
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <option value="<?php echo $i; ?>" <?php echo $i === $f['bulan'] ? 'selected' : ''; ?>><?php echo nama_bulan($i); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 mb-2" data-untuk="bulan tahun">
                <label for="tahun" class="small">Tahun</label>
                <select id="tahun" name="tahun" class="custom-select">
                    <?php foreach ($daftar_tahun as $y) { ?>
                        <option value="<?php echo $y; ?>" <?php echo $y === $f['tahun'] ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 mb-2" data-untuk="rentang">
                <label for="mulai" class="small">Dari tanggal</label>
                <input type="date" id="mulai" name="mulai" class="form-control" value="<?php echo e($f['mulai']); ?>">
            </div>
            <div class="col-md-2 mb-2" data-untuk="rentang">
                <label for="akhir" class="small">Sampai tanggal</label>
                <input type="date" id="akhir" name="akhir" class="form-control" value="<?php echo e($f['akhir']); ?>">
            </div>
            <div class="col-md-auto mb-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i> Terapkan</button>
            </div>
        </div>
        <div class="small text-muted">
            Periode aktif: <span class="lap-periode"><?php echo e($f['label']); ?></span>
            <?php if ($kolom_tanggal) { ?> &middot; disaring berdasarkan <?php echo e(strtolower($kolom_tanggal)); ?><?php } ?>
        </div>
    </form>
    <?php } ?>

    <?php if ($jenis === 'ringkasan') { ?>

        <h2 class="h5 mb-2">Periode: <?php echo e($f['label']); ?></h2>
        <div class="row">
            <?php foreach ($ringkasan as [$lbl, $ik, $nilai, $uang_kartu]) { ?>
                <div class="col-6 col-lg-3 mb-3">
                    <div class="pz-stat">
                        <div class="d-flex justify-content-between"><div class="pz-stat-value"><?php echo $uang_kartu ? format_rupiah($nilai) : number_format($nilai, 0, ',', '.'); ?></div><i class="fas <?php echo $ik; ?> text-gold"></i></div>
                        <div class="pz-stat-label"><?php echo e($lbl); ?></div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <h2 class="h5 mb-2 mt-2">Kondisi saat ini <small class="text-muted">(tidak dipengaruhi filter)</small></h2>
        <div class="row">
            <?php foreach ($sekarang as [$lbl, $nilai]) { ?>
                <div class="col-6 col-lg-3 mb-3">
                    <div class="pz-stat is-accent">
                        <div class="pz-stat-value"><?php echo number_format($nilai, 0, ',', '.'); ?></div>
                        <div class="pz-stat-label"><?php echo e($lbl); ?></div>
                    </div>
                </div>
            <?php } ?>
        </div>

    <?php } else { ?>

        <?php if ($data['catatan']) { ?><div class="alert alert-info"><?php echo e($data['catatan']); ?></div><?php } ?>

        <div class="row">
            <?php foreach ($data['total'] as $lbl => $nilai) { ?>
                <div class="col-6 col-lg-3 mb-3">
                    <div class="pz-stat"><div class="pz-stat-value"><?php echo is_int($nilai) ? number_format($nilai, 0, ',', '.') : e($nilai); ?></div><div class="pz-stat-label"><?php echo e($lbl); ?></div></div>
                </div>
            <?php } ?>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (!$data['rows']) { ?>
                    <p class="text-center text-muted py-4 mb-0" id="lapKosong">Tidak ada data pada periode <strong><?php echo e($f['label']); ?></strong>.</p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm datatable">
                        <thead><tr><?php foreach ($data['headers'] as $hd) { ?><th><?php echo e($hd); ?></th><?php } ?></tr></thead>
                        <tbody>
                        <?php foreach ($data['rows'] as $row) { ?>
                            <tr>
                            <?php foreach ($row as $i => $sel) { $u = in_array($i, $uang, true); ?>
                                <td<?php echo $u ? ' data-order="' . (int) $sel . '"' : ''; ?>><?php echo $u ? format_rupiah($sel) : e($sel); ?></td>
                            <?php } ?>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>

    <?php } ?>

</div>
</section>

<script>
/* Tampilkan hanya isian yang dipakai mode terpilih, dan terapkan
   otomatis untuk mode yang tidak butuh isian tambahan. Isian yang
   disembunyikan dinonaktifkan supaya tidak ikut ke URL. */
(function () {
    var form = document.getElementById('formFilter');
    if (!form) { return; }
    var mode = document.getElementById('mode');

    function atur() {
        form.querySelectorAll('[data-untuk]').forEach(function (el) {
            var tampil = el.getAttribute('data-untuk').split(' ').indexOf(mode.value) !== -1;
            el.style.display = tampil ? '' : 'none';
            el.querySelectorAll('select, input').forEach(function (i) { i.disabled = !tampil; });
        });
    }

    mode.addEventListener('change', function () {
        atur();
        if (['semua', 'hari', '7hari', '30hari'].indexOf(mode.value) !== -1) { form.submit(); }
    });
    form.querySelectorAll('#bulan, #tahun').forEach(function (s) {
        s.addEventListener('change', function () { form.submit(); });
    });

    /* Tombol Kembali/Maju browser memulihkan isian lama; paksa
       tampilan kembali sesuai nilai dari server */
    window.addEventListener('pageshow', function (e) { if (e.persisted) { location.reload(); } });
    atur();
})();
</script>

<?php include "inc/footer.php"; ?>
