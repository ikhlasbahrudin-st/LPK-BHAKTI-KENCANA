<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. (Opsional) Fungsi pembatasan halaman berdasarkan role
// Contoh: Jika halaman ini hanya boleh diakses oleh Super Admin dan Koordinator:
/*
if (!in_array($_SESSION['admin_role'], ['super_admin', 'koordinator'])) {
    echo "<script>alert('Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.'); window.location='index.php';</script>";
    exit;
}
*/
?>