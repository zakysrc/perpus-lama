<?php
/* =========================================================
   PENGAJUAN PEMINJAMAN (khusus admin)
   ---------------------------------------------------------
   Anggota mengajukan pinjaman dari akunnya. Stok BELUM
   berkurang sampai admin menekan "Serahkan buku" saat anggota
   datang ke meja sirkulasi. Pada saat itu trigger database
   mengisi tanggal pinjam, jatuh tempo, dan mengurangi stok.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $no   = (int) ($_POST['no_pinjam'] ?? 0);
    $aksi = (string) ($_POST['aksi'] ?? '');

    mysqli_begin_transaction($koneksi);

    $q = mysqli_query(
        $koneksi,
        "SELECT p.*, a.nama, b.judul
           FROM tbpinjam p
           JOIN tbanggota a ON a.id_ang = p.id_ang
           JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.no_pinjam = $no AND p.status = 'diajukan'
          FOR UPDATE"
    );
    $aj = $q ? mysqli_fetch_assoc($q) : null;

    if (!$aj) {
        mysqli_rollback($koneksi);
        flash_set('warning', 'Pengajuan sudah diproses atau tidak ditemukan.');

    } elseif ($aksi === 'setujui') {

        $alasan = cek_boleh_pinjam($koneksi, $aj['id_ang'], $aj['kode_buku'], $aj['jml_pinjam'], $no);

        if ($alasan !== null) {
            mysqli_rollback($koneksi);
            flash_set('danger', 'Tidak bisa diserahkan: ' . $alasan);
        } else {
            /* Trigger: isi tgl_pinjam & jatuh tempo, kurangi stok */
            $ok = @mysqli_query($koneksi, "UPDATE tbpinjam SET status = 'pinjam' WHERE no_pinjam = $no");

            if ($ok) {
                mysqli_commit($koneksi);
                catat_log('setujui_pinjam', "#$no {$aj['nama']} - \"{$aj['judul']}\" x{$aj['jml_pinjam']}");
                flash_set('success', "Buku \"{$aj['judul']}\" diserahkan ke {$aj['nama']}. Jatuh tempo "
                    . date('d-m-Y', strtotime('+' . masa_pinjam_hari() . ' days')) . '.');
            } else {
                mysqli_rollback($koneksi);
                flash_set('danger', 'Gagal: ' . mysqli_error($koneksi));
            }
        }

    } elseif ($aksi === 'tolak') {

        $catatan = mb_substr(trim((string) ($_POST['catatan'] ?? '')), 0, 150);
        if ($catatan === '') {
            $catatan = 'Ditolak petugas';
        }

        $stmt = mysqli_prepare($koneksi, "UPDATE tbpinjam SET status = 'ditolak', catatan = ? WHERE no_pinjam = ?");
        mysqli_stmt_bind_param($stmt, 'si', $catatan, $no);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($koneksi);

        catat_log('tolak_pinjam', "#$no {$aj['nama']} - \"{$aj['judul']}\": $catatan");
        flash_set('info', "Pengajuan {$aj['nama']} untuk \"{$aj['judul']}\" ditolak.");

    } else {
        mysqli_rollback($koneksi);
    }

    header("Location: pengajuan.php");
    exit;
}


/* Daftar pengajuan yang menunggu */
$daftar = [];
$q = mysqli_query(
    $koneksi,
    "SELECT p.no_pinjam, p.jml_pinjam, p.tgl_ajuan, p.id_ang, p.kode_buku,
            a.nama, a.masa_berlaku, b.judul, b.jml_buku
       FROM tbpinjam p
       JOIN tbanggota a ON a.id_ang = p.id_ang
       JOIN tbbuku b ON b.kode_buku = p.kode_buku
      WHERE p.status = 'diajukan'
      ORDER BY p.tgl_ajuan ASC, p.no_pinjam ASC"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $daftar[] = $r;
}

