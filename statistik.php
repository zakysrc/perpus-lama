<?php
require_once "inc/admin_only.php";
include "koneksi.php";

$page_title = "Smart Analytics";

/* =========================================================
   HELPER
========================================================= */
function total_query($koneksi, $sql, $field = 'total')
{
    $q = mysqli_query($koneksi, $sql);

    if (!$q) {
        return 0;
    }

    $r = mysqli_fetch_assoc($q);

    return isset($r[$field]) ? $r[$field] : 0;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   FILTER PERIODE
========================================================= */
$periode = isset($_GET['periode']) ? $_GET['periode'] : '180';

$allowed_periode = ['7', '30', '90', '180', '365'];

if (!in_array($periode, $allowed_periode, true)) {
    $periode = '180';
}

$hari = (int)$periode;

$tanggal_akhir = date('Y-m-d');
$tanggal_mulai = date('Y-m-d', strtotime('-' . ($hari - 1) . ' days'));

$label_periode = [
    '7'   => '7 Hari',
    '30'  => '30 Hari',
    '90'  => '3 Bulan',
    '180' => '6 Bulan',
    '365' => '1 Tahun'
];

$judul_periode = $label_periode[$periode];

/* =========================================================
   RINGKASAN UTAMA
========================================================= */
$total_anggota = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbanggota"
);

$total_buku = total_query(
    $koneksi,
    "SELECT COUNT(*) total FROM tbbuku"
);

$total_pinjam_aktif = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE status='pinjam'"
);

$total_transaksi_periode = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE tgl_pinjam BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'"
);

$total_kembali_periode = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE status='kembali'
       AND tgl_kembali BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'"
);

$total_stok = total_query(
    $koneksi,
    "SELECT COALESCE(SUM(jml_buku),0) total FROM tbbuku"
);

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

$total_terlambat = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE status='pinjam'
       AND " . sql_terlambat()
);

$total_denda_periode = total_query(
    $koneksi,
    "SELECT COALESCE(SUM(denda),0) total
     FROM tbpinjam
     WHERE status='kembali'
       AND tgl_kembali BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'"
);

/* =========================================================
   DATA GRAFIK
   7/30 HARI  = HARIAN
   90/180/365  = BULANAN
========================================================= */
$chart_labels = [];
$chart_pinjam = [];
$chart_kembali = [];

if ($hari <= 30) {

    for ($i = $hari - 1; $i >= 0; $i--) {
        $tanggal = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('d', strtotime($tanggal)) . ' ' . bulan_singkat(date('n', strtotime($tanggal)));
        $chart_pinjam[$tanggal] = 0;
        $chart_kembali[$tanggal] = 0;
    }

    $q_chart = mysqli_query(
        $koneksi,
        "SELECT
            tgl_pinjam AS periode,
            COUNT(*) AS jumlah
         FROM tbpinjam
         WHERE tgl_pinjam BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
         GROUP BY tgl_pinjam
         ORDER BY tgl_pinjam ASC"
    );

    if ($q_chart) {
        while ($row = mysqli_fetch_assoc($q_chart)) {
            if (isset($chart_pinjam[$row['periode']])) {
                $chart_pinjam[$row['periode']] = (int)$row['jumlah'];
            }
        }
    }

    $q_return = mysqli_query(
        $koneksi,
        "SELECT
            tgl_kembali AS periode,
            COUNT(*) AS jumlah
         FROM tbpinjam
         WHERE status='kembali'
           AND tgl_kembali BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
         GROUP BY tgl_kembali
         ORDER BY tgl_kembali ASC"
    );

    if ($q_return) {
        while ($row = mysqli_fetch_assoc($q_return)) {
            if (isset($chart_kembali[$row['periode']])) {
                $chart_kembali[$row['periode']] = (int)$row['jumlah'];
            }
        }
    }

    $chart_pinjam_data = array_values($chart_pinjam);
    $chart_kembali_data = array_values($chart_kembali);

} else {

    $jumlah_bulan = $hari === 90 ? 3 : ($hari === 180 ? 6 : 12);

    $map_pinjam = [];
    $map_kembali = [];

    for ($i = $jumlah_bulan - 1; $i >= 0; $i--) {
        $bulan = date('Y-m', strtotime("-$i months"));
        $chart_labels[] = bulan_singkat(date('n', strtotime($bulan . '-01'))) . ' ' . date('Y', strtotime($bulan . '-01'));
        $map_pinjam[$bulan] = 0;
        $map_kembali[$bulan] = 0;
    }

    $awal_bulan = date(
        'Y-m-01',
        strtotime("-" . ($jumlah_bulan - 1) . " months")
    );

    $q_chart = mysqli_query(
        $koneksi,
        "SELECT
            DATE_FORMAT(tgl_pinjam,'%Y-%m') AS periode,
            COUNT(*) AS jumlah
         FROM tbpinjam
         WHERE tgl_pinjam >= '$awal_bulan'
           AND tgl_pinjam <= '$tanggal_akhir'
         GROUP BY DATE_FORMAT(tgl_pinjam,'%Y-%m')
         ORDER BY periode ASC"
    );

    if ($q_chart) {
        while ($row = mysqli_fetch_assoc($q_chart)) {
            if (isset($map_pinjam[$row['periode']])) {
                $map_pinjam[$row['periode']] = (int)$row['jumlah'];
            }
        }
    }

    $q_return = mysqli_query(
        $koneksi,
        "SELECT
            DATE_FORMAT(tgl_kembali,'%Y-%m') AS periode,
            COUNT(*) AS jumlah
         FROM tbpinjam
         WHERE status='kembali'
           AND tgl_kembali >= '$awal_bulan'
           AND tgl_kembali <= '$tanggal_akhir'
         GROUP BY DATE_FORMAT(tgl_kembali,'%Y-%m')
         ORDER BY periode ASC"
    );

    if ($q_return) {
        while ($row = mysqli_fetch_assoc($q_return)) {
            if (isset($map_kembali[$row['periode']])) {
                $map_kembali[$row['periode']] = (int)$row['jumlah'];
            }
        }
    }

    $chart_pinjam_data = array_values($map_pinjam);
    $chart_kembali_data = array_values($map_kembali);
}

