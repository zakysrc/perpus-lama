<?php
/* =========================================================
   HAPUS BUKU - PERPUS ZAKY
   ---------------------------------------------------------
   - Hanya menerima POST (bukan link GET) dan dilindungi token
     CSRF otomatis dari inc/security.php.
   - Tidak boleh dihapus jika masih dipinjam.
   - Tidak boleh dihapus jika sudah punya riwayat peminjaman,
     supaya laporan lama tidak ikut hilang.
========================================================= */

include "inc/admin_only.php";
include "koneksi.php";


function selesai($pesan, $tujuan)
{
    echo "<script>
            alert(" . json_encode($pesan, JSON_UNESCAPED_UNICODE) . ");
            window.location=" . json_encode($tujuan) . ";
          </script>";
    exit;
}


/* Hanya boleh lewat form POST */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: buku.php");
    exit;
}

$kode_buku = (int) ($_POST['kode_buku'] ?? 0);

if ($kode_buku <= 0) {
    selesai('Kode buku tidak valid!', 'buku.php');
}


/* Pastikan buku ada */
$cek = mysqli_query(
    $koneksi,
    "SELECT judul, gambar FROM tbbuku WHERE kode_buku = $kode_buku LIMIT 1"
);

if (!$cek || mysqli_num_rows($cek) == 0) {
    selesai('Data buku tidak ditemukan!', 'buku.php');
}

$buku_hapus = mysqli_fetch_assoc($cek);


/* Hitung pinjaman aktif dan seluruh riwayat buku ini */
$hitung = mysqli_query(
    $koneksi,
    "SELECT
        COALESCE(SUM(status = 'pinjam'), 0) AS aktif,
        COUNT(*) AS total
     FROM tbpinjam
     WHERE kode_buku = $kode_buku"
);

if (!$hitung) {
    selesai('Gagal memeriksa status peminjaman buku!', 'buku.php');
}

$h = mysqli_fetch_assoc($hitung);

if ((int) $h['aktif'] > 0) {
    selesai(
        'Buku tidak dapat dihapus karena masih sedang dipinjam.',
        'buku.php'
    );
}

if ((int) $h['total'] > 0) {
    selesai(
        'Buku tidak dapat dihapus karena sudah memiliki ' . (int) $h['total'] .
        ' riwayat peminjaman. Menghapusnya akan menghilangkan data laporan. ' .
        'Jika buku tidak dipakai lagi, ubah stoknya menjadi 0.',
        'buku.php'
    );
}


/* Aman dihapus: belum pernah dipinjam sama sekali */
$hapus = mysqli_query(
    $koneksi,
    "DELETE FROM tbbuku WHERE kode_buku = $kode_buku"
);

if ($hapus && mysqli_affected_rows($koneksi) === 1) {
    hapus_file_sampul($buku_hapus['gambar'] ?? '');
    catat_log('hapus_buku', "Kode buku #$kode_buku");
    selesai('Data buku berhasil dihapus.', 'buku.php');
}

selesai('Data buku gagal dihapus!', 'buku.php');
