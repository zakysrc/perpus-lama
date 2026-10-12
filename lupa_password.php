<?php

require_once __DIR__ . '/inc/security.php';

include "koneksi.php";

$error = "";

$success = "";


/* =========================================================
   STATUS PROSES
========================================================= */

$step = 1;


/* =========================================================
   CEK APAKAH SUDAH LOLOS VERIFIKASI
========================================================= */

if (
    isset($_SESSION['reset_user_id']) &&
    isset($_SESSION['reset_time'])
) {

    /*
     * Masa verifikasi berlaku 10 menit.
     */

    if (
        (time() - $_SESSION['reset_time']) <= 600
    ) {

        $step = 2;

    } else {

        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_time']);

        $step = 1;

        $error =
            "Sesi verifikasi telah berakhir. Silakan verifikasi kembali.";

    }

}


/* =========================================================
   PROSES VERIFIKASI AKUN
========================================================= */

if (
    isset($_POST['verifikasi']) &&
    $step == 1
) {

    $username = trim(
        $_POST['username'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $tgl_lahir = trim(
        $_POST['tgl_lahir'] ?? ''
    );


    /* =====================================================
       VALIDASI
    ===================================================== */

    if (
        $username == "" ||
        $email == "" ||
        $tgl_lahir == ""
    ) {

        $error =
            "Semua data verifikasi wajib diisi.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error =
            "Format email tidak valid.";

    } else {


        /* =================================================
           CARI AKUN + DATA ANGGOTA
        ================================================= */

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT
                u.id_user,
                u.id_ang,
                u.username,
                u.status,
                a.email,
                a.tgl_lahir
             FROM tbuser u
             INNER JOIN tbanggota a
                ON u.id_ang = a.id_ang
             WHERE u.username = ?
               AND a.email = ?
               AND a.tgl_lahir = ?
             LIMIT 1"
        );


        if (!$stmt) {

            $error =
                "Terjadi kesalahan sistem: " .
                mysqli_error($koneksi);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $username,
                $email,
                $tgl_lahir
            );


            mysqli_stmt_execute($stmt);


            $result =
                mysqli_stmt_get_result($stmt);


            if (
                $result &&
                mysqli_num_rows($result) == 1
            ) {

                $data =
                    mysqli_fetch_assoc($result);


                /* =========================================
                   SIMPAN ID USER UNTUK PROSES RESET
                ========================================== */

                $_SESSION['reset_user_id'] =
                    $data['id_user'];

                $_SESSION['reset_username'] =
                    $data['username'];

                $_SESSION['reset_time'] =
                    time();


                $step = 2;


            } else {

                $error =
                    "Data verifikasi tidak cocok. Periksa kembali username, email, dan tanggal lahir.";

            }


            mysqli_stmt_close($stmt);

        }

    }

}


/* =========================================================
   PROSES PASSWORD BARU
========================================================= */

