<?php
// 1. Mulai sesi (Wajib ada di baris paling atas sebelum output apa pun)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CEK SESI: Jika admin belum login, tendang kembali ke login.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Ambil informasi role dan ID admin yang sedang login
$role_admin = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'koordinator'; 
$is_super_admin = (strtolower($role_admin) === 'super_admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'Bahrodin1'));

// Ambil ID admin yang sedang login dari session (sesuaikan key session Anda, misal 'admin_id' atau 'id')
$current_admin_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;

require_once '../config.php';

// Pastikan kolom database tersedia (Opsional pengaman)
@$conn->query("ALTER TABLE laporan_harian ADD COLUMN IF NOT EXISTS masukan_koordinator TEXT NULL");
@$conn->query("ALTER TABLE laporan_harian ADD COLUMN IF NOT EXISTS admin_id INT(11) NULL");

// 3. Handle AJAX Request Pencarian / Filter jika ada
if (isset($_GET['ajax_search'])) {
    $keyword = "%" . trim($_GET['ajax_search']) . "%";
    $filter_pekerja = isset($_GET['filter_pekerja']) ? intval($_GET['filter_pekerja']) : 0;

    $query = "SELECT l.*, p.nama_lengkap AS nama_pekerja, p.spesialisasi, pl.nama_pelanggan, a.nama_lengkap AS nama_admin_koordinator 
              FROM laporan_harian l 
              JOIN pekerja p ON l.pekerja_id = p.id 
              LEFT JOIN pelanggan pl ON l.pelanggan_id = pl.id 
              LEFT JOIN admins a ON l.admin_id = a.id 
              WHERE (p.nama_lengkap LIKE ? OR l.kategori_tugas LIKE ? OR l.deskripsi_kegiatan LIKE ?)";

    if ($filter_pekerja > 0) {
        $query .= " AND l.pekerja_id = ?";
        $stmt = $conn->prepare($query . " ORDER BY l.id DESC");
        $stmt->bind_param("sssi", $keyword, $keyword, $keyword, $filter_pekerja);
    } else {
        $stmt = $conn->prepare($query . " ORDER BY l.id DESC");
        $stmt->bind_param("sss", $keyword, $keyword, $keyword);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            echo '<tr class="hover:bg-slate-50/50 transition">';
            echo '<td class="p-4 font-medium text-slate-500">' . $no++ . '</td>';
            echo '<td class="p-4 text-xs text-slate-500 whitespace-nowrap">' . date('d M Y', strtotime($row['tanggal_laporan'])) . '</td>';
            echo '<td class="p-4">';
            echo '<span class="font-bold block text-slate-900">' . htmlspecialchars($row['nama_pekerja']) . '</span>';
            echo '<span class="text-xs text-indigo-600 font-medium">' . htmlspecialchars($row['spesialisasi']) . '</span>';
            echo '</td>';
            echo '<td class="p-4 text-xs text-slate-700">' . (!empty($row['nama_pelanggan']) ? htmlspecialchars($row['nama_pelanggan']) : '<span class="text-slate-400 italic">Umum / Mandiri</span>') . '</td>';
            echo '<td class="p-4 text-xs font-semibold text-slate-800">' . htmlspecialchars($row['kategori_tugas']) . '</td>';
            echo '<td class="p-4 text-xs text-slate-600 max-w-xs">';
            echo '<p>' . htmlspecialchars($row['deskripsi_kegiatan']) . '</p>';
            if (!empty($row['kendala'])) {
                echo '<span class="text-rose-600 block mt-1"><strong>Kendala:</strong> ' . htmlspecialchars($row['kendala']) . '</span>';
            }
            if (!empty($row['masukan_koordinator'])) {
                $nama_penulis = !empty($row['nama_admin_koordinator']) ? htmlspecialchars($row['nama_admin_koordinator']) : 'Koordinator';
                echo '<span class="text-emerald-600 block mt-1">💬 <strong>Balasan (' . $nama_penulis . '):</strong> ' . htmlspecialchars($row['masukan_koordinator']) . '</span>';
            }
            if (!empty($row['latitude']) && !empty($row['longitude'])) {
                echo '<span class="text-indigo-600 block mt-1">📍 <strong>Lokasi:</strong> ' . htmlspecialchars($row['latitude']) . ', ' . htmlspecialchars($row['longitude']) . '</span>';
            }
            echo '</td>';
            echo '<td class="p-4 text-center">';
            if (!empty($row['foto_bukti'])) {
                echo '<a href="../assets/uploads/laporan/' . htmlspecialchars($row['foto_bukti']) . '" target="_blank" class="w-10 h-10 rounded-lg overflow-hidden border border-slate-300 inline-block shadow-xs hover:opacity-80 transition">';
                echo '<img src="../assets/uploads/laporan/' . htmlspecialchars($row['foto_bukti']) . '" class="w-full h-full object-cover">';
                echo '</a>';
            } else {
                echo '<span class="text-xs text-slate-400 italic">-</span>';
            }
            echo '</td>';
            echo '<td class="p-4 text-center space-x-1 whitespace-nowrap">';
            echo '<button onclick=\'bukaDetailModal(' . json_encode($row) . ')\' class="inline-block bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Detail</button> ';
            
            if ($is_super_admin) {
                echo '<button onclick=\'editLaporan(' . json_encode($row) . ')\' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button> ';
                echo '<a href="laporan_harian.php?hapus=' . $row['id'] . '" onclick="return confirm(\'Yakin ingin menghapus laporan harian ini?\');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" class="p-12 text-center text-slate-400 font-medium">Tidak ada data laporan harian yang ditemukan.</td></tr>';
    }
    exit;
}

$sukses = "";
$error = "";

// ==========================================
// PROSES POST (TAMBAH, EDIT, & BERI MASUKAN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Proses Simpan Masukan / Balasan oleh Koordinator / Admin (Mencatat admin_id yang login)
    if ($action == 'beri_masukan') {
        $id = intval($_POST['id']);
        $masukan_koordinator = trim($_POST['masukan_koordinator']);

        $stmt = $conn->prepare("UPDATE laporan_harian SET masukan_koordinator = ?, admin_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $masukan_koordinator, $current_admin_id, $id);
        if ($stmt->execute()) {
            $stmt->close();
            catatLogAktivitas($conn, "Memberikan balasan/masukan pada laporan harian ID: {$id}", "Laporan Harian");
            header("Location: laporan_harian.php?status=sukses_masukan");
            exit;
        } else {
            $error = "Gagal menyimpan masukan.";
        }
        $stmt->close();
    }

    // Proses Tambah & Edit Khusus Super Admin
    if ($is_super_admin) {
        $pekerja_id = intval($_POST['pekerja_id']);
        $pelanggan_id = !empty($_POST['pelanggan_id']) ? intval($_POST['pelanggan_id']) : null;
        $tanggal_laporan = trim($_POST['tanggal_laporan']);
        $kategori_tugas = trim($_POST['kategori_tugas']);
        $deskripsi_kegiatan = trim($_POST['deskripsi_kegiatan']);
        $kendala = trim($_POST['kendala']);
        $latitude = !empty($_POST['latitude']) ? trim($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? trim($_POST['longitude']) : null;

        $upload_dir = "../assets/uploads/laporan/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $foto_bukti_name = $_POST['old_foto'] ?? '';

        if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
            $ext = pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION);
            $foto_bukti_name = "laporan_" . time() . "_" . uniqid() . "." . $ext;
            move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $foto_bukti_name);
        }

        if ($action == 'tambah') {
            $stmt = $conn->prepare("INSERT INTO laporan_harian (pekerja_id, pelanggan_id, tanggal_laporan, kategori_tugas, deskripsi_kegiatan, kendala, foto_bukti, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssss", $pekerja_id, $pelanggan_id, $tanggal_laporan, $kategori_tugas, $deskripsi_kegiatan, $kendala, $foto_bukti_name, $latitude, $longitude);
            if ($stmt->execute()) {
                $stmt->close();
                catatLogAktivitas($conn, "Menambahkan laporan harian baru (Kategori: {$kategori_tugas})", "Laporan Harian");
                header("Location: laporan_harian.php?status=sukses_tambah");
                exit;
            } else {
                $error = "Gagal menyimpan laporan.";
            }
            $stmt->close();
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE laporan_harian SET pekerja_id=?, pelanggan_id=?, tanggal_laporan=?, kategori_tugas=?, deskripsi_kegiatan=?, kendala=?, foto_bukti=?, latitude=?, longitude=? WHERE id=?");
            $stmt->bind_param("iisssssssi", $pekerja_id, $pelanggan_id, $tanggal_laporan, $kategori_tugas, $deskripsi_kegiatan, $kendala, $foto_bukti_name, $latitude, $longitude, $id);
            if ($stmt->execute()) {
                $stmt->close();
                catatLogAktivitas($conn, "Memperbarui laporan harian ID: {$id}", "Laporan Harian");
                header("Location: laporan_harian.php?status=sukses_edit");
                exit;
            } else {
                $error = "Gagal memperbarui laporan.";
            }
            $stmt->close();
        }
    } else {
        if ($action == 'tambah' || $action == 'edit') {
            $error = "Akses ditolak. Hanya Super Admin yang dapat menambah atau mengedit laporan.";
        }
    }
}

