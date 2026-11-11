<?php
// 1. Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CEK SESI: Jika admin belum login atau sudah logout, tendang kembali ke halaman login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';
require_once 'header.php';

$sukses = "";
$error = "";

// Cek apakah pengguna saat ini adalah Koordinator (misalnya berdasarkan username 'kori' atau role 'koordinator')
$is_koordinator = (isset($_SESSION['admin_username']) && strtolower($_SESSION['admin_username']) === 'kori') || 
                  (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === 'koordinator');

// Directory untuk upload saat EDIT (assets/img/)
$upload_dir_img = "../assets/img/";
if (!is_dir($upload_dir_img)) {
    mkdir($upload_dir_img, 0777, true);
}

// Directory untuk pendaftaran (assets/uploads/)
$upload_dir_uploads = "../assets/uploads/";

// 1. PROSES TAMBAH PEKERJA (Blokir jika Koordinator)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_pekerja'])) {
    if ($is_koordinator) {
        $error = "Akses ditolak: Koordinator tidak memiliki hak akses untuk menambah data pekerja.";
    } else {
        $pendaftaran_id = intval($_POST['pendaftaran_id']);
        $spesialisasi = trim($_POST['spesialisasi']);
        $deskripsi = trim($_POST['deskripsi']);
        $pengalaman = trim($_POST['pengalaman']);
        $status_pekerja = trim($_POST['status_pekerja']);
        
        $tanggal_mulai = ($status_pekerja === 'Sedang Bertugas') ? trim($_POST['tanggal_mulai']) : null;
        $tanggal_selesai = ($status_pekerja === 'Sedang Bertugas') ? trim($_POST['tanggal_selesai']) : null;
        
        $jam_kerja = trim($_POST['jam_kerja_pilihan']);
        if ($jam_kerja === 'Kustom' && !empty($_POST['jam_kerja_kustom'])) {
            $jam_kerja = trim($_POST['jam_kerja_kustom']);
        }

        $q_cek_id = $conn->prepare("SELECT id FROM pekerja WHERE pendaftaran_id = ?");
        $q_cek_id->bind_param("i", $pendaftaran_id);
        $q_cek_id->execute();
        $q_cek_id->store_result();

        if ($q_cek_id->num_rows > 0) {
            $error = "Gagal: Peserta ini sudah terdaftar sebagai pekerja!";
            $q_cek_id->close();
        } else {
            $q_cek_id->close();

            $q_pendaftaran = $conn->prepare("SELECT nama_lengkap, pasfoto FROM pendaftaran WHERE id = ? LIMIT 1");
            $q_pendaftaran->bind_param("i", $pendaftaran_id);
            $q_pendaftaran->execute();
            $res_pend = $q_pendaftaran->get_result()->fetch_assoc();
            $q_pendaftaran->close();

            if (!$res_pend) {
                $error = "Gagal: Data pendaftaran tidak ditemukan!";
            } else {
                $nama_lengkap = $res_pend['nama_lengkap'];

                $foto_path = "assets/uploads/default.jpg";
                if (!empty($res_pend['pasfoto'])) {
                    $foto_path = "assets/uploads/" . $res_pend['pasfoto'];
                }

                $stmt = $conn->prepare("INSERT INTO pekerja (pendaftaran_id, nama_lengkap, spesialisasi, deskripsi, pengalaman, status_pekerja, tanggal_mulai, tanggal_selesai, jam_kerja, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssssss", $pendaftaran_id, $nama_lengkap, $spesialisasi, $deskripsi, $pengalaman, $status_pekerja, $tanggal_mulai, $tanggal_selesai, $jam_kerja, $foto_path);
                
                if ($stmt->execute()) {
                    catatLogAktivitas($conn, "Menambahkan data pekerja baru: " . $nama_lengkap, "Pekerja");
                    echo "<script>window.location='pekerja.php?status=sukses_tambah';</script>";
                    exit;
                } else {
                    $error = "Gagal menambahkan data pekerja ke database.";
                }
                $stmt->close();
            }
        }
    }
}

