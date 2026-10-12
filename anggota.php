<?php

include "inc/admin_only.php";

/* =========================================================
   DATA ANGGOTA - PERPUS ZAKY
========================================================= */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Data anggota";
include "inc/header.php";
include "koneksi.php";


/* =========================================================
   PENCARIAN
========================================================= */

$cari = isset($_GET['cari'])
    ? trim($_GET['cari'])
    : "";

$cari_safe = mysqli_real_escape_string(
    $koneksi,
    $cari
);


/* =========================================================
   QUERY DATA ANGGOTA
========================================================= */

if ($cari != "") {

    $query = mysqli_query(
        $koneksi,
        "SELECT *
         FROM tbanggota
         WHERE
            nama LIKE '%$cari_safe%'
            OR telp LIKE '%$cari_safe%'
            OR email LIKE '%$cari_safe%'
            OR alamat LIKE '%$cari_safe%'
         ORDER BY id_ang DESC"
    );

} else {

    $query = mysqli_query(
        $koneksi,
        "SELECT *
         FROM tbanggota
         ORDER BY id_ang DESC"
    );
}


/* =========================================================
   CEK QUERY
========================================================= */

if (!$query) {
    die(
        "Query data anggota gagal: " .
        mysqli_error($koneksi)
    );
}

?>

<!-- =========================================================
     HEADER HALAMAN
========================================================= -->

<div class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <div class="col-sm-6">

                <h1 class="m-0">

                    <i class="fas fa-users mr-2"></i>

                    Data Anggota

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

                        Data Anggota

                    </li>

                </ol>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     CONTENT
========================================================= -->