/* =========================================================
   BUKU PALING SERING DIPINJAM
========================================================= */
$buku_populer = [];

$q_populer = mysqli_query(
    $koneksi,
    "SELECT
        tbbuku.kode_buku,
        tbbuku.judul,
        COALESCE(SUM(tbpinjam.jml_pinjam),0) AS total_pinjam
     FROM tbbuku
     LEFT JOIN tbpinjam
        ON tbbuku.kode_buku = tbpinjam.kode_buku
       AND tbpinjam.tgl_pinjam BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
     GROUP BY tbbuku.kode_buku, tbbuku.judul
     ORDER BY total_pinjam DESC, tbbuku.judul ASC
     LIMIT 5"
);

if ($q_populer) {
    while ($row = mysqli_fetch_assoc($q_populer)) {
        $buku_populer[] = $row;
    }
}

/* =========================================================
   ANGGOTA PALING AKTIF
========================================================= */
$anggota_populer = [];

$q_anggota = mysqli_query(
    $koneksi,
    "SELECT
        tbanggota.id_ang,
        tbanggota.nama,
        COUNT(tbpinjam.no_pinjam) AS total_transaksi
     FROM tbanggota
     LEFT JOIN tbpinjam
        ON tbanggota.id_ang = tbpinjam.id_ang
       AND tbpinjam.tgl_pinjam BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
     GROUP BY tbanggota.id_ang, tbanggota.nama
     ORDER BY total_transaksi DESC, tbanggota.nama ASC
     LIMIT 5"
);

if ($q_anggota) {
    while ($row = mysqli_fetch_assoc($q_anggota)) {
        $anggota_populer[] = $row;
    }
}

/* =========================================================
   DISTRIBUSI STOK
========================================================= */
$stok_aman = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbbuku
     WHERE jml_buku > 2"
);

$stok_menipis = $total_stok_menipis;
$stok_habis = $total_stok_habis;

/* =========================================================
   PERBANDINGAN DENGAN PERIODE SEBELUMNYA
========================================================= */
$periode_sebelumnya_mulai = date(
    'Y-m-d',
    strtotime("-$hari days", strtotime($tanggal_mulai))
);

$periode_sebelumnya_akhir = date(
    'Y-m-d',
    strtotime('-1 day', strtotime($tanggal_mulai))
);

$total_sebelumnya = total_query(
    $koneksi,
    "SELECT COUNT(*) total
     FROM tbpinjam
     WHERE tgl_pinjam BETWEEN '$periode_sebelumnya_mulai'
                           AND '$periode_sebelumnya_akhir'"
);

$selisih_peminjaman = (int)$total_transaksi_periode - (int)$total_sebelumnya;

