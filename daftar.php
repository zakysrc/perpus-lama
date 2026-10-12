<?php

require_once __DIR__ . '/inc/security.php';

include "koneksi.php";

$error = "";
$success = "";


/* =========================================================
   PROSES PENDAFTARAN AKUN
   MODEL PROFESIONAL:
   AKUN DIBUAT UNTUK ANGGOTA YANG SUDAH TERDAFTAR
========================================================= */

if (isset($_POST['daftar'])) {

    /* =====================================================
       EMAIL
    ===================================================== */

    $email_awal = strtolower(
        trim($_POST['email_awal'] ?? '')
    );

    $email_domain = strtolower(
        trim($_POST['email_domain'] ?? '')
    );

    $email_domain_custom = "";

    if ($email_domain === "lainnya") {

        $email_domain_custom = strtolower(
            trim($_POST['email_domain_custom'] ?? '')
        );

        /* Tambahkan @ otomatis jika belum ada */

        if (
            $email_domain_custom != "" &&
            substr($email_domain_custom, 0, 1) !== "@"
        ) {
            $email_domain_custom = "@" . $email_domain_custom;
        }

        $email = $email_awal . $email_domain_custom;

    } else {

        $email = $email_awal . $email_domain;
    }


    /* =====================================================
       DATA AKUN
    ===================================================== */

    $username = trim(
        $_POST['username'] ?? ''
    );

    $password = $_POST['password'] ?? '';

    $konfirmasi = $_POST['konfirmasi'] ?? '';


    /* =====================================================
       VALIDASI
    ===================================================== */

    if (
        $email_awal == "" ||
        $email_domain == "" ||
        $username == "" ||
        $password == "" ||
        $konfirmasi == ""
    ) {

        $error = "Semua field wajib diisi.";

    } elseif (
        $email_domain === "lainnya" &&
        $email_domain_custom == ""
    ) {

        $error = "Domain email wajib diisi.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = "Format email tidak valid.";

    } elseif (
        strlen($username) < 3
    ) {

        $error = "Username minimal 3 karakter.";

    } elseif (
        strlen($password) < 8
    ) {

        $error = "Password minimal 8 karakter.";

    } elseif (
        $password !== $konfirmasi
    ) {

        $error = "Konfirmasi password tidak sama.";

    } else {

        /* =================================================
           DATA AMAN
        ================================================= */

        $email_safe = mysqli_real_escape_string(
            $koneksi,
            $email
        );

        $username_safe = mysqli_real_escape_string(
            $koneksi,
            $username
        );


        /* =================================================
           CEK USERNAME
        ================================================= */

        $cek_username = mysqli_query(
            $koneksi,
            "SELECT id_user
             FROM tbuser
             WHERE username = '$username_safe'
             LIMIT 1"
        );

        if (!$cek_username) {

            $error =
                "Gagal mengecek username: " .
                mysqli_error($koneksi);

        } elseif (
            mysqli_num_rows($cek_username) > 0
        ) {

            $error =
                "Username sudah digunakan. " .
                "Silakan pilih username lain.";

        } else {

            /* =============================================
               CARI ANGGOTA BERDASARKAN EMAIL
            ============================================= */

            $cek_anggota = mysqli_query(
                $koneksi,
                "SELECT id_ang, nama
                 FROM tbanggota
                 WHERE LOWER(email) = LOWER('$email_safe')"
            );

            if (!$cek_anggota) {

                $error =
                    "Gagal mengecek data anggota: " .
                    mysqli_error($koneksi);

            } else {

                $jumlah_anggota =
                    mysqli_num_rows($cek_anggota);


                /* =========================================
                   EMAIL TIDAK DITEMUKAN
                ========================================= */

                if ($jumlah_anggota == 0) {

                    $error =
                        "Email belum terdaftar sebagai anggota. " .
                        "Silakan hubungi admin untuk mendaftarkan " .
                        "data anggota terlebih dahulu.";

                }

                /* =========================================
                   EMAIL GANDA
                ========================================= */

                elseif ($jumlah_anggota > 1) {

                    $error =
                        "Email tersebut digunakan oleh lebih dari " .
                        "satu anggota. Silakan hubungi admin untuk " .
                        "memperbaiki data anggota.";

                }

                else {

                    $anggota =
                        mysqli_fetch_assoc($cek_anggota);

                    $id_ang =
                        intval($anggota['id_ang']);

                    $nama_anggota =
                        $anggota['nama'];


                    /* =====================================
                       CEK APAKAH ANGGOTA SUDAH MEMILIKI AKUN
                    ===================================== */

                    $cek_akun = mysqli_query(
                        $koneksi,
                        "SELECT id_user, username
                         FROM tbuser
                         WHERE id_ang = '$id_ang'
                         LIMIT 1"
                    );

                    if (!$cek_akun) {

                        $error =
                            "Gagal mengecek akun anggota: " .
                            mysqli_error($koneksi);

                    } elseif (
                        mysqli_num_rows($cek_akun) > 0
                    ) {

                        $akun_lama =
                            mysqli_fetch_assoc($cek_akun);

                        $error =
                            "Anggota atas nama " .
                            htmlspecialchars(
                                $nama_anggota,
                                ENT_QUOTES,
                                'UTF-8'
                            ) .
                            " sudah memiliki akun dengan username " .
                            htmlspecialchars(
                                $akun_lama['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) .
                            ". Silakan gunakan akun tersebut.";

                    } else {

                        /* =================================
                           HASH PASSWORD
                        ================================= */

                        $password_hash =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                        $password_safe =
                            mysqli_real_escape_string(
                                $koneksi,
                                $password_hash
                            );


                        /* =================================
                           SIMPAN AKUN
                        ================================= */

                        $insert_user = mysqli_query(
                            $koneksi,
                            "INSERT INTO tbuser
                            (
                                id_ang,
                                username,
                                password,
                                status
                            )
                            VALUES
                            (
                                '$id_ang',
                                '$username_safe',
                                '$password_safe',
                                'user'
                            )"
                        );


                        if (!$insert_user) {

                            $error =
                                "Akun gagal dibuat: " .
                                mysqli_error($koneksi);

                        } else {

                            $success =
                                "Pendaftaran akun berhasil! " .
                                "Akun untuk anggota " .
                                $nama_anggota .
                                " sudah dibuat. " .
                                "Silakan login.";

                            /* Kosongkan password */

                            $_POST['password'] = "";
                            $_POST['konfirmasi'] = "";
                        }
                    }
                }
            }
        }
    }
}

?>


<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Daftar Akun | Perpustakaan Zaky
    </title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="/perpuszaky/plugins/fontawesome-free/css/all.min.css">


    <!-- AdminLTE -->

    <link
        rel="stylesheet"
        href="/perpuszaky/dist/css/adminlte.min.css">


    <link rel="stylesheet" href="/perpuszaky/assets/theme.css?v=16.0">
    <style>

        /* =====================================================
           V15 - DAFTAR DISAMAKAN DENGAN HALAMAN LOGIN
        ====================================================== */

        body.login-page {
            background: var(--pz-navy-950) !important;
        }

        .login-box {
            width: 500px;
            max-width: 95%;
        }

        .login-logo a {
            color: #ffffff !important;
            text-decoration: none;
        }

        .login-logo .logo-icon {
            color: var(--pz-gold-500) !important;
        }

        .login-logo .text-muted {
            color: #c9d4e3 !important;
        }

        .card {
            border: 0 !important;
            border-top: 3px solid #ffffff !important;
            border-radius: 14px !important;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .24) !important;
        }

        .login-card-body {
            padding: 30px;
        }

        .login-box-msg {
            color: var(--pz-navy-950);
        }

        .login-box-msg strong {
            font-size: 22px;
        }

        .form-control {
            border-color: var(--pz-navy-200) !important;
            box-shadow: none !important;
        }

        .form-control:focus {
            border-color: var(--pz-gold-500) !important;
        }

        .input-group-text {
            background: var(--pz-gold-100) !important;
            border-color: var(--pz-navy-200) !important;
            color: var(--pz-gold-700) !important;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--pz-gold-500) !important;
            color: var(--pz-gold-700) !important;
        }

        .info-box-daftar {
            padding: 15px;
            border: 1px solid var(--pz-gold-500);
            border-radius: 8px;
            background: var(--pz-gold-100);
            margin-bottom: 20px;
        }

        .info-box-daftar .text-primary,
        .text-primary {
            color: var(--pz-gold-700) !important;
        }

        .alert-info {
            color: var(--pz-navy-950) !important;
            background: var(--pz-gold-100) !important;
            border-color: var(--pz-gold-500) !important;
        }

        .alert-info i {
            color: var(--pz-gold-700);
        }

        .btn-primary {
            background: var(--pz-gold-500) !important;
            border-color: var(--pz-gold-500) !important;
            color: var(--pz-navy-950) !important;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: var(--pz-gold-600) !important;
            border-color: var(--pz-gold-600) !important;
            color: #ffffff !important;
        }

        .text-center a strong,
        .text-center a {
            color: var(--pz-gold-700);
        }

        .text-center a:hover {
            color: var(--pz-gold-600);
        }

        @media (max-width: 500px) {
            .login-card-body {
                padding: 22px;
            }
        }

        .info-box-daftar-original {
            display: none;
        }

        .info-box-daftar {

            padding: 15px;

            border: 1px dashed #ced4da;

            border-radius: 8px;

            background: #f8f9fa;

            margin-bottom: 20px;
        }

    
        /* =====================================================
           V16 - LATAR PUTIH RATA, NAVY HANYA DI BAGIAN ATAS
        ====================================================== */
        body.login-page {
            min-height: 100vh !important;
            background: var(--pz-navy-950) !important;
            background-color: var(--pz-navy-950) !important;
        }

        .login-box {
            position: relative;
            z-index: 1;
        }

        @media (max-width: 500px) {
            body.login-page {
                background: var(--pz-navy-950) !important;
                background-color: var(--pz-navy-950) !important;
            }
        }

    
        /* V18 - latar navy penuh pada Login dan Daftar */
        html, body.login-page {
            background-color: var(--pz-navy-950) !important;
        }

        body.login-page {
            min-height: 100vh !important;
        }

        /* V17 - garis atas kartu form dibuat putih */
        body.login-page .card {
            border-top-color: #ffffff !important;
        }