// Tangkap status dari Redirect URL
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "Laporan harian berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_edit') {
        $sukses = "Laporan harian berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Laporan harian berhasil dihapus!";
    } elseif ($_GET['status'] == 'sukses_masukan') {
        $sukses = "Balasan/masukan koordinator berhasil disimpan!";
    }
}

// ==========================================
// PROSES HAPUS DATA (GET) - KHUSUS SUPER ADMIN
// ==========================================
if (isset($_GET['hapus'])) {
    if (!$is_super_admin) {
        header("Location: laporan_harian.php?status=akses_ditolak");
        exit;
    }

    $id_hapus = intval($_GET['hapus']);
    $q_foto = $conn->query("SELECT foto_bukti FROM laporan_harian WHERE id = $id_hapus");
    if ($q_foto && $q_foto->num_rows > 0) {
        $row_f = $q_foto->fetch_assoc();
        if (!empty($row_f['foto_bukti']) && file_exists("../assets/uploads/laporan/" . $row_f['foto_bukti'])) {
            unlink("../assets/uploads/laporan/" . $row_f['foto_bukti']);
        }
    }
    
    if ($conn->query("DELETE FROM laporan_harian WHERE id = $id_hapus")) {
        catatLogAktivitas($conn, "Menghapus laporan harian ID: {$id_hapus}", "Laporan Harian");
    }

    header("Location: laporan_harian.php?status=sukses_hapus");
    exit;
}

