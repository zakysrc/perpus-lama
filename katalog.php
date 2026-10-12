<?php

include "koneksi.php";

/* =========================================================
   KATALOG BUKU - PERPUSTAKAAN ZAKY
   Tidak mengubah database
========================================================= */

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';

$buku = [];

if ($cari !== '') {

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT kode_buku, judul, penulis, penerbit, kategori, isbn, th_terbit, jml_buku, gambar
         FROM tbbuku
         WHERE judul LIKE ?
            OR penulis LIKE ?
            OR penerbit LIKE ?
            OR isbn LIKE ?
         ORDER BY kode_buku DESC"
    );

    $keyword = "%" . $cari . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

} else {

    $result = mysqli_query(
        $koneksi,
        "SELECT kode_buku, judul, penulis, penerbit, kategori, isbn, th_terbit, jml_buku, gambar
         FROM tbbuku
         ORDER BY kode_buku DESC"
    );
}

$kategori_pilih = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($kategori_pilih !== '' && ($row['kategori'] ?? '') !== $kategori_pilih) {
            continue;
        }
        $buku[] = $row;
    }
}

$semua_kategori = daftar_kategori($koneksi);

/* Sampul buku: lihat sampul_buku() di inc/aturan.php */


function warna_cover($judul)
{
    $warna = [
        "#284c7d",
        "#1d3a63",
        "#2e7d5b",
        "#b23b3b",
        "#a8822e",
        "#1d3a63"
    ];

    $angka = abs(crc32($judul));

    return $warna[$angka % count($warna)];
}

