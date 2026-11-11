<?php
// 1. Mulai Sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek apakah peserta sudah login
if (!isset($_SESSION['peserta_logged_in']) || $_SESSION['peserta_logged_in'] !== true) {
    header("Location: login-peserta.php");
    exit;
}

require_once 'config.php';

$sukses = "";
$error = "";
$peserta_id = $_SESSION['peserta_id'];

// Direktori upload
$upload_dir = "assets/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 3. Proses Update Data Diri ketika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama_lengkap      = trim($_POST['nama_lengkap']);
    $nik               = trim($_POST['nik']);
    $telepon           = trim($_POST['telepon']);
    $usia              = intval($_POST['usia']);
    $jenis_kelamin     = trim($_POST['jenis_kelamin']);
    $lulusan_sekolah   = trim($_POST['lulusan_sekolah']);
    $nama_sekolah_asal = trim($_POST['nama_sekolah_asal']);
    $alamat            = trim($_POST['alamat']);

    // Validasi NIK 16 digit
    if (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $error = "Gagal: NIK harus berupa angka dan tepat 16 digit!";
    } else {
        // Cek duplikasi NIK: Pastikan NIK tidak dipakai oleh ID peserta LAIN
        $stmt_nik = $conn->prepare("SELECT id FROM pendaftaran WHERE nik = ? AND id != ?");
        $stmt_nik->bind_param("si", $nik, $peserta_id);
        $stmt_nik->execute();
        $result_nik = $stmt_nik->get_result();

        if ($result_nik->num_rows > 0) {
            $error = "Gagal: NIK '{$nik}' sudah terdaftar oleh peserta lain!";
            $stmt_nik->close();
        } else {
            $stmt_nik->close();

            // Ambil data file lama untuk keperluan hapus/ganti file baru
            $stmt_old = $conn->prepare("SELECT foto_kk, pasfoto, berkas_sk FROM pendaftaran WHERE id = ?");
            $stmt_old->bind_param("i", $peserta_id);
            $stmt_old->execute();
            $old_files = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $foto_kk = $old_files['foto_kk'];
            $pasfoto = $old_files['pasfoto'];
            $berkas_sk = $old_files['berkas_sk'];

            // Fungsi Helper Upload File Baru
            function uploadFileBaru($fileInputName, $target_dir, $old_file) {
                if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
                    $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
                    $filename = $fileInputName . "_" . time() . "_" . uniqid() . "." . $ext;
                    $target_path = $target_dir . $filename;
                    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $target_path)) {
                        if (!empty($old_file) && file_exists($target_dir . $old_file)) {
                            @unlink($target_dir . $old_file);
                        }
                        return $filename;
                    }
                }
                return $old_file;
            }

            $foto_kk = uploadFileBaru('foto_kk', $upload_dir, $foto_kk);
            $pasfoto = uploadFileBaru('pasfoto', $upload_dir, $pasfoto);
            $berkas_sk = uploadFileBaru('berkas_sk', $upload_dir, $berkas_sk);

            // Update ke database
            $stmt_up = $conn->prepare("UPDATE pendaftaran SET nama_lengkap = ?, nik = ?, telepon = ?, usia = ?, jenis_kelamin = ?, lulusan_sekolah = ?, nama_sekolah_asal = ?, alamat = ?, foto_kk = ?, pasfoto = ?, berkas_sk = ? WHERE id = ?");
            $stmt_up->bind_param("sssisssssssi", $nama_lengkap, $nik, $telepon, $usia, $jenis_kelamin, $lulusan_sekolah, $nama_sekolah_asal, $alamat, $foto_kk, $pasfoto, $berkas_sk, $peserta_id);

            if ($stmt_up->execute()) {
                $stmt_up->close();
                $_SESSION['peserta_nama'] = $nama_lengkap;
                echo "<script>window.location='ubah-data-diri.php?status=sukses';</script>";
                exit;
            } else {
                $error = "Terjadi kesalahan sistem saat memperbarui data diri.";
                $stmt_up->close();
            }
        }
    }
}

// 4. Ambil data peserta terbaru dari database berdasarkan ID yang sedang login
$stmt_get = $conn->prepare("SELECT * FROM pendaftaran WHERE id = ? LIMIT 1");
$stmt_get->bind_param("i", $peserta_id);
$stmt_get->execute();
$peserta = $stmt_get->get_result()->fetch_assoc();
$stmt_get->close();

