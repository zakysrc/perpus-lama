<?php
/* =========================================================
   PERPUS ZAKY - HOME / LANDING PAGE
   ---------------------------------------------------------
   Fokus:
   - Beranda bergaya perpustakaan
   - Fokus pada koleksi dan pencarian buku
   - Tanpa statistik dashboard pada halaman depan
   - Warna mengikuti tema Perpus Zaky: navy, putih, emas
========================================================= */

require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/inc/aturan.php';

include __DIR__ . '/koneksi.php';

/* =========================================================
   DATA KOLEKSI
========================================================= */
$query_buku = mysqli_query(
    $koneksi,
    "SELECT
        kode_buku,
        judul,
        penulis,
        penerbit,
        isbn,
        th_terbit,
        jml_buku,
        gambar
     FROM tbbuku
     ORDER BY kode_buku DESC
     LIMIT 8"
);

$koleksi_buku = [];

if ($query_buku) {
    while ($buku = mysqli_fetch_assoc($query_buku)) {
        $koleksi_buku[] = $buku;
    }
}

/* =========================================================
   STATUS LOGIN
========================================================= */
$sudah_login = isset($_SESSION['login']) && $_SESSION['login'] === true;
$status_login = strtolower(trim($_SESSION['status'] ?? ''));

if ($status_login === 'admin' || $status_login === 'administrator') {
    $dashboard_url = 'beranda_admin.php';
} else {
    $dashboard_url = 'beranda_user.php';
}

/* Helper output */
function home_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Perpus Zaky | Perpustakaan</title>
    <meta name="description" content="Perpus Zaky — temukan koleksi buku dan jelajahi perpustakaan dengan lebih mudah.">

    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/theme.css?v=7.0">
    <link rel="stylesheet" href="assets/home.css?v=20.0">
</head>

<body class="pz-home">

<header class="home-header">
    <div class="home-container home-nav">

        <a href="index.php" class="home-brand" aria-label="Perpus Zaky">
            <span class="home-brand-mark">
                <i class="fas fa-book-open"></i>
            </span>
            <span class="home-brand-name">Perpus Zaky</span>
        </a>

        <nav class="home-nav-links" aria-label="Navigasi utama">
            <a class="is-active" href="#beranda">Beranda</a>
            <a href="#tentang">Tentang</a>
            <a href="#koleksi">Koleksi</a>
            <a href="#faq">Pertanyaan</a>
        </nav>

        <div class="home-nav-actions">
            <?php if ($sudah_login): ?>
                <a href="<?php echo home_e($dashboard_url); ?>" class="home-login">
                    Dashboard
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="home-login">
                    Masuk
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>

        <button
            type="button"
            class="home-menu-toggle"
            aria-label="Buka menu"
            aria-expanded="false"
        >
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="home-mobile-menu">
        <a href="#beranda">Beranda</a>
        <a href="#tentang">Tentang</a>
        <a href="#koleksi">Koleksi</a>
        <a href="#faq">Pertanyaan</a>

        <?php if ($sudah_login): ?>
            <a href="<?php echo home_e($dashboard_url); ?>">Dashboard</a>
        <?php else: ?>
            <a href="login.php">Masuk</a>
        <?php endif; ?>
    </div>
</header>

