<?php
/* =========================================================
   BERANDA ANGGOTA
   Ringkasan: yang sedang dipinjam, jatuh tempo terdekat,
   pengajuan yang menunggu, dan denda yang belum dibayar.
========================================================= */

require_once __DIR__ . '/inc/security.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

/* Admin punya dashboard sendiri (sebelumnya salah diarahkan ke index.php) */
if (isset($_SESSION['status']) && strtolower($_SESSION['status']) === 'admin') {
    header('Location: beranda_admin.php');
    exit;
}

include_once 'koneksi.php';

$stmt = mysqli_prepare($koneksi, "SELECT id_ang FROM tbuser WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);
mysqli_stmt_execute($stmt);
$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$id_ang  = (int) ($akun['id_ang'] ?? 0);
$anggota = null;

if ($id_ang > 0) {
    $q = mysqli_query($koneksi, "SELECT * FROM tbanggota WHERE id_ang = $id_ang LIMIT 1");
    $anggota = $q ? mysqli_fetch_assoc($q) : null;
}

$aktif = [];
$jml_ajuan = 0;
$jml_kembali = 0;
$denda_belum = 0;

if ($id_ang > 0) {
    $q = mysqli_query(
        $koneksi,
        "SELECT p.*, b.judul
           FROM tbpinjam p JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.id_ang = $id_ang AND p.status = 'pinjam'
          ORDER BY " . sql_jatuh_tempo('p') . " ASC"
    );
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $aktif[] = $r;
    }

    $q = mysqli_query(
        $koneksi,
        "SELECT
            SUM(status = 'diajukan') AS ajuan,
            SUM(status = 'kembali') AS kembali,
            COALESCE(SUM(CASE WHEN status = 'kembali' AND denda_dibayar = 0 THEN denda ELSE 0 END), 0) AS denda_belum
           FROM tbpinjam WHERE id_ang = $id_ang"
    );
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $jml_ajuan   = (int) $r['ajuan'];
        $jml_kembali = (int) $r['kembali'];
        $denda_belum = (int) $r['denda_belum'];
    }
}

$terdekat = $aktif[0] ?? null;
$nama = $anggota['nama'] ?? $_SESSION['username'];
$masa_habis = $anggota && !empty($anggota['masa_berlaku']) && $anggota['masa_berlaku'] < date('Y-m-d');

$page_title = "Beranda";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Halo, <?php echo e($nama); ?></h1>
            <p><?php echo $terdekat
                ? 'Buku berikutnya yang harus dikembalikan: ' . e($terdekat['judul']) . ', paling lambat ' . date('d-m-Y', strtotime(jatuh_tempo_baris($terdekat))) . '.'
                : 'Tidak ada buku yang sedang kamu pinjam.'; ?></p>
        </div>
        <a href="buku_user.php" class="btn btn-gold"><i class="fas fa-search mr-1"></i> Cari buku</a>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if (!$anggota) { ?>
        <div class="alert alert-warning">Akun ini belum terhubung dengan data anggota. Hubungi petugas perpustakaan.</div>
    <?php } elseif ($masa_habis) { ?>
        <div class="alert alert-danger">Masa berlaku keanggotaan habis pada <?php echo date('d-m-Y', strtotime($anggota['masa_berlaku'])); ?>. Perpanjang di meja sirkulasi supaya bisa meminjam lagi.</div>
    <?php } ?>

    <?php $pengingat = pengingat_anggota($koneksi, $id_ang); if ($pengingat) { ?>
        <div class="card card-body py-2 mb-3">
            <?php foreach ($pengingat as $p) { ?>
                <a href="<?php echo e($p['url']); ?>" class="d-block py-1 text-reset">
                    <i class="fas <?php echo $p['ikon']; ?> text-<?php echo $p['jenis']; ?> mr-2"></i><?php echo e($p['teks']); ?>
                </a>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-6 col-lg-3 mb-3">
            <div class="pz-stat is-accent">
                <div class="pz-stat-value"><?php echo count($aktif); ?></div>
                <div class="pz-stat-label">Sedang dipinjam</div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-3">
            <a href="pinjam_user.php" class="d-block h-100 text-reset">
                <div class="pz-stat">
                    <div class="pz-stat-value"><?php echo $jml_ajuan; ?></div>
                    <div class="pz-stat-label">Menunggu diambil</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3 mb-3">
            <div class="pz-stat">
                <div class="pz-stat-value"><?php echo $jml_kembali; ?></div>
                <div class="pz-stat-label">Sudah dikembalikan</div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-3">
            <a href="riwayat_user.php" class="d-block h-100 text-reset">
                <div class="pz-stat <?php echo $denda_belum > 0 ? 'is-alert' : ''; ?>">
                    <div class="pz-stat-value"><?php echo format_rupiah($denda_belum); ?></div>
                    <div class="pz-stat-label">Denda belum dibayar</div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Jatuh tempo</h3></div>
                <div class="card-body p-0">
                    <?php if (!$aktif) { ?>
                        <div class="text-center py-5 text-muted">
                            Belum ada pinjaman aktif.
                            <a href="pinjam_user.php">Ajukan pinjaman</a> lalu ambil bukunya di perpustakaan.
                        </div>
                    <?php } else { ?>
                    <table class="table mb-0">
                        <tbody>
                        <?php foreach ($aktif as $r) {
                            $tempo = jatuh_tempo_baris($r);
                            $sisa  = (int) floor((strtotime($tempo) - strtotime(date('Y-m-d'))) / 86400);
                        ?>
                            <tr>
                                <td><strong><?php echo e($r['judul']); ?></strong></td>
                                <td class="text-nowrap"><?php echo date('d-m-Y', strtotime($tempo)); ?></td>
                                <td class="text-nowrap text-right">
                                    <?php if ($sisa < 0) { ?>
                                        <span class="pz-status telat">Terlambat <?php echo -$sisa; ?> hari</span>
                                    <?php } elseif ($sisa <= 2) { ?>
                                        <span class="pz-status diajukan"><?php echo $sisa === 0 ? 'Hari ini' : "$sisa hari lagi"; ?></span>
                                    <?php } else { ?>
                                        <span class="text-muted"><?php echo $sisa; ?> hari lagi</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <?php } ?>
                </div>
                <?php if ($aktif) { ?>
                    <div class="card-footer bg-white"><a href="riwayat_user.php">Lihat detail pinjaman</a></div>
                <?php } ?>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Akun</h3></div>
                <div class="card-body">
                    <?php if ($anggota) { ?>
                        <p class="mb-1 text-muted">Nomor anggota</p>
                        <p class="h5 pz-title mb-3"><?php echo sprintf('AGT-%04d', $anggota['id_ang']); ?></p>
                        <p class="mb-1 text-muted">Berlaku sampai</p>
                        <p class="mb-3"><?php echo $anggota['masa_berlaku'] ? date('d-m-Y', strtotime($anggota['masa_berlaku'])) : '-'; ?></p>
                    <?php } ?>
                    <a href="kartu_anggota.php" class="btn btn-outline-primary btn-block"><i class="fas fa-id-card mr-1"></i> Kartu anggota</a>
                    <a href="profil.php" class="btn btn-secondary btn-block"><i class="fas fa-user-cog mr-1"></i> Profil &amp; password</a>
                </div>
            </div>
        </div>
    </div>

</div>
</section>

<?php include "inc/footer.php"; ?>
