<?php

require_once __DIR__ . '/inc/security.php';

include "koneksi.php";

$error = "";

$success = "";


/* =========================================================
   PESAN BERHASIL DAFTAR
========================================================= */

if (isset($_GET['daftar']) && $_GET['daftar'] == "berhasil") {

    $success = "Pendaftaran berhasil. Silakan login menggunakan akun Anda.";

}


/* =========================================================
   PESAN DARI SISTEM (mis. sesi berakhir karena tidak aktif)
========================================================= */

if (!empty($_SESSION['pesan_login'])) {

    $error = $_SESSION['pesan_login'];

    unset($_SESSION['pesan_login']);

}


/* =========================================================
   PROSES LOGIN
========================================================= */

if (isset($_POST['login'])) {

    $username = trim($_POST['username']);

    $password = $_POST['password'];


    /* =====================================================
       VALIDASI
    ===================================================== */

    if ($username == "" || $password == "") {

        $error = "Username dan password wajib diisi.";

    } elseif (
        ($menit_kunci = login_menit_terkunci($koneksi, $username)) > 0
    ) {

        $error = "Terlalu banyak percobaan login yang gagal. "
               . "Silakan coba lagi dalam $menit_kunci menit.";

    } else {

        /* =================================================
           CARI USER (prepared statement)
        ================================================= */

        $user = null;

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id_user, id_ang, username, password, status
             FROM tbuser
             WHERE username = ?
             LIMIT 1"
        );

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $username);

            mysqli_stmt_execute($stmt);

            mysqli_stmt_bind_result(
                $stmt,
                $u_id_user,
                $u_id_ang,
                $u_username,
                $u_password,
                $u_status
            );

            if (mysqli_stmt_fetch($stmt)) {

                $user = [
                    'id_user'  => $u_id_user,
                    'id_ang'   => $u_id_ang,
                    'username' => $u_username,
                    'password' => $u_password,
                    'status'   => $u_status,
                ];
            }

            mysqli_stmt_close($stmt);
        }


        if ($user !== null) {


            /* =================================================
               CEK PASSWORD
            ================================================= */

            /*
             * Hash modern diverifikasi dengan password_verify().
             * Password lama (plaintext) hanya diterima satu kali,
             * lalu otomatis di-hash. Lihat inc/security.php.
             */

            $password_valid = verifikasi_password(
                $koneksi,
                $user,
                $password
            );


            /* =================================================
               LOGIN BERHASIL
            ================================================= */

            if ($password_valid) {


                /* =============================================
                   CEK ROLE
                ============================================== */

                if (
                    $user['status'] != "admin" &&
                    $user['status'] != "user"
                ) {

                    $error =
                        "Role akun tidak dikenali.";

                } else {


                    /* =========================================
                       SESSION
                    ========================================== */

                    /* Cegah session fixation: ID session baru saat login */
                    session_regenerate_id(true);

                    login_bersihkan($koneksi, $username);

                    $_SESSION['login'] = true;

                    $_SESSION['id_user'] =
                        $user['id_user'];

                    $_SESSION['id_ang'] =
                        $user['id_ang'];

                    $_SESSION['username'] =
                        $user['username'];

                    $_SESSION['status'] =
                        $user['status'];

                    catat_log('login', 'Login berhasil');


                    /* =========================================
                       REDIRECT ADMIN
                    ========================================== */

                    if (
                        strtolower(
                            trim($user['status'])
                        ) === 'admin'
                    ) {

                        header(
                            "Location: beranda_admin.php"
                        );

                        exit;

                    }


                    /* =========================================
                       REDIRECT USER
                    ========================================== */

                    if (
                        strtolower(
                            trim($user['status'])
                        ) === 'user'
                    ) {

                        header(
                            "Location: beranda_user.php"
                        );

                        exit;

                    }

                }


            } else {

                login_catat_gagal($koneksi, $username);
                catat_log('login_gagal', 'Password salah untuk "' . mb_substr($username, 0, 35) . '"');

                $error = "Username atau password salah.";

            }


        } else {

            login_catat_gagal($koneksi, $username);
            catat_log('login_gagal', 'Username "' . mb_substr($username, 0, 35) . '" tidak terdaftar');

            $error = "Username atau password salah.";

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
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login | Perpustakaan Zaky
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


    <link rel="stylesheet" href="/perpuszaky/assets/theme.css?v=16.0">
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
           LOGIN BOX
        ====================================================== */

        .login-box {

            width: 420px;

            max-width: 95%;

        }


        /* =====================================================
           LOGO
        ====================================================== */

        .login-logo {

            margin-bottom: 24px;

        }


        .login-logo a {

            color: var(--pz-navy-900);

            font-weight: 700;

            text-decoration: none;

            font-size: 28px;

        }


        .login-logo a:hover {

            color: var(--pz-navy-700);

        }


        .login-logo .logo-icon {

            color: var(--pz-navy-700);

            margin-right: 6px;

        }


        .login-logo .logo-subtitle {

            display: block;

            margin-top: 6px;

            color: #6b7280;

            font-size: 13px;

            font-weight: 400;

        }


        /* =====================================================
           CARD
        ====================================================== */

        .login-card {

            border: 0;

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 10px 35px
                rgba(15, 31, 56, .12);

        }


        .login-card-body {

            padding: 30px;

        }


        /* =====================================================
           JUDUL
        ====================================================== */

        .login-title {

            margin-bottom: 6px;

            color: var(--pz-navy-900);

            font-size: 22px;

            font-weight: 700;

            text-align: center;

        }


        .login-description {

            margin-bottom: 25px;

            color: #6b7280;

            font-size: 14px;

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
           TOGGLE PASSWORD
        ====================================================== */

        .password-toggle {

            cursor: pointer;

            user-select: none;

        }


        .password-toggle:hover {

            background: var(--pz-navy-50);

            color: var(--pz-navy-700);

        }


        /* =====================================================
           LUPA PASSWORD
        ====================================================== */

        .forgot-password {

            color: var(--pz-navy-700);

            font-size: 13px;

            font-weight: 600;

            text-decoration: none;

        }


        .forgot-password:hover {

            color: var(--pz-navy-900);

            text-decoration: underline;

        }


        /* =====================================================
           BUTTON LOGIN
        ====================================================== */

        .btn-login {

            height: 45px;

            border: none;

            border-radius: 7px;

            background: var(--pz-navy-700);

            border-color: var(--pz-navy-700);

            color: #ffffff;

            font-weight: 600;

            transition: .2s ease;

        }


        .btn-login:hover {

            background: var(--pz-navy-900);

            border-color: var(--pz-navy-900);

            color: #ffffff;

            transform: translateY(-1px);

        }


        .btn-login:active {

            transform: translateY(0);

        }


        /* =====================================================
           PEMISAH
        ====================================================== */

        .login-divider {

            display: flex;

            align-items: center;

            margin: 24px 0;

            color: #94a3b8;

            font-size: 12px;

        }


        .login-divider::before,
        .login-divider::after {

            content: "";

            flex: 1;

            height: 1px;

            background: var(--pz-navy-100);

        }


        .login-divider span {

            padding: 0 12px;

        }


        /* =====================================================
           LINK DAFTAR
        ====================================================== */

        .register-text {

            color: #6b7280;

            font-size: 14px;

        }


        .register-link {

            color: var(--pz-navy-700);

            font-weight: 600;

            text-decoration: none;

        }


        .register-link:hover {

            color: var(--pz-navy-900);

            text-decoration: underline;

        }


        /* =====================================================
           HOME
        ====================================================== */

        .home-link {

            color: #6b7280;

            font-size: 13px;

            text-decoration: none;

        }


        .home-link:hover {

            color: var(--pz-navy-700);

        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        /* =====================================================
           V14 - PALET SAMA DENGAN HALAMAN HOME
        ====================================================== */

        body.login-page {
            background: var(--pz-navy-950) !important;
        }

        .login-logo a {
            color: #ffffff !important;
        }

        .login-logo .logo-icon {
            color: var(--pz-gold-500) !important;
        }

        .login-logo .logo-subtitle {
            color: #c9d4e3 !important;
        }

        .login-card {
            border-top: 3px solid #ffffff;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .24);
        }

        .login-title {
            color: var(--pz-navy-950) !important;
        }

        .login-description,
        .register-text,
        .home-link {
            color: #5d6b80 !important;
        }

        .form-control {
            border-color: var(--pz-navy-200) !important;
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

        .password-toggle:hover {
            background: var(--pz-gold-100) !important;
            color: var(--pz-gold-700) !important;
        }

        .forgot-password,
        .register-link {
            color: var(--pz-gold-700) !important;
        }

        .forgot-password:hover,
        .register-link:hover,
        .home-link:hover {
            color: var(--pz-gold-600) !important;
        }

        .btn-login {
            background: var(--pz-gold-500) !important;
            border-color: var(--pz-gold-500) !important;
            color: var(--pz-navy-950) !important;
        }

        .btn-login:hover {
            background: var(--pz-gold-600) !important;
            border-color: var(--pz-gold-600) !important;
            color: #ffffff !important;
        }

        .login-divider::before,
        .login-divider::after {
            background: var(--pz-navy-100) !important;
        }

        @media (max-width: 500px) {

            .login-card-body {

                padding: 22px;

            }


            .login-logo a {

                font-size: 24px;

            }

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
        .login-card, .card.login-card, body.login-page .card {
            border-top-color: #ffffff !important;
        }

</style>

</head>


<body class="hold-transition login-page">


<div class="login-box">


    <!-- =====================================================
         LOGO
    ====================================================== -->

    <div class="login-logo">

        <a href="index.php">

            <i
                class="fas fa-book-open logo-icon"
            ></i>

            PERPUSTAKAAN ZAKY

        </a>


        <span class="logo-subtitle">

            Sistem Informasi Perpustakaan

        </span>

    </div>


    <!-- =====================================================
         CARD LOGIN
    ====================================================== -->

    <div class="card login-card">

        <div class="card-body login-card-body">


            <!-- =================================================
                 JUDUL
            ================================================== -->

            <div class="login-title">

                Selamat Datang

            </div>


            <div class="login-description">

                Silakan login untuk masuk ke sistem

            </div>


            <!-- =================================================
                 BERHASIL DAFTAR
            ================================================== -->

            <?php if ($success != "") { ?>

                <div
                    class="alert alert-success custom-alert"
                >

                    <i
                        class="fas fa-check-circle mr-1"
                    ></i>

                    <?php

                    echo htmlspecialchars($success);

                    ?>

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
                 FORM LOGIN
            ================================================== -->

            <form
                method="POST"
                action=""
            >


                <!-- USERNAME -->

                <div class="input-group">

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="Username"
                        autocomplete="username"
                        value="<?php

                            echo isset($_POST['username'])

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


                <!-- PASSWORD -->

                <div class="input-group">

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Password"
                        autocomplete="current-password"
                        required
                    >


                    <div class="input-group-append">

                        <div
                            class="input-group-text password-toggle"
                            id="togglePassword"
                            title="Tampilkan password"
                        >

                            <span
                                class="fas fa-eye"
                            ></span>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     LUPA PASSWORD
                ================================================== -->

                <div
                    class="text-right mb-3"
                >

                    <a
                        href="lupa_password.php"
                        class="forgot-password"
                    >

                        Lupa Password?

                    </a>

                </div>


                <!-- =================================================
                     LOGIN
                ================================================== -->

                <button
                    type="submit"
                    name="login"
                    class="btn btn-login btn-block"
                >

                    <i
                        class="fas fa-sign-in-alt mr-1"
                    ></i>

                    Login

                </button>


            </form>


            <!-- =================================================
                 PEMISAH
            ================================================== -->

            <div class="login-divider">

                <span>
                    ATAU
                </span>

            </div>


            <!-- =================================================
                 REGISTER USER
            ================================================== -->

            <div class="text-center register-text">

                Belum punya akun?

                <a
                    href="daftar.php"
                    class="register-link ml-1"
                >

                    Daftar sebagai User

                </a>

            </div>


            <!-- =================================================
                 HOME
            ================================================== -->

            <div
                class="text-center mt-4"
            >

                <a
                    href="index.php"
                    class="home-link"
                >

                    <i
                        class="fas fa-home mr-1"
                    ></i>

                    Kembali ke halaman utama

                </a>

            </div>


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
       SHOW / HIDE PASSWORD
    ====================================================== */

    const togglePassword =
        document.getElementById("togglePassword");

    const password =
        document.getElementById("password");


    if (
        togglePassword &&
        password
    ) {

        togglePassword.addEventListener(
            "click",
            function () {

                const icon =
                    this.querySelector("span");


                if (
                    password.type === "password"
                ) {

                    password.type = "text";


                    icon.classList.remove(
                        "fa-eye"
                    );


                    icon.classList.add(
                        "fa-eye-slash"
                    );


                    this.title =
                        "Sembunyikan password";


                } else {

                    password.type = "password";


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
