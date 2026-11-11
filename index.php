<?php
// Panggil file konfigurasi database
include 'config.php'; 

// =========================================================================
// FITUR PENCARIAN AJAX (TERINTEGRASI DI HALAMAN INI)
// =========================================================================
if (isset($_GET['ajax_cari']) && $_GET['ajax_cari'] == '1') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

    if (empty($keyword)) {
        echo json_encode(['status' => 'error', 'message' => 'Silakan masukkan Nama atau NIK terlebih dahulu!']);
        exit;
    }

    if (isset($conn) && !$conn->connect_error) {
        // PERUBAHAN 1: Mengganti 'berkas_sk' menjadi 'sertifikat' pada query SQL
        $stmt = $conn->prepare("SELECT nama_lengkap, nik, telepon, program_pilihan, tanggal_daftar, status, sertifikat FROM pendaftaran WHERE nama_lengkap LIKE ? OR nik LIKE ?");
        if ($stmt) {
            $searchTerm = "%" . $keyword . "%";
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'nama_lengkap'    => htmlspecialchars($row['nama_lengkap']),
                        'nik'             => htmlspecialchars($row['nik'] ?? '-'),
                        'telepon'         => htmlspecialchars($row['telepon']),
                        'program_pilihan' => htmlspecialchars($row['program_pilihan']),
                        'tanggal_daftar'  => htmlspecialchars($row['tanggal_daftar']),
                        'status'          => htmlspecialchars($row['status']),
                        // PERUBAHAN 2: Mengganti key dan sumber data dari 'berkas_sk' ke 'sertifikat'
                        'sertifikat'      => htmlspecialchars($row['sertifikat'] ?? '')
                    ];
                }
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data peserta tidak ditemukan dengan Nama atau NIK tersebut.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Kesalahan pada query database.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    }
    exit;
}

// Nilai default pengaman jika tabel belum ada atau kosong
$pendaftaran = [
    'gelombang'         => 'Gelombang Reguler',
    'program'           => 'Caregiver Lansia,',
    'lokasi'            => 'Cianjur, Jawa Barat',
    'telepon_panitia' => '081220192571',
    'status'            => 'Aktif'
];

if (isset($conn) && !$conn->connect_error) {
    $check_table = $conn->query("SHOW TABLES LIKE 'pendaftaran_info'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("SELECT * FROM pendaftaran_info ORDER BY id DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $pendaftaran = $result->fetch_assoc();
        }
    }
}

// Query Total Pelanggan & Ambil Data untuk Peta Persebaran
$total_pelanggan = 0;
$pelanggan_markers = [];
if (isset($conn) && !$conn->connect_error) {
    $check_pelanggan = $conn->query("SHOW TABLES LIKE 'pelanggan'");
    if ($check_pelanggan && $check_pelanggan->num_rows > 0) {
        $res_total = $conn->query("SELECT COUNT(*) as total FROM pelanggan");
        if ($res_total) {
            $total_pelanggan = $res_total->fetch_assoc()['total'];
        }
        $result_peta = $conn->query("SELECT * FROM pelanggan WHERE koordinat IS NOT NULL AND koordinat != ''");
        if ($result_peta) {
            while ($m_row = $result_peta->fetch_assoc()) {
                $pelanggan_markers[] = $m_row;
            }
        }
    }
}

// Inisialisasi variabel dengan aman agar tidak terjadi Undefined Variable
$gelombang       = isset($pendaftaran['gelombang']) ? $pendaftaran['gelombang'] : 'Gelombang Reguler';
$status_db       = isset($pendaftaran['status']) ? $pendaftaran['status'] : 'Aktif';
$is_aktif        = (strtolower($status_db) === 'aktif');
$telepon_panitia = isset($pendaftaran['telepon_panitia']) ? $pendaftaran['telepon_panitia'] : '081220192571';
$formatted_phone = preg_replace('/^0/', '62', $telepon_panitia);
$lokasi          = isset($pendaftaran['lokasi']) ? $pendaftaran['lokasi'] : 'Cianjur, Jawa Barat';
$program         = isset($pendaftaran['program']) ? $pendaftaran['program'] : 'Caregiver Lansia & Early Childhood Care';
// Pastikan variabel status dari database sudah diambil, misalnya:
// $status_db = $row['status']; (atau variabel yang menyimpan data status dari database Anda)

// 1. Tentukan apakah statusnya Aktif atau Tidak Aktif
$is_aktif = (isset($status_db) && $status_db == 'Aktif');

// 2. Definisikan variabel $status_teks agar tidak muncul warning undefined variable
// Jika Aktif -> "Dibuka", Jika Tidak Aktif -> "Ditutup"
$status_teks = $is_aktif ? 'Dibuka' : 'Ditutup';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LPK Politeknik Bhakti Kencana</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- AOS Library CSS untuk Animasi Masuk/Keluar -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Leaflet CSS untuk Peta -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body class="bg-slate-50 text-slate-800 antialiased overflow-x-hidden">

    <!-- ================= NAVBAR ================= -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between relative">
            <a href="index.php" class="flex items-center space-x-3 hover:opacity-90 transition-opacity">
                <img src="assets/img/logo.png" alt="Logo LPK" class="w-14 h-14 sm:w-16 sm:h-16 object-contain">
                <div>
                    <span class="block font-bold text-base sm:text-lg leading-tight">𝙻𝙿𝙺 𝙿𝙾𝙻𝙸𝚃𝙴𝙺𝙽𝙸𝙺 𝙱𝙷𝙰𝙺𝚃𝙸 𝙺𝙴𝙽𝙲𝙰𝙽𝙰</span>
                    <span class="text-xs text-slate-500">Training Center</span>
                </div>
            </a>

            <!-- Menu Desktop -->
            <div class="hidden md:flex space-x-8 font-medium text-slate-700">
                <a href="#layanan" class="hover:text-indigo-600 transition">Layanan</a>
                <a href="#pegawai-lpk" class="hover:text-indigo-600 transition">Pegawai LPK</a>
                <a href="#lpk" class="hover:text-indigo-600 transition">Program LPK</a>
                <a href="#keunggulan" class="hover:text-indigo-600 transition">Keunggulan</a>
                <a href="#kontak" class="hover:text-indigo-600 transition">Kontak</a>
            </div>

            <!-- Tombol Konsultasi & Hamburger Mobile -->
            <div class="flex items-center space-x-3">
                <a href="https://wa.me/6285199274269" target="_blank" class="hidden sm:inline-block bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-full font-medium shadow-sm transition text-sm">
                    WhatsApp Konsultasi
                </a>

                <button id="menu-btn" type="button" class="md:hidden text-slate-700 focus:outline-none p-2.5 rounded-xl hover:bg-slate-100 transition cursor-pointer relative z-50 border border-slate-200 shadow-xs" aria-label="Menu Mobile">
                    <svg id="menu-icon" class="w-6 h-6 pointer-events-none transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path id="icon-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Menu Dropdown Mobile -->
        <div id="mobile-menu" class="hidden md:hidden absolute top-20 left-0 w-full bg-white/95 backdrop-blur-md border-b border-slate-200 px-6 py-6 space-y-4 shadow-xl z-45 transition-all duration-300">
            <div class="flex flex-col space-y-3 font-medium text-slate-700">
                <a href="#layanan" class="mobile-link hover:text-indigo-600 transition py-2 border-b border-slate-100 block">Layanan</a>
                <a href="#pegawai-lpk" class="mobile-link hover:text-indigo-600 transition py-2 border-b border-slate-100 block">Pegawai LPK</a>
                <a href="#lpk" class="mobile-link hover:text-indigo-600 transition py-2 border-b border-slate-100 block">Program LPK</a>
                <a href="#keunggulan" class="mobile-link hover:text-indigo-600 transition py-2 border-b border-slate-100 block">Keunggulan</a>
                <a href="#kontak" class="mobile-link hover:text-indigo-600 transition py-2 block">Kontak</a>
            </div>
            <div class="pt-2">
                <a href="https://wa.me/6285199274269" target="_blank" class="block text-center bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl font-medium shadow-sm transition text-sm">
                    WhatsApp Konsultasi
                </a>
            </div>
        </div>
    </nav>

    <!-- Script Navbar Interaktif & Toggle Mobile Menu -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const menuBtn = document.getElementById('menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const iconPath = document.getElementById('icon-path');
        const mobileLinks = document.querySelectorAll('.mobile-link');

        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('hidden');
                const isOpen = !mobileMenu.classList.contains('hidden');
                
                if (isOpen) {
                    iconPath.setAttribute('d', 'M6 18L18 6M6 6l12 12');
                } else {
                    iconPath.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
                }
            });

            document.addEventListener('click', function (e) {
                if (!mobileMenu.contains(e.target) && !menuBtn.contains(e.target)) {
                    mobileMenu.classList.add('hidden');
                    iconPath.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
                }
            });

            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    iconPath.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
                });
            });
        }
    });
    </script>

    <!-- ================= HERO SECTION ================= -->
    <header class="relative bg-slate-900 text-white py-20 lg:py-32 overflow-hidden" data-aos="fade" data-aos-duration="1000">
        <div id="hero-slider" class="absolute inset-0 z-0">
            <div class="hero-slide absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-100" style="background-image: url('assets/hero/slide1.jpg');"></div>
            <div class="hero-slide absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-0" style="background-image: url('assets/hero/slide2.jpg');"></div>
            <div class="hero-slide absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-0" style="background-image: url('assets/hero/slide3.jpg');"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-950/90 via-indigo-900/80 to-slate-950/90"></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center max-w-3xl" data-aos="zoom-in" data-aos-duration="1000">
            <span class="bg-indigo-500/30 text-indigo-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider mb-4 inline-block backdrop-blur-sm">Resmi & Tersertifikasi</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-6 leading-tight drop-shadow-sm">
                Solusi Terbaik Perawatan Keluarga & Pelatihan Kerja Profesional
            </h1>
            <p class="text-base sm:text-lg text-indigo-100 mb-10 drop-shadow-sm">
                Layanan Daycare, Home Care Anak Usia Dini, Perawatan Lansia (Caregiver), serta Pelatihan Kerja siap cetak tenaga andal.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="#layanan" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-8 py-3.5 rounded-xl shadow-lg transition">Jelajahi Layanan</a>
                <a href="#lpk" class="bg-white/10 hover:bg-white/20 hover:backdrop-blur-md text-white border border-white/20 font-medium px-8 py-3.5 rounded-xl transition">Daftar LPK</a>
            </div>
        </div>
    </header>

    <!-- Script Slider Background Otomatis -->
    <script>
        const slides = document.querySelectorAll('.hero-slide');
        let currentSlide = 0;
        const slideInterval = 4000;

        function nextSlide() {
            if (slides.length > 0) {
                slides[currentSlide].classList.remove('opacity-100');
                slides[currentSlide].classList.add('opacity-0');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.remove('opacity-0');
                slides[currentSlide].classList.add('opacity-100');
            }
        }
        if (slides.length > 0) {
            setInterval(nextSlide, slideInterval);
        }
    </script>

