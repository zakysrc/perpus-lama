<?php
/* TAMBAH BUKU - form & validasi ada di inc/form_buku.php dan inc/simpan_buku.php */
include "inc/admin_only.php";
include_once "koneksi.php";
require_once "inc/simpan_buku.php";

$mode  = 'tambah';
$buku  = [];
$error = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = simpan_buku($koneksi, 'tambah');
}

$page_title = "Tambah buku";
include "inc/header.php";
include "inc/form_buku.php";
include "inc/footer.php";