<main>

    <!-- =====================================================
         HERO
    ====================================================== -->
    <section class="home-hero" id="beranda">
        <div class="home-container home-hero-grid">

            <div class="home-hero-copy">
                <p class="home-overline">Perpustakaan Zaky</p>

                <h1>
                    Temukan buku
                    <span>Perluas wawasan</span>
                </h1>

                <p class="home-hero-lead">
                    Satu tempat untuk menemukan koleksi, membaca informasi buku,
                    dan mengenal lebih dekat perpustakaan Zaky.
                </p>

                <form class="home-search" action="pencarian.php" method="GET">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input
                        type="search"
                        name="q"
                        placeholder="Cari judul buku, penulis, atau ISBN"
                        aria-label="Cari buku"
                    >
                    <button type="submit">Cari buku</button>
                </form>

                <div class="home-hero-actions">
                    <a href="#koleksi" class="home-btn home-btn-gold">
                        Jelajahi koleksi
                        <i class="fas fa-arrow-down"></i>
                    </a>

                    <?php if ($sudah_login): ?>
                        <a href="<?php echo home_e($dashboard_url); ?>" class="home-btn home-btn-outline">
                            Buka dashboard
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="home-btn home-btn-outline">
                            Masuk ke sistem
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>

    <!-- =====================================================
         TENTANG
    ====================================================== -->
    <section class="home-section home-about" id="tentang">
        <div class="home-container home-about-grid">

            <div class="home-section-intro">
                <p class="home-eyebrow">Tentang Perpus Zaky</p>
                <h2>Satu tempat untuk mengelola perpustakaan dengan lebih rapi</h2>
            </div>

            <div class="home-about-copy">
                <p>
                    Perpus Zaky dibuat untuk membantu aktivitas perpustakaan
                    tetap teratur, mudah dicari, dan nyaman digunakan.
                </p>

                <p>
                    Koleksi buku dapat ditemukan melalui pencarian dan katalog,
                    sementara informasi setiap buku tersedia dalam satu tempat.
                </p>

                <a href="#koleksi" class="home-inline-link">
                    Mulai dari koleksi buku
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- =====================================================
         KEUNGGULAN
    ====================================================== -->
    <section class="home-section home-reading" id="keunggulan">
        <div class="home-container">

            <div class="home-section-intro home-section-intro-wide">
                <p class="home-eyebrow">Pengalaman perpustakaan</p>
                <h2>Dibuat untuk membuat pekerjaan perpustakaan lebih mudah</h2>
                <p>
                    Dari pencarian buku sampai informasi peminjaman,
                    semuanya ditempatkan agar mudah ditemukan.
                </p>
            </div>

            <div class="home-reading-grid">
                <article class="reading-item">
                    <span class="reading-icon"><i class="fas fa-search"></i></span>
                    <h3>Temukan dengan cepat</h3>
                    <p>Cari buku berdasarkan judul, penulis, atau ISBN.</p>
                </article>

                <article class="reading-item">
                    <span class="reading-icon"><i class="fas fa-book-open"></i></span>
                    <h3>Kenali setiap koleksi</h3>
                    <p>Lihat sampul dan informasi buku sebelum membukanya.</p>
                </article>

                <article class="reading-item">
                    <span class="reading-icon"><i class="fas fa-book-reader"></i></span>
                    <h3>Gunakan dengan nyaman</h3>
                    <p>Informasi perpustakaan disajikan dengan alur yang sederhana.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- =====================================================
         KOLEKSI
    ====================================================== -->
    <section class="home-section home-books" id="koleksi">
        <div class="home-container">

            <div class="home-heading-row">
                <div class="home-section-intro">
                    <p class="home-eyebrow">Koleksi Buku</p>
                    <h2>Jelajahi koleksi pilihan Perpus Zaky</h2>
                    <p>
                        Temukan buku yang tersedia dan buka detailnya untuk
                        melihat informasi koleksi secara lengkap.
                    </p>
                </div>

                <a href="katalog.php" class="home-inline-link">
                    Lihat semua koleksi
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="home-book-grid">

                <?php if (!empty($koleksi_buku)): ?>

                    <?php foreach ($koleksi_buku as $buku): ?>
                        <?php
                        $judul_asli = $buku['judul'] ?? '';
                        $penulis_asli = $buku['penulis'] ?? 'Penulis tidak diketahui';
                        $tahun_asli = $buku['th_terbit'] ?? '-';
                        $stok = (int) ($buku['jml_buku'] ?? 0);
                        $cover = sampul_buku($buku);
                        ?>

                        <a
                            href="detail_buku.php?kode_buku=<?php echo (int) $buku['kode_buku']; ?>"
                            class="home-book-card"
                            aria-label="Lihat detail <?php echo home_e($judul_asli); ?>"
                        >
                            <div class="home-book-cover">
                                <?php if ($cover !== ''): ?>
                                    <img
                                        src="<?php echo home_e($cover); ?>"
                                        alt="Cover <?php echo home_e($judul_asli); ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="home-book-fallback">
                                        <i class="fas fa-book-open"></i>
                                        <span><?php echo home_e($judul_asli); ?></span>
                                    </div>
                                <?php endif; ?>

                                <span class="home-stock <?php echo $stok > 0 ? '' : 'empty'; ?>">
                                    <?php echo $stok > 0 ? 'Tersedia' : 'Habis'; ?>
                                </span>
                            </div>

                            <div class="home-book-body">
                                <h3><?php echo home_e($judul_asli); ?></h3>
                                <p><?php echo home_e($penulis_asli); ?></p>

                                <div class="home-book-meta">
                                    <span><?php echo home_e($tahun_asli); ?></span>
                                    <span><?php echo $stok > 0 ? 'Tersedia' : 'Tidak tersedia'; ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="home-empty">
                        <i class="fas fa-book-open"></i>
                        <strong>Belum ada koleksi buku</strong>
                        <span>Data buku belum tersedia di perpustakaan.</span>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- =====================================================
         FAQ
    ====================================================== -->
    <section class="home-section home-faq" id="faq">
        <div class="home-container home-faq-grid">

            <div class="home-section-intro">
                <p class="home-eyebrow">Pertanyaan Umum</p>
                <h2>Hal yang sering ingin diketahui</h2>
            </div>

            <div class="faq-list">

                <details class="faq-item" open>
                    <summary>
                        <span>Apa itu Perpus Zaky?</span>
                        <i class="fas fa-plus"></i>
                    </summary>
                    <p>
                        Perpus Zaky adalah sistem informasi perpustakaan
                        berbasis web untuk membantu pengelolaan koleksi
                        dan aktivitas perpustakaan.
                    </p>
                </details>

                <details class="faq-item">
                    <summary>
                        <span>Bagaimana cara mencari buku?</span>
                        <i class="fas fa-plus"></i>
                    </summary>
                    <p>
                        Gunakan kolom pencarian pada bagian utama halaman
                        atau buka katalog untuk melihat koleksi yang tersedia.
                    </p>
                </details>

                <details class="faq-item">
                    <summary>
                        <span>Bagaimana cara melakukan peminjaman?</span>
                        <i class="fas fa-plus"></i>
                    </summary>
                    <p>
                        Pengguna yang sudah memiliki akun dapat masuk ke sistem
                        dan menggunakan layanan sesuai hak aksesnya.
                    </p>
                </details>

                <details class="faq-item">
                    <summary>
                        <span>Apakah ketersediaan buku diperbarui?</span>
                        <i class="fas fa-plus"></i>
                    </summary>
                    <p>
                        Informasi ketersediaan pada halaman koleksi mengikuti
                        data buku yang tersimpan di sistem perpustakaan.
                    </p>
                </details>

            </div>
        </div>
    </section>

    <!-- =====================================================
         PENUTUP
    ====================================================== -->
    <section class="home-closing">
        <div class="home-container home-closing-inner">
            <p class="home-eyebrow">Perpustakaan Zaky</p>
            <h2>Selamat menemukan bacaan berikutnya</h2>
            <a href="#koleksi" class="home-btn home-btn-gold">
                Jelajahi koleksi
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

