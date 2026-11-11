<?php
include 'config.php';

$daftar_pekerja = [];
$query = mysqli_query($conn, "SELECT * FROM pekerja WHERE spesialisasi = 'bebycare' ORDER BY id ASC");
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $daftar_pekerja[] =$row;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Care Anak (Babysitter) - LPK Bhakti Kencana Cianjur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 font-sans overflow-x-hidden">

    <!-- Header Navigasi -->
    <header class="bg-white shadow-sm sticky top-0 z-50 w-full">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="index.php" class="font-bold text-xs sm:text-sm md:text-base text-indigo-600 flex items-center space-x-1.5 hover:text-indigo-800 transition">
                <span>←</span>
                <span>Kembali ke Beranda</span>
            </a>
            <span class="text-xs md:text-sm font-medium text-slate-500 truncate max-w-[140px] sm:max-w-none">Babysitter & Nanny Profesional</span>
        </div>
    </header>

    <!-- Banner Layanan -->
    <section class="relative text-white py-14 md:py-20 px-4 text-center overflow-hidden bg-cover bg-center w-full" style="background-image: url('assets/layanan/homecare-lansia.jpg');">
        <div class="absolute inset-0 bg-indigo-950/80 backdrop-blur-[2px]"></div>
        <div class="max-w-4xl mx-auto relative z-10" data-aos="fade-down">
            <span class="inline-block bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 px-3 py-1 rounded-full text-xs md:text-sm font-medium">Layanan Home Care Anak</span>
            <h1 class="text-2xl sm:text-3xl md:text-5xl font-bold mt-3 mb-3 leading-snug">Babysitter & Nanny Profesional di Rumah Anda ✨</h1>
            <p class="text-indigo-100 text-sm md:text-lg max-w-2xl mx-auto">Pendampingan pengasuhan privat harian, bulanan, maupun sistem menginap (live-in) yang aman, telaten, dan berakhlak mulia.</p>
        </div>
    </section>

    <!-- Konten Detail & Keunggulan -->
    <main class="max-w-6xl mx-auto px-4 py-10 md:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 md:gap-12">
            
            <!-- Deskripsi Utama & Slider Foto -->
            <div class="lg:col-span-2 space-y-8" data-aos="fade-right">
                
                <!-- Card Tentang Program -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-xl md:text-2xl font-bold text-slate-900 mb-6">Tentang Program Babysitter / Nanny</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                        <div class="space-y-4">
                            <p class="text-slate-600 leading-relaxed text-sm md:text-base">
                                Layanan Home Care Anak dari LPK Bhakti Kencana Cianjur menghadirkan pengasuh privat berpengalaman yang siap merawat buah hati tercinta langsung di rumah Anda. Kami memahami betapa pentingnya rasa aman bagi orang tua.
                            </p>
                            <p class="text-slate-600 leading-relaxed text-sm md:text-base">
                                Setiap pengasuh telah melalui seleksi ketat serta pelatihan profesional bersertifikat, mulai dari perawatan bayi, penyiapan MPASI, hingga stimulasi edukasi dini.
                            </p>
                        </div>

                        <!-- Kolom Slider Foto -->
                        <div class="relative w-full h-52 sm:h-64 rounded-xl overflow-hidden shadow-md group">
                            <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out opacity-100 slider-item">
                                <img src="assets/img/babysitter-1.jpg" alt="Kegiatan Babysitter 1" class="w-full h-full object-cover">
                            </div>
                            <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out opacity-0 slider-item">
                                <img src="assets/img/babysitter-2.jpg" alt="Kegiatan Babysitter 2" class="w-full h-full object-cover">
                            </div>
                            <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out opacity-0 slider-item">
                                <img src="assets/img/babysitter-3.jpg" alt="Kegiatan Babysitter 3" class="w-full h-full object-cover">
                            </div>
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
                    <h3 class="text-lg md:text-xl font-bold text-slate-900 mb-6">Keunggulan Layanan Babysitter Kami</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">📜</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Bersertifikat Resmi</h4>
                                <p class="text-xs md:text-sm text-slate-600">Lulusan pelatihan resmi LPK dengan kompetensi pengasuhan anak.</p>
                            </div>
                        </div>
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">🧸</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Stimulasi Tumbuh Kembang</h4>
                                <p class="text-xs md:text-sm text-slate-600">Aktif mendampingi bermain edukatif dan melatih motorik anak.</p>
                            </div>
                        </div>
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">🍼</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Steril & Higienis</h4>
                                <p class="text-xs md:text-sm text-slate-600">Terampil menjaga kebersihan perlengkapan makan dan susu.</p>
                            </div>
                        </div>
                        <div class="flex space-x-4">
                            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold flex-shrink-0">🤝</div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm md:text-base">Jujur & Beretika</h4>
                                <p class="text-xs md:text-sm text-slate-600">Menjunjung tinggi nilai moral yang baik di lingkungan keluarga.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Sidebar Kontak -->
            <div class="space-y-6" data-aos="fade-left">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-200 lg:sticky lg:top-24">
                    <h3 class="text-base md:text-lg font-bold text-slate-900 mb-2">Butuh Babysitter Terpercaya?</h3>
                    <p class="text-slate-600 text-xs md:text-sm mb-6">Konsultasikan kebutuhan pengasuh anak Anda atau daftarkan diri melalui sistem online kami.</p>
                    <a href="https://wa.me/6285199274269?text=Halo,%20saya%20tertarik%20dengan%20layanan%20Home%20Care%20Anak" target="_blank" class="block text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-lg transition mb-3 text-sm">Tanya Admin via WhatsApp</a>
                    <a href="index.php#layanan" class="block text-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-2.5 rounded-xl transition text-xs md:text-sm">Lihat Layanan Lainnya</a>
                </div>
            </div>

        </div>

        <!-- Daftar Talenta Bebycare -->
        <section class="mt-16" data-aos="fade-up">
            <div class="mb-8 text-center">
                <h3 class="text-2xl md:text-3xl font-bold text-slate-900">Daftar Babysitter & Nanny Profesional</h3>
                <p class="text-slate-600 text-sm mt-2">Pilih pengasuh anak berpengalaman dan bersertifikat yang siap bertugas. (Klik foto untuk memperbesar)</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                <?php if (count($daftar_pekerja) > 0): ?>
                    <?php foreach ($daftar_pekerja as$row): ?>
                    <div class="relative w-full h-80 rounded-2xl overflow-hidden shadow-md border border-slate-200 flex flex-col justify-between p-4 group cursor-pointer" onclick="openModal('<?= htmlspecialchars($row['foto']); ?>', '<?= htmlspecialchars($row['nama_lengkap']); ?>', '<?= htmlspecialchars($row['spesialisasi']); ?>', '<?= htmlspecialchars($row['status_pekerja']); ?>')">
                        
                        <!-- Foto Full Screen Background -->
                        <div class="absolute inset-0 z-0">
                            <img src="<?= htmlspecialchars($row['foto']); ?>" alt="<?= htmlspecialchars($row['nama_lengkap']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>

                        <!-- Overlay Gradasi Gelap -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-slate-950/20 z-10"></div>

                        <!-- Lapisan Atas -->
                        <div class="relative z-20 flex justify-between items-start w-full">
                            <span class="bg-indigo-600/80 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider">
                                <?= htmlspecialchars($row['spesialisasi']); ?>
                            </span>

                            <?php if ($row['status_pekerja'] == 'Tersedia'): ?>
                                <span class="bg-emerald-500/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm">
                                    Tersedia
                                </span>
                            <?php else: ?>
                                <span class="bg-amber-500/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm">
                                    Bertugas
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Lapisan Bawah -->
                        <div class="relative z-20 text-white space-y-3 w-full" onclick="event.stopPropagation()">
                            <h4 class="font-bold text-base leading-tight drop-shadow-md line-clamp-2">
                                <?= htmlspecialchars($row['nama_lengkap']); ?>
                            </h4>

                            <a href="https://wa.me/6285199274269?text=Halo,%20saya%20tertarik%20dengan%20<?= urlencode($row['nama_lengkap']); ?>" target="_blank" class="block w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs py-2.5 rounded-xl transition font-semibold text-center shadow-lg">
                                Pilih / Booking
                            </a>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-8 text-slate-500 bg-white rounded-2xl border border-slate-200">
                        Belum ada data babysitter (bebycare) yang tersedia di database.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Modal Pop-up (Lightbox) untuk Preview Foto -->
    <div id="photoModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeModal()">
        <div class="bg-white rounded-3xl overflow-hidden max-w-sm w-full shadow-2xl relative transform transition-all" onclick="event.stopPropagation()">
            <!-- Tombol Close -->
            <button onclick="closeModal()" class="absolute top-3 right-3 z-30 bg-black/50 hover:bg-black/70 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition">
                ✕
            </button>
            
            <!-- Foto Besar -->
            <div class="relative w-full h-80 bg-slate-100">
                <img id="modalImg" src="" alt="Foto Pekerja" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent"></div>
                <div id="modalStatus" class="absolute top-3 left-3 text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm"></div>
            </div>

            <!-- Informasi Detail di Modal -->
            <div class="p-5 text-center space-y-3 bg-white">
                <span id="modalSpesialisasi" class="inline-block bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider"></span>
                <h3 id="modalNama" class="text-xl font-bold text-slate-900 leading-tight"></h3>
                
                <a id="modalWaBtn" href="#" target="_blank" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm py-3 rounded-xl transition font-semibold text-center shadow-lg">
                    Booking via WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-8 text-center text-xs md:text-sm">
        <p>&copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.</p>
    </footer>

    <!-- Skrip Slider, AOS, & Modal Pop-up -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // Script Slider Foto Otomatis
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

        setInterval(nextSlide, 3500);

        // Fungsi Modal Pop-up (Lightbox)
        function openModal(fotoUrl, nama, spesialisasi, status) {
            document.getElementById('modalImg').src = fotoUrl;
            document.getElementById('modalNama').innerText = nama;
            document.getElementById('modalSpesialisasi').innerText = spesialisasi;
            
            const statusEl = document.getElementById('modalStatus');
            if (status === 'Tersedia') {
                statusEl.className = 'absolute top-3 left-3 bg-emerald-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm';
                statusEl.innerText = 'Tersedia';
            } else {
                statusEl.className = 'absolute top-3 left-3 bg-amber-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-sm';
                statusEl.innerText = 'Sedang Bertugas';
            }

            document.getElementById('modalWaBtn').href = `https://wa.me/6285199274269?text=Halo,%20saya%20tertarik%20dengan%20${encodeURIComponent(nama)}`;
            
            document.getElementById('photoModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('photoModal').classList.add('hidden');
        }
    </script>
</body>
</html>