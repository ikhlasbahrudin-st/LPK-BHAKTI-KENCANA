<?php
// 1. Mulai sesi (Wajib ada di baris paling atas sebelum output apa pun)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CEK SESI: Jika admin belum login, tendang kembali ke halaman login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php"); 
    exit;
}

// 3. VALIDASI ROLE: Jika bukan super_admin, tolak akses dan lempar kembali ke dashboard dengan alert
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
if ($admin_role !== 'super_admin') {
    echo "<script>
            alert('Akses ditolak! Halaman ini khusus untuk Super Admin.');
            window.location.href = 'index.php';
          </script>";
    exit;
}

// 3. Panggil file konfigurasi koneksi database
require_once '../config.php';

// ==========================================
// PROSES AKSI (TAMBAH, UPDATE, HAPUS)
// ==========================================

$sukses = "";
$error = "";

// Tangkap pesan status dari URL redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "Informasi pendaftaran baru berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_update') {
        $sukses = "Informasi pendaftaran berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Informasi pendaftaran berhasil dihapus.";
    } elseif ($_GET['status'] == 'gagal') {
        $error = "Terjadi kesalahan pada sistem database.";
    }
}

// 1. PROSES TAMBAH INFORMASI PENDAFTARAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_info'])) {
    $gelombang       = trim($_POST['gelombang']);
    $program         = trim($_POST['program']);
    $lokasi          = trim($_POST['lokasi']);
    $telepon_panitia = trim($_POST['telepon_panitia']);
    $status          = trim($_POST['status']);
    $keterangan      = trim($_POST['keterangan']);

    $stmt = $conn->prepare("INSERT INTO pendaftaran_info (gelombang, program, lokasi, telepon_panitia, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $gelombang, $program, $lokasi, $telepon_panitia, $status, $keterangan);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Menambahkan informasi pendaftaran baru (Gelombang: {$gelombang} - Program: {$program})", "Informasi Pendaftaran");

        header("Location: pendaftaran_info.php?status=sukses_tambah");
        exit;
    } else {
        $stmt->close();
        header("Location: pendaftaran_info.php?status=gagal");
        exit;
    }
}

// 2. PROSES EDIT / UPDATE INFORMASI PENDAFTARAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $id              = intval($_POST['id']);
    $gelombang       = trim($_POST['gelombang']);
    $program         = trim($_POST['program']);
    $lokasi          = trim($_POST['lokasi']);
    $telepon_panitia = trim($_POST['telepon_panitia']);
    $status          = trim($_POST['status']);
    $keterangan      = trim($_POST['keterangan']);

    $stmt_up = $conn->prepare("UPDATE pendaftaran_info SET gelombang = ?, program = ?, lokasi = ?, telepon_panitia = ?, status = ?, keterangan = ? WHERE id = ?");
    $stmt_up->bind_param("ssssssi", $gelombang, $program, $lokasi, $telepon_panitia, $status, $keterangan, $id);
    
    if ($stmt_up->execute()) {
        $stmt_up->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Memperbarui informasi pendaftaran ID: {$id} (Gelombang: {$gelombang} - Program: {$program})", "Informasi Pendaftaran");

        header("Location: pendaftaran_info.php?status=sukses_update");
        exit;
    } else {
        $stmt_up->close();
        header("Location: pendaftaran_info.php?status=gagal");
        exit;
    }
}

// 3. PROSES HAPUS INFORMASI
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);

    // Ambil data gelombang & program untuk log sebelum dihapus
    $q_log_del = $conn->prepare("SELECT gelombang, program FROM pendaftaran_info WHERE id = ?");
    $q_log_del->bind_param("i", $id_hapus);
    $q_log_del->execute();
    $res_ld = $q_log_del->get_result()->fetch_assoc();
    $q_log_del->close();

    $info_hapus = $res_ld ? "(Gelombang: {$res_ld['gelombang']} - Program: {$res_ld['program']})" : "ID: {$id_hapus}";

    $stmt_del = $conn->prepare("DELETE FROM pendaftaran_info WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    if ($stmt_del->execute()) {
        $stmt_del->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Menghapus informasi pendaftaran {$info_hapus}", "Informasi Pendaftaran");

        header("Location: pendaftaran_info.php?status=sukses_hapus");
        exit;
    }
    $stmt_del->close();
}

// ==========================================
// AMBIL DATA UNTUK DITAMPILKAN DI HALAMAN HTML
// ==========================================

$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $q_edit = $conn->prepare("SELECT * FROM pendaftaran_info WHERE id = ?");
    $q_edit->bind_param("i", $id_edit);
    $q_edit->execute();
    $edit_data = $q_edit->get_result()->fetch_assoc();
    $q_edit->close();
}

$result_info = $conn->query("SELECT * FROM pendaftaran_info ORDER BY id DESC");

// Panggil header admin
require_once 'header.php';
?>

