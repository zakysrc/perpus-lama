<?php
/* =========================================================
   PENGEMBALIAN BUKU (admin)
   ---------------------------------------------------------
   PHP hanya mengubah status 'pinjam' -> 'kembali'. Trigger
   database kemudian:
     1. mengisi tgl_kembali = hari ini
     2. menghitung denda dari tanggal JATUH TEMPO
     3. mengembalikan stok buku
   Dari halaman ini admin juga bisa memperpanjang peminjaman.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kembali'])) {

    $id = (int) $_POST['kembali'];

    $q = mysqli_query(
        $koneksi,
        "SELECT p.no_pinjam, a.nama, b.judul
           FROM tbpinjam p
           JOIN tbanggota a ON a.id_ang = p.id_ang
           JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.no_pinjam = $id AND p.status = 'pinjam' LIMIT 1"
    );
    $data = $q ? mysqli_fetch_assoc($q) : null;

    if (!$data) {
        flash_set('warning', 'Peminjaman tidak ditemukan atau sudah dikembalikan.');
    } else {
        $ok = mysqli_query($koneksi, "UPDATE tbpinjam SET status = 'kembali' WHERE no_pinjam = $id AND status = 'pinjam'");

        if ($ok && mysqli_affected_rows($koneksi) === 1) {
            $r = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT denda FROM tbpinjam WHERE no_pinjam = $id"));
            $denda = (int) ($r['denda'] ?? 0);

            catat_log('kembali', "#$id {$data['nama']} - \"{$data['judul']}\"" . ($denda > 0 ? ', denda ' . format_rupiah($denda) : ''));

            flash_set(
                $denda > 0 ? 'warning' : 'success',
                "\"{$data['judul']}\" dari {$data['nama']} sudah dikembalikan."
                . ($denda > 0 ? ' Denda ' . format_rupiah($denda) . ' tercatat belum lunas di Riwayat & denda.' : ' Tidak ada denda.')
            );
        } else {
            flash_set('danger', 'Pengembalian gagal: ' . mysqli_error($koneksi));
        }
    }

    header("Location: kembali.php");
    exit;
}


$aktif = [];
$q = mysqli_query(
    $koneksi,
    "SELECT p.*, a.nama, a.telp, b.judul
       FROM tbpinjam p
       JOIN tbanggota a ON a.id_ang = p.id_ang
       JOIN tbbuku b ON b.kode_buku = p.kode_buku
      WHERE p.status = 'pinjam'
      ORDER BY " . sql_jatuh_tempo('p') . " ASC, p.no_pinjam ASC"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $aktif[] = $r;
}

$jml_telat = 0;
$est_denda = 0;
foreach ($aktif as $r) {
    $t = jatuh_tempo_baris($r);
    if (hari_telat_dari_tempo($t) > 0) {
        $jml_telat++;
        $est_denda += denda_dari_tempo($t);
    }
}

$page_title = "Pengembalian";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Pengembalian buku</h1>
            <p>Diurutkan dari jatuh tempo paling dekat. Denda dihitung otomatis saat tombol Kembalikan ditekan.</p>
        </div>
    </div>

    <?php echo flash_tampil(); ?>

    <div class="row">
        <div class="col-md-4 mb-3"><div class="pz-stat pz-stat-navy"><div class="pz-stat-value"><?php echo count($aktif); ?></div><div class="pz-stat-label">Sedang dipinjam</div></div></div>
        <div class="col-md-4 mb-3"><div class="pz-stat pz-stat-navy"><div class="pz-stat-value"><?php echo $jml_telat; ?></div><div class="pz-stat-label">Lewat jatuh tempo</div></div></div>
        <div class="col-md-4 mb-3"><div class="pz-stat pz-stat-navy"><div class="pz-stat-value"><?php echo format_rupiah($est_denda); ?></div><div class="pz-stat-label">Perkiraan denda jika dikembalikan hari ini</div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr><th>Anggota</th><th>Buku</th><th>Dipinjam</th><th>Jatuh tempo</th><th>Status</th><th>Perkiraan denda</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$aktif) { ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada buku yang sedang dipinjam.</td></tr>
                    <?php } ?>
                    <?php foreach ($aktif as $r) {
                        $tempo = jatuh_tempo_baris($r);
                        $telat = hari_telat_dari_tempo($tempo);
                        $boleh_panjang = cek_boleh_perpanjang($r) === null;
                    ?>
                        <tr>
                            <td><strong><?php echo e($r['nama']); ?></strong><br><small class="text-muted"><?php echo sprintf('AGT-%04d', $r['id_ang']); ?></small></td>
                            <td><?php echo e($r['judul']); ?><br><small class="text-muted"><?php echo (int) $r['jml_pinjam']; ?> buku<?php echo (int) $r['perpanjang'] ? ' &middot; diperpanjang ' . (int) $r['perpanjang'] . 'x' : ''; ?></small></td>
                            <td class="text-nowrap" data-order="<?php echo e($r['tgl_pinjam']); ?>"><?php echo date('d-m-Y', strtotime($r['tgl_pinjam'])); ?></td>
                            <td class="text-nowrap" data-order="<?php echo e($tempo); ?>"><?php echo date('d-m-Y', strtotime($tempo)); ?></td>
                            <td><?php echo $telat > 0 ? '<span class="pz-status telat">Telat ' . $telat . ' hari</span>' : badge_status($r); ?></td>
                            <td data-order="<?php echo denda_dari_tempo($tempo); ?>"><?php echo $telat > 0 ? format_rupiah(denda_dari_tempo($tempo)) : '<span class="text-muted">-</span>'; ?></td>
                            <td class="text-nowrap">
                                <a href="bukti.php?no=<?php echo (int) $r['no_pinjam']; ?>" class="btn btn-sm btn-outline-primary" title="Cetak bukti peminjaman"><i class="fas fa-receipt"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Terima pengembalian buku ini?');">
                                    <button type="submit" name="kembali" value="<?php echo (int) $r['no_pinjam']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-undo mr-1"></i> Kembalikan
                                    </button>
                                </form>
                                <?php if ($boleh_panjang) { ?>
                                <form method="post" action="perpanjang.php" class="d-inline">
                                    <input type="hidden" name="no_pinjam" value="<?php echo (int) $r['no_pinjam']; ?>">
                                    <input type="hidden" name="kembali_ke" value="kembali.php">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Tambah <?php echo masa_pinjam_hari(); ?> hari">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                </form>
                                <?php } ?>
                            </td>
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
