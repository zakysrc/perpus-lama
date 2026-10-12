<?php
/* =========================================================
   TANDAI STATUS DENDA - PERPUS ZAKY
   ---------------------------------------------------------
   Admin menandai denda sebuah peminjaman sudah dibayar atau
   belum. Hanya berlaku untuk peminjaman yang sudah dikembalikan
   dan memiliki denda > 0.
========================================================= */

include "inc/admin_only.php";
include "koneksi.php";

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: riwayat_kembali.php");
    exit;
}

$no_pinjam = (int) ($_POST['no_pinjam'] ?? 0);
$status    = (int) ($_POST['status'] ?? 0) === 1 ? 1 : 0;

if ($no_pinjam > 0) {

    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE tbpinjam
            SET denda_dibayar = ?
          WHERE no_pinjam = ?
            AND status = 'kembali'
            AND denda > 0"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $status, $no_pinjam);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) === 1) {
            catat_log('denda', "#$no_pinjam ditandai " . ($status ? 'lunas' : 'belum lunas'));
        }
        mysqli_stmt_close($stmt);
    }
}

header("Location: riwayat_kembali.php");
exit;
