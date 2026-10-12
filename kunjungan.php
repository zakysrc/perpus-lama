<?php
/* =========================================================
   BUKU TAMU / KUNJUNGAN (admin)
   Mencatat setiap orang yang datang ke perpustakaan.
   - Anggota: scan/ketik kode kartu (AGT-0001)
   - Tamu umum: ketik nama
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

$keperluan_list = ['Membaca di tempat', 'Meminjam / mengembalikan', 'Mengerjakan tugas', 'Mencari referensi', 'Lainnya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $kode      = trim((string) ($_POST['kode'] ?? ''));
    $keperluan = (string) ($_POST['keperluan'] ?? '');
    if (!in_array($keperluan, $keperluan_list, true)) {
        $keperluan = 'Lainnya';
    }

    $id_ang = null;
    $nama   = '';

    $id = id_dari_kode_anggota($kode);
    if ($id > 0) {
        $q = mysqli_query($koneksi, "SELECT id_ang, nama FROM tbanggota WHERE id_ang = $id");
        if ($q && ($a = mysqli_fetch_assoc($q))) {
            $id_ang = (int) $a['id_ang'];
            $nama   = $a['nama'];
        }
    }

    if ($id_ang === null) {
        $nama = mb_substr($kode, 0, 50);
    }

    if ($nama === '' || ($id > 0 && $id_ang === null && preg_match('/^(AGT-?)?\d+$/i', $kode))) {
        flash_set('danger', $nama === '' ? 'Isi kode anggota atau nama tamu.' : "Kode anggota $kode tidak ditemukan.");
    } else {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO tbkunjungan (id_ang, nama, keperluan, waktu) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'iss', $id_ang, $nama, $keperluan);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash_set('success', "Kunjungan $nama tercatat" . ($id_ang ? ' (anggota).' : ' (tamu umum).'));
    }

    header("Location: kunjungan.php");
    exit;
}

$hari_ini = [];
$q = @mysqli_query($koneksi, "SELECT * FROM tbkunjungan WHERE DATE(waktu) = CURDATE() ORDER BY waktu DESC");
$tabel_ada = (bool) $q;
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $hari_ini[] = $r;
}

/* 7 hari terakhir untuk grafik batang sederhana */
$per_hari = [];
for ($i = 6; $i >= 0; $i--) {
    $per_hari[date('Y-m-d', strtotime("-$i days"))] = 0;
}
$q = @mysqli_query(
    $koneksi,
    "SELECT DATE(waktu) AS tgl, COUNT(*) AS n FROM tbkunjungan
      WHERE waktu >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(waktu)"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $per_hari[$r['tgl']] = (int) $r['n'];
}
$maks = max(1, max($per_hari));
$total_minggu = array_sum($per_hari);
$nama_hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

$page_title = "Kunjungan";
include "inc/header.php";
?>

<style>
    .pz-bar-wrap { display: flex; align-items: flex-end; gap: 10px; height: 150px; }
    .pz-bar-col { flex: 1; text-align: center; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; }
    .pz-bar { background: var(--pz-navy-700); border-radius: 4px 4px 0 0; min-height: 3px; }
    .pz-bar-col.hari-ini .pz-bar { background: var(--pz-gold-500); }
    .pz-bar-col small { color: var(--pz-muted); margin-top: 4px; }
    .pz-bar-col b { font-size: 13px; color: var(--pz-navy-800); }
</style>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Buku tamu</h1>
            <p>Scan kartu anggota atau ketik nama tamu, lalu tekan Enter.</p>
        </div>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if (!$tabel_ada) { ?>
        <div class="alert alert-warning">Tabel kunjungan belum ada. Impor <code>database/migrasi_v4.sql</code>.</div>
    <?php } ?>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <form method="post" class="card card-primary card-outline h-100">
                <div class="card-header"><h3 class="card-title">Catat kunjungan</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="kode">Kode kartu anggota atau nama tamu</label>
                        <input type="text" class="form-control form-control-lg" id="kode" name="kode" maxlength="50" required autofocus
                               placeholder="AGT-0001 atau Nama Tamu" autocomplete="off">
                    </div>
                    <div class="form-group mb-0">
                        <label for="keperluan">Keperluan</label>
                        <select class="custom-select" id="keperluan" name="keperluan">
                            <?php foreach ($keperluan_list as $k) { ?><option><?php echo e($k); ?></option><?php } ?>
                        </select>
                    </div>
                </div>
                <div class="card-footer bg-white text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Catat</button>
                </div>
            </form>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">7 hari terakhir: <?php echo $total_minggu; ?> kunjungan</h3></div>
                <div class="card-body">
                    <div class="pz-bar-wrap">
                        <?php foreach ($per_hari as $tgl => $n) { ?>
                            <div class="pz-bar-col <?php echo $tgl === date('Y-m-d') ? 'hari-ini' : ''; ?>">
                                <b><?php echo $n; ?></b>
                                <div class="pz-bar" style="height: <?php echo round($n / $maks * 100); ?>%"></div>
                                <small><?php echo $nama_hari[(int) date('w', strtotime($tgl))]; ?></small>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Hari ini (<?php echo count($hari_ini); ?>)</h3></div>
        <div class="card-body p-0">
            <?php if (!$hari_ini) { ?>
                <p class="text-muted text-center py-4 mb-0">Belum ada kunjungan hari ini.</p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Jam</th><th>Nama</th><th>Jenis</th><th>Keperluan</th></tr></thead>
                    <tbody>
                    <?php foreach ($hari_ini as $k) { ?>
                        <tr>
                            <td><?php echo date('H:i', strtotime($k['waktu'])); ?></td>
                            <td><?php echo e($k['nama']); ?></td>
                            <td><?php echo $k['id_ang'] ? '<span class="badge badge-primary">' . sprintf('AGT-%04d', $k['id_ang']) . '</span>' : '<span class="badge badge-secondary">Tamu umum</span>'; ?></td>
                            <td><?php echo e($k['keperluan']); ?></td>
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
