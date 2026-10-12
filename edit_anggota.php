<?php

/* =========================================================
   EDIT ANGGOTA - PERPUS ZAKY
========================================================= */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include "inc/admin_only.php";
include "koneksi.php";


/* =========================================================
   CEK ID ANGGOTA
========================================================= */

if (!isset($_GET['id']) || empty($_GET['id'])) {

    echo "<script>
            alert('ID anggota tidak ditemukan!');
            window.location='anggota.php';
          </script>";

    exit;
}

$id = (int) $_GET['id'];


/* =========================================================
   AMBIL DATA ANGGOTA
========================================================= */

$query = mysqli_query(
    $koneksi,
    "SELECT * FROM tbanggota WHERE id_ang = '$id'"
);

if (!$query) {

    die(
        "Query data anggota gagal: " .
        mysqli_error($koneksi)
    );
}

if (mysqli_num_rows($query) == 0) {

    echo "<script>
            alert('Data anggota tidak ditemukan!');
            window.location='anggota.php';
          </script>";

    exit;
}

$data = mysqli_fetch_assoc($query);


/* =========================================================
   PROSES UPDATE
========================================================= */

if (isset($_POST['update'])) {


    /* =====================================================
       AMBIL DATA FORM
    ===================================================== */

    $nama = trim($_POST['nama'] ?? '');

    $jk = trim($_POST['jk'] ?? '');

    $tgl_lahir = trim($_POST['tgl_lahir'] ?? '');

    $telp = trim($_POST['telp'] ?? '');

    $alamat = trim($_POST['alamat'] ?? '');

    $email = strtolower(
        trim($_POST['email'] ?? '')
    );


    /* =====================================================
       VALIDASI DATA WAJIB
    ===================================================== */

    if (
        $nama == '' ||
        $jk == '' ||
        $tgl_lahir == '' ||
        $telp == '' ||
        $alamat == '' ||
        $email == ''
    ) {

        echo "<script>
                alert('Semua data anggota wajib diisi!');
              </script>";

    } elseif (
        $jk != 'L' &&
        $jk != 'P'
    ) {

        echo "<script>
                alert('Jenis kelamin tidak valid!');
              </script>";

    } else {


        /* =================================================
           EMAIL OTOMATIS @gmail.com
        ================================================= */

        if (
            $email != "" &&
            strpos($email, "@") === false
        ) {

            $email .= "@gmail.com";

        }


        /* =================================================
           ESCAPE DATA
        ================================================= */

        $nama_safe = mysqli_real_escape_string(
            $koneksi,
            $nama
        );

        $jk_safe = mysqli_real_escape_string(
            $koneksi,
            $jk
        );

        $tgl_lahir_safe = mysqli_real_escape_string(
            $koneksi,
            $tgl_lahir
        );

        $telp_safe = mysqli_real_escape_string(
            $koneksi,
            $telp
        );

        $alamat_safe = mysqli_real_escape_string(
            $koneksi,
            $alamat
        );

        $email_safe = mysqli_real_escape_string(
            $koneksi,
            $email
        );


        /* =================================================
           FOTO LAMA
        ================================================= */

        $foto_lama = trim(
            $data['foto'] ?? ''
        );

        $nama_foto = $foto_lama;

        $foto_baru_berhasil = false;

        $file_foto_baru = '';


        /* =================================================
           FOLDER FOTO
        ================================================= */

        $folder_upload = __DIR__ . "/assets/img/";

        $folder_url = "assets/img/";


        /* =================================================
           PROSES UPLOAD FOTO BARU
        ================================================= */

        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] != UPLOAD_ERR_NO_FILE
        ) {

            /* =============================================
               CEK ERROR UPLOAD
            ============================================= */

            if (
                $_FILES['foto']['error'] !== UPLOAD_ERR_OK
            ) {

                echo "<script>
                        alert('Foto gagal diupload!');
                      </script>";

            } else {

                $file = $_FILES['foto'];

                $nama_file_asli = $file['name'];

                $tmp_file = $file['tmp_name'];

                $ukuran_file = $file['size'];


                /* =========================================
                   CEK UKURAN
                ========================================= */

                if (
                    $ukuran_file >
                    2 * 1024 * 1024
                ) {

                    echo "<script>
                            alert('Ukuran foto maksimal 2 MB!');
                          </script>";

                } else {


                    /* =====================================
                       CEK EKSTENSI
                    ===================================== */

                    $ekstensi = strtolower(
                        pathinfo(
                            $nama_file_asli,
                            PATHINFO_EXTENSION
                        )
                    );

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

                        echo "<script>
                                alert('Format foto harus JPG, JPEG, PNG, atau WEBP!');
                              </script>";

                    } else {


                        /* =================================
                           CEK MIME TYPE
                        ================================= */

                        $finfo = finfo_open(
                            FILEINFO_MIME_TYPE
                        );

                        $mime_type = finfo_file(
                            $finfo,
                            $tmp_file
                        );

                        finfo_close($finfo);


                        $mime_valid = [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ];


                        if (
                            !in_array(
                                $mime_type,
                                $mime_valid,
                                true
                            )
                        ) {

                            echo "<script>
                                    alert('File yang dipilih bukan gambar yang valid!');
                                  </script>";

                        } elseif (
                            getimagesize($tmp_file) === false
                        ) {

                            echo "<script>
                                    alert('File yang dipilih bukan gambar yang valid!');
                                  </script>";

                        } else {


                            /* =================================
                               BUAT FOLDER JIKA BELUM ADA
                            ================================= */

                            if (
                                !is_dir($folder_upload)
                            ) {

                                if (
                                    !mkdir(
                                        $folder_upload,
                                        0755,
                                        true
                                    )
                                ) {

                                    echo "<script>
                                            alert('Folder penyimpanan foto gagal dibuat!');
                                          </script>";

                                }

                            }


                            /* =================================
                               CEK FOLDER
                            ================================= */

                            if (
                                is_dir($folder_upload) &&
                                is_writable($folder_upload)
                            ) {


                                /* =============================
                                   NAMA FOTO BARU
                                ============================= */

                                $nama_foto_baru =
                                    'anggota_' .
                                    $id .
                                    '_' .
                                    time() .
                                    '_' .
                                    bin2hex(
                                        random_bytes(4)
                                    ) .
                                    '.' .
                                    $ekstensi;


                                $file_foto_baru =
                                    $folder_upload .
                                    $nama_foto_baru;


                                /* =============================
                                   PINDAHKAN FOTO
                                ============================= */

                                if (
                                    move_uploaded_file(
                                        $tmp_file,
                                        $file_foto_baru
                                    )
                                ) {

                                    $nama_foto =
                                        $nama_foto_baru;

                                    $foto_baru_berhasil =
                                        true;

                                } else {

                                    echo "<script>
                                            alert('Foto gagal dipindahkan ke folder assets/img!');
                                          </script>";

                                }

                            } else {

                                echo "<script>
                                        alert('Folder assets/img tidak dapat digunakan untuk menyimpan foto!');
                                      </script>";

                            }

                        }

                    }

                }

            }

        }


        /* =================================================
           UPDATE DATABASE
        ================================================= */

        $nama_foto_safe = mysqli_real_escape_string(
            $koneksi,
            $nama_foto
        );


        $update = mysqli_query(
            $koneksi,
            "UPDATE tbanggota SET
                nama = '$nama_safe',
                jk = '$jk_safe',
                tgl_lahir = '$tgl_lahir_safe',
                telp = '$telp_safe',
                alamat = '$alamat_safe',
                email = '$email_safe',
                foto = '$nama_foto_safe'
             WHERE id_ang = '$id'"
        );


        /* =================================================
           HASIL UPDATE
        ================================================= */

        if ($update) {

            catat_log('ubah_anggota', "Anggota #$id " . $nama);


            /* =============================================
               HAPUS FOTO LAMA
               HANYA JIKA FOTO BARU BERHASIL DISIMPAN
            ============================================= */

            if (
                $foto_baru_berhasil &&
                !empty($foto_lama)
            ) {

                $file_foto_lama =
                    $folder_upload .
                    basename($foto_lama);


                if (
                    file_exists($file_foto_lama) &&
                    is_file($file_foto_lama)
                ) {

                    unlink($file_foto_lama);

                }

            }


            echo "<script>
                    alert('Data anggota berhasil diperbarui!');
                    window.location='anggota.php';
                  </script>";

            exit;


        } else {


            /* =============================================
               JIKA DATABASE GAGAL UPDATE
               HAPUS FOTO BARU AGAR TIDAK MENJADI SAMPAH
            ============================================= */

            if (
                $foto_baru_berhasil &&
                !empty($file_foto_baru) &&
                file_exists($file_foto_baru)
            ) {

                unlink($file_foto_baru);

            }


            echo "<script>
                    alert('Data anggota gagal diperbarui!');
                  </script>";

        }

    }

}

