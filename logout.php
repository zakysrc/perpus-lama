<?php
require_once __DIR__ . '/inc/security.php';

if (isset($_SESSION['username'])) {
    include_once __DIR__ . '/koneksi.php';
    catat_log('logout', 'Keluar dari aplikasi');
}

// Hapus seluruh data session
$_SESSION = array();
session_unset();

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

// Setelah logout selalu kembali ke halaman depan
header("Location: index.php");
exit;
?>
