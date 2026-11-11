<?php
// Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$sukses = "";
$error = "";

// Buat direktori upload jika belum ada
$upload_dir = "assets/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Proses form pendaftaran dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['daftar_sekarang'])) {
    $username          = trim($_POST['username']);
    $password_raw      = $_POST['password'];
    $nama_lengkap      = trim($_POST['nama_lengkap']);
    $nik               = trim($_POST['nik']);
    $telepon           = trim($_POST['telepon']);
    $usia              = intval($_POST['usia']);
    $jenis_kelamin     = trim($_POST['jenis_kelamin']);
    $program_pilihan   = trim($_POST['program_pilihan']);
    $lulusan_sekolah   = trim($_POST['lulusan_sekolah']);
    $nama_sekolah_asal = trim($_POST['nama_sekolah_asal']);
    $alamat            = trim($_POST['alamat']);
    $tanggal_daftar    = date('Y-m-d H:i:s');
    $status            = 'Diperiksa'; // Default status pendaftaran baru

    // 1. Validasi NIK: Harus tepat 16 digit dan berupa angka
    if (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $error = "Gagal: NIK harus berupa angka dan tepat 16 digit!";
    } else {
        // Cek ganda apakah NIK sudah ada di database
        $stmt_nik = $conn->prepare("SELECT id FROM pendaftaran WHERE nik = ?");
        $stmt_nik->bind_param("s", $nik);
        $stmt_nik->execute();
        $stmt_nik->store_result();

        if ($stmt_nik->num_rows > 0) {
            $error = "Gagal: NIK '{$nik}' sudah terdaftar dalam sistem!";
            $stmt_nik->close();
        } else {
            $stmt_nik->close();

            // 2. Cek username unik
            $stmt_cek = $conn->prepare("SELECT id FROM pendaftaran WHERE username = ?");
            $stmt_cek->bind_param("s", $username);
            $stmt_cek->execute();
            $stmt_cek->store_result();

            if ($stmt_cek->num_rows > 0) {
                $error = "Username sudah digunakan, silakan gunakan username lain.";
                $stmt_cek->close();
            } else {
                $stmt_cek->close();

                // Hash password
                $password = password_hash($password_raw, PASSWORD_DEFAULT);

                // Fungsi Helper Upload File
                function uploadFile($fileInputName, $target_dir) {
                    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
                        $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
                        $filename = $fileInputName . "_" . time() . "_" . uniqid() . "." . $ext;
                        $target_path = $target_dir . $filename;
                        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $target_path)) {
                            return $filename;
                        }
                    }
                    return null;
                }

                // Upload file-file dokumen
                $foto_kk   = uploadFile('foto_kk', $upload_dir);
                $pasfoto   = uploadFile('pasfoto', $upload_dir);
                $berkas_sk = uploadFile('berkas_sk', $upload_dir);

                // Insert ke database
                $stmt = $conn->prepare("INSERT INTO pendaftaran (username, password, nama_lengkap, nik, telepon, usia, jenis_kelamin, program_pilihan, lulusan_sekolah, nama_sekolah_asal, alamat, foto_kk, pasfoto, berkas_sk, tanggal_daftar, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                // PERBAIKAN: Ubah string bind_param menjadi "sssssissssssssss" (hanya usia yang 'i', sisanya 's')
                $stmt->bind_param("sssssissssssssss", 
                    $username, $password, $nama_lengkap, $nik, $telepon, 
                    $usia, $jenis_kelamin, $program_pilihan, $lulusan_sekolah, 
                    $nama_sekolah_asal, $alamat, $foto_kk, $pasfoto, 
                    $berkas_sk, $tanggal_daftar, $status
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    // Redirect langsung ke halaman login peserta dengan membawa status sukses registrasi
                    echo "<script>window.location='login-peserta.php?status=registrasi_sukses';</script>";
                    exit;
                } else {
                    $error = "Terjadi kesalahan pada sistem saat menyimpan data pendaftaran: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pendaftaran - LPK Bhakti Kencana Cianjur</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col justify-between font-sans text-slate-800">

<!-- Header Sederhana / Root Header -->
<header class="bg-white border-b border-slate-200 py-4 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <!-- Bagian Kiri: Logo & Nama Instansi (Bisa Diklik) -->
        <a href="http://localhost/LPK_PBK/index.php" class="flex items-center space-x-3 group focus:outline-none">
            <img src="assets/img/logo.png" alt="Logo LPK" class="w-12 h-12 sm:w-14 sm:h-14 object-contain rounded-xl bg-slate-50 p-1.5 border border-slate-200 shadow-sm transition group-hover:scale-105">
            <div>
                <span class="font-bold text-slate-800 text-base sm:text-lg block tracking-tight group-hover:text-indigo-600 transition">LPK Bhakti Kencana</span>
                <span class="text-xs text-slate-500 font-medium">Cianjur, Jawa Barat</span>
            </div>
        </a>

        <!-- Bagian Kanan (Opsional: Status Sistem) -->
        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                ● Sistem Aktif
            </span>
        </div>
    </div>
</header>

    <!-- Konten Formulir -->
    <main class="max-w-4xl w-full mx-auto px-4 py-8 flex-1">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-xl overflow-hidden p-8">
            
            <div class="mb-8 border-b border-slate-100 pb-4">
                <h2 class="text-xl font-black text-slate-900">Formulir Pendaftaran Pelatihan Kerja</h2>
                <p class="text-xs text-slate-500 mt-1">Lengkapi data diri dan unggah berkas persyaratan dengan benar.</p>
            </div>

            <!-- Notifikasi -->
            <div id="alertNotification">
                <?php if (!empty($sukses)): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                        <span>✅ <?php echo $sukses; ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                        <span>⚠️ <?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form Pendaftaran -->
            <form action="pendaftaran.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Username *</label>
                        <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Buat username untuk akun anda">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Password *</label>
                        <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Buat password akun">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Nama sesuai KTP">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan) *</label>
                        <input type="text" name="nik" required maxlength="16" minlength="16" pattern="[0-9]{16}" title="NIK harus terdiri dari 16 angka" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="16 digit NIK">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon / WhatsApp *</label>
                        <input type="text" name="telepon" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: 081220192571">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Usia (Tahun) *</label>
                        <input type="number" name="usia" required min="17" max="60" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: 22">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin *</label>
                        <select name="jenis_kelamin" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled selected>-- Pilih Jenis Kelamin --</option>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pilihan Program Pelatihan *</label>
                        <select name="program_pilihan" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium text-indigo-600">
                            <option value="" disabled selected>-- Pilih Program --</option>
                            <option value="Homecare">Homecare</option>
                            <option value="Caregiver">Caregiver</option>
                            <option value="Daycare">Daycare</option>
                            <option value="Babycare">Babycare</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pendidikan / Lulusan Sekolah *</label>
                        <select name="lulusan_sekolah" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled selected>-- Pilih Pendidikan --</option>
                            <option value="SMA / Sederajat">SMA / Sederajat</option>
                            <option value="SMK / Sederajat">SMK / Sederajat</option>
                            <option value="MA / Sederajat">MA / Sederajat</option>
                            <option value="Diploma (D3/D4)">Diploma (D3/D4)</option>
                            <option value="Sarjana (S1)">Sarjana (S1)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Sekolah / Kampus Asal *</label>
                        <input type="text" name="nama_sekolah_asal" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: SMAN 1 Cianjur">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Domisili Lengkap *</label>
                    <textarea name="alamat" rows="3" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Masukkan alamat domisili saat ini..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Foto KK (Kartu Keluarga)</label>
                        <input type="file" name="foto_kk" accept="image/*,.pdf" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pasfoto Terbaru</label>
                        <input type="file" name="pasfoto" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ijazah /Berkas Pendukung</label>
                        <input type="file" name="berkas_sk" accept="image/*,.pdf" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 flex justify-end">
                    <button type="submit" name="daftar_sekarang" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-8 py-3 rounded-xl text-sm transition shadow-lg shadow-indigo-200">
                        🚀 Kirim Pendaftaran Sekarang
                    </button>
                </div>

            </form>
        </div>
    </main>

    <!-- JavaScript untuk Auto-Hide Notifikasi dan Pembersihan URL -->
    <script>
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

    <!-- Footer Sederhana -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 w-full mt-auto">
        &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
    </footer>

</body>
</html>