?>


<?php $page_title = "Ubah anggota";
include "inc/header.php"; ?>


<!-- =========================================================
     HEADER
========================================================= -->

<section class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <div class="col-sm-6">

                <h1>

                    <i class="fas fa-user-edit mr-2"></i>

                    Edit Anggota

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

                        Edit Anggota

                    </li>

                </ol>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     CONTENT
========================================================= -->

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

                            <i class="fas fa-user-edit mr-2"></i>

                            Form Edit Anggota

                        </h3>

                    </div>


                    <div class="card-body">

                        <form
                            method="POST"
                            enctype="multipart/form-data"
                        >


                            <!-- =================================================
                                 ID ANGGOTA
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    ID Anggota

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($data['id_ang']); ?>"
                                    readonly
                                >

                            </div>


                            <!-- =================================================
                                 FOTO
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Foto Anggota

                                </label>


                                <!-- FOTO LAMA -->

                                <div class="mb-3">

                                    <?php

                                    $foto_tampil = trim(
                                        $data['foto'] ?? ''
                                    );

                                    $file_foto_tampil =
                                        __DIR__ .
                                        "/assets/img/" .
                                        $foto_tampil;


                                    if (
                                        $foto_tampil != "" &&
                                        file_exists(
                                            $file_foto_tampil
                                        )
                                    ) {

                                    ?>

                                        <img
                                            src="assets/img/<?php echo htmlspecialchars($foto_tampil); ?>"
                                            id="previewFoto"
                                            style="
                                                width:150px;
                                                height:150px;
                                                object-fit:cover;
                                                border-radius:10px;
                                                border:1px solid #ddd;
                                            "
                                            alt="Foto Anggota"
                                        >

                                    <?php

                                    } else {

                                    ?>

                                        <div
                                            id="previewFoto"
                                            style="
                                                width:150px;
                                                height:150px;
                                                display:flex;
                                                align-items:center;
                                                justify-content:center;
                                                background:#f2f5f9;
                                                border-radius:10px;
                                                border:1px solid #ddd;
                                                color:#9ca3af;
                                            "
                                        >

                                            <i
                                                class="fas fa-user fa-4x"
                                            ></i>

                                        </div>

                                    <?php

                                    }

                                    ?>

                                </div>


                                <!-- INPUT FOTO -->

                                <input
                                    type="file"
                                    name="foto"
                                    id="foto"
                                    class="form-control-file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >


                                <small class="text-muted">

                                    Format: JPG, JPEG, PNG, WEBP.

                                    Maksimal 2 MB.

                                    Kosongkan jika tidak ingin
                                    mengganti foto.

                                </small>

                            </div>


                            <!-- =================================================
                                 NAMA
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Nama Lengkap

                                </label>

                                <input
                                    type="text"
                                    name="nama"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($data['nama']); ?>"
                                    placeholder="Masukkan nama lengkap"
                                    required
                                >

                            </div>


                            <!-- =================================================
                                 JENIS KELAMIN
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Jenis Kelamin

                                </label>

                                <select
                                    name="jk"
                                    class="form-control"
                                    required
                                >

                                    <option value="">

                                        -- Pilih Jenis Kelamin --

                                    </option>


                                    <option
                                        value="L"
                                        <?php
                                        if (
                                            strtoupper(
                                                trim(
                                                    $data['jk'] ?? ''
                                                )
                                            ) == 'L'
                                        ) {
                                            echo 'selected';
                                        }
                                        ?>
                                    >

                                        Laki-laki

                                    </option>


                                    <option
                                        value="P"
                                        <?php
                                        if (
                                            strtoupper(
                                                trim(
                                                    $data['jk'] ?? ''
                                                )
                                            ) == 'P'
                                        ) {
                                            echo 'selected';
                                        }
                                        ?>
                                    >

                                        Perempuan

                                    </option>

                                </select>

                            </div>


                            <!-- =================================================
                                 TANGGAL LAHIR
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Tanggal Lahir

                                </label>

                                <input
                                    type="date"
                                    name="tgl_lahir"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($data['tgl_lahir']); ?>"
                                    required
                                >

                            </div>


                            <!-- =================================================
                                 TELEPON
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Nomor Telepon

                                </label>

                                <input
                                    type="text"
                                    name="telp"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($data['telp']); ?>"
                                    placeholder="Contoh: 081234567890"
                                    required
                                >

                            </div>


                            <!-- =================================================
                                 ALAMAT
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Alamat

                                </label>

                                <textarea
                                    name="alamat"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Masukkan alamat lengkap"
                                    required
                                ><?php echo htmlspecialchars($data['alamat']); ?></textarea>

                            </div>


                            <!-- =================================================
                                 EMAIL
                            ================================================== -->

                            <div class="form-group">

                                <label>

                                    Email

                                </label>

                                <input
                                    type="text"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($data['email']); ?>"
                                    placeholder="Contoh: zaky2003@gmail.com"
                                    autocomplete="email"
                                    required
                                >


                                <small class="text-muted">

                                    Email otomatis menggunakan
                                    huruf kecil.

                                    Jika tidak menulis @gmail.com,
                                    sistem akan menambahkannya.

                                </small>

                            </div>


                            <!-- =================================================
                                 BUTTON
                            ================================================== -->

                            <div class="form-group mt-4">

                                <button
                                    type="submit"
                                    name="update"
                                    class="btn btn-primary"
                                >

                                    <i class="fas fa-save mr-1"></i>

                                    Simpan Perubahan

                                </button>


                                <a
                                    href="anggota.php"
                                    class="btn btn-secondary"
                                >

                                    <i class="fas fa-arrow-left mr-1"></i>

                                    Kembali

                                </a>

                            </div>


                        </form>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 INFO
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

                            Silakan ubah data anggota yang diperlukan,
                            kemudian klik Simpan Perubahan.

                        </p>


                        <hr>


                        <div class="mb-3">

                            <i class="fas fa-id-card text-primary mr-2"></i>

                            <strong>ID Anggota</strong>

                            <br>

                            <small class="text-muted ml-4">

                                ID tidak dapat diubah.

                            </small>

                        </div>


                        <div class="mb-3">

                            <i class="fas fa-camera text-primary mr-2"></i>

                            <strong>Foto Anggota</strong>

                            <br>

                            <small class="text-muted ml-4">

                                Pilih foto baru jika ingin mengganti
                                foto anggota.

                            </small>

                        </div>


                        <div class="mb-3">

                            <i class="fas fa-calendar-check text-primary mr-2"></i>

                            <strong>Masa Berlaku</strong>

                            <br>

                            <small class="text-muted ml-4">

                                Tetap mengikuti data anggota sebelumnya.

                            </small>

                        </div>


                        <div>

                            <i class="fas fa-save text-success mr-2"></i>

                            <strong>Simpan Perubahan</strong>

                            <br>

                            <small class="text-muted ml-4">

                                Data akan diperbarui setelah tombol
                                disimpan.

                            </small>

                        </div>


                    </div>

                </div>

            </div>


        </div>

    </div>

