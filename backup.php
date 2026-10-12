<?php
/* =========================================================
   BACKUP DATABASE (khusus admin)
   ---------------------------------------------------------
   Mengunduh seluruh isi database sebagai file .sql:
   struktur tabel, semua data, dan trigger (stok & denda).
   File hasil bisa diimpor ulang lewat phpMyAdmin ke database
   kosong untuk memulihkan data.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

function sql_nilai($koneksi, $v)
{
    if ($v === null) {
        return 'NULL';
    }
    return "'" . mysqli_real_escape_string($koneksi, (string) $v) . "'";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    @set_time_limit(120);

    $db = mysqli_fetch_row(mysqli_query($koneksi, "SELECT DATABASE()"))[0];

    $tabel = [];
    $q = mysqli_query($koneksi, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($r = mysqli_fetch_row($q)) {
        $tabel[] = $r[0];
    }

    catat_log('backup', 'Backup database (' . count($tabel) . ' tabel)');

    /* Buang buffer halaman supaya file tidak tercampur HTML */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $nama_file = 'backup_' . $db . '_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama_file . '"');

    echo "-- Backup database `$db` - Perpus Zaky\n";
    echo "-- Dibuat: " . date('Y-m-d H:i:s') . " oleh " . ($_SESSION['username'] ?? '-') . "\n";
    echo "-- Cara memulihkan: buat database kosong, lalu impor file ini.\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach ($tabel as $t) {
        $buat = mysqli_fetch_row(mysqli_query($koneksi, "SHOW CREATE TABLE `$t`"))[1];
        echo "-- ------------------------------------------------\n-- Tabel `$t`\n-- ------------------------------------------------\n";
        echo "DROP TABLE IF EXISTS `$t`;\n$buat;\n\n";

        $res = mysqli_query($koneksi, "SELECT * FROM `$t`", MYSQLI_USE_RESULT);
        $baris = [];
        $kolom = null;

        while ($row = mysqli_fetch_assoc($res)) {
            if ($kolom === null) {
                $kolom = '`' . implode('`, `', array_keys($row)) . '`';
            }
            $baris[] = '(' . implode(', ', array_map(fn($v) => sql_nilai($koneksi, $v), $row)) . ')';

            if (count($baris) >= 200) {
                echo "INSERT INTO `$t` ($kolom) VALUES\n" . implode(",\n", $baris) . ";\n";
                $baris = [];
            }
        }
        mysqli_free_result($res);

        if ($baris) {
            echo "INSERT INTO `$t` ($kolom) VALUES\n" . implode(",\n", $baris) . ";\n";
        }
        echo "\n";
    }

    /* Trigger: logika stok & denda */
    $q = mysqli_query($koneksi, "SHOW TRIGGERS");
    $trg = [];
    while ($r = mysqli_fetch_assoc($q)) {
        $trg[] = $r['Trigger'];
    }
    foreach ($trg as $nama) {
        $def = mysqli_fetch_assoc(mysqli_query($koneksi, "SHOW CREATE TRIGGER `$nama`"));
        $sql = $def['SQL Original Statement'];
        /* Hapus DEFINER supaya bisa diimpor oleh user MySQL lain */
        $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $sql);
        echo "DROP TRIGGER IF EXISTS `$nama`;\nDELIMITER $$\n$sql$$\nDELIMITER ;\n\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

/* Ringkasan isi database untuk ditampilkan */
$ringkas = [];
$q = mysqli_query($koneksi, "SHOW TABLE STATUS");
while ($r = mysqli_fetch_assoc($q)) {
    if (($r['Comment'] ?? '') === 'VIEW') {
        continue;
    }
    $n = (int) mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM `{$r['Name']}`"))[0];
    $ringkas[] = ['nama' => $r['Name'], 'baris' => $n, 'ukuran' => (int) $r['Data_length'] + (int) $r['Index_length']];
}

$terakhir = null;
$q = @mysqli_query($koneksi, "SELECT waktu, username FROM tblog WHERE aksi = 'backup' ORDER BY id DESC LIMIT 1");
if ($q) {
    $terakhir = mysqli_fetch_assoc($q);
}

$page_title = "Backup database";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Backup database</h1>
            <p>Unduh salinan lengkap data perpustakaan. Simpan di flashdisk atau cloud secara berkala.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card card-primary card-outline h-100">
                <div class="card-header"><h3 class="card-title">Buat backup</h3></div>
                <div class="card-body">
                    <p>File <code>.sql</code> berisi struktur tabel, seluruh data, dan trigger stok &amp; denda.</p>
                    <p class="mb-0 text-muted">
                        Backup terakhir:
                        <strong><?php echo $terakhir ? date('d-m-Y H:i', strtotime($terakhir['waktu'])) . ' oleh ' . e($terakhir['username']) : 'belum pernah'; ?></strong>
                    </p>
                </div>
                <div class="card-footer bg-white">
                    <form method="post">
                        <button type="submit" class="btn btn-gold btn-block"><i class="fas fa-download mr-1"></i> Unduh backup sekarang</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Isi database</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Tabel</th><th class="text-right">Baris</th><th class="text-right">Ukuran</th></tr></thead>
                        <tbody>
                        <?php foreach ($ringkas as $r) { ?>
                            <tr>
                                <td><code><?php echo e($r['nama']); ?></code></td>
                                <td class="text-right"><?php echo number_format($r['baris'], 0, ',', '.'); ?></td>
                                <td class="text-right text-muted"><?php echo number_format($r['ukuran'] / 1024, 0, ',', '.'); ?> KB</td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-muted small">
                    Memulihkan: buat database kosong di phpMyAdmin, lalu menu Import dan pilih file backup.
                </div>
            </div>
        </div>
    </div>

</div>
</section>

<?php include "inc/footer.php"; ?>
