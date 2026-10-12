<?php

/* =========================================================
   KONEKSI DATABASE - PERPUS ZAKY
   ---------------------------------------------------------
   Nilai bawaan cocok untuk XAMPP di komputer lokal.
   Untuk server/hosting, JANGAN ubah file ini. Salin
   config.local.example.php menjadi config.local.php lalu
   isi kredensial di sana (file itu tidak ikut ke GitHub).
========================================================= */

/* Zona waktu WIB. Tanpa baris ini PHP memakai zona bawaan XAMPP
   (Europe/Berlin), sehingga jam, sapaan pagi/siang/sore/malam, dan
   perhitungan "hari ini" meleset 5-6 jam. */
date_default_timezone_set('Asia/Jakarta');

/* Sudah terhubung? (file ini dipanggil lebih dari sekali) */
if (isset($koneksi) && $koneksi instanceof mysqli) {
    return;
}

$host     = "localhost";
$user     = "root";
$password = "";
$database = "dbperpuszaky";

if (is_file(__DIR__ . "/config.local.php")) {
    require __DIR__ . "/config.local.php";
}

/* Perilaku error konsisten di semua versi PHP:
   kegagalan query dicek manual dengan if (!$query) */
mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = @mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {

    error_log("Koneksi database gagal: " . mysqli_connect_error());

    http_response_code(500);

    die("Koneksi database gagal. Periksa pengaturan database.");
}

/* Pastikan karakter (huruf/emoji/aksara) tersimpan benar */
mysqli_set_charset($koneksi, "utf8mb4");

/* Samakan zona waktu MySQL dengan PHP, supaya NOW()/CURDATE()
   di database dan date() di PHP selalu menunjuk hari yang sama */
@mysqli_query($koneksi, "SET time_zone = '+07:00'");

/* Aturan bisnis (masa pinjam, denda, batas pinjam) */
require_once __DIR__ . "/inc/aturan.php";
