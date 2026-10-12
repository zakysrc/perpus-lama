<?php
require_once "inc/admin_only.php";
include "koneksi.php";

$page_title = "Dashboard Admin";

/* =========================================================
   FUNGSI TOTAL QUERY
========================================================= */

function total_query($koneksi, $sql, $field = 'total') {
    $q = mysqli_query($koneksi, $sql);

    if (!$q) {
        return 0;
    }

    $r = mysqli_fetch_assoc($q);

    return isset($r[$field]) ? $r[$field] : 0;
}


/* =========================================================
   DATA RINGKASAN
========================================================= */

$total_anggota = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbanggota"
);

$total_buku = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbbuku"
);

$total_pinjam = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE status='pinjam'"
);

$total_kembali = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE status='kembali'"
);

$total_denda = total_query(
    $koneksi,
    "SELECT COALESCE(SUM(denda),0) total
     FROM tbpinjam
     WHERE status='kembali'"
);

$total_stok = total_query(
    $koneksi,
    "SELECT COALESCE(SUM(jml_buku),0) total
     FROM tbbuku"
);


/* =========================================================
   MONITORING STOK
========================================================= */

$total_stok_habis = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbbuku
     WHERE jml_buku <= 0"
);

$total_stok_menipis = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbbuku
     WHERE jml_buku > 0
     AND jml_buku <= 2"
);


/* =========================================================
   PEMINJAMAN TERLAMBAT
========================================================= */

$total_terlambat = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE status='pinjam'
       AND " . sql_terlambat()
);

/* =========================================================
   RINGKASAN HARI INI & STATUS DATA
========================================================= */

$total_pinjam_hari_ini = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbpinjam WHERE DATE(tgl_pinjam)=CURDATE()"
);

$total_kembali_hari_ini = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbpinjam WHERE status='kembali' AND DATE(tgl_kembali)=CURDATE()"
);

$total_anggota_aktif = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbanggota WHERE masa_berlaku >= CURDATE()"
);

/* Layanan perpustakaan (tabel v4; 0 jika belum dimigrasi) */
$layanan = [];
foreach ([
    ['kunjungan.php', 'door-open', 'Kunjungan hari ini', "SELECT COUNT(*) total FROM tbkunjungan WHERE DATE(waktu) = CURDATE()"],
    ['pengajuan.php', 'inbox', 'Pengajuan menunggu', "SELECT COUNT(*) total FROM tbpinjam WHERE status = 'diajukan'"],
    ['reservasi.php', 'bookmark', 'Antrean reservasi', "SELECT COUNT(*) total FROM tbreservasi WHERE status = 'menunggu'"],
    ['usulan.php', 'lightbulb', 'Usulan buku baru', "SELECT COUNT(*) total FROM tbusulan WHERE status = 'diajukan'"],
] as $l) {
    $q_l = @mysqli_query($koneksi, $l[3]);
    $layanan[] = [$l[0], $l[1], $l[2], $q_l ? (int) mysqli_fetch_assoc($q_l)['total'] : 0];
}

$db_terhubung = @mysqli_ping($koneksi);

$insight_messages = [];

if ($total_stok_habis > 0) {
    $insight_messages[] = [
        'danger', 'box-open',
        $total_stok_habis . ' buku kehabisan stok.',
        'Periksa Data Buku dan lakukan pembaruan stok bila diperlukan.',
        'buku.php'
    ];
}

if ($total_stok_menipis > 0) {
    $insight_messages[] = [
        'warning', 'exclamation-triangle',
        $total_stok_menipis . ' buku memiliki stok menipis.',
        'Stok 1–2 buku terdeteksi oleh sistem.',
        'buku.php'
    ];
}

if ($total_terlambat > 0) {
    $insight_messages[] = [
        'warning', 'clock',
        $total_terlambat . ' peminjaman melewati jatuh tempo.',
        'Periksa daftar keterlambatan untuk proses pengembalian.',
        'terlambat.php'
    ];
}

