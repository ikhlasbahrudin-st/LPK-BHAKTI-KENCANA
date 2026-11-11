<?php
// 1. Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek Sesi Login
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

require_once '../config.php';

$sukses = "";
$error = "";

// Tangkap status dari URL setelah redirect (PRG Pattern)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "User pendaftar berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_update') {
        $sukses = "Data user pendaftar berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "User pendaftar berhasil dihapus.";
    } elseif ($_GET['status'] == 'gagal_tambah') {
        $error = "Gagal menambahkan user (Username mungkin sudah digunakan).";
    } elseif ($_GET['status'] == 'gagal_update') {
        $error = "Gagal memperbarui data user pendaftar.";
    }
}

// Direktori upload berkas/pasfoto pendaftar
$upload_dir = "../assets/uploads/"; 
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 1. PROSES TAMBAH USER PENDAFTAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_user_lpk'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $nik = trim($_POST['nik']);
    $telepon = trim($_POST['telepon']);
    $program_pilihan = trim($_POST['program_pilihan']);
    $alamat = trim($_POST['alamat']);
    $status = trim($_POST['status']);
    $pasfoto_nama = "";

    // Handle Upload Pasfoto
    if (isset($_FILES['pasfoto']) && $_FILES['pasfoto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['pasfoto']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['pasfoto']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            $pasfoto_nama = 'pasfoto_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            move_uploaded_file($file_tmp, $upload_dir . $pasfoto_nama);
        }
    }

    $stmt = $conn->prepare("INSERT INTO pendaftaran (nama_lengkap, username, password, nik, telepon, program_pilihan, alamat, pasfoto, status, tanggal_daftar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssssss", $nama_lengkap, $username, $password, $nik, $telepon, $program_pilihan, $alamat, $pasfoto_nama, $status);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Menambahkan user pendaftar baru: {$nama_lengkap} (Program: {$program_pilihan})", "User Pendaftar");

        header("Location: user_lpk.php?status=sukses_tambah");
        exit;
    } else {
        $stmt->close();
        header("Location: user_lpk.php?status=gagal_tambah");
        exit;
    }
}

// 2. PROSES EDIT USER PENDAFTAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user_lpk'])) {
    $id = intval($_POST['id']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $nik = trim($_POST['nik']);
    $telepon = trim($_POST['telepon']);
    $program_pilihan = trim($_POST['program_pilihan']);
    $alamat = trim($_POST['alamat']);
    $status = trim($_POST['status']);

    // Ambil pasfoto lama
    $q_old = $conn->prepare("SELECT pasfoto FROM pendaftaran WHERE id = ?");
    $q_old->bind_param("i", $id);
    $q_old->execute();
    $old_pasfoto = $q_old->get_result()->fetch_assoc()['pasfoto'] ?? '';
    $q_old->close();

    $pasfoto_nama = $old_pasfoto;
    if (isset($_FILES['pasfoto']) && $_FILES['pasfoto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['pasfoto']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['pasfoto']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            $pasfoto_nama = 'pasfoto_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $pasfoto_nama) && !empty($old_pasfoto) && file_exists($upload_dir . $old_pasfoto)) {
                @unlink($upload_dir . $old_pasfoto);
            }
        }
    }

    if (!empty($_POST['password'])) {
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $stmt_up = $conn->prepare("UPDATE pendaftaran SET nama_lengkap = ?, username = ?, password = ?, nik = ?, telepon = ?, program_pilihan = ?, alamat = ?, pasfoto = ?, status = ? WHERE id = ?");
        $stmt_up->bind_param("sssssssssi", $nama_lengkap, $username, $password, $nik, $telepon, $program_pilihan, $alamat, $pasfoto_nama, $status, $id);
    } else {
        $stmt_up = $conn->prepare("UPDATE pendaftaran SET nama_lengkap = ?, username = ?, nik = ?, telepon = ?, program_pilihan = ?, alamat = ?, pasfoto = ?, status = ? WHERE id = ?");
        $stmt_up->bind_param("ssssssssi", $nama_lengkap, $username, $nik, $telepon, $program_pilihan, $alamat, $pasfoto_nama, $status, $id);
    }

    if ($stmt_up->execute()) {
        $stmt_up->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Memperbarui data user pendaftar: {$nama_lengkap}", "User Pendaftar");

        header("Location: user_lpk.php?status=sukses_update");
        exit;
    } else {
        $stmt_up->close();
        header("Location: user_lpk.php?status=gagal_update");
        exit;
    }
}

