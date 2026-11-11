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

// Tangkap status dari URL setelah redirect (PRG Pattern agar bersih saat di-refresh)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "Admin baru beserta foto dan deskripsi berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_update') {
        $sukses = "Data admin berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Admin berhasil dihapus dari sistem.";
    } elseif ($_GET['status'] == 'gagal_tambah') {
        $error = "Gagal menambahkan admin (Username mungkin sudah terdaftar).";
    } elseif ($_GET['status'] == 'gagal_update') {
        $error = "Gagal memperbarui data admin.";
    } elseif ($_GET['status'] == 'gagal_hapus_sendiri') {
        $error = "Anda tidak dapat menghapus akun yang sedang digunakan saat ini!";
    }
}

// Direktori upload foto admin
$upload_dir = "../assets/img/admin/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 1. PROSES TAMBAH ADMIN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_admin'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $role = trim($_POST['role']);
    $deskripsi = trim($_POST['deskripsi']);
    $foto_nama = "";

    // Handle Upload Foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            $foto_nama = 'admin_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            move_uploaded_file($file_tmp, $upload_dir . $foto_nama);
        }
    }

    $stmt = $conn->prepare("INSERT INTO admins (nama_lengkap, username, password, role, foto, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nama_lengkap, $username, $password, $role, $foto_nama, $deskripsi);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Menambahkan data admin/operator baru: " . $nama_lengkap, "Admin");

        header("Location: admin.php?status=sukses_tambah");
        exit;
    } else {
        $stmt->close();
        header("Location: admin.php?status=gagal_tambah");
        exit;
    }
}

// 2. PROSES EDIT / UPDATE ADMIN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin'])) {
    $id = intval($_POST['id']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $role = trim($_POST['role']);
    $deskripsi = trim($_POST['deskripsi']);

    // Ambil foto lama
    $q_old = $conn->prepare("SELECT foto FROM admins WHERE id = ?");
    $q_old->bind_param("i", $id);
    $q_old->execute();
    $old_foto = $q_old->get_result()->fetch_assoc()['foto'] ?? '';
    $q_old->close();

    $foto_nama = $old_foto;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            $foto_nama = 'admin_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $foto_nama) && !empty($old_foto) && file_exists($upload_dir . $old_foto)) {
                @unlink($upload_dir . $old_foto);
            }
        }
    }

    if (!empty($_POST['password'])) {
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $stmt_up = $conn->prepare("UPDATE admins SET nama_lengkap = ?, username = ?, password = ?, role = ?, foto = ?, deskripsi = ? WHERE id = ?");
        $stmt_up->bind_param("ssssssi", $nama_lengkap, $username, $password, $role, $foto_nama, $deskripsi, $id);
    } else {
        $stmt_up = $conn->prepare("UPDATE admins SET nama_lengkap = ?, username = ?, role = ?, foto = ?, deskripsi = ? WHERE id = ?");
        $stmt_up->bind_param("sssssi", $nama_lengkap, $username, $role, $foto_nama, $deskripsi, $id);
    }

    if ($stmt_up->execute()) {
        $stmt_up->close();
        
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Memperbarui data akun admin/operator: " . $nama_lengkap, "Admin");

        header("Location: admin.php?status=sukses_update");
        exit;
    } else {
        $stmt_up->close();
        header("Location: admin.php?status=gagal_update");
        exit;
    }
}