if (empty($insight_messages)) {
    $insight_messages[] = [
        'success', 'check-circle',
        'Semua indikator utama dalam kondisi normal.',
        'Tidak ada stok habis, stok menipis, atau keterlambatan yang perlu ditindaklanjuti.',
        ''
    ];
}


/* =========================================================
   5 BUKU TERPOPULER
========================================================= */

$buku_populer = [];

$query_buku_populer = mysqli_query(
    $koneksi,
    "SELECT
        tbbuku.judul,
        COALESCE(SUM(tbpinjam.jml_pinjam),0) AS total_pinjam
     FROM tbbuku
     LEFT JOIN tbpinjam
        ON tbbuku.kode_buku = tbpinjam.kode_buku
     GROUP BY tbbuku.kode_buku, tbbuku.judul
     ORDER BY total_pinjam DESC, tbbuku.judul ASC
     LIMIT 5"
);

if ($query_buku_populer) {

    while ($row = mysqli_fetch_assoc($query_buku_populer)) {
        $buku_populer[] = $row;
    }
}


/* =========================================================
   5 ANGGOTA PALING AKTIF
========================================================= */

$anggota_aktif = [];

$query_anggota_aktif = mysqli_query(
    $koneksi,
    "SELECT
        tbanggota.nama,
        COUNT(tbpinjam.no_pinjam) AS total_pinjam
     FROM tbanggota
     LEFT JOIN tbpinjam
        ON tbanggota.id_ang = tbpinjam.id_ang
     GROUP BY tbanggota.id_ang, tbanggota.nama
     ORDER BY total_pinjam DESC, tbanggota.nama ASC
     LIMIT 5"
);

if ($query_anggota_aktif) {

    while ($row = mysqli_fetch_assoc($query_anggota_aktif)) {
        $anggota_aktif[] = $row;
    }
}


/* =========================================================
   GRAFIK PEMINJAMAN 6 BULAN TERAKHIR
========================================================= */

$label_bulan = [];
$data_peminjaman = [];
$map_peminjaman = [];

for ($i = 5; $i >= 0; $i--) {
    $tanggal_bulan = strtotime("-$i month");
    $bulan_sql = date("Y-m", $tanggal_bulan);
    $label_bulan[] = bulan_singkat(date("n", $tanggal_bulan)) . " " . date("Y", $tanggal_bulan);
    $map_peminjaman[$bulan_sql] = 0;
}

$awal_grafik = date('Y-m-01', strtotime('-5 months'));
$query_grafik = mysqli_query(
    $koneksi,
    "SELECT DATE_FORMAT(tgl_pinjam,'%Y-%m') AS periode, COUNT(*) AS jumlah
     FROM tbpinjam
     WHERE tgl_pinjam >= '$awal_grafik'
     GROUP BY DATE_FORMAT(tgl_pinjam,'%Y-%m')
     ORDER BY periode ASC"
);

if ($query_grafik) {
    while ($row = mysqli_fetch_assoc($query_grafik)) {
        if (isset($map_peminjaman[$row['periode']])) {
            $map_peminjaman[$row['periode']] = (int)$row['jumlah'];
        }
    }
}

$data_peminjaman = array_values($map_peminjaman);


/* =========================================================
   PEMINJAMAN TERBARU
========================================================= */

$peminjaman_terbaru = [];

$query_terbaru = mysqli_query(
    $koneksi,
    "SELECT
        tbpinjam.no_pinjam,
        tbpinjam.tgl_pinjam,
        tbpinjam.status,
        tbanggota.nama,
        tbbuku.judul
     FROM tbpinjam
     INNER JOIN tbanggota
        ON tbpinjam.id_ang = tbanggota.id_ang
     INNER JOIN tbbuku
        ON tbpinjam.kode_buku = tbbuku.kode_buku
     WHERE tbpinjam.status IN ('pinjam','kembali')
     ORDER BY tbpinjam.no_pinjam DESC
     LIMIT 5"
);

