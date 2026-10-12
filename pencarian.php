<?php

/* =========================================================
   PENCARIAN GLOBAL - PERPUS ZAKY
   Tidak mengubah struktur database.
========================================================= */

$page_title = "Pencarian";
require_once "inc/header.php";

$q = trim($_GET['q'] ?? '');
$q_safe = mysqli_real_escape_string($koneksi, $q);

$anggota = [];
$buku = [];
$transaksi = [];

if ($q !== '') {

    /* =====================================================
       CARI ANGGOTA
    ====================================================== */

    /* Data anggota hanya untuk admin */
    $query_anggota = !$is_admin ? false : mysqli_query(
        $koneksi,
        "SELECT id_ang, nama, telp, email, masa_berlaku
         FROM tbanggota
         WHERE CAST(id_ang AS CHAR) LIKE '%$q_safe%'
            OR nama LIKE '%$q_safe%'
            OR telp LIKE '%$q_safe%'
            OR email LIKE '%$q_safe%'
         ORDER BY nama ASC
         LIMIT 10"
    );

    if ($query_anggota) {
        while ($row = mysqli_fetch_assoc($query_anggota)) {
            $anggota[] = $row;
        }
    }


    /* =====================================================
       CARI BUKU
    ====================================================== */

    $query_buku = mysqli_query(
        $koneksi,
        "SELECT kode_buku, judul, penulis, penerbit, isbn, jml_buku
         FROM tbbuku
         WHERE CAST(kode_buku AS CHAR) LIKE '%$q_safe%'
            OR judul LIKE '%$q_safe%'
            OR penulis LIKE '%$q_safe%'
            OR penerbit LIKE '%$q_safe%'
            OR isbn LIKE '%$q_safe%'
         ORDER BY judul ASC
         LIMIT 10"
    );

    if ($query_buku) {
        while ($row = mysqli_fetch_assoc($query_buku)) {
            $buku[] = $row;
        }
    }


    /* =====================================================
       CARI TRANSAKSI
    ====================================================== */

    $query_transaksi = mysqli_query(
        $koneksi,
        "SELECT
            p.no_pinjam,
            p.tgl_pinjam,
            p.tgl_jatuh_tempo,
            p.status,
            p.jml_pinjam,
            a.nama,
            b.judul
         FROM tbpinjam p
         INNER JOIN tbanggota a ON p.id_ang = a.id_ang
         INNER JOIN tbbuku b ON p.kode_buku = b.kode_buku
         WHERE (CAST(p.no_pinjam AS CHAR) LIKE '%$q_safe%'
            OR a.nama LIKE '%$q_safe%'
            OR b.judul LIKE '%$q_safe%'
            OR p.status LIKE '%$q_safe%')
            /* Anggota hanya melihat transaksinya sendiri */
            " . ($is_admin ? "" : " AND p.id_ang = " . (int) $id_ang_login) . "
         ORDER BY p.tgl_pinjam DESC, p.no_pinjam DESC
         LIMIT 10"
    );

    if ($query_transaksi) {
        while ($row = mysqli_fetch_assoc($query_transaksi)) {
            $transaksi[] = $row;
        }
    }
}

$total_hasil =
    count($anggota) +
    count($buku) +
    count($transaksi);

?>

