<?php

/* =========================================================
   TAMBAH ANGGOTA - PERPUS ZAKY
========================================================= */

include "inc/admin_only.php";
include "koneksi.php";


/* =========================================================
   PROSES SIMPAN DATA
========================================================= */

if (isset($_POST['simpan'])) {


    /* =====================================================
       DATA FORM
    ===================================================== */

    $nama = mysqli_real_escape_string(
        $koneksi,
        trim($_POST['nama'] ?? '')
    );

    $jk = mysqli_real_escape_string(
        $koneksi,
        $_POST['jk'] ?? ''
    );

    $tgl_lahir = mysqli_real_escape_string(
        $koneksi,
        $_POST['tgl_lahir'] ?? ''
    );

    $telp = mysqli_real_escape_string(
        $koneksi,
        trim($_POST['telp'] ?? '')
    );

    $alamat = mysqli_real_escape_string(
        $koneksi,
        trim($_POST['alamat'] ?? '')
    );


    /* =====================================================
       EMAIL
    ===================================================== */

    $email = strtolower(
        trim($_POST['email'] ?? '')
    );

    if (
        $email != "" &&
        strpos($email, "@") === false
    ) {

        $email .= "@gmail.com";

    }

    $email = mysqli_real_escape_string(
        $koneksi,
        $email
    );


    /* =====================================================
       TANGGAL ANGGOTA
    ===================================================== */

    $tgl_entry = date("Y-m-d");

    $masa_berlaku = date(
        "Y-m-d",
        strtotime("+3 years")
    );


    /* =====================================================
       FOTO (OPSIONAL)
       Tanpa foto -> kolom foto NULL, kartu anggota memakai
       inisial nama. Jika dipilih, tetap divalidasi penuh.
    ===================================================== */

    $ada_foto = isset($_FILES['foto'])
        && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;

    $nama_file_foto = '';

    if ($ada_foto && $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {

        echo "
        <script>
            alert('Foto gagal diunggah. Coba file lain atau kosongkan foto.');
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;
    }

    if ($ada_foto) {

    /* =====================================================
       INFORMASI FILE
    ===================================================== */

    $nama_foto_asli = $_FILES['foto']['name'];
    $tmp_foto       = $_FILES['foto']['tmp_name'];
    $ukuran         = $_FILES['foto']['size'];


    /* =====================================================
       AMBIL EKSTENSI FILE
    ===================================================== */

    $ekstensi = strtolower(
        pathinfo(
            $nama_foto_asli,
            PATHINFO_EXTENSION
        )
    );


    /* =====================================================
       FORMAT YANG DIIZINKAN
    ===================================================== */

    $ekstensi_valid = [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ];


    if (
        !in_array(
            $ekstensi,
            $ekstensi_valid,
            true
        )
    ) {

        echo "
        <script>
            alert('Format foto harus JPG, JPEG, PNG, atau WEBP.');
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;

    }


    /* =====================================================
       BATAS UKURAN
       MAKSIMAL 2 MB
    ===================================================== */

    if (
        $ukuran >
        2 * 1024 * 1024
    ) {

        echo "
        <script>
            alert('Ukuran foto maksimal 2 MB.');
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;

    }


    /* =====================================================
       PASTIKAN FILE BENAR-BENAR GAMBAR
    ===================================================== */

    $cek_gambar = @getimagesize(
        $tmp_foto
    );


    if (
        $cek_gambar === false
    ) {

        echo "
        <script>
            alert('File yang dipilih bukan gambar yang valid.');
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;

    }


    /* =====================================================
       FOLDER FOTO
    ===================================================== */

    $folder_foto =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "assets" .
        DIRECTORY_SEPARATOR .
        "img" .
        DIRECTORY_SEPARATOR;


    if (
        !is_dir($folder_foto)
    ) {

        if (
            !mkdir(
                $folder_foto,
                0755,
                true
            )
        ) {

            echo "
            <script>
                alert('Folder penyimpanan foto tidak dapat dibuat.');
                window.location='tambah_anggota.php';
            </script>
            ";

            exit;

        }

    }


    /* =====================================================
       BUAT NAMA FILE UNIK
       
       CONTOH:
       anggota_1787035924_d6bde435e5.jpg
    ===================================================== */

    try {

        $random =
            bin2hex(
                random_bytes(5)
            );

    } catch (Exception $e) {

        $random =
            uniqid();

    }


    $nama_file_foto =
        "anggota_" .
        time() .
        "_" .
        $random .
        "." .
        $ekstensi;


    $tujuan_foto =
        $folder_foto .
        $nama_file_foto;


    /* =====================================================
       UPLOAD FOTO
    ===================================================== */

    if (
        !move_uploaded_file(
            $tmp_foto,
            $tujuan_foto
        )
    ) {

        echo "
        <script>
            alert('Foto gagal diupload.');
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;

    }


    /* =====================================================
       PASTIKAN FILE HASIL UPLOAD ADA
    ===================================================== */

    if (
        !file_exists($tujuan_foto)
    ) {

        echo "
        <script>
            alert('File foto tidak ditemukan setelah proses upload.');
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;

    }


    } /* akhir if ($ada_foto) */


    /* =====================================================
       SIMPAN NAMA FOTO KE DATABASE
    ===================================================== */

    $foto = mysqli_real_escape_string(
        $koneksi,
        $nama_file_foto
    );


    /* =====================================================
       INSERT TBANGGOTA
    ===================================================== */

    $query = mysqli_query(
        $koneksi,
        "INSERT INTO tbanggota
        (
            nama,
            jk,
            tgl_lahir,
            telp,
            alamat,
            email,
            tgl_entry,
            masa_berlaku,
            foto
        )
        VALUES
        (
            '$nama',
            '$jk',
            '$tgl_lahir',
            '$telp',
            '$alamat',
            '$email',
            '$tgl_entry',
            '$masa_berlaku',
            " . ($foto === '' ? "NULL" : "'$foto'") . "
        )"
    );


    /* =====================================================
       HASIL SIMPAN
    ===================================================== */

    if ($query) {

        catat_log('tambah_anggota', 'Anggota #' . mysqli_insert_id($koneksi) . ' ' . $nama);

        echo "
        <script>
            alert('Data anggota berhasil ditambahkan!');
            window.location='anggota.php';
        </script>
        ";

        exit;

    } else {


        /* =================================================
           JIKA DATABASE GAGAL,
           HAPUS FOTO YANG SUDAH DIUPLOAD
        ================================================= */

        if (
            file_exists($tujuan_foto)
        ) {

            unlink($tujuan_foto);

        }


        echo "
        <script>
            alert(" .
            json_encode(
                "Data anggota gagal ditambahkan: " .
                mysqli_error($koneksi),
                JSON_UNESCAPED_UNICODE
            ) .
            ");
            window.location='tambah_anggota.php';
        </script>
        ";

        exit;

    }

}

?>


<?php $page_title = "Tambah anggota";
include "inc/header.php"; ?>


<!-- =====================================================
     CONTENT HEADER
===================================================== -->

<section class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <div class="col-sm-6">

                <h1>

                    <i class="fas fa-user-plus"></i>

                    Tambah Anggota

                </h1>

            </div>


            <div class="col-sm-6">

                <ol class="breadcrumb float-sm-right">

                    <li class="breadcrumb-item">

                        <a href="beranda_admin.php">

                            Dashboard

                        </a>

                    </li>

                    <li class="breadcrumb-item">

                        <a href="anggota.php">

                            Data Anggota

                        </a>

                    </li>

                    <li class="breadcrumb-item active">

                        Tambah Anggota

                    </li>

                </ol>

            </div>

        </div>

    </div>

</section>


<!-- =====================================================
     CONTENT
===================================================== -->

<section class="content">

    <div class="container-fluid">

        <div class="row">


            <!-- =================================================
                 FORM
            ================================================== -->

            <div class="col-md-8">

                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-user-plus mr-2"></i>

                            Form Tambah Anggota

                        </h3>

                    </div>


                    <div class="card-body">


                        <form
                            method="POST"
                            enctype="multipart/form-data">


                            <!-- NAMA -->

                            <div class="form-group">

                                <label>

                                    Nama Lengkap

                                </label>

                                <input
                                    type="text"
                                    name="nama"
                                    class="form-control"
                                    placeholder="Masukkan nama lengkap"
                                    required>

                            </div>


                            <!-- JENIS KELAMIN -->

                            <div class="form-group">

                                <label>

                                    Jenis Kelamin

                                </label>

                                <select
                                    name="jk"
                                    class="form-control"
                                    required>

                                    <option value="">

                                        -- Pilih Jenis Kelamin --

                                    </option>

                                    <option value="L">

                                        Laki-laki

                                    </option>

                                    <option value="P">

                                        Perempuan

                                    </option>

                                </select>

                            </div>


                            <!-- TANGGAL LAHIR -->

                            <div class="form-group">

                                <label>

                                    Tanggal Lahir

                                </label>

                                <input
                                    type="date"
                                    name="tgl_lahir"
                                    class="form-control"
                                    required>

                            </div>


                            <!-- TELEPON -->

                            <div class="form-group">

                                <label>

                                    Nomor Telepon

                                </label>

                                <input
                                    type="text"
                                    name="telp"
                                    class="form-control"
                                    placeholder="Contoh: 081234567890"
                                    required>

                            </div>


                            <!-- ALAMAT -->

                            <div class="form-group">

                                <label>

                                    Alamat

                                </label>

                                <textarea
                                    name="alamat"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Masukkan alamat lengkap"
                                    required></textarea>

                            </div>


                            <!-- EMAIL -->

                            <div class="form-group">

                                <label>

                                    Email

                                </label>

                                <input
                                    type="text"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    placeholder="Contoh: zaky2003 atau zaky2003@gmail.com"
                                    autocomplete="email"
                                    required>

                                <small class="text-muted">

                                    Jika tidak menulis @gmail.com,
                                    sistem akan menambahkannya otomatis.

                                </small>

                            </div>


                            <!-- FOTO -->

                            <div class="form-group">

                                <label>

                                    <i class="fas fa-camera mr-1"></i>

                                    Foto Anggota
                                    <span class="text-muted font-weight-normal">(opsional)</span>

                                </label>

                                <input
                                    type="file"
                                    name="foto"
                                    class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                                <small class="text-muted">

                                    Opsional. Jika dikosongkan, kartu anggota memakai inisial nama.
                                    Format JPG, JPEG, PNG, atau WEBP.
                                    Maksimal 2 MB.

                                </small>

                            </div>


                            <!-- BUTTON -->

                            <div class="form-group mt-4">

                                <button
                                    type="submit"
                                    name="simpan"
                                    class="btn btn-primary">

                                    <i class="fas fa-save mr-1"></i>

                                    Simpan Anggota

                                </button>


                                <a
                                    href="anggota.php"
                                    class="btn btn-secondary">

                                    <i class="fas fa-arrow-left mr-1"></i>

                                    Kembali

                                </a>

                            </div>


                        </form>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 INFORMASI
            ================================================== -->

            <div class="col-md-4">

                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-info-circle mr-2"></i>

                            Informasi

                        </h3>

                    </div>


                    <div class="card-body">

                        <p class="text-muted">

                            Silakan isi data anggota dengan lengkap
                            dan benar.

                        </p>

                        <hr>


                        <div class="mb-3">

                            <i class="fas fa-camera text-primary mr-2"></i>

                            <strong>

                                Foto Anggota

                            </strong>

                            <br>

                            <small class="text-muted ml-4">

                                Foto boleh dikosongkan dan
                                ditambahkan nanti lewat Ubah anggota.

                            </small>

                        </div>


                        <div class="mb-3">

                            <i class="fas fa-calendar-check text-primary mr-2"></i>

                            <strong>

                                Masa Berlaku

                            </strong>

                            <br>

                            <small class="text-muted ml-4">

                                Otomatis berlaku selama 3 tahun.

                            </small>

                        </div>


                        <div class="mb-3">

                            <i class="fas fa-id-card text-primary mr-2"></i>

                            <strong>

                                ID Anggota

                            </strong>

                            <br>

                            <small class="text-muted ml-4">

                                ID anggota dibuat otomatis oleh database.

                            </small>

                        </div>


                        <div>

                            <i class="fas fa-check-circle text-success mr-2"></i>

                            <strong>

                                Status

                            </strong>

                            <br>

                            <small class="text-muted ml-4">

                                Data akan langsung masuk ke daftar anggota.

                            </small>

                        </div>


                    </div>

                </div>

            </div>


        </div>

    </div>

</section>


<?php include "inc/footer.php"; ?>


<!-- =====================================================
     OTOMATIS EMAIL
===================================================== -->

<script>

const emailInput =
    document.getElementById("email");


if (emailInput) {

    emailInput.addEventListener(
        "input",
        function () {

            this.value =
                this.value.toLowerCase();

        }
    );


    emailInput.addEventListener(
        "blur",
        function () {

            let email =
                this.value
                    .trim()
                    .toLowerCase();


            if (
                email !== "" &&
                !email.includes("@")
            ) {

                this.value =
                    email + "@gmail.com";

            }

        }
    );

}

</script>