<!-- ================= 4 PILAR LAYANAN UTAMA ================= -->
    <section id="layanan" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 overflow-hidden">
        <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up" data-aos-duration="800">
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-4">Pilar Layanan Utama Kami</h2>
            <p class="text-slate-600 text-sm sm:text-base">Pilih layanan profesional yang dirancang khusus untuk memenuhi kebutuhan kasih sayang dan kesehatan keluarga Anda.</p>
        </div>
        
        <!-- Wrapper Container Scroll Horisontal Mobile & Grid Desktop -->
        <div id="layanan-container" class="flex md:grid md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 overflow-x-auto md:overflow-visible snap-x snap-mandatory scrollbar-none pb-4 -mx-4 px-4 md:mx-0 md:px-0">
            
            <!-- 1. PBK Daycare -->
            <div data-aos="fade-up" data-aos-delay="100" class="min-w-[65%] sm:min-w-[260px] md:min-w-0 h-[440px] sm:h-[480px] snap-center relative rounded-3xl p-5 shadow-xl overflow-hidden bg-cover bg-center group transition hover:-translate-y-1 flex-shrink-0 flex flex-col justify-between" style="background-image: url('assets/layanan/homecare-anak.jpg');">
                <!-- Gradasi gelap dari atas ke bawah khas kartu profil -->
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-black/20"></div>
                
                <div class="relative z-10 flex justify-between items-center">
                    <span class="bg-indigo-600/90 text-white text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-sm">Daycare</span>
                </div>

                <div class="relative z-10 text-white flex flex-col justify-end">
                    <h3 class="text-xl font-extrabold mb-1 tracking-tight">PBK Daycare</h3>
                    <p class="text-slate-300 text-xs mb-3 line-clamp-2">
                        Lingkungan aman, higienis, dan edukatif untuk buah hati Anda.
                    </p>
                    <div class="flex items-center space-x-1.5 text-indigo-300 text-[11px] font-medium mb-4">
                        <span></span>
                        <span>Penitipan Harian / Full Jam</span>
                    </div>
                    <a href="daycare.php" class="block text-center bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold py-2.5 rounded-xl text-xs backdrop-blur-sm transition">
                        Klik untuk Detail & Booking
                    </a>
                </div>
            </div>

            <!-- 2. PBK Babycare -->
            <div data-aos="fade-up" data-aos-delay="200" class="min-w-[65%] sm:min-w-[260px] md:min-w-0 h-[440px] sm:h-[480px] snap-center relative rounded-3xl p-5 shadow-xl overflow-hidden bg-cover bg-center group transition hover:-translate-y-1 flex-shrink-0 flex flex-col justify-between" style="background-image: url('assets/layanan/homecare-babycare.jpg');">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-black/20"></div>
                
                <div class="relative z-10 flex justify-between items-center">
                    <span class="bg-pink-600/90 text-white text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-sm">Babycare</span>
                </div>

                <div class="relative z-10 text-white flex flex-col justify-end">
                    <h3 class="text-xl font-extrabold mb-1 tracking-tight">PBK Babycare</h3>
                    <p class="text-slate-300 text-xs mb-3 line-clamp-2">
                        Perawatan khusus bayi baru lahir hingga balita dengan penuh kelembutan.
                    </p>
                    <div class="flex items-center space-x-1.5 text-pink-300 text-[11px] font-medium mb-4">
                        <span></span>
                        <span>Perawatan Khusus Bayi</span>
                    </div>
                    <a href="babycare.php" class="block text-center bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold py-2.5 rounded-xl text-xs backdrop-blur-sm transition">
                        Klik untuk Detail & Booking
                    </a>
                </div>
            </div>

            <!-- 3. PBK Homecare -->
            <div data-aos="fade-up" data-aos-delay="300" class="min-w-[65%] sm:min-w-[260px] md:min-w-0 h-[440px] sm:h-[480px] snap-center relative rounded-3xl p-5 shadow-xl overflow-hidden bg-cover bg-center group transition hover:-translate-y-1 flex-shrink-0 flex flex-col justify-between" style="background-image: url('assets/layanan/homecare.jpg');">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-black/20"></div>
                
                <div class="relative z-10 flex justify-between items-center">
                    <span class="bg-sky-600/90 text-white text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-sm">Homecare</span>
                </div>

                <div class="relative z-10 text-white flex flex-col justify-end">
                    <h3 class="text-xl font-extrabold mb-1 tracking-tight">PBK Homecare</h3>
                    <p class="text-slate-300 text-xs mb-3 line-clamp-2">
                        Layanan kesehatan dan perawat privat yang didatangkan ke rumah.
                    </p>
                    <div class="flex items-center space-x-1.5 text-sky-300 text-[11px] font-medium mb-4">
                        <span></span>
                        <span>Medis & Non-Medis</span>
                    </div>
                    <a href="homecare.php" class="block text-center bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold py-2.5 rounded-xl text-xs backdrop-blur-sm transition">
                        Klik untuk Detail & Booking
                    </a>
                </div>
            </div>

            <!-- 4. PBK Caregiver -->
            <div data-aos="fade-up" data-aos-delay="400" class="min-w-[65%] sm:min-w-[260px] md:min-w-0 h-[440px] sm:h-[480px] snap-center relative rounded-3xl p-5 shadow-xl overflow-hidden bg-cover bg-center group transition hover:-translate-y-1 flex-shrink-0 flex flex-col justify-between" style="background-image: url('assets/layanan/homecare-lansia.jpg');">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-black/20"></div>
                
                <div class="relative z-10 flex justify-between items-center">
                    <span class="bg-emerald-600/90 text-white text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-sm">Caregiver</span>
                </div>

                <div class="relative z-10 text-white flex flex-col justify-end">
                    <h3 class="text-xl font-extrabold mb-1 tracking-tight">PBK Caregiver</h3>
                    <p class="text-slate-300 text-xs mb-3 line-clamp-2">
                        Pendampingan dan perawatan khusus lansia di rumah penuh kesabaran.
                    </p>
                    <div class="flex items-center space-x-1.5 text-emerald-300 text-[11px] font-medium mb-4">
                        <span></span>
                        <span>Live-in / Shift Jaga</span>
                    </div>
                    <a href="caregiver.php" class="block text-center bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold py-2.5 rounded-xl text-xs backdrop-blur-sm transition">
                        Klik untuk Detail & Booking
                    </a>
                </div>
            </div>

        </div>

        <!-- Indikator Titik (Dots) Khusus Tampilan Mobile -->
        <div class="flex justify-center items-center space-x-2 mt-6 md:hidden">
            <span class="dot h-2 w-6 bg-indigo-600 rounded-full transition-all duration-300"></span>
            <span class="dot h-2 w-2 bg-slate-300 rounded-full transition-all duration-300"></span>
            <span class="dot h-2 w-2 bg-slate-300 rounded-full transition-all duration-300"></span>
            <span class="dot h-2 w-2 bg-slate-300 rounded-full transition-all duration-300"></span>
        </div>
    </section>

    <!-- Script untuk Indikator Geser (Dots) -->
    <script>
    const container = document.getElementById('layanan-container');
    const dots = document.querySelectorAll('.dot');

    if (container) {
        container.addEventListener('scroll', () => {
            const scrollLeft = container.scrollLeft;
            const card = container.querySelector('div');
            const cardWidth = card.offsetWidth + 16; // Lebar card + gap (gap-4 = 16px)
            const index = Math.round(scrollLeft / cardWidth);

            dots.forEach((dot, i) => {
                if (i === index) {
                    dot.classList.remove('w-2', 'bg-slate-300');
                    dot.classList.add('w-6', 'bg-indigo-600');
                } else {
                    dot.classList.remove('w-6', 'bg-indigo-600');
                    dot.classList.add('w-2', 'bg-slate-300');
                }
            });
        });
    }
    </script>

    <!-- CSS Tambahan untuk Menyembunyikan Scrollbar Bawaan -->
    <style>
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    </style>