</main>

<footer class="home-footer">
    <div class="home-container home-footer-inner">

        <div class="home-footer-brand">
            <a href="index.php" class="home-brand home-brand-footer">
                <span class="home-brand-mark">
                    <i class="fas fa-book-open"></i>
                </span>
                <span class="home-brand-name">Perpus Zaky</span>
            </a>

            <p>
                Sistem informasi perpustakaan untuk menemukan dan mengelola koleksi buku.
            </p>
        </div>

        <nav class="home-footer-links" aria-label="Navigasi footer">
            <a href="#beranda">Beranda</a>
            <a href="#tentang">Tentang</a>
            <a href="#koleksi">Koleksi</a>
            <a href="#faq">Pertanyaan</a>
            <a href="katalog.php">Katalog</a>
        </nav>

        <div class="home-footer-account">
            <?php if ($sudah_login): ?>
                <a href="<?php echo home_e($dashboard_url); ?>">Dashboard</a>
            <?php else: ?>
                <a href="login.php">Masuk</a>
                <a href="register.php">Daftar</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="home-container home-footer-bottom">
        <span>&copy; <?php echo date('Y'); ?> Perpus Zaky</span>
        <span>Perpustakaan Zaky</span>
    </div>
</footer>

<script>
(function () {
    const toggle = document.querySelector('.home-menu-toggle');
    const menu = document.querySelector('.home-mobile-menu');

    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
        const opened = menu.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
    });

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            menu.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
})();
</script>

</body>
</html>