if (
    isset($_POST['ubah_password']) &&
    $step == 2
) {

    $password_baru =
        $_POST['password_baru'] ?? '';

    $konfirmasi_password =
        $_POST['konfirmasi_password'] ?? '';


    /* =====================================================
       VALIDASI PASSWORD
    ===================================================== */

    if (
        $password_baru == "" ||
        $konfirmasi_password == ""
    ) {

        $error =
            "Password baru dan konfirmasi password wajib diisi.";

    } elseif (
        strlen($password_baru) < 8
    ) {

        $error =
            "Password baru minimal 8 karakter.";

    } elseif (
        $password_baru !== $konfirmasi_password
    ) {

        $error =
            "Konfirmasi password tidak sama.";

    } else {


        /* =================================================
           CEK SESSION RESET
        ================================================= */

        if (
            !isset($_SESSION['reset_user_id']) ||
            !isset($_SESSION['reset_time'])
        ) {

            $error =
                "Sesi reset tidak valid. Silakan ulangi proses.";

            $step = 1;

        } elseif (
            (time() - $_SESSION['reset_time']) > 600
        ) {

            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_username']);
            unset($_SESSION['reset_time']);

            $error =
                "Sesi reset telah berakhir. Silakan verifikasi kembali.";

            $step = 1;

        } else {


            $id_user =
                (int) $_SESSION['reset_user_id'];


            /* =================================================
               HASH PASSWORD BARU
            ================================================== */

            $password_hash =
                password_hash(
                    $password_baru,
                    PASSWORD_DEFAULT
                );


            /* =================================================
               UPDATE PASSWORD
            ================================================== */

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE tbuser
                 SET password = ?
                 WHERE id_user = ?
                 LIMIT 1"
            );


            if (!$stmt) {

                $error =
                    "Terjadi kesalahan sistem: " .
                    mysqli_error($koneksi);

                $step = 2;

            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $password_hash,
                    $id_user
                );


                if (
                    mysqli_stmt_execute($stmt)
                ) {


                    /* =========================================
                       HAPUS SESSION RESET
                    ========================================== */

                    unset(
                        $_SESSION['reset_user_id']
                    );

                    unset(
                        $_SESSION['reset_username']
                    );

                    unset(
                        $_SESSION['reset_time']
                    );


                    /* =========================================
                       PESAN BERHASIL
                    ========================================== */

                    header(
                        "Location: login.php?reset=berhasil"
                    );

                    exit;


                } else {

                    $error =
                        "Password gagal diperbarui. Silakan coba lagi.";

                    $step = 2;

                }


                mysqli_stmt_close($stmt);

            }

        }

    }

}


/* =========================================================
   BATALKAN RESET
========================================================= */

if (
    isset($_GET['batal']) &&
    $_GET['batal'] == "1"
) {

    unset(
        $_SESSION['reset_user_id']
    );

    unset(
        $_SESSION['reset_username']
    );

    unset(
        $_SESSION['reset_time']
    );


    header(
        "Location: login.php"
    );

    exit;

}

?>


