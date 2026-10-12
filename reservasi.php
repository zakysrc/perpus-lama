<?php
/* =========================================================
   RESERVASI BUKU (admin & anggota)
   ---------------------------------------------------------
   Anggota memesan buku yang stoknya habis dan masuk antrean.
   Saat buku dikembalikan, anggota di urutan terdepan mendapat
   pengingat "sudah tersedia" (lihat pengingat_anggota()), dan
   stok itu tidak bisa diambil anggota lain yang tidak antre
   (aturan no. 6 di cek_boleh_pinjam()).
   Reservasi otomatis selesai saat anggota mengajukan pinjaman.
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

$admin_res = $akun && strtolower($akun['status']) === 'admin';
$id_ang    = (int) ($akun['id_ang'] ?? 0);


/* ---------------- PROSES ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'pesan' && !$admin_res && $id_ang > 0) {

        $kode = (int) ($_POST['kode_buku'] ?? 0);
        $q = mysqli_query($koneksi, "SELECT judul, jml_buku FROM tbbuku WHERE kode_buku = $kode");
        $buku = $q ? mysqli_fetch_assoc($q) : null;

        $aktif = (int) mysqli_fetch_assoc(mysqli_query(
            $koneksi,
            "SELECT COUNT(*) AS n FROM tbreservasi WHERE id_ang = $id_ang AND status = 'menunggu'"
        ))['n'];

        $dobel = (int) mysqli_fetch_assoc(mysqli_query(
            $koneksi,
            "SELECT COUNT(*) AS n FROM tbreservasi WHERE id_ang = $id_ang AND kode_buku = $kode AND status = 'menunggu'"
        ))['n'];

        $sedang = (int) mysqli_fetch_assoc(mysqli_query(
            $koneksi,
            "SELECT COUNT(*) AS n FROM tbpinjam WHERE id_ang = $id_ang AND kode_buku = $kode
              AND status IN ('pinjam','diajukan')"
        ))['n'];

        if (!$buku) {
            flash_set('danger', 'Buku tidak ditemukan.');
        } elseif ($dobel) {
            flash_set('info', 'Kamu sudah ada di antrean buku ini.');
        } elseif ($sedang) {
            flash_set('warning', 'Buku ini sedang kamu pinjam atau ajukan.');
        } elseif ($aktif >= maks_reservasi()) {
            flash_set('warning', 'Batas reservasi aktif adalah ' . maks_reservasi() . ' buku.');
        } elseif ((int) $buku['jml_buku'] > antrean_di_depan($koneksi, $id_ang, $kode)) {
            flash_set('info', 'Buku ini masih tersedia, langsung ajukan pinjaman saja.');
            header("Location: pinjam_user.php?kode_buku=$kode");
            exit;
        } else {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO tbreservasi (id_ang, kode_buku, tgl_reservasi) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, 'ii', $id_ang, $kode);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $posisi = antrean_di_depan($koneksi, $id_ang, $kode) + 1;
            catat_log('reservasi', "Anggota #$id_ang - \"{$buku['judul']}\" antrean ke-$posisi");
            flash_set('success', "Reservasi \"{$buku['judul']}\" tercatat. Kamu antrean ke-$posisi dan akan diberi tahu saat buku tersedia.");
        }

        header("Location: reservasi.php");
        exit;
    }

    if ($aksi === 'batal') {

        $id = (int) ($_POST['id'] ?? 0);
        $syarat = $admin_res ? '' : " AND id_ang = $id_ang";

        mysqli_query(
            $koneksi,
            "UPDATE tbreservasi SET status = 'batal', tgl_selesai = NOW()
              WHERE id = $id AND status = 'menunggu' $syarat"
        );

        if (mysqli_affected_rows($koneksi) === 1) {
            catat_log('batal_reservasi', "Reservasi #$id dibatalkan");
            flash_set('info', 'Reservasi dibatalkan.');
        }

        header("Location: reservasi.php");
        exit;
    }
}


/* ---------------- DATA ---------------- */
$syarat = $admin_res ? '' : " AND r.id_ang = $id_ang";