// 2. PROSES EDIT / UPDATE PEKERJA (Blokir jika Koordinator)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pekerja'])) {
    if ($is_koordinator) {
        $error = "Akses ditolak: Koordinator tidak memiliki hak akses untuk mengubah data pekerja.";
    } else {
        $id = intval($_POST['id']);
        $pendaftaran_id = intval($_POST['pendaftaran_id']);
        $spesialisasi = trim($_POST['spesialisasi']);
        $deskripsi = trim($_POST['deskripsi']);
        $pengalaman = trim($_POST['pengalaman']);
        $status_pekerja = trim($_POST['status_pekerja']);
        
        $tanggal_mulai = ($status_pekerja === 'Sedang Bertugas') ? trim($_POST['tanggal_mulai']) : null;
        $tanggal_selesai = ($status_pekerja === 'Sedang Bertugas') ? trim($_POST['tanggal_selesai']) : null;
        
        $jam_kerja = trim($_POST['jam_kerja_pilihan']);
        if ($jam_kerja === 'Kustom' && !empty($_POST['jam_kerja_kustom'])) {
            $jam_kerja = trim($_POST['jam_kerja_kustom']);
        }

        $q_pendaftaran = $conn->prepare("SELECT nama_lengkap FROM pendaftaran WHERE id = ? LIMIT 1");
        $q_pendaftaran->bind_param("i", $pendaftaran_id);
        $q_pendaftaran->execute();
        $res_pend = $q_pendaftaran->get_result()->fetch_assoc();
        $q_pendaftaran->close();
        
        $nama_lengkap = $res_pend ? $res_pend['nama_lengkap'] : '';

        $q_old = $conn->prepare("SELECT foto FROM pekerja WHERE id = ?");
        $q_old->bind_param("i", $id);
        $q_old->execute();
        $old_data = $q_old->get_result()->fetch_assoc();
        $q_old->close();

        $foto_path = $old_data['foto'];

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto_name = "pekerja_" . time() . "_" . uniqid() . "." . $ext;
            
            if (!empty($old_data['foto']) && file_exists("../" . $old_data['foto']) && strpos($old_data['foto'], 'default.jpg') === false) {
                @unlink("../" . $old_data['foto']);
            }

            move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir_img . $foto_name);
            $foto_path = "assets/img/" . $foto_name;
        }

        $stmt_up = $conn->prepare("UPDATE pekerja SET pendaftaran_id = ?, nama_lengkap = ?, spesialisasi = ?, deskripsi = ?, pengalaman = ?, status_pekerja = ?, tanggal_mulai = ?, tanggal_selesai = ?, jam_kerja = ?, foto = ? WHERE id = ?");
        $stmt_up->bind_param("isssssssssi", $pendaftaran_id, $nama_lengkap, $spesialisasi, $deskripsi, $pengalaman, $status_pekerja, $tanggal_mulai, $tanggal_selesai, $jam_kerja, $foto_path, $id);
        
        if ($stmt_up->execute()) {
            catatLogAktivitas($conn, "Memperbarui data pekerja: " . $nama_lengkap, "Pekerja");
            echo "<script>window.location='pekerja.php?status=sukses_update';</script>";
            exit;
        } else {
            $error = "Gagal memperbarui data pekerja.";
        }
        $stmt_up->close();
    }
}

