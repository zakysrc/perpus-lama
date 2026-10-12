<?php
/* =========================================================
   PEMINJAMAN TERLAMBAT (admin)
   Semua peminjaman aktif yang sudah melewati tanggal jatuh
   tempo, lengkap dengan perkiraan denda dan tombol untuk
   mengirim pengingat lewat WhatsApp.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

$data = [];
$q = mysqli_query(
    $koneksi,
    "SELECT p.*, a.nama, a.telp, b.judul
       FROM tbpinjam p
       JOIN tbanggota a ON a.id_ang = p.id_ang
       JOIN tbbuku b ON b.kode_buku = p.kode_buku
      WHERE p.status = 'pinjam' AND " . sql_terlambat('p') . "
      ORDER BY " . sql_jatuh_tempo('p') . " ASC"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $r['tempo'] = jatuh_tempo_baris($r);
    $r['telat'] = hari_telat_dari_tempo($r['tempo']);
    $r['est']   = denda_dari_tempo($r['tempo']);
    $data[] = $r;
}

$total_denda = array_sum(array_column($data, 'est'));

/* Nomor 08xx -> 628xx untuk tautan wa.me */
function nomor_wa($telp)
{
    $n = preg_replace('/\D/', '', (string) $telp);
    if ($n === '') {
        return '';
    }
    if (strpos($n, '0') === 0) {
        $n = '62' . substr($n, 1);
    }
    return $n;
}

$page_title = "Terlambat";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Peminjaman terlambat</h1>
            <p>Denda <?php echo format_rupiah(denda_per_hari()); ?> per hari dihitung sejak tanggal jatuh tempo.</p>
        </div>
        <a href="kembali.php" class="btn btn-outline-primary"><i class="fas fa-undo mr-1"></i> Ke pengembalian</a>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3"><div class="pz-stat pz-stat-navy"><div class="pz-stat-value"><?php echo count($data); ?></div><div class="pz-stat-label">Peminjaman terlambat</div></div></div>
        <div class="col-md-6 mb-3"><div class="pz-stat pz-stat-navy"><div class="pz-stat-value"><?php echo format_rupiah($total_denda); ?></div><div class="pz-stat-label">Perkiraan denda per hari ini</div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Terlambat</th><th>Perkiraan denda</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$data) { ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-check-circle text-success mr-1"></i> Tidak ada peminjaman yang terlambat.</td></tr>
                    <?php } ?>
                    <?php foreach ($data as $r) {
                        $wa = nomor_wa($r['telp']);
                        $pesan = "Halo {$r['nama']}, buku \"{$r['judul']}\" yang dipinjam dari " . nama_perpustakaan()
                            . " sudah lewat jatuh tempo " . date('d-m-Y', strtotime($r['tempo']))
                            . " ({$r['telat']} hari). Perkiraan denda saat ini " . format_rupiah($r['est'])
                            . ". Mohon segera dikembalikan. Terima kasih.";
                    ?>
                        <tr>
                            <td><strong><?php echo e($r['nama']); ?></strong><br><small class="text-muted"><?php echo e($r['telp'] ?: 'Tanpa telepon'); ?></small></td>
                            <td><?php echo e($r['judul']); ?></td>
                            <td class="text-nowrap" data-order="<?php echo e($r['tempo']); ?>"><?php echo date('d-m-Y', strtotime($r['tempo'])); ?></td>
                            <td data-order="<?php echo $r['telat']; ?>"><span class="pz-status telat"><?php echo $r['telat']; ?> hari</span></td>
                            <td data-order="<?php echo $r['est']; ?>"><?php echo format_rupiah($r['est']); ?></td>
                            <td class="text-nowrap">
                                <?php if ($wa !== '') { ?>
                                    <a class="btn btn-sm btn-whatsapp" target="_blank" rel="noopener"
                                       href="https://wa.me/<?php echo $wa; ?>?text=<?php echo rawurlencode($pesan); ?>">
                                        <i class="fab fa-whatsapp mr-1"></i> Ingatkan
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted small">Isi nomor telepon anggota untuk mengirim pengingat</span>
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
