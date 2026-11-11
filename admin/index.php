<?php
// 1. Mulai sesi (Wajib ada di baris paling atas sebelum output apa pun)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CEK SESI: Jika admin belum login atau sudah logout, tendang kembali ke halaman login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php"); 
    exit;
}

// Ambil role admin yang sedang aktif (default ke 'admin' jika belum diset)
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';

// 3. Panggil file konfigurasi koneksi database terlebih dahulu
require_once '../config.php';

// Pastikan tabel pelanggan memiliki kolom koordinat (jaga-jaga jika belum ada)
$check_col = $conn->query("SHOW COLUMNS FROM pelanggan LIKE 'koordinat'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE pelanggan ADD COLUMN koordinat VARCHAR(100) DEFAULT NULL AFTER alamat");
}

// 4. Panggil header (yang di dalamnya biasanya sudah termasuk pemanggilan sidebar.php)
require_once 'header.php';

// Ambil statistik total dari masing-masing tabel
$total_pekerja = $conn->query("SELECT COUNT(*) as total FROM pekerja")->fetch_assoc()['total'];
$total_pendaftaran = $conn->query("SELECT COUNT(*) as total FROM pendaftaran")->fetch_assoc()['total'];
$total_info = $conn->query("SELECT COUNT(*) as total FROM pendaftaran_info")->fetch_assoc()['total'];

// Tambahan: Query Total Pelanggan & Ambil Data untuk Peta Persebaran
$total_pelanggan = $conn->query("SELECT COUNT(*) as total FROM pelanggan")->fetch_assoc()['total'];
$result_peta = $conn->query("SELECT * FROM pelanggan WHERE koordinat IS NOT NULL AND koordinat != ''");
$pelanggan_markers = [];
while ($m_row = $result_peta->fetch_assoc()) {
    $pelanggan_markers[] = $m_row;
}

// Ambil data terbaru untuk ditampilkan di Dashboard
$data_pekerja = $conn->query("SELECT * FROM pekerja ORDER BY id DESC LIMIT 5");
$data_pendaftaran = $conn->query("SELECT * FROM pendaftaran ORDER BY tanggal_daftar DESC LIMIT 5");
$data_info = $conn->query("SELECT * FROM pendaftaran_info LIMIT 5");

// 5. AMBIL TOTAL PENDAPATAN PER BULAN DARI TABEL transaksi_keuangan
$bulan_array = [];
$total_array = [];

