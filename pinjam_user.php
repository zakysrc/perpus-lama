<?php
/* =========================================================
   AJUKAN PEMINJAMAN (anggota)
   ---------------------------------------------------------
   Anggota TIDAK langsung mengurangi stok dari rumah.
   Pengajuan disimpan dengan status 'diajukan'; buku dianggap
   dipinjam setelah petugas menyerahkannya di meja sirkulasi
   (halaman pengajuan.php). Saat itulah trigger database
   `sebelum_pengembalian` mengisi tanggal pinjam, jatuh tempo,
   dan mengurangi stok.

   Aturan (batas jumlah, masa berlaku, pinjaman terlambat,
   buku dobel, batas buku aktif) memakai cek_boleh_pinjam()
   yang SAMA dengan halaman admin.
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
    header("Location: pinjam.php");
    exit;
}

$id_ang = (int) ($akun['id_ang'] ?? 0);


/* ---------------- PROSES ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_ang > 0) {

    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'ajukan') {

        $kode_buku = (int) ($_POST['kode_buku'] ?? 0);
        $jumlah    = (int) ($_POST['jml_pinjam'] ?? 0);

        mysqli_begin_transaction($koneksi);

        $q = mysqli_query($koneksi, "SELECT judul, jml_buku FROM tbbuku WHERE kode_buku = $kode_buku FOR UPDATE");
        $buku = $q ? mysqli_fetch_assoc($q) : null;

        if (!$buku || $jumlah < 1) {
            $alasan = 'Pilih buku dan jumlah yang valid.';
        } elseif ((int) $buku['jml_buku'] < $jumlah) {
            $alasan = 'Stok "' . $buku['judul'] . '" tinggal ' . (int) $buku['jml_buku'] . ' buku.';
        } else {
            $alasan = cek_boleh_pinjam($koneksi, $id_ang, $kode_buku, $jumlah);
        }

        if ($alasan !== null) {
            mysqli_rollback($koneksi);
            flash_set('danger', $alasan);
            header("Location: pinjam_user.php" . ($kode_buku ? "?kode_buku=$kode_buku" : ''));
            exit;
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO tbpinjam (id_ang, kode_buku, jml_pinjam, status, denda, tgl_ajuan)
             VALUES (?, ?, ?, 'diajukan', 0, NOW())"
        );
        mysqli_stmt_bind_param($stmt, 'iii', $id_ang, $kode_buku, $jumlah);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            mysqli_commit($koneksi);
            selesaikan_reservasi($koneksi, $id_ang, $kode_buku);
            catat_log('ajukan_pinjam', "Anggota #$id_ang - \"{$buku['judul']}\" x$jumlah");
            flash_set('success', 'Pengajuan "' . $buku['judul'] . '" terkirim. Ambil bukunya di meja sirkulasi dengan menunjukkan kartu anggota.');
        } else {
            mysqli_rollback($koneksi);
            flash_set('danger', 'Pengajuan gagal disimpan: ' . mysqli_error($koneksi));
        }

        header("Location: pinjam_user.php");
        exit;
    }

    if ($aksi === 'batal') {

        $no = (int) ($_POST['no_pinjam'] ?? 0);

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE tbpinjam SET status = 'ditolak', catatan = 'Dibatalkan anggota'
              WHERE no_pinjam = ? AND id_ang = ? AND status = 'diajukan'"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $no, $id_ang);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);

        if ($ok) {
            catat_log('batal_pinjam', "Anggota #$id_ang membatalkan pengajuan #$no");
            flash_set('info', 'Pengajuan dibatalkan.');
        }

        header("Location: pinjam_user.php");
        exit;
    }
}


/* ---------------- DATA TAMPILAN ---------------- */
$kode_pilih = (int) ($_GET['kode_buku'] ?? 0);

$buku_list = [];
$q = mysqli_query($koneksi, "SELECT kode_buku, judul, penulis, jml_buku FROM tbbuku WHERE jml_buku > 0 ORDER BY judul");
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $buku_list[] = $r;
}

$ajuan = [];
if ($id_ang > 0) {
    $q = mysqli_query(
        $koneksi,
        "SELECT p.no_pinjam, p.jml_pinjam, p.tgl_ajuan, p.status, b.judul
           FROM tbpinjam p JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.id_ang = $id_ang AND p.status = 'diajukan'
          ORDER BY p.tgl_ajuan DESC"
    );
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $ajuan[] = $r;
    }
}

