<?php
/* =========================================================
   BUKTI TRANSAKSI (admin) - struk siap cetak
   bukti.php?no=NO_PINJAM
   - Status pinjam  : bukti peminjaman + tanggal jatuh tempo
   - Status kembali : bukti pengembalian + denda & status bayar
   Ukuran 80 mm (printer struk) dan tetap rapi di kertas A4.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

$no = (int) ($_GET['no'] ?? 0);
$q = mysqli_query(
    $koneksi,
    "SELECT p.*, a.nama, b.judul, b.penulis, b.isbn
       FROM tbpinjam p
       JOIN tbanggota a ON a.id_ang = p.id_ang
       JOIN tbbuku b ON b.kode_buku = p.kode_buku
      WHERE p.no_pinjam = $no AND p.status IN ('pinjam','kembali')"
);
$t = $q ? mysqli_fetch_assoc($q) : null;

if (!$t) {
    flash_set('warning', 'Transaksi tidak ditemukan atau belum diserahkan.');
    header("Location: kembali.php");
    exit;
}

$kembali = strtolower($t['status']) === 'kembali';
$tempo   = jatuh_tempo_baris($t);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bukti #<?php echo (int) $t['no_pinjam']; ?> | Perpus Zaky</title>
<link rel="stylesheet" href="/perpuszaky/assets/theme.css?v=5.0">
<style>
    body { background: var(--pz-paper); margin: 0; padding: 24px; font-family: var(--pz-font-body); color: var(--pz-ink); }
    .struk { width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 16px 18px; border-top: 4px solid var(--pz-navy-800); box-shadow: var(--pz-shadow); }
    .struk h1 { font-size: 16px; margin: 0; text-align: center; color: var(--pz-navy-900); }
    .struk .sub { text-align: center; font-size: 12px; color: var(--pz-muted); margin-bottom: 10px; }
    .struk .jenis { text-align: center; font-weight: 700; letter-spacing: .05em; border: 1px dashed var(--pz-navy-300); padding: 4px; margin-bottom: 10px; font-size: 13px; }
    .struk table { width: 100%; font-size: 12.5px; border-collapse: collapse; }
    .struk td { padding: 3px 0; vertical-align: top; }
    .struk td:first-child { color: var(--pz-muted); width: 42%; }
    .struk hr { border: 0; border-top: 1px dashed var(--pz-line); margin: 8px 0; }
    .struk .besar { font-size: 15px; font-weight: 700; }
    .struk .kaki { text-align: center; font-size: 11px; color: var(--pz-muted); margin-top: 10px; }
    .aksi { text-align: center; margin-top: 16px; }
    .aksi button, .aksi a { font: inherit; padding: 8px 16px; border-radius: 6px; border: 1px solid var(--pz-navy-700); background: var(--pz-navy-700); color: #fff; cursor: pointer; text-decoration: none; margin: 0 4px; }
    .aksi a { background: #fff; color: var(--pz-navy-700); }
    @media print {
        body { background: #fff; padding: 0; }
        .struk { box-shadow: none; margin: 0; }
        .aksi { display: none; }
        @page { margin: 6mm; }
    }
</style>
</head>
<body>
<div class="struk">
    <h1><?php echo e(nama_perpustakaan()); ?></h1>
    <div class="sub">Sistem Informasi Perpustakaan</div>
    <div class="jenis"><?php echo $kembali ? 'BUKTI PENGEMBALIAN' : 'BUKTI PEMINJAMAN'; ?></div>

    <table>
        <tr><td>No. transaksi</td><td>#<?php echo (int) $t['no_pinjam']; ?></td></tr>
        <tr><td>Anggota</td><td><?php echo e($t['nama']); ?><br><?php echo sprintf('AGT-%04d', $t['id_ang']); ?></td></tr>
    </table>
    <hr>
    <table>
        <tr><td>Buku</td><td><?php echo e($t['judul']); ?></td></tr>
        <tr><td>Penulis</td><td><?php echo e($t['penulis']); ?></td></tr>
        <tr><td>Jumlah</td><td><?php echo (int) $t['jml_pinjam']; ?> buku</td></tr>
    </table>
    <hr>
    <table>
        <tr><td>Tanggal pinjam</td><td><?php echo tanggal_indo($t['tgl_pinjam']); ?></td></tr>
        <tr><td>Jatuh tempo</td><td class="<?php echo $kembali ? '' : 'besar'; ?>"><?php echo tanggal_indo($tempo); ?></td></tr>
        <?php if ((int) $t['perpanjang'] > 0) { ?><tr><td>Diperpanjang</td><td><?php echo (int) $t['perpanjang']; ?> kali</td></tr><?php } ?>
        <?php if ($kembali) { ?>
            <tr><td>Tanggal kembali</td><td><?php echo tanggal_indo($t['tgl_kembali']); ?></td></tr>
            <tr><td>Denda</td><td class="besar"><?php echo format_rupiah($t['denda']); ?></td></tr>
            <?php if ((int) $t['denda'] > 0) { ?>
                <tr><td>Status denda</td><td><?php echo (int) $t['denda_dibayar'] ? 'LUNAS' : 'BELUM LUNAS'; ?></td></tr>
            <?php } ?>
        <?php } ?>
    </table>
    <hr>
    <div class="kaki">
        <?php if (!$kembali) { ?>Denda keterlambatan <?php echo format_rupiah(denda_per_hari()); ?>/hari.<br><?php } ?>
        Dicetak <?php echo date('d-m-Y H:i'); ?> WIB oleh <?php echo e($_SESSION['username'] ?? '-'); ?>
    </div>
</div>
<div class="aksi">
    <button type="button" onclick="window.print()">Cetak</button>
    <a href="javascript:history.back()">Kembali</a>
</div>
</body>
</html>
