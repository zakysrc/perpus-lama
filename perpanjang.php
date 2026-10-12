<?php
/* =========================================================
   PERPANJANG PEMINJAMAN (POST) - KHUSUS ADMIN
   Perpanjangan dilakukan petugas (meja sirkulasi / halaman
   pengembalian), bukan oleh anggota sendiri.
   Jatuh tempo digeser sebanyak masa pinjam, maksimal sesuai
   pengaturan "maks_perpanjang", dan tidak boleh jika sudah
   terlambat (dendanya harus diselesaikan dulu).
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

/* Halaman tujuan setelah proses (hanya yang diizinkan) */
$tujuan_boleh = ['kembali.php', 'terlambat.php', 'sirkulasi.php'];
$tujuan = basename((string) ($_POST['kembali_ke'] ?? ''));
if (!in_array($tujuan, $tujuan_boleh, true)) {
    $tujuan = 'kembali.php';
}
if ($tujuan === 'sirkulasi.php' && id_dari_kode_anggota($_POST['kartu'] ?? '') > 0) {
    $tujuan .= '?kartu=' . sprintf('AGT-%04d', id_dari_kode_anggota($_POST['kartu']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $tujuan");
    exit;
}

$no_pinjam = (int) ($_POST['no_pinjam'] ?? 0);

mysqli_begin_transaction($koneksi);

$q = mysqli_query(
    $koneksi,
    "SELECT p.*, b.judul
       FROM tbpinjam p
       JOIN tbbuku b ON b.kode_buku = p.kode_buku
      WHERE p.no_pinjam = $no_pinjam
      FOR UPDATE"
);
$pinjam = $q ? mysqli_fetch_assoc($q) : null;

if (!$pinjam) {
    mysqli_rollback($koneksi);
    flash_set('danger', 'Data peminjaman tidak ditemukan.');
    header("Location: $tujuan");
    exit;
}

$alasan = cek_boleh_perpanjang($pinjam);

if ($alasan !== null) {
    mysqli_rollback($koneksi);
    flash_set('warning', $alasan);
    header("Location: $tujuan");
    exit;
}

$tempo_lama = jatuh_tempo_baris($pinjam);
$tempo_baru = date('Y-m-d', strtotime($tempo_lama . ' +' . masa_pinjam_hari() . ' days'));

$stmt = mysqli_prepare(
    $koneksi,
    "UPDATE tbpinjam
        SET tgl_jatuh_tempo = ?, perpanjang = perpanjang + 1
      WHERE no_pinjam = ? AND status = 'pinjam'"
);
mysqli_stmt_bind_param($stmt, 'si', $tempo_baru, $no_pinjam);
mysqli_stmt_execute($stmt);
$ok = mysqli_stmt_affected_rows($stmt) === 1;
mysqli_stmt_close($stmt);

if ($ok) {
    mysqli_commit($koneksi);
    catat_log('perpanjang', "#$no_pinjam \"{$pinjam['judul']}\" jatuh tempo $tempo_lama -> $tempo_baru");
    flash_set('success', 'Peminjaman "' . $pinjam['judul'] . '" diperpanjang. Jatuh tempo baru: ' . date('d-m-Y', strtotime($tempo_baru)) . '.');
} else {
    mysqli_rollback($koneksi);
    flash_set('danger', 'Perpanjangan gagal disimpan. Coba lagi.');
}

header("Location: $tujuan");
exit;