$daftar = [];
$q = @mysqli_query(
    $koneksi,
    "SELECT r.*, a.nama, b.judul, b.jml_buku,
            (SELECT COUNT(*) FROM tbreservasi r2
              WHERE r2.kode_buku = r.kode_buku AND r2.status = 'menunggu'
                AND r2.id < r.id) + 1 AS posisi
       FROM tbreservasi r
       JOIN tbanggota a ON a.id_ang = r.id_ang
       JOIN tbbuku b ON b.kode_buku = r.kode_buku
      WHERE r.status = 'menunggu' $syarat
      ORDER BY b.judul, r.id"
);
$tabel_ada = (bool) $q;
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $daftar[] = $r;
}

$riwayat = [];
$q = @mysqli_query(
    $koneksi,
    "SELECT r.*, a.nama, b.judul FROM tbreservasi r
       JOIN tbanggota a ON a.id_ang = r.id_ang
       JOIN tbbuku b ON b.kode_buku = r.kode_buku
      WHERE r.status <> 'menunggu' $syarat
      ORDER BY r.tgl_selesai DESC LIMIT 15"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $riwayat[] = $r;
}

$page_title = "Reservasi";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1><?php echo $admin_res ? 'Antrean reservasi' : 'Reservasi saya'; ?></h1>
            <p><?php echo $admin_res
                ? 'Anggota yang menunggu buku dengan stok habis, urut sesuai waktu reservasi.'
                : 'Pesan buku yang sedang habis. Kamu akan diberi tahu di lonceng notifikasi saat buku tersedia.'; ?></p>
        </div>
        <?php if (!$admin_res) { ?>
            <a href="buku_user.php" class="btn btn-outline-primary"><i class="fas fa-book mr-1"></i> Cari buku</a>
        <?php } ?>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if (!$tabel_ada) { ?>
        <div class="alert alert-warning">Tabel reservasi belum ada. Impor <code>database/migrasi_v4.sql</code>.</div>
    <?php } ?>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Menunggu (<?php echo count($daftar); ?>)</h3></div>
        <div class="card-body p-0">
            <?php if (!$daftar) { ?>
                <p class="text-muted text-center py-4 mb-0">
                    <?php echo $admin_res ? 'Tidak ada antrean.' : 'Belum ada reservasi. Tombol Reservasi muncul di buku yang stoknya habis.'; ?>
                </p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Buku</th><?php if ($admin_res) { ?><th>Anggota</th><?php } ?><th>Sejak</th><th class="text-center">Antrean</th><th>Status</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftar as $r) { $siap = (int) $r['jml_buku'] >= (int) $r['posisi']; ?>
                        <tr>
                            <td><strong><?php echo e($r['judul']); ?></strong><br><small class="text-muted">Stok saat ini <?php echo (int) $r['jml_buku']; ?></small></td>
                            <?php if ($admin_res) { ?><td><?php echo e($r['nama']); ?><br><small class="text-muted"><?php echo sprintf('AGT-%04d', $r['id_ang']); ?></small></td><?php } ?>
                            <td class="text-nowrap"><?php echo date('d-m-Y H:i', strtotime($r['tgl_reservasi'])); ?></td>
                            <td class="text-center">ke-<?php echo (int) $r['posisi']; ?></td>
                            <td><?php echo $siap ? '<span class="pz-status kembali">Tersedia</span>' : '<span class="pz-status diajukan">Menunggu</span>'; ?></td>
                            <td class="text-right text-nowrap">
                                <?php if ($siap && !$admin_res) { ?>
                                    <a href="pinjam_user.php?kode_buku=<?php echo (int) $r['kode_buku']; ?>" class="btn btn-sm btn-gold">Ajukan sekarang</a>
                                <?php } ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Batalkan reservasi ini?');">
                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" name="aksi" value="batal" class="btn btn-sm btn-outline-danger">Batalkan</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($riwayat) { ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Riwayat reservasi</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Buku</th><?php if ($admin_res) { ?><th>Anggota</th><?php } ?><th>Dibuat</th><th>Hasil</th></tr></thead>
                    <tbody>
                    <?php foreach ($riwayat as $r) { ?>
                        <tr>
                            <td><?php echo e($r['judul']); ?></td>
                            <?php if ($admin_res) { ?><td><?php echo e($r['nama']); ?></td><?php } ?>
                            <td class="text-nowrap"><?php echo date('d-m-Y', strtotime($r['tgl_reservasi'])); ?></td>
                            <td><?php echo $r['status'] === 'selesai' ? '<span class="pz-status kembali">Sudah diajukan</span>' : '<span class="pz-status ditolak">Dibatalkan</span>'; ?></td>
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