// 3. PROSES HAPUS ADMIN
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    if ($id_hapus == $_SESSION['admin_id']) {
        header("Location: admin.php?status=gagal_hapus_sendiri");
        exit;
    } else {
        // Ambil nama admin yang akan dihapus untuk keperluan log
        $q_nama_del = $conn->prepare("SELECT nama_lengkap, foto FROM admins WHERE id = ?");
        $q_nama_del->bind_param("i", $id_hapus);
        $q_nama_del->execute();
        $res_d_admin = $q_nama_del->get_result()->fetch_assoc();
        $nama_admin_del = $res_d_admin ? $res_d_admin['nama_lengkap'] : "ID: " . $id_hapus;
        $f_res = $res_d_admin['foto'] ?? '';
        $q_nama_del->close();

        // Hapus file foto jika ada
        if (!empty($f_res) && file_exists($upload_dir . $f_res)) {
            @unlink($upload_dir . $f_res);
        }

        $stmt_del = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt_del->bind_param("i", $id_hapus);
        if ($stmt_del->execute()) {
            $stmt_del->close();
            
            // CATAT LOG AKTIVITAS
            catatLogAktivitas($conn, "Menghapus akun admin/operator: " . $nama_admin_del, "Admin");

            header("Location: admin.php?status=sukses_hapus");
            exit;
        }
        $stmt_del->close();
    }
}

require_once 'header.php';
$result_admins = $conn->query("SELECT * FROM admins ORDER BY id DESC");
?>

