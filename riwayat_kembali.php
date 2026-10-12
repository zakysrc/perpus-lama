<?php

include "inc/admin_only.php";

/* =========================================================
   SESSION
========================================================= */

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['username'])) {

    header("Location: login.php");

    exit;
}


/* =========================================================
   KONEKSI DATABASE
========================================================= */

include "koneksi.php";


/* =========================================================
   USER LOGIN
========================================================= */

$username_login =
    $_SESSION['username'];


/* =========================================================
   CEK ROLE USER
========================================================= */

$is_user = false;


/*
   Jika tabel tbuser memiliki kolom level/role,
   bagian ini akan menentukan user atau administrator.
*/

$query_user = mysqli_query(
    $koneksi,
    "SELECT *
     FROM tbuser
     WHERE username = '" .
        mysqli_real_escape_string(
            $koneksi,
            $username_login
        ) .
        "'
     LIMIT 1"
);


if ($query_user && mysqli_num_rows($query_user) > 0) {

    $user_data =
        mysqli_fetch_assoc($query_user);


    if (
        isset($user_data['level']) &&
        strtolower(
            trim($user_data['level'])
        ) == 'user'
    ) {

        $is_user = true;
    }


    if (
        isset($user_data['role']) &&
        strtolower(
            trim($user_data['role'])
        ) == 'user'
    ) {

        $is_user = true;
    }
}


/* =========================================================
   PENCARIAN
========================================================= */

$cari = "";

if (isset($_GET['cari'])) {

    $cari =
        trim($_GET['cari']);
}


/* =========================================================
   TOTAL RIWAYAT
========================================================= */

$query_total_riwayat = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM tbpinjam
     WHERE status = 'Kembali'"
);


if ($query_total_riwayat) {

    $row_total_riwayat =
        mysqli_fetch_assoc(
            $query_total_riwayat
        );

    $total_riwayat =
        (int) $row_total_riwayat['total'];
} else {

    $total_riwayat = 0;
}


/* =========================================================
   TOTAL DENDA
========================================================= */

$query_total_denda = mysqli_query(
    $koneksi,
    "SELECT
        COALESCE(SUM(denda), 0) AS total_denda,
        COALESCE(SUM(CASE WHEN denda_dibayar = 0 THEN denda ELSE 0 END), 0) AS total_denda_belum
     FROM tbpinjam
     WHERE status = 'Kembali'"
);


if ($query_total_denda) {

    $row_total_denda =
        mysqli_fetch_assoc(
            $query_total_denda
        );

    $total_denda =
        (int) (
            $row_total_denda['total_denda']
            ?? 0
        );

    $total_denda_belum =
        (int) (
            $row_total_denda['total_denda_belum']
            ?? 0
        );
} else {

    $total_denda = 0;
    $total_denda_belum = 0;
}


/* =========================================================
   QUERY RIWAYAT
========================================================= */

$where_cari = "";


if ($cari !== '') {

    $cari_sql =
        mysqli_real_escape_string(
            $koneksi,
            $cari
        );


    $where_cari = "

        AND (

            tbanggota.nama LIKE '%$cari_sql%'

            OR tbbuku.judul LIKE '%$cari_sql%'

            OR tbbuku.penulis LIKE '%$cari_sql%'

            OR tbpinjam.no_pinjam LIKE '%$cari_sql%'

            OR tbpinjam.id_ang LIKE '%$cari_sql%'

        )

    ";
}


$query = mysqli_query(
    $koneksi,

    "SELECT

        tbpinjam.*,

        tbanggota.nama,

        tbbuku.judul,

        tbbuku.penulis

     FROM tbpinjam

     INNER JOIN tbanggota

        ON tbpinjam.id_ang =
           tbanggota.id_ang

     INNER JOIN tbbuku

        ON tbpinjam.kode_buku =
           tbbuku.kode_buku

     WHERE tbpinjam.status = 'Kembali'

     $where_cari

     ORDER BY
        tbpinjam.no_pinjam DESC"
);


/* =========================================================
   HEADER
========================================================= */

