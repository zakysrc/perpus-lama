<?php
/* =========================================================
   AKUN LOGIN (khusus admin)
   ---------------------------------------------------------
   - Melihat semua akun: username, pemilik, peran, kapan
     terakhir login, dan berapa kali gagal login hari ini.
   - Reset password anggota yang lupa (password sementara),
     tanpa perlu phpMyAdmin.
   Riwayat login gagal beserta alasannya (username tidak
   terdaftar / password salah) tercatat di Log aktivitas.
========================================================= */

include "inc/admin_only.php";
include_once "koneksi.php";

$hasil_reset = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'reset') {

    $id_user = (int) ($_POST['id_user'] ?? 0);
    $q = mysqli_query($koneksi, "SELECT id_user, username, status FROM tbuser WHERE id_user = $id_user");
    $akun = $q ? mysqli_fetch_assoc($q) : null;

    if (!$akun) {
        flash_set('danger', 'Akun tidak ditemukan.');
    } elseif ($akun['username'] === ($_SESSION['username'] ?? '')) {
        flash_set('warning', 'Password akun sendiri diganti lewat menu Profil saya.');
    } else {
        /* Password sementara: mudah dibacakan, tanpa huruf yang mirip (0/O, 1/l) */
        $huruf = 'abcdefghjkmnpqrstuvwxyz23456789';
        $sementara = '';
        for ($i = 0; $i < 8; $i++) {
            $sementara .= $huruf[random_int(0, strlen($huruf) - 1)];
        }

        simpan_hash_password($koneksi, $akun['id_user'], $sementara);

        /* Buka kunci login yang mungkin sedang aktif */
        $st = mysqli_prepare($koneksi, "DELETE FROM tblogin_gagal WHERE username = ?");
        mysqli_stmt_bind_param($st, 's', $akun['username']);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        catat_log('reset_password', 'Admin mereset password akun ' . $akun['username']);

        /* Disimpan di session lalu redirect, supaya menekan F5 tidak
           mereset ulang; ditampilkan sekali lalu dihapus */
        $_SESSION['hasil_reset'] = ['username' => $akun['username'], 'password' => $sementara];
    }

    header("Location: akun.php");
    exit;
}

if (!empty($_SESSION['hasil_reset'])) {
    $hasil_reset = $_SESSION['hasil_reset'];
    unset($_SESSION['hasil_reset']);
}

/* Daftar akun + login terakhir dari log */
$akun_list = [];
$q = mysqli_query(
    $koneksi,
    "SELECT u.id_user, u.username, u.status, u.id_ang, a.nama, a.masa_berlaku,
            (SELECT MAX(l.waktu) FROM tblog l WHERE l.aksi = 'login' AND l.username = u.username) AS login_terakhir,
            (SELECT COUNT(*) FROM tblogin_gagal g WHERE g.username = u.username
                AND g.waktu >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS gagal
       FROM tbuser u
       LEFT JOIN tbanggota a ON a.id_ang = u.id_ang
      ORDER BY u.status = 'admin' DESC, u.username"
);
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $akun_list[] = $r;
}

/* Anggota yang belum punya akun */
$tanpa_akun = (int) mysqli_fetch_row(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) FROM tbanggota a WHERE NOT EXISTS (SELECT 1 FROM tbuser u WHERE u.id_ang = a.id_ang)"
))[0];

$page_title = "Akun login";
include "inc/header.php";
?>

<section class="content">
<div class="container-fluid">

    <div class="pz-page-head">
        <div>
            <h1>Akun login</h1>
            <p>Semua akun yang bisa masuk ke aplikasi. Anggota yang lupa password bisa direset di sini.</p>
        </div>
        <a href="log_aktivitas.php?aksi=login_gagal&amp;hari=7" class="btn btn-outline-primary"><i class="fas fa-user-lock mr-1"></i> Riwayat login gagal</a>
    </div>

    <?php echo flash_tampil(); ?>

    <?php if ($hasil_reset) { ?>
        <div class="alert alert-success">
            <strong>Password akun <?php echo e($hasil_reset['username']); ?> sudah direset.</strong><br>
            Password sementara: <code class="h5 mb-0 ml-1" id="pwSementara"><?php echo e($hasil_reset['password']); ?></code>
            <br><small>Berikan ke anggota secara langsung, lalu minta ia menggantinya di menu Profil saya. Password ini hanya ditampilkan sekali.</small>
        </div>
    <?php } ?>

    <?php if ($tanpa_akun > 0) { ?>
        <div class="alert alert-info">
            <?php echo $tanpa_akun; ?> anggota belum punya akun. Anggota membuat akun sendiri di halaman <strong>Daftar</strong> memakai email yang terdaftar di data anggota.
        </div>
    <?php } ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr><th>Username</th><th>Pemilik</th><th>Peran</th><th>Login terakhir</th><th>Gagal 24 jam</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($akun_list as $a) { $saya = $a['username'] === ($_SESSION['username'] ?? ''); ?>
                        <tr>
                            <td><strong><?php echo e($a['username']); ?></strong><?php echo $saya ? ' <span class="badge badge-warning">Anda</span>' : ''; ?></td>
                            <td><?php echo $a['nama'] ? e($a['nama']) . '<br><small class="text-muted">' . sprintf('AGT-%04d', $a['id_ang']) . '</small>' : '<span class="text-muted">-</span>'; ?></td>
                            <td><?php echo $a['status'] === 'admin' ? '<span class="badge badge-primary">Admin</span>' : '<span class="badge badge-secondary">Anggota</span>'; ?></td>
                            <td data-order="<?php echo e($a['login_terakhir'] ?? ''); ?>"><?php echo $a['login_terakhir'] ? date('d-m-Y H:i', strtotime($a['login_terakhir'])) : '<span class="text-muted">Belum pernah</span>'; ?></td>
                            <td><?php echo (int) $a['gagal'] ? '<span class="pz-status telat">' . (int) $a['gagal'] . ' kali</span>' : '<span class="text-muted">-</span>'; ?></td>
                            <td>
                                <?php if (!$saya) { ?>
                                    <form method="post" onsubmit="return confirm('Reset password akun <?php echo e($a['username']); ?>? Password lama tidak bisa dipakai lagi.');">
                                        <input type="hidden" name="id_user" value="<?php echo (int) $a['id_user']; ?>">
                                        <button type="submit" name="aksi" value="reset" class="btn btn-sm btn-outline-primary"><i class="fas fa-key mr-1"></i> Reset password</button>
                                    </form>
                                <?php } else { ?>
                                    <a href="profil.php" class="btn btn-sm btn-outline-primary">Profil saya</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</section>

<?php include "inc/footer.php"; ?>