if ($total_sebelumnya > 0) {
    $persentase_perubahan = round(
        ($selisih_peminjaman / $total_sebelumnya) * 100,
        1
    );
} elseif ($total_transaksi_periode > 0) {
    $persentase_perubahan = 100;
} else {
    $persentase_perubahan = 0;
}

/* =========================================================
   INSIGHT SISTEM
========================================================= */
$insights = [];

if ($total_stok_habis > 0) {
    $insights[] = [
        'danger',
        'box-open',
        $total_stok_habis . ' buku kehabisan stok.',
        'Periksa Data Buku dan perbarui stok bila diperlukan.',
        'buku.php'
    ];
}

if ($total_terlambat > 0) {
    $insights[] = [
        'warning',
        'clock',
        $total_terlambat . ' peminjaman sedang terlambat.',
        'Periksa daftar keterlambatan untuk tindak lanjut.',
        'terlambat.php'
    ];
}

if ($total_stok_menipis > 0) {
    $insights[] = [
        'warning',
        'exclamation-triangle',
        $total_stok_menipis . ' buku memiliki stok menipis.',
        'Stok 1–2 buku perlu diperhatikan.',
        'buku.php'
    ];
}

if ($total_transaksi_periode > 0) {
    if ($selisih_peminjaman > 0) {
        $insights[] = [
            'info',
            'chart-line',
            'Aktivitas peminjaman meningkat.',
            'Periode ini memiliki ' . abs($selisih_peminjaman) .
            ' transaksi lebih banyak daripada periode sebelumnya.',
            ''
        ];
    } elseif ($selisih_peminjaman < 0) {
        $insights[] = [
            'secondary',
            'chart-line',
            'Aktivitas peminjaman menurun.',
            'Periode ini memiliki ' . abs($selisih_peminjaman) .
            ' transaksi lebih sedikit daripada periode sebelumnya.',
            ''
        ];
    } else {
        $insights[] = [
            'info',
            'chart-line',
            'Aktivitas peminjaman relatif stabil.',
            'Jumlah transaksi sama dengan periode sebelumnya.',
            ''
        ];
    }
}

if (empty($insights)) {
    $insights[] = [
        'success',
        'check-circle',
        'Indikator utama dalam kondisi normal.',
        'Belum ada kondisi yang membutuhkan perhatian khusus.',
        ''
    ];
}

include "inc/header.php";
?>

<div class="content-header">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap">

            <div>
                <h1 class="m-0">
                    <i class="fas fa-chart-line mr-2"></i>
                    Smart Analytics
                </h1>

                <p class="text-muted mb-0 mt-1">
                    Analisis aktivitas dan kondisi perpustakaan berdasarkan data sistem.
                </p>
            </div>

            <div class="mt-2 mt-md-0">
                <a href="laporan.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-alt mr-1"></i>
                    Pusat Laporan
                </a>

                <a href="beranda_admin.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i>
                    Dashboard
                </a>
            </div>

        </div>

    </div>
</div>

