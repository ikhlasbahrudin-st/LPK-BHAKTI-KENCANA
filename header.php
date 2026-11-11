<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tambahkan kode ini agar browser tidak menyimpan cache halaman setelah logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['peserta_logged_in']) || $_SESSION['peserta_logged_in'] !== true) {
    // Opsional: Jika belum login, pastikan langsung dialihkan
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Peserta - LPK Bhakti Kencana</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 font-sans text-slate-800">
    <div class="flex h-screen overflow-hidden">