if ($query_terbaru) {

    while ($row = mysqli_fetch_assoc($query_terbaru)) {
        $peminjaman_terbaru[] = $row;
    }
}


include "inc/header.php";
?>


<!-- =======================================================
     HEADER DASHBOARD
======================================================= -->

<div class="content-header">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap">

            <div>
                <h1 class="m-0">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Dashboard Admin
                </h1>

                <p class="text-muted mb-0 mt-1">
                    <?php
                    echo '<span id="pzSapaan">' . sapaan_waktu() . '</span>, '
                        . htmlspecialchars($_SESSION['username'] ?? 'Administrator')
                        . '. Berikut pusat kendali perpustakaan hari ini.';
                    ?>
                </p>

                <div class="mt-2">
                    <span class="badge badge-light border mr-1"><i class="fas fa-calendar-day mr-1"></i><?php echo tanggal_indo(date('Y-m-d')); ?></span>
                    <span class="badge badge-light border"><i class="fas fa-clock mr-1"></i><span id="liveClock">--:--:--</span> WIB</span>
                </div>
            </div>

            <div class="mt-2 mt-md-0">

                <a href="laporan.php" class="btn btn-primary mr-1">
                    <i class="fas fa-file-alt mr-1"></i>
                    Pusat Laporan
                </a>

                <a href="statistik.php" class="btn btn-outline-primary">
                    <i class="fas fa-chart-bar mr-1"></i>
                    Statistik
                </a>

            </div>

        </div>

    </div>
</div>


<section class="content">
<div class="container-fluid">


<!-- =======================================================
     KARTU UTAMA
======================================================= -->

<div class="row">

<?php
$cards = [

    [
        'Total Anggota',
        $total_anggota,
        'users',
        'primary'
    ],

    [
        'Total Judul Buku',
        $total_buku,
        'book',
        'success'
    ],

    [
        'Sedang Dipinjam',
        $total_pinjam,
        'book-reader',
        'warning'
    ],

    [
        'Sudah Dikembalikan',
        $total_kembali,
        'undo',
        'info'
    ]

];

foreach ($cards as $c):
?>

<div class="col-lg-3 col-md-6">

    <div class="small-box bg-<?php echo $c[3]; ?>">

        <div class="inner">

            <h3>
                <?php
                echo htmlspecialchars((string)$c[1]);
                ?>
            </h3>

            <p>
                <?php
                echo $c[0];
                ?>
            </p>

        </div>

        <div class="icon">

            <i class="fas fa-<?php echo $c[2]; ?>"></i>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

<!-- =======================================================
     LAYANAN PERPUSTAKAAN
======================================================= -->
<div class="row">
    <?php foreach ($layanan as $l) { ?>
        <div class="col-6 col-lg-3 mb-3">
            <a href="<?php echo $l[0]; ?>" class="d-block h-100 text-reset">
                <div class="pz-stat">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="pz-stat-value"><?php echo $l[3]; ?></div>
                        <i class="fas fa-<?php echo $l[1]; ?> text-gold"></i>
                    </div>
                    <div class="pz-stat-label"><?php echo $l[2]; ?></div>
                </div>
            </a>
        </div>
    <?php } ?>
</div>

<!-- =======================================================
     AKTIVITAS HARI INI
======================================================= -->

<div class="row mb-3">
    <div class="col-lg-4 col-md-4 mb-2 mb-md-0">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-primary"><i class="fas fa-book-reader"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Peminjaman Hari Ini</span>
                <span class="info-box-number"><?php echo (int)$total_pinjam_hari_ini; ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 mb-2 mb-md-0">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-undo"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pengembalian Hari Ini</span>
                <span class="info-box-number"><?php echo (int)$total_kembali_hari_ini; ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4">
        <div class="info-box mb-0 shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Anggota Aktif</span>
                <span class="info-box-number"><?php echo (int)$total_anggota_aktif; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     STOK & DENDA