<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Lupa Password | Perpustakaan Zaky
    </title>


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        rel="stylesheet"
        href="/perpuszaky/plugins/fontawesome-free/css/all.min.css"
    >


    <!-- =====================================================
         ADMINLTE
    ====================================================== -->

    <link
        rel="stylesheet"
        href="/perpuszaky/dist/css/adminlte.min.css"
    >


    <link rel="stylesheet" href="/perpuszaky/assets/theme.css?v=5.0">
    <style>

        /* =====================================================
           DASAR
        ====================================================== */

        body {

            min-height: 100vh;

            background:
                linear-gradient(
                    135deg,
                    var(--pz-navy-50) 0%,
                    var(--pz-navy-100) 50%,
                    var(--pz-navy-50) 100%
                );

        }


        /* =====================================================
           BOX
        ====================================================== */

        .forgot-box {

            width: 460px;

            max-width: 95%;

        }


        /* =====================================================
           LOGO
        ====================================================== */

        .forgot-logo {

            margin-bottom: 24px;

            text-align: center;

        }


        .forgot-logo a {

            color: var(--pz-navy-900);

            font-size: 27px;

            font-weight: 700;

            text-decoration: none;

        }


        .forgot-logo a:hover {

            color: var(--pz-navy-700);

        }


        .forgot-logo .logo-icon {

            color: var(--pz-navy-700);

            margin-right: 6px;

        }


        .forgot-logo .subtitle {

            display: block;

            margin-top: 6px;

            color: #6b7280;

            font-size: 13px;

        }


        /* =====================================================
           CARD
        ====================================================== */

        .forgot-card {

            border: 0;

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 10px 35px
                rgba(15, 31, 56, .12);

        }


        .forgot-card-body {

            padding: 30px;

        }


        /* =====================================================
           ICON HEADER
        ====================================================== */

        .page-icon {

            width: 58px;

            height: 58px;

            margin: 0 auto 15px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: var(--pz-navy-50);

            color: var(--pz-navy-700);

            font-size: 24px;

        }


        /* =====================================================
           JUDUL
        ====================================================== */

        .forgot-title {

            margin-bottom: 7px;

            color: var(--pz-navy-900);

            font-size: 21px;

            font-weight: 700;

            text-align: center;

        }


        .forgot-description {

            margin-bottom: 25px;

            color: #6b7280;

            font-size: 14px;

            line-height: 1.6;

            text-align: center;

        }


        /* =====================================================
           ALERT
        ====================================================== */

        .custom-alert {

            border: 0;

            border-radius: 8px;

            font-size: 13px;

            margin-bottom: 20px;

        }


        /* =====================================================
           LABEL
        ====================================================== */

        .form-label {

            margin-bottom: 7px;

            color: #334155;

            font-size: 13px;

            font-weight: 600;

        }


        /* =====================================================
           INPUT
        ====================================================== */

        .input-group {

            margin-bottom: 18px;

        }


        .form-control {

            height: 45px;

            border: 1px solid var(--pz-navy-200);

            border-right: 0;

            font-size: 14px;

            box-shadow: none !important;

        }


        .form-control:focus {

            border-color: var(--pz-navy-700);

        }


        .input-group-text {

            min-width: 45px;

            justify-content: center;

            background: var(--pz-navy-50);

            border: 1px solid var(--pz-navy-200);

            color: #64748b;

        }


        .input-group:focus-within
        .input-group-text {

            border-color: var(--pz-navy-700);

            color: var(--pz-navy-700);

        }


        /* =====================================================
           BUTTON
        ====================================================== */

        .btn-reset {

            height: 45px;

            border: none;

            border-radius: 7px;

            background: var(--pz-navy-700);

            color: #ffffff;

            font-weight: 600;

            transition: .2s ease;

        }


        .btn-reset:hover {

            background: var(--pz-navy-900);

            color: #ffffff;

            transform: translateY(-1px);

        }


        /* =====================================================
           INFO
        ====================================================== */

        .info-box-custom {

            padding: 12px 14px;

            margin-bottom: 20px;

            border-radius: 8px;

            background: var(--pz-navy-50);

            border: 1px solid var(--pz-navy-100);

            color: #64748b;

            font-size: 12px;

            line-height: 1.6;

        }


        .info-box-custom i {

            color: var(--pz-navy-700);

        }


        /* =====================================================
           BACK LINK
        ====================================================== */

        .back-link {

            color: #6b7280;

            font-size: 13px;

            text-decoration: none;

        }


        .back-link:hover {

            color: var(--pz-navy-700);

        }


        /* =====================================================
           PASSWORD INFO
        ====================================================== */

        .password-info {

            margin-top: -10px;

            margin-bottom: 18px;

            color: #94a3b8;

            font-size: 12px;

        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 500px) {

            .forgot-card-body {

                padding: 22px;

            }


            .forgot-logo a {

                font-size: 23px;

            }

        }

    </style>

</head>


<body class="hold-transition login-page">


