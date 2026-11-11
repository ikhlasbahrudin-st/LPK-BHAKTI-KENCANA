<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Overlay Gelap (Muncul saat sidebar terbuka di HP) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden transition-opacity"></div>

<!-- Sidebar (Responsive Drawer untuk Mobile & Desktop) -->
<aside id="appSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out flex flex-col justify-between shadow-xl md:shadow-none">
    <div>
        <!-- Logo Brand & Tombol Tutup (X) khusus Mobile -->
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="bg-emerald-600 text-white font-bold p-2.5 rounded-xl text-sm">LPK</div>
                <div>
                    <h1 class="font-extrabold text-sm text-slate-900">Bhakti Kencana</h1>
                    <p class="text-[10px] text-slate-500">Panel Peserta LPK</p>
                </div>
            </div>
            <!-- Tombol Tutup (X) untuk HP -->
            <button id="closeSidebar" class="md:hidden text-slate-500 hover:text-slate-900 font-bold p-2 rounded-xl bg-slate-100 hover:bg-slate-200 transition text-sm">
                ✕
            </button>
        </div>

        <!-- Menu Navigasi -->
        <nav class="p-4 space-y-1.5 text-xs font-semibold">
            <a href="dashboard-peserta.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo ($current_page == 'dashboard-peserta.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'; ?>">
                <span>📊</span> Dashboard Utama
            </a>
            <a href="laporan-harian.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo ($current_page == 'laporan-harian.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'; ?>">
                <span>📝</span> Upload Laporan Harian
            </a>
            <a href="ubah-data-diri.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo ($current_page == 'ubah-data-diri.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'; ?>">
                <span>✏️</span> Ubah Data Diri
            </a>
            <a href="ubah-password.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo ($current_page == 'ubah-password.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'; ?>">
                <span>🔒</span> Ubah Password
            </a>
        </nav>
    </div>

    <!-- Tombol Logout di Bawah Sidebar -->
    <div class="p-4 border-t border-slate-100">
        <a href="logout-peserta.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 transition">
            <span>🚪</span> Keluar Sistem
        </a>
    </div>
</aside>