<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">
    <div>
        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-xl hover:bg-slate-100 transition flex items-center justify-center w-10 h-10 cursor-pointer" title="Buka/Tutup Menu">
                    <span class="text-xl font-extrabold">☰</span>
                </button>
                <span class="font-bold text-sm text-slate-800">Manajemen User Admin</span>
            </div>
            <a href="../index.php" target="_blank" class="inline-flex items-center space-x-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold px-3.5 py-2 rounded-xl transition shadow-sm">
                <span>🌐</span><span>Lihat Halaman Publik</span>
            </a>
        </header>

        <main class="p-6 md:p-8 w-full max-w-7xl mx-auto overflow-x-hidden">
            <!-- Notifikasi Otomatis Hilang dalam 3 Detik dengan ID "notif-box" -->
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
                    <h2 class="text-lg font-extrabold text-slate-900">Daftar Akun Administrator</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola akses akun, foto profil, deskripsi, dan role administrator sistem.</p>
                </div>
                <button onclick="openAddModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition shadow-sm inline-flex items-center gap-2 cursor-pointer">
                    <span>➕</span> Tambah Admin Baru
                </button>
            </div>

            <!-- Tabel Daftar Admin -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-4 w-12 text-center">No</th>
                                <th class="p-4">Foto & Nama</th>
                                <th class="p-4">Username</th>
                                <th class="p-4">Role</th>
                                <th class="p-4">Deskripsi Singkat</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700">
                            <?php if ($result_admins->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result_admins->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4 font-medium text-slate-500 text-center"><?php echo $no++; ?></td>
                                        <td class="p-4 flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-indigo-200 flex-shrink-0 bg-slate-100 shadow-sm">
                                                <?php if (!empty($row['foto']) && file_exists($upload_dir . $row['foto'])): ?>
                                                    <img src="../assets/img/admin/<?php echo htmlspecialchars($row['foto']); ?>" alt="Foto" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-xs font-bold text-indigo-600 bg-indigo-50">
                                                        <?php echo strtoupper(substr($row['nama_lengkap'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="font-bold text-slate-900 block"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
                                                <span class="text-[10px] text-slate-400">ID: #<?php echo $row['id']; ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-xs text-indigo-600 font-semibold">@<?php echo htmlspecialchars($row['username']); ?></td>
                                        <td class="p-4">
                                            <?php 
                                                $role_badge = 'bg-slate-100 text-slate-700 border-slate-200';
                                                if ($row['role'] === 'super_admin') $role_badge = 'bg-purple-50 text-purple-700 border-purple-200';
                                                if ($row['role'] === 'koordinator') $role_badge = 'bg-amber-50 text-amber-700 border-amber-200';
                                            ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border <?php echo $role_badge; ?> uppercase">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $row['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-xs text-slate-500 max-w-xs truncate">
                                            <?php echo !empty($row['deskripsi']) ? htmlspecialchars($row['deskripsi']) : '<span class="italic text-slate-400">Tidak ada deskripsi</span>'; ?>
                                        </td>
                                        <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                            <button onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1 cursor-pointer">
                                                👁️ Detail
                                            </button>
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1 cursor-pointer">
                                                ✏️ Edit
                                            </button>
                                            <?php if ($row['id'] != 1 && $row['id'] != $_SESSION['admin_id']): ?>
                                                <a href="admin.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus admin ini?');" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1">
                                                    🗑️ Hapus
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="p-12 text-center text-slate-400">Belum ada akun admin.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- 1. MODAL POPUP FORM (UNTUK TAMBAH & EDIT) -->
    <div id="adminModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all max-h-[90vh] flex flex-col">
            <div class="bg-indigo-900 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
                <h3 id="modalTitle" class="font-bold text-sm">➕ Tambah Admin Baru</h3>
                <button onclick="closeModal()" class="text-slate-300 hover:text-white p-1 rounded-lg transition cursor-pointer">✕</button>
            </div>

            <form id="adminForm" action="admin.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
                <input type="hidden" id="formAction" name="tambah_admin" value="1">
                <input type="hidden" id="adminId" name="id" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                    <input type="text" id="namaLengkap" name="nama_lengkap" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Nama Administrator">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Username Login *</label>
                    <input type="text" id="usernameLogin" name="username" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Username">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Password <span id="passwordNote" class="text-slate-400 font-normal">*</span>
                    </label>
                    <input type="password" id="passwordInput" name="password" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Hak Akses Role *</label>
                    <select id="roleSelect" name="role" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="super_admin">Super Admin (Akses Penuh Sistem)</option>
                        <option value="koordinator">Koordinator (Pemantauan LPK & Laporan)</option>
                        <option value="admin">Admin Biasa</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Upload Foto Profil</label>
                    <input type="file" name="foto" accept="image/*" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-xs file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <span class="text-[10px] text-slate-400 mt-1 block">Format: JPG, JPEG, PNG, WEBP</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi / Bio Singkat</label>
                    <textarea id="deskripsiInput" name="deskripsi" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Tuliskan deskripsi atau tanggung jawab admin..."></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-medium transition cursor-pointer">Batal</button>
                    <button type="submit" id="submitBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition shadow-sm cursor-pointer">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. MODAL POPUP DETAIL -->
    <div id="detailModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all text-center p-6 relative">
            <button onclick="closeDetailModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg transition cursor-pointer">✕</button>
            
            <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-indigo-100 mx-auto mb-4 shadow-md bg-slate-100 flex items-center justify-center">
                <img id="detailFoto" src="" alt="Foto Profil" class="w-full h-full object-cover hidden">
                <span id="detailInisial" class="text-xl font-bold text-indigo-600"></span>
            </div>

            <h3 id="detailNama" class="text-lg font-bold text-slate-900"></h3>
            <p id="detailUsername" class="text-xs text-indigo-600 font-semibold mb-2"></p>
            <div id="detailRoleBadge" class="inline-block px-3 py-1 rounded-full text-xs font-bold border mb-4 uppercase"></div>

            <div class="text-left bg-slate-50 p-4 rounded-2xl border border-slate-200 mb-4">
                <span class="text-[11px] font-semibold text-slate-400 block uppercase mb-1">Deskripsi & Biografi:</span>
                <p id="detailDeskripsi" class="text-xs text-slate-700 leading-relaxed italic"></p>
            </div>

            <div class="text-[11px] text-slate-400 mb-4">
                Akun dibuat pada: <span id="detailCreatedAt" class="font-medium text-slate-600"></span>
            </div>

            <button onclick="closeDetailModal()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-medium transition shadow-sm cursor-pointer">Tutup</button>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT -->
    <script>
        // Skrip untuk menghilangkan notifikasi secara otomatis dalam 3 detik & membersihkan URL parameter
        setTimeout(function() {
            const notifBox = document.getElementById('notif-box');
            if (notifBox) {
                notifBox.style.opacity = '0';
                setTimeout(function() {
                    notifBox.style.display = 'none';
                    // Membersihkan parameter ?status=... dari URL agar saat di-refresh tidak memunculkan notif lagi
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 500);
            }
        }, 3000);

        const adminModal = document.getElementById('adminModal');
        const detailModal = document.getElementById('detailModal');

        function openAddModal() {
            document.getElementById('modalTitle').innerText = "➕ Tambah Admin Baru";
            document.getElementById('formAction').name = "tambah_admin";
            document.getElementById('formAction').value = "1";
            document.getElementById('adminId').value = "";
            document.getElementById('namaLengkap').value = "";
            document.getElementById('usernameLogin').value = "";
            document.getElementById('passwordInput').value = "";
            document.getElementById('passwordInput').required = true;
            document.getElementById('passwordNote').innerText = "*";
            document.getElementById('roleSelect').value = "admin";
            document.getElementById('deskripsiInput').value = "";
            document.getElementById('submitBtn').innerText = "Tambah Admin";
            
            adminModal.classList.remove('hidden');
        }

        function openEditModal(admin) {
            document.getElementById('modalTitle').innerText = "✏️ Edit Admin: " + admin.nama_lengkap;
            document.getElementById('formAction').name = "update_admin";
            document.getElementById('formAction').value = "1";
            document.getElementById('adminId').value = admin.id;
            document.getElementById('namaLengkap').value = admin.nama_lengkap;
            document.getElementById('usernameLogin').value = admin.username;
            document.getElementById('passwordInput').value = "";
            document.getElementById('passwordInput').required = false;
            document.getElementById('passwordNote').innerText = "(Kosongkan jika tidak diubah)";
            document.getElementById('roleSelect').value = admin.role;
            document.getElementById('deskripsiInput').value = admin.deskripsi || '';
            document.getElementById('submitBtn').innerText = "Simpan Perubahan";

            adminModal.classList.remove('hidden');
        }

        function closeModal() {
            adminModal.classList.add('hidden');
        }

        function openDetailModal(admin) {
            document.getElementById('detailNama').innerText = admin.nama_lengkap;
            document.getElementById('detailUsername').innerText = "@" + admin.username;
            document.getElementById('detailCreatedAt').innerText = admin.created_at;
            document.getElementById('detailDeskripsi').innerText = admin.deskripsi ? admin.deskripsi : "Tidak ada deskripsi yang ditambahkan.";

            const badge = document.getElementById('detailRoleBadge');
            badge.innerText = admin.role.replace('_', ' ');
            badge.className = "inline-block px-3 py-1 rounded-full text-xs font-bold border uppercase ";
            if (admin.role === 'super_admin') {
                badge.className += "bg-purple-50 text-purple-700 border-purple-200";
            } else if (admin.role === 'koordinator') {
                badge.className += "bg-amber-50 text-amber-700 border-amber-200";
            } else {
                badge.className += "bg-slate-100 text-slate-700 border-slate-200";
            }

            const fotoImg = document.getElementById('detailFoto');
            const inisialSpan = document.getElementById('detailInisial');
            if (admin.foto && admin.foto.trim() !== "") {
                fotoImg.src = "../assets/img/admin/" + admin.foto;
                fotoImg.classList.remove('hidden');
                inisialSpan.classList.add('hidden');
            } else {
                fotoImg.classList.add('hidden');
                inisialSpan.innerText = admin.nama_lengkap.substring(0, 2).toUpperCase();
                inisialSpan.classList.remove('hidden');
            }

            detailModal.classList.remove('hidden');
        }

        function closeDetailModal() {
            detailModal.classList.add('hidden');
        }

        window.onclick = function(event) {
            if (event.target == adminModal) closeModal();
            if (event.target == detailModal) closeDetailModal();
        }
    </script>

</div>

<?php require_once 'footer.php'; ?>