</section>


<?php include "inc/footer.php"; ?>


<!-- =========================================================
     JAVASCRIPT
     EMAIL + PREVIEW FOTO
========================================================= -->

<script>

/* =========================================================
   EMAIL OTOMATIS
========================================================= */

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


/* =========================================================
   PREVIEW FOTO
========================================================= */

const fotoInput =
    document.getElementById("foto");


if (fotoInput) {

    fotoInput.addEventListener(
        "change",
        function (event) {

            const file =
                event.target.files[0];


            if (!file) {
                return;
            }


            /* =============================================
               CEK EKSTENSI
            ============================================= */

            const namaFile =
                file.name.toLowerCase();

            const ekstensiValid = [
                ".jpg",
                ".jpeg",
                ".png",
                ".webp"
            ];

            const validEkstensi =
                ekstensiValid.some(
                    function (ext) {
                        return namaFile.endsWith(ext);
                    }
                );


            if (!validEkstensi) {

                alert(
                    "Format foto harus JPG, JPEG, PNG, atau WEBP!"
                );

                fotoInput.value = "";

                return;

            }


            /* =============================================
               CEK UKURAN
            ============================================= */

            if (
                file.size >
                2 * 1024 * 1024
            ) {

                alert(
                    "Ukuran foto maksimal 2 MB!"
                );

                fotoInput.value = "";

                return;

            }


            /* =============================================
               PREVIEW
            ============================================= */

            const reader =
                new FileReader();


            reader.onload =
                function (e) {

                    const preview =
                        document.getElementById(
                            "previewFoto"
                        );


                    if (!preview) {
                        return;
                    }


                    if (
                        preview.tagName === "IMG"
                    ) {

                        preview.src =
                            e.target.result;

                    } else {

                        const img =
                            document.createElement(
                                "img"
                            );

                        img.id =
                            "previewFoto";

                        img.src =
                            e.target.result;

                        img.alt =
                            "Foto Anggota";

                        img.style.width =
                            "150px";

                        img.style.height =
                            "150px";

                        img.style.objectFit =
                            "cover";

                        img.style.borderRadius =
                            "10px";

                        img.style.border =
                            "1px solid #ddd";


                        preview.replaceWith(
                            img
                        );

                    }

                };


            reader.readAsDataURL(file);

        }
    );

}

</script>