<div class="forgot-box">


    <!-- =====================================================
         LOGO
    ====================================================== -->

    <div class="forgot-logo">

        <a href="login.php">

            <i
                class="fas fa-book-open logo-icon"
            ></i>

            PERPUSTAKAAN ZAKY

        </a>


        <span class="subtitle">

            Sistem Informasi Perpustakaan

        </span>

    </div>


    <!-- =====================================================
         CARD
    ====================================================== -->

    <div class="card forgot-card">

        <div class="card-body forgot-card-body">


            <!-- =================================================
                 ICON
            ================================================== -->

            <div class="page-icon">

                <i class="fas fa-key"></i>

            </div>


            <!-- =================================================
                 JUDUL
            ================================================== -->

            <?php if ($step == 1) { ?>

                <div class="forgot-title">

                    Lupa Password?

                </div>


                <div class="forgot-description">

                    Verifikasi data akun Anda untuk membuat
                    password baru.

                </div>

            <?php } else { ?>

                <div class="forgot-title">

                    Buat Password Baru

                </div>


                <div class="forgot-description">

                    Silakan masukkan password baru untuk
                    akun Anda.

                </div>

            <?php } ?>


            <!-- =================================================
                 ERROR
            ================================================== -->

            <?php if ($error != "") { ?>

                <div
                    class="alert alert-danger custom-alert"
                >

                    <i
                        class="fas fa-exclamation-circle mr-1"
                    ></i>

                    <?php

                    echo htmlspecialchars($error);

                    ?>

                </div>

            <?php } ?>


            <!-- =================================================
                 STEP 1
                 VERIFIKASI
            ================================================== -->

            <?php if ($step == 1) { ?>


                <div class="info-box-custom">

                    <i class="fas fa-info-circle mr-1"></i>

                    Masukkan username, email, dan tanggal
                    lahir yang terdaftar pada akun Anda.

                </div>


                <form
                    method="POST"
                    action=""
                >


                    <!-- USERNAME -->

                    <label class="form-label">

                        Username

                    </label>


                    <div class="input-group">

                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            placeholder="Masukkan username"
                            autocomplete="username"
                            value="<?php

                                echo isset(
                                    $_POST['username']
                                )

                                    ? htmlspecialchars(
                                        $_POST['username']
                                    )

                                    : '';

                            ?>"
                            required
                        >


                        <div class="input-group-append">

                            <div class="input-group-text">

                                <span
                                    class="fas fa-user"
                                ></span>

                            </div>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <label class="form-label">

                        Email

                    </label>


                    <div class="input-group">

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="Masukkan email"
                            autocomplete="email"
                            value="<?php

                                echo isset(
                                    $_POST['email']
                                )

                                    ? htmlspecialchars(
                                        $_POST['email']
                                    )

                                    : '';

                            ?>"
                            required
                        >


                        <div class="input-group-append">

                            <div class="input-group-text">

                                <span
                                    class="fas fa-envelope"
                                ></span>

                            </div>

                        </div>

                    </div>


                    <!-- TANGGAL LAHIR -->

                    <label class="form-label">

                        Tanggal Lahir

                    </label>


                    <div class="input-group">

                        <input
                            type="date"
                            name="tgl_lahir"
                            class="form-control"
                            required
                        >


                        <div class="input-group-append">

                            <div class="input-group-text">

                                <span
                                    class="fas fa-calendar-alt"
                                ></span>

                            </div>

                        </div>

                    </div>


                    <!-- VERIFIKASI -->

                    <button
                        type="submit"
                        name="verifikasi"
                        class="btn btn-reset btn-block"
                    >

                        <i
                            class="fas fa-user-check mr-1"
                        ></i>

                        Verifikasi Akun

                    </button>


                </form>


            <?php } ?>


            <!-- =================================================
                 STEP 2
                 PASSWORD BARU
            ================================================== -->

            <?php if ($step == 2) { ?>


                <div class="info-box-custom">

                    <i class="fas fa-shield-alt mr-1"></i>

                    Identitas akun berhasil diverifikasi.
                    Silakan buat password baru.

                </div>


                <form
                    method="POST"
                    action=""
                >


                    <!-- PASSWORD BARU -->

                    <label class="form-label">

                        Password Baru

                    </label>


                    <div class="input-group">

                        <input
                            type="password"
                            name="password_baru"
                            id="password_baru"
                            class="form-control"
                            placeholder="Masukkan password baru"
                            autocomplete="new-password"
                            required
                        >


                        <div class="input-group-append">

                            <div
                                class="input-group-text"
                                id="togglePasswordBaru"
                                style="cursor:pointer;"
                                title="Tampilkan password"
                            >

                                <span
                                    class="fas fa-eye"
                                ></span>

                            </div>

                        </div>

                    </div>


                    <div class="password-info">

                        <i class="fas fa-info-circle mr-1"></i>

                        Password minimal 8 karakter.

                    </div>


                    <!-- KONFIRMASI PASSWORD -->

                    <label class="form-label">

                        Konfirmasi Password

                    </label>


                    <div class="input-group">

                        <input
                            type="password"
                            name="konfirmasi_password"
                            id="konfirmasi_password"
                            class="form-control"
                            placeholder="Ulangi password baru"
                            autocomplete="new-password"
                            required
                        >


                        <div class="input-group-append">

                            <div
                                class="input-group-text"
                                id="toggleKonfirmasi"
                                style="cursor:pointer;"
                                title="Tampilkan password"
                            >

                                <span
                                    class="fas fa-eye"
                                ></span>

                            </div>

                        </div>

                    </div>


                    <!-- UBAH PASSWORD -->

                    <button
                        type="submit"
                        name="ubah_password"
                        class="btn btn-reset btn-block"
                    >

                        <i
                            class="fas fa-save mr-1"
                        ></i>

                        Simpan Password Baru

                    </button>


                </form>


                <!-- BATAL -->

                <div
                    class="text-center mt-3"
                >

                    <a
                        href="lupa_password.php?batal=1"
                        class="back-link"
                    >

                        <i
                            class="fas fa-arrow-left mr-1"
                        ></i>

                        Batalkan

                    </a>

                </div>


            <?php } ?>


            <!-- =================================================
                 KEMBALI LOGIN
            ================================================== -->

            <?php if ($step == 1) { ?>

                <div
                    class="text-center mt-4"
                >

                    <a
                        href="login.php"
                        class="back-link"
                    >

                        <i
                            class="fas fa-arrow-left mr-1"
                        ></i>

                        Kembali ke Login

                    </a>

                </div>

            <?php } ?>


        </div>

    </div>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="/perpuszaky/plugins/jquery/jquery.min.js"
