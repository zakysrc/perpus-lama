<?php

/* =========================================================
   DETAIL BUKU - PERPUS ZAKY
   HALAMAN PUBLIK - TIDAK MENGUBAH DATABASE
========================================================= */

require_once __DIR__ . '/inc/security.php';

include "koneksi.php";


/* =========================================================
   CEK KODE BUKU
========================================================= */

$kode_buku = isset($_GET['kode_buku'])
    ? (int) $_GET['kode_buku']
    : 0;

if ($kode_buku <= 0) {

    echo "<script>
            alert('Kode buku tidak ditemukan.');
            window.location='index.php#katalog';
          </script>";

    exit;
}


/* =========================================================
   AMBIL DATA BUKU
========================================================= */

$query = mysqli_query(
    $koneksi,
    "SELECT
        kode_buku,
        judul,
        penulis,
        penerbit,
        isbn,
        th_terbit,
        jml_buku,
        deskripsi,
        kategori,
        gambar
     FROM tbbuku
     WHERE kode_buku = '$kode_buku'
     LIMIT 1"
);

if (!$query) {

    die(
        "Gagal mengambil data buku: " .
        htmlspecialchars(
            mysqli_error($koneksi),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

if (mysqli_num_rows($query) === 0) {

    echo "<script>
            alert('Data buku tidak ditemukan.');
            window.location='index.php#katalog';
          </script>";

    exit;
}

$buku = mysqli_fetch_assoc($query);


/* =========================================================
   DATA BUKU
========================================================= */

$judul = htmlspecialchars(
    $buku['judul'] ?? '-',
    ENT_QUOTES,
    'UTF-8'
);

$penulis = htmlspecialchars(
    $buku['penulis'] ?? '-',
    ENT_QUOTES,
    'UTF-8'
);

$penerbit = htmlspecialchars(
    $buku['penerbit'] ?? '-',
    ENT_QUOTES,
    'UTF-8'
);

$isbn = htmlspecialchars(
    $buku['isbn'] ?? '-',
    ENT_QUOTES,
    'UTF-8'
);

$tahun = htmlspecialchars(
    $buku['th_terbit'] ?? '-',
    ENT_QUOTES,
    'UTF-8'
);

/* =========================================================
   ULASAN & RATING (tabel tbulasan, migrasi_v4.sql)
   Hanya anggota yang pernah MENGEMBALIKAN buku ini yang boleh
   memberi ulasan; satu anggota satu ulasan (bisa diperbarui).
========================================================= */
$ulasan_id_ang = 0;
$ulasan_milik  = null;
$boleh_ulas    = false;

if (isset($_SESSION['username']) && strtolower($_SESSION['status'] ?? '') === 'user') {
    $st = mysqli_prepare($koneksi, "SELECT id_ang FROM tbuser WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $_SESSION['username']);
    mysqli_stmt_execute($st);
    $ulasan_id_ang = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($st))['id_ang'] ?? 0);
    mysqli_stmt_close($st);

    if ($ulasan_id_ang > 0) {
        $q = mysqli_query(
            $koneksi,
            "SELECT COUNT(*) AS n FROM tbpinjam
              WHERE id_ang = $ulasan_id_ang AND kode_buku = $kode_buku AND status = 'kembali'"
        );
        $boleh_ulas = $q && (int) mysqli_fetch_assoc($q)['n'] > 0;

        $q = @mysqli_query($koneksi, "SELECT * FROM tbulasan WHERE id_ang = $ulasan_id_ang AND kode_buku = $kode_buku");
        $ulasan_milik = $q ? mysqli_fetch_assoc($q) : null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_ulasan']) && $boleh_ulas) {

    $rating = (int) ($_POST['rating'] ?? 0);
    $teks   = mb_substr(trim((string) ($_POST['ulasan'] ?? '')), 0, 500);

    if ($rating >= 1 && $rating <= 5) {
        $st = mysqli_prepare(
            $koneksi,
            "INSERT INTO tbulasan (kode_buku, id_ang, rating, ulasan, tgl) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), ulasan = VALUES(ulasan), tgl = NOW()"
        );
        mysqli_stmt_bind_param($st, 'iiis', $kode_buku, $ulasan_id_ang, $rating, $teks);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        catat_log('ulasan', "Anggota #$ulasan_id_ang memberi $rating bintang untuk buku #$kode_buku");
    }

    header("Location: detail_buku.php?kode_buku=$kode_buku#ulasan");
    exit;
}

$daftar_ulasan = [];
$q = @mysqli_query(
    $koneksi,
    "SELECT u.rating, u.ulasan, u.tgl, a.nama FROM tbulasan u JOIN tbanggota a ON a.id_ang = u.id_ang
      WHERE u.kode_buku = $kode_buku AND u.tampil = 1 ORDER BY u.tgl DESC LIMIT 20"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $daftar_ulasan[] = $r;
}
$ringkas_rating = rating_buku($koneksi, [$kode_buku])[$kode_buku] ?? null;


$deskripsi_asli = trim(
    (string) ($buku['deskripsi'] ?? '')
);

$deskripsi = htmlspecialchars(
    $deskripsi_asli !== ''
        ? $deskripsi_asli
        : 'Belum ada deskripsi untuk buku ini.',
    ENT_QUOTES,
    'UTF-8'
);

$stok = (int) ($buku['jml_buku'] ?? 0);

$sudah_login =
    isset($_SESSION['login']) &&
    $_SESSION['login'] === true;


/* =========================================================
   ROLE & TUJUAN PEMINJAMAN
========================================================= */

$status_login = strtolower(
    trim($_SESSION['status'] ?? '')
);

$is_admin =
    $status_login === 'admin' ||
    $status_login === 'administrator';

$dashboard_url = $is_admin
    ? 'beranda_admin.php'
    : 'beranda_user.php';


/* Sampul buku: lihat sampul_buku() di inc/aturan.php */


$cover = sampul_buku($buku);

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
        <?= $judul ?> | Perpustakaan Zaky
    </title>

    <meta
        name="description"
        content="Detail buku <?= $judul ?> di Perpustakaan Zaky."
    >

    <link
        rel="stylesheet"
        href="plugins/fontawesome-free/css/all.min.css"
    >

    <link rel="stylesheet" href="/perpuszaky/assets/theme.css?v=5.0">
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            

            background: var(--pz-navy-50);
            color: var(--pz-navy-950);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 999;
            height: 76px;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--pz-navy-100);
        }

        .nav-container {
            width: min(1180px,92%);
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--pz-navy-900);
            font-size: 20px;
            font-weight: 800;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            color: white;
            background: linear-gradient(135deg,var(--pz-navy-800),var(--pz-navy-600));
            box-shadow: 0 8px 22px rgba(29, 58, 99, .22);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .nav-menu a {
            color: #68758d;
            font-size: 14px;
            font-weight: 600;
            transition: .25s;
        }

        .nav-menu a:hover {
            color: var(--pz-navy-600);
        }

        .nav-button {
            padding: 11px 18px;
            border-radius: 11px;
            color: white !important;
            background: linear-gradient(135deg,var(--pz-navy-700),var(--pz-navy-600));
            box-shadow: 0 7px 18px rgba(40, 76, 125, .22);
        }

        .page {
            width: min(1120px,92%);
            margin: auto;
            padding: 55px 0 80px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            color: var(--pz-navy-600);
            font-size: 13px;
            font-weight: 700;
        }

        .detail-card {
            display: grid;
            grid-template-columns: 330px 1fr;
            gap: 50px;
            padding: 42px;
            background: white;
            border: 1px solid var(--pz-navy-100);
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(15, 31, 56, .08);
        }

        .cover-box {
            width: 100%;
            height: 450px;
            overflow: hidden;
            border-radius: 17px;
            background: linear-gradient(135deg,var(--pz-navy-900),var(--pz-navy-600));
            box-shadow: 0 18px 35px rgba(22, 41, 74, .16);
        }

        .cover-box img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .cover-fallback {
            width: 100%;
            height: 100%;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
        }

        .cover-fallback i {
            margin-bottom: 18px;
            font-size: 55px;
        }

        .cover-fallback span {
            font-size: 17px;
            font-weight: 800;
        }

        .eyebrow {
            margin-bottom: 10px;
            color: var(--pz-navy-600);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            margin-bottom: 10px;
            color: var(--pz-navy-950);
            font-size: clamp(30px,4vw,46px);
            line-height: 1.15;
            letter-spacing: -1.2px;
        }

        .author {
            margin-bottom: 25px;
            color: #72809a;
            font-size: 15px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
            padding: 8px 13px;
            border-radius: 9px;
            color: var(--pz-success);
            background: var(--pz-success-100);
            font-size: 12px;
            font-weight: 800;
        }

        .status.empty {
            color: var(--pz-danger);
            background: var(--pz-danger-100);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 13px;
            margin-bottom: 28px;
        }

        .info-item {
            padding: 14px 15px;
            border: 1px solid var(--pz-navy-100);
            border-radius: 12px;
            background: #fafcff;
        }

        .info-label {
            display: block;
            margin-bottom: 3px;
            color: #8995a9;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .info-value {
            color: var(--pz-navy-800);
            font-size: 13px;
            font-weight: 700;
            word-break: break-word;
        }

        .description {
            padding-top: 24px;
            border-top: 1px solid #edf0f5;
        }

        .description h2 {
            margin-bottom: 8px;
            color: var(--pz-navy-900);
            font-size: 18px;
        }

        .description p {
            color: #68758d;
            font-size: 14px;
            line-height: 1.8;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 11px;
            margin-top: 28px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 19px;
            border-radius: 11px;
            font-size: 13px;
            font-weight: 800;
            transition: .25s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            color: white;
            background: linear-gradient(135deg,var(--pz-navy-700),var(--pz-navy-600));
            box-shadow: 0 9px 22px rgba(40, 76, 125, .20);
        }

        .btn-secondary {
            color: var(--pz-navy-700);
            background: var(--pz-navy-50);
            border: 1px solid var(--pz-navy-100);
        }

        .note {
            margin-top: 12px;
            color: #8a95a8;
            font-size: 11px;
        }

        footer {
            padding: 35px 0;
            background: var(--pz-navy-950);
            color: var(--pz-navy-300);
        }

        .footer-container {
            width: min(1180px,92%);
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .footer-brand {
            color: white;
            font-size: 15px;
            font-weight: 800;
        }

        .footer-text {
            color: #8fa1bd;
            font-size: 12px;
        }

        @media (max-width: 800px) {

            .detail-card {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 25px;
            }

            .cover-box {
                max-width: 330px;
                height: 430px;
                margin: auto;
            }
        }

        @media (max-width: 600px) {

            .nav-menu a:not(.nav-button) {
                display: none;
            }

            .page {
                padding-top: 35px;
            }

            .detail-card {
                padding: 20px;
                border-radius: 18px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .cover-box {
                height: 380px;
            }

            .footer-container {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    
        .ulasan-card { margin-top: 24px; display: block !important; }
        .ulasan-judul { font-size: 1.3rem; font-weight: 600; margin-bottom: 8px; }
        .ulasan-form { margin: 16px 0 20px; padding: 16px; background: var(--pz-navy-50); border-radius: 8px; }
        .ulasan-item { border-top: 1px solid var(--pz-line); padding: 12px 0; }
        /* Halaman ini tidak memuat Bootstrap: gaya minimal untuk form ulasan */
        .ulasan-card .form-control { display: block; width: 100%; padding: 10px 12px; border: 1px solid var(--pz-line); border-radius: 6px; font: inherit; }
        .ulasan-card .btn { display: inline-block; padding: 9px 18px; border-radius: 6px; border: 0; cursor: pointer; font: inherit; font-weight: 600; }
        .ulasan-card .d-block { display: block; } .ulasan-card .mb-0 { margin-bottom: 0; } .ulasan-card .mb-1 { margin-bottom: 4px; }
        .ulasan-card .mb-2 { margin-bottom: 8px; } .ulasan-card .mt-1 { margin-top: 4px; } .ulasan-card .ml-1 { margin-left: 4px; }
        .ulasan-card label { font-weight: 600; }
    </style>

</head>

<body>

<header class="navbar">

    <div class="nav-container">

        <a href="index.php" class="brand">

            <span class="brand-icon">
                <i class="fas fa-book-open"></i>
            </span>

            <span>PERPUS ZAKY</span>

        </a>

        <nav class="nav-menu">

            <a href="index.php#katalog">
                Katalog
            </a>

            <?php if ($sudah_login): ?>

                <a
                    href="<?= htmlspecialchars($dashboard_url, ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-button"
                >
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>

            <?php else: ?>

                <a
                    href="login.php"
                    class="nav-button"
                >
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>


<main class="page">

    <a
        href="index.php#katalog"
        class="back-link"
    >
        <i class="fas fa-arrow-left"></i>
        Kembali ke Katalog
    </a>


    <section class="detail-card">

        <div class="cover-box">

            <?php if ($cover !== ''): ?>

                <img
                    src="<?= htmlspecialchars(
                        $cover,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    alt="Cover <?= $judul ?>"
                >

            <?php else: ?>

                <div class="cover-fallback">

                    <i class="fas fa-book-open"></i>

                    <span>
                        <?= $judul ?>
                    </span>

                </div>

            <?php endif; ?>

        </div>


        <div>

            <div class="eyebrow">
                Detail Koleksi Buku
            </div>

            <h1>
                <?= $judul ?>
            </h1>

            <div class="author">
                oleh <?= $penulis ?>
            </div>


            <?php if ($stok > 0): ?>

                <div class="status">

                    <i class="fas fa-check-circle"></i>

                    Tersedia · <?= $stok ?> buku

                </div>

            <?php else: ?>

                <div class="status empty">

                    <i class="fas fa-times-circle"></i>

                    Sedang tidak tersedia

                </div>

            <?php endif; ?>


            <div class="info-grid">

                <?php if (!empty($buku['kategori'])): ?>
                <div class="info-item">
                    <span class="info-label">Kategori</span>
                    <span class="info-value"><?= htmlspecialchars($buku['kategori']) ?></span>
                </div>
                <?php endif; ?>

                <div class="info-item">

                    <span class="info-label">
                        Penerbit
                    </span>

                    <span class="info-value">
                        <?= $penerbit ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Tahun Terbit
                    </span>

                    <span class="info-value">
                        <?= $tahun ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        ISBN
                    </span>

                    <span class="info-value">
                        <?= $isbn ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Stok
                    </span>

                    <span class="info-value">
                        <?= $stok ?> buku
                    </span>

                </div>

            </div>


            <div class="description">

                <h2>
                    Deskripsi Buku
                </h2>

                <p>
                    <?= nl2br($deskripsi) ?>
                </p>

            </div>


            <div class="actions">

                <?php if ($stok > 0): ?>

                    <?php if ($sudah_login && !$is_admin): ?>

                        <a
                            href="pinjam_user.php?kode_buku=<?= (int) $buku['kode_buku'] ?>"
                            class="btn btn-primary"
                        >
                            <i class="fas fa-book-reader"></i>
                            Ajukan Peminjaman
                        </a>

                    <?php elseif ($sudah_login && $is_admin): ?>

                        <a
                            href="pinjam.php"
                            class="btn btn-primary"
                        >
                            <i class="fas fa-book-reader"></i>
                            Kelola Peminjaman
                        </a>

                    <?php else: ?>

                        <a
                            href="login.php"
                            class="btn btn-primary"
                        >
                            <i class="fas fa-sign-in-alt"></i>
                            Masuk untuk Meminjam
                        </a>

                    <?php endif; ?>

                <?php else: ?>

                    <span class="btn btn-secondary">
                        <i class="fas fa-ban"></i>
                        Buku sedang tidak tersedia
                    </span>

                <?php endif; ?>


                <a
                    href="index.php#katalog"
                    class="btn btn-secondary"
                >
                    <i class="fas fa-books"></i>
                    Lihat Buku Lain
                </a>

            </div>


            <?php if (!$sudah_login && $stok > 0): ?>

                <div class="note">
                    Silakan masuk ke akun untuk mengajukan peminjaman buku.
                </div>

            <?php endif; ?>

        </div>

    </section>

    <section class="detail-card ulasan-card" id="ulasan">
        <h2 class="ulasan-judul">Ulasan pembaca</h2>

        <?php if ($ringkas_rating): ?>
            <p class="ulasan-ringkas"><?= bintang($ringkas_rating['rata']) ?>
                <strong><?= $ringkas_rating['rata'] ?></strong> dari 5 &middot; <?= $ringkas_rating['jumlah'] ?> ulasan</p>
        <?php else: ?>
            <p class="ulasan-ringkas text-muted">Belum ada ulasan untuk buku ini.</p>
        <?php endif; ?>

        <?php if ($boleh_ulas): ?>
            <form method="post" class="ulasan-form">
                <label class="d-block mb-1"><?= $ulasan_milik ? 'Perbarui ulasanmu' : 'Beri ulasan' ?></label>
                <div class="pz-rating-input mb-2">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="r<?= $i ?>" name="rating" value="<?= $i ?>" required
                            <?= (int) ($ulasan_milik['rating'] ?? 0) === $i ? 'checked' : '' ?>>
                        <label for="r<?= $i ?>" title="<?= $i ?> bintang"><i class="fas fa-star"></i></label>
                    <?php endfor; ?>
                </div>
                <textarea name="ulasan" class="form-control mb-2" rows="3" maxlength="500"
                    placeholder="Ceritakan kesanmu tentang buku ini (opsional)"><?= htmlspecialchars($ulasan_milik['ulasan'] ?? '') ?></textarea>
                <button type="submit" name="kirim_ulasan" value="1" class="btn btn-primary">Kirim ulasan</button>
            </form>
        <?php elseif (isset($_SESSION['username']) && ($_SESSION['status'] ?? '') === 'user'): ?>
            <p class="text-muted">Ulasan bisa diberikan setelah kamu meminjam dan mengembalikan buku ini.</p>
        <?php endif; ?>

        <?php foreach ($daftar_ulasan as $u): ?>
            <div class="ulasan-item">
                <div><?= bintang($u['rating']) ?> <strong class="ml-1"><?= htmlspecialchars($u['nama']) ?></strong>
                    <small class="text-muted ml-1"><?= date('d-m-Y', strtotime($u['tgl'])) ?></small></div>
                <?php if ($u['ulasan'] !== null && $u['ulasan'] !== ''): ?>
                    <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($u['ulasan'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

</main>


<footer>

    <div class="footer-container">

        <div class="footer-brand">

            <i class="fas fa-book-open"></i>

            PERPUS ZAKY

        </div>

        <div class="footer-text">

            Sistem Informasi Perpustakaan Zaky
            &nbsp;•&nbsp;
            <?= date('Y') ?>

        </div>

    </div>

</footer>

</body>
</html>
