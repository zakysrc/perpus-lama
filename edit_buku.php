<?php
/* UBAH BUKU - form & validasi ada di inc/form_buku.php dan inc/simpan_buku.php */
include "inc/admin_only.php";
include_once "koneksi.php";
require_once "inc/simpan_buku.php";

$kode = (int) ($_GET['kode_buku'] ?? $_GET['id'] ?? 0);
$q    = mysqli_query($koneksi, "SELECT * FROM tbbuku WHERE kode_buku = $kode LIMIT 1");
$buku = $q ? mysqli_fetch_assoc($q) : null;

if (!$buku) {
    flash_set('warning', 'Buku tidak ditemukan.');
    header("Location: buku.php");
    exit;
}

$mode  = 'edit';
$error = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = simpan_buku($koneksi, 'edit', $buku);
}

$page_title = "Ubah buku";
include "inc/header.php";
include "inc/form_buku.php";
include "inc/footer.php";