<div class="container-fluid">

    <div class="row">

        <div class="col-12">

            <div class="card card-primary card-outline">

                <div class="card-header">

                    <h3 class="card-title">
                        <i class="fas fa-search mr-2"></i>
                        Pencarian Global
                    </h3>

                </div>

                <div class="card-body">

                    <form method="GET" action="pencarian.php">

                        <div class="input-group input-group-lg">

                            <input
                                type="search"
                                name="q"
                                value="<?php echo htmlspecialchars($q); ?>"
                                class="form-control"
                                placeholder="Cari anggota, buku, atau transaksi..."
                                autofocus
                            >

                            <div class="input-group-append">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    <i class="fas fa-search mr-1"></i>
                                    Cari
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>


    <?php if ($q === '') { ?>

        <div class="card">

            <div class="card-body text-center py-5">

                <i class="fas fa-search fa-3x text-muted mb-3"></i>

                <h4>Mulai pencarian</h4>

                <p class="text-muted mb-0">
                    Cari nama anggota, judul buku, penulis, ISBN,
                    atau nomor transaksi.
                </p>

            </div>

        </div>

    <?php } else { ?>

        <div class="mb-3">

            <h5 class="mb-1">
                Hasil pencarian untuk:
                <strong><?php echo htmlspecialchars($q); ?></strong>
            </h5>

            <small class="text-muted">
                <?php echo $total_hasil; ?> hasil ditemukan
            </small>

        </div>


        <!-- =================================================
             ANGGOTA
        ================================================== -->

        <?php if ($is_admin && count($anggota) > 0) { ?>

            <div class="card card-outline card-primary">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users mr-2"></i>
                        Anggota
                    </h3>
                    <span class="badge badge-primary float-right">
                        <?php echo count($anggota); ?>
                    </span>
                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Telepon</th>
                                    <th>Email</th>
                                    <th>Masa Berlaku</th>
                                    <th width="90">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($anggota as $row) { ?>

                                    <tr>

                                        <td><?php echo htmlspecialchars($row['id_ang']); ?></td>

                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nama']); ?></strong>
                                        </td>

                                        <td><?php echo htmlspecialchars($row['telp'] ?? '-'); ?></td>

                                        <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>

                                        <td><?php echo htmlspecialchars($row['masa_berlaku'] ?? '-'); ?></td>

                                        <td>
                                            <a
                                                href="anggota.php?cari=<?php echo urlencode($row['nama']); ?>"
                                                class="btn btn-sm btn-primary"
                                                title="Buka data anggota"
                                            >
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>

                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        <?php } ?>


        <!-- =================================================
             BUKU
        ================================================== -->

        <?php if (count($buku) > 0) { ?>

            <div class="card card-outline card-success">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-book mr-2"></i>
                        Buku
                    </h3>
                    <span class="badge badge-success float-right">
                        <?php echo count($buku); ?>
                    </span>
                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Judul</th>
                                    <th>Penulis</th>
                                    <th>Penerbit</th>
                                    <th>ISBN</th>
                                    <th>Stok</th>
                                    <th width="90">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($buku as $row) { ?>

                                    <tr>

                                        <td><?php echo htmlspecialchars($row['kode_buku']); ?></td>

                                        <td>
                                            <strong><?php echo htmlspecialchars($row['judul']); ?></strong>
                                        </td>

                                        <td><?php echo htmlspecialchars($row['penulis'] ?? '-'); ?></td>

                                        <td><?php echo htmlspecialchars($row['penerbit'] ?? '-'); ?></td>

                                        <td><?php echo htmlspecialchars($row['isbn'] ?? '-'); ?></td>

                                        <td>
                                            <?php
                                            $stok = (int) $row['jml_buku'];
                                            if ($stok <= 0) {
                                                echo '<span class="badge badge-danger">Habis</span>';
                                            } elseif ($stok <= 2) {
                                                echo '<span class="badge badge-warning">' . $stok . ' buku</span>';
                                            } else {
                                                echo '<span class="badge badge-success">' . $stok . ' buku</span>';
                                            }
                                            ?>
                                        </td>

                                        <td>
                                            <a
                                                href="detail_buku.php?kode_buku=<?php echo urlencode($row['kode_buku']); ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Detail buku"
                                            >
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>

                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        <?php } ?>


        <!-- =================================================
             TRANSAKSI
        ================================================== -->

        <?php if (count($transaksi) > 0) { ?>

            <div class="card card-outline card-warning">

                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exchange-alt mr-2"></i>
                        Transaksi
                    </h3>
                    <span class="badge badge-warning float-right">
                        <?php echo count($transaksi); ?>
                    </span>
                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>
                                <tr>
                                    <th>No. Pinjam</th>
                                    <th>Anggota</th>
                                    <th>Buku</th>
                                    <th>Tanggal</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th width="90">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($transaksi as $row) { ?>

                                    <tr>

                                        <td><?php echo htmlspecialchars($row['no_pinjam']); ?></td>

                                        <td><?php echo htmlspecialchars($row['nama']); ?></td>

                                        <td><?php echo htmlspecialchars($row['judul']); ?></td>

                                        <td><?php echo htmlspecialchars($row['tgl_pinjam']); ?></td>

                                        <td><?php echo htmlspecialchars($row['jml_pinjam']); ?></td>

                                        <td>
                                            <?php echo badge_status($row); ?>
                                        </td>

                                        <td>
                                            <?php if ($is_admin) { ?>
                                                <a
                                                    href="riwayat_kembali.php"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Lihat riwayat"
                                                >
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php } else { ?>
                                                <a
                                                    href="riwayat_user.php"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Lihat riwayat saya"
                                                >
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php } ?>
                                        </td>

                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        <?php } ?>


        <?php if ($total_hasil === 0) { ?>

            <div class="card">

                <div class="card-body text-center py-5">

                    <i class="fas fa-search-minus fa-3x text-muted mb-3"></i>

                    <h4>Tidak ada hasil</h4>

                    <p class="text-muted mb-0">
                        Tidak ditemukan anggota, buku, atau transaksi yang sesuai.
                    </p>

                </div>

            </div>

        <?php } ?>

    <?php } ?>

</div>

<?php require_once "inc/footer.php"; ?>
