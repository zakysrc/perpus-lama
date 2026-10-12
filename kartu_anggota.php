<?php
/* =========================================================
   KARTU ANGGOTA (siap cetak)
   - Anggota: melihat kartunya sendiri.
   - Admin : kartu_anggota.php?id=ID_ANGGOTA
   Barcode Code 39 dibuat langsung sebagai SVG (tanpa library
   dan tanpa internet), bisa dibaca scanner barcode biasa.
========================================================= */

require_once __DIR__ . '/inc/security.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

include_once "koneksi.php";

$stmt = mysqli_prepare($koneksi, "SELECT id_ang, status FROM tbuser WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);
mysqli_stmt_execute($stmt);
$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$is_admin_kartu = $akun && strtolower($akun['status']) === 'admin';

/* Anggota hanya boleh melihat kartunya sendiri */
$id_ang = $is_admin_kartu ? (int) ($_GET['id'] ?? 0) : (int) ($akun['id_ang'] ?? 0);

$anggota = null;
if ($id_ang > 0) {
    $q = mysqli_query($koneksi, "SELECT * FROM tbanggota WHERE id_ang = $id_ang LIMIT 1");
    $anggota = $q ? mysqli_fetch_assoc($q) : null;
}


/* ---------------- BARCODE CODE 39 (SVG) ---------------- */
function barcode39_svg($teks, $tinggi = 46)
{
    $pola = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'G' => 'nnnnnwwnw',
        'T' => 'nnnnwnwwn', '-' => 'nwnnnnwnw', '*' => 'nwnnwnwnn',
    ];

    $teks  = '*' . strtoupper($teks) . '*';
    $sempit = 2;
    $lebar  = 5;
    $x      = 0;
    $batang = '';

    foreach (str_split($teks) as $c) {
        if (!isset($pola[$c])) {
            continue;
        }
        foreach (str_split($pola[$c]) as $i => $w) {
            $l = $w === 'w' ? $lebar : $sempit;
            if ($i % 2 === 0) {
                $batang .= '<rect x="' . $x . '" y="0" width="' . $l . '" height="' . $tinggi . '"/>';
            }
            $x += $l;
        }
        $x += $sempit; /* jeda antar karakter */
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $x . ' ' . $tinggi
        . '" preserveAspectRatio="none" class="kartu-barcode" role="img" aria-label="Barcode ' . htmlspecialchars($teks) . '">'
        . '<g fill="#0a1628">' . $batang . '</g></svg>';
}

$page_title = "Kartu anggota";
include "inc/header.php";
?>

<style>
    .kartu-wrap { display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start; }

    /* Ukuran kartu kredit standar: 85,6 x 54 mm */
    .kartu {
        width: 85.6mm; height: 54mm;
        border-radius: 3.5mm;
        overflow: hidden;
        position: relative;
        background: var(--pz-navy-900);
        color: #fff;
        box-shadow: 0 10px 28px rgba(10, 22, 40, 0.25);
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        font-size: 9pt;
    }
    .kartu-atas {
        display: flex; align-items: center; gap: 8px;
        padding: 3.2mm 4mm 2.6mm;
        border-bottom: 0.5mm solid var(--pz-gold-500);
    }
    .kartu-atas i { color: var(--pz-gold-500); font-size: 12pt; }
    .kartu-atas strong { font-family: var(--pz-font-title); font-size: 10.5pt; letter-spacing: .01em; }
    .kartu-atas span { margin-left: auto; font-size: 7pt; color: var(--pz-navy-200); }

    .kartu-isi { display: flex; gap: 3.5mm; padding: 3mm 4mm 0; }
    .kartu-foto {
        width: 17mm; height: 21mm; flex: none; border-radius: 1.5mm;
        background: var(--pz-navy-700); object-fit: cover;
        display: flex; align-items: center; justify-content: center;
        font-family: var(--pz-font-title); font-size: 18pt; color: var(--pz-gold-300);
        border: 0.3mm solid rgba(201, 162, 74, 0.6);
    }
    .kartu-data { min-width: 0; line-height: 1.35; }
    .kartu-nama { font-family: var(--pz-font-title); font-size: 11pt; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kartu-id { color: var(--pz-gold-300); font-weight: 700; letter-spacing: .06em; margin-bottom: 1mm; }
    .kartu-data small { display: block; color: var(--pz-navy-200); font-size: 7pt; }

    .kartu-bawah {
        position: absolute; left: 0; right: 0; bottom: 0;
        background: #fff; padding: 1.6mm 4mm 1.2mm;
        display: flex; align-items: center; gap: 3mm;
    }
    .kartu-barcode { height: 8mm; width: 44mm; }
    .kartu-bawah small { color: var(--pz-muted); font-size: 6.5pt; line-height: 1.25; }

    .kartu-catatan { max-width: 360px; }

    @media print {
        @page { margin: 12mm; }
        body * { visibility: hidden; }
        .kartu, .kartu * { visibility: visible; }
        .kartu { position: absolute; left: 0; top: 0; box-shadow: none; }
    }
</style>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head no-print">
        <div>
            <h1>Kartu anggota</h1>
            <p>Tunjukkan kartu ini saat meminjam atau mengembalikan buku di meja sirkulasi.</p>
        </div>
        <?php if ($anggota) { ?>
            <button type="button" class="btn btn-gold" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak kartu</button>
        <?php } ?>
    </div>

    <?php if (!$anggota) { ?>

        <div class="alert alert-info">
            <?php if ($is_admin_kartu) { ?>
                Buka kartu dari halaman <a href="anggota.php">Data anggota</a> dengan tombol kartu di tiap baris.
            <?php } else { ?>
                Akun ini belum terhubung dengan data anggota. Hubungi petugas perpustakaan.
            <?php } ?>
        </div>

    <?php } else {

        $kode   = sprintf('AGT-%04d', $anggota['id_ang']);
        $habis  = !empty($anggota['masa_berlaku']) && $anggota['masa_berlaku'] < date('Y-m-d');
        $foto   = $anggota['foto'] ?? '';
        $ada_foto = $foto !== '' && is_file(__DIR__ . '/assets/img/' . basename($foto));
    ?>

        <div class="kartu-wrap">
            <div class="kartu">
                <div class="kartu-atas">
                    <i class="fas fa-book-open"></i>
                    <strong><?php echo e(nama_perpustakaan()); ?></strong>
                    <span>Kartu anggota</span>
                </div>

                <div class="kartu-isi">
                    <?php if ($ada_foto) { ?>
                        <img class="kartu-foto" src="assets/img/<?php echo e(basename($foto)); ?>" alt="Foto <?php echo e($anggota['nama']); ?>">
                    <?php } else { ?>
                        <div class="kartu-foto"><?php echo e(mb_strtoupper(mb_substr($anggota['nama'], 0, 1))); ?></div>
                    <?php } ?>

                    <div class="kartu-data">
                        <div class="kartu-nama"><?php echo e($anggota['nama']); ?></div>
                        <div class="kartu-id"><?php echo $kode; ?></div>
                        <small>Terdaftar <?php echo $anggota['tgl_entry'] ? date('d-m-Y', strtotime($anggota['tgl_entry'])) : '-'; ?></small>
                        <small>Berlaku s/d <?php echo $anggota['masa_berlaku'] ? date('d-m-Y', strtotime($anggota['masa_berlaku'])) : '-'; ?></small>
                    </div>
                </div>

                <div class="kartu-bawah">
                    <?php echo barcode39_svg($kode); ?>
                    <small>Kartu ini milik perpustakaan. Jika ditemukan, mohon dikembalikan.</small>
                </div>
            </div>

            <div class="kartu-catatan no-print">
                <?php if ($habis) { ?>
                    <div class="alert alert-danger mb-3">
                        Masa berlaku kartu sudah habis. Peminjaman baru akan ditolak sampai keanggotaan diperpanjang.
                    </div>
                <?php } ?>
                <p class="mb-2"><strong>Nomor anggota:</strong> <?php echo $kode; ?></p>
                <p class="text-muted mb-0">Saat dicetak, kartu berukuran 85,6 × 54 mm (sama dengan kartu ATM). Aktifkan "Background graphics" di dialog cetak supaya warna ikut tercetak.</p>
            </div>
        </div>

    <?php } ?>

</div>
</section>

<?php include "inc/footer.php"; ?>
