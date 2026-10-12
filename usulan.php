<?php
/* =========================================================
   USULAN PENGADAAN BUKU (admin & anggota)
   Anggota mengusulkan judul yang belum ada di koleksi.
   Admin menerima/menolak dengan tanggapan; anggota melihat
   hasilnya di sini dan di lonceng notifikasi.
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

$admin_us = $akun && strtolower($akun['status']) === 'admin';
$id_ang   = (int) ($akun['id_ang'] ?? 0);
$error    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'usul' && !$admin_us && $id_ang > 0) {

        $judul   = trim((string) ($_POST['judul'] ?? ''));
        $penulis = trim((string) ($_POST['penulis'] ?? ''));
        $alasan  = trim((string) ($_POST['alasan'] ?? ''));

        if ($judul === '' || mb_strlen($judul) > 100) $error[] = 'Judul wajib diisi (maksimal 100 karakter).';
        if (mb_strlen($penulis) > 60)                 $error[] = 'Penulis maksimal 60 karakter.';
        if (mb_strlen($alasan) > 300)                 $error[] = 'Alasan maksimal 300 karakter.';

        if (!$error) {
            $stmt = mysqli_prepare($koneksi, "SELECT kode_buku FROM tbbuku WHERE judul = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $judul);
            mysqli_stmt_execute($stmt);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                $error[] = 'Buku dengan judul ini sudah ada di koleksi.';
            }
            mysqli_stmt_close($stmt);
        }

        if (!$error) {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO tbusulan (id_ang, judul, penulis, alasan, tgl) VALUES (?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, 'isss', $id_ang, $judul, $penulis, $alasan);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            catat_log('usulan', "Anggota #$id_ang mengusulkan \"$judul\"");
            flash_set('success', "Usulan \"$judul\" terkirim. Terima kasih!");
            header("Location: usulan.php");
            exit;
        }
    }

    if (($aksi === 'terima' || $aksi === 'tolak') && $admin_us) {

        $id = (int) ($_POST['id'] ?? 0);
        $tanggapan = mb_substr(trim((string) ($_POST['tanggapan'] ?? '')), 0, 300);
        $status = $aksi === 'terima' ? 'diterima' : 'ditolak';

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE tbusulan SET status = ?, tanggapan = ?, tgl_tanggapan = NOW() WHERE id = ? AND status = 'diajukan'"
        );
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $tanggapan, $id);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);

        if ($ok) {
            catat_log('usulan', "Usulan #$id $status");
            flash_set('success', "Usulan $status.");
        }
        header("Location: usulan.php");
        exit;
    }
}

$syarat = $admin_us ? '' : "WHERE u.id_ang = $id_ang";
$daftar = [];
$q = @mysqli_query(
    $koneksi,
    "SELECT u.*, a.nama FROM tbusulan u JOIN tbanggota a ON a.id_ang = u.id_ang
     $syarat ORDER BY (u.status = 'diajukan') DESC, u.tgl DESC"
);
$tabel_ada = (bool) $q;
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $daftar[] = $r;
}

$label = [
    'diajukan' => '<span class="pz-status diajukan">Menunggu</span>',
    'diterima' => '<span class="pz-status kembali">Diterima</span>',
    'ditolak'  => '<span class="pz-status ditolak">Ditolak</span>',
];

$page_title = "Usulan buku";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Usulan buku</h1>
            <p><?php echo $admin_us
                ? 'Judul yang diminta anggota. Bahan pertimbangan pengadaan koleksi.'
                : 'Belum menemukan buku yang dicari? Usulkan supaya perpustakaan membelinya.'; ?></p>
        </div>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if (!$tabel_ada) { ?>
        <div class="alert alert-warning">Tabel usulan belum ada. Impor <code>database/migrasi_v4.sql</code>.</div>
    <?php } ?>

    <?php if (!$admin_us && $id_ang > 0) { ?>
    <form method="post" class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Usulan baru</h3></div>
        <div class="card-body">
            <?php if ($error) { ?><div class="alert alert-danger"><?php echo e(implode(' ', $error)); ?></div><?php } ?>
            <div class="form-row">
                <div class="form-group col-md-7">
                    <label for="judul">Judul buku</label>
                    <input type="text" class="form-control" id="judul" name="judul" maxlength="100" required value="<?php echo e($_POST['judul'] ?? ''); ?>">
                </div>
                <div class="form-group col-md-5">
                    <label for="penulis">Penulis <span class="text-muted font-weight-normal">(opsional)</span></label>
                    <input type="text" class="form-control" id="penulis" name="penulis" maxlength="60" value="<?php echo e($_POST['penulis'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group mb-0">
                <label for="alasan">Alasan <span class="text-muted font-weight-normal">(opsional)</span></label>
                <input type="text" class="form-control" id="alasan" name="alasan" maxlength="300" placeholder="Misalnya: referensi mata kuliah Basis Data" value="<?php echo e($_POST['alasan'] ?? ''); ?>">
            </div>
        </div>
        <div class="card-footer bg-white text-right">
            <button type="submit" name="aksi" value="usul" class="btn btn-gold"><i class="fas fa-paper-plane mr-1"></i> Kirim usulan</button>
        </div>
    </form>
    <?php } ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo $admin_us ? 'Semua usulan' : 'Usulan saya'; ?> (<?php echo count($daftar); ?>)</h3></div>
        <div class="card-body p-0">
            <?php if (!$daftar) { ?>
                <p class="text-muted text-center py-4 mb-0">Belum ada usulan.</p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Tanggal</th><th>Judul</th><?php if ($admin_us) { ?><th>Pengusul</th><?php } ?><th>Status</th><th><?php echo $admin_us ? 'Tanggapan' : 'Tanggapan petugas'; ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($daftar as $u) { ?>
                        <tr>
                            <td class="text-nowrap"><?php echo date('d-m-Y', strtotime($u['tgl'])); ?></td>
                            <td>
                                <strong><?php echo e($u['judul']); ?></strong>
                                <?php if ($u['penulis']) { ?><br><small class="text-muted"><?php echo e($u['penulis']); ?></small><?php } ?>
                                <?php if ($u['alasan']) { ?><br><small class="text-muted">Alasan: <?php echo e($u['alasan']); ?></small><?php } ?>
                            </td>
                            <?php if ($admin_us) { ?><td><?php echo e($u['nama']); ?></td><?php } ?>
                            <td><?php echo $label[$u['status']]; ?></td>
                            <td style="min-width: 260px;">
                                <?php if ($admin_us && $u['status'] === 'diajukan') { ?>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                        <input type="text" name="tanggapan" class="form-control form-control-sm mb-1" maxlength="300" placeholder="Tanggapan (opsional)">
                                        <button type="submit" name="aksi" value="terima" class="btn btn-sm btn-primary">Terima</button>
                                        <button type="submit" name="aksi" value="tolak" class="btn btn-sm btn-outline-danger">Tolak</button>
                                    </form>
                                <?php } else { ?>
                                    <?php echo $u['tanggapan'] ? e($u['tanggapan']) : '<span class="text-muted">-</span>'; ?>
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

</div>
</section>

<?php include "inc/footer.php"; ?>