$cek_tabel = $conn->query("SHOW TABLES LIKE 'transaksi_keuangan'");
if ($cek_tabel && $cek_tabel->num_rows > 0) {
    // Mengelompokkan dan menjumlahkan total pendapatan berdasarkan bulan dari tanggal_transaksi
    $query_keuangan = $conn->query("
        SELECT 
            DATE_FORMAT(tanggal_transaksi, '%b %Y') as bulan, 
            SUM(jumlah) as total_pendapatan 
        FROM transaksi_keuangan 
        WHERE jenis_transaksi = 'Uang Masuk' 
        GROUP BY YEAR(tanggal_transaksi), MONTH(tanggal_transaksi) 
        ORDER BY tanggal_transaksi ASC
    ");
    
    if ($query_keuangan && $query_keuangan->num_rows > 0) {
        while ($row = $query_keuangan->fetch_assoc()) {
            $bulan_array[] = $row['bulan'];
            $total_array[] = (float)$row['total_pendapatan'];
        }
    }
}

// Jika data kosong, beri nilai default agar grafik tidak error
if (empty($bulan_array)) {
    $bulan_array = ['Belum Ada Data'];
    $total_array = [0];
}

// Query untuk menghitung jumlah pendaftaran per status
$query_statistik = "SELECT status, COUNT(*) as total FROM pendaftaran GROUP BY status";
$result_statistik = $conn->query($query_statistik);

$data_status = [
    'Diperiksa' => 0,
    'Diterima' => 0,
    'Ditolak' => 0
];

while ($row_stat = $result_statistik->fetch_assoc()) {
    if (array_key_exists($row_stat['status'], $data_status)) {
        $data_status[$row_stat['status']] = (int)$row_stat['total'];
    }
}


$data_pekerja = $conn->query("
    SELECT 
        p.*, 
        b.kode_booking, 
        b.layanan, 
        b.tanggal_mulai AS booking_mulai, 
        b.tanggal_selesai AS booking_selesai, 
        b.status_booking, 
        b.catatan AS catatan_booking,
        pl.nama_pelanggan,
        pl.telepon AS telepon_pelanggan,
        pl.alamat AS alamat_pelanggan,
        pl.koordinat
    FROM pekerja p
    LEFT JOIN booking b ON p.id = b.pekerja_id AND b.status_booking = 'Sedang Berjalan'
    LEFT JOIN pelanggan pl ON b.pelanggan_id = pl.id
    ORDER BY p.id DESC
");
?>

<!-- Panggil CSS & JS Leaflet untuk Peta Interaktif -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Pembungkus Konten Utama -->
<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">

    <div>
        <!-- Topbar / Header -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-lg hover:bg-slate-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div>
                    <span class="font-bold text-sm text-slate-800 block">Admin Dashboard LPK Bhakti Kencana</span>
                    <span class="text-[11px] text-indigo-600 font-semibold uppercase tracking-wider">
                        Role: <?php echo htmlspecialchars(str_replace('_', ' ', $admin_role)); ?>
                    </span>
                </div>
            </div>

            <div>
                <a href="../index.php" target="_blank" class="inline-flex items-center space-x-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold px-3.5 py-2 rounded-xl transition shadow-sm">
                    <span>🌐</span>
                    <span class="hidden sm:inline">Lihat Halaman Publik</span>
                    <span class="sm:hidden">Web</span>
                </a>
            </div>
        </header>

        <!-- Konten Utama Dashboard -->
        <main class="p-6 md:p-8 w-full max-w-7xl mx-auto overflow-x-hidden">

            <!-- Banner Sambutan Berdasarkan Role -->
            <div class="bg-indigo-900 text-white p-6 rounded-2xl mb-8 shadow-md flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-lg font-bold">Selamat Datang, <?php echo htmlspecialchars($_SESSION['admin_nama'] ?? $_SESSION['admin_username']); ?>! 👋</h2>
                    <p class="text-xs text-indigo-200 mt-1">
                        Panel kendali operasional dan laporan keuangan LPK Bhakti Kencana Cianjur.
                    </p>
                </div>
                <div class="bg-indigo-800 border border-indigo-700 px-3 py-1.5 rounded-xl text-xs font-semibold uppercase tracking-wider text-emerald-400">
                    ● Role: <?php echo htmlspecialchars($admin_role); ?>
                </div>
            </div>

            <!-- Statistik Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card Pekerja -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pekerja / Mitra</p>
                        <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?php echo $total_pekerja; ?></h3>
                    </div>
                    <div class="bg-indigo-50 text-indigo-600 p-3.5 rounded-xl text-xl shadow-inner">👥</div>
                </div>

                <!-- Card Pendaftaran -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pendaftar</p>
                        <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?php echo $total_pendaftaran; ?></h3>
                    </div>
                    <div class="bg-emerald-50 text-emerald-600 p-3.5 rounded-xl text-xl shadow-inner">📋</div>
                </div>

                <!-- Card Total Pelanggan / Klien -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pelanggan</p>
                        <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?php echo $total_pelanggan; ?></h3>
                    </div>
                    <div class="bg-sky-50 text-sky-600 p-3.5 rounded-xl text-xl shadow-inner">🏢</div>
                </div>

                <!-- Card Info Pendaftaran (Hanya Tampil untuk Super Admin) -->
                <?php if ($admin_role === 'super_admin'): ?>
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Info / Gelombang Aktif</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?php echo $total_info; ?></h3>
                        </div>
                        <div class="bg-amber-50 text-amber-600 p-3.5 rounded-xl text-xl shadow-inner">ℹ️</div>
                    </div>
                <?php endif; ?>
            </div>


<!-- SECTION CONTAINER GRAFIK & LOG AKTIVITAS DENGAN TOMBOL NAVIGASI SLIDE -->
<div class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-8 p-6 relative">
    <!-- Header & Tombol Navigasi Panah -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3 border-b border-slate-100 pb-4">
        <div>
            <h2 id="grafikTitle" class="text-base font-bold text-slate-900">Grafik Total Pendapatan Per Bulan</h2>
            <p id="grafikDesc" class="text-xs text-slate-500">Akumulasi jumlah uang masuk (pemasukan) berdasarkan bulan transaksi.</p>
        </div>
        <div class="flex items-center gap-3">
            <div id="grafikBadge" class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-lg">
                Tahun 2026
            </div>
            <!-- Tombol Navigasi Panah -->
            <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl">
                <button onclick="slideGrafik('prev')" class="w-8 h-8 flex items-center justify-center bg-white hover:bg-indigo-600 hover:text-white text-slate-700 rounded-lg shadow-sm transition font-bold" title="Sebelumnya">
                    ‹
                </button>
                <span id="slideIndicator" class="text-xs font-bold text-slate-600 px-2">1 / 3</span>
                <button onclick="slideGrafik('next')" class="w-8 h-8 flex items-center justify-center bg-white hover:bg-indigo-600 hover:text-white text-slate-700 rounded-lg shadow-sm transition font-bold" title="Berikutnya">
                    ›
                </button>
            </div>
        </div>
    </div>

    <!-- Wrapper Slider Full Layar (3 Slide) -->
    <div id="grafikSliderContainer" class="flex overflow-x-hidden snap-x snap-mandatory scroll-smooth w-full">
        
        <!-- SLIDE 1: GRAFIK KEUANGAN -->
        <div class="w-full min-w-full flex-shrink-0 snap-start">
            <div class="relative w-full h-80 sm:h-96">
                <canvas id="grafikKeuangan"></canvas>
            </div>
        </div>

        <!-- SLIDE 2: GRAFIK STATUS PENDAFTARAN -->
        <div class="w-full min-w-full flex-shrink-0 snap-start">
            <div class="relative w-full h-80 sm:h-96 flex justify-center items-center">
                <canvas id="statistikPendaftaranChart"></canvas>
            </div>
        </div>

        <!-- SLIDE 3: LOG AKTIVITAS TERBARU (5 Data Terbaru) -->
        <div class="w-full min-w-full flex-shrink-0 snap-start">
            <div class="w-full min-h-[320px] sm:min-h-[384px] flex flex-col justify-between">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-3">Waktu</th>
                                <th class="p-3">Admin / Pelaku</th>
                                <th class="p-3">Kategori</th>
                                <th class="p-3">Aktivitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 text-xs">
                            <?php 
                            // Query khusus mengambil 5 data aktivitas terbaru
                            $result_log_slide = $conn->query("SELECT al.*, a.foto, a.role FROM aktivitas_log al LEFT JOIN admins a ON al.admin_id = a.id ORDER BY al.id DESC LIMIT 5");
                            if ($result_log_slide && $result_log_slide->num_rows > 0): 
                                while ($log = $result_log_slide->fetch_assoc()): 
                            ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-3 whitespace-nowrap text-slate-400">
                                        <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <?php if (!empty($log['foto']) && file_exists('assets/uploads/' . $log['foto'])): ?>
                                                <img src="assets/uploads/<?php echo htmlspecialchars($log['foto']); ?>" class="w-7 h-7 rounded-full object-cover border border-slate-300 shadow-sm">
                                            <?php else: ?>
                                                <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[10px] shadow-sm">
                                                    <?php echo strtoupper(substr($log['admin_nama'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <span class="font-bold text-slate-800 block"><?php echo htmlspecialchars($log['admin_nama']); ?></span>
                                                <span class="text-[9px] text-indigo-600 uppercase font-semibold"><?php echo htmlspecialchars($log['role'] ?? 'admin'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 font-semibold px-2 py-0.5 rounded-md text-[10px]">
                                            <?php echo htmlspecialchars($log['kategori']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-slate-700 font-medium">
                                        <?php echo htmlspecialchars($log['aktivitas']); ?>
                                    </td>
                                </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-slate-400 italic">Belum ada aktivitas tercatat pada sistem.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right pt-2 text-[11px] text-slate-400 italic">
                    * Menampilkan 5 riwayat aktivitas sistem terbaru.
                </div>
            </div>
        </div>

    </div>
</div>

<!-- SECTION PETA PERSEBARAN PELANGGAN DI DASHBOARD -->
<div class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-8 p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
        <div>
            <h2 class="text-base font-bold text-slate-900">🗺️ Peta Persebaran Pelanggan</h2>
            <p class="text-xs text-slate-500">Visualisasi titik lokasi persebaran klien/pelanggan di seluruh wilayah.</p>
        </div>
        <div class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-lg">
            Total Titik Terpetakan: <?php echo count($pelanggan_markers); ?> Lokasi
        </div>
    </div>
    <div id="dashboardPersebaranMap" class="w-full h-96 rounded-xl border border-slate-300 z-10 shadow-inner"></div>
</div>

<!-- SCRIPT JAVASCRIPT UNTUK TOMBOL SLIDER (DIUPDATE MENJADI 3 SLIDE) -->
<script>
    let currentSlide = 0;
    const totalSlides = 3; // Diperbarui dari 2 menjadi 3 slide
    const container = document.getElementById('grafikSliderContainer');
    const titleEl = document.getElementById('grafikTitle');
    const descEl = document.getElementById('grafikDesc');
    const badgeEl = document.getElementById('grafikBadge');
    const indicatorEl = document.getElementById('slideIndicator');

    const slideInfo = [
        {
            title: "Grafik Total Pendapatan Per Bulan",
            desc: "Akumulasi jumlah uang masuk (pemasukan) berdasarkan bulan transaksi.",
            badge: "Tahun 2026"
        },
        {
            title: "Grafik Status Pendaftaran Peserta",
            desc: "Statistik rekapitulasi status calon peserta yang masuk ke sistem.",
            badge: "Statistik Peserta"
        },
        {
            title: "📋 Log Aktivitas Terbaru Admin",
            desc: "Riwayat aktivitas penambahan, perubahan, dan penghapusan data oleh sistem.",
            badge: "Aktivitas Terbaru"
        }
    ];

    function slideGrafik(direction) {
        if (direction === 'next') {
            currentSlide = (currentSlide + 1) % totalSlides;
        } else {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        }

        // Geser kontainer sesuai indeks slide aktif
        container.scrollTo({
            left: container.clientWidth * currentSlide,
            behavior: 'smooth'
        });

        // Perbarui teks informasi header sesuai slide aktif
        titleEl.innerText = slideInfo[currentSlide].title;
        descEl.innerText = slideInfo[currentSlide].desc;
        badgeEl.innerText = slideInfo[currentSlide].badge;
        indicatorEl.innerText = (currentSlide + 1) + " / " + totalSlides;
    }
</script>
<!-- Tabel Pekerja & Detail Lokasi Penugasan -->
<div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col mb-8">
    <div class="p-6 border-b border-slate-200">
        <h2 class="text-base font-bold text-slate-900">Data Pekerja / Mitra, Status & Lokasi Pelanggan</h2>
        <p class="text-xs text-slate-500">Memuat status ketersediaan, detail booking, serta alamat/lokasi pelanggan.</p>
    </div>
    <div class="overflow-x-auto flex-1">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                    <th class="p-4">Nama Pekerja</th>
                    <th class="p-4">Spesialisasi</th>
                    <th class="p-4">Status Pekerja</th>
                    <th class="p-4">Informasi Penugasan & Lokasi Pelanggan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-slate-700">
                <?php if ($data_pekerja && $data_pekerja->num_rows > 0): ?>
                    <?php while ($p = $data_pekerja->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-medium text-slate-900 text-xs">
                                <?php echo htmlspecialchars($p['nama_lengkap']); ?>
                            </td>
                            <td class="p-4 text-xs capitalize">
                                <?php echo htmlspecialchars($p['spesialisasi']); ?>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <?php if ($p['status_pekerja'] === 'Tersedia'): ?>
                                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                                        Tersedia
                                    </span>
                                <?php else: ?>
                                    <span class="bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                                        Sedang Bertugas
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-xs space-y-1">
                                <?php if ($p['status_pekerja'] === 'Sedang Bertugas' && !empty($p['kode_booking'])): ?>
                                    <span class="font-bold text-slate-800 block">Booking: <?php echo htmlspecialchars($p['kode_booking']); ?> (<?php echo htmlspecialchars($p['layanan']); ?>)</span>
                                    <span class="text-slate-700 font-semibold block">👤 Pelanggan: <?php echo htmlspecialchars($p['nama_pelanggan']); ?> (📞 <?php echo htmlspecialchars($p['telepon_pelanggan']); ?>)</span>
                                    <span class="text-indigo-600 font-medium block">📍 Alamat/Lokasi: <?php echo htmlspecialchars($p['alamat_pelanggan']); ?></span>
                                    <?php if (!empty($p['koordinat'])): ?>
                                        <span class="text-slate-500 text-[11px] block">🗺️ Lokasi: <?php echo htmlspecialchars($p['koordinat']); ?></span>
                                    <?php endif; ?>
                                    <span class="text-slate-400 text-[11px] block mt-0.5">📅 periode kontrak <?php echo htmlspecialchars($p['booking_mulai']); ?> s/d <?php echo htmlspecialchars($p['booking_selesai']); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic">Tidak ada penugasan aktif (Standby)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="p-6 text-center text-slate-400">Belum ada data pekerja.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

        </main>
    </div>

    <!-- Footer Dashboard -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 w-full mt-auto">
        &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
    </footer>

</div>

<!-- SCRIPT INISIALISASI CHART.JS & LEAFLET MAP -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // 0. Inisialisasi Peta Persebaran Pelanggan di Dashboard
    document.addEventListener("DOMContentLoaded", function() {
        const dashboardMap = L.map('dashboardPersebaranMap').setView([-6.8244, 107.1415], 10); // Default Cianjur
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(dashboardMap);

        // Data array marker pelanggan dari PHP
        const dataPelanggan = <?php echo json_encode($pelanggan_markers); ?>;

        if (dataPelanggan.length > 0) {
            let bounds = [];
            dataPelanggan.forEach(item => {
                if (item.koordinat) {
                    const coords = item.koordinat.split(',');
                    if (coords.length === 2) {
                        const lat = parseFloat(coords[0].trim());
                        const lng = parseFloat(coords[1].trim());

                        if (!isNaN(lat) && !isNaN(lng)) {
                            bounds.push([lat, lng]);

                            // Buat konten popup informatif saat marker diklik
                            let popupContent = `
                                <div style="font-family: inherit; font-size: 12px; line-height: 1.4;">
                                    <strong style="font-size: 14px; color: #4338ca; display: block; margin-bottom: 4px;">${item.nama_pelanggan}</strong>
                                    📍 <b>Alamat:</b> ${item.alamat || '-'}<br>
                                    📞 <b>Telepon:</b> ${item.telepon || '-'}<br>
                                    💼 <b>Layanan:</b> <span style="color: #059669; font-weight: bold;">${item.layanan_dipilih}</span><br>
                                    📅 <b>Mulai:</b> ${item.tanggal_mulai}<br>
                                    📌 <b>Status:</b> <span style="font-weight: bold; color: ${item.status === 'Aktif' ? '#10b981' : '#64748b'};">${item.status}</span>
                                </div>
                            `;

                            L.marker([lat, lng]).addTo(dashboardMap)
                              .bindPopup(popupContent);
                        }
                    }
                }
            });

            // Jika ada titik koordinat, atur otomatis zoom agar semua titik terlihat
            if (bounds.length > 0) {
                dashboardMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
            }
        }
    });

    // 1. Inisialisasi Grafik Keuangan
    const labelsData = <?php echo json_encode($bulan_array); ?>;
    const valuesData = <?php echo json_encode($total_array); ?>;

    const ctxKeuangan = document.getElementById('grafikKeuangan').getContext('2d');
    new Chart(ctxKeuangan, {
        type: 'line',
        data: {
            labels: labelsData,
            datasets: [{
                label: 'Total Pendapatan (Rp)',
                data: valuesData,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointRadius: 5,
                pointBackgroundColor: '#4f46e5'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 },
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });

    // 2. Inisialisasi Grafik Status Pendaftaran Peserta
    const ctxStatistik = document.getElementById('statistikPendaftaranChart').getContext('2d');
    new Chart(ctxStatistik, {
        type: 'bar',
        data: {
            labels: ['Diperiksa', 'Diterima', 'Ditolak'],
            datasets: [{
                label: 'Jumlah Pendaftar',
                data: [
                    <?php echo $data_status['Diperiksa']; ?>, 
                    <?php echo $data_status['Diterima']; ?>, 
                    <?php echo $data_status['Ditolak']; ?>
                ],
                backgroundColor: [
                    'rgba(245, 158, 11, 0.8)', // Amber untuk Diperiksa
                    'rgba(16, 185, 129, 0.8)', // Hijau untuk Diterima
                    'rgba(239, 68, 68, 0.8)'   // Merah untuk Ditolak
                ],
                borderColor: [
                    'rgb(245, 158, 11)',
                    'rgb(16, 185, 129)',
                    'rgb(239, 68, 68)'
                ],
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: 'rgba(226, 232, 240, 0.6)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>

<?php
require_once 'footer.php';
?>