/* 10 keputusan terakhir untuk konteks */
$terakhir = [];
$q = mysqli_query(
    $koneksi,
    "SELECT p.no_pinjam, p.status, p.catatan, p.tgl_ajuan, p.tgl_pinjam, a.nama, b.judul
       FROM tbpinjam p
       JOIN tbanggota a ON a.id_ang = p.id_ang
       JOIN tbbuku b ON b.kode_buku = p.kode_buku
      WHERE p.tgl_ajuan IS NOT NULL AND p.status <> 'diajukan'
      ORDER BY p.tgl_ajuan DESC
      LIMIT 10"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $terakhir[] = $r;
}

$page_title = "Pengajuan";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Pengajuan peminjaman</h1>
            <p>Serahkan buku saat anggota datang ke meja sirkulasi. Stok baru berkurang ketika buku diserahkan.</p>
        </div>
        <a href="pinjam.php" class="btn btn-outline-primary"><i class="fas fa-plus mr-1"></i> Peminjaman langsung</a>
    </div>

    <?php echo flash_tampil(); ?>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">Menunggu persetujuan (<?php echo count($daftar); ?>)</h3>
        </div>
        <div class="card-body p-0">
            <?php if (!$daftar) { ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    Tidak ada pengajuan. Pengajuan baru dari anggota akan muncul di sini.
                </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Diajukan</th><th>Anggota</th><th>Buku</th><th class="text-center">Jumlah</th><th class="text-center">Stok</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftar as $d) {
                        $alasan = cek_boleh_pinjam($koneksi, $d['id_ang'], $d['kode_buku'], $d['jml_pinjam'], $d['no_pinjam']);
                        if ($alasan === null && (int) $d['jml_buku'] < (int) $d['jml_pinjam']) {
                            $alasan = 'Stok tidak mencukupi.';
                        }
                    ?>
                        <tr>
                            <td class="text-nowrap"><?php echo $d['tgl_ajuan'] ? date('d-m-Y H:i', strtotime($d['tgl_ajuan'])) : '-'; ?></td>
                            <td>
                                <strong><?php echo e($d['nama']); ?></strong><br>
                                <small class="text-muted"><?php echo sprintf('AGT-%04d', $d['id_ang']); ?></small>
                            </td>
                            <td><?php echo e($d['judul']); ?></td>
                            <td class="text-center"><?php echo (int) $d['jml_pinjam']; ?></td>
                            <td class="text-center"><?php echo (int) $d['jml_buku']; ?></td>
                            <td class="text-right text-nowrap">
                                <?php if ($alasan !== null) { ?>
                                    <div class="small text-danger mb-1"><?php echo e($alasan); ?></div>
                                <?php } ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="no_pinjam" value="<?php echo (int) $d['no_pinjam']; ?>">
                                    <button type="submit" name="aksi" value="setujui" class="btn btn-sm btn-primary" <?php echo $alasan !== null ? 'disabled' : ''; ?>>
                                        <i class="fas fa-hand-holding mr-1"></i> Serahkan buku
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="collapse" data-target="#tolak<?php echo (int) $d['no_pinjam']; ?>">Tolak</button>
                                <form method="post" id="tolak<?php echo (int) $d['no_pinjam']; ?>" class="collapse mt-2">
                                    <input type="hidden" name="no_pinjam" value="<?php echo (int) $d['no_pinjam']; ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="catatan" class="form-control" maxlength="150" placeholder="Alasan (opsional)">
                                        <div class="input-group-append">
                                            <button type="submit" name="aksi" value="tolak" class="btn btn-danger">Tolak pengajuan</button>
                                        </div>
                                    </div>
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

    <?php if ($terakhir) { ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Keputusan terakhir</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Diajukan</th><th>Anggota</th><th>Buku</th><th>Hasil</th><th>Catatan</th></tr></thead>
                    <tbody>
                    <?php foreach ($terakhir as $t) { ?>
                        <tr>
                            <td class="text-nowrap"><?php echo date('d-m-Y H:i', strtotime($t['tgl_ajuan'])); ?></td>
                            <td><?php echo e($t['nama']); ?></td>
                            <td><?php echo e($t['judul']); ?></td>
                            <td><?php echo badge_status($t); ?></td>
                            <td class="text-muted"><?php echo e($t['catatan'] ?? ''); ?></td>
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
