<?php
/* =========================================================
   KOLEKSI BUKU (anggota)
   Pencarian judul/penulis/penerbit/ISBN + filter kategori.
   Buku yang stoknya habis tetap tampil, supaya anggota tahu
   buku itu ada dan bisa kembali lagi nanti.
========================================================= */

require_once __DIR__ . '/inc/security.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

include_once "koneksi.php";

$cari     = trim((string) ($_GET['cari'] ?? ''));
$kategori = trim((string) ($_GET['kategori'] ?? ''));

$sql    = "SELECT kode_buku, judul, penulis, penerbit, kategori, isbn, th_terbit, jml_buku, gambar FROM tbbuku WHERE 1 = 1";
$tipe   = '';
$param  = [];

if ($cari !== '') {
    $sql .= " AND (judul LIKE ? OR penulis LIKE ? OR penerbit LIKE ? OR isbn LIKE ?)";
    $kata = '%' . $cari . '%';
    $tipe .= 'ssss';
    array_push($param, $kata, $kata, $kata, $kata);
}
if ($kategori !== '') {
    $sql .= " AND kategori = ?";
    $tipe .= 's';
    $param[] = $kategori;
}
$sql .= " ORDER BY (jml_buku > 0) DESC, judul ASC";

$stmt = mysqli_prepare($koneksi, $sql);
if ($param) {
    mysqli_stmt_bind_param($stmt, $tipe, ...$param);
}
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$buku = [];
while ($r = mysqli_fetch_assoc($hasil)) {
    $buku[] = $r;
}
mysqli_stmt_close($stmt);

$semua_kategori = daftar_kategori($koneksi);
$rating = rating_buku($koneksi);

function url_filter($kat, $cari)
{
    $q = [];
    if ($cari !== '') $q['cari'] = $cari;
    if ($kat !== '')  $q['kategori'] = $kat;
    return 'buku_user.php' . ($q ? '?' . http_build_query($q) : '');
}

$page_title = "Koleksi buku";
include "inc/header.php";
?>

<style>
    .pz-chip { display: inline-block; padding: 5px 12px; border-radius: 999px; border: 1px solid var(--pz-line); background: #fff; color: var(--pz-ink); margin: 0 6px 8px 0; font-size: 13.5px; }
    .pz-chip:hover { border-color: var(--pz-gold-500); color: var(--pz-navy-800); text-decoration: none; }
    .pz-chip.aktif { background: var(--pz-navy-800); border-color: var(--pz-navy-800); color: #fff; }
    .pz-chip small { color: inherit; opacity: .7; }
    .pz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 18px; }
    .pz-buku { background: #fff; border: 1px solid var(--pz-line); border-radius: var(--pz-radius); padding: 10px; display: flex; flex-direction: column; }
    .pz-buku .pz-cover-kosong { display: flex; flex-direction: column; justify-content: flex-end; padding: 12px; color: #fff; background: var(--pz-navy-800); border-bottom: 3px solid var(--pz-gold-500); font-family: var(--pz-font-title); font-size: 14px; line-height: 1.3; }
    .pz-buku h3 { font-size: 15px; margin: 10px 0 2px; line-height: 1.3; }
    .pz-buku .meta { color: var(--pz-muted); font-size: 13px; }
    .pz-buku .aksi { margin-top: auto; padding-top: 10px; display: flex; gap: 6px; }
    .pz-buku.habis { opacity: .75; }
</style>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Koleksi buku</h1>
            <p><?php echo count($buku); ?> judul<?php echo $kategori !== '' ? ' dalam kategori ' . e($kategori) : ''; ?><?php echo $cari !== '' ? ' untuk "' . e($cari) . '"' : ''; ?>.</p>
        </div>
        <form method="get" class="form-inline">
            <?php if ($kategori !== '') { ?><input type="hidden" name="kategori" value="<?php echo e($kategori); ?>"><?php } ?>
            <div class="input-group">
                <input type="search" name="cari" class="form-control" placeholder="Judul, penulis, atau ISBN" value="<?php echo e($cari); ?>" aria-label="Cari buku">
                <div class="input-group-append"><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button></div>
            </div>
        </form>
    </div>

    <?php if ($semua_kategori) { ?>
        <div class="mb-2">
            <a class="pz-chip <?php echo $kategori === '' ? 'aktif' : ''; ?>" href="<?php echo e(url_filter('', $cari)); ?>">Semua</a>
            <?php foreach ($semua_kategori as $k => $n) { ?>
                <a class="pz-chip <?php echo $kategori === $k ? 'aktif' : ''; ?>" href="<?php echo e(url_filter($k, $cari)); ?>"><?php echo e($k); ?> <small><?php echo $n; ?></small></a>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (!$buku) { ?>
        <div class="card card-body text-center text-muted py-5">
            Tidak ada buku yang cocok. <a href="buku_user.php">Tampilkan semua koleksi</a>
        </div>
    <?php } else { ?>
        <div class="pz-grid">
            <?php foreach ($buku as $b) { $sampul = sampul_buku($b); $ada = (int) $b['jml_buku'] > 0; ?>
                <div class="pz-buku <?php echo $ada ? '' : 'habis'; ?>">
                    <?php if ($sampul) { ?>
                        <img class="pz-cover" src="<?php echo e($sampul); ?>" alt="Sampul <?php echo e($b['judul']); ?>" loading="lazy">
                    <?php } else { ?>
                        <div class="pz-cover pz-cover-kosong"><?php echo e($b['judul']); ?></div>
                    <?php } ?>
                    <h3 class="pz-title"><?php echo e($b['judul']); ?></h3>
                    <div class="meta"><?php echo e($b['penulis']); ?><?php echo $b['th_terbit'] ? ', ' . (int) $b['th_terbit'] : ''; ?></div>
                    <?php if (isset($rating[(int) $b['kode_buku']])) { $rt = $rating[(int) $b['kode_buku']]; ?>
                        <div class="meta"><?php echo bintang($rt['rata']); ?> <small><?php echo $rt['rata']; ?> (<?php echo $rt['jumlah']; ?>)</small></div>
                    <?php } ?>
                    <div class="meta mt-1">
                        <?php if (!empty($b['kategori'])) { ?><span class="badge badge-warning"><?php echo e($b['kategori']); ?></span><?php } ?>
                        <?php echo $ada ? '<span class="badge badge-success">Tersedia ' . (int) $b['jml_buku'] . '</span>' : '<span class="badge badge-secondary">Sedang habis</span>'; ?>
                    </div>
                    <div class="aksi">
                        <a href="detail_buku.php?kode_buku=<?php echo (int) $b['kode_buku']; ?>" class="btn btn-sm btn-secondary flex-fill">Detail</a>
                        <?php if ($ada) { ?>
                            <a href="pinjam_user.php?kode_buku=<?php echo (int) $b['kode_buku']; ?>" class="btn btn-sm btn-primary flex-fill">Ajukan</a>
                        <?php } else { ?>
                            <form method="post" action="reservasi.php" class="flex-fill d-flex">
                                <input type="hidden" name="kode_buku" value="<?php echo (int) $b['kode_buku']; ?>">
                                <button type="submit" name="aksi" value="pesan" class="btn btn-sm btn-gold flex-fill" title="Masuk antrean, diberi tahu saat tersedia">Reservasi</button>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

</div>
</section>

<?php include "inc/footer.php"; ?>
