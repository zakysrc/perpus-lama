<?php
/* =========================================================
   PENGATURAN PERPUSTAKAAN (khusus admin)
   ---------------------------------------------------------
   Mengubah aturan bisnis di tabel tbpengaturan lewat form,
   tanpa perlu membuka phpMyAdmin. Trigger database membaca
   tabel yang sama, jadi perubahan langsung berlaku di semua
   halaman dan perhitungan denda.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

/* Definisi setiap pengaturan: label, batas nilai, keterangan */
$daftar = [
    'masa_pinjam_hari' => [
        'label' => 'Masa pinjam',
        'satuan' => 'hari',
        'min' => 1, 'max' => 60,
        'bantuan' => 'Jatuh tempo = tanggal pinjam + nilai ini. Juga dipakai sebagai lama tambahan saat perpanjangan.',
    ],
    'denda_per_hari' => [
        'label' => 'Denda keterlambatan',
        'satuan' => 'rupiah/hari',
        'min' => 0, 'max' => 100000,
        'bantuan' => 'Dihitung otomatis oleh trigger saat buku dikembalikan.',
    ],
    'maks_jml_per_pinjam' => [
        'label' => 'Maksimal buku per transaksi',
        'satuan' => 'buku',
        'min' => 1, 'max' => 20,
        'bantuan' => 'Jumlah eksemplar yang boleh dipinjam dalam satu kali peminjaman.',
    ],
    'maks_buku_aktif' => [
        'label' => 'Maksimal buku aktif per anggota',
        'satuan' => 'buku',
        'min' => 1, 'max' => 50,
        'bantuan' => 'Total buku yang sedang dipinjam + yang masih diajukan.',
    ],
    'hari_pengingat' => [
        'label' => 'Pengingat jatuh tempo',
        'satuan' => 'hari sebelumnya',
        'min' => 0, 'max' => 14,
        'bantuan' => 'Anggota mendapat pengingat di lonceng notifikasi sejak H-sekian.',
    ],
    'maks_reservasi' => [
        'label' => 'Maksimal reservasi aktif',
        'satuan' => 'buku',
        'min' => 0, 'max' => 20,
        'bantuan' => 'Isi 0 untuk menonaktifkan reservasi.',
    ],
    'maks_perpanjang' => [
        'label' => 'Batas perpanjangan',
        'satuan' => 'kali',
        'min' => 0, 'max' => 5,
        'bantuan' => 'Isi 0 jika perpustakaan tidak mengizinkan perpanjangan.',
    ],
];

$error = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $baru = [];

    foreach ($daftar as $kunci => $def) {

        $nilai = trim((string) ($_POST[$kunci] ?? ''));

        if ($nilai === '' || !ctype_digit($nilai)) {
            $error[] = $def['label'] . ' harus berupa angka bulat.';
            continue;
        }

        $nilai = (int) $nilai;

        if ($nilai < $def['min'] || $nilai > $def['max']) {
            $error[] = $def['label'] . " harus di antara {$def['min']} dan {$def['max']}.";
            continue;
        }

        $baru[$kunci] = (string) $nilai;
    }

    $baru['cek_masa_berlaku'] = isset($_POST['cek_masa_berlaku']) ? '1' : '0';

    $nama = trim((string) ($_POST['nama_perpustakaan'] ?? ''));
    if ($nama === '' || mb_strlen($nama) > 100) {
        $error[] = 'Nama perpustakaan wajib diisi (maksimal 100 karakter).';
    } else {
        $baru['nama_perpustakaan'] = $nama;
    }

    if (!$error) {

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO tbpengaturan (nama, nilai) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)"
        );

        $ringkas = [];

        foreach ($baru as $k => $v) {
            mysqli_stmt_bind_param($stmt, 'ss', $k, $v);
            mysqli_stmt_execute($stmt);
            $ringkas[] = "$k=$v";
        }

        mysqli_stmt_close($stmt);

        catat_log('pengaturan', implode(', ', $ringkas));
        flash_set('success', 'Pengaturan disimpan dan langsung berlaku.');

        header("Location: pengaturan.php");
        exit;
    }
}