======================================================= -->

<div class="row">

    <div class="col-md-4">

        <div class="card card-outline card-primary">

            <div class="card-header">

                <h3 class="card-title">
                    <i class="fas fa-warehouse mr-2"></i>
                    Total Stok Tersedia
                </h3>

            </div>

            <div class="card-body">

                <h2 class="mb-0">

                    <?php
                    echo number_format(
                        (int)$total_stok,
                        0,
                        ',',
                        '.'
                    );
                    ?>

                    <small class="text-muted">
                        buku
                    </small>

                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card card-outline card-danger">

            <div class="card-header">

                <h3 class="card-title">
                    <i class="fas fa-money-bill-wave mr-2"></i>
                    Total Denda
                </h3>

            </div>

            <div class="card-body">

                <h2 class="mb-0">

                    Rp
                    <?php
                    echo number_format(
                        (float)$total_denda,
                        0,
                        ',',
                        '.'
                    );
                    ?>

                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card card-outline card-warning">

            <div class="card-header">

                <h3 class="card-title">
                    <i class="fas fa-clock mr-2"></i>
                    Peminjaman Terlambat
                </h3>

            </div>

            <div class="card-body">

                <h2 class="mb-0">

                    <?php echo (int)$total_terlambat; ?>

                    <small class="text-muted">
                        transaksi
                    </small>

                </h2>

                <?php if ($total_terlambat > 0): ?>

                    <a href="terlambat.php"
                       class="btn btn-warning btn-sm mt-2">

                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Lihat Peminjaman Terlambat

                    </a>

                <?php else: ?>

                    <span class="text-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        Tidak ada keterlambatan
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     MONITORING STOK
======================================================= -->

<div class="row">

    <div class="col-md-6">

        <div class="info-box">

            <span class="info-box-icon bg-danger">

                <i class="fas fa-box-open"></i>

            </span>

            <div class="info-box-content">

                <span class="info-box-text">
                    Buku Stok Habis
                </span>

                <span class="info-box-number">

                    <?php echo (int)$total_stok_habis; ?>

                    <small>judul</small>

                </span>

                <?php if ($total_stok_habis > 0): ?>

                    <span class="progress-description text-danger">

                        Perlu perhatian admin

                    </span>

                <?php else: ?>

                    <span class="progress-description text-success">

                        Semua buku masih tersedia

                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <div class="col-md-6">

        <div class="info-box">

            <span class="info-box-icon bg-warning">

                <i class="fas fa-exclamation-circle"></i>

            </span>

            <div class="info-box-content">

                <span class="info-box-text">
                    Buku Stok Menipis
                </span>

                <span class="info-box-number">

                    <?php echo (int)$total_stok_menipis; ?>

                    <small>judul</small>

                </span>

                <span class="progress-description">

                    Stok 1–2 buku

                </span>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     GRAFIK PEMINJAMAN
======================================================= -->

<div class="row">

    <div class="col-lg-8">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-chart-line mr-2"></i>

                    Grafik Peminjaman

                </h3>

                <div class="card-tools">

                    <span class="badge badge-primary">
                        6 Bulan Terakhir
                    </span>

                </div>

            </div>

            <div class="card-body">

                <div style="height:300px;">

                    <canvas id="grafikPeminjaman"></canvas>

                </div>

            </div>

        </div>

    </div>


    <!-- ===================================================
         STATUS SISTEM
    =================================================== -->

    <div class="col-lg-4">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-info-circle mr-2"></i>

                    Status Sistem

                </h3>

            </div>

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted small">Administrator</div>
                        <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong>
                    </div>
                    <span class="badge badge-primary px-2 py-1">ADMIN</span>
                </div>

                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><i class="fas fa-database text-primary mr-2"></i>Database</span>
                        <span class="badge badge-<?php echo $db_terhubung ? 'success' : 'danger'; ?>">
                            <?php echo $db_terhubung ? 'Terhubung' : 'Terputus'; ?>
                        </span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><i class="fas fa-users text-success mr-2"></i>Anggota aktif</span>
                        <strong><?php echo (int)$total_anggota_aktif; ?></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><i class="fas fa-boxes text-warning mr-2"></i>Total stok</span>
                        <strong><?php echo number_format((int)$total_stok,0,',','.'); ?></strong>
                    </li>
                </ul>

                <div class="alert alert-<?php echo $db_terhubung ? 'success' : 'danger'; ?> mb-0">
                    <i class="fas fa-<?php echo $db_terhubung ? 'check-circle' : 'exclamation-circle'; ?> mr-1"></i>
                    <?php echo $db_terhubung ? 'Sistem dan koneksi database berjalan normal.' : 'Koneksi database perlu diperiksa.'; ?>
                </div>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     SMART INSIGHT & NOTIFIKASI