require_once 'header.php';

$result_pekerja = $conn->query("SELECT id, nama_lengkap, spesialisasi FROM pekerja ORDER BY nama_lengkap ASC");
$result_pelanggan = $conn->query("SELECT id, nama_pelanggan, layanan_dipilih FROM pelanggan ORDER BY nama_pelanggan ASC");

// Ambil data awal laporan dengan JOIN ke tabel admins
$result_laporan = $conn->query("SELECT l.*, p.nama_lengkap AS nama_pekerja, p.spesialisasi, pl.nama_pelanggan, a.nama_lengkap AS nama_admin_koordinator 
                                FROM laporan_harian l 
                                JOIN pekerja p ON l.pekerja_id = p.id 
                                LEFT JOIN pelanggan pl ON l.pelanggan_id = pl.id 
                                LEFT JOIN admins a ON l.admin_id = a.id 
                                ORDER BY l.id DESC");
?>

<!-- ================= PEMBUNGKUS UTAMA ================= -->
<div class="md:ml-64 min-h-screen flex flex-col justify-between transition-all duration-300">

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <span class="bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Manajemen Lapangan</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold mt-2 text-slate-900">Kelola Laporan Harian Petugas</h1>
                <p class="text-slate-600 text-sm mt-1">Pantau, evaluasi, dan berikan balasan laporan tugas harian pekerja.</p>
            </div>
            <?php if ($is_super_admin): ?>
                <button onclick="openModal('tambah')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-3 rounded-xl text-sm shadow-md transition inline-flex items-center gap-2 cursor-pointer">
                    <span>➕ Tambah Laporan Baru</span>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($sukses)): ?>
            <div id="notifAlert" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex justify-between items-center">
                <span>✅ <?= $sukses; ?></span>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold hover:text-emerald-900">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm">❌ <?= $error; ?></div>
        <?php endif; ?>

        <!-- Filter & Search Box -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Cari Laporan (Nama / Kegiatan)</label>
                    <input type="text" id="searchKeyword" placeholder="Ketik nama petugas atau aktivitas..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Berdasarkan Pekerja</label>
                    <select id="filterPekerja" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0">-- Semua Petugas --</option>
                        <?php 
                        $result_pekerja->data_seek(0);
                        while ($p = $result_pekerja->fetch_assoc()): ?>
                            <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['nama_lengkap']); ?> (<?= htmlspecialchars($p['spesialisasi']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabel Data Laporan Harian -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="p-4 font-semibold">No</th>
                            <th class="p-4 font-semibold">Tanggal</th>
                            <th class="p-4 font-semibold">Petugas / Spesialisasi</th>
                            <th class="p-4 font-semibold">Pelanggan / Klien</th>
                            <th class="p-4 font-semibold">Kategori Tugas</th>
                            <th class="p-4 font-semibold">Deskripsi & Balasan Koordinator</th>
                            <th class="p-4 font-semibold text-center">Foto Bukti</th>
                            <th class="p-4 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-slate-100 text-sm">
                        <?php if ($result_laporan && $result_laporan->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result_laporan->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-medium text-slate-500"><?= $no++; ?></td>
                                    <td class="p-4 text-xs text-slate-500 whitespace-nowrap"><?= date('d M Y', strtotime($row['tanggal_laporan'])); ?></td>
                                    <td class="p-4">
                                        <span class="font-bold block text-slate-900"><?= htmlspecialchars($row['nama_pekerja']); ?></span>
                                        <span class="text-xs text-indigo-600 font-medium"><?= htmlspecialchars($row['spesialisasi']); ?></span>
                                    </td>
                                    <td class="p-4 text-xs text-slate-700"><?= !empty($row['nama_pelanggan']) ? htmlspecialchars($row['nama_pelanggan']) : '<span class="text-slate-400 italic">Umum / Mandiri</span>'; ?></td>
                                    <td class="p-4 text-xs font-semibold text-slate-800"><?= htmlspecialchars($row['kategori_tugas']); ?></td>
                                    <td class="p-4 text-xs text-slate-600 max-w-xs">
                                        <p><?= htmlspecialchars($row['deskripsi_kegiatan']); ?></p>
                                        <?php if (!empty($row['kendala'])): ?>
                                            <span class="text-rose-600 block mt-1"><strong>Kendala:</strong> <?= htmlspecialchars($row['kendala']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($row['masukan_koordinator'])): ?>
                                            <?php $nama_penulis = !empty($row['nama_admin_koordinator']) ? htmlspecialchars($row['nama_admin_koordinator']) : 'Koordinator'; ?>
                                            <span class="text-emerald-600 block mt-1">💬 <strong>Balasan (<?= $nama_penulis; ?>):</strong> <?= htmlspecialchars($row['masukan_koordinator']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                            <span class="text-indigo-600 block mt-1">📍 <strong>Lokasi:</strong> <?= htmlspecialchars($row['latitude']); ?>, <?= htmlspecialchars($row['longitude']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if (!empty($row['foto_bukti'])): ?>
                                            <a href="../assets/uploads/laporan/<?= htmlspecialchars($row['foto_bukti']); ?>" target="_blank" class="w-10 h-10 rounded-lg overflow-hidden border border-slate-300 inline-block shadow-xs hover:opacity-80 transition">
                                                <img src="../assets/uploads/laporan/<?= htmlspecialchars($row['foto_bukti']); ?>" class="w-full h-full object-cover">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                        <button onclick='bukaDetailModal(<?= json_encode($row); ?>)' class="inline-block bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Detail</button>
                                        
                                        <?php if ($is_super_admin): ?>
                                            <button onclick='editLaporan(<?= json_encode($row); ?>)' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button>
                                            <a href="laporan_harian.php?hapus=<?= $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus laporan harian ini?');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="p-12 text-center text-slate-400 font-medium">Belum ada data laporan harian.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php require_once 'footer.php'; ?>

</div>

<?php if ($is_super_admin): ?>
<!-- MODAL TAMBAH / EDIT -->
<div id="laporanModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white shrink-0">
            <h3 id="modalTitle" class="font-bold text-lg">Tambah Laporan Harian</h3>
            <button onclick="closeModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-grow">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="old_foto" id="formOldFoto">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Petugas / Pekerja *</label>
                <select name="pekerja_id" id="pekerjaId" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                    <option value="">-- Pilih Pekerja --</option>
                    <?php 
                    $result_pekerja->data_seek(0);
                    while ($p = $result_pekerja->fetch_assoc()): ?>
                        <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['nama_lengkap']); ?> (<?= htmlspecialchars($p['spesialisasi']); ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Pelanggan / Klien (Opsional)</label>
                <select name="pelanggan_id" id="pelangganId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                    <option value="">-- Tanpa Klien / Umum --</option>
                    <?php 
                    $result_pelanggan->data_seek(0);
                    while ($pl = $result_pelanggan->fetch_assoc()): ?>
                        <option value="<?= $pl['id']; ?>"><?= htmlspecialchars($pl['nama_pelanggan']); ?> (<?= htmlspecialchars($pl['layanan_dipilih']); ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Laporan *</label>
                    <input type="date" name="tanggal_laporan" id="tanggalLaporan" value="<?= date('Y-m-d'); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori Tugas *</label>
                    <input type="text" name="kategori_tugas" id="kategoriTugas" placeholder="Contoh: Caregiver Lansia" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi Kegiatan *</label>
                <textarea name="deskripsi_kegiatan" id="deskripsiKegiatan" rows="3" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kendala / Catatan Khusus</label>
                <textarea name="kendala" id="kendala" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm"></textarea>
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-semibold text-slate-700">Koordinat Lokasi (Latitude & Longitude)</label>
                    <button type="button" onclick="ambilLokasiGPS()" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1 cursor-pointer">
                        <span>📍 Ambil Lokasi Saat Ini</span>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><input type="text" name="latitude" id="latitude" placeholder="Latitude" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs"></div>
                    <div><input type="text" name="longitude" id="longitude" placeholder="Longitude" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs"></div>
                </div>
                <p id="lokasiStatus" class="text-[11px] text-slate-500 italic"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Foto Bukti Dokumentasi</label>
                <input type="file" name="foto_bukti" accept="image/*" class="w-full text-xs text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white cursor-pointer">
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition text-sm cursor-pointer shrink-0">Simpan Laporan</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- MODAL DETAIL, PETA & BALASAN KOORDINATOR -->
<div id="detailModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white shrink-0">
            <h3 class="font-bold text-base">Detail Laporan & Balasan Koordinator</h3>
            <button onclick="tutupDetailModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto space-y-4 text-xs text-slate-700 flex-grow">
            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div>
                    <p class="font-semibold text-slate-400 uppercase">Petugas</p>
                    <p id="modalDetailPetugas" class="font-bold text-slate-900 mt-0.5 text-sm">-</p>
                </div>
                <div>
                    <p class="font-semibold text-slate-400 uppercase">Tanggal & Kategori</p>
                    <p id="modalDetailTanggalKategori" class="font-medium text-emerald-600 mt-0.5 text-sm">-</p>
                </div>
            </div>

            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Deskripsi Kegiatan</p>
                <div id="modalDetailDeskripsi" class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-slate-800 whitespace-pre-line">-</div>
            </div>

            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Kendala / Catatan Petugas</p>
                <div id="modalDetailKendala" class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-slate-800 whitespace-pre-line">-</div>
            </div>

            <!-- Form Beri Balasan / Masukan (Otomatis merekam admin_id yang login) -->
            <form action="" method="POST" class="space-y-2 bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100">
                <input type="hidden" name="action" value="beri_masukan">
                <input type="hidden" name="id" id="modalDetailId">
                <label class="block font-bold text-indigo-900">💬 Balasan / Masukan Koordinator:</label>
                <textarea name="masukan_koordinator" id="modalDetailMasukan" rows="3" placeholder="Tuliskan balasan, evaluasi, atau arahan..." class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl text-xs transition cursor-pointer">Kirim Balasan</button>
            </form>

            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Titik Lokasi (Google Maps)</p>
                <div class="w-full h-48 rounded-2xl overflow-hidden border border-slate-300 shadow-inner bg-slate-100 flex items-center justify-center">
                    <iframe id="googleMapFrame" class="w-full h-full border-0 hidden" loading="lazy" allowfullscreen></iframe>
                    <p id="noMapText" class="text-slate-400 italic text-xs hidden">Koordinat lokasi tidak disertakan pada laporan ini.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('laporanModal');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formId');
    const formOldFoto = document.getElementById('formOldFoto');
    const pekerjaId = document.getElementById('pekerjaId');
    const pelangganId = document.getElementById('pelangganId');
    const tanggalLaporan = document.getElementById('tanggalLaporan');
    const kategoriTugas = document.getElementById('kategoriTugas');
    const deskripsiKegiatan = document.getElementById('deskripsiKegiatan');
    const kendala = document.getElementById('kendala');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const lokasiStatus = document.getElementById('lokasiStatus');

    function openModal(mode) {
        if (mode === 'tambah') {
            modalTitle.textContent = 'Tambah Laporan Harian';
            formAction.value = 'tambah';
            formId.value = '';
            formOldFoto.value = '';
            pekerjaId.value = '';
            pelangganId.value = '';
            tanggalLaporan.value = '<?= date('Y-m-d'); ?>';
            kategoriTugas.value = '';
            deskripsiKegiatan.value = '';
            kendala.value = '';
            latitudeInput.value = '';
            longitudeInput.value = '';
            lokasiStatus.textContent = '';
        }
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal() {
        if (modal) modal.classList.add('hidden');
    }

    function editLaporan(data) {
        modalTitle.textContent = 'Edit Laporan Harian';
        formAction.value = 'edit';
        formId.value = data.id;
        formOldFoto.value = data.foto_bukti;
        pekerjaId.value = data.pekerja_id;
        pelangganId.value = data.pelanggan_id || '';
        tanggalLaporan.value = data.tanggal_laporan;
        kategoriTugas.value = data.kategori_tugas;
        deskripsiKegiatan.value = data.deskripsi_kegiatan;
        kendala.value = data.kendala || '';
        latitudeInput.value = data.latitude || '';
        longitudeInput.value = data.longitude || '';
        lokasiStatus.textContent = '';
        if (modal) modal.classList.remove('hidden');
    }

    const detailModal = document.getElementById('detailModal');
    const modalDetailId = document.getElementById('modalDetailId');
    const modalDetailPetugas = document.getElementById('modalDetailPetugas');
    const modalDetailTanggalKategori = document.getElementById('modalDetailTanggalKategori');
    const modalDetailDeskripsi = document.getElementById('modalDetailDeskripsi');
    const modalDetailKendala = document.getElementById('modalDetailKendala');
    const modalDetailMasukan = document.getElementById('modalDetailMasukan');
    const googleMapFrame = document.getElementById('googleMapFrame');
    const noMapText = document.getElementById('noMapText');

    function bukaDetailModal(data) {
        modalDetailId.value = data.id;
        modalDetailPetugas.textContent = data.nama_pekerja + ' (' + data.spesialisasi + ')';
        modalDetailTanggalKategori.textContent = data.tanggal_laporan + ' - ' + data.kategori_tugas;
        modalDetailDeskripsi.textContent = data.deskripsi_kegiatan || '-';
        modalDetailKendala.textContent = data.kendala ? data.kendala : 'Tidak ada kendala.';
        modalDetailMasukan.value = data.masukan_koordinator ? data.masukan_koordinator : '';

        if (data.latitude && data.longitude) {
            googleMapFrame.src = `https://maps.google.com/maps?q=${data.latitude},${data.longitude}&z=15&output=embed`;
            googleMapFrame.classList.remove('hidden');
            noMapText.classList.add('hidden');
        } else {
            googleMapFrame.src = '';
            googleMapFrame.classList.add('hidden');
            noMapText.classList.remove('hidden');
        }

        if (detailModal) detailModal.classList.remove('hidden');
    }

    function tutupDetailModal() {
        if (googleMapFrame) googleMapFrame.src = '';
        if (detailModal) detailModal.classList.add('hidden');
    }

    function ambilLokasiGPS() {
        if (!navigator.geolocation) {
            lokasiStatus.textContent = "❌ Browser Anda tidak Mendukung Geolocation.";
            return;
        }
        lokasiStatus.textContent = "⏳ Sedang mendeteksi posisi GPS...";
        navigator.geolocation.getCurrentPosition(
            (position) => {
                latitudeInput.value = position.coords.latitude;
                longitudeInput.value = position.coords.longitude;
                lokasiStatus.textContent = "✅ Berhasil mendapatkan koordinat lokasi!";
            },
            (error) => {
                lokasiStatus.textContent = "❌ Gagal mendeteksi lokasi.";
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    const searchInput = document.getElementById('searchKeyword');
    const filterSelect = document.getElementById('filterPekerja');
    const tableBody = document.getElementById('tableBody');

    function fetchLaporan() {
        const query = searchInput.value;
        const pekerjaIdFilter = filterSelect.value;
        fetch(`laporan_harian.php?ajax_search=${encodeURIComponent(query)}&filter_pekerja=${pekerjaIdFilter}`)
            .then(response => response.text())
            .then(html => { tableBody.innerHTML = html; });
    }

    if (searchInput) searchInput.addEventListener('input', fetchLaporan);
    if (filterSelect) filterSelect.addEventListener('change', fetchLaporan);
</script>