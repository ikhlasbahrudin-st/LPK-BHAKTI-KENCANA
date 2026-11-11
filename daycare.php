<?php
include 'config.php';

$daftar_pekerja = [];
// Memfilter pekerja yang spesialisasinya 'daycare'
$query = mysqli_query($conn, "SELECT * FROM pekerja WHERE spesialisasi = 'daycare' ORDER BY id ASC");
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $daftar_pekerja[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Daycare (Penitipan Anak) - LPK Bhakti Kencana Cianjur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 font-sans overflow-x-hidden">

    <!-- Header Navigasi Responsif -->
    <header class="bg-white shadow-sm sticky top-0 z-50 w-full">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="index.php" class="font-bold text-xs sm:text-sm md:text-base text-sky-600 flex items-center space-x-1 hover:text-sky-800 transition">
                <span>←</span>
                <span>Kembali ke Beranda</span>
            </a>
            <span class="text-xs md:text-sm font-medium text-slate-500 truncate max-w-[140px] sm:max-w-none">Daycare Profesional</span>
        </div>
    </header>

    <!-- Banner Layanan dengan Background yang Proporsional di Mobile -->
    <section class="relative text-white py-14 md:py-20 px-4 text-center overflow-hidden bg-cover bg-center w-full" style="background-image: url('assets/layanan/homecare-anak.jpg');">
        
        <!-- Overlay Gelap Transparan -->
        <div class="absolute inset-0 bg-sky-950/80 backdrop-blur-[2px]"></div>

        <!-- Konten Banner -->
        <div class="max-w-4xl mx-auto relative z-10" data-aos="fade-down">
            <span class="inline-block bg-sky-500/30 text-sky-200 border border-sky-400/30 px-3 py-1 rounded-full text-xs md:text-sm font-medium">Layanan Penitipan Anak</span>
            <h1 class="text-2xl sm:text-3xl md:text-5xl font-bold mt-3 mb-3 leading-snug">Daycare (Penitipan Anak) Yang Aman & Edukatif ✨</h1>
            <p class="text-sky-100 text-sm md:text-lg max-w-2xl mx-auto">Solusi terpercaya bagi orang tua bekerja untuk memberikan pengasuhan terbaik, stimulasi tumbuh kembang, serta lingkungan yang higienis.</p>
        </div>
    </section>

    <!-- Konten Detail & Keunggulan -->
    <main class="max-w-6xl mx-auto px-4 py-10 md:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 md:gap-12">
            
            <!-- Deskripsi Utama & Slider Foto -->
            <div class="lg:col-span-2 space-y-8" data-aos="fade-right">
                
                <!-- Card Tentang Program dengan Slider Gambar -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-xl md:text-2xl font-bold text-slate-900 mb-6">Tentang Program Daycare Kami</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                        <!-- Kolom Teks -->
                        <div class="space-y-4">
                            <p class="text-slate-600 leading-relaxed text-sm md:text-base">
                                Daycare LPK Bhakti Kencana Cianjur dirancang khusus menyerupai rumah kedua bagi buah hati Anda. Kami mengutamakan keamanan, kenyamanan, serta stimulasi kecerdasan anak melalui metode bermain sambil belajar yang menyenangkan.
                            </p>
                            <p class="text-slate-600 leading-relaxed text-sm md:text-base">
                                Setiap anak ditangani langsung oleh pengasuh dan pendidik profesional bersertifikat yang memiliki empati tinggi dan terlatih dalam penanganan dini kesehatan anak.
                            </p>
                        </div>

                        <!-- Kolom Slider Foto -->
                        <div class="relative w-full h-52 sm:h-64 rounded-xl overflow-hidden shadow-md group">
                            <!-- Slide 1 -->
                            <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out opacity-100 slider-item">
                                <img src="assets/img/daycare-1.jpg" alt="Kegiatan Daycare 1" class="w-full h-full object-cover">
                            </div>
                            <!-- Slide 2 -->
                            <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out opacity-0 slider-item">
                                <img src="assets/img/daycare-2.jpg" alt="Kegiatan Daycare 2" class="w-full h-full object-cover">
                            </div>
                            <!-- Slide 3 -->
                            <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out opacity-0 slider-item">
                                <img src="assets/img/daycare-3.jpg" alt="Kegiatan Daycare 3" class="w-full h-full object-cover">
                            </div>
                            
                            <!-- Indikator Titik (Dots) -->
                            <div class="absolute bottom-3 left-1/2 transform -translate-x-1/2 flex space-x-2 z-10">
                                <span class="w-2 h-2 rounded-full bg-white/60 slider-dot transition-all"></span>
                                <span class="w-2 h-2 rounded-full bg-white/60 slider-dot transition-all"></span>
                                <span class="w-2 h-2 rounded-full bg-white/60 slider-dot transition-all"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keunggulan Layanan -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200">
                    <h3 class="text-lg md:text-xl font-bold text-slate-900 mb-6">Keunggulan Layanan Daycare</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">🛡️</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Keamanan & Higienitas</h4>
                                <p class="text-xs md:text-sm text-slate-600">Area bermain steril, mainan rutin dibersihkan, dan pengawasan CCTV 24 jam.</p>
                            </div>
                        </div>
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">🎨</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Kurikulum Stimulasi</h4>
                                <p class="text-xs md:text-sm text-slate-600">Kegiatan motorik halus/kasar, seni, bahasa, dan pembentukan karakter akhlak mulia.</p>
                            </div>
                        </div>
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">🍲</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Menu Sehat & Bergizi</h4>
                                <p class="text-xs md:text-sm text-slate-600">Makanan dan selingan (snack) dirancang higienis sesuai standar nutrisi tumbuh kembang anak.</p>
                            </div>
                        </div>
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">📱</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Laporan Harian</h4>
                                <p class="text-xs md:text-sm text-slate-600">Orang tua menerima laporan aktivitas, jadwal makan, tidur, dan tumbuh kembang anak secara berkala.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Kontak / Pendaftaran -->
            <div class="space-y-6" data-aos="fade-left">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-200 lg:sticky lg:top-24">
                    <h3 class="text-base md:text-lg font-bold text-slate-900 mb-2">Tertarik dengan Daycare Kami?</h3>
                    <p class="text-slate-600 text-xs md:text-sm mb-6">Konsultasikan kebutuhan penitipan anak Anda sekarang atau jadwalkan kunjungan langsung ke lokasi.</p>
                    
                    <a href="https://wa.me/6285199274269?text=Halo,%20saya%20tertarik%20untuk%20mendaftarkan%20anak%20ke%20layanan%20Daycare" target="_blank" class="block text-center bg-sky-600 hover:bg-sky-500 text-white font-semibold py-3 rounded-xl shadow-lg transition mb-3 text-sm">Tanya & Daftar via WhatsApp</a>
                    
                    <a href="index.php#layanan" class="block text-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-2.5 rounded-xl transition text-xs md:text-sm">Lihat Layanan Lainnya</a>
                </div>
            </div>

        </div>

        <!-- Daftar Talenta Daycare (Tombol Lihat Detail & Popup Lengkap) -->
        <section class="mt-16" data-aos="fade-up">
            <div class="mb-8 text-center">
                <h3 class="text-2xl md:text-3xl font-bold text-slate-900">Daftar Tenaga Penitipan Anak Profesional</h3>
                <p class="text-slate-600 text-sm mt-2">Pilih pengasuh bersertifikat yang siap mendampingi tumbuh kembang buah hati Anda.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                <?php if (count($daftar_pekerja) > 0): ?>
                    <?php foreach ($daftar_pekerja as $row): ?>
                    <!-- Kartu menggunakan data-attributes agar aman dan presisi -->
                    <div class="relative w-full h-80 rounded-2xl overflow-hidden shadow-md border border-slate-200 flex flex-col justify-between p-4 group cursor-pointer pekerja-card"
                         data-foto="<?= htmlspecialchars($row['foto']); ?>"
                         data-nama="<?= htmlspecialchars($row['nama_lengkap']); ?>"
                         data-spesialisasi="<?= htmlspecialchars($row['spesialisasi']); ?>"
                         data-status="<?= htmlspecialchars($row['status_pekerja']); ?>"
                         data-mulai="<?= htmlspecialchars($row['tanggal_mulai'] ?? ''); ?>"
                         data-selesai="<?= htmlspecialchars($row['tanggal_selesai'] ?? ''); ?>"
                         data-jam="<?= htmlspecialchars($row['jam_kerja']); ?>">
                        
                        <!-- 1. Foto Full Screen di Belakang Kotak -->
                        <div class="absolute inset-0 z-0">
                            <img src="<?= htmlspecialchars($row['foto']); ?>" alt="<?= htmlspecialchars($row['nama_lengkap']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>

                        <!-- 2. Lapisan Gradasi Gelap (Overlay) -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-slate-950/20 z-10"></div>

                        <!-- 3. Lapisan Atas: Spesialisasi & Status -->
                        <div class="relative z-20 flex justify-between items-start w-full gap-2">
                            <span class="bg-sky-600/80 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider">
                                <?= htmlspecialchars($row['spesialisasi']); ?>
                            </span>

                            <?php if ($row['status_pekerja'] == 'Tersedia'): ?>
                                <span class="bg-emerald-500/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm whitespace-nowrap">
                                    Tersedia
                                </span>
                            <?php else: ?>
                                <span class="bg-amber-500/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm whitespace-nowrap">
                                    Bertugas
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- 4. Lapisan Bawah: Nama & Tombol Lihat Detail -->
                        <div class="relative z-20 text-white space-y-3 w-full">
                            <h4 class="font-bold text-base leading-tight drop-shadow-md line-clamp-2">
                                <?= htmlspecialchars($row['nama_lengkap']); ?>
                            </h4>

                            <button type="button" class="btn-detail block w-full bg-sky-600 hover:bg-sky-500 text-white text-xs py-2.5 rounded-xl transition font-semibold text-center shadow-lg">
                                Lihat Detail
                            </button>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-8 text-slate-500 bg-white rounded-2xl border border-slate-200">
                        Belum ada data tenaga daycare yang tersedia di database.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Modal Pop-up (Lightbox) untuk Detail Lengkap -->
    <div id="photoModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeModal()">
        <div class="bg-white rounded-3xl overflow-hidden max-w-sm w-full shadow-2xl relative transform transition-all" onclick="event.stopPropagation()">
            <!-- Tombol Close -->
            <button onclick="closeModal()" class="absolute top-3 right-3 z-30 bg-black/50 hover:bg-black/70 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition">
                ✕
            </button>
            
            <!-- Foto Besar -->
            <div class="relative w-full h-56 bg-slate-100">
                <img id="modalImg" src="" alt="Foto Pekerja" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent"></div>
                <div id="modalStatus" class="absolute top-3 left-3 text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm"></div>
            </div>

            <!-- Informasi Detail di Modal -->
            <div class="p-5 space-y-4 bg-white">
                <div class="text-center">
                    <span id="modalSpesialisasi" class="inline-block bg-sky-50 text-sky-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider mb-1"></span>
                    <h3 id="modalNama" class="text-xl font-bold text-slate-900 leading-tight"></h3>
                </div>

                <!-- Kotak Informasi Jadwal & Jam Kerja -->
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-medium">📅 Status Pekerja:</span>
                        <span id="modalStatusText" class="font-semibold text-slate-800 text-right"></span>
                    </div>
                    <div id="modalKontrakWrapper" class="flex justify-between items-center">
                        <span class="text-slate-500 font-medium">⏳ Periode Kontrak:</span>
                        <span id="modalTanggal" class="font-semibold text-slate-800 text-right"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-medium">⏰ Jam Kerja:</span>
                        <span id="modalJam" class="font-semibold text-slate-800 text-right"></span>
                    </div>
                </div>
                
                <a id="modalWaBtn" href="#" target="_blank" class="block w-full bg-emerald-600 hover:bg-emerald-500 text-white text-xs py-3 rounded-xl transition font-semibold text-center shadow-lg">
                    Hubungi / Booking via WhatsApp
                </a>
            </div>
        </div>
    </div>

    <footer class="bg-slate-900 text-slate-400 py-8 text-center text-xs md:text-sm mt-16">
        <p>&copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.</p>
    </footer>

    <!-- Skrip Slider, AOS, & Modal Pop-up Detail -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // Script Slider Foto Otomatis untuk Daycare
        let currentIndex = 0;
        const slides = document.querySelectorAll('.slider-item');
        const dots = document.querySelectorAll('.slider-dot');

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('opacity-100', i === index);
                slide.classList.toggle('opacity-0', i !== index);
            });
            dots.forEach((dot, i) => {
                dot.classList.toggle('bg-white', i === index);
                dot.classList.toggle('bg-white/60', i !== index);
            });
        }

        function nextSlide() {
            currentIndex = (currentIndex + 1) % slides.length;
            showSlide(currentIndex);
        }

        // Berganti slide setiap 3.5 detik
        if (slides.length > 0) {
            setInterval(nextSlide, 3500);
        }

        // Script Interaktif Modal Detail via Data Attributes
        document.addEventListener("DOMContentLoaded", function () {
            const cards = document.querySelectorAll('.pekerja-card');

            cards.forEach(card => {
                card.addEventListener('click', function () {
                    const foto = this.getAttribute('data-foto');
                    const nama = this.getAttribute('data-nama');
                    const spesialisasi = this.getAttribute('data-spesialisasi');
                    const status = this.getAttribute('data-status');
                    const tglMulai = this.getAttribute('data-mulai');
                    const tglSelesai = this.getAttribute('data-selesai');
                    const jamKerja = this.getAttribute('data-jam');

                    // Masukkan data ke elemen dalam modal
                    document.getElementById('modalImg').src = foto;
                    document.getElementById('modalNama').innerText = nama;
                    document.getElementById('modalSpesialisasi').innerText = spesialisasi;

                    // Format Tanggal Ketersediaan
                    let formatTanggal = "Fleksibel / Hubungi Admin";
                    if (tglMulai && tglSelesai && tglMulai !== "0000-00-00" && tglSelesai !== "0000-00-00" && tglMulai !== "") {
                        let d1 = new Date(tglMulai).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                        let d2 = new Date(tglSelesai).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                        formatTanggal = `${d1} s/d ${d2}`;
                    }
                    document.getElementById('modalTanggal').innerText = formatTanggal;
                    document.getElementById('modalJam').innerText = (jamKerja && jamKerja !== "") ? jamKerja : "Menyesuaikan";

                    // Badge Status Ketersediaan di Modal
                    const statusEl = document.getElementById('modalStatus');
                    const statusTextEl = document.getElementById('modalStatusText');
                    const kontrakWrapperEl = document.getElementById('modalKontrakWrapper');

                    if (status === 'Tersedia') {
                        statusEl.className = 'absolute top-3 left-3 bg-emerald-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm';
                        statusEl.innerText = 'Tersedia';
                        statusTextEl.innerText = 'Tersedia';
                        kontrakWrapperEl.style.display = 'none';
                    } else {
                        statusEl.className = 'absolute top-3 left-3 bg-amber-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm';
                        statusEl.innerText = 'Sedang Bertugas';
                        statusTextEl.innerText = 'Sedang Bertugas';
                        kontrakWrapperEl.style.display = 'flex';
                    }

                    // Tombol WhatsApp otomatis membawa nama pekerja
                    document.getElementById('modalWaBtn').href = `https://wa.me/6285199274269?text=Halo,%20saya%20tertarik%20dengan%20${encodeURIComponent(nama)}%20untuk%20layanan%20Daycare`;

                    // Tampilkan Modal
                    document.getElementById('photoModal').classList.remove('hidden');
                });
            });
        });

        function closeModal() {
            document.getElementById('photoModal').classList.add('hidden');
        }
    </script>
</body>
</html>