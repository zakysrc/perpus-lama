<?php
/* =========================================================
   MEJA SIRKULASI (admin) - pinjam & kembali dengan scan
   ---------------------------------------------------------
   1. Scan/ketik kode kartu anggota (AGT-0001)
   2. Semua urusan anggota itu tampil di satu layar:
      - pengajuan yang menunggu  -> Serahkan
      - buku yang sedang dipinjam -> Kembalikan / Perpanjang
      - scan kode buku atau ISBN  -> Pinjam langsung
   Scanner barcode USB bekerja seperti keyboard + Enter,
   jadi tidak perlu driver atau library tambahan.
   Semua aturan tetap lewat cek_boleh_pinjam() & trigger.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

$kartu  = trim((string) ($_REQUEST['kartu'] ?? ''));
$id_ang = id_dari_kode_anggota($kartu);

function kembali_ke_kartu($id_ang)
{
    header("Location: sirkulasi.php" . ($id_ang ? '?kartu=' . sprintf('AGT-%04d', $id_ang) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_ang > 0) {

    $aksi = (string) ($_POST['aksi'] ?? '');

    /* ---------- PINJAM LANGSUNG ---------- */
    if ($aksi === 'pinjam') {

        $kode_input = trim((string) ($_POST['buku'] ?? ''));
        $jumlah     = max(1, (int) ($_POST['jumlah'] ?? 1));

        /* Kode buku (angka pendek) atau ISBN */
        $isbn = preg_replace('/[^0-9Xx]/', '', $kode_input);
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT kode_buku, judul, jml_buku FROM tbbuku
              WHERE kode_buku = ? OR REPLACE(isbn, '-', '') = ? LIMIT 1"
        );
        $kode_num = ctype_digit($kode_input) && strlen($kode_input) <= 6 ? (int) $kode_input : 0;
        mysqli_stmt_bind_param($stmt, 'is', $kode_num, $isbn);
        mysqli_stmt_execute($stmt);
        $buku = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$buku) {
            flash_set('danger', "Buku dengan kode/ISBN \"$kode_input\" tidak ditemukan.");
            kembali_ke_kartu($id_ang);
        }

        $kode = (int) $buku['kode_buku'];

        mysqli_begin_transaction($koneksi);
        mysqli_query($koneksi, "SELECT jml_buku FROM tbbuku WHERE kode_buku = $kode FOR UPDATE");

        $alasan = cek_boleh_pinjam($koneksi, $id_ang, $kode, $jumlah);
        if ($alasan === null && (int) $buku['jml_buku'] < $jumlah) {
            $alasan = 'Stok tidak mencukupi (tersisa ' . (int) $buku['jml_buku'] . ').';
        }

        if ($alasan !== null) {
            mysqli_rollback($koneksi);
            flash_set('danger', $alasan);
            kembali_ke_kartu($id_ang);
        }

        $ok = @mysqli_query(
            $koneksi,
            "INSERT INTO tbpinjam (id_ang, kode_buku, tgl_pinjam, jml_pinjam, status, denda)
             VALUES ($id_ang, $kode, CURDATE(), $jumlah, 'pinjam', 0)"
        );

        if ($ok) {
            mysqli_commit($koneksi);
            selesaikan_reservasi($koneksi, $id_ang, $kode);
            catat_log('pinjam', "Meja sirkulasi: anggota #$id_ang - \"{$buku['judul']}\" x$jumlah");
            flash_set('success', "\"{$buku['judul']}\" dipinjam. Jatuh tempo " . date('d-m-Y', strtotime('+' . masa_pinjam_hari() . ' days')) . '.');
        } else {
            mysqli_rollback($koneksi);
            flash_set('danger', 'Gagal: ' . mysqli_error($koneksi));
        }
        kembali_ke_kartu($id_ang);
    }

    /* ---------- PERPANJANG KEANGGOTAAN (+3 tahun, sama dengan anggota baru) ---------- */
    if ($aksi === 'perpanjang_anggota') {
        mysqli_query(
            $koneksi,
            "UPDATE tbanggota
                SET masa_berlaku = DATE_ADD(GREATEST(COALESCE(masa_berlaku, CURDATE()), CURDATE()), INTERVAL 3 YEAR)
              WHERE id_ang = $id_ang"
        );
        $baru = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT masa_berlaku FROM tbanggota WHERE id_ang = $id_ang"))['masa_berlaku'] ?? null;
        catat_log('ubah_anggota', "Meja sirkulasi: keanggotaan #$id_ang diperpanjang s/d $baru");
        flash_set('success', 'Keanggotaan diperpanjang sampai ' . date('d-m-Y', strtotime($baru)) . '.');
        kembali_ke_kartu($id_ang);
    }

    $no = (int) ($_POST['no_pinjam'] ?? 0);

    /* Pastikan transaksi milik anggota ini */
    $q = mysqli_query(
        $koneksi,
        "SELECT p.*, b.judul FROM tbpinjam p JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.no_pinjam = $no AND p.id_ang = $id_ang"
    );
    $trx = $q ? mysqli_fetch_assoc($q) : null;

    /* ---------- KEMBALIKAN ---------- */
    if ($aksi === 'kembali' && $trx && strtolower($trx['status']) === 'pinjam') {
        mysqli_query($koneksi, "UPDATE tbpinjam SET status = 'kembali' WHERE no_pinjam = $no AND status = 'pinjam'");
        $denda = (int) (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT denda FROM tbpinjam WHERE no_pinjam = $no"))['denda'] ?? 0);
        catat_log('kembali', "Meja sirkulasi: #$no \"{$trx['judul']}\"" . ($denda ? ', denda ' . format_rupiah($denda) : ''));
        flash_set($denda ? 'warning' : 'success', "\"{$trx['judul']}\" dikembalikan." . ($denda ? ' Denda ' . format_rupiah($denda) . ' (belum lunas).' : ' Tanpa denda.'));
    }

    /* ---------- SERAHKAN PENGAJUAN ---------- */
    if ($aksi === 'serahkan' && $trx && strtolower($trx['status']) === 'diajukan') {
        mysqli_begin_transaction($koneksi);
        $alasan = cek_boleh_pinjam($koneksi, $id_ang, $trx['kode_buku'], $trx['jml_pinjam'], $no);
        if ($alasan === null && @mysqli_query($koneksi, "UPDATE tbpinjam SET status = 'pinjam' WHERE no_pinjam = $no")) {
            mysqli_commit($koneksi);
            catat_log('setujui_pinjam', "Meja sirkulasi: #$no \"{$trx['judul']}\"");
            flash_set('success', "\"{$trx['judul']}\" diserahkan.");
        } else {
            mysqli_rollback($koneksi);
            flash_set('danger', 'Tidak bisa diserahkan: ' . ($alasan ?? mysqli_error($koneksi)));
        }
    }

    /* ---------- LUNASI DENDA ---------- */
    if ($aksi === 'lunas' && $trx && (int) $trx['denda'] > 0 && (int) $trx['denda_dibayar'] === 0) {
        mysqli_query($koneksi, "UPDATE tbpinjam SET denda_dibayar = 1 WHERE no_pinjam = $no");
        catat_log('denda', "Meja sirkulasi: #$no ditandai lunas");
        flash_set('success', 'Denda ' . format_rupiah($trx['denda']) . ' ditandai lunas.');
    }

    kembali_ke_kartu($id_ang);
}