// 3. PROSES HAPUS USER PENDAFTAR
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    
    // Ambil nama pendaftar & pasfoto untuk log sebelum dihapus
    $q_pasfoto = $conn->prepare("SELECT nama_lengkap, pasfoto FROM pendaftaran WHERE id = ?");
    $q_pasfoto->bind_param("i", $id_hapus);
    $q_pasfoto->execute();
    $res_user_del = $q_pasfoto->get_result()->fetch_assoc();
    $nama_user_del = $res_user_del ? $res_user_del['nama_lengkap'] : "ID: " . $id_hapus;
    $f_res = $res_user_del['pasfoto'] ?? '';
    
    if (!empty($f_res) && file_exists($upload_dir . $f_res)) {
        @unlink($upload_dir . $f_res);
    }
    $q_pasfoto->close();

    $stmt_del = $conn->prepare("DELETE FROM pendaftaran WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    if ($stmt_del->execute()) {
        $stmt_del->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Menghapus user pendaftar: {$nama_user_del}", "User Pendaftar");

        header("Location: user_lpk.php?status=sukses_hapus");
        exit;
    }
    $stmt_del->close();
}
require_once 'header.php';
$result_users = $conn->query("SELECT * FROM pendaftaran ORDER BY id DESC");
?>

<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">
    <div>
        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-xl hover:bg-slate-100 transition flex items-center justify-center w-10 h-10 cursor-pointer" title="Buka/Tutup Menu">
                    <span class="text-xl font-extrabold">☰</span>
                </button>
                <span class="font-bold text-sm text-slate-800">Manajemen User Pendaftar (LPK)</span>
            </div>
            <a href="../index.php" target="_blank" class="inline-flex items-center space-x-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold px-3.5 py-2 rounded-xl transition shadow-sm">
                <span>🌐</span><span>Lihat Halaman Publik</span>
            </a>
        </header>

        <main class="p-6 md:p-8 w-full max-w-7xl mx-auto overflow-x-hidden">
            <!-- Notifikasi Otomatis Hilang 3 Detik dengan ID "notif-box" -->
            <?php if (!empty($sukses)): ?>
                <div id="notif-box" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm shadow-sm flex items-center gap-2 transition-opacity duration-500">
                    <span>✅</span> <span><?php echo $sukses; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div id="notif-box" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm shadow-sm flex items-center gap-2 transition-opacity duration-500">
                    <span>⚠️</span> <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">Daftar Akun User Pendaftar (LPK)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola data pendaftaran peserta, pasfoto, status, dan program pelatihan.</p>
                </div>
                <button onclick="openAddModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition shadow-sm inline-flex items-center gap-2 cursor-pointer">
                    <span>➕</span> Tambah User Pendaftar
                </button>
            </div>

            <!-- Tabel Daftar User Pendaftar -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-4 w-12 text-center">No</th>
                                <th class="p-4">Pasfoto & Nama</th>
                                <th class="p-4">NIK & Kontak</th>
                                <th class="p-4">Program Pilihan</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700">
                            <?php if ($result_users->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result_users->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4 font-medium text-slate-500 text-center"><?php echo $no++; ?></td>
                                        <td class="p-4 flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-indigo-200 flex-shrink-0 bg-slate-100 shadow-sm">
                                                <?php if (!empty($row['pasfoto']) && file_exists($upload_dir . $row['pasfoto'])): ?>
                                                    <img src="../assets/uploads/<?php echo htmlspecialchars($row['pasfoto']); ?>" alt="Pasfoto" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-xs font-bold text-indigo-600 bg-indigo-50">
                                                        <?php echo strtoupper(substr($row['nama_lengkap'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="font-bold text-slate-900 block"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
                                                <span class="text-[10px] text-indigo-600 font-semibold">@<?php echo htmlspecialchars($row['username']); ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-xs">
                                            <span class="block text-slate-800">NIK: <?php echo htmlspecialchars($row['nik'] ?? '-'); ?></span>
                                            <span class="text-slate-500">📞 <?php echo htmlspecialchars($row['telepon']); ?></span>
                                        </td>
                                        <td class="p-4 text-xs font-medium"><?php echo htmlspecialchars($row['program_pilihan']); ?></td>
                                        <td class="p-4 whitespace-nowrap">
                                            <?php 
                                                $badge_color = "bg-amber-50 text-amber-700 border-amber-200";
                                                if ($row['status'] == 'Diterima') $badge_color = "bg-emerald-50 text-emerald-700 border-emerald-200";
                                                if ($row['status'] == 'Ditolak') $badge_color = "bg-rose-50 text-rose-700 border-rose-200";
                                            ?>
                                            <span class="<?php echo $badge_color; ?> border text-xs font-semibold px-2.5 py-1 rounded-full inline-block">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                            <button onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1 cursor-pointer">
                                                👁️ Detail
                                            </button>
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1 cursor-pointer">
                                                ✏️ Edit
                                            </button>
                                            <a href="user_lpk.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus user pendaftar ini?');" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1">
                                                🗑️ Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="p-12 text-center text-slate-400">Belum ada akun pendaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL POPUP FORM (UNTUK TAMBAH & EDIT) -->
    <div id="userModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all max-h-[90vh] flex flex-col">
            <div class="bg-indigo-900 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
                <h3 id="modalTitle" class="font-bold text-sm">➕ Tambah User Pendaftar</h3>
                <button onclick="closeModal()" class="text-slate-300 hover:text-white p-1 rounded-lg transition cursor-pointer">✕</button>
            </div>

            <form id="userForm" action="user_lpk.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
                <input type="hidden" id="formAction" name="tambah_user_lpk" value="1">
                <input type="hidden" id="userId" name="id" value="">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                        <input type="text" id="namaLengkap" name="nama_lengkap" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Nama Peserta">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Username Login *</label>
                        <input type="text" id="usernameLogin" name="username" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Username">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Password <span id="passwordNote" class="text-slate-400 font-normal">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="passwordInput" name="password" class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-4 pr-24 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="••••••••">
                            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center space-x-1.5">
                                <!-- Tombol Generate Password Acak dengan Ikon Lingkaran Putar (🔄) -->
                                <button type="button" onclick="generatePassword(this)" class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium px-2 py-1 rounded-lg transition inline-flex items-center gap-1 cursor-pointer" title="Buat Password Acak">
                                    <span id="spinIcon" class="inline-block transition-transform duration-500">🔄</span> Acak
                                </button>
                                <!-- Tombol Show/Hide Password dengan Ikon FontAwesome (Mata Tertutup default) -->
                                <button type="button" id="eyeBtn" onclick="togglePasswordVisibility('passwordInput', this)" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer text-sm" title="Lihat/Sembunyikan Password">
                                    <i id="eyeIcon" class="fa-solid fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan)</label>
                        <input type="text" id="nikInput" name="nik" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="3203xxxxxxxxxxxx">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon *</label>
                        <input type="text" id="teleponInput" name="telepon" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="08xxxxxxxxxx">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status Pendaftaran *</label>
                        <select id="statusSelect" name="status" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="Diperiksa">Diperiksa</option>
                            <option value="Diterima">Diterima</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Program Pilihan *</label>
                    <select id="programSelect" name="program_pilihan" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Caregiver Lansia (Perawat Lansia Profesional)">Caregiver Lansia (Perawat Lansia Profesional)</option>
                        <option value="Early Childhood Care / Babysitter (Pengasuh Anak)">Early Childhood Care / Babysitter (Pengasuh Anak)</option>
                        <option value="Daycare Management & Pelatihan Dasar">Daycare Management & Pelatihan Dasar</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
</div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
                    <textarea id="alamatInput" name="alamat" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Alamat domisili peserta..."></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Upload Pasfoto</label>
                    <input type="file" name="pasfoto" accept="image/*" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-xs file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <span class="text-[10px] text-slate-400 mt-1 block">Format: JPG, JPEG, PNG, WEBP</span>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-medium transition cursor-pointer">Batal</button>
                    <button type="submit" id="submitBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition shadow-sm cursor-pointer">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL POPUP DETAIL -->
    <div id="detailModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all text-center p-6 relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeDetailModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg transition cursor-pointer">✕</button>
            
            <div class="w-28 h-28 rounded-2xl overflow-hidden border-4 border-indigo-100 mx-auto mb-4 shadow-md bg-slate-100 flex items-center justify-center">
                <img id="detailPasfoto" src="" alt="Pasfoto Peserta" class="w-full h-full object-cover hidden">
                <span id="detailInisial" class="text-2xl font-bold text-indigo-600"></span>
            </div>

            <h3 id="detailNama" class="text-lg font-bold text-slate-900"></h3>
            <p id="detailUsername" class="text-xs text-indigo-600 font-semibold mb-2"></p>
            <div id="detailStatusBadge" class="inline-block px-3 py-1 rounded-full text-xs font-bold border mb-4"></div>

            <div class="text-left space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 mb-4 text-xs">
                <div><span class="font-semibold text-slate-400 block uppercase">NIK:</span> <span id="detailNik" class="text-slate-800 font-medium"></span></div>
                <div><span class="font-semibold text-slate-400 block uppercase">Nomor Telepon:</span> <span id="detailTelepon" class="text-slate-800 font-medium"></span></div>
                <div><span class="font-semibold text-slate-400 block uppercase">Program Pilihan:</span> <span id="detailProgram" class="text-slate-800 font-medium"></span></div>
                <div><span class="font-semibold text-slate-400 block uppercase">Alamat Lengkap:</span> <span id="detailAlamat" class="text-slate-700 leading-relaxed"></span></div>
            </div>

            <div class="text-[11px] text-slate-400 mb-4">
                Terdaftar pada: <span id="detailTanggal" class="font-medium text-slate-600"></span>
            </div>

            <button onclick="closeDetailModal()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-medium transition shadow-sm cursor-pointer">Tutup</button>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT -->
    <script>
        // 1. Bersihkan parameter status dari URL setelah halaman dimuat untuk mencegah duplikasi saat di-refresh (F5)
        if (window.history.replaceState) {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('status')) {
                urlParams.delete('status');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({path: newUrl}, '', newUrl);
            }
        }

        // 2. Skrip untuk menghilangkan notifikasi dalam 3 detik secara otomatis
        setTimeout(function() {
            const notifBox = document.getElementById('notif-box');
            if (notifBox) {
                notifBox.style.opacity = '0';
                setTimeout(function() {
                    notifBox.style.display = 'none';
                }, 500);
            }
        }, 3000);

        const userModal = document.getElementById('userModal');
        const detailModal = document.getElementById('detailModal');

        // Fungsi Generate Password Acak dengan Efek Putar pada Ikon Lingkaran (🔄)
        function generatePassword(btn) {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$";
            let pass = "";
            for (let i = 0; i < 8; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('passwordInput').value = pass;
            document.getElementById('passwordInput').type = 'text';
            
            // Ubah ikon mata menjadi terbuka saat password digenerate
            const eyeIcon = document.getElementById('eyeIcon');
            eyeIcon.className = "fa-solid fa-eye";

            // Animasi putar (rotate) pada ikon lingkaran
            const icon = btn.querySelector('#spinIcon');
            if (icon) {
                icon.style.transform = "rotate(360deg)";
                setTimeout(() => {
                    icon.style.transform = "rotate(0deg)";
                }, 500);
            }
        }

        // Fungsi Tampilkan/Sembunyikan Password dengan FontAwesome (<i class="fa-solid fa-eye"></i> & <i class="fa-solid fa-eye-slash"></i>)
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');

            if (input.type === "password") {
                input.type = "text";
                icon.className = "fa-solid fa-eye"; // Mata Terbuka
            } else {
                input.type = "password";
                icon.className = "fa-solid fa-eye-slash"; // Mata Tertutup / Tercoret
            }
        }

        function openAddModal() {
            document.getElementById('modalTitle').innerText = "➕ Tambah User Pendaftar Baru";
            document.getElementById('formAction').name = "tambah_user_lpk";
            document.getElementById('formAction').value = "1";
            document.getElementById('userId').value = "";
            document.getElementById('namaLengkap').value = "";
            document.getElementById('usernameLogin').value = "";
            document.getElementById('passwordInput').value = "";
            document.getElementById('passwordInput').type = "password";
            document.getElementById('eyeIcon').className = "fa-solid fa-eye-slash";
            document.getElementById('passwordInput').required = true;
            document.getElementById('passwordNote').innerText = "*";
            document.getElementById('nikInput').value = "";
            document.getElementById('teleponInput').value = "";
            document.getElementById('statusSelect').value = "Diperiksa";
            document.getElementById('programSelect').selectedIndex = 0;
            document.getElementById('alamatInput').value = "";
            document.getElementById('submitBtn').innerText = "Tambah User";
            userModal.classList.remove('hidden');
        }

        function openEditModal(user) {
            document.getElementById('modalTitle').innerText = "✏️ Edit Pendaftar: " + user.nama_lengkap;
            document.getElementById('formAction').name = "update_user_lpk";
            document.getElementById('formAction').value = "1";
            document.getElementById('userId').value = user.id;
            document.getElementById('namaLengkap').value = user.nama_lengkap;
            document.getElementById('usernameLogin').value = user.username;
            document.getElementById('passwordInput').value = "";
            document.getElementById('passwordInput').type = "password";
            document.getElementById('eyeIcon').className = "fa-solid fa-eye-slash";
            document.getElementById('passwordInput').required = false;
            document.getElementById('passwordNote').innerText = "(Kosongkan jika tidak diubah)";
            document.getElementById('nikInput').value = user.nik || '';
            document.getElementById('teleponInput').value = user.telepon || '';
            document.getElementById('statusSelect').value = user.status;
            document.getElementById('programSelect').value = user.program_pilihan;
            document.getElementById('alamatInput').value = user.alamat || '';
            document.getElementById('submitBtn').innerText = "Simpan Perubahan";
            userModal.classList.remove('hidden');
        }

        function closeModal() {
            userModal.classList.add('hidden');
        }

        function openDetailModal(user) {
            document.getElementById('detailNama').innerText = user.nama_lengkap;
            document.getElementById('detailUsername').innerText = "@" + user.username;
            document.getElementById('detailNik').innerText = user.nik ? user.nik : "-";
            document.getElementById('detailTelepon').innerText = user.telepon ? user.telepon : "-";
            document.getElementById('detailProgram').innerText = user.program_pilihan;
            document.getElementById('detailAlamat').innerText = user.alamat ? user.alamat : "Tidak ada alamat yang diisi.";
            document.getElementById('detailTanggal').innerText = user.tanggal_daftar;

            const badge = document.getElementById('detailStatusBadge');
            badge.innerText = user.status;
            badge.className = "inline-block px-3 py-1 rounded-full text-xs font-bold border ";
            if (user.status === 'Diterima') {
                badge.className += "bg-emerald-50 text-emerald-700 border-emerald-200";
            } else if (user.status === 'Ditolak') {
                badge.className += "bg-rose-50 text-rose-700 border-rose-200";
            } else {
                badge.className += "bg-amber-50 text-amber-700 border-amber-200";
            }

            const pasfotoImg = document.getElementById('detailPasfoto');
            const inisialSpan = document.getElementById('detailInisial');
            if (user.pasfoto && user.pasfoto.trim() !== "") {
                pasfotoImg.src = "../assets/uploads/" + user.pasfoto;
                pasfotoImg.classList.remove('hidden');
                inisialSpan.classList.add('hidden');
            } else {
                pasfotoImg.classList.add('hidden');
                inisialSpan.innerText = user.nama_lengkap.substring(0, 2).toUpperCase();
                inisialSpan.classList.remove('hidden');
            }

            detailModal.classList.remove('hidden');
        }

        function closeDetailModal() {
            detailModal.classList.add('hidden');
        }

        window.onclick = function(event) {
            if (event.target == userModal) closeModal();
            if (event.target == detailModal) closeDetailModal();
        }
    </script>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 w-full mt-auto">
        &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
    </footer>
</div>

<?php require_once 'footer.php'; ?>