$page_title = "Riwayat & denda";
include "inc/header.php";

?>


<!-- =========================================================
     CONTENT HEADER
========================================================= -->

<div class="row mb-3">

    <div class="col-sm-6">

        <h1 class="m-0">

            <i class="fas fa-history mr-2"></i>

            Riwayat Pengembalian

        </h1>

    </div>


    <div class="col-sm-6">

        <ol class="breadcrumb float-sm-right">

            <li class="breadcrumb-item">

                <a href="beranda_admin.php">

                    Dashboard

                </a>

            </li>

            <li class="breadcrumb-item active">

                Riwayat

            </li>

        </ol>

    </div>

</div>


<!-- =========================================================
     STATISTIK
========================================================= -->

<div class="row">


    <!-- TOTAL RIWAYAT -->

    <div class="col-lg-6 col-md-6 col-sm-12">

        <div class="small-box bg-primary">

            <div class="inner">

                <h3>

                    <?php

                    echo number_format(
                        $total_riwayat,
                        0,
                        ',',
                        '.'
                    );

                    ?>

                </h3>

                <p>

                    Total Pengembalian

                </p>

            </div>


            <div class="icon">

                <i class="fas fa-history"></i>

            </div>

        </div>

    </div>


    <!-- TOTAL DENDA -->

    <div class="col-lg-6 col-md-6 col-sm-12">

        <div class="small-box bg-warning">

            <div class="inner">

                <h3>

                    Rp
                    <?php

                    echo number_format(
                        $total_denda,
                        0,
                        ',',
                        '.'
                    );

                    ?>

                </h3>

                <p>

                    Total Denda

                    <?php if ($total_denda_belum > 0) { ?>
                        <br>
                        <small>
                            Belum dibayar: Rp<?php echo number_format($total_denda_belum, 0, ',', '.'); ?>
                        </small>
                    <?php } ?>

                </p>

            </div>


            <div class="icon">

                <i class="fas fa-money-bill-wave"></i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     CARD RIWAYAT
========================================================= -->

