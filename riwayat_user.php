<?php
/* =========================================================
   PINJAMAN SAYA (anggota)
   - Buku yang sedang dipinjam: jatuh tempo, sisa hari,
     perkiraan denda, dan tombol perpanjang.
   - Riwayat: pengembalian, denda (lunas / belum lunas),
     dan pengajuan yang ditolak/dibatalkan.
   Memakai layout yang sama dengan halaman anggota lainnya.
========================================================= */

require_once __DIR__ . '/inc/security.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

include_once "koneksi.php";

$stmt = mysqli_prepare($koneksi, "SELECT id_ang, status FROM tbuser WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);
mysqli_stmt_execute($stmt);
$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($akun && strtolower($akun['status']) === 'admin') {
    header("Location: riwayat_kembali.php");
    exit;
}

$id_ang = (int) ($akun['id_ang'] ?? 0);

$aktif   = [];
$riwayat = [];

if ($id_ang > 0) {
    $q = mysqli_query(
        $koneksi,
        "SELECT p.*, b.judul, b.penulis
           FROM tbpinjam p
           JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.id_ang = $id_ang
          ORDER BY p.no_pinjam DESC"
    );
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $st = strtolower($r['status']);
        if ($st === 'pinjam' || $st === 'diajukan') {
            $aktif[] = $r;
        } else {
            $riwayat[] = $r;
        }
    }
}

$denda_belum = 0;
foreach ($riwayat as $r) {
    if (strtolower($r['status']) === 'kembali' && (int) $r['denda'] > 0 && (int) $r['denda_dibayar'] === 0) {
        $denda_belum += (int) $r['denda'];
    }
}

$page_title = "Pinjaman saya";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Pinjaman saya</h1>
            <p>Pantau jatuh tempo supaya tidak kena denda <?php echo format_rupiah(denda_per_hari()); ?> per hari.</p>
        </div>
        <a href="pinjam_user.php" class="btn btn-gold"><i class="fas fa-plus mr-1"></i> Ajukan pinjaman</a>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if ($id_ang <= 0) { ?>
        <div class="alert alert-danger">Akun ini belum terhubung dengan data anggota. Hubungi petugas perpustakaan.</div>
    <?php } else { ?>

    <?php if ($denda_belum > 0) { ?>
        <div class="alert alert-danger">
            <i class="fas fa-receipt mr-2"></i>
            Ada denda yang belum dibayar sebesar <strong><?php echo format_rupiah($denda_belum); ?></strong>. Lunasi di meja sirkulasi.
        </div>
    <?php } ?>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Sedang berjalan (<?php echo count($aktif); ?>)</h3></div>
        <div class="card-body p-0">
            <?php if (!$aktif) { ?>
                <p class="text-muted text-center py-4 mb-0">Tidak ada buku yang sedang dipinjam atau diajukan.</p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Buku</th><th>Dipinjam</th><th>Jatuh tempo</th><th>Status</th><th class="text-right">Keterangan</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($aktif as $r) {
                        $st    = strtolower($r['status']);
                        $tempo = $st === 'pinjam' ? jatuh_tempo_baris($r) : null;
                        $telat = $tempo ? hari_telat_dari_tempo($tempo) : 0;
                        $sisa  = $tempo ? (int) floor((strtotime($tempo) - strtotime(date('Y-m-d'))) / 86400) : null;
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo e($r['judul']); ?></strong><br>
                                <small class="text-muted"><?php echo e($r['penulis']); ?> &middot; <?php echo (int) $r['jml_pinjam']; ?> buku</small>
                            </td>
                            <td class="text-nowrap"><?php echo $r['tgl_pinjam'] ? date('d-m-Y', strtotime($r['tgl_pinjam'])) : '-'; ?></td>
                            <td class="text-nowrap">
                                <?php if ($tempo) { ?>
                                    <?php echo date('d-m-Y', strtotime($tempo)); ?><br>
                                    <?php if ($telat > 0) { ?>
                                        <small class="text-danger font-weight-bold">Terlambat <?php echo $telat; ?> hari, perkiraan denda <?php echo format_rupiah(denda_dari_tempo($tempo)); ?></small>
                                    <?php } elseif ($sisa === 0) { ?>
                                        <small class="text-warning font-weight-bold">Kembalikan hari ini</small>
                                    <?php } else { ?>
                                        <small class="text-muted"><?php echo $sisa; ?> hari lagi</small>
                                    <?php } ?>
                                    <?php if ((int) $r['perpanjang'] > 0) { ?><br><small class="text-muted">Diperpanjang <?php echo (int) $r['perpanjang']; ?>x</small><?php } ?>
                                <?php } else { ?>
                                    <small class="text-muted">Dihitung saat buku diserahkan</small>
                                <?php } ?>
                            </td>
                            <td><?php echo badge_status($r); ?></td>
                            <td class="text-right">
                                <?php if ($st === 'diajukan') { ?>
                                    <a href="pinjam_user.php" class="btn btn-sm btn-outline-secondary">Kelola pengajuan</a>
                                <?php } elseif (cek_boleh_perpanjang($r) === null) { ?>
                                    <small class="text-muted">Butuh waktu lebih? Minta perpanjangan ke petugas.</small>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Riwayat</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr><th>Buku</th><th>Dipinjam</th><th>Dikembalikan</th><th>Status</th><th>Denda</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$riwayat) { ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Riwayat akan muncul setelah buku pertama dikembalikan.</td></tr>
                    <?php } ?>
                    <?php foreach ($riwayat as $r) { ?>
                        <tr>
                            <td><?php echo e($r['judul']); ?><?php if (!empty($r['catatan'])) { ?><br><small class="text-muted"><?php echo e($r['catatan']); ?></small><?php } ?></td>
                            <td class="text-nowrap" data-order="<?php echo e($r['tgl_pinjam'] ?? $r['tgl_ajuan']); ?>"><?php echo $r['tgl_pinjam'] ? date('d-m-Y', strtotime($r['tgl_pinjam'])) : '-'; ?></td>
                            <td class="text-nowrap"><?php echo $r['tgl_kembali'] ? date('d-m-Y', strtotime($r['tgl_kembali'])) : '-'; ?></td>
                            <td><?php echo badge_status($r); ?></td>
                            <td class="text-nowrap" data-order="<?php echo (int) $r['denda']; ?>">
                                <?php if ((int) $r['denda'] > 0) { ?>
                                    <?php echo format_rupiah($r['denda']); ?><br>
                                    <?php echo (int) $r['denda_dibayar'] === 1
                                        ? '<span class="badge badge-success">Lunas</span>'
                                        : '<span class="badge badge-danger">Belum lunas</span>'; ?>
                                <?php } else { ?>
                                    <span class="text-muted">-</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php } ?>

</div>
</section>

<?php include "inc/footer.php"; ?>
