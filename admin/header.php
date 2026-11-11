<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LPK Bhakti Kencana Cianjur</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- FontAwesome CDN untuk Ikon (Mata & Putar) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <!-- Panggil file sidebar -->
    <?php require_once 'sidebar.php'; ?>

    <!-- SCRIPT GLOBAL UNTUK KONTROL SIDEBAR & DROPDOWN -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            // Mencari elemen pembungkus konten utama (biasanya berkelas md:ml-64)
            const mainContent = document.querySelector('div.min-h-screen');

            if (window.innerWidth >= 768) {
                // Di layar Desktop: Geser sidebar masuk/keluar & atur margin konten utama
                sidebar.classList.toggle('-translate-x-full');
                
                if (mainContent) {
                    mainContent.classList.toggle('md:ml-64');
                    mainContent.classList.toggle('md:ml-0');
                }
            } else {
                // Di layar Mobile: Toggle sidebar & tampilkan latar gelap
                sidebar.classList.toggle('-translate-x-full');
                if (overlay) {
                    overlay.classList.toggle('hidden');
                }
            }
        }

        function toggleUserDropdown() {
            const submenu = document.getElementById('userSubmenu');
            const arrow = document.getElementById('dropdownArrow');
            
            if (submenu) {
                submenu.classList.toggle('hidden');
            }
            if (arrow) {
                arrow.classList.toggle('rotate-180');
            }
        }
    </script>