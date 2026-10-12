<?php

include "inc/admin_only.php";

$page_title = "Peminjaman";
include "inc/header.php";
include "koneksi.php";


/* =====================================================
   PROSES PEMINJAMAN
===================================================== */

if (isset($_POST['pinjam'])) {

    $id_ang    = isset($_POST['id_ang']) ? (int) $_POST['id_ang'] : 0;
    $kode_buku = isset($_POST['kode_buku']) ? (int) $_POST['kode_buku'] : 0;
    $jumlah    = isset($_POST['jml_pinjam']) ? (int) $_POST['jml_pinjam'] : 0;


    /* =================================================
       VALIDASI DASAR
    ================================================= */

    if ($id_ang <= 0 || $kode_buku <= 0 || $jumlah < 1) {

        echo "
        <script>
            alert('Data peminjaman belum lengkap.');
            window.location='pinjam.php';
        </script>
        ";

        exit;
    }


    /* =================================================
       MULAI TRANSAKSI DATABASE
    ================================================= */

    mysqli_begin_transaction($koneksi);


    try {


        /* =================================================
           CEK ANGGOTA
        ================================================= */

        $cek_anggota = mysqli_query(
            $koneksi,
            "SELECT id_ang
             FROM tbanggota
             WHERE id_ang = '$id_ang'
             LIMIT 1"
        );


        if (!$cek_anggota) {

            throw new Exception(
                "Query anggota gagal: " .
                    mysqli_error($koneksi)
            );
        }


        if (mysqli_num_rows($cek_anggota) == 0) {

            throw new Exception(
                "Data anggota tidak ditemukan."
            );
        }


        /* =================================================
           CEK BUKU + KUNCI STOK
           
           FOR UPDATE digunakan agar stok tidak berubah
           bersamaan ketika transaksi sedang berjalan.
        ================================================= */

        $cek_buku = mysqli_query(
            $koneksi,
            "SELECT
                kode_buku,
                judul,
                jml_buku
             FROM tbbuku
             WHERE kode_buku = '$kode_buku'
             LIMIT 1
             FOR UPDATE"
        );


        if (!$cek_buku) {

            throw new Exception(
                "Query buku gagal: " .
                    mysqli_error($koneksi)
            );
        }


        if (mysqli_num_rows($cek_buku) == 0) {

            throw new Exception(
                "Data buku tidak ditemukan."
            );
        }


        $data_buku = mysqli_fetch_assoc($cek_buku);

        $stok_awal  = (int) $data_buku['jml_buku'];
        $judul_buku = $data_buku['judul'];


        /* =================================================
           CEK STOK
        ================================================= */

        if ($stok_awal < $jumlah) {

            throw new Exception(
                "Stok buku tidak mencukupi. " .
                    "Stok tersedia: " . $stok_awal . " buku."
            );
        }


        /* =================================================
           ATURAN PEMINJAMAN (lihat inc/aturan.php)
           Batas jumlah, masa berlaku keanggotaan, pinjaman
           terlambat, buku dobel, dan batas total pinjaman.
        ================================================= */
        $alasan_ditolak = cek_boleh_pinjam(
            $koneksi,
            $id_ang,
            $kode_buku,
            $jumlah
        );

        if ($alasan_ditolak !== null) {
            throw new Exception($alasan_ditolak);
        }


        /* =================================================
           INSERT DATA PEMINJAMAN
           
           STATUS = pinjam
           
           Catatan:
           Tidak ada UPDATE stok melalui trigger peminjaman.
        ================================================= */

        $tgl_pinjam = date("Y-m-d");


        $insert = mysqli_query(
            $koneksi,
            "INSERT INTO tbpinjam
            (
                id_ang,
                kode_buku,
                tgl_pinjam,
                jml_pinjam,
                status,
                denda
            )
            VALUES
            (
                '$id_ang',
                '$kode_buku',
                '$tgl_pinjam',
                '$jumlah',
                'pinjam',
                '0'
            )"
        );


        if (!$insert) {

            throw new Exception(
                "Peminjaman gagal disimpan: " .
                    mysqli_error($koneksi)
            );
        }

        /* =================================================
           VERIFIKASI STOK SETELAH INSERT

           Pengurangan stok tetap dilakukan oleh trigger
           database. PHP hanya memverifikasi hasilnya.
           Jika stok tidak berkurang sesuai jumlah pinjaman,
           transaksi dibatalkan agar data tidak tidak sinkron.
        ================================================= */

        $cek_stok_akhir = mysqli_query(
            $koneksi,
            "SELECT jml_buku
             FROM tbbuku
             WHERE kode_buku = '$kode_buku'
             LIMIT 1
             FOR UPDATE"
        );

        if (!$cek_stok_akhir) {

            throw new Exception(
                "Verifikasi stok gagal: " .
                    mysqli_error($koneksi)
            );
        }

        $data_stok_akhir = mysqli_fetch_assoc($cek_stok_akhir);
        $stok_sekarang = (int) $data_stok_akhir['jml_buku'];
        $stok_seharusnya = $stok_awal - $jumlah;

        if ($stok_sekarang !== $stok_seharusnya) {

            throw new Exception(
                "Peminjaman dibatalkan karena stok buku tidak berubah sesuai transaksi. " .
                "Pastikan trigger peminjaman sudah aktif di database."
            );
        }

        /* =================================================
           COMMIT TRANSAKSI
        ================================================= */

        mysqli_commit($koneksi);

        selesaikan_reservasi($koneksi, $id_ang, $kode_buku);

        catat_log('pinjam', "Anggota #$id_ang - \"$judul_buku\" x$jumlah");


        echo "
        <script>

            alert(" .
            json_encode(
                "Peminjaman buku berhasil.\n\n" .
                    "Buku: " . $judul_buku . "\n" .
                    "Jumlah: " . $jumlah . " buku\n" .
                    "Stok sebelumnya: " . $stok_awal . " buku\n" .
                    "Stok sekarang: " . $stok_sekarang . " buku",
                JSON_UNESCAPED_UNICODE
            ) .
            ");

            window.location='pinjam.php';

        </script>
        ";

        exit;
    } catch (Exception $e) {


        /* =================================================
           ROLLBACK JIKA GAGAL
        ================================================= */

        mysqli_rollback($koneksi);


        echo "
        <script>

            alert(" .
            json_encode(
                $e->getMessage(),
                JSON_UNESCAPED_UNICODE
            ) .
            ");

            window.location='pinjam.php';

        </script>
        ";

        exit;
    }
}


/* =====================================================
   DATA ANGGOTA
===================================================== */

$anggota = mysqli_query(
    $koneksi,
    "SELECT *
     FROM tbanggota
     ORDER BY nama ASC"
);


if (!$anggota) {

    die("Query anggota gagal: " .
        mysqli_error($koneksi));
}


/* =====================================================
   DATA BUKU YANG MASIH TERSEDIA
===================================================== */

$buku = mysqli_query(
    $koneksi,
    "SELECT *
     FROM tbbuku
     WHERE jml_buku > 0
     ORDER BY judul ASC"
);


if (!$buku) {

    die("Query buku gagal: " .
        mysqli_error($koneksi));
}


/* =====================================================
   DATA PEMINJAMAN AKTIF
===================================================== */

$query = mysqli_query(
    $koneksi,
    "SELECT
        tbpinjam.*,
        tbanggota.nama,
        tbbuku.judul

     FROM tbpinjam

     INNER JOIN tbanggota
        ON tbpinjam.id_ang = tbanggota.id_ang

     INNER JOIN tbbuku
        ON tbpinjam.kode_buku = tbbuku.kode_buku

     WHERE tbpinjam.status = 'pinjam'

     ORDER BY tbpinjam.no_pinjam DESC"
);


if (!$query) {

    die("Query peminjaman gagal: " .
        mysqli_error($koneksi));
}

?>


<!-- =====================================================
     HEADER HALAMAN
===================================================== -->

<div class="container-fluid">

    <div class="row mb-3 align-items-center">

        <div class="col-sm-8">

            <h1 class="m-0">

                <i class="fas fa-book-reader text-primary mr-2"></i>

                Peminjaman Buku

            </h1>

            <p class="text-muted mb-0 mt-1">

                Kelola transaksi peminjaman buku perpustakaan.

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

                    Peminjaman

                </li>

            </ol>

        </div>

    </div>

</div>


<!-- =====================================================
     FORM PEMINJAMAN
===================================================== -->

<div class="container-fluid">

    <div class="card card-primary card-outline">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-plus-circle mr-2"></i>

                Transaksi Peminjaman

            </h3>

        </div>


        <form method="POST">

            <div class="card-body">

                <div class="row">


                    <!-- ANGGOTA -->

                    <div class="col-md-5">

                        <div class="form-group">

                            <label>

                                <i class="fas fa-user mr-1"></i>

                                Anggota

                            </label>


                            <select
                                name="id_ang"
                                class="form-control"
                                required>

                                <option value="">

                                    -- Pilih Anggota --

                                </option>


                                <?php

                                while (
                                    $data =
                                    mysqli_fetch_assoc($anggota)
                                ) {

                                ?>

                                    <option
                                        value="<?php echo (int) $data['id_ang']; ?>">

                                        <?php

                                        echo htmlspecialchars(
                                            $data['nama']
                                        );

                                        ?>

                                        — ID:

                                        <?php

                                        echo (int) $data['id_ang'];

                                        ?>

                                    </option>

                                <?php

                                }

                                ?>

                            </select>

                        </div>

                    </div>


                    <!-- BUKU -->

                    <div class="col-md-5">

                        <div class="form-group">

                            <label>

                                <i class="fas fa-book mr-1"></i>

                                Buku

                            </label>


                            <select
                                name="kode_buku"
                                class="form-control"
                                required>

                                <option value="">

                                    -- Pilih Buku --

                                </option>


                                <?php

                                while (
                                    $data =
                                    mysqli_fetch_assoc($buku)
                                ) {

                                ?>

                                    <option
                                        value="<?php echo (int) $data['kode_buku']; ?>">

                                        <?php

                                        echo htmlspecialchars(
                                            $data['judul']
                                        );

                                        ?>

                                        — Stok:

                                        <?php

                                        echo (int) $data['jml_buku'];

                                        ?>

                                    </option>

                                <?php

                                }

                                ?>

                            </select>

                        </div>

                    </div>


                    <!-- JUMLAH -->

                    <div class="col-md-2">

                        <div class="form-group">

                            <label>

                                <i class="fas fa-sort-numeric-up mr-1"></i>

                                Jumlah

                            </label>


                            <input
                                type="number"
                                name="jml_pinjam"
                                class="form-control"
                                min="1"
                                max="<?php echo maks_jml_per_pinjam(); ?>"
                                value="1"
                                required>

                        <small class="form-text text-muted">
                            Maks. <?php echo maks_jml_per_pinjam(); ?> buku per peminjaman,
                            <?php echo maks_buku_aktif(); ?> buku aktif per anggota.
                            Masa pinjam <?php echo masa_pinjam_hari(); ?> hari,
                            denda <?php echo format_rupiah(denda_per_hari()); ?>/hari.
                        </small>

                        </div>

                    </div>

                </div>


                <!-- INFO -->

                <div class="alert alert-info mb-0">

                    <i class="fas fa-info-circle mr-2"></i>

                    Pastikan anggota, buku, dan jumlah sudah benar
                    sebelum memproses peminjaman.

                </div>

            </div>


            <!-- FOOTER -->

            <div class="card-footer">

                <button
                    type="submit"
                    name="pinjam"
                    class="btn btn-primary">

                    <i class="fas fa-book-reader mr-1"></i>

                    Proses Peminjaman

                </button>


                <button
                    type="reset"
                    class="btn btn-secondary">

                    <i class="fas fa-redo mr-1"></i>

                    Reset

                </button>

            </div>

        </form>

    </div>


    <!-- =====================================================
         DAFTAR PEMINJAMAN AKTIF
    ====================================================== -->

    <div class="card card-outline card-secondary">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h3 class="card-title mb-0">
                <i class="fas fa-list mr-2"></i>Peminjaman Aktif
            </h3>

            <a href="laporan.php?jenis=pinjam" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-file-alt mr-1"></i>Laporan Peminjaman
            </a>

        </div>


        <div class="card-body">

            <div class="table-responsive">


                <table
                    class="table table-hover table-bordered"
                    style="width:100%;">

                    <thead class="bg-primary text-white">

                        <tr>

                            <th
                                width="5%"
                                class="text-center">

                                No

                            </th>


                            <th>

                                Anggota

                            </th>


                            <th>

                                Buku

                            </th>


                            <th>

                                Tanggal Pinjam

                            </th>


                            <th
                                width="10%"
                                class="text-center">

                                Jumlah

                            </th>


                            <th
                                width="15%"
                                class="text-center">

                                Status

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php

                        $no = 1;


                        if (mysqli_num_rows($query) > 0) {


                            while (
                                $data =
                                mysqli_fetch_assoc($query)
                            ) {

                        ?>

                                <tr>


                                    <!-- NO -->

                                    <td class="text-center align-middle">

                                        <?php

                                        echo $no++;

                                        ?>

                                    </td>


                                    <!-- ANGGOTA -->

                                    <td class="align-middle">

                                        <i class="fas fa-user text-muted mr-1"></i>

                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $data['nama']
                                            );

                                            ?>

                                        </strong>

                                    </td>


                                    <!-- BUKU -->

                                    <td class="align-middle">

                                        <i class="fas fa-book text-muted mr-1"></i>

                                        <?php

                                        echo htmlspecialchars(
                                            $data['judul']
                                        );

                                        ?>

                                    </td>


                                    <!-- TANGGAL -->

                                    <td class="align-middle">

                                        <?php

                                        if (
                                            !empty($data['tgl_pinjam'])
                                        ) {

                                            echo date(
                                                "d-m-Y",
                                                strtotime(
                                                    $data['tgl_pinjam']
                                                )
                                            );
                                        } else {

                                            echo "-";
                                        }

                                        ?>

                                    </td>


                                    <!-- JUMLAH -->

                                    <td
                                        class="text-center align-middle">

                                        <span class="badge badge-primary">

                                            <?php

                                            echo (int)
                                            $data['jml_pinjam'];

                                            ?>

                                            Buku

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td
                                        class="text-center align-middle">

                                        <span
                                            class="badge badge-primary px-2 py-2">

                                            <i
                                                class="fas fa-book-reader mr-1"></i>

                                            Sedang Dipinjam

                                        </span>

                                    </td>


                                </tr>

                            <?php

                            }
                        } else {

                            ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center py-5">

                                    <i
                                        class="fas fa-book-open fa-3x text-muted mb-3"></i>


                                    <h5 class="text-muted">

                                        Belum Ada Peminjaman Aktif

                                    </h5>


                                    <p class="text-muted mb-0">

                                        Belum terdapat buku yang sedang dipinjam.

                                    </p>

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

<?php include "inc/footer.php"; ?>
