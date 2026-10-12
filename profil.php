<?php
/* =========================================================
   PROFIL SAYA  (admin & anggota)
   ---------------------------------------------------------
   - Anggota bisa memperbarui telepon, alamat, dan email.
   - Semua akun bisa mengganti password (wajib memasukkan
     password lama). Admin bawaan "admin123" bisa diganti
     dari sini, tidak perlu lewat phpMyAdmin.
========================================================= */

require_once __DIR__ . '/inc/security.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

include_once "koneksi.php";

/* Akun yang sedang login */
$stmt = mysqli_prepare($koneksi, "SELECT id_user, id_ang, username, password, status FROM tbuser WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);
mysqli_stmt_execute($stmt);
$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$akun) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$id_ang  = (int) $akun['id_ang'];
$anggota = null;

if ($id_ang > 0) {
    $q = mysqli_query($koneksi, "SELECT * FROM tbanggota WHERE id_ang = $id_ang LIMIT 1");
    $anggota = $q ? mysqli_fetch_assoc($q) : null;
}

$error_profil = [];
$error_pass   = [];


/* ---------------- SIMPAN DATA KONTAK ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_profil']) && $anggota) {

    $telp   = trim((string) ($_POST['telp'] ?? ''));
    $alamat = trim((string) ($_POST['alamat'] ?? ''));
    $email  = trim((string) ($_POST['email'] ?? ''));

    if ($telp !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $telp)) {
        $error_profil[] = 'Nomor telepon hanya boleh angka, 8-15 digit.';
    }
    if (mb_strlen($alamat) > 70) {
        $error_profil[] = 'Alamat maksimal 70 karakter.';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 30)) {
        $error_profil[] = 'Email tidak valid (maksimal 30 karakter).';
    }

    if (!$error_profil) {
        $stmt = mysqli_prepare($koneksi, "UPDATE tbanggota SET telp = ?, alamat = ?, email = ? WHERE id_ang = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $telp, $alamat, $email, $id_ang);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        catat_log('ubah_profil', "Anggota #$id_ang memperbarui data kontak");
        flash_set('success', 'Data kontak diperbarui.');
        header("Location: profil.php");
        exit;
    }
}


/* ---------------- GANTI PASSWORD ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {

    $lama  = (string) ($_POST['password_lama'] ?? '');
    $baru  = (string) ($_POST['password_baru'] ?? '');
    $ulang = (string) ($_POST['password_ulang'] ?? '');

    if (!verifikasi_password($koneksi, $akun, $lama)) {
        $error_pass[] = 'Password lama salah.';
    }
    if (strlen($baru) < 8) {
        $error_pass[] = 'Password baru minimal 8 karakter.';
    }
    if ($baru !== $ulang) {
        $error_pass[] = 'Konfirmasi password baru tidak sama.';
    }
    if ($baru !== '' && hash_equals($lama, $baru)) {
        $error_pass[] = 'Password baru harus berbeda dari password lama.';
    }

    if (!$error_pass) {
        simpan_hash_password($koneksi, $akun['id_user'], $baru);

        /* ID session baru setelah password berubah */
        session_regenerate_id(true);

        catat_log('ganti_password', 'Akun ' . $akun['username'] . ' mengganti password');
        flash_set('success', 'Password berhasil diganti. Gunakan password baru saat login berikutnya.');
        header("Location: profil.php");
        exit;
    }
}

/* Peringatan untuk admin yang masih memakai password bawaan */
$masih_bawaan = password_sudah_hash($akun['password'])
    ? password_verify('admin123', $akun['password'])
    : $akun['password'] === 'admin123';

$page_title = "Profil saya";
include "inc/header.php";

function val($nama, $bawaan)
{
    return e(isset($_POST[$nama]) ? $_POST[$nama] : $bawaan);
}
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Profil saya</h1>
            <p>Data akun <?php echo e($akun['username']); ?> (<?php echo $akun['status'] === 'admin' ? 'administrator' : 'anggota'; ?>).</p>
        </div>
        <?php if ($anggota) { ?>
            <a href="kartu_anggota.php" class="btn btn-outline-primary"><i class="fas fa-id-card mr-1"></i> Lihat kartu anggota</a>
        <?php } ?>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if ($masih_bawaan) { ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Akun ini masih memakai password bawaan <strong>admin123</strong>. Ganti sekarang lewat form di bawah.
        </div>
    <?php } ?>

    <div class="row">

        <?php if ($anggota) { ?>
        <div class="col-lg-7 mb-3">
            <form method="post" class="card card-primary card-outline h-100">
                <div class="card-header"><h3 class="card-title">Data anggota</h3></div>
                <div class="card-body">

                    <?php if ($error_profil) { ?>
                        <div class="alert alert-danger"><?php echo e(implode(' ', $error_profil)); ?></div>
                    <?php } ?>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Nama</label>
                            <input type="text" class="form-control" value="<?php echo e($anggota['nama']); ?>" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Masa berlaku</label>
                            <input type="text" class="form-control" readonly
                                   value="<?php echo $anggota['masa_berlaku'] ? date('d-m-Y', strtotime($anggota['masa_berlaku'])) : '-'; ?>">
                        </div>
                    </div>
                    <small class="form-text text-muted mt-n2 mb-3">Nama dan masa berlaku hanya bisa diubah petugas perpustakaan.</small>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="telp">Telepon</label>
                            <input type="tel" class="form-control" id="telp" name="telp" maxlength="15" value="<?php echo val('telp', $anggota['telp']); ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="30" value="<?php echo val('email', $anggota['email']); ?>">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="alamat">Alamat</label>
                        <input type="text" class="form-control" id="alamat" name="alamat" maxlength="70" value="<?php echo val('alamat', $anggota['alamat']); ?>">
                    </div>
                </div>
                <div class="card-footer bg-white text-right">
                    <button type="submit" name="simpan_profil" value="1" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan data kontak</button>
                </div>
            </form>
        </div>
        <?php } ?>

        <div class="<?php echo $anggota ? 'col-lg-5' : 'col-lg-6'; ?> mb-3">
            <form method="post" class="card card-warning card-outline h-100" autocomplete="off">
                <div class="card-header"><h3 class="card-title">Ganti password</h3></div>
                <div class="card-body">

                    <?php if ($error_pass) { ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 pl-3"><?php foreach ($error_pass as $er) { ?><li><?php echo e($er); ?></li><?php } ?></ul>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label for="password_lama">Password lama</label>
                        <input type="password" class="form-control" id="password_lama" name="password_lama" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="password_baru">Password baru</label>
                        <input type="password" class="form-control" id="password_baru" name="password_baru" minlength="8" required autocomplete="new-password">
                        <small class="form-text text-muted">Minimal 8 karakter.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="password_ulang">Ulangi password baru</label>
                        <input type="password" class="form-control" id="password_ulang" name="password_ulang" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <div class="card-footer bg-white text-right">
                    <button type="submit" name="ganti_password" value="1" class="btn btn-gold"><i class="fas fa-key mr-1"></i> Ganti password</button>
                </div>
            </form>
        </div>

    </div>
</div>
</section>

<?php include "inc/footer.php"; ?>
