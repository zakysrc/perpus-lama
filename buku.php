<?php

include "inc/admin_only.php";

require_once "koneksi.php";

/* =====================================================
   CEK LOGIN
===================================================== */

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}


/* =====================================================
   PENCARIAN
===================================================== */

$cari = "";

if (isset($_GET['cari'])) {
    $cari = trim($_GET['cari']);
}

$cari_safe = mysqli_real_escape_string($koneksi, $cari);


/* =====================================================
   QUERY DATA BUKU
===================================================== */

if ($cari != "") {

    $query = mysqli_query(
        $koneksi,
        "SELECT *
         FROM tbbuku
         WHERE
            kode_buku LIKE '%$cari_safe%'
            OR judul LIKE '%$cari_safe%'
            OR penulis LIKE '%$cari_safe%'
            OR penerbit LIKE '%$cari_safe%'
            OR isbn LIKE '%$cari_safe%'
            OR th_terbit LIKE '%$cari_safe%'
         ORDER BY kode_buku DESC"
    );

} else {

    $query = mysqli_query(
        $koneksi,
        "SELECT *
         FROM tbbuku
         ORDER BY kode_buku DESC"
    );
}


/* =====================================================
   CEK QUERY
===================================================== */

if (!$query) {

    die(
        "Query database gagal: " .
        htmlspecialchars(mysqli_error($koneksi))
    );

}


/* =====================================================
   HEADER
===================================================== */

$page_title = "Data buku";
include "inc/header.php";

?>


<!-- =====================================================
     HEADER HALAMAN
===================================================== -->

<div class="row mb-3 align-items-center">

    <div class="col-sm-8">

        <h1 class="m-0">

            <i class="fas fa-book text-primary mr-2"></i>

            Data Buku

        </h1>

        <p class="text-muted mb-0 mt-1">

            Kelola koleksi buku perpustakaan.

        </p>

    </div>


    <div class="col-sm-4">

        <ol class="breadcrumb float-sm-right mb-0">

            <li class="breadcrumb-item">

                <a href="beranda_admin.php">

                    <i class="fas fa-home mr-1"></i>

                    Dashboard

                </a>

            </li>

            <li class="breadcrumb-item active">

                Data Buku

            </li>

        </ol>

    </div>

</div>


<!-- =====================================================
     CARD UTAMA
===================================================== -->