$total_buku = count($buku);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Katalog Buku | Perpustakaan Zaky</title>

    <link rel="stylesheet"
          href="plugins/fontawesome-free/css/all.min.css">

    <link rel="stylesheet" href="/perpuszaky/assets/theme.css?v=5.0">
    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            
            background: var(--pz-navy-50);
            color: var(--pz-navy-950);
        }

        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            background: linear-gradient(
                135deg,
                var(--pz-navy-950),
                var(--pz-navy-700)
            );
            border-bottom: 3px solid var(--pz-gold-500);

            color: white;
            padding: 55px 7% 60px;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: "\f518";

            font-family: "Font Awesome 5 Free";
            font-weight: 900;

            position: absolute;
            right: 9%;
            top: 55px;

            font-size: 145px;
            color: rgba(255,255,255,0.12);
        }

        .hero-content {
            max-width: 900px;
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: 42px;
            font-weight: 700;
        }

        .hero p {
            margin: 0 0 25px;
            font-size: 18px;
            line-height: 1.7;
            color: rgba(255,255,255,0.9);
        }

        /* =====================================================
           SEARCH
        ===================================================== */

        .search-box {
            max-width: 820px;
            display: flex;
            background: white;
            border-radius: 9px;
            padding: 5px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            padding: 14px 16px;
            font-size: 15px;
            color: #333;
        }

        .search-box button {
            border: none;
            background: var(--pz-navy-600);
            color: white;
            border-radius: 7px;
            padding: 0 25px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-box button:hover {
            background: var(--pz-navy-600);
        }

        /* =====================================================
           NAVBAR
        ===================================================== */

        .top-nav {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 13px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            color: var(--pz-navy-700);
            font-size: 20px;
            font-weight: 700;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            text-decoration: none;
            color: #475569;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
        }

        .nav-links a:hover {
            background: var(--pz-navy-50);
            color: var(--pz-navy-600);
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .container {
            width: 86%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 35px 0 60px;
        }

        .catalog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .catalog-title {
            font-size: 21px;
            font-weight: 700;
        }

        .catalog-title i {
            color: var(--pz-navy-600);
            margin-right: 7px;
        }

        .total {
            color: #64748b;
            font-size: 14px;
        }

        /* =====================================================
           GRID
        ===================================================== */

        .book-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }

        /* =====================================================
           CARD
        ===================================================== */

        .book-card {
            background: white;
            border: 1px solid var(--pz-navy-100);
            border-radius: 11px;
            overflow: hidden;
            transition: 0.25s ease;
            box-shadow: 0 3px 10px rgba(10, 22, 40, 0.04);
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(10, 22, 40, 0.12);
        }

        /* =====================================================
           COVER
        ===================================================== */

        .book-cover {
            height: 245px;
            background: var(--pz-navy-100);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }

        .book-cover img {
            max-height: 220px;
            max-width: 150px;
            width: auto;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.18);
        }

        .fake-cover {
            width: 145px;
            height: 210px;
            border-radius: 5px;
            padding: 20px 13px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            box-shadow: 0 7px 18px rgba(0,0,0,0.2);
        }

        .fake-cover .book-icon {
            font-size: 32px;
            opacity: 0.9;
        }

        .fake-cover .fake-title {
            font-size: 17px;
            font-weight: 700;
            line-height: 1.35;
        }

        .fake-cover small {
            font-size: 11px;
            opacity: 0.9;
        }

        /* =====================================================
           CARD BODY
        ===================================================== */

        .book-body {
            padding: 17px;
        }

        .book-title {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 9px;

            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .book-author {
            color: #475569;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .book-author i {
            color: #64748b;
            width: 17px;
        }

        .book-publisher {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            min-height: 38px;
        }

        .book-info {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stock {
            font-size: 13px;
            font-weight: 700;
        }

        .stock.available {
            color: var(--pz-success);
        }

        .stock.empty {
            color: var(--pz-danger);
        }

        .year {
            font-size: 12px;
            color: #64748b;
        }

        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {
            background: white;
            border: 1px solid var(--pz-navy-100);
            border-radius: 10px;
            padding: 60px 20px;
            text-align: center;
        }

        .empty i {
            font-size: 55px;
            color: #94a3b8;
            margin-bottom: 15px;
        }

        .empty h3 {
            margin: 0 0 8px;
        }

        .empty p {
            margin: 0;
            color: #64748b;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: var(--pz-navy-950);
            color: #cbd5e1;
            text-align: center;
            padding: 22px;
            font-size: 13px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .book-grid {
                grid-template-columns: repeat(3, 1fr);
            }

        }

        @media (max-width: 800px) {

            .book-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero h1 {
                font-size: 34px;
            }

            .hero::after {
                display: none;
            }

        }

        @media (max-width: 550px) {

            .book-grid {
                grid-template-columns: 1fr;
            }

            .top-nav {
                flex-direction: column;
                gap: 10px;
            }

            .search-box {
                flex-direction: column;
                padding: 6px;
            }

            .search-box button {
                height: 45px;
            }

            .container {
                width: 92%;
            }

        }

    
        .kategori-bar { margin: 0 0 22px; }
        .kategori-chip { text-decoration: none !important; display: inline-block; padding: 5px 13px; margin: 0 6px 8px 0; border-radius: 999px; border: 1px solid var(--pz-line); background: #fff; color: var(--pz-ink); font-size: 14px; }
        .kategori-chip:hover { border-color: var(--pz-gold-500); text-decoration: none; color: var(--pz-navy-800); }
        .kategori-chip.aktif { background: var(--pz-navy-800); border-color: var(--pz-navy-800); color: #fff; }
        .kategori-chip small { opacity: .7; }
    </style>

</head>

<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<div class="top-nav">

    <a href="index.php" class="brand">
        <i class="fas fa-book-open mr-1"></i>
        Perpustakaan Zaky
    </a>

    <div class="nav-links">

        <a href="index.php">
            <i class="fas fa-home"></i>
            Beranda
        </a>

        <a href="login.php">
            <i class="fas fa-sign-in-alt"></i>
            Login
        </a>

    </div>

</div>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="hero-content">

        <h1>
            <i class="fas fa-book-open"></i>
            Katalog Buku
        </h1>

        <p>
            Temukan koleksi buku Perpustakaan Zaky
            untuk menambah ilmu dan wawasan Anda.
        </p>


        <form method="GET" class="search-box">

            <input
                type="text"
                name="cari"
                value="<?php echo htmlspecialchars($cari); ?>"
                placeholder="Cari judul buku, penulis, atau penerbit..."
                autocomplete="off"
            >

            <button type="submit">
                <i class="fas fa-search mr-1"></i>
                Cari
            </button>

        </form>

    </div>

</section>


<!-- =========================================================
     CONTENT
========================================================= -->

<div class="container">

    <div class="catalog-header">

        <div class="catalog-title">

            <i class="fas fa-books"></i>

            <?php if ($cari !== ''): ?>

                Hasil pencarian:
                <strong>
                    "<?php echo htmlspecialchars($cari); ?>"
                </strong>

            <?php else: ?>

                Koleksi Buku

            <?php endif; ?>

        </div>


        <div class="total">

            <?php echo $total_buku; ?> buku

        </div>

    </div>


    <?php if ($semua_kategori): ?>
        <div class="kategori-bar">
            <a class="kategori-chip <?php echo $kategori_pilih === '' ? 'aktif' : ''; ?>"
               href="katalog.php<?php echo $cari !== '' ? '?cari=' . urlencode($cari) : ''; ?>">Semua</a>
            <?php foreach ($semua_kategori as $k => $n): ?>
                <a class="kategori-chip <?php echo $kategori_pilih === $k ? 'aktif' : ''; ?>"
                   href="katalog.php?<?php echo htmlspecialchars(http_build_query(array_filter(['cari' => $cari, 'kategori' => $k]))); ?>">
                    <?php echo htmlspecialchars($k); ?> <small><?php echo $n; ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_buku > 0): ?>


        <div class="book-grid">


            <?php foreach ($buku as $data): ?>

                <?php

                $judul = $data['judul'];
                $cover = sampul_buku($data);

                $warna = warna_cover($judul);

                ?>


                <div class="book-card">


                    <!-- COVER -->

                    <div class="book-cover">


                        <?php if (!empty($cover)): ?>

                            <img
                                src="<?php echo htmlspecialchars($cover); ?>"
                                alt="Cover <?php echo htmlspecialchars($judul); ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >

                        <?php endif; ?>


                        <div
                            class="fake-cover"
                            style="
                                background:
                                <?php echo $warna; ?>;
                                <?php echo !empty($cover)
                                    ? 'display:none;'
                                    : ''; ?>
                            "
                        >

                            <div class="book-icon">

                                <i class="fas fa-book"></i>

                            </div>

                            <div class="fake-title">

                                <?php
                                echo htmlspecialchars($judul);
                                ?>

                            </div>

                            <small>
                                PERPUSTAKAAN ZAKY
                            </small>

                        </div>


                    </div>


                    <!-- BODY -->

                    <div class="book-body">


                        <div class="book-title">

                            <?php
                            echo htmlspecialchars($judul);
                            ?>

                        </div>


                        <div class="book-author">

                            <i class="fas fa-user-edit"></i>

                            <?php
                            echo htmlspecialchars(
                                $data['penulis'] ?: 'Penulis tidak tersedia'
                            );
                            ?>

                        </div>


                        <div class="book-publisher">

                            <i class="fas fa-building"></i>

                            <?php
                            echo htmlspecialchars(
                                $data['penerbit'] ?: 'Penerbit tidak tersedia'
                            );
                            ?>

                        </div>


                        <div class="book-info">


                            <?php if ((int)$data['jml_buku'] > 0): ?>

                                <div class="stock available">

                                    <i class="fas fa-check-circle"></i>

                                    Stok:
                                    <?php
                                    echo (int)$data['jml_buku'];
                                    ?>

                                </div>

                            <?php else: ?>

                                <div class="stock empty">

                                    <i class="fas fa-times-circle"></i>

                                    Stok Habis

                                </div>

                            <?php endif; ?>


                            <div class="year">

                                <i class="far fa-calendar-alt"></i>

                                <?php
                                echo htmlspecialchars(
                                    $data['th_terbit'] ?: '-'
                                );
                                ?>

                            </div>


                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty">

            <i class="fas fa-book-open"></i>

            <h3>
                Buku Tidak Ditemukan
            </h3>

            <p>
                <?php if ($cari !== ''): ?>

                    Tidak ada buku yang cocok dengan
                    pencarian
                    "<strong><?php echo htmlspecialchars($cari); ?></strong>".

                <?php else: ?>

                    Belum ada data buku di perpustakaan.

                <?php endif; ?>

            </p>

        </div>


    <?php endif; ?>


</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    Sistem Informasi Perpustakaan Zaky
    &nbsp;|&nbsp;
    Developed by Zaky

</footer>


</body>

</html>