// 3. PROSES HAPUS PEKERJA (Blokir jika Koordinator)
if (isset($_GET['hapus'])) {
    if ($is_koordinator) {
        $error = "Akses ditolak: Koordinator tidak memiliki hak akses untuk menghapus data pekerja.";
    } else {
        $id_hapus = intval($_GET['hapus']);

        $q_nama_del = $conn->prepare("SELECT nama_lengkap, foto FROM pekerja WHERE id = ?");
        $q_nama_del->bind_param("i", $id_hapus);
        $q_nama_del->execute();
        $res_file = $q_nama_del->get_result();
        $nama_pekerja_del = "ID: " . $id_hapus;
        
        if ($row_file = $res_file->fetch_assoc()) {
            $nama_pekerja_del = $row_file['nama_lengkap'];
            if (!empty($row_file['foto']) && file_exists("../" . $row_file['foto']) && strpos($row_file['foto'], 'default.jpg') === false) {
                @unlink("../" . $row_file['foto']);
            }
        }
        $q_nama_del->close();

        $stmt_del = $conn->prepare("DELETE FROM pekerja WHERE id = ?");
        $stmt_del->bind_param("i", $id_hapus);
        if ($stmt_del->execute()) {
            catatLogAktivitas($conn, "Menghapus data pekerja: " . $nama_pekerja_del, "Pekerja");
            echo "<script>window.location='pekerja.php?status=sukses_hapus';</script>";
            exit;
        }
        $stmt_del->close();
    }
}

// 4. PENANGANAN FILTER DATA
$filter_spesialisasi = isset($_GET['spesialisasi']) ? trim($_GET['spesialisasi']) : '';
$filter_status = isset($_GET['status_pekerja']) ? trim($_GET['status_pekerja']) : '';

$query_sql = "SELECT * FROM pekerja WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_spesialisasi)) {
    $query_sql .= " AND spesialisasi = ?";
    $params[] = $filter_spesialisasi;
    $types .= "s";
}

