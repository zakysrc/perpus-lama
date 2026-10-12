<?php
/* =========================================================
   HAPUS ANGGOTA - PERPUS ZAKY
   ---------------------------------------------------------
   - Hanya menerima POST dan dilindungi token CSRF otomatis.
   - Tidak boleh dihapus jika masih punya pinjaman aktif.
   - Tidak boleh dihapus jika sudah punya riwayat peminjaman,
     supaya laporan lama tidak ikut hilang.
   - Saat dihapus: akun login (tbuser) ikut dihapus agar tidak
     ada akun yatim, dan file foto ikut dibersihkan.
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
    header("Location: anggota.php");
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    selesai('ID anggota tidak valid!', 'anggota.php');
}


/* Pastikan anggota ada */
$cek = mysqli_query(
    $koneksi,
    "SELECT id_ang, foto FROM tbanggota WHERE id_ang = $id LIMIT 1"
);

if (!$cek || mysqli_num_rows($cek) == 0) {
    selesai('Data anggota tidak ditemukan!', 'anggota.php');
}

$anggota = mysqli_fetch_assoc($cek);


/* Hitung pinjaman aktif dan seluruh riwayat anggota ini */
$hitung = mysqli_query(
    $koneksi,
    "SELECT
        COALESCE(SUM(status = 'pinjam'), 0) AS aktif,
        COUNT(*) AS total
     FROM tbpinjam
     WHERE id_ang = $id"
);

if (!$hitung) {
    selesai('Gagal memeriksa status peminjaman anggota!', 'anggota.php');
}

$h = mysqli_fetch_assoc($hitung);

if ((int) $h['aktif'] > 0) {
    selesai(
        'Anggota tidak dapat dihapus karena masih memiliki peminjaman aktif.',
        'anggota.php'
    );
}

if ((int) $h['total'] > 0) {
    selesai(
        'Anggota tidak dapat dihapus karena sudah memiliki ' . (int) $h['total'] .
        ' riwayat peminjaman. Menghapusnya akan menghilangkan data laporan. ' .
        'Jika sudah tidak aktif, ubah masa berlaku keanggotaannya.',
        'anggota.php'
    );
}


/* Hapus anggota + akun login-nya dalam satu transaksi */
mysqli_begin_transaction($koneksi);

$ok_akun = mysqli_query(
    $koneksi,
    "DELETE FROM tbuser WHERE id_ang = $id AND status = 'user'"
);

$ok_anggota = mysqli_query(
    $koneksi,
    "DELETE FROM tbanggota WHERE id_ang = $id"
);

if (!$ok_akun || !$ok_anggota || mysqli_affected_rows($koneksi) !== 1) {

    mysqli_rollback($koneksi);

    selesai('Data anggota gagal dihapus!', 'anggota.php');
}

mysqli_commit($koneksi);

catat_log('hapus_anggota', "Anggota #$id");


/* Bersihkan file foto (hanya jika tidak dipakai anggota lain) */
$foto = basename(trim((string) ($anggota['foto'] ?? '')));

if ($foto !== '') {

    $foto_sql = mysqli_real_escape_string($koneksi, $foto);

    $masih = mysqli_query(
        $koneksi,
        "SELECT COUNT(*) AS n FROM tbanggota WHERE foto = '$foto_sql'"
    );

    $dipakai = $masih ? (int) mysqli_fetch_assoc($masih)['n'] : 1;

    $path = __DIR__ . '/assets/img/' . $foto;

    if ($dipakai === 0 && is_file($path)) {
        @unlink($path);
    }
}

selesai('Data anggota dan akun loginnya berhasil dihapus.', 'anggota.php');
