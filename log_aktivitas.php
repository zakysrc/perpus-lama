<?php
/* =========================================================
   LOG AKTIVITAS (khusus admin)
   Jejak audit: siapa melakukan apa dan kapan.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

$aksi_filter = trim((string) ($_GET['aksi'] ?? ''));
$hari        = (int) ($_GET['hari'] ?? 30);
if (!in_array($hari, [1, 7, 30, 90, 365], true)) {
    $hari = 30;
}

/* Daftar jenis aksi yang pernah tercatat, untuk dropdown filter */
$jenis = [];
$q = @mysqli_query($koneksi, "SELECT DISTINCT aksi FROM tblog ORDER BY aksi");
$tabel_ada = (bool) $q;
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $jenis[] = $r['aksi'];
    }
}

$baris = [];

if ($tabel_ada) {
    $sql = "SELECT waktu, username, aksi, detail, ip FROM tblog
            WHERE waktu >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    if ($aksi_filter !== '') {
        $sql .= " AND aksi = ?";
    }
    $sql .= " ORDER BY id DESC LIMIT 1000";

    $stmt = mysqli_prepare($koneksi, $sql);
    if ($aksi_filter !== '') {
        mysqli_stmt_bind_param($stmt, 'is', $hari, $aksi_filter);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $hari);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $baris[] = $r;
    }
    mysqli_stmt_close($stmt);
}

/* Nama aksi yang ramah dibaca */
$label_aksi = [
    'login' => 'Login', 'logout' => 'Logout',
    'tambah_buku' => 'Tambah buku', 'ubah_buku' => 'Ubah buku', 'hapus_buku' => 'Hapus buku',
    'tambah_anggota' => 'Tambah anggota', 'ubah_anggota' => 'Ubah anggota', 'hapus_anggota' => 'Hapus anggota',
    'pinjam' => 'Peminjaman', 'ajukan_pinjam' => 'Pengajuan pinjam', 'setujui_pinjam' => 'Setujui pengajuan',
    'tolak_pinjam' => 'Tolak pengajuan', 'batal_pinjam' => 'Batalkan pengajuan',
    'kembali' => 'Pengembalian', 'perpanjang' => 'Perpanjangan', 'denda' => 'Status denda',
    'pengaturan' => 'Ubah pengaturan', 'reservasi' => 'Reservasi', 'batal_reservasi' => 'Batal reservasi',
    'usulan' => 'Usulan buku', 'ulasan' => 'Ulasan buku', 'backup' => 'Backup database', 'laporan' => 'Unduh laporan', 'login_gagal' => 'Login gagal', 'reset_password' => 'Reset password', 'ganti_password' => 'Ganti password', 'ubah_profil' => 'Ubah profil',
];

$page_title = "Log aktivitas";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Log aktivitas</h1>
            <p>Catatan perubahan data dan transaksi, terbaru di atas (maksimal 1.000 baris).</p>
        </div>
    </div>

    <?php if (!$tabel_ada) { ?>
        <div class="alert alert-warning">Tabel <code>tblog</code> belum ada. Impor <code>database/migrasi_v3.sql</code> terlebih dahulu.</div>
    <?php } ?>

    <form method="get" class="card card-body py-3" autocomplete="off">
        <div class="form-row align-items-end">
            <div class="col-md-4 mb-2 mb-md-0">
                <label for="aksi" class="small">Jenis aktivitas</label>
                <select id="aksi" name="aksi" class="custom-select" onchange="this.form.submit()">
                    <option value="">Semua aktivitas</option>
                    <?php foreach ($jenis as $j) { ?>
                        <option value="<?php echo e($j); ?>" <?php echo $j === $aksi_filter ? 'selected' : ''; ?>><?php echo e($label_aksi[$j] ?? $j); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
                <label for="hari" class="small">Rentang waktu</label>
                <select id="hari" name="hari" class="custom-select" onchange="this.form.submit()">
                    <?php foreach ([1 => 'Hari ini', 7 => '7 hari', 30 => '30 hari', 90 => '90 hari', 365 => '1 tahun'] as $h => $lbl) { ?>
                        <option value="<?php echo $h; ?>" <?php echo $h === $hari ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i> Terapkan</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Keterangan</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$baris) { ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada aktivitas pada rentang ini.</td></tr>
                    <?php } ?>
                    <?php foreach ($baris as $r) { ?>
                        <tr>
                            <td class="text-nowrap" data-order="<?php echo e($r['waktu']); ?>"><?php echo date('d-m-Y H:i', strtotime($r['waktu'])); ?></td>
                            <td><?php echo e($r['username'] ?? '-'); ?></td>
                            <td><span class="badge badge-primary"><?php echo e($label_aksi[$r['aksi']] ?? $r['aksi']); ?></span></td>
                            <td><?php echo e($r['detail']); ?></td>
                            <td class="text-muted small"><?php echo e($r['ip']); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</section>

<?php include "inc/footer.php"; ?>