<?php echo flash_tampil(); ?>
<div class="card card-primary card-outline">


    <!-- HEADER CARD -->

    <div class="card-header">

        <div class="row align-items-center">

            <div class="col-md-7">

                <h3 class="card-title mb-0">

                    <i class="fas fa-book mr-2"></i>

                    Koleksi Buku

                </h3>

            </div>


            <div class="col-md-5 text-md-right mt-2 mt-md-0">

                <a href="laporan.php?jenis=buku" class="btn btn-outline-primary mr-1"><i class="fas fa-print mr-1"></i>Laporan</a>

                <a
                    href="tambah_buku.php"
                    class="btn btn-primary"
                >

                    <i class="fas fa-plus mr-1"></i>

                    Tambah Buku

                </a>

            </div>

        </div>

    </div>


    <!-- BODY CARD -->

    <div class="card-body">


        <!-- =================================================
             STATISTIK
        ================================================== -->

        <div class="row mb-3">

            <div class="col-lg-4 col-md-6 col-sm-12">

                <div class="small-box bg-primary">

                    <div class="inner">

                        <h3>

                            <?php

                            echo mysqli_num_rows($query);

                            ?>

                        </h3>

                        <p>

                            Buku Ditemukan

                        </p>

                    </div>


                    <div class="icon">

                        <i class="fas fa-book"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             PENCARIAN
        ================================================== -->

        <form
            method="GET"
            action="buku.php"
            class="mb-4"
        >

            <div class="row">


                <div class="col-lg-8 col-md-9 col-sm-12">

                    <label class="font-weight-normal">

                        <i class="fas fa-search mr-1"></i>

                        Cari Buku

                    </label>


                    <div class="input-group">

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari judul, penulis, penerbit, ISBN, atau kode buku..."
                            value="<?php echo htmlspecialchars($cari); ?>"
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

                </div>


                <div class="col-lg-4 col-md-3 col-sm-12 d-flex align-items-end mt-2 mt-md-0">

                    <?php if ($cari != "") { ?>

                        <a
                            href="buku.php"
                            class="btn btn-secondary"
                        >

                            <i class="fas fa-sync-alt mr-1"></i>

                            Reset

                        </a>

                    <?php } ?>

                </div>

            </div>

        </form>


        <!-- =================================================
             TABEL
        ================================================== -->

        <div class="table-responsive">

            <table
                class="table table-bordered table-hover datatable"
                style="width:100%;"
            >

                <thead>

                    <tr>

                        <th
                            width="50"
                            class="text-center"
                        >
                            No
                        </th>

                        <th>
                            Kode Buku
                        </th>

                        <th>
                            Judul Buku
                        </th>

                        <th>
                            Penulis
                        </th>

                        <th>
                            Penerbit
                        </th>

                        <th>
                            ISBN
                        </th>

                        <th
                            width="90"
                            class="text-center"
                        >
                            Tahun
                        </th>

                        <th
                            width="100"
                            class="text-center"
                        >
                            Stok
                        </th>

                        <th
                            width="110"
                            class="text-center"
                        >
                            Aksi
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php

                    $no = 1;

                    if (mysqli_num_rows($query) > 0) {

                        while (
                            $data = mysqli_fetch_assoc($query)
                        ) {

                    ?>

                        <tr>


                            <!-- NO -->

                            <td class="text-center align-middle">

                                <?php

                                echo $no++;

                                ?>

                            </td>


                            <!-- KODE BUKU -->

                            <td class="align-middle">

                                <span class="badge badge-secondary">

                                    <i class="fas fa-barcode mr-1"></i>

                                    <?php

                                    echo htmlspecialchars(
                                        $data['kode_buku']
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- JUDUL -->

                            <td class="align-middle">

                                <div class="d-flex align-items-center">
                                    <?php $sampul = sampul_buku($data); ?>
                                    <?php if ($sampul) { ?>
                                        <img src="<?php echo e($sampul); ?>" class="pz-cover-sm mr-2" alt="">
                                    <?php } else { ?>
                                        <span class="pz-cover-sm mr-2 d-inline-flex align-items-center justify-content-center text-muted"><i class="fas fa-book"></i></span>
                                    <?php } ?>
                                    <span>
                                        <strong><?php echo e($data['judul']); ?></strong>
                                        <?php if (!empty($data['kategori'])) { ?><br><span class="badge badge-warning"><?php echo e($data['kategori']); ?></span><?php } ?>
                                    </span>
                                </div>

                            </td>


                            <!-- PENULIS -->

                            <td class="align-middle">

                                <?php

                                echo htmlspecialchars(
                                    $data['penulis']
                                );

                                ?>

                            </td>


                            <!-- PENERBIT -->

                            <td class="align-middle">

                                <?php

                                echo htmlspecialchars(
                                    $data['penerbit']
                                );

                                ?>

                            </td>


                            <!-- ISBN -->

                            <td class="align-middle">

                                <?php

                                if (!empty($data['isbn'])) {

                                    echo htmlspecialchars(
                                        $data['isbn']
                                    );

                                } else {

                                    echo '<span class="text-muted">-</span>';

                                }

                                ?>

                            </td>


                            <!-- TAHUN -->

                            <td class="text-center align-middle">

                                <?php

                                if (!empty($data['th_terbit'])) {

                                    echo htmlspecialchars(
                                        $data['th_terbit']
                                    );

                                } else {

                                    echo "-";

                                }

                                ?>

                            </td>


                            <!-- STOK -->

                            <td class="text-center align-middle">

                                <?php

                                $stok = (int) $data['jml_buku'];


                                if ($stok <= 0) {

                                ?>

                                    <span class="badge badge-danger px-2 py-2">

                                        <i class="fas fa-times-circle mr-1"></i>

                                        Habis

                                    </span>

                                <?php

                                } elseif ($stok <= 3) {

                                ?>

                                    <span class="badge badge-warning px-2 py-2">

                                        <i class="fas fa-exclamation-triangle mr-1"></i>

                                        <?php echo $stok; ?>

                                        Buku

                                    </span>

                                <?php

                                } else {

                                ?>

                                    <span class="badge badge-success px-2 py-2">

                                        <i class="fas fa-check-circle mr-1"></i>

                                        <?php echo $stok; ?>

                                        Buku

                                    </span>

                                <?php

                                }

                                ?>

                            </td>


                            <!-- AKSI -->

                            <td class="text-center align-middle">

                                <div
                                    class="btn-group"
                                    role="group"
                                >


                                    <!-- EDIT -->

                                    <a
                                        href="edit_buku.php?kode_buku=<?php echo urlencode($data['kode_buku']); ?>"
                                        class="btn btn-outline-primary btn-sm"
                                        title="Edit Buku"
                                    >

                                        <i class="fas fa-edit"></i>

                                    </a>


                                    <!-- HAPUS -->

                                    <form
                                        method="post"
                                        action="hapus_buku.php"
                                        class="d-inline"
                                        onsubmit="return confirm(<?php echo htmlspecialchars(json_encode('Yakin ingin menghapus buku ' . $data['judul'] . '?', JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>);"
                                    >

                                        <input
                                            type="hidden"
                                            name="kode_buku"
                                            value="<?php echo (int) $data['kode_buku']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                            title="Hapus Buku"
                                        >

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>

                    <?php

                        }

                    } else {

                    ?>

                        <!-- DATA KOSONG -->

                        <tr>

                            <td
                                colspan="9"
                                class="text-center py-5"
                            >

                                <div class="text-muted">

                                    <i
                                        class="fas fa-book-open fa-3x mb-3"
                                    ></i>


                                    <h5>

                                        <?php

                                        if ($cari != "") {

                                            echo "Buku tidak ditemukan.";

                                        } else {

                                            echo "Belum ada data buku.";

                                        }

                                        ?>

                                    </h5>


                                    <p class="mb-3">

                                        <?php

                                        if ($cari != "") {

                                            echo "Coba gunakan kata pencarian yang berbeda.";

                                        } else {

                                            echo "Silakan tambahkan koleksi buku terlebih dahulu.";

                                        }

                                        ?>

                                    </p>


                                    <?php if ($cari == "") { ?>

                                        <a
                                            href="tambah_buku.php"
                                            class="btn btn-primary"
                                        >

                                            <i class="fas fa-plus mr-1"></i>

                                            Tambah Buku

                                        </a>

                                    <?php } ?>

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


    <!-- FOOTER -->

    <div class="card-footer">

        <small class="text-muted">

            <i class="fas fa-info-circle mr-1"></i>

            Kelola data koleksi buku, stok, dan informasi penerbitan.

        </small>

    </div>

</div>


<?php

include "inc/footer.php";

?>