<div class="card card-primary card-outline">


    <!-- CARD HEADER -->

    <div class="card-header">

        <div class="row align-items-center">

            <div class="col-md-7">

                <h3 class="card-title mb-0">

                    <i class="fas fa-list mr-2"></i>

                    Daftar Riwayat Pengembalian

                </h3>

            </div>


            <div
                class="col-md-5 text-md-right mt-2 mt-md-0">

                <span
                    class="badge badge-success"
                    style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        width:155px;
                        min-width:155px;
                        height:30px;
                        padding:0;
                        margin:0;
                        white-space:nowrap;
                        box-sizing:border-box;
                    ">

                    <i class="fas fa-check-circle mr-1"></i>

                    Buku Dikembalikan

                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
         CARD BODY
    ====================================================== -->

    <div class="card-body">


        <!-- =================================================
             FORM PENCARIAN
        ================================================== -->

        <form
            method="GET"
            action="riwayat_kembali.php"
            class="mb-4">

            <div class="row">

                <div class="col-lg-8 col-md-8 col-sm-12">

                    <div class="input-group">

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari nama anggota, judul buku, atau penulis..."
                            value="<?php
                                    echo htmlspecialchars(
                                        $cari
                                    );
                                    ?>">


                        <div class="input-group-append">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="fas fa-search mr-1"></i>

                                Cari

                            </button>

                        </div>

                    </div>

                </div>


                <div
                    class="col-lg-4 col-md-4 col-sm-12 mt-2 mt-md-0">

                    <?php

                    if ($cari !== '') {

                    ?>

                        <a
                            href="riwayat_kembali.php"
                            class="btn btn-secondary">

                            <i
                                class="fas fa-sync-alt mr-1"></i>

                            Reset

                        </a>

                    <?php

                    }

                    ?>

                </div>

            </div>

        </form>


        <!-- =================================================
             INFO PENCARIAN
        ================================================== -->

        <?php

        if ($cari !== '') {

        ?>

            <div class="alert alert-info">

                <i class="fas fa-search mr-2"></i>

                Hasil pencarian untuk:

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $cari
                    );

                    ?>

                </strong>

            </div>

        <?php

        }

        ?>


        <!-- =================================================
             TABEL
        ================================================== -->

        <div class="table-responsive">

            <table
                class="table table-bordered table-hover datatable"
                style="width:100%;">

                <thead>

                    <tr>

                        <th
                            width="50"
                            class="text-center">

                            No

                        </th>


                        <th>

                            Anggota

                        </th>


                        <th>

                            Buku

                        </th>


                        <th
                            width="125"
                            class="text-center">

                            Tanggal Pinjam

                        </th>


                        <th
                            width="110"
                            class="text-center">

                            Jumlah

                        </th>


                        <th
                            width="155"
                            class="text-center">

                            Denda

                        </th>


                        <th
                            width="170"
                            class="text-center">

                            Status Denda

                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php

                    $no = 1;


                    if (
                        $query &&
                        mysqli_num_rows($query) > 0
                    ) {


                        while (
                            $data =
                            mysqli_fetch_assoc(
                                $query
                            )
                        ) {

                    ?>

                            <tr>


                                <!-- =================================
                                     NO
                                ================================== -->

                                <td
                                    class="text-center align-middle">

                                    <?php

                                    echo $no++;

                                    ?>

                                    <br>
                                    <a href="bukti.php?no=<?php echo (int) $data['no_pinjam']; ?>" class="small" title="Cetak bukti pengembalian"><i class="fas fa-receipt"></i> Bukti</a>

                                </td>


                                <!-- =================================
                                     ANGGOTA
                                ================================== -->

                                <td
                                    class="align-middle">

                                    <div>

                                        <strong>

                                            <i
                                                class="fas fa-user text-muted mr-1"></i>

                                            <?php

                                            echo htmlspecialchars(
                                                $data['nama']
                                            );

                                            ?>

                                        </strong>

                                    </div>


                                    <small
                                        class="text-muted">

                                        ID:

                                        <?php

                                        echo htmlspecialchars(
                                            $data['id_ang']
                                        );

                                        ?>

                                    </small>

                                </td>


                                <!-- =================================
                                     BUKU
                                ================================== -->

                                <td
                                    class="align-middle">

                                    <div>

                                        <strong>

                                            <i
                                                class="fas fa-book text-muted mr-1"></i>

                                            <?php

                                            echo htmlspecialchars(
                                                $data['judul']
                                            );

                                            ?>

                                        </strong>

                                    </div>


                                    <?php

                                    if (
                                        !empty($data['penulis'])
                                    ) {

                                    ?>

                                        <small
                                            class="text-muted">

                                            Penulis:

                                            <?php

                                            echo htmlspecialchars(
                                                $data['penulis']
                                            );

                                            ?>

                                        </small>

                                    <?php

                                    }

                                    ?>

                                </td>


                                <!-- =================================
                                     TANGGAL PINJAM
                                ================================== -->

                                <td
                                    class="text-center align-middle">

                                    <?php

                                    if (
                                        !empty($data['tgl_pinjam'])
                                    ) {

                                        echo date(
                                            'd-m-Y',
                                            strtotime(
                                                $data['tgl_pinjam']
                                            )
                                        );
                                    } else {

                                        echo "-";
                                    }

                                    ?>

                                </td>


                                <!-- =================================
                                     JUMLAH
                                ================================== -->

                                <td
                                    class="text-center align-middle">

                                    <span
                                        class="badge badge-primary"
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            justify-content:center;
                                            width:85px;
                                            min-width:85px;
                                            height:30px;
                                            padding:0;
                                            margin:0;
                                            white-space:nowrap;
                                            box-sizing:border-box;
                                        ">

                                        <i
                                            class="fas fa-book mr-1"></i>

                                        <?php

                                        echo (int)
                                        $data['jml_pinjam'];

                                        ?>

                                        Buku

                                    </span>

                                </td>


                                <!-- =================================
                                     DENDA
                                ================================== -->

                                <td
                                    class="text-center align-middle">

                                    <?php

                                    $denda =
                                        (int)
                                        $data['denda'];


                                    if ($denda > 0) {

                                    ?>

                                        <!-- =========================
                                             DENDA MERAH
                                        ========================== -->

                                        <span
                                            class="badge badge-danger"
                                            style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                width:135px;
                                                min-width:135px;
                                                height:30px;
                                                padding:0;
                                                margin:0;
                                                white-space:nowrap;
                                                box-sizing:border-box;
                                            ">

                                            <i
                                                class="fas fa-exclamation-circle mr-1"></i>

                                            Rp

                                            <?php

                                            echo number_format(
                                                $denda,
                                                0,
                                                ',',
                                                '.'
                                            );

                                            ?>

                                        </span>

                                    <?php

                                    } else {

                                    ?>

                                        <!-- =========================
                                             TIDAK ADA DENDA HIJAU
                                        ========================== -->

                                        <span
                                            class="badge badge-success"
                                            style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                width:135px;
                                                min-width:135px;
                                                height:30px;
                                                padding:0;
                                                margin:0;
                                                white-space:nowrap;
                                                box-sizing:border-box;
                                            ">

                                            <i
                                                class="fas fa-check-circle mr-1"></i>

                                            Tidak Ada Denda

                                        </span>

                                    <?php

                                    }

                                    ?>

                                </td>


                                <!-- =================================
                                     STATUS
                                ================================== -->

                                <td
                                    class="text-center align-middle">

                                    <?php if ($denda > 0) { ?>

                                        <?php if ((int) $data['denda_dibayar'] === 1) { ?>

                                            <span class="badge badge-success mb-1" style="display:inline-block;">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Lunas
                                            </span>
                                            <br>
                                            <form method="post" action="tandai_denda.php" class="d-inline">
                                                <input type="hidden" name="no_pinjam" value="<?php echo (int) $data['no_pinjam']; ?>">
                                                <input type="hidden" name="status" value="0">
                                                <button type="submit" class="btn btn-link btn-sm p-0">
                                                    Batalkan
                                                </button>
                                            </form>

                                        <?php } else { ?>

                                            <span class="badge badge-danger mb-1" style="display:inline-block;">
                                                <i class="fas fa-times-circle mr-1"></i>
                                                Belum Lunas
                                            </span>
                                            <br>
                                            <form method="post" action="tandai_denda.php" class="d-inline">
                                                <input type="hidden" name="no_pinjam" value="<?php echo (int) $data['no_pinjam']; ?>">
                                                <input type="hidden" name="status" value="1">
                                                <button type="submit" class="btn btn-link btn-sm p-0">
                                                    Tandai Lunas
                                                </button>
                                            </form>

                                        <?php } ?>

                                    <?php } else { ?>

                                        <span class="text-muted">&mdash;</span>

                                    <?php } ?>

                                </td>


                            </tr>

                        <?php

                        }
                    } else {

                        ?>

                        <!-- =================================
                             DATA KOSONG
                        ================================== -->

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-5">

                                <div
                                    class="text-muted">

                                    <i
                                        class="fas fa-history fa-3x mb-3"></i>


                                    <h5>

                                        <?php

                                        if (
                                            $cari !== ''
                                        ) {

                                            echo
                                            "Riwayat tidak ditemukan.";
                                        } else {

                                            echo
                                            "Belum ada riwayat pengembalian.";
                                        }

                                        ?>

                                    </h5>


                                    <p class="mb-0">

                                        <?php

                                        if (
                                            $cari !== ''
                                        ) {

                                            echo
                                            "Coba gunakan kata kunci pencarian yang berbeda.";
                                        } else {

                                            echo
                                            "Data pengembalian akan muncul di sini.";
                                        }

                                        ?>

                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php

                    }

                    ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         CARD FOOTER
    ====================================================== -->

    <div class="card-footer">

        <small class="text-muted">

            <i
                class="fas fa-info-circle mr-1"></i>

            Data menampilkan seluruh transaksi buku
            yang telah dikembalikan.

        </small>

    </div>

</div>


<?php

/* =========================================================
   FOOTER
========================================================= */

include "inc/footer.php";

?>