/* ---------------- DATA ANGGOTA ---------------- */
$anggota = null;
$aktif = $ajuan = $denda = [];

if ($id_ang > 0) {
    $q = mysqli_query($koneksi, "SELECT * FROM tbanggota WHERE id_ang = $id_ang");
    $anggota = $q ? mysqli_fetch_assoc($q) : null;
}

if ($anggota) {
    $q = mysqli_query(
        $koneksi,
        "SELECT p.*, b.judul FROM tbpinjam p JOIN tbbuku b ON b.kode_buku = p.kode_buku
          WHERE p.id_ang = $id_ang AND (p.status IN ('pinjam','diajukan')
                OR (p.status = 'kembali' AND p.denda > 0 AND p.denda_dibayar = 0))
          ORDER BY p.no_pinjam"
    );
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $st = strtolower($r['status']);
        if ($st === 'pinjam')        $aktif[] = $r;
        elseif ($st === 'diajukan')  $ajuan[] = $r;
        else                          $denda[] = $r;
    }
}

$total_denda = array_sum(array_map(fn($r) => (int) $r['denda'], $denda));
$masa_habis  = $anggota && !empty($anggota['masa_berlaku']) && $anggota['masa_berlaku'] < date('Y-m-d');

$page_title = "Meja sirkulasi";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Meja sirkulasi</h1>
            <p>Scan kartu anggota, lalu layani peminjaman, pengembalian, dan denda dari satu layar.</p>
        </div>
    </div>

    <?php echo flash_tampil(); ?>

    <form method="get" class="card card-body py-3">
        <div class="input-group input-group-lg">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-card"></i></span></div>
            <input type="text" name="kartu" class="form-control" placeholder="Scan atau ketik kode kartu, mis. AGT-0001"
                   value="<?php echo e($kartu); ?>" <?php echo $anggota ? '' : 'autofocus'; ?> autocomplete="off" aria-label="Kode kartu anggota">
            <div class="input-group-append"><button class="btn btn-primary" type="submit">Buka</button></div>
        </div>
    </form>

    <?php if ($kartu !== '' && !$anggota) { ?>
        <div class="alert alert-danger">Kartu "<?php echo e($kartu); ?>" tidak ditemukan.</div>
    <?php } ?>

    <?php if ($anggota) { ?>

    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted mb-1"><?php echo sprintf('AGT-%04d', $anggota['id_ang']); ?></p>
                    <h2 class="mb-2"><?php echo e($anggota['nama']); ?></h2>
                    <p class="mb-1">Berlaku s/d <?php echo $anggota['masa_berlaku'] ? date('d-m-Y', strtotime($anggota['masa_berlaku'])) : '-'; ?>
                        <?php echo $masa_habis ? '<span class="pz-status telat ml-1">Habis</span>' : ''; ?></p>
                    <p class="mb-3 text-muted"><?php echo e($anggota['telp'] ?: 'Tanpa telepon'); ?></p>
                    <div class="d-flex flex-wrap" style="gap:8px">
                        <span class="pz-status pinjam"><?php echo count($aktif); ?> dipinjam</span>
                        <span class="pz-status diajukan"><?php echo count($ajuan); ?> pengajuan</span>
                        <span class="pz-status <?php echo $total_denda ? 'telat' : 'kembali'; ?>">Denda <?php echo format_rupiah($total_denda); ?></span>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="kartu_anggota.php?id=<?php echo (int) $anggota['id_ang']; ?>" class="btn btn-sm btn-outline-primary">Kartu</a>
                    <a href="edit_anggota.php?id=<?php echo (int) $anggota['id_ang']; ?>" class="btn btn-sm btn-outline-primary">Ubah data</a>
                    <?php if ($masa_habis || ($anggota['masa_berlaku'] && $anggota['masa_berlaku'] <= date('Y-m-d', strtotime('+30 days')))) { ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Perpanjang keanggotaan 3 tahun?');">
                            <input type="hidden" name="kartu" value="<?php echo e($kartu); ?>">
                            <button type="submit" name="aksi" value="perpanjang_anggota" class="btn btn-sm btn-gold">Perpanjang keanggotaan</button>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-3">
            <form method="post" class="card card-primary card-outline h-100">
                <input type="hidden" name="kartu" value="<?php echo e($kartu); ?>">
                <div class="card-header"><h3 class="card-title">Pinjam buku</h3></div>
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="col-md-8 mb-2">
                            <label for="buku">Kode buku atau ISBN</label>
                            <input type="text" id="buku" name="buku" class="form-control form-control-lg" required autofocus autocomplete="off" placeholder="Scan barcode ISBN di sampul belakang">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="jumlah">Jumlah</label>
                            <input type="number" id="jumlah" name="jumlah" class="form-control form-control-lg" min="1" max="<?php echo maks_jml_per_pinjam(); ?>" value="1">
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="submit" name="aksi" value="pinjam" class="btn btn-gold btn-lg btn-block">Pinjam</button>
                        </div>
                    </div>
                    <small class="text-muted">Jatuh tempo otomatis <?php echo masa_pinjam_hari(); ?> hari. Aturan batas pinjam, keanggotaan, keterlambatan, dan antrean reservasi tetap diperiksa.</small>
                </div>
            </form>
        </div>
    </div>

    <?php
    function baris_aksi($r, $kartu, $tombol)
    {
        $h = '<form method="post" class="d-inline"><input type="hidden" name="kartu" value="' . e($kartu) . '">'
           . '<input type="hidden" name="no_pinjam" value="' . (int) $r['no_pinjam'] . '">';
        foreach ($tombol as [$aksi, $label, $kelas]) {
            $h .= '<button type="submit" name="aksi" value="' . $aksi . '" class="btn btn-sm ' . $kelas . ' ml-1">' . $label . '</button>';
        }
        return $h . '</form>';
    }
    ?>

    <?php if ($ajuan) { ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Pengajuan menunggu diserahkan</h3></div>
        <div class="card-body p-0"><table class="table mb-0"><tbody>
            <?php foreach ($ajuan as $r) { ?>
                <tr>
                    <td><strong><?php echo e($r['judul']); ?></strong> &times; <?php echo (int) $r['jml_pinjam']; ?></td>
                    <td class="text-muted">Diajukan <?php echo date('d-m-Y H:i', strtotime($r['tgl_ajuan'])); ?></td>
                    <td class="text-right"><?php echo baris_aksi($r, $kartu, [['serahkan', 'Serahkan buku', 'btn-primary']]); ?></td>
                </tr>
            <?php } ?>
        </tbody></table></div>
    </div>
    <?php } ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Sedang dipinjam (<?php echo count($aktif); ?>)</h3></div>
        <div class="card-body p-0">
            <?php if (!$aktif) { ?>
                <p class="text-muted text-center py-4 mb-0">Tidak ada buku yang sedang dipinjam.</p>
            <?php } else { ?>
            <table class="table mb-0"><tbody>
            <?php foreach ($aktif as $r) {
                $tempo = jatuh_tempo_baris($r);
                $telat = hari_telat_dari_tempo($tempo);
                $tombol = [['kembali', 'Kembalikan', 'btn-primary']];
            ?>
                <tr>
                    <td><strong><?php echo e($r['judul']); ?></strong> &times; <?php echo (int) $r['jml_pinjam']; ?></td>
                    <td class="text-nowrap">Jatuh tempo <?php echo date('d-m-Y', strtotime($tempo)); ?></td>
                    <td><?php echo $telat ? '<span class="pz-status telat">Telat ' . $telat . ' hari &middot; ' . format_rupiah(denda_dari_tempo($tempo)) . '</span>' : badge_status($r); ?></td>
                    <td class="text-right text-nowrap">
                        <a href="bukti.php?no=<?php echo (int) $r['no_pinjam']; ?>" class="btn btn-sm btn-outline-primary" title="Cetak bukti peminjaman"><i class="fas fa-receipt"></i></a>
                        <?php echo baris_aksi($r, $kartu, $tombol); ?>
                        <?php if (cek_boleh_perpanjang($r) === null) { ?>
                            <form method="post" action="perpanjang.php" class="d-inline">
                                <input type="hidden" name="no_pinjam" value="<?php echo (int) $r['no_pinjam']; ?>">
                                <input type="hidden" name="kembali_ke" value="sirkulasi.php">
                                <input type="hidden" name="kartu" value="<?php echo e($kartu); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary ml-1" title="Perpanjang"><i class="fas fa-calendar-plus"></i></button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody></table>
            <?php } ?>
        </div>
    </div>

    <?php if ($denda) { ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Denda belum lunas: <?php echo format_rupiah($total_denda); ?></h3></div>
        <div class="card-body p-0"><table class="table mb-0"><tbody>
            <?php foreach ($denda as $r) { ?>
                <tr>
                    <td><?php echo e($r['judul']); ?></td>
                    <td class="text-muted">Dikembalikan <?php echo date('d-m-Y', strtotime($r['tgl_kembali'])); ?></td>
                    <td><strong class="text-danger"><?php echo format_rupiah($r['denda']); ?></strong></td>
                    <td class="text-right text-nowrap">
                        <a href="bukti.php?no=<?php echo (int) $r['no_pinjam']; ?>" class="btn btn-sm btn-outline-primary" title="Cetak bukti"><i class="fas fa-receipt"></i></a>
                        <?php echo baris_aksi($r, $kartu, [['lunas', 'Terima pembayaran', 'btn-outline-primary']]); ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody></table></div>
    </div>
    <?php } ?>

    <?php } ?>

</div>
</section>

<?php include "inc/footer.php"; ?>