if (!$peserta) {
    header("Location: login-peserta.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Data Diri - Panel Peserta LPK Bhakti Kencana</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col justify-between font-sans text-slate-800">

    <!-- Topbar Mobile -->
    <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30 md:hidden">
        <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-lg hover:bg-slate-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        <span class="font-bold text-sm text-slate-800">Ubah Data Diri</span>
        <div class="w-6"></div>
    </header>

    <div class="flex flex-1">
        <!-- Sidebar Peserta -->
        <?php include 'sidebar.php'; ?>

        <!-- Konten Utama -->
        <main class="flex-1 p-6 md:p-8 max-w-5xl mx-auto">
            
            <div class="mb-6">
                <h1 class="text-xl font-black text-slate-900">Ubah Data Diri & Berkas Persyaratan</h1>
                <p class="text-xs text-slate-500 mt-1">Perbarui informasi profil atau ganti dokumen persyaratan Anda dengan mudah.</p>
            </div>

            <!-- Notifikasi -->
            <div id="alertNotification">
                <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                        <span>✅ Data diri berhasil diperbarui!</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                        <span>⚠️ <?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Form -->
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 md:p-8">
                <form action="ubah-data-diri.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                            <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan) *</label>
                            <input type="text" name="nik" value="<?php echo htmlspecialchars($peserta['nik']); ?>" required maxlength="16" minlength="16" pattern="[0-9]{16}" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon / WhatsApp *</label>
                            <input type="text" name="telepon" value="<?php echo htmlspecialchars($peserta['telepon']); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Usia (Tahun) *</label>
                            <input type="number" name="usia" value="<?php echo htmlspecialchars($peserta['usia'] ?? ''); ?>" required min="17" max="60" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="Laki-laki" <?php echo ($peserta['jenis_kelamin'] === 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?php echo ($peserta['jenis_kelamin'] === 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Lulusan Sekolah / Jenjang Terakhir *</label>
                            <select name="lulusan_sekolah" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <?php 
                                $jenjang_list = ['SMA / Sederajat', 'SMK / Sederajat', 'MA / Sederajat', 'Diploma (D3/D4)', 'Sarjana (S1)'];
                                foreach ($jenjang_list as $j) {
                                    $sel = ($peserta['lulusan_sekolah'] === $j) ? 'selected' : '';
                                    echo "<option value=\"$j\" $sel>$j</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Sekolah / Kampus Asal *</label>
                            <input type="text" name="nama_sekolah_asal" value="<?php echo htmlspecialchars($peserta['nama_sekolah_asal'] ?? ''); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Domisili Lengkap *</label>
                        <textarea name="alamat" rows="2" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><?php echo htmlspecialchars($peserta['alamat']); ?></textarea>
                    </div>

                    <!-- Bagian Berkas / Dokumen -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Foto KK (Kartu Keluarga)</label>
                            <?php if (!empty($peserta['foto_kk'])): ?>
                                <span class="block text-[11px] text-emerald-600 mb-1 font-medium">📄 File saat ini tersimpan</span>
                            <?php endif; ?>
                            <input type="file" name="foto_kk" accept="image/*,.pdf" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pasfoto Terbaru</label>
                            <?php if (!empty($peserta['pasfoto'])): ?>
                                <span class="block text-[11px] text-emerald-600 mb-1 font-medium">📷 File saat ini tersimpan</span>
                            <?php endif; ?>
                            <input type="file" name="pasfoto" accept="image/*" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Berkas Ijazah </label>
                            <?php if (!empty($peserta['berkas_sk'])): ?>
                                <span class="block text-[11px] text-emerald-600 mb-1 font-medium">📄 File saat ini tersimpan</span>
                            <?php endif; ?>
                            <input type="file" name="berkas_sk" accept="image/*,.pdf" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex justify-end">
                        <button type="submit" name="update_profil" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-8 py-3 rounded-xl text-sm transition shadow-md">
                            💾 Simpan Perubahan Data Diri
                        </button>
                    </div>

                </form>
            </div>

        </main>
    </div>

    <!-- Script Sidebar & Auto-hide Alert -->
    <script>
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const closeBtn = document.getElementById('closeSidebar');

        function toggleSidebar() {
            if (sidebar && overlay) {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }
        }

        if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', toggleSidebar);

        setTimeout(() => {
            const alertBox = document.getElementById('alertNotification');
            if (alertBox) {
                alertBox.style.transition = "opacity 0.5s ease";
                alertBox.style.opacity = "0";
                setTimeout(() => alertBox.remove(), 500);
            }
            if (window.history.replaceState) {
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({path: cleanUrl}, '', cleanUrl);
            }
        }, 3000);
    </script>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 w-full mt-auto">
        &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
    </footer>

</body>
</html>