<!-- ================= SECTION PEGAWAI LPK ================= -->
    <section id="pegawai-lpk" class="mt-16 py-8 overflow-hidden" data-aos="fade-up" data-aos-duration="1000">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col items-center justify-center gap-4 text-center">
                <div>
                    <h3 class="text-2xl md:text-3xl font-bold text-slate-900">Daftar Talenta & Pegawai LPK</h3>
                    <p class="text-slate-600 text-sm mt-1">Pilih tenaga profesional bersertifikat kami yang siap bertugas. (Klik kartu untuk melihat detail)</p>
                </div>

                <div id="sliderControlWrapper" class="hidden flex-wrap items-center justify-center gap-2">
                    <button id="togglePlayBtn" class="flex items-center space-x-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-full text-xs font-semibold transition border border-slate-200 shadow-sm cursor-pointer">
                        <svg id="playPauseIcon" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path id="iconSvgPath" d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                        <span id="playPauseText">Jeda Geser</span>
                    </button>
                </div>

                <!-- MENU FILTER BERDASARKAN JAM KERJA -->
                <div id="filterJamKerjaContainer" class="flex flex-wrap items-center justify-center gap-2 mt-2">
                    <button data-filter="all" class="filter-btn bg-indigo-600 text-white px-4 py-1.5 rounded-full text-xs font-semibold shadow-sm transition cursor-pointer">Semua Jam Kerja</button>
                    <button data-filter="Shift Pagi" class="filter-btn bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-4 py-1.5 rounded-full text-xs font-semibold shadow-sm transition cursor-pointer">Shift Pagi</button>
                    <button data-filter="Shift Siang" class="filter-btn bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-4 py-1.5 rounded-full text-xs font-semibold shadow-sm transition cursor-pointer">Shift Siang</button>
                    <button data-filter="Shift Malam" class="filter-btn bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-4 py-1.5 rounded-full text-xs font-semibold shadow-sm transition cursor-pointer">Shift Malam</button>
                    <button data-filter="Full Time" class="filter-btn bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-4 py-1.5 rounded-full text-xs font-semibold shadow-sm transition cursor-pointer">Full Time</button>
                    <button data-filter="Live-in" class="filter-btn bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-4 py-1.5 rounded-full text-xs font-semibold shadow-sm transition cursor-pointer">Live-in</button>
                </div>
            </div>

            <?php
            $query_pekerja = "SELECT * FROM `pekerja` ORDER BY id DESC";
            $result_pekerja = mysqli_query($conn, $query_pekerja);
            ?>

            <?php if ($result_pekerja && mysqli_num_rows($result_pekerja) > 0): ?>
            <!-- Tambahkan justify-center agar jika data sedikit, posisinya berada di tengah -->
            <div class="relative overflow-hidden py-2">
                <div class="flex overflow-x-auto gap-6 pb-6 pt-2 snap-x snap-mandatory scroll-smooth no-scrollbar justify-start md:justify-center" id="pekerjaSlider">
                    <?php while ($row = mysqli_fetch_assoc($result_pekerja)): ?>
                    <div class="relative min-w-[220px] sm:min-w-[240px] md:min-w-[260px] max-w-[260px] h-[360px] rounded-2xl overflow-hidden shadow-md border border-slate-200 flex flex-col justify-between p-4 group cursor-pointer pekerja-card flex-shrink-0 bg-white transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
                         data-foto="<?= htmlspecialchars($row['foto']); ?>"
                         data-nama="<?= htmlspecialchars($row['nama_lengkap']); ?>"
                         data-spesialisasi="<?= htmlspecialchars($row['spesialisasi']); ?>"
                         data-jamkerja="<?= htmlspecialchars($row['jam_kerja']); ?>">
                        
                        <div class="absolute inset-0 z-0 overflow-hidden">
                            <img src="<?= htmlspecialchars($row['foto']); ?>" alt="<?= htmlspecialchars($row['nama_lengkap']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>

                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/20 to-transparent z-10"></div>

                        <div class="relative z-20 flex justify-between items-start w-full gap-2">
                            <span class="bg-indigo-600/90 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider truncate max-w-[120px]">
                                <?= htmlspecialchars($row['spesialisasi']); ?>
                            </span>

                            <?php if ($row['status_pekerja'] == 'Tersedia'): ?>
                                <span class="bg-emerald-500/95 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm whitespace-nowrap">Tersedia</span>
                            <?php else: ?>
                                <span class="bg-amber-500/95 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm whitespace-nowrap">Bertugas</span>
                            <?php endif; ?>
                        </div>

                        <div class="relative z-20 text-white space-y-1 w-full pb-1">
                            <h4 class="font-bold text-base leading-tight drop-shadow-md line-clamp-2">
                                <?= htmlspecialchars($row['nama_lengkap']); ?>
                            </h4>
                            <p class="text-[11px] text-indigo-200 font-medium">⏰ <?= htmlspecialchars($row['jam_kerja']); ?></p>
                            <p class="text-[10px] text-slate-200 italic">Klik untuk Detail & Booking</p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php else: ?>
                <div class="text-center py-8 text-slate-500 bg-white rounded-2xl border border-slate-200">
                    Belum ada data pegawai/talenta yang tersedia di database.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #pekerjaSlider { user-select: none; -webkit-user-select: none; }
    </style>

    <!-- Modal Pop-up Pekerja -->
    <div id="photoModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl overflow-hidden max-w-sm w-full shadow-2xl relative transform transition-all" onclick="event.stopPropagation()">
            <button onclick="closeModal()" class="absolute top-3 right-3 z-30 bg-black/50 hover:bg-black/70 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition cursor-pointer">✕</button>
            
            <div class="relative w-full h-64 bg-slate-100">
                <img id="modalImg" src="" alt="Foto Pekerja" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>
            </div>

            <div class="p-6 space-y-4 bg-white text-center">
                <div>
                    <span id="modalSpesialisasi" class="inline-block bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider mb-2"></span>
                    <h3 id="modalNama" class="text-xl font-bold text-slate-900 leading-tight"></h3>
                    <p class="text-xs text-slate-500 mt-1">Silakan hubungi admin LPK untuk informasi ketersediaan dan proses booking talenta ini.</p>
                </div>
                
                <a id="modalWaBtn" href="#" target="_blank" class="block w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm py-3 rounded-xl transition font-semibold text-center shadow-lg">
                    Hubungi / Booking via WhatsApp
                </a>
            </div>
        </div>
    </div>