<section class="content">

    <div class="container-fluid">


        <!-- =================================================
             CARD
        ================================================== -->

        <div class="card card-primary card-outline">


            <!-- =================================================
                 CARD HEADER
            ================================================== -->

            <div class="card-header">

                <div class="row align-items-center">

                    <div class="col-md-6">

                        <h3 class="card-title">

                            <i class="fas fa-users mr-2"></i>

                            Daftar Anggota

                        </h3>

                    </div>


                    <div class="col-md-6 text-md-right mt-2 mt-md-0">

                        <a href="laporan.php?jenis=anggota" class="btn btn-outline-primary mr-1"><i class="fas fa-print mr-1"></i>Laporan</a>

                        <a
                            href="tambah_anggota.php"
                            class="btn btn-primary">

                            <i class="fas fa-user-plus mr-1"></i>

                            Tambah Anggota

                        </a>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 CARD BODY
            ================================================== -->

            <div class="card-body">


                <!-- =================================================
                     PENCARIAN
                ================================================== -->

                <form
                    method="GET"
                    action="anggota.php"
                    class="mb-4">

                    <div class="row">

                        <div class="col-md-8">

                            <div class="input-group">

                                <input
                                    type="text"
                                    name="cari"
                                    class="form-control"
                                    placeholder="Cari nama, telepon, email, atau alamat..."
                                    value="<?php echo htmlspecialchars($cari); ?>"
                                >

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


                        <div class="col-md-4 mt-2 mt-md-0">

                            <?php if ($cari != "") { ?>

                                <a
                                    href="anggota.php"
                                    class="btn btn-secondary">

                                    <i class="fas fa-sync-alt mr-1"></i>

                                    Reset

                                </a>

                            <?php } ?>

                        </div>

                    </div>

                </form>


                <!-- =================================================
                     JUMLAH DATA
                ================================================== -->

                <div class="mb-3">

                    <span class="badge badge-info">

                        <i class="fas fa-users mr-1"></i>

                        <?php echo mysqli_num_rows($query); ?>

                        Anggota

                    </span>

                </div>


                <!-- =================================================
                     TABLE
                ================================================== -->

                <div class="table-responsive">

                    <!--
                        PENTING:
                        Tidak menggunakan class "datatable"
                        agar tidak diproses DataTables.
                    -->

                    <table
                        class="table table-bordered table-hover"
                        style="width:100%;">

                        <thead>

                            <tr>

                                <th
                                    width="5%"
                                    class="text-center">

                                    No

                                </th>


                                <th>

                                    ID Anggota

                                </th>


                                <th
                                    width="90px"
                                    class="text-center">

                                    Foto

                                </th>


                                <th>

                                    Nama

                                </th>


                                <th>

                                    Jenis Kelamin

                                </th>


                                <th>

                                    Tanggal Lahir

                                </th>


                                <th>

                                    Telepon

                                </th>


                                <th>

                                    Email

                                </th>


                                <th>

                                    Masa Berlaku

                                </th>


                                <th
                                    width="100px"
                                    class="text-center">

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


                                        <!-- =================================================
                                             NO
                                        ================================================== -->

                                        <td class="text-center align-middle">

                                            <?php echo $no++; ?>

                                        </td>


                                        <!-- =================================================
                                             ID ANGGOTA
                                        ================================================== -->

                                        <td class="align-middle">

                                            <span class="badge badge-secondary">

                                                <?php

                                                echo htmlspecialchars(
                                                    $data['id_ang']
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <!-- =================================================
                                             FOTO
                                        ================================================== -->

                                        <td class="text-center align-middle">

                                            <?php

                                            $foto = trim(
                                                $data['foto'] ?? ''
                                            );


                                            if ($foto != "") {

                                                $file_foto =
                                                    __DIR__ .
                                                    "/assets/img/" .
                                                    $foto;


                                                if (
                                                    file_exists($file_foto)
                                                ) {

                                            ?>

                                                    <img
                                                        src="assets/img/<?php echo htmlspecialchars($foto); ?>"
                                                        alt="Foto Anggota"
                                                        style="
                                                            width:60px;
                                                            height:60px;
                                                            object-fit:cover;
                                                            border-radius:8px;
                                                            border:1px solid #ddd;
                                                        "
                                                    >

                                            <?php

                                                } else {

                                            ?>

                                                    <div
                                                        title="File foto tidak ditemukan"
                                                        style="
                                                            width:60px;
                                                            height:60px;
                                                            display:flex;
                                                            align-items:center;
                                                            justify-content:center;
                                                            margin:auto;
                                                            background:#f2f5f9;
                                                            border-radius:8px;
                                                            color:#b23b3b;
                                                        "
                                                    >

                                                        <i class="fas fa-image fa-lg"></i>

                                                    </div>

                                            <?php

                                                }

                                            } else {

                                            ?>

                                                <div
                                                    style="
                                                        width:60px;
                                                        height:60px;
                                                        display:flex;
                                                        align-items:center;
                                                        justify-content:center;
                                                        margin:auto;
                                                        background:#f2f5f9;
                                                        border-radius:8px;
                                                        color:#9ca3af;
                                                    "
                                                >

                                                    <i class="fas fa-user fa-lg"></i>

                                                </div>

                                            <?php

                                            }

                                            ?>

                                        </td>


                                        <!-- =================================================
                                             NAMA
                                        ================================================== -->

                                        <td class="align-middle">

                                            <strong>

                                                <?php

                                                echo htmlspecialchars(
                                                    $data['nama']
                                                );

                                                ?>

                                            </strong>

                                        </td>


                                        <!-- =================================================
                                             JENIS KELAMIN
                                        ================================================== -->

                                        <td class="align-middle">

                                            <?php

                                            $jk = strtolower(
                                                trim(
                                                    $data['jk'] ?? ''
                                                )
                                            );


                                            if (
                                                $jk == 'l' ||
                                                $jk == 'laki-laki' ||
                                                $jk == 'laki laki'
                                            ) {

                                            ?>

                                                <span class="badge badge-primary">

                                                    <i class="fas fa-mars mr-1"></i>

                                                    Laki-laki

                                                </span>

                                            <?php

                                            } else {

                                            ?>

                                                <span class="badge badge-danger">

                                                    <i class="fas fa-venus mr-1"></i>

                                                    Perempuan

                                                </span>

                                            <?php

                                            }

                                            ?>

                                        </td>


                                        <!-- =================================================
                                             TANGGAL LAHIR
                                        ================================================== -->

                                        <td class="align-middle">

                                            <?php

                                            if (
                                                !empty($data['tgl_lahir'])
                                            ) {

                                                echo date(
                                                    "d-m-Y",
                                                    strtotime(
                                                        $data['tgl_lahir']
                                                    )
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- =================================================
                                             TELEPON
                                        ================================================== -->

                                        <td class="align-middle">

                                            <?php

                                            if (
                                                !empty($data['telp'])
                                            ) {

                                            ?>

                                                <i
                                                    class="fas fa-phone text-muted mr-1">
                                                </i>

                                                <?php

                                                echo htmlspecialchars(
                                                    $data['telp']
                                                );

                                                ?>

                                            <?php

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- =================================================
                                             EMAIL
                                        ================================================== -->

                                        <td class="align-middle">

                                            <?php

                                            if (
                                                !empty($data['email'])
                                            ) {

                                            ?>

                                                <i
                                                    class="fas fa-envelope text-muted mr-1">
                                                </i>

                                                <?php

                                                echo htmlspecialchars(
                                                    $data['email']
                                                );

                                                ?>

                                            <?php

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- =================================================
                                             MASA BERLAKU
                                        ================================================== -->

                                        <td class="align-middle">

                                            <?php

                                            if (
                                                !empty(
                                                    $data['masa_berlaku']
                                                )
                                            ) {

                                                $tanggal_berlaku =
                                                    strtotime(
                                                        $data['masa_berlaku']
                                                    );


                                                $hari_ini =
                                                    strtotime(
                                                        date("Y-m-d")
                                                    );


                                                if (
                                                    $tanggal_berlaku <
                                                    $hari_ini
                                                ) {

                                            ?>

                                                    <span class="badge badge-danger">

                                                        <i
                                                            class="fas fa-times-circle mr-1">
                                                        </i>

                                                        Kadaluarsa

                                                    </span>

                                            <?php

                                                } else {

                                            ?>

                                                    <span class="badge badge-success">

                                                        <i
                                                            class="fas fa-check-circle mr-1">
                                                        </i>

                                                        <?php

                                                        echo date(
                                                            "d-m-Y",
                                                            $tanggal_berlaku
                                                        );

                                                        ?>

                                                    </span>

                                            <?php

                                                }

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- =================================================
                                             AKSI
                                        ================================================== -->

                                        <td class="text-center align-middle">

                                            <div
                                                class="btn-group"
                                                role="group">


                                                <!-- EDIT -->

                                                <a
                                                    href="kartu_anggota.php?id=<?php echo (int) $data['id_ang']; ?>"
                                                    class="btn btn-outline-primary btn-sm"
                                                    title="Kartu Anggota">
                                                    <i class="fas fa-id-card"></i>
                                                </a>

                                                <a
                                                    href="edit_anggota.php?id=<?php echo (int) $data['id_ang']; ?>"
                                                    class="btn btn-outline-primary btn-sm"
                                                    title="Edit Anggota">

                                                    <i class="fas fa-edit"></i>

                                                </a>


                                                <!-- HAPUS -->

                                                <form
                                                    method="post"
                                                    action="hapus_anggota.php"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Yakin ingin menghapus anggota ini beserta akun loginnya?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?php echo (int) $data['id_ang']; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-danger btn-sm"
                                                        title="Hapus Anggota"
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

                                <tr>

                                    <td
                                        colspan="10"
                                        class="text-center py-5">

                                        <i
                                            class="fas fa-users fa-3x text-muted mb-3">
                                        </i>

                                        <h5 class="text-muted">

                                            <?php

                                            if ($cari != "") {

                                                echo "Anggota tidak ditemukan.";

                                            } else {

                                                echo "Belum ada data anggota.";

                                            }

                                            ?>

                                        </h5>


                                        <?php

                                        if ($cari == "") {

                                        ?>

                                            <a
                                                href="tambah_anggota.php"
                                                class="btn btn-primary mt-2">

                                                <i
                                                    class="fas fa-user-plus mr-1">
                                                </i>

                                                Tambah Anggota

                                            </a>

                                        <?php

                                        }

                                        ?>

                                    </td>

                                </tr>

                            <?php

                            }

                            ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</section>


<?php

include "inc/footer.php";

?>