/* Nilai saat ini (atau isian terakhir kalau ada error) */
function nilai_form($kunci, $bawaan)
{
    if (isset($_POST[$kunci])) {
        return (string) $_POST[$kunci];
    }
    return (string) setting($kunci, $bawaan);
}

$bawaan = [
    'masa_pinjam_hari' => 7, 'denda_per_hari' => 5000,
    'maks_jml_per_pinjam' => 3, 'maks_buku_aktif' => 5, 'maks_perpanjang' => 1,
    'hari_pengingat' => 2, 'maks_reservasi' => 3,
];

$page_title = "Pengaturan";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Pengaturan perpustakaan</h1>
            <p>Aturan peminjaman yang dipakai di semua halaman, form, dan perhitungan denda.</p>
        </div>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if ($error) { ?>
        <div class="alert alert-danger">
            <strong>Pengaturan belum disimpan.</strong>
            <ul class="mb-0 mt-1 pl-3">
                <?php foreach ($error as $er) { ?><li><?php echo e($er); ?></li><?php } ?>
            </ul>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-lg-8">
            <form method="post" class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Aturan peminjaman</h3>
                </div>
                <div class="card-body">

                    <?php foreach ($daftar as $kunci => $def) { ?>
                        <div class="form-group row">
                            <label for="<?php echo $kunci; ?>" class="col-md-5 col-form-label"><?php echo e($def['label']); ?></label>
                            <div class="col-md-7">
                                <div class="input-group">
                                    <input type="number" class="form-control" id="<?php echo $kunci; ?>" name="<?php echo $kunci; ?>"
                                           min="<?php echo $def['min']; ?>" max="<?php echo $def['max']; ?>" required
                                           value="<?php echo e(nilai_form($kunci, $bawaan[$kunci])); ?>">
                                    <div class="input-group-append"><span class="input-group-text"><?php echo e($def['satuan']); ?></span></div>
                                </div>
                                <small class="form-text text-muted"><?php echo e($def['bantuan']); ?></small>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="form-group row">
                        <div class="col-md-5 col-form-label font-weight-bold">Masa berlaku anggota</div>
                        <div class="col-md-7 pt-2">
                            <div class="custom-control custom-switch">
                                <?php $cek = isset($_POST['nama_perpustakaan']) ? isset($_POST['cek_masa_berlaku']) : cek_masa_berlaku_aktif(); ?>
                                <input type="checkbox" class="custom-control-input" id="cek_masa_berlaku" name="cek_masa_berlaku" <?php echo $cek ? 'checked' : ''; ?>>
                                <label class="custom-control-label font-weight-normal" for="cek_masa_berlaku">Tolak peminjaman jika masa berlaku sudah habis</label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group row mb-0">
                        <label for="nama_perpustakaan" class="col-md-5 col-form-label">Nama perpustakaan</label>
                        <div class="col-md-7">
                            <input type="text" class="form-control" id="nama_perpustakaan" name="nama_perpustakaan" maxlength="100" required
                                   value="<?php echo e(nilai_form('nama_perpustakaan', 'Perpustakaan Zaky')); ?>">
                            <small class="form-text text-muted">Tampil di kartu anggota.</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan pengaturan</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Contoh perhitungan</h3></div>
                <div class="card-body">
                    <p class="mb-2">Buku dipinjam hari ini jatuh tempo pada
                        <strong><?php echo date('d-m-Y', strtotime('+' . masa_pinjam_hari() . ' days')); ?></strong>.</p>
                    <p class="mb-2">Jika terlambat 3 hari, denda yang dikenakan
                        <strong><?php echo format_rupiah(3 * denda_per_hari()); ?></strong>.</p>
                    <p class="mb-0 text-muted">Perubahan hanya memengaruhi peminjaman baru dan pengembalian berikutnya. Jatuh tempo yang sudah tercatat tidak berubah.</p>
                </div>
            </div>
        </div>
    </div>

</div>
</section>

<?php include "inc/footer.php"; ?>