<!-- Script Slider Cerdas (Tanpa Duplikasi Jika Data Sedikit) -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const slider = document.getElementById('pekerjaSlider');
        const togglePlayBtn = document.getElementById('togglePlayBtn');
        const sliderControlWrapper = document.getElementById('sliderControlWrapper');
        const iconSvgPath = document.getElementById('iconSvgPath');
        const playPauseText = document.getElementById('playPauseText');
        const filterButtons = document.querySelectorAll('.filter-btn');

        let isPlaying = true;
        let scrollAnimationId;

        function checkSliderOverflow() {
            if (!slider) return;
            
            // Hitung kartu yang sedang tampil (tidak di-display: none oleh filter)
            const visibleCards = Array.from(slider.querySelectorAll('.pekerja-card')).filter(card => card.style.display !== 'none');
            
            // Cek apakah total lebar konten meluas melebihi lebar kontainer
            const isOverflowing = slider.scrollWidth > slider.clientWidth;

            if (isOverflowing && visibleCards.length > 1) {
                if (sliderControlWrapper) sliderControlWrapper.style.display = 'flex';
                slider.classList.remove('justify-center');
                slider.classList.add('justify-start');
            } else {
                // Jika data sedikit / pas, matikan animasi geser & posisikan di tengah tanpa duplikasi
                if (sliderControlWrapper) sliderControlWrapper.style.display = 'none';
                if (scrollAnimationId) cancelAnimationFrame(scrollAnimationId);
                slider.classList.remove('justify-start');
                slider.classList.add('justify-center');
            }
        }

        if (slider) {
            function stepScroll() {
                const visibleCards = Array.from(slider.querySelectorAll('.pekerja-card')).filter(card => card.style.display !== 'none');
                const isOverflowing = slider.scrollWidth > slider.clientWidth;

                if (isPlaying && isOverflowing && visibleCards.length > 1) {
                    slider.scrollLeft += 1.2;
                    // Efek putar balik mulus ke awal jika sudah sampai ujung
                    if (slider.scrollLeft >= (slider.scrollWidth - slider.clientWidth)) {
                        slider.scrollLeft = 0;
                    }
                }
                scrollAnimationId = requestAnimationFrame(stepScroll);
            }

            scrollAnimationId = requestAnimationFrame(stepScroll);

            slider.addEventListener('mouseenter', () => { if (isPlaying) cancelAnimationFrame(scrollAnimationId); });
            slider.addEventListener('mouseleave', () => { 
                const visibleCards = Array.from(slider.querySelectorAll('.pekerja-card')).filter(card => card.style.display !== 'none');
                if (isPlaying && slider.scrollWidth > slider.clientWidth && visibleCards.length > 1) {
                    scrollAnimationId = requestAnimationFrame(stepScroll); 
                }
            });

            if (togglePlayBtn) {
                togglePlayBtn.addEventListener('click', function () {
                    isPlaying = !isPlaying;
                    if (isPlaying) {
                        iconSvgPath.setAttribute('d', 'M6 19h4V5H6v14zm8-14v14h4V5h-4z');
                        playPauseText.textContent = 'Jeda Geser';
                        scrollAnimationId = requestAnimationFrame(stepScroll);
                    } else {
                        iconSvgPath.setAttribute('d', 'M8 5v14l11-7z');
                        playPauseText.textContent = 'Lanjut Geser';
                        cancelAnimationFrame(scrollAnimationId);
                    }
                });
            }

            // Fitur Filter Berdasarkan Jam Kerja
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterButtons.forEach(b => {
                        b.classList.remove('bg-indigo-600', 'text-white');
                        b.classList.add('bg-white', 'text-slate-700', 'border', 'border-slate-200');
                    });
                    this.classList.remove('bg-white', 'text-slate-700', 'border', 'border-slate-200');
                    this.classList.add('bg-indigo-600', 'text-white');

                    const filterValue = this.getAttribute('data-filter');
                    const cards = slider.querySelectorAll('.pekerja-card');

                    cards.forEach(card => {
                        const jamKerjaCard = card.getAttribute('data-jamkerja');
                        if (filterValue === 'all' || jamKerjaCard.includes(filterValue)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    slider.scrollLeft = 0;
                    setTimeout(checkSliderOverflow, 50); // Evaluasi ulang setelah filter diklik
                });
            });

            window.addEventListener('load', checkSliderOverflow);
            window.addEventListener('resize', checkSliderOverflow);
            setTimeout(checkSliderOverflow, 100);
        }

        const modal = document.getElementById('photoModal');
        const modalImg = document.getElementById('modalImg');
        const modalNama = document.getElementById('modalNama');
        const modalSpesialisasi = document.getElementById('modalSpesialisasi');
        const modalWaBtn = document.getElementById('modalWaBtn');

        if (slider) {
            slider.addEventListener('click', function (e) {
                const card = e.target.closest('.pekerja-card');
                if (!card) return;

                const foto = card.getAttribute('data-foto');
                const nama = card.getAttribute('data-nama');
                const spesialisasi = card.getAttribute('data-spesialisasi');

                modalImg.src = foto;
                modalNama.textContent = nama;
                modalSpesialisasi.textContent = spesialisasi;

                const nomorWaAdmin = '6285199274269';
                const pesanWa = `Halo Admin LPK, saya tertarik untuk booking talenta atas nama *${nama}* (${spesialisasi}). Mohon informasi lebih lanjut.`;
                modalWaBtn.href = `https://wa.me/${nomorWaAdmin}?text=${encodeURIComponent(pesanWa)}`;

                modal.classList.remove('hidden');
            });
        }

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }
    });

    function closeModal() {
        const modal = document.getElementById('photoModal');
        if (modal) modal.classList.add('hidden');
    }
    </script>

    <!-- ================= SECTION PROGRAM LPK ================= -->
    <section id="lpk" class="py-20 bg-slate-50 border-y border-slate-200 overflow-hidden" data-aos="fade-up" data-aos-duration="1000">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                
                <!-- Sisi Kiri -->
                <div data-aos="fade-right" data-aos-duration="1000">
                    <span class="text-indigo-600 font-semibold text-sm uppercase tracking-wider bg-indigo-100 px-3 py-1 rounded-full">Pendidikan & Pelatihan</span>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mt-3 mb-6">Program LPK Bhakti Kencana Cianjur</h2>
                    <p class="text-slate-600 leading-relaxed mb-6">
                        Kami mencetak tenaga kerja profesional, terampil, dan berakhlak mulia yang siap disalurkan ke dunia kerja formal maupun mandiri di bidang perawatan keluarga.
                    </p>
                    <div class="space-y-4 mb-8">
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mt-1 shadow-md">✓</div>
                            <div>
                                <h4 class="font-bold text-slate-800">Kurikulum Berstandar Industri</h4>
                                <p class="text-sm text-slate-600">Materi praktik langsung dan teori terpadu sesuai kebutuhan pasar kerja saat ini.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mt-1 shadow-md">✓</div>
                            <div>
                                <h4 class="font-bold text-slate-800">Sertifikasi Resmi</h4>
                                <p class="text-sm text-slate-600">Lulusan mendapatkan sertifikat resmi LPK yang diakui dan siap pakai.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mt-1 shadow-md">✓</div>
                            <div>
                                <h4 class="font-bold text-slate-800">Penyaluran Kerja & Konsultasi</h4>
                                <p class="text-sm text-slate-600">Dukungan penuh penempatan kerja serta pendampingan karier bagi peserta didik.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 items-center">
                        <?php if ($is_aktif): ?>
                            <a href="pendaftaran.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-8 py-3.5 rounded-xl shadow-lg transition inline-block">Daftar Program LPK Sekarang</a>
                        <?php else: ?>
                            <button disabled class="bg-slate-300 text-slate-500 font-medium px-8 py-3.5 rounded-xl cursor-not-allowed shadow-none inline-block">Pendaftaran Ditutup Sementara</button>
                        <?php endif; ?>

                        <button onclick="openCheckModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-6 py-3.5 rounded-xl shadow-lg transition inline-flex items-center gap-2 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Cek Data Peserta
                        </button>
                    </div>
                </div>