></script>


<script
    src="/perpuszaky/plugins/bootstrap/js/bootstrap.bundle.min.js"
></script>


<script
    src="/perpuszaky/dist/js/adminlte.min.js"
></script>


<script>

    /* =====================================================
       SHOW / HIDE PASSWORD BARU
    ====================================================== */

    const togglePasswordBaru =
        document.getElementById(
            "togglePasswordBaru"
        );

    const passwordBaru =
        document.getElementById(
            "password_baru"
        );


    if (
        togglePasswordBaru &&
        passwordBaru
    ) {

        togglePasswordBaru.addEventListener(
            "click",
            function () {

                const icon =
                    this.querySelector("span");


                if (
                    passwordBaru.type === "password"
                ) {

                    passwordBaru.type = "text";

                    icon.classList.remove(
                        "fa-eye"
                    );

                    icon.classList.add(
                        "fa-eye-slash"
                    );

                    this.title =
                        "Sembunyikan password";

                } else {

                    passwordBaru.type =
                        "password";

                    icon.classList.remove(
                        "fa-eye-slash"
                    );

                    icon.classList.add(
                        "fa-eye"
                    );

                    this.title =
                        "Tampilkan password";

                }

            }
        );

    }


    /* =====================================================
       SHOW / HIDE KONFIRMASI PASSWORD
    ====================================================== */

    const toggleKonfirmasi =
        document.getElementById(
            "toggleKonfirmasi"
        );

    const konfirmasiPassword =
        document.getElementById(
            "konfirmasi_password"
        );


    if (
        toggleKonfirmasi &&
        konfirmasiPassword
    ) {

        toggleKonfirmasi.addEventListener(
            "click",
            function () {

                const icon =
                    this.querySelector("span");


                if (
                    konfirmasiPassword.type ===
                    "password"
                ) {

                    konfirmasiPassword.type =
                        "text";

                    icon.classList.remove(
                        "fa-eye"
                    );

                    icon.classList.add(
                        "fa-eye-slash"
                    );

                    this.title =
                        "Sembunyikan password";

                } else {

                    konfirmasiPassword.type =
                        "password";

                    icon.classList.remove(
                        "fa-eye-slash"
                    );

                    icon.classList.add(
                        "fa-eye"
                    );

                    this.title =
                        "Tampilkan password";

                }

            }
        );

    }

</script>


</body>

</html>