======================================================= -->

<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bell mr-2"></i>Perlu Perhatian</h3>
                <div class="card-tools"><span class="badge badge-warning"><?php echo count($insight_messages); ?> indikator</span></div>
            </div>
            <div class="card-body">
                <?php foreach ($insight_messages as $insight): ?>
                    <div class="alert alert-<?php echo $insight[0]; ?> d-flex align-items-center justify-content-between flex-wrap">
                        <div class="mr-3 mb-2 mb-md-0">
                            <strong><i class="fas fa-<?php echo $insight[1]; ?> mr-1"></i><?php echo htmlspecialchars($insight[2]); ?></strong>
                            <div class="small mt-1"><?php echo htmlspecialchars($insight[3]); ?></div>
                        </div>
                        <?php if ($insight[4] !== ''): ?>
                            <a href="<?php echo htmlspecialchars($insight[4]); ?>" class="btn btn-sm btn-<?php echo $insight[0]; ?>">
                                Periksa <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lightbulb mr-2"></i>Insight Hari Ini</h3>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong><?php echo (int)$total_pinjam_hari_ini; ?></strong> peminjaman tercatat hari ini.
                </p>
                <p class="mb-2">
                    <strong><?php echo (int)$total_kembali_hari_ini; ?></strong> pengembalian tercatat hari ini.
                </p>
                <p class="mb-0 text-muted">
                    Gunakan <strong>Statistik</strong> untuk melihat pola aktivitas dan <strong>Laporan</strong> untuk dokumentasi periodik.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     BUKU POPULER & ANGGOTA AKTIF
======================================================= -->

<div class="row">

    <!-- BUKU POPULER -->

    <div class="col-lg-6">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-trophy mr-2"></i>

                    Buku Terpopuler

                </h3>

            </div>

            <div class="card-body p-0">

                <?php if (!empty($buku_populer)): ?>

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th style="width:60px;">
                                        #
                                    </th>

                                    <th>
                                        Judul Buku
                                    </th>

                                    <th class="text-right">
                                        Dipinjam
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php
                            $nomor = 1;

                            foreach ($buku_populer as $buku):
                            ?>

                                <tr>

                                    <td>

                                        <?php echo $nomor++; ?>

                                    </td>

                                    <td>

                                        <i class="fas fa-book text-success mr-1"></i>

                                        <?php
                                        echo htmlspecialchars(
                                            $buku['judul']
                                        );
                                        ?>

                                    </td>

                                    <td class="text-right">

                                        <span class="badge badge-success">

                                            <?php
                                            echo (int)$buku['total_pinjam'];
                                            ?>

                                            kali

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="p-3 text-muted">

                        Belum ada data peminjaman buku.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- ANGGOTA AKTIF -->

    <div class="col-lg-6">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-user-check mr-2"></i>

                    Anggota Paling Aktif

                </h3>

            </div>

            <div class="card-body p-0">

                <?php if (!empty($anggota_aktif)): ?>

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th style="width:60px;">
                                        #
                                    </th>

                                    <th>
                                        Nama Anggota
                                    </th>

                                    <th class="text-right">
                                        Peminjaman
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php
                            $nomor = 1;

                            foreach ($anggota_aktif as $anggota):
                            ?>

                                <tr>

                                    <td>

                                        <?php echo $nomor++; ?>

                                    </td>

                                    <td>

                                        <i class="fas fa-user text-primary mr-1"></i>

                                        <?php
                                        echo htmlspecialchars(
                                            $anggota['nama']
                                        );
                                        ?>

                                    </td>

                                    <td class="text-right">

                                        <span class="badge badge-primary">

                                            <?php
                                            echo (int)$anggota['total_pinjam'];
                                            ?>

                                            kali

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="p-3 text-muted">

                        Belum ada data anggota aktif.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     AKTIVITAS TERBARU