if (!empty($filter_status)) {
    $query_sql .= " AND status_pekerja = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$query_sql .= " ORDER BY id DESC";

if (!empty($params)) {
    $stmt_filter = $conn->prepare($query_sql);
    $stmt_filter->bind_param($types, ...$params);
    $stmt_filter->execute();
    $result_pekerja = $stmt_filter->get_result();
} else {
    $result_pekerja = $conn->query($query_sql);
}

$result_pendaftaran_tambah = $conn->query("
    SELECT id, nama_lengkap, pasfoto, program_pilihan 
    FROM pendaftaran 
    WHERE status = 'Diterima' 
    AND id NOT IN (SELECT pendaftaran_id FROM pekerja) 
    ORDER BY nama_lengkap ASC
");

$result_pendaftaran_all = $conn->query("
    SELECT id, nama_lengkap, pasfoto, program_pilihan 
    FROM pendaftaran 
    WHERE status = 'Diterima' 
    ORDER BY nama_lengkap ASC
");
?>

<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">
    <div>
        <!-- Topbar Mobile -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30 md:hidden">
            <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-lg hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="font-bold text-sm text-slate-800">Kelola Pekerja</span>
            <div class="w-6"></div>
        </header>

        <!-- Konten Utama -->
        <main class="p-6 md:p-8 w-full max-w-7xl mx-auto overflow-x-hidden">

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

                <?php if (isset($_GET['status'])): ?>
                    <?php if ($_GET['status'] == 'sukses_tambah'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                            <span>✅ Data pekerja baru berhasil ditambahkan!</span>
                        </div>
                    <?php elseif ($_GET['status'] == 'sukses_update'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                            <span>✅ Data pekerja berhasil diperbarui!</span>
                        </div>
                    <?php elseif ($_GET['status'] == 'sukses_hapus'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                            <span>✅ Data pekerja berhasil dihapus dari sistem.</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">Kelola Data Pekerja / Mitra</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola informasi tenaga medis, caregiver, babycare, dan daycare.</p>
                </div>
                <?php if (!$is_koordinator): ?>
                    <button onclick="openAddModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition shadow-sm inline-flex items-center gap-2">
                        <span>➕</span> Tambah Pekerja Baru
                    </button>
                <?php endif; ?>
            </div>

            <!-- FILTER DATA -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm mb-6">
                <form method="GET" action="pekerja.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Spesialisasi</label>
                        <select name="spesialisasi" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Semua Spesialisasi --</option>
                            <option value="daycare" <?php echo ($filter_spesialisasi === 'daycare') ? 'selected' : ''; ?>>Daycare</option>
                            <option value="babycare" <?php echo ($filter_spesialisasi === 'babycare') ? 'selected' : ''; ?>>Babycare</option>
                            <option value="homecare" <?php echo ($filter_spesialisasi === 'homecare') ? 'selected' : ''; ?>>Homecare</option>
                            <option value="caregiver" <?php echo ($filter_spesialisasi === 'caregiver') ? 'selected' : ''; ?>>Caregiver</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Status Pekerja</label>
                        <select name="status_pekerja" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Semua Status --</option>
                            <option value="Tersedia" <?php echo ($filter_status === 'Tersedia') ? 'selected' : ''; ?>>Tersedia</option>
                            <option value="Sedang Bertugas" <?php echo ($filter_status === 'Sedang Bertugas') ? 'selected' : ''; ?>>Sedang Bertugas</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-xl text-sm transition shadow-sm">
                            🔍 Filter Data
                        </button>
                        <a href="pekerja.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium py-2 px-4 rounded-xl text-sm transition flex items-center justify-center">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- TABEL PEKERJA -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="p-6 border-b border-slate-200 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Daftar Seluruh Pekerja / Mitra</h2>
                        <p class="text-xs text-slate-500">Daftar tenaga kerja aktif di sistem berdasarkan filter yang dipilih.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-4 text-center w-12">No</th>
                                <th class="p-4">Foto</th>
                                <th class="p-4">Nama Lengkap</th>
                                <th class="p-4">Spesialisasi</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Jadwal & Jam Kerja</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700">
                            <?php if ($result_pekerja->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result_pekerja->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4 font-medium text-slate-500 text-center"><?php echo $no++; ?></td>
                                        <td class="p-4">
                                            <?php 
                                                $img_src = !empty($row['foto']) ? $row['foto'] : 'assets/img/default.jpg';
                                                $final_img = "../" . $img_src;
                                            ?>
                                            <img src="<?php echo htmlspecialchars($final_img); ?>" alt="Foto" class="w-12 h-12 object-cover rounded-xl border border-slate-200 shadow-sm">
                                        </td>
                                        <td class="p-4 font-bold text-slate-900">
                                            <?php echo htmlspecialchars($row['nama_lengkap']); ?>
                                            <?php if (!empty($row['pengalaman'])): ?>
                                                <span class="block text-[11px] font-normal text-slate-500">Exp: <?php echo htmlspecialchars($row['pengalaman']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-indigo-50 text-indigo-700 text-xs font-semibold px-2.5 py-1 rounded-full uppercase">
                                                <?php echo htmlspecialchars($row['spesialisasi']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap">
                                            <?php if ($row['status_pekerja'] == 'Tersedia'): ?>
                                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    Tersedia
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    Sedang Bertugas
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-xs space-y-1 whitespace-nowrap">
                                            <?php if ($row['status_pekerja'] == 'Sedang Bertugas' && !empty($row['tanggal_mulai'])): ?>
                                                <span class="block text-slate-600">📅 <?php echo htmlspecialchars($row['tanggal_mulai']); ?> s/d <?php echo htmlspecialchars($row['tanggal_selesai']); ?></span>
                                            <?php else: ?>
                                                <span class="block text-slate-400 italic">Tersedia</span>
                                            <?php endif; ?>
                                            <span class="block text-slate-500 font-medium">⏰ <?php echo htmlspecialchars($row['jam_kerja']); ?></span>
                                        </td>
                                        <td class="p-4 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <button onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg transition">
                                                    👁️ Detail
                                                </button>
                                                <?php if (!$is_koordinator): ?>
                                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg transition">
                                                        ✏️ Edit
                                                    </button>
                                                    <a href="pekerja.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus data pekerja ini?');" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg transition">
                                                        🗑️ Hapus
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-slate-400">Tidak ada data pekerja yang sesuai dengan filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <?php if (!$is_koordinator): ?>
    <!-- MODAL FORM (TAMBAH & EDIT) - Hanya dirender jika bukan Koordinator -->
    <div id="pekerjaModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all max-h-[90vh] flex flex-col">
            <div class="bg-indigo-900 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
                <h3 id="modalTitle" class="font-bold text-sm">➕ Tambah Pekerja Baru</h3>
                <button onclick="closeModal()" class="text-slate-300 hover:text-white p-1 rounded-lg transition">✕</button>
            </div>

            <form id="pekerjaForm" action="pekerja.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
                <input type="hidden" id="formAction" name="tambah_pekerja" value="1">
                <input type="hidden" id="pekerjaId" name="id" value="">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Pendaftar *</label>
                        
                        <select id="pendaftaranIdSelect" name="pendaftaran_id" required onchange="previewPasfoto(this)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed">
                            <option value="" disabled selected>-- Pilih Peserta --</option>
                            <?php 
                            if ($result_pendaftaran_tambah->num_rows > 0) {
                                while ($p_row = $result_pendaftaran_tambah->fetch_assoc()): 
                            ?>
                                    <option class="opt-tambah" value="<?php echo $p_row['id']; ?>" data-pasfoto="<?php echo htmlspecialchars($p_row['pasfoto']); ?>">
                                        <?php echo htmlspecialchars($p_row['nama_lengkap']); ?> (<?php echo htmlspecialchars($p_row['program_pilihan']); ?>)
                                    </option>
                            <?php 
                                endwhile; 
                            } 
                            
                            if ($result_pendaftaran_all->num_rows > 0) {
                                $result_pendaftaran_all->data_seek(0);
                                while ($p_row_all = $result_pendaftaran_all->fetch_assoc()): 
                            ?>
                                    <option class="opt-edit hidden" value="<?php echo $p_row_all['id']; ?>" data-pasfoto="<?php echo htmlspecialchars($p_row_all['pasfoto']); ?>">
                                        <?php echo htmlspecialchars($p_row_all['nama_lengkap']); ?> (<?php echo htmlspecialchars($p_row_all['program_pilihan']); ?>)
                                    </option>
                            <?php 
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Preview Pasfoto -->
                    <div class="flex items-center space-x-3 pt-4 sm:pt-0">
                        <div class="w-12 h-12 rounded-xl overflow-hidden border-2 border-indigo-200 bg-slate-100 flex-shrink-0 shadow-sm flex items-center justify-center">
                            <img id="previewPasfotoImg" src="../assets/img/default.jpg" alt="Preview" class="w-full h-full object-cover">
                        </div>
                        <span id="infoFotoLabel" class="text-[11px] text-slate-500">Pasfoto otomatis dari data pendaftaran.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Spesialisasi *</label>
                        <select id="spesialisasiSelect" name="spesialisasi" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled selected>-- Pilih Spesialisasi --</option>
                            <option value="daycare">Daycare</option>
                            <option value="babycare">Babycare</option>
                            <option value="homecare">Homecare</option>
                            <option value="caregiver">Caregiver</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status Pekerja *</label>
                        <select id="statusSelect" name="status_pekerja" required onchange="toggleKontrakFields(this)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="Tersedia">Tersedia</option>
                            <option value="Sedang Bertugas">Sedang Bertugas</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pengalaman Kerja</label>
                        <input type="text" id="pengalamanInput" name="pengalaman" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: 2 Tahun">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jam Kerja / Sistem *</label>
                        <select id="jamKerjaSelect" name="jam_kerja_pilihan" required onchange="checkJamKerjaCustom(this)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled selected>-- Pilih Sistem Jam Kerja --</option>
                            <option value="Shift Pagi (07:00 - 15:00 WIB)">Shift Pagi (07:00 - 15:00 WIB)</option>
                            <option value="Shift Siang (15:00 - 23:00 WIB)">Shift Siang (15:00 - 23:00 WIB)</option>
                            <option value="Shift Malam (23:00 - 07:00 WIB)">Shift Malam (23:00 - 07:00 WIB)</option>
                            <option value="Full Time (08:00 - 17:00 WIB)">Full Time (08:00 - 17:00 WIB)</option>
                            <option value="Live-in (24 Jam / Menginap)">Live-in (24 Jam / Menginap)</option>
                            <option value="Kustom">Atur Jam Kerja Manual / Kustom...</option>
                        </select>
                    </div>
                </div>

                <div id="jamKerjaKustomContainer" class="hidden">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Ketik Jam Kerja Kustom</label>
                    <input type="text" id="jamKerjaKustomInput" name="jam_kerja_kustom" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: 09:00 - 16:00 WIB">
                </div>

                <div id="kontrakContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-4 hidden">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai Kontrak *</label>
                        <input type="date" id="tglMulaiInput" name="tanggal_mulai" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai Kontrak *</label>
                        <input type="date" id="tglSelesaiInput" name="tanggal_selesai" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi / Keterangan Profil</label>
                    <textarea id="deskripsiInput" name="deskripsi" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Tuliskan deskripsi singkat..."></textarea>
                </div>

                <div id="uploadFotoContainer" class="hidden pt-2 border-t border-slate-100">
                    <label class="block text-xs font-semibold text-indigo-600 mb-1">Ganti Foto Pekerja (Opsional - khusus mode edit)</label>
                    <input type="file" name="foto" accept="image/*" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-xs file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <span class="text-[10px] text-slate-400 mt-1 block">Foto baru akan disimpan ke folder assets/img/</span>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-medium transition">Batal</button>
                    <button type="submit" id="submitBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL DETAIL -->
    <div id="detailModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all text-center p-6 relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeDetailModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg transition">✕</button>
            
            <div class="w-24 h-24 rounded-2xl overflow-hidden border-4 border-indigo-100 mx-auto mb-4 shadow-md bg-slate-100 flex items-center justify-center">
                <img id="detailFoto" src="" alt="Foto Pekerja" class="w-full h-full object-cover hidden">
                <span id="detailInisial" class="text-2xl font-bold text-indigo-600"></span>
            </div>

            <h3 id="detailNama" class="text-lg font-bold text-slate-900"></h3>
            <div id="detailSpesialisasiBadge" class="inline-block px-3 py-1 rounded-full text-xs font-bold border mb-2 uppercase bg-indigo-50 text-indigo-700 border-indigo-200"></div>
            <div id="detailStatusBadge" class="block mb-4"></div>

            <div class="text-left space-y-2.5 bg-slate-50 p-4 rounded-2xl border border-slate-200 mb-4 text-xs">
                <div><span class="font-semibold text-slate-400 block uppercase">Pengalaman:</span> <span id="detailPengalaman" class="text-slate-800 font-medium"></span></div>
                <div><span class="font-semibold text-slate-400 block uppercase">Jam Kerja / Sistem:</span> <span id="detailJamKerja" class="text-slate-800 font-medium"></span></div>
                <div id="detailKontrakWrapper"><span class="font-semibold text-slate-400 block uppercase">Periode Kontrak:</span> <span id="detailKontrak" class="text-slate-800 font-medium"></span></div>
                <div><span class="font-semibold text-slate-400 block uppercase">Deskripsi & Biografi:</span> <p id="detailDeskripsi" class="text-slate-700 leading-relaxed italic mt-1"></p></div>
            </div>

            <button onclick="closeDetailModal()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-medium transition shadow-sm">Tutup</button>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        const detailModal = document.getElementById('detailModal');
        
        <?php if (!$is_koordinator): ?>
        const pekerjaModal = document.getElementById('pekerjaModal');
        const previewPasfotoImg = document.getElementById('previewPasfotoImg');
        const uploadFotoContainer = document.getElementById('uploadFotoContainer');
        const jamKerjaSelect = document.getElementById('jamKerjaSelect');
        const jamKerjaKustomContainer = document.getElementById('jamKerjaKustomContainer');
        const jamKerjaKustomInput = document.getElementById('jamKerjaKustomInput');
        const statusSelect = document.getElementById('statusSelect');
        const kontrakContainer = document.getElementById('kontrakContainer');
        const tglMulaiInput = document.getElementById('tglMulaiInput');
        const tglSelesaiInput = document.getElementById('tglSelesaiInput');
        
        const formAction = document.getElementById('formAction');
        const pekerjaId = document.getElementById('pekerjaId');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const pendaftaranIdSelect = document.getElementById('pendaftaranIdSelect');
        const infoFotoLabel = document.getElementById('infoFotoLabel');
        <?php endif; ?>

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

        <?php if (!$is_koordinator): ?>
        function toggleKontrakFields(selectStatus) {
            if (selectStatus.value === 'Sedang Bertugas') {
                kontrakContainer.classList.remove('hidden');
                tglMulaiInput.required = true;
                tglSelesaiInput.required = true;
            } else {
                kontrakContainer.classList.add('hidden');
                tglMulaiInput.required = false;
                tglSelesaiInput.required = false;
                tglMulaiInput.value = '';
                tglSelesaiInput.value = '';
            }
        }

        function previewPasfoto(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const pasfotoFilename = selectedOption.getAttribute('data-pasfoto');
            if (pasfotoFilename && pasfotoFilename.trim() !== "") {
                previewPasfotoImg.src = "../assets/uploads/" + pasfotoFilename;
            }
        }

        function checkJamKerjaCustom(selectEl) {
            if (selectEl.value === 'Kustom') {
                jamKerjaKustomContainer.classList.remove('hidden');
                jamKerjaKustomInput.required = true;
            } else {
                jamKerjaKustomContainer.classList.add('hidden');
                jamKerjaKustomInput.required = false;
                jamKerjaKustomInput.value = '';
            }
        }

        function openAddModal() {
            modalTitle.innerText = "➕ Tambah Pekerja Baru";
            formAction.name = "tambah_pekerja";
            formAction.value = "1";
            pekerjaId.value = "";
            
            document.querySelectorAll('.opt-edit').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.opt-tambah').forEach(el => el.classList.remove('hidden'));

            pendaftaranIdSelect.value = "";
            pendaftaranIdSelect.disabled = false; 

            document.getElementById('spesialisasiSelect').value = "";
            statusSelect.value = "Tersedia";
            toggleKontrakFields(statusSelect);
            document.getElementById('pengalamanInput').value = "";
            document.getElementById('deskripsiInput').value = "";
            jamKerjaSelect.value = "";
            jamKerjaKustomContainer.classList.add('hidden');
            jamKerjaKustomInput.value = "";
            previewPasfotoImg.src = "../assets/img/default.jpg";
            infoFotoLabel.innerText = "Pasfoto otomatis dari data pendaftaran.";
            
            uploadFotoContainer.classList.add('hidden');
            submitBtn.innerText = "Tambah Pekerja";

            pekerjaModal.classList.remove('hidden');
        }

        function openEditModal(pekerja) {
            modalTitle.innerText = "✏️ Edit Pekerja: " + pekerja.nama_lengkap;
            formAction.name = "update_pekerja";
            formAction.value = "1";
            pekerjaId.value = pekerja.id;
            
            document.querySelectorAll('.opt-tambah').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.opt-edit').forEach(el => el.classList.remove('hidden'));

            pendaftaranIdSelect.value = pekerja.pendaftaran_id;
            pendaftaranIdSelect.disabled = true; 

            document.getElementById('spesialisasiSelect').value = pekerja.spesialisasi.toLowerCase();
            statusSelect.value = pekerja.status_pekerja;
            toggleKontrakFields(statusSelect);

            document.getElementById('pengalamanInput').value = pekerja.pengalaman || '';
            document.getElementById('deskripsiInput').value = pekerja.deskripsi || '';
            
            let foundOption = false;
            for (let i = 0; i < jamKerjaSelect.options.length; i++) {
                if (jamKerjaSelect.options[i].value === pekerja.jam_kerja) {
                    jamKerjaSelect.value = pekerja.jam_kerja;
                    foundOption = true;
                    break;
                }
            }

            if (!foundOption && pekerja.jam_kerja) {
                jamKerjaSelect.value = 'Kustom';
                jamKerjaKustomContainer.classList.remove('hidden');
                jamKerjaKustomInput.value = pekerja.jam_kerja;
                jamKerjaKustomInput.required = true;
            } else {
                jamKerjaKustomContainer.classList.add('hidden');
                jamKerjaKustomInput.value = '';
            }

            if (pekerja.status_pekerja === 'Sedang Bertugas') {
                tglMulaiInput.value = pekerja.tanggal_mulai || '';
                tglSelesaiInput.value = pekerja.tanggal_selesai || '';
            }

            if (pekerja.foto && pekerja.foto.trim() !== "") {
                previewPasfotoImg.src = "../" + pekerja.foto;
            } else {
                previewPasfotoImg.src = "../assets/img/default.jpg";
            }
            infoFotoLabel.innerText = "Foto pekerja saat ini.";

            uploadFotoContainer.classList.remove('hidden');
            submitBtn.innerText = "Simpan Perubahan";

            pekerjaModal.classList.remove('hidden');
        }

        function closeModal() {
            pendaftaranIdSelect.disabled = false;
            pekerjaModal.classList.add('hidden');
        }
        <?php endif; ?>

        function openDetailModal(pekerja) {
            document.getElementById('detailNama').innerText = pekerja.nama_lengkap;
            document.getElementById('detailSpesialisasiBadge').innerText = "Spesialisasi: " + pekerja.spesialisasi;
            document.getElementById('detailPengalaman').innerText = pekerja.pengalaman ? pekerja.pengalaman : "Tidak dicantumkan";
            document.getElementById('detailJamKerja').innerText = pekerja.jam_kerja ? pekerja.jam_kerja : "-";
            
            const kontrakWrapper = document.getElementById('detailKontrakWrapper');
            if (pekerja.status_pekerja === 'Sedang Bertugas' && pekerja.tanggal_mulai) {
                kontrakWrapper.style.display = 'block';
                document.getElementById('detailKontrak').innerText = pekerja.tanggal_mulai + " s/d " + pekerja.tanggal_selesai;
            } else {
                kontrakWrapper.style.display = 'none';
            }

            document.getElementById('detailDeskripsi').innerText = pekerja.deskripsi ? pekerja.deskripsi : "Tidak ada deskripsi yang ditambahkan.";

            const sBadge = document.getElementById('detailStatusBadge');
            if (pekerja.status_pekerja === 'Tersedia') {
                sBadge.innerHTML = '<span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Tersedia</span>';
            } else {
                sBadge.innerHTML = '<span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Sedang Bertugas</span>';
            }

            const fotoImg = document.getElementById('detailFoto');
            const inisialSpan = document.getElementById('detailInisial');
            if (pekerja.foto && pekerja.foto.trim() !== "") {
                fotoImg.src = "../" + pekerja.foto;
                fotoImg.classList.remove('hidden');
                inisialSpan.classList.add('hidden');
            } else {
                fotoImg.classList.add('hidden');
                inisialSpan.innerText = pekerja.nama_lengkap.substring(0, 2).toUpperCase();
                inisialSpan.classList.remove('hidden');
            }

            detailModal.classList.remove('hidden');
        }

        function closeDetailModal() {
            detailModal.classList.add('hidden');
        }

        window.onclick = function(event) {
            <?php if (!$is_koordinator): ?>
            if (event.target == pekerjaModal) closeModal();
            <?php endif; ?>
            if (event.target == detailModal) closeDetailModal();
        }

        <?php if (!$is_koordinator): ?>
        document.getElementById('pekerjaForm').addEventListener('submit', function() {
            if (pendaftaranIdSelect.disabled) {
                pendaftaranIdSelect.disabled = false;
            }
        });
        <?php endif; ?>
    </script>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 w-full mt-auto">
        &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
    </footer>
</div>

<?php
require_once 'footer.php';
?>