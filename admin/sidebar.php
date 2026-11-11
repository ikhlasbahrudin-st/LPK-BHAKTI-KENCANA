<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
?>

<!-- Overlay Gelap untuk Mobile saat Sidebar Terbuka -->
<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 z-40 hidden md:hidden transition-opacity"></div>

<!-- Sidebar Menu -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 border-r border-slate-800 flex flex-col justify-between shrink-0 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out shadow-2xl">
    <div>
        <!-- Header Sidebar -->
        <div class="h-20 flex items-center justify-between px-6 border-b border-slate-800 bg-slate-950/50">
            <!-- Bagian Kiri: Logo & Info Admin -->
            <div class="flex items-center space-x-3">
                <img src="../assets/img/logo.png" alt="Logo LPK" class="w-12 h-12 sm:w-14 sm:h-14 object-contain rounded-lg bg-white/5 p-1 border border-slate-800">
                <div class="flex flex-col">
                    <span class="font-bold text-white text-sm tracking-wide leading-tight">LPK Bhakti Kencana</span>
                    <span class="text-[10px] text-indigo-400 font-semibold uppercase tracking-wider bg-indigo-950/80 px-2 py-0.5 rounded border border-indigo-800/50 mt-1 w-fit">
                        Role: <?php echo htmlspecialchars(str_replace('_', ' ', $admin_role)); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Navigasi Menu Berdasarkan Hak Akses Role -->
        <nav class="p-4 space-y-1.5 overflow-y-auto max-h-[calc(100vh-160px)]">
            
            <!-- Dashboard Utama -->
            <?php if (in_array($admin_role, ['super_admin', 'koordinator', 'admin'])): ?>
                <a href="index.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition <?php echo ($current_page == 'index.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <span>📊</span><span>Dashboard</span>
                </a>
            <?php endif; ?>

            <!-- MENU LAYANAN & OPERASIONAL -->
            <?php 
            $allowed_operasional_pages = [];
            if ($admin_role === 'super_admin') {
                $allowed_operasional_pages = ['pekerja.php', 'pelanggan.php', 'booking.php'];
            } elseif ($admin_role === 'koordinator') {
                $allowed_operasional_pages = ['pekerja.php'];
            } elseif ($admin_role === 'admin') {
                $allowed_operasional_pages = ['pelanggan.php', 'booking.php'];
            }

            $is_operasional_page = in_array($current_page, $allowed_operasional_pages); 
            $can_see_layanan = in_array($admin_role, ['super_admin']) || ($admin_role === 'koordinator') || ($admin_role === 'admin');
            
            if ($can_see_layanan):
            ?>
            <div>
                <button onclick="toggleOperasionalDropdown()" class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition <?php echo $is_operasional_page ? 'bg-slate-800 text-indigo-400 font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-3"><span>💼</span><span>Layanan</span></div>
                    <svg id="operasionalArrow" class="w-4 h-4 transition-transform duration-200 <?php echo $is_operasional_page ? 'rotate-180 text-indigo-400' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="operasionalSubmenu" class="space-y-1 pl-4 pt-1 <?php echo $is_operasional_page ? '' : 'hidden'; ?>">
                    
                    <!-- Kelola Pekerja: Super Admin & Koordinator -->
                    <?php if (in_array($admin_role, ['super_admin', 'koordinator'])): ?>
                        <a href="pekerja.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition <?php echo ($current_page == 'pekerja.php') ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                            <span>👥</span><span>Kelola Pekerja</span>
                        </a>
                    <?php endif; ?>

                    <!-- Kelola Pelanggan: Super Admin & Admin -->
                    <?php if (in_array($admin_role, ['super_admin', 'admin'])): ?>
                        <a href="pelanggan.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition <?php echo ($current_page == 'pelanggan.php') ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                            <span>🤝</span><span>Kelola Pelanggan</span>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Kelola Booking: Super Admin & Admin -->
                    <?php if (in_array($admin_role, ['super_admin', 'admin'])): ?>
                        <a href="booking.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition <?php echo ($current_page == 'booking.php') ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                            <span>📅</span><span>Kelola Booking</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Menu Data Pendaftaran -->
            <?php if (in_array($admin_role, ['super_admin', 'koordinator'])): ?>
                <a href="pendaftaran.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition <?php echo ($current_page == 'pendaftaran.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <span>📋</span><span>Data Pendaftaran</span>
                </a>
            <?php endif; ?>

            <!-- Kelola Laporan Harian: Super Admin & Koordinator -->
            <?php if (in_array($admin_role, ['super_admin', 'koordinator'])): ?>
                <a href="laporan_harian.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition <?php echo ($current_page == 'laporan_harian.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <span>📈</span><span>Kelola Laporan Harian</span>
                </a>
            <?php endif; ?>

            <!-- KELOLA TESTIMONI: Hanya Super Admin & Koordinator -->
            <?php if (in_array($admin_role, ['super_admin', 'koordinator'])): ?>
                <a href="testimoni.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition <?php echo ($current_page == 'testimoni.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <span>💬</span><span>Kelola Testimoni</span>
                </a>
            <?php endif; ?>

            <!-- Kelola Laporan Keuangan: Super Admin & Admin -->
            <?php if (in_array($admin_role, ['super_admin', 'admin'])): ?>
                <a href="keuangan.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition <?php echo ($current_page == 'keuangan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <span>💰</span><span>Kelola Laporan Keuangan</span>
                </a>
            <?php endif; ?>

            <!-- Super Admin Area Eksklusif -->
            <?php if ($admin_role === 'super_admin'): ?>
                <div class="pt-4 pb-2">
                    <p class="px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Super Admin Area</p>
                </div>
                <a href="pendaftaran_info.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition <?php echo ($current_page == 'pendaftaran_info.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <span>ℹ️</span><span>Info Pendaftaran</span>
                </a>

                <!-- DROPDOWN: MANAJEMEN USER -->
                <?php $is_user_page = in_array($current_page, ['admin.php', 'user_lpk.php']); ?>
                <div>
                    <button onclick="toggleUserDropdown()" class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition <?php echo $is_user_page ? 'bg-slate-800 text-indigo-400 font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                        <div class="flex items-center space-x-3"><span>🔐</span><span>Manajemen User</span></div>
                        <svg id="dropdownArrow" class="w-4 h-4 transition-transform duration-200 <?php echo $is_user_page ? 'rotate-180 text-indigo-400' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="userSubmenu" class="space-y-1 pl-4 pt-1 <?php echo $is_user_page ? '' : 'hidden'; ?>">
                        <a href="admin.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition <?php echo ($current_page == 'admin.php') ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                            <span>🛡️</span><span>User Admin</span>
                        </a>
                        <a href="user_lpk.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition <?php echo ($current_page == 'user_lpk.php') ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                            <span>👥</span><span>User Pendaftar (LPK)</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Tombol Logout -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/30">
        <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center justify-center space-x-2 w-full bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 font-medium py-2.5 rounded-xl text-sm transition border border-rose-500/20">
            <span>🚪</span><span>Keluar (Logout)</span>
        </a>
    </div>
</aside>

<!-- Skrip JavaScript Pendukung -->
<script>
function toggleOperasionalDropdown() {
    const submenu = document.getElementById('operasionalSubmenu');
    const arrow = document.getElementById('operasionalArrow');
    if(submenu) {
        submenu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        arrow.classList.toggle('text-indigo-400');
    }
}

function toggleUserDropdown() {
    const submenu = document.getElementById('userSubmenu');
    const arrow = document.getElementById('dropdownArrow');
    if(submenu) {
        submenu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        arrow.classList.toggle('text-indigo-400');
    }
}
</script>