<section class="content">
<div class="container-fluid">

    <!-- FILTER -->
    <div class="card card-outline card-primary">

        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter mr-2"></i>
                Periode Analisis
            </h3>
        </div>

        <div class="card-body">

            <form method="get" class="form-row align-items-end" id="formPeriode" autocomplete="off">

                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="small text-muted">Rentang waktu</label>

                    <select name="periode" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($label_periode as $key => $label): ?>
                            <option
                                value="<?php echo h($key); ?>"
                                <?php echo $periode === (string) $key ? 'selected' : ''; ?>>
                                <?php echo h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-2 mb-md-0">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sync-alt mr-1"></i>
                        Terapkan Analisis
                    </button>
                </div>

                <div class="col-md-5">
                    <div class="text-muted small">
                        Periode aktif:
                        <strong><?php echo h($judul_periode); ?></strong>
                        <br>
                        <?php echo h(date('d-m-Y', strtotime($tanggal_mulai))); ?>
                        s/d
                        <?php echo h(date('d-m-Y', strtotime($tanggal_akhir))); ?>
                    </div>
                </div>

            </form>

        </div>

    </div>

    <!-- RINGKASAN -->
    <div class="row">

        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3><?php echo number_format($total_anggota); ?></h3>
                    <p>Total Anggota</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <a href="anggota.php" class="small-box-footer">
                    Lihat data <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php echo number_format($total_buku); ?></h3>
                    <p>Total Judul Buku</p>
                </div>
                <div class="icon">
                    <i class="fas fa-book"></i>
                </div>
                <a href="buku.php" class="small-box-footer">
                    Lihat katalog <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php echo number_format($total_transaksi_periode); ?></h3>
                    <p>Peminjaman <?php echo h($judul_periode); ?></p>
                </div>
                <div class="icon">
                    <i class="fas fa-book-reader"></i>
                </div>
                <span class="small-box-footer">
                    <?php echo number_format($total_pinjam_aktif); ?> masih aktif
                </span>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php echo number_format($total_kembali_periode); ?></h3>
                    <p>Pengembalian <?php echo h($judul_periode); ?></p>
                </div>
                <div class="icon">
                    <i class="fas fa-undo"></i>
                </div>
                <span class="small-box-footer">
                    Rp <?php echo number_format($total_denda_periode, 0, ',', '.'); ?> denda
                </span>
            </div>
        </div>

    </div>

    <!-- GRAFIK -->
    <div class="row">

        <div class="col-lg-8">

            <div class="card card-outline card-primary">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-area mr-2"></i>
                        Tren Peminjaman & Pengembalian
                    </h3>
                </div>

                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="smartAnalyticsChart"></canvas>
                    </div>
                </div>

            </div>

        </div>

        <div class="col-lg-4">

            <div class="card card-outline card-success">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-boxes mr-2"></i>
                        Kondisi Stok
                    </h3>
                </div>

                <div class="card-body">

                    <div style="height: 250px;">
                        <canvas id="stockChart"></canvas>
                    </div>

                    <div class="row text-center mt-3">

                        <div class="col-4">
                            <strong><?php echo number_format($stok_aman); ?></strong>
                            <div class="small text-muted">Aman</div>
                        </div>

                        <div class="col-4">
                            <strong><?php echo number_format($stok_menipis); ?></strong>
                            <div class="small text-muted">Menipis</div>
                        </div>

                        <div class="col-4">
                            <strong><?php echo number_format($stok_habis); ?></strong>
                            <div class="small text-muted">Habis</div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- PERINGATAN + PERBANDINGAN -->
    <div class="row">

        <div class="col-lg-7">

            <div class="card card-outline card-warning">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Insight Sistem
                    </h3>
                </div>

                <div class="card-body">

                    <?php foreach ($insights as $insight): ?>

                        <div class="alert alert-<?php echo h($insight[0]); ?>">

                            <div class="d-flex">

                                <div class="mr-3">
                                    <i class="fas fa-<?php echo h($insight[1]); ?> fa-lg"></i>
                                </div>

                                <div class="flex-grow-1">

                                    <strong>
                                        <?php echo h($insight[2]); ?>
                                    </strong>

                                    <div class="small mt-1">
                                        <?php echo h($insight[3]); ?>
                                    </div>

                                    <?php if (!empty($insight[4])): ?>
                                        <a
                                            href="<?php echo h($insight[4]); ?>"
                                            class="btn btn-sm btn-light mt-2">
                                            Periksa sekarang
                                            <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

        <div class="col-lg-5">

            <div class="card card-outline card-info">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exchange-alt mr-2"></i>
                        Perbandingan Aktivitas
                    </h3>
                </div>

                <div class="card-body">

                    <div class="text-center mb-3">

                        <div class="text-muted small">
                            Peminjaman <?php echo h($judul_periode); ?>
                        </div>

                        <div class="display-4 font-weight-bold">
                            <?php echo number_format($total_transaksi_periode); ?>
                        </div>

                    </div>

                    <div class="progress mb-3" style="height: 8px;">
                        <?php
                        $progress = min(
                            100,
                            max(
                                0,
                                $total_sebelumnya > 0
                                    ? round(($total_transaksi_periode / $total_sebelumnya) * 100)
                                    : ($total_transaksi_periode > 0 ? 100 : 0)
                            )
                        );
                        ?>
                        <div
                            class="progress-bar"
                            role="progressbar"
                            style="width: <?php echo $progress; ?>%;">
                        </div>
                    </div>

                    <div class="text-center">

                        <?php if ($selisih_peminjaman > 0): ?>

                            <span class="badge badge-success p-2">
                                <i class="fas fa-arrow-up mr-1"></i>
                                +<?php echo number_format($persentase_perubahan, 1); ?>%
                            </span>

                            <div class="small text-muted mt-2">
                                Dibanding periode sebelumnya
                            </div>

                        <?php elseif ($selisih_peminjaman < 0): ?>

                            <span class="badge badge-secondary p-2">
                                <i class="fas fa-arrow-down mr-1"></i>
                                <?php echo number_format(abs($persentase_perubahan), 1); ?>%
                            </span>

                            <div class="small text-muted mt-2">
                                Dibanding periode sebelumnya
                            </div>

                        <?php else: ?>

                            <span class="badge badge-info p-2">
                                <i class="fas fa-minus mr-1"></i>
                                Stabil
                            </span>

                            <div class="small text-muted mt-2">
                                Tidak ada perubahan jumlah transaksi
                            </div>

                        <?php endif; ?>

                    </div>

                    <hr>

                    <div class="row text-center">

                        <div class="col-6">
                            <strong><?php echo number_format($total_stok); ?></strong>
                            <div class="small text-muted">Total stok</div>
                        </div>

                        <div class="col-6">
                            <strong><?php echo number_format($total_terlambat); ?></strong>
                            <div class="small text-muted">Terlambat</div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- RANKING -->
    <div class="row">

        <div class="col-lg-6">

            <div class="card card-outline card-danger">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-trophy mr-2"></i>
                        Buku Paling Sering Dipinjam
                    </h3>
                </div>

                <div class="card-body p-0">

                    <?php if (!empty($buku_populer)): ?>

                        <div class="list-group list-group-flush">

                            <?php foreach ($buku_populer as $index => $buku): ?>

                                <div class="list-group-item d-flex align-items-center">

                                    <span class="badge badge-primary mr-3">
                                        <?php echo $index + 1; ?>
                                    </span>

                                    <div class="flex-grow-1">
                                        <strong><?php echo h($buku['judul']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo number_format($buku['total_pinjam']); ?>
                                            buku dipinjam
                                        </div>
                                    </div>

                                    <i class="fas fa-book text-muted"></i>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="p-4 text-center text-muted">
                            Belum ada data peminjaman pada periode ini.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="col-lg-6">

            <div class="card card-outline card-primary">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-check mr-2"></i>
                        Anggota Paling Aktif
                    </h3>
                </div>

                <div class="card-body p-0">

                    <?php if (!empty($anggota_populer)): ?>

                        <div class="list-group list-group-flush">

                            <?php foreach ($anggota_populer as $index => $anggota): ?>

                                <div class="list-group-item d-flex align-items-center">

                                    <span class="badge badge-info mr-3">
                                        <?php echo $index + 1; ?>
                                    </span>

                                    <div class="flex-grow-1">
                                        <strong><?php echo h($anggota['nama']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo number_format($anggota['total_transaksi']); ?>
                                            transaksi
                                        </div>
                                    </div>

                                    <i class="fas fa-user text-muted"></i>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="p-4 text-center text-muted">
                            Belum ada data anggota aktif pada periode ini.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>
</section>

<script src="/perpuszaky/plugins/chart.js/Chart.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    var trendCanvas = document.getElementById("smartAnalyticsChart");

    if (trendCanvas) {

        /* Batang: data harian banyak nol, garis melengkung
           membuat "paku" yang menyesatkan */
        new Chart(trendCanvas, {
            type: "bar",

            data: {
                labels: <?php echo json_encode($chart_labels); ?>,

                datasets: [
                    {
                        label: "Peminjaman",
                        data: <?php echo json_encode($chart_pinjam_data); ?>,
                        borderColor: "#1d3a63",
                        backgroundColor: "#1d3a63",
                        pointBackgroundColor: "#1d3a63",
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: "Pengembalian",
                        data: <?php echo json_encode($chart_kembali_data); ?>,
                        borderColor: "#c9a24a",
                        backgroundColor: "#c9a24a",
                        pointBackgroundColor: "#c9a24a",
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
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
    }

    var stockCanvas = document.getElementById("stockChart");

    if (stockCanvas) {

        new Chart(stockCanvas, {
            type: "doughnut",

            data: {
                labels: [
                    "Stok Aman",
                    "Stok Menipis",
                    "Stok Habis"
                ],

                datasets: [{
                    data: [
                        <?php echo (int)$stok_aman; ?>,
                        <?php echo (int)$stok_menipis; ?>,
                        <?php echo (int)$stok_habis; ?>
                    ],

                    backgroundColor: ["#1d3a63", "#c9a24a", "#b23b3b"],
                    borderColor: "#ffffff",
                    borderWidth: 2
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                legend: {
                    position: "bottom"
                },

                cutoutPercentage: 62
            }
        });
    }

});
</script>

<script>
/* Tombol Kembali browser memulihkan pilihan dropdown lama tanpa
   memuat ulang data; muat ulang agar pilihan = data yang tampil */
window.addEventListener('pageshow', function (e) { if (e.persisted) { location.reload(); } });
</script>

<?php include "inc/footer.php"; ?>