<!-- Pembungkus Utama Layout -->
<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">

    <div>
        <!-- Topbar / Header Mobile -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30 md:hidden">
            <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-lg hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="font-bold text-sm text-slate-800">Info Pendaftaran</span>
            <div class="w-6"></div>
        </header>

        <!-- Konten Utama -->
        <main class="p-6 md:p-8 w-full max-w-7xl mx-auto overflow-x-hidden">

            <!-- Notifikasi Sukses / Error dengan ID "notif-box" -->
            <?php if (!empty($sukses)): ?>
                <div id="notif-box" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm transition-opacity duration-500">
                    <span>✅ <?php echo $sukses; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div id="notif-box" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm transition-opacity duration-500">
                    <span>⚠️ <?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- FORM TAMBAH / EDIT INFORMASI PENDAFTARAN -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 mb-8">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">
                        <?php echo $edit_data ? 'Edit Informasi Pendaftaran ID: <span class="text-indigo-600">#' . $edit_data['id'] . '</span>' : '➕ Tambah Informasi & Gelombang Baru'; ?>
                    </h2>
                    <?php if ($edit_data): ?>
                        <a href="pendaftaran_info.php" class="text-xs text-rose-600 hover:underline font-semibold">Batal Edit ✕</a>
                    <?php endif; ?>
                </div>

                <form action="pendaftaran_info.php" method="POST" class="space-y-4">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                        <input type="hidden" name="update_info" value="1">
                    <?php else: ?>
                        <input type="hidden" name="tambah_info" value="1">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama / Nomor Gelombang *</label>
                            <input type="text" name="gelombang" value="<?php echo $edit_data ? htmlspecialchars($edit_data['gelombang']) : ''; ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Contoh: Gelombang 1 / Reguler">
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Program Pelatihan / Layanan *</label>
                            <input type="text" name="program" value="<?php echo $edit_data ? htmlspecialchars($edit_data['program']) : ''; ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Contoh: Caregiver Lansia, Homecare">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Status Gelombang *</label>
                            <select name="status" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-indigo-600">
                                <option value="Aktif" <?php echo ($edit_data && $edit_data['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Tutup" <?php echo ($edit_data && $edit_data['status'] == 'Tutup') ? 'selected' : ''; ?>>Tutup</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Lokasi Kantor / Penempatan *</label>
                            <input type="text" name="lokasi" value="<?php echo $edit_data ? htmlspecialchars($edit_data['lokasi']) : ''; ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Contoh: Cianjur, Jawa Barat">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon Panitia / Admin *</label>
                            <input type="text" name="telepon_panitia" value="<?php echo $edit_data ? htmlspecialchars($edit_data['telepon_panitia']) : ''; ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Contoh: 081234567890">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan / Pengumuman Detail</label>
                        <textarea name="keterangan" rows="3" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Tuliskan detail info pendaftaran atau gelombang aktif di sini..."><?php echo $edit_data ? htmlspecialchars($edit_data['keterangan']) : ''; ?></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <?php if ($edit_data): ?>
                            <a href="pendaftaran_info.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-medium transition">Batal</a>
                        <?php endif; ?>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                            <?php echo $edit_data ? 'Simpan Perubahan' : 'Tambah Informasi'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- TABEL DAFTAR INFORMASI PENDAFTARAN -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="p-6 border-b border-slate-200 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Daftar Informasi & Gelombang Pendaftaran</h2>
                        <p class="text-xs text-slate-500">Kelola informasi gelombang, program, lokasi, kontak panitia, dan status pendaftaran aktif.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-4 w-12">No</th>
                                <th class="p-4">Gelombang</th>
                                <th class="p-4">Program Pelayanan</th>
                                <th class="p-4">Lokasi & Kontak Panitia</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Keterangan</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700">
                            <?php if ($result_info->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result_info->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4 font-medium text-slate-500"><?php echo $no++; ?></td>
                                        <td class="p-4 font-semibold text-indigo-600 text-xs">
                                            <?php echo htmlspecialchars($row['gelombang']); ?>
                                        </td>
                                        <td class="p-4 font-bold text-slate-900 text-xs">
                                            <?php echo htmlspecialchars($row['program']); ?>
                                        </td>
                                        <td class="p-4 text-xs">
                                            <span class="font-semibold block text-slate-800">📍 <?php echo htmlspecialchars($row['lokasi']); ?></span>
                                            <span class="text-slate-500 block">📞 <?php echo htmlspecialchars($row['telepon_panitia']); ?></span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap">
                                            <?php if ($row['status'] == 'Aktif'): ?>
                                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    Tutup
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-xs text-slate-600 max-w-xs">
                                            <?php echo nl2br(htmlspecialchars($row['keterangan'])); ?>
                                        </td>
                                        <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                            <a href="pendaftaran_info.php?edit=<?php echo $row['id']; ?>" class="inline-block bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                                Edit
                                            </a>
                                            <a href="pendaftaran_info.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus informasi pendaftaran ini?');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                                Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-slate-400 font-medium">Belum ada informasi pendaftaran di database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 w-full mt-auto">
        &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
    </footer>

</div>

<!-- SCRIPT JAVASCRIPT UNTUK MENGHILANGKAN NOTIFIKASI DALAM 3 DETIK -->
<script>
    setTimeout(function() {
        const notifBox = document.getElementById('notif-box');
        if (notifBox) {
            notifBox.style.opacity = '0';
            setTimeout(function() {
                notifBox.style.display = 'none';
            }, 500); // Waktu animasi fade-out selesai (0.5 detik)
        }
    }, 3000); // 3000 milidetik = 3 detik
</script>

<?php
// Panggil footer admin
require_once 'footer.php';
?>