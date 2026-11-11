<?php
require_once 'config.php';
include 'header.php'; // Memuat pembuka HTML, tag head, sesi, dan anti-cache

// Validasi apakah peserta sudah login
if (!isset($_SESSION['peserta_logged_in']) || $_SESSION['peserta_logged_in'] !== true) {
    header("Location: login-peserta.php");
    exit;
}

$peserta_id = $_SESSION['peserta_id'];

// Ambil data terbaru peserta dari tabel pendaftaran DAN spesialisasi dari tabel pekerja
$stmt = $conn->prepare("
    SELECT p.*, pk.spesialisasi 
    FROM pendaftaran p 
    LEFT JOIN pekerja pk ON (pk.pendaftaran_id = p.id OR pk.id = p.id) 
    WHERE p.id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $peserta_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

// Tentukan program pilihan: Prioritaskan dari tabel pekerja.spesialisasi, jika kosong fallback ke pendaftaran / teks default
$program_pilihan = !empty($data['spesialisasi']) ? $data['spesialisasi'] : ($data['program_pilihan'] ?? 'Belum ditentukan');
?>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Wrapper -->
<main class="flex-1 flex flex-col h-screen overflow-y-auto">
    
    <!-- Top Bar Navigation -->
    <header class="bg-white border-b border-slate-200 px-6 md:px-8 py-4 flex justify-between items-center shadow-sm sticky top-0 z-30">
        <div class="flex items-center space-x-3">
            <!-- Tombol Hamburger (Hanya muncul di HP) -->
            <button id="hamburgerToggle" class="md:hidden text-slate-700 focus:outline-none p-1.5 rounded-xl hover:bg-slate-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="font-bold text-slate-800 text-xs md:text-sm">Dashboard Utama Peserta</span>
        </div>
        <div class="flex items-center space-x-3 md:space-x-4">
            <span class="text-xs text-slate-600 hidden sm:inline">Halo, <b><?php echo htmlspecialchars($data['nama_lengkap']); ?></b></span>
        </div>
    </header>

    <!-- Content Body -->
    <div class="p-6 md:p-8 max-w-4xl w-full space-y-6">
        
        <!-- Notifikasi Berhasil Login (Akan hilang otomatis dalam 3 detik) -->
        <div id="alertLogin">
            <?php if (isset($_GET['status']) && $_GET['status'] == 'login_sukses'): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-xs md:text-sm flex items-center shadow-sm">
                    <span>🎉 Selamat datang kembali, <b><?php echo htmlspecialchars($data['nama_lengkap']); ?></b>! Anda berhasil masuk ke sistem.</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Peringatan / Catatan Admin Jika Ada Kekurangan -->
        <?php if (!empty($data['catatan_admin'])): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 p-5 rounded-3xl text-xs md:text-sm shadow-sm">
                <span class="font-bold block uppercase tracking-wider mb-1">⚠️ Catatan Verifikasi / Kekurangan dari Admin:</span>
                <p class="leading-relaxed"><?php echo nl2br(htmlspecialchars($data['catatan_admin'])); ?></p>
                <div class="mt-3">
                    <a href="ubah-data-diri.php" class="inline-block bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition shadow-sm">
                        ✏️ Perbarui Data / Berkas Sekarang
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Welcome Card & Status -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Selamat Datang, <?php echo htmlspecialchars($data['nama_lengkap']); ?>!</h1>
                <!-- Program Pilihan diambil dari kolom spesialisasi tabel pekerja -->
                <p class="text-xs text-slate-500 mt-1">Program Pilihan: <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($program_pilihan); ?></span></p>
            </div>
            <div>
                <?php 
                    $status = $data['status'] ?? 'Diperiksa';
                    $badgeColor = "bg-amber-100 text-amber-800 border-amber-200";
                    if (strtolower($status) == 'diterima' || strtolower($status) == 'aktif') {
                        $badgeColor = "bg-emerald-100 text-emerald-800 border-emerald-200";
                    } elseif (strtolower($status) == 'ditolak') {
                        $badgeColor = "bg-rose-100 text-rose-800 border-rose-200";
                    }
                ?>
                <span class="px-4 py-2 rounded-xl text-xs font-bold border <?php echo $badgeColor; ?>">
                    Status: <?php echo htmlspecialchars(ucfirst($status)); ?>
                </span>
            </div>
        </div>

        <!-- Detail Data Peserta (Biodata di Kiri, Pasfoto Langsung di Kanan) -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm">
            <div class="flex justify-between items-center mb-6 pb-3 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Informasi Pendaftaran & Biodata</h2>
                <a href="ubah-data-diri.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline">
                    ✏️ Edit Biodata
                </a>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8 items-start justify-between">
                <!-- SISI KIRI: BIODATA -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs flex-1 w-full">
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Nama Lengkap</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['nama_lengkap']); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">NIK</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['nik'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Username</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['username'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">No. Telepon / WhatsApp</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['telepon'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Usia & Jenis Kelamin</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['usia'] ?? '-'); ?> Tahun, <?php echo htmlspecialchars($data['jenis_kelamin'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Pendidikan / Sekolah Asal</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['lulusan_sekolah'] ?? '-'); ?> <?php echo htmlspecialchars($data['nama_sekolah_asal'] ?? ''); ?></p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Alamat Sesuai KTP / Domisili</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo nl2br(htmlspecialchars($data['alamat'] ?? '-')); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Tanggal Pendaftaran</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($data['tanggal_daftar'] ?? '-'); ?></p>
                    </div>
                </div>

                <!-- SISI KANAN: PASFOTO LANGSUNG -->
                <div class="flex flex-col items-center shrink-0 mx-auto md:mx-0">
                    <p class="font-semibold text-slate-400 uppercase tracking-wider text-xs mb-2">Pasfoto Peserta</p>
                    <div class="w-36 h-48 bg-slate-100 border-2 border-slate-200 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center">
                        <?php if (!empty($data['pasfoto']) && file_exists("assets/uploads/" . $data['pasfoto'])): ?>
                            <img src="assets/uploads/<?php echo htmlspecialchars($data['pasfoto']); ?>" alt="Pasfoto" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-slate-400 text-xs italic text-center p-2">Belum ada pasfoto</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Berkas / Lampiran Lainnya di Bagian Bawah -->
            <div class="mt-8 pt-6 border-t border-slate-100">
                <h3 class="text-xs font-bold text-slate-900 mb-3 uppercase tracking-wider">Berkas Lampiran Lainnya</h3>
                <div class="flex flex-wrap gap-3">
                    <?php if(!empty($data['foto_kk'])): ?>
                        <a href="assets/uploads/<?php echo htmlspecialchars($data['foto_kk']); ?>" target="_blank" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-medium text-slate-700 transition inline-flex items-center gap-1.5">
                            <span>📄</span> Lihat Foto KK
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($data['berkas_sk'])): ?>
                        <a href="assets/uploads/<?php echo htmlspecialchars($data['berkas_sk']); ?>" target="_blank" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-medium text-slate-700 transition inline-flex items-center gap-1.5">
                            <span>📑</span> Lihat Berkas SK / Ijazah
                        </a>
                    <?php endif; ?>
                    <?php if(empty($data['foto_kk']) && empty($data['berkas_sk'])): ?>
                        <span class="text-xs text-slate-400 italic">Tidak ada berkas tambahan yang diunggah.</span>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</main>

<!-- Skrip JavaScript untuk Auto-Hide Notifikasi dan Bersihkan URL -->
<script>
    setTimeout(() => {
        const alertBox = document.getElementById('alertLogin');
        if (alertBox) {
            alertBox.style.transition = "opacity 0.5s ease";
            alertBox.style.opacity = "0";
            setTimeout(() => alertBox.remove(), 500);
        }
        // Bersihkan parameter ?status=login_sukses dari URL agar bersih saat direfresh
        if (window.history.replaceState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    }, 3000);
</script>

<!-- Include Footer -->
<?php include 'footer.php'; ?>