$page_title = "Ajukan pinjaman";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Ajukan pinjaman</h1>
            <p>Ajukan dari sini, lalu ambil bukunya di meja sirkulasi. Masa pinjam dihitung sejak buku diserahkan.</p>
        </div>
        <a href="buku_user.php" class="btn btn-outline-primary"><i class="fas fa-book mr-1"></i> Lihat koleksi</a>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if ($id_ang <= 0) { ?>

        <div class="alert alert-danger">Akun ini belum terhubung dengan data anggota. Hubungi petugas perpustakaan.</div>

    <?php } else { ?>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <form method="post" class="card card-primary card-outline h-100">
                <div class="card-header"><h3 class="card-title">Pilih buku</h3></div>
                <div class="card-body">
                    <?php if (!$buku_list) { ?>
                        <p class="text-muted mb-0">Semua buku sedang dipinjam. Coba lagi nanti.</p>
                    <?php } else { ?>
                        <div class="form-group">
                            <label for="kode_buku">Buku</label>
                            <select id="kode_buku" name="kode_buku" class="custom-select" required>
                                <option value="">Pilih judul</option>
                                <?php foreach ($buku_list as $b) { ?>
                                    <option value="<?php echo (int) $b['kode_buku']; ?>" data-stok="<?php echo (int) $b['jml_buku']; ?>"
                                        <?php echo (int) $b['kode_buku'] === $kode_pilih ? 'selected' : ''; ?>>
                                        <?php echo e($b['judul']); ?> - <?php echo e($b['penulis']); ?> (stok <?php echo (int) $b['jml_buku']; ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label for="jml_pinjam">Jumlah</label>
                            <input type="number" id="jml_pinjam" name="jml_pinjam" class="form-control" min="1"
                                   max="<?php echo maks_jml_per_pinjam(); ?>" value="1" required style="max-width:140px">
                            <small class="form-text text-muted">Maksimal <?php echo maks_jml_per_pinjam(); ?> buku per pengajuan.</small>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($buku_list) { ?>
                <div class="card-footer bg-white text-right">
                    <button type="submit" name="aksi" value="ajukan" class="btn btn-gold"><i class="fas fa-paper-plane mr-1"></i> Kirim pengajuan</button>
                </div>
                <?php } ?>
            </form>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Aturan peminjaman</h3></div>
                <div class="card-body">
                    <ul class="pl-3 mb-0">
                        <li>Masa pinjam <?php echo masa_pinjam_hari(); ?> hari sejak buku diserahkan.</li>
                        <li>Denda keterlambatan <?php echo format_rupiah(denda_per_hari()); ?> per hari.</li>
                        <li>Maksimal <?php echo maks_buku_aktif(); ?> buku dipinjam/diajukan sekaligus.</li>
                        <li><?php echo maks_perpanjang() > 0
                            ? 'Perpanjangan (maksimal ' . maks_perpanjang() . ' kali) dilakukan oleh petugas di meja sirkulasi, selama belum lewat jatuh tempo.'
                            : 'Peminjaman tidak dapat diperpanjang.'; ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Pengajuan menunggu (<?php echo count($ajuan); ?>)</h3></div>
        <div class="card-body p-0">
            <?php if (!$ajuan) { ?>
                <p class="text-muted text-center py-4 mb-0">Belum ada pengajuan yang menunggu.</p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Diajukan</th><th>Buku</th><th class="text-center">Jumlah</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($ajuan as $a) { ?>
                        <tr>
                            <td class="text-nowrap"><?php echo date('d-m-Y H:i', strtotime($a['tgl_ajuan'])); ?></td>
                            <td><?php echo e($a['judul']); ?></td>
                            <td class="text-center"><?php echo (int) $a['jml_pinjam']; ?></td>
                            <td><?php echo badge_status($a); ?></td>
                            <td class="text-right">
                                <form method="post" onsubmit="return confirm('Batalkan pengajuan ini?');">
                                    <input type="hidden" name="no_pinjam" value="<?php echo (int) $a['no_pinjam']; ?>">
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

    <?php } ?>

</div>
</section>

<?php include "inc/footer.php"; ?>
