<?php
/* =========================================================
   UNDUH LAPORAN CSV (admin)
   Memakai filter & query yang SAMA dengan laporan.php
   (inc/laporan_data.php). Uang diekspor sebagai angka supaya
   bisa dijumlah di Excel.
========================================================= */

require_once "inc/admin_only.php";
include_once "koneksi.php";
require_once "inc/laporan_data.php";

$jenis = strtolower(trim((string) ($_GET['jenis'] ?? '')));
if (!isset(LAPORAN_JENIS[$jenis]) || $jenis === 'ringkasan') {
    header('Location: laporan.php');
    exit;
}

$f    = laporan_filter($_GET);
$data = laporan_data($koneksi, $jenis, $f);
$judul = LAPORAN_JENIS[$jenis][0];

catat_log('laporan', "Unduh CSV $judul ({$f['label']})");

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="laporan_' . $jenis . '_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); /* BOM: huruf tampil benar di Excel */

/* Excel versi Indonesia memakai titik koma sebagai pemisah kolom */
$sep = ';';
fputcsv($out, [nama_perpustakaan()], $sep);
fputcsv($out, ["Laporan $judul"], $sep);
fputcsv($out, ['Periode', $jenis === 'terlambat' ? 'Per ' . tanggal_indo(date('Y-m-d')) : $f['label']], $sep);
fputcsv($out, ['Diunduh', date('d-m-Y H:i') . ' WIB'], $sep);
fputcsv($out, [], $sep);
fputcsv($out, $data['headers'], $sep);
foreach ($data['rows'] as $row) {
    fputcsv($out, $row, $sep);
}
fputcsv($out, [], $sep);
foreach ($data['total'] as $lbl => $nilai) {
    fputcsv($out, [$lbl, $nilai], $sep);
}
fclose($out);
exit;