</style>

</head>


<body class="hold-transition login-page">


<div
    class="login-box"
    style="width: 500px;">


    <!-- LOGO -->

    <div class="login-logo">

        <a href="index.php">

            <i class="fas fa-book-open logo-icon"></i>

            PERPUSTAKAAN ZAKY

        </a>

        <div
            class="text-muted"
            style="font-size:14px;">

            Sistem Informasi Perpustakaan

        </div>

    </div>


    <!-- CARD -->

    <div class="card">

        <div class="card-body login-card-body">


            <p class="login-box-msg">

                <strong>Buat Akun User Baru</strong>

                <br>

                <small class="text-muted">

                    Buat akun untuk anggota yang sudah terdaftar

                </small>

            </p>


            <!-- ERROR -->

            <?php if ($error != "") { ?>

                <div class="alert alert-danger">

                    <i class="fas fa-exclamation-circle mr-1"></i>

                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </div>

            <?php } ?>


            <!-- SUCCESS -->

            <?php if ($success != "") { ?>

                <div class="alert alert-success">

                    <i class="fas fa-check-circle mr-1"></i>

                    <?php
                    echo htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                    <hr>

                    <a
                        href="login.php"
                        class="btn btn-primary btn-sm">

                        <i class="fas fa-sign-in-alt mr-1"></i>

                        Login Sekarang

                    </a>

                </div>

            <?php } ?>


            <!-- INFORMASI -->

            <div class="info-box-daftar">

                <div class="text-primary mb-2">

                    <i class="fas fa-info-circle mr-1"></i>

                    <strong>Informasi Pendaftaran</strong>

                </div>

                <small class="text-muted">

                    Data anggota harus sudah didaftarkan oleh
                    admin sebelum membuat akun login.

                    Gunakan email yang terdaftar pada data anggota.

                </small>

            </div>


            <!-- FORM -->

            <form
                method="POST"
                action="">


                <!-- EMAIL -->

                <div class="form-group mb-3">

                    <label>

                        <i class="fas fa-envelope mr-1"></i>

                        Email Anggota

                        <span class="text-danger">*</span>

                    </label>


                    <div class="input-group">


                        <!-- NAMA EMAIL -->

                        <input
                            type="text"
                            name="email_awal"
                            id="email_awal"
                            class="form-control"
                            placeholder="Contoh: ayyevr2001"
                            value="<?php
                            echo isset($_POST['email_awal'])
                                ? htmlspecialchars(
                                    $_POST['email_awal'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '';
                            ?>"
                            autocomplete="email"
                            required>


                        <!-- DOMAIN -->

                        <select
                            name="email_domain"
                            id="email_domain"
                            class="form-control"
                            style="max-width:180px;"
                            required>


                            <option
                                value="@gmail.com"
                                <?php

                                echo (
                                    !isset($_POST['email_domain']) ||
                                    $_POST['email_domain'] == '@gmail.com'
                                )
                                    ? 'selected'
                                    : '';

                                ?>>

                                @gmail.com

                            </option>


                            <option
                                value="@yahoo.com"
                                <?php

                                echo (
                                    isset($_POST['email_domain']) &&
                                    $_POST['email_domain'] == '@yahoo.com'
                                )
                                    ? 'selected'
                                    : '';

                                ?>>

                                @yahoo.com

                            </option>


                            <option
                                value="@outlook.com"
                                <?php

                                echo (
                                    isset($_POST['email_domain']) &&
                                    $_POST['email_domain'] == '@outlook.com'
                                )
                                    ? 'selected'
                                    : '';

                                ?>>

                                @outlook.com

                            </option>


                            <option
                                value="@hotmail.com"
                                <?php

                                echo (
                                    isset($_POST['email_domain']) &&
                                    $_POST['email_domain'] == '@hotmail.com'
                                )
                                    ? 'selected'
                                    : '';

                                ?>>

                                @hotmail.com

                            </option>


                            <option
                                value="lainnya"
                                <?php

                                echo (
                                    isset($_POST['email_domain']) &&
                                    $_POST['email_domain'] == 'lainnya'
                                )
                                    ? 'selected'
                                    : '';

                                ?>>

                                Lainnya

                            </option>


                        </select>


                        <div class="input-group-append">

                            <div class="input-group-text">

                                <span class="fas fa-envelope"></span>

                            </div>

                        </div>

                    </div>


                    <!-- DOMAIN CUSTOM -->

                    <div
                        id="domainCustomBox"
                        class="mt-2"
                        style="display:none;">

                        <input
                            type="text"
                            name="email_domain_custom"
                            id="email_domain_custom"
                            class="form-control"
                            placeholder="Contoh: @proton.me"
                            value="<?php
                            echo isset($_POST['email_domain_custom'])
                                ? htmlspecialchars(
                                    $_POST['email_domain_custom'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '';
                            ?>">

                        <small class="text-muted">

                            Masukkan domain, contoh:

                            @proton.me

                        </small>

                    </div>


                    <small class="text-muted">

                        Gunakan email yang sudah didaftarkan
                        oleh admin.

                    </small>

                </div>


                <hr>


                <!-- DATA AKUN -->

                <div class="text-primary mb-3">

                    <i class="fas fa-lock mr-1"></i>

                    <strong>Data Akun Login</strong>

                </div>


                <!-- USERNAME -->

                <div class="input-group mb-3">

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="Username"
                        value="<?php
                        echo isset($_POST['username'])
                            ? htmlspecialchars(
                                $_POST['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            : '';
                        ?>"
                        autocomplete="username"
                        required>


                    <div class="input-group-append">

                        <div class="input-group-text">

                            <span class="fas fa-user-circle"></span>

                        </div>

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="input-group mb-3">

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Password minimal 8 karakter"
                        autocomplete="new-password"
                        required>


                    <div class="input-group-append">

                        <div class="input-group-text">

                            <span class="fas fa-lock"></span>

                        </div>

                    </div>

                </div>


                <!-- KONFIRMASI -->

                <div class="input-group mb-3">

                    <input
                        type="password"
                        name="konfirmasi"
                        class="form-control"
                        placeholder="Konfirmasi Password"
                        autocomplete="new-password"
                        required>


                    <div class="input-group-append">

                        <div class="input-group-text">

                            <span class="fas fa-lock"></span>

                        </div>

                    </div>

                </div>


                <!-- INFO ROLE -->

                <div class="alert alert-info">

                    <i class="fas fa-user-check mr-1"></i>

                    Akun yang didaftarkan otomatis menjadi

                    <strong>User</strong>.

                    <br>

                    <small>

                        Akun terhubung dengan data anggota
                        berdasarkan email.

                    </small>

                </div>


                <!-- TOMBOL -->

                <button
                    type="submit"
                    name="daftar"
                    class="btn btn-primary btn-block">

                    <i class="fas fa-user-plus mr-1"></i>

                    Daftar Sekarang

                </button>


            </form>


            <!-- LOGIN -->

            <div class="text-center mt-3">

                <p class="mb-0">

                    Sudah punya akun?

                    <a href="login.php">

                        <strong>

                            Login sekarang

                        </strong>

                    </a>

                </p>

            </div>


            <!-- HOME -->

            <div class="text-center mt-2">

                <a
                    href="index.php"
                    class="text-secondary">

                    <i class="fas fa-home mr-1"></i>

                    Kembali ke halaman utama

                </a>

            </div>


        </div>

    </div>

</div>


<!-- JAVASCRIPT -->

<script
    src="/perpuszaky/plugins/jquery/jquery.min.js">
</script>

<script
    src="/perpuszaky/plugins/bootstrap/js/bootstrap.bundle.min.js">
</script>

<script
    src="/perpuszaky/dist/js/adminlte.min.js">
</script>


<script>


/* ========================================================
   EMAIL DOMAIN OTOMATIS
======================================================== */

const emailDomain =
    document.getElementById('email_domain');

const domainCustomBox =
    document.getElementById('domainCustomBox');

const emailDomainCustom =
    document.getElementById('email_domain_custom');


function cekDomainEmail() {

    if (emailDomain.value === 'lainnya') {

        domainCustomBox.style.display = 'block';

        emailDomainCustom.required = true;

    } else {

        domainCustomBox.style.display = 'none';

        emailDomainCustom.required = false;

    }

}


emailDomain.addEventListener(
    'change',
    cekDomainEmail
);


cekDomainEmail();


/* ========================================================
   HILANGKAN @ DARI INPUT EMAIL AWAL
======================================================== */

document.getElementById('email_awal')
    .addEventListener(
        'input',
        function() {

            this.value =
                this.value
                .toLowerCase()
                .replace(/\s/g, '')
                .split('@')[0];

        }
    );


/* ========================================================
   DOMAIN CUSTOM OTOMATIS
======================================================== */

emailDomainCustom.addEventListener(
    'blur',
    function() {

        let value =
            this.value
            .trim()
            .toLowerCase()
            .replace(/\s/g, '');

        if (
            value !== "" &&
            !value.startsWith("@")
        ) {

            value = "@" + value;

        }

        this.value = value;

    }
);

</script>


</body>

</html>