======================================================= -->

<div class="row">

    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-history mr-2"></i>

                    Aktivitas Peminjaman Terbaru

                </h3>

                <div class="card-tools">

                    <a href="pinjam.php"
                       class="btn btn-sm btn-primary">

                        <i class="fas fa-book-reader mr-1"></i>

                        Kelola Peminjaman

                    </a>

                </div>

            </div>

            <div class="card-body p-0">

                <?php if (!empty($peminjaman_terbaru)): ?>

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        No. Pinjam
                                    </th>

                                    <th>
                                        Anggota
                                    </th>

                                    <th>
                                        Buku
                                    </th>

                                    <th>
                                        Tanggal
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($peminjaman_terbaru as $data): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            #<?php
                                            echo (int)$data['no_pinjam'];
                                            ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $data['nama']
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $data['judul']
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo date(
                                            'd-m-Y',
                                            strtotime(
                                                $data['tgl_pinjam']
                                            )
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        if (
                                            strtolower(
                                                trim(
                                                    $data['status']
                                                )
                                            ) === 'pinjam'
                                        ):
                                        ?>

                                            <span class="badge badge-primary">

                                                <i class="fas fa-book-reader mr-1"></i>

                                                Dipinjam

                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-success">

                                                <i class="fas fa-check mr-1"></i>

                                                Kembali

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="p-3 text-muted">

                        Belum ada aktivitas peminjaman.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     AKSES CEPAT
======================================================= -->

<div class="row">

    <div class="col-lg-8">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-bolt mr-2"></i>

                    Akses Cepat

                </h3>

            </div>

            <div class="card-body">

                <div class="row">

                <?php

                $quick = [

                    [
                        'anggota.php',
                        'users',
                        'Data Anggota',
                        'outline-primary'
                    ],

                    [
                        'buku.php',
                        'book',
                        'Data Buku',
                        'outline-primary'
                    ],

                    [
                        'pinjam.php',
                        'book-reader',
                        'Peminjaman',
                        'outline-primary'
                    ],

                    [
                        'kembali.php',
                        'undo',
                        'Pengembalian',
                        'outline-primary'
                    ],

                    [
                        'riwayat_kembali.php',
                        'history',
                        'Riwayat',
                        'outline-primary'
                    ],

                    [
                        'laporan.php',
                        'file-alt',
                        'Laporan',
                        'outline-primary'
                    ],

                    [
                        'statistik.php',
                        'chart-bar',
                        'Statistik',
                        'outline-primary'
                    ],

                    [
                        'terlambat.php',
                        'clock',
                        'Terlambat',
                        'outline-primary'
                    ]

                ];

                foreach ($quick as $q):
                ?>

                    <div class="col-md-3 col-sm-6 mb-3">

                        <a href="<?php echo $q[0]; ?>"
                           class="btn btn-<?php echo $q[3]; ?> btn-block">

                            <i class="fas fa-<?php echo $q[1]; ?> mr-1"></i>

                            <?php echo $q[2]; ?>

                        </a>

                    </div>

                <?php endforeach; ?>

                </div>

            </div>

        </div>

    </div>


    <div class="col-lg-4">

        <div class="card card-outline card-info">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-lightbulb mr-2"></i>

                    Monitoring

                </h3>

            </div>

            <div class="card-body">

                <?php if ($total_stok_habis > 0): ?>

                    <div class="alert alert-danger">

                        <i class="fas fa-box-open mr-1"></i>

                        <strong>
                            <?php echo $total_stok_habis; ?>
                        </strong>

                        buku kehabisan stok.

                    </div>

                <?php endif; ?>


                <?php if ($total_stok_menipis > 0): ?>

                    <div class="alert alert-warning">

                        <i class="fas fa-exclamation-triangle mr-1"></i>

                        <strong>
                            <?php echo $total_stok_menipis; ?>
                        </strong>

                        buku memiliki stok menipis.

                    </div>

                <?php endif; ?>


                <?php if ($total_terlambat > 0): ?>

                    <div class="alert alert-warning">

                        <i class="fas fa-clock mr-1"></i>

                        Ada

                        <strong>
                            <?php echo $total_terlambat; ?>
                        </strong>

                        peminjaman terlambat.

                    </div>

                <?php endif; ?>


                <?php
                if (
                    $total_stok_habis == 0 &&
                    $total_stok_menipis == 0 &&
                    $total_terlambat == 0
                ):
                ?>

                    <div class="alert alert-success mb-0">

                        <i class="fas fa-check-circle mr-1"></i>

                        Semua kondisi perpustakaan
                        dalam keadaan normal.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


</div>
</section>


<!-- =======================================================
     GRAFIK CHART.JS
======================================================= -->

<script src="/perpuszaky/plugins/chart.js/Chart.min.js"></script>

<script>

document.addEventListener("DOMContentLoaded", function () {

    var canvas = document.getElementById("grafikPeminjaman");

    if (!canvas) {
        return;
    }

    new Chart(canvas, {

        type: "line",

        data: {

            labels: <?php echo json_encode($label_bulan); ?>,

            datasets: [{

                label: "Jumlah Peminjaman",

                data: <?php echo json_encode($data_peminjaman); ?>,

                borderColor: "#1d3a63",

                backgroundColor: "rgba(29, 58, 99, 0.10)",

                pointBackgroundColor: "#c9a24a",

                pointBorderColor: "#c9a24a",

                fill: true,

                tension: 0.3,

                borderWidth: 2,

                pointRadius: 4,

                pointHoverRadius: 6

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            animation: {
                duration: 700,
                easing: "easeOutQuart"
            },

            legend: {

                display: true

            },

            scales: {

                yAxes: [{

                    ticks: {

                        beginAtZero: true,

                        precision: 0

                    }

                }]

            },

            tooltips: {

                mode: "index",

                intersect: false

            }

        }

    });

});

</script>


<script>
/* =========================================================
   JAM REALTIME WIB (ASIA/JAKARTA)
========================================================= */
(function () {
    const clock = document.getElementById("liveClock");
    if (!clock) return;

    function updateLiveClock() {
        const now = new Date();
        const waktu = new Intl.DateTimeFormat("id-ID", {
            timeZone: "Asia/Jakarta",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hourCycle: "h23"
        }).format(now);

        clock.textContent = waktu;

        /* Sapaan ikut berganti bila halaman dibiarkan terbuka
           (aturan jam sama dengan sapaan_waktu() di inc/aturan.php) */
        const jam = parseInt(new Intl.DateTimeFormat("en-GB", {
            timeZone: "Asia/Jakarta", hour: "2-digit", hourCycle: "h23"
        }).format(now), 10);
        const sapa = document.getElementById("pzSapaan");
        if (sapa) {
            sapa.textContent = jam >= 4 && jam < 11 ? "Selamat pagi"
                : jam >= 11 && jam < 15 ? "Selamat siang"
                : jam >= 15 && jam < 18 ? "Selamat sore" : "Selamat malam";
        }
    }

    updateLiveClock();
    setInterval(updateLiveClock, 1000);
})();
</script>

<?php include "inc/footer.php"; ?>