<!-- Sisi Kanan (Informasi Gelombang Aktif dari Database) -->
<div data-aos="fade-left" data-aos-duration="1000" class="rounded-3xl p-8 text-white shadow-xl relative overflow-hidden bg-cover bg-center" style="background-image: url('assets/img/daftar.jpg');">
    <div class="absolute inset-0 bg-indigo-950/80 backdrop-blur-[2px]"></div>

    <div class="relative z-10">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-2xl font-bold">Status Pendaftaran</h3>
            <!-- Badge berubah warna dan tulisannya otomatis -->
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php echo $is_aktif ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'; ?>">
                <?php echo htmlspecialchars($status_teks); ?>
            </span>
        </div>

        <div class="text-indigo-300 font-semibold text-sm mb-4">
            📢 <?php echo htmlspecialchars($gelombang); ?>
        </div>

        <p class="text-indigo-200 text-sm mb-6">
            <?php echo $is_aktif ? 'Amankan kursi pelatihan Anda sekarang di LPK Bhakti Kencana Cianjur. Kuota kelas terbatas setiap sesinya.' : 'Mohon maaf, gelombang pendaftaran saat ini sedang ditutup. Silakan pantau informasi berkala.'; ?>
        </p>

        <ul class="space-y-3 text-sm text-indigo-100 mb-8">
            <li>📍 <strong>Lokasi:</strong> <?php echo htmlspecialchars($lokasi); ?></li>
            <li>🎓 <strong>Program:</strong> <?php echo htmlspecialchars($program); ?></li>
            <li>📞 <strong>Hotline Panitia:</strong> <?php echo htmlspecialchars($telepon_panitia); ?></li>
        </ul>

        <?php if ($is_aktif): ?>
            <a href="https://wa.me/<?php echo $formatted_phone; ?>?text=Halo,%20saya%20ingin%20tanya%20jadwal%20pendaftaran%20<?php echo urlencode($gelombang); ?>%20LPK%20Bhakti%20Kencana%20Cianjur" target="_blank" class="block text-center bg-white text-indigo-900 hover:bg-indigo-50 font-bold py-3 rounded-xl transition shadow-sm">Hubungi Panitia Pendaftaran</a>
        <?php else: ?>
            <div class="block text-center bg-slate-800 text-slate-400 font-medium py-3 rounded-xl text-sm">Pendaftaran Ditutup</div>
        <?php endif; ?>
    </div>
</div>

            </div>
        </div>
    </section>

<!-- Modal Cek Data Peserta -->
<div id="checkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden transform transition-all">
        <div class="bg-emerald-600 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-lg">Cek Status & Download Sertifikat</h3>
            <button onclick="closeCheckModal()" class="text-white hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Masukkan Nama Lengkap atau NIK</label>
                <div class="flex gap-2">
                    <input type="text" id="keyword" placeholder="Contoh: Kori / 320307..." class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button onclick="searchData()" id="searchBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl transition cursor-pointer">Cari</button>
                </div>
            </div>

            <div id="resultContainer" class="hidden mt-4 border-t border-slate-200 pt-4 max-h-80 overflow-y-auto"></div>
        </div>
    </div>
</div>

<!-- Script Modal & AJAX Pencarian dengan Animasi Download 0-100% -->
<script>
function openCheckModal() { 
    document.getElementById('checkModal').classList.remove('hidden'); 
}

function closeCheckModal() {
    document.getElementById('checkModal').classList.add('hidden');
    document.getElementById('resultContainer').classList.add('hidden');
    document.getElementById('keyword').value = '';
}

function searchData() {
    const keyword = document.getElementById('keyword').value.trim();
    const resultContainer = document.getElementById('resultContainer');
    
    if (!keyword) {
        alert('Silakan masukkan Nama atau NIK terlebih dahulu!');
        return;
    }

    resultContainer.innerHTML = '<p class="text-center text-slate-500 py-4">Mencari data...</p>';
    resultContainer.classList.remove('hidden');

    fetch('?ajax_cari=1&keyword=' + encodeURIComponent(keyword))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '';
                data.data.forEach((item, index) => {
                    let downloadButton = '';
                    
                    // Validasi ketat memastikan kolom 'sertifikat' terisi nama file/path yang valid
                    if (item.sertifikat && item.sertifikat.trim() !== "" && item.sertifikat !== "null" && item.sertifikat !== "-") {
                        downloadButton = `
                            <div class="mt-3 pt-3 border-t border-slate-200">
                                <button id="dl-btn-${index}" onclick="triggerDownload(this, 'assets/uploads/${item.sertifikat}')" class="relative overflow-hidden w-full sm:w-auto inline-flex items-center justify-center space-x-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition shadow-sm cursor-pointer">
                                    <span id="dl-progress-${index}" class="absolute left-0 top-0 bottom-0 bg-sky-800 w-0 transition-all duration-150 z-0"></span>
                                    <span id="dl-text-${index}" class="relative z-10 inline-flex items-center space-x-1.5">
                                        <span>🏆</span>
                                        <span>Download Sertifikat</span>
                                    </span>
                                </button>
                            </div>
                        `;
                    } else {
                        downloadButton = `
                            <div class="mt-3 pt-3 border-t border-slate-200">
                                <span class="text-xs text-amber-600 font-medium">⚠️ Sertifikat belum diterbitkan / diunggah admin.</span>
                            </div>
                        `;
                    }

                    html += `
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 mb-3">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-slate-800">${item.nama_lengkap}</span>
                                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-full">${item.status}</span>
                            </div>
                            <div class="text-sm text-slate-600 space-y-1">
                                <p><strong>NIK:</strong> ${item.nik || '-'}</p>
                                <p><strong>Telepon:</strong> ${item.telepon}</p>
                                <p><strong>Program:</strong> ${item.program_pilihan}</p>
                                <p><strong>Tanggal Daftar:</strong> ${item.tanggal_daftar}</p>
                            </div>
                            ${downloadButton}
                        </div>
                    `;
                });
                resultContainer.innerHTML = html;
            } else {
                resultContainer.innerHTML = `<div class="text-center text-red-500 py-4 font-medium">${data.message}</div>`;
            }
        })
        .catch(error => {
            resultContainer.innerHTML = `<div class="text-center text-red-500 py-4">Terjadi kesalahan pada server.</div>`;
        });
}

function triggerDownload(buttonElement, fileUrl) {
    if (buttonElement.classList.contains('downloading') || buttonElement.classList.contains('completed')) {
        return;
    }
    buttonElement.classList.add('downloading');

    const btnId = buttonElement.id.replace('dl-btn-', '');
    const progressBar = document.getElementById(`dl-progress-${btnId}`);
    const textLabel = document.getElementById(`dl-text-${btnId}`);

    let progress = 0;
    const totalDuration = 1500;
    const intervalTime = 30;
    const increment = (100 / (totalDuration / intervalTime));

    const downloadTimer = setInterval(() => {
        progress += increment;
        
        if (progress >= 100) {
            progress = 100;
            clearInterval(downloadTimer);

            progressBar.style.width = '100%';
            progressBar.className = "absolute left-0 top-0 bottom-0 bg-sky-700 w-full transition-all duration-300 z-0";
            textLabel.innerHTML = `<span>✅</span><span>Unduhan Selesai!</span>`;
            buttonElement.classList.remove('downloading');
            buttonElement.classList.add('completed');

            const hiddenAnchor = document.createElement('a');
            hiddenAnchor.href = fileUrl;
            hiddenAnchor.download = '';
            document.body.appendChild(hiddenAnchor);
            hiddenAnchor.click();
            document.body.removeChild(hiddenAnchor);

            setTimeout(() => {
                progressBar.style.width = '0%';
                progressBar.className = "absolute left-0 top-0 bottom-0 bg-sky-800 w-0 transition-all duration-150 z-0";
                textLabel.innerHTML = `<span>🏆</span><span>Download Sertifikat</span>`;
                buttonElement.classList.remove('completed');
            }, 3000);

        } else {
            progressBar.style.width = progress + '%';
            const currentPercent = Math.floor(progress);
            textLabel.innerHTML = `<span>⏳</span><span>Mengunduh... (${currentPercent}%)</span>`;
        }
    }, intervalTime);
}
</script>

    <!-- ================= SECTION KEUNGGULAN ================= -->
    <section id="keunggulan" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 overflow-hidden bg-slate-50/50" data-aos="fade-up" data-aos-duration="1000">
        <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-down" data-aos-duration="800">
            <span class="text-indigo-600 font-semibold text-xs uppercase tracking-wider bg-indigo-50 px-3.5 py-1.5 rounded-full border border-indigo-100/50">Mengapa Memilih Kami</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 mt-3 mb-4">Keunggulan LPK Bhakti Kencana</h2>
            <p class="text-slate-600 text-sm sm:text-base">Komitmen kami dalam memberikan standar pelayanan dan pelatihan terbaik bagi masyarakat Cianjur dan sekitarnya.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8">
            <div data-aos="zoom-in" data-aos-delay="100" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 text-center hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 group">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300 shadow-inner">🛡️</div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Resmi & Tersertifikasi</h3>
                <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">Lembaga berbadan hukum resmi dengan instruktur berpengalaman dan kurikulum tervalidasi.</p>
            </div>

            <div data-aos="zoom-in" data-aos-delay="200" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 text-center hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 group">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300 shadow-inner">🤝</div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Terpercaya & Beretika</h3>
                <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">Pengasuh dan perawat melalui proses seleksi ketat dengan menjunjung tinggi nilai moral dan empati.</p>
            </div>

            <div data-aos="zoom-in" data-aos-delay="300" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 text-center hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 group">
                <div class="w-14 h-14 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-6 group-hover:bg-sky-600 group-hover:text-white transition-colors duration-300 shadow-inner">💬</div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Konsultasi 24 Jam</h3>
                <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">Dilengkapi Asisten AI CIKA dan Admin tanggap darurat yang siap membantu kapan saja.</p>
            </div>

            <div data-aos="zoom-in" data-aos-delay="400" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 text-center hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 group">
                <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-6 group-hover:bg-amber-600 group-hover:text-white transition-colors duration-300 shadow-inner">⭐</div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Garansi & Keamanan</h3>
                <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">Proses administrasi transparan, aman, serta memiliki pendampingan penuh bagi klien maupun pekerja.</p>
            </div>
        </div>
    </section>

<!-- ================= SECTION TESTIMONI ================= -->
<section id="testimoni" class="py-20 bg-slate-50 overflow-hidden" data-aos="fade-up" data-aos-duration="1000">
    <div class="text-center max-w-2xl mx-auto mb-14 px-4" data-aos="fade-down" data-aos-duration="800">
        <span class="text-indigo-600 font-semibold text-xs uppercase tracking-wider bg-indigo-50 px-3.5 py-1.5 rounded-full border border-indigo-100/50">Testimoni & Ulasan</span>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 mt-3 mb-4">Apa Kata Keluarga di Cianjur Tentang Kami?</h2>
        <p class="text-slate-600 text-sm sm:text-base mb-6">Kepuasan dan kenyamanan Anda adalah prioritas utama pelayanan LPK Bhakti Kencana.</p>
        
        <!-- Tombol untuk memunculkan Popup Form Testimoni Warga -->
        <button onclick="openPublicTestiModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-semibold px-6 py-3 rounded-xl shadow-md transition inline-flex items-center gap-2 cursor-pointer">
            <span>✍️ Tulis Testimoni Anda</span>
        </button>
    </div>

    <div class="relative w-full overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-16 sm:w-24 bg-gradient-to-r from-slate-50 to-transparent z-10 pointer-events-none"></div>
        <div class="absolute right-0 top-0 bottom-0 w-16 sm:w-24 bg-gradient-to-l from-slate-50 to-transparent z-10 pointer-events-none"></div>

        <div id="testimonial-track" class="flex space-x-6 py-4 px-4 w-max hover:[animation-play-state:paused]" style="animation: scrollMarquee 40s linear infinite;">
            <?php
            // Ambil data testimoni yang aktif dari database
            $query_testi = "SELECT * FROM testimoni WHERE status = 'Aktif' ORDER BY id DESC";
            $result_public_testi = $conn->query($query_testi);

            if ($result_public_testi && $result_public_testi->num_rows > 0):
                while ($t = $result_public_testi->fetch_assoc()):
                    // Cek foto (jika upload lokal ada di assets/uploads/, jika kosong pakai Unsplash default)
                    $foto_path = !empty($t['foto']) ? 'assets/uploads/' . $t['foto'] : 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop&crop=faces';
            ?>
                <!-- Item Testimoni Dinamis -->
                <div class="w-[320px] sm:w-[360px] bg-white p-7 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col justify-between flex-shrink-0">
                    <div>
                        <div class="flex text-amber-400 text-sm mb-3 space-x-0.5">
                            <?php for($i=0; $i < $t['rating']; $i++): ?><span>★</span><?php endfor; ?>
                        </div>
                        <p class="text-slate-600 text-sm leading-relaxed italic mb-6">"<?= htmlspecialchars($t['pesan']); ?>"</p>
                    </div>
                    <div class="flex items-center space-x-3 pt-4 border-t border-slate-100">
                        <img src="<?= htmlspecialchars($foto_path); ?>" alt="<?= htmlspecialchars($t['nama']); ?>" class="w-11 h-11 rounded-full object-cover border-2 border-indigo-100">
                        <div>
                            <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($t['nama']); ?></h4>
                            <span class="text-xs text-slate-500"><?= htmlspecialchars($t['pekerjaan_lokasi']); ?></span>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile; 
            else:
            ?>
                <p class="text-center text-slate-400 py-4">Belum ada ulasan testimoni yang tersedia.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ================= MODAL POPUP FORM TESTIMONI WARGA ================= -->
<div id="publicTestiModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-lg">Kirim Ulasan / Testimoni Anda</h3>
            <button onclick="closePublicTestiModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <!-- Form mengirim testimoni publik (bisa diarahkan ke file proses khusus atau langsung ke logika backend) -->
        <form action="proses_testimoni.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                <input type="text" name="nama" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: Ibu Siti Nurhaliza">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pekerjaan / Wilayah Domisili *</label>
                <input type="text" name="pekerjaan_lokasi" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: Warga Karangtengah, Cianjur">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Rating Kepuasan *</label>
                    <select name="rating" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="5">★★★★★ (5 Bintang - Sangat Baik)</option>
                        <option value="4">★★★★☆ (4 Bintang - Baik)</option>
                        <option value="3">★★★☆☆ (3 Bintang - Cukup)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Foto Profil / Wajah (Opsional)</label>
                    <input type="file" name="foto" accept="image/*" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pesan / Kesannya *</label>
                <textarea name="pesan" rows="3" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ceritakan pengalaman Anda menggunakan layanan LPK Bhakti Kencana..."></textarea>
            </div>

            <button type="submit" name="kirim_testimoni" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition text-sm cursor-pointer shadow-sm">Kirim Ulasan Sekarang</button>
        </form>
    </div>
</div>

<!-- SCRIPT PENDUKUNG MODAL TESTIMONI -->
<script>
    const publicModal = document.getElementById('publicTestiModal');

    function openPublicTestiModal() {
        if(publicModal) {
            publicModal.classList.remove('hidden');
        }
    }

    function closePublicTestiModal() {
        if(publicModal) {
            publicModal.classList.add('hidden');
        }
    }
</script>

    <style>
        @keyframes scrollMarquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        #testimonial-track { display: flex; width: max-content; }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const track = document.getElementById("testimonial-track");
            if (track) {
                track.innerHTML += track.innerHTML;
            }
        });
    </script>
    
    <!-- ================= PETA PERSEBARAN PELANGGAN ================= -->
    <section class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-aos="fade-up" data-aos-duration="1000">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
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
    </section>

    <!-- Leaflet JS untuk Peta -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const dashboardMap = L.map('dashboardPersebaranMap').setView([-6.8244, 107.1415], 10); // Default Cianjur
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(dashboardMap);

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

            if (bounds.length > 0) {
                dashboardMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
            }
        }
    });
    </script>

<!-- ================= SECTION KONTAK ================= -->
    <section id="kontak" class="py-20 bg-slate-900 text-white" data-aos="fade-up" data-aos-duration="1000">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-6">
                    <span class="text-indigo-400 font-semibold text-xs uppercase tracking-wider bg-indigo-500/10 px-3.5 py-1.5 rounded-full border border-indigo-500/20">Hubungi Kami</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-3 mb-4">Mari Terhubung dengan LPK Bhakti Kencana</h2>
                    <p class="text-slate-300 text-sm leading-relaxed mb-8">
                        Punya pertanyaan seputar penitipan anak, layanan home care lansia, atau jadwal pendaftaran pelatihan kerja? Silakan kunjungi kantor kami di Cianjur atau hubungi kontak resmi di bawah ini.
                    </p>
                    
                    <div class="space-y-4 text-sm text-slate-300">
                        <div class="flex items-start space-x-3.5">
                            <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center flex-shrink-0 text-indigo-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <div>
                                <span class="font-semibold text-white block">Alamat Kantor</span>
                                <span class="text-xs text-slate-400 block mb-1">Jl. Lkr. Selatan No.22, Bojong, Kec. Karangtengah, Kabupaten Cianjur, Jawa Barat 43281</span>
                                <!-- Link teks menggunakan URL presisi baru -->
                                <a href="https://www.google.com/maps/place/POLITEKNIK+BHAKTI+KENCANA/@-6.803601,107.1783545,763m/data=!3m1!1e3!4m6!3m5!1s0x2e6853d3c1dd7ec5:0xbac1a89e3f5507c0!8m2!3d-6.8039632!4d107.1788266!16s%2Fg%2F11z3cddw78?entry=ttu&g_ep=EgoyMDI2MTEwNC4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="text-xs text-indigo-400 font-medium hover:underline inline-flex items-center gap-1">
                                    📍 Buka di Google Maps &rarr;
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3.5">
                            <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center flex-shrink-0 text-emerald-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            </div>
                            <div>
                                <span class="font-semibold text-white block">WhatsApp Admin</span>
                                <a href="https://wa.me/6285199274269" target="_blank" class="text-xs text-indigo-400 hover:underline">0812-3456-7890</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sisi Kanan: Google Maps Card dengan Link Presisi Baru -->
                <div class="lg:col-span-6">
                    <div class="bg-slate-800 p-2 rounded-2xl border border-slate-700 shadow-xl overflow-hidden">
                        <!-- Seluruh kotak peta menggunakan URL presisi baru -->
                        <a href="https://www.google.com/maps/place/POLITEKNIK+BHAKTI+KENCANA/@-6.803601,107.1783545,763m/data=!3m1!1e3!4m6!3m5!1s0x2e6853d3c1dd7ec5:0xbac1a89e3f5507c0!8m2!3d-6.8039632!4d107.1788266!16s%2Fg%2F11z3cddw78?entry=ttu&g_ep=EgoyMDI2MTEwNC4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="block w-full h-[350px] sm:h-[400px] rounded-xl overflow-hidden relative group cursor-pointer">
                            
                            <!-- Iframe Peta -->
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3960.835408990714!2d107.1788266!3d-6.8039632!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e6853d3c1dd7ec5%3A0xbac1a89e3f5507c0!2sPOLITEKNIK+BHAKTI+KENCANA!5e0!3m2!1sid!2sid!4v1600000000000!5m2!1sid!2sid" 
                                width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                class="w-full h-full grayscale group-hover:grayscale-0 transition duration-500 pointer-events-none">
                            </iframe>

                            <!-- Tombol Aksi Timbul -->
                            <div class="absolute inset-0 bg-slate-950/20 group-hover:bg-transparent transition flex items-end justify-center pb-4">
                                <span class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-lg transition inline-flex items-center gap-1.5 backdrop-blur-sm">
                                    📍 Klik untuk Buka di Google Maps
                                </span>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ================= WIDGET ASISTEN AI ================= -->
    <div class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 flex items-center space-x-3">
        <div class="bg-white px-4 py-2 rounded-2xl shadow-lg border border-slate-200 text-xs font-semibold text-slate-700 hidden sm:block animate-bounce">
            💬 Tanya Asisten AI 24 Jam
        </div>

        <button id="ai-chat-btn" class="bg-indigo-600 hover:bg-indigo-700 w-14 h-14 sm:w-16 sm:h-16 rounded-full shadow-2xl flex items-center justify-center transition hover:scale-110 overflow-hidden border-2 border-white focus:outline-none relative flex-shrink-0 cursor-pointer" aria-label="Buka Chat AI">
            <img src="assets/img/ai.png" alt="Asisten AI" class="w-full h-full object-cover">
        </button>

        <div id="ai-chat-popup" class="hidden absolute bottom-20 right-0 w-[92vw] sm:w-[440px] max-w-[480px] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col h-[75vh] sm:h-[580px] transition-all">
            <div class="bg-indigo-900 text-white p-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-700 overflow-hidden border border-indigo-400 flex-shrink-0">
                        <img src="assets/img/ai.png" alt="AI" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h4 class="font-bold text-sm">Cika Asisten Ai</h4>
                        <span class="text-xs text-emerald-400 flex items-center gap-1">● Online Siap Membantu</span>
                    </div>
                </div>
                <button id="ai-close-btn" class="text-slate-300 hover:text-white p-1 rounded-lg transition cursor-pointer">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div id="chat-messages" class="flex-1 p-4 overflow-y-auto space-y-4 bg-slate-50 text-sm">
                <div class="flex items-start space-x-2">
                    <div class="bg-indigo-600 text-white p-3.5 rounded-2xl rounded-tl-none max-w-[90%] shadow-sm leading-relaxed text-xs sm:text-sm">
                        Halo! Selamat datang di LPK Bhakti Kencana Cianjur. Ada yang bisa saya bantu? Ketik nama pekerja atau tanyakan jadwal yang tersedia 😊
                    </div>
                </div>
            </div>

            <div class="p-3 bg-white border-t border-slate-100 flex items-center space-x-2 flex-shrink-0">
                <input type="text" id="chat-input" placeholder="Ketik nama pekerja / tanyakan jadwal..." class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button id="chat-send" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl font-medium text-sm transition flex-shrink-0 cursor-pointer">Kirim</button>
            </div>
        </div>
    </div>

    <!-- Skrip Chat AI -->
    <script>
        const aiChatBtn = document.getElementById('ai-chat-btn');
        const aiChatPopup = document.getElementById('ai-chat-popup');
        const aiCloseBtn = document.getElementById('ai-close-btn');
        const chatInput = document.getElementById('chat-input');
        const chatSend = document.getElementById('chat-send');
        const chatMessages = document.getElementById('chat-messages');

        const ADMIN_WHATSAPP_NUMBER = "6285199274269"; 
        let chatHistory = JSON.parse(localStorage.getItem('cika_chat_history')) || [];

        function loadSavedMessages() {
            chatHistory.forEach(chat => {
                const isUser = chat.role === 'user';
                const bubble = document.createElement('div');
                bubble.className = isUser ? 'flex justify-end' : 'flex items-start space-x-2';
                bubble.innerHTML = isUser 
                    ? `<div class="bg-emerald-600 text-white p-3 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm leading-relaxed text-xs sm:text-sm">${chat.message}</div>`
                    : `<div class="bg-indigo-600 text-white p-3.5 rounded-2xl rounded-tl-none max-w-[95%] shadow-sm leading-relaxed text-xs sm:text-sm">${chat.message}</div>`;
                chatMessages.appendChild(bubble);
            });
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        loadSavedMessages();

        aiChatBtn.addEventListener('click', () => {
            aiChatPopup.classList.toggle('hidden');
            if (!aiChatPopup.classList.contains('hidden')) chatInput.focus();
        });

        aiCloseBtn.addEventListener('click', () => { aiChatPopup.classList.add('hidden'); });

        function renderScheduleTable(pekerjaArray, filterType) {
            if (!pekerjaArray || pekerjaArray.length === 0) {
                return `<div class="mt-2 p-3 bg-amber-50 text-amber-800 text-xs rounded-xl border border-amber-200">⚠️ Maaf, data pekerja tidak ditemukan.</div>`;
            }

            let titleBadge = "📋 Daftar Pekerja & Jadwal";
            if (filterType === 'tersedia') titleBadge = "✨ Pekerja yang SEDANG TERSEDIA";
            if (filterType === 'bertugas') titleBadge = "📌 Pekerja yang SEDANG BERTUGAS";

            let tableHtml = `
                <div class="mt-3 bg-white border border-slate-200 rounded-xl shadow-md overflow-hidden text-xs text-slate-800">
                    <div class="bg-indigo-900 text-white px-3.5 py-2.5 font-bold flex justify-between items-center">
                        <span>${titleBadge}</span>
                        <span class="bg-indigo-700 text-indigo-100 px-2.5 py-0.5 rounded-full text-[10px]">${pekerjaArray.length} Orang</span>
                    </div>
                    <div class="overflow-x-auto max-h-60">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0 border-b border-slate-200 z-10">
                                <tr>
                                    <th class="p-3 font-semibold">Nama</th>
                                    <th class="p-3 font-semibold">Spesialisasi</th>
                                    <th class="p-3 font-semibold">Status</th>
                                    <th class="p-3 font-semibold text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
            `;

            pekerjaArray.forEach(p => {
                const isAvailable = p.status_pekerja.toLowerCase().includes('tersedia');
                const badgeClass = isAvailable ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800';
                const textMessage = `Halo Admin LPK Bhakti Kencana, saya ingin booking pekerja atas nama *${p.nama_lengkap}* (${p.spesialisasi}).`;
                const waLink = `https://wa.me/${ADMIN_WHATSAPP_NUMBER}?text=${encodeURIComponent(textMessage)}`;

                tableHtml += `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-3 font-bold text-slate-900">${p.nama_lengkap}</td>
                        <td class="p-3 text-slate-700">${p.spesialisasi}</td>
                        <td class="p-3"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold ${badgeClass}">${p.status_pekerja}</span></td>
                        <td class="p-3 text-center">
                            <a href="${waLink}" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg font-semibold text-[11px] inline-flex items-center gap-1 transition shadow-sm">💬 Booking WA</a>
                        </td>
                    </tr>
                `;
            });

            tableHtml += `</tbody></table></div></div>`;
            return tableHtml;
        }

        async function handleSendMessage() {
            const userText = chatInput.value.trim();
            if (!userText) return;

            const userBubble = document.createElement('div');
            userBubble.className = 'flex justify-end';
            userBubble.innerHTML = `<div class="bg-emerald-600 text-white p-3 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm leading-relaxed text-xs sm:text-sm">${userText}</div>`;
            chatMessages.appendChild(userBubble);
            
            chatInput.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;

            chatHistory.push({ role: 'user', message: userText });
            localStorage.setItem('cika_chat_history', JSON.stringify(chatHistory));

            const loadingBubble = document.createElement('div');
            loadingBubble.id = 'loading-ai';
            loadingBubble.className = 'flex items-start space-x-2';
            loadingBubble.innerHTML = `<div class="bg-slate-200 text-slate-600 p-3 rounded-2xl rounded-tl-none max-w-[85%] text-xs italic animate-pulse">Cika sedang menyiapkan informasi...</div>`;
            chatMessages.appendChild(loadingBubble);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                const response = await fetch('admin/gemini-ai.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: userText, history: chatHistory })
                });

                const data = await response.json();
                const loadingElem = document.getElementById('loading-ai');
                if (loadingElem) loadingElem.remove();

                const aiBubble = document.createElement('div');
                aiBubble.className = 'flex items-start space-x-2';
                
                let contentHtml = `<div class="bg-indigo-600 text-white p-3.5 rounded-2xl rounded-tl-none max-w-[95%] shadow-sm leading-relaxed text-xs sm:text-sm">${data.reply}</div>`;
                if (data.show_table && data.pekerja_data) {
                    contentHtml = `
                        <div class="bg-indigo-600 text-white p-3.5 rounded-2xl rounded-tl-none max-w-[98%] shadow-sm leading-relaxed text-xs sm:text-sm">
                            <div class="mb-2">${data.reply}</div>
                            ${renderScheduleTable(data.pekerja_data, data.filter_type)}
                        </div>
                    `;
                }

                aiBubble.innerHTML = contentHtml;
                chatMessages.appendChild(aiBubble);
                chatMessages.scrollTop = chatMessages.scrollHeight;

                chatHistory.push({ role: 'assistant', message: data.reply });
                localStorage.setItem('cika_chat_history', JSON.stringify(chatHistory));

            } catch (error) {
                const loadingElem = document.getElementById('loading-ai');
                if (loadingElem) loadingElem.remove();
                
                const errBubble = document.createElement('div');
                errBubble.className = 'flex items-start space-x-2';
                errBubble.innerHTML = `<div class="bg-rose-600 text-white p-3 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm text-xs">Maaf, terjadi kendala koneksi. Silakan coba lagi.</div>`;
                chatMessages.appendChild(errBubble);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        chatSend.addEventListener('click', handleSendMessage);
        chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSendMessage(); });
    </script>

    <!-- Inisialisasi AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: false,      
            mirror: true,     
            duration: 900,    
            offset: 50,       
        });
    </script>

    <!-- ================= FOOTER ================= -->
    <footer class="bg-slate-900 text-slate-400 py-12 text-center text-sm border-t border-slate-800">
        <p>&copy; 2026/2027 LPK Politeknik Bhakti Kencana. All rights reserved.</p>
    </footer>

</body>
</html>