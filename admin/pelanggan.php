<?php
// 1. Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek Sesi Login Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Deteksi apakah user yang login adalah Koordinator
$is_koordinator = (isset($_SESSION['admin_username']) && strtolower($_SESSION['admin_username']) === 'kori') || 
                  (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === 'koordinator');

// 3. Handle AJAX Request Pencarian Live Search jika ada
if (isset($_GET['ajax_search'])) {
    require_once '../config.php';

    $keyword = "%" . trim($_GET['ajax_search']) . "%";
    $stmt = $conn->prepare("SELECT * FROM pelanggan WHERE nama_pelanggan LIKE ? OR telepon LIKE ? OR layanan_dipilih LIKE ? ORDER BY id DESC");
    $stmt->bind_param("sss", $keyword, $keyword, $keyword);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $badge_status = ($row['status'] == 'Aktif') ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600';
            
            echo '<tr class="hover:bg-slate-50/50 transition">';
            echo '<td class="p-4 font-medium text-slate-500">' . $no++ . '</td>';
            echo '<td class="p-4">';
            echo '<span class="font-bold block text-slate-900">' . htmlspecialchars($row['nama_pelanggan']) . '</span>';
            echo '<span class="text-xs text-slate-500">📞 ' . htmlspecialchars($row['telepon']) . '</span>';
            echo '</td>';
            echo '<td class="p-4 text-xs font-semibold text-indigo-600">' . htmlspecialchars($row['layanan_dipilih']) . '</td>';
            echo '<td class="p-4 text-xs text-slate-600 max-w-xs">';
            echo htmlspecialchars($row['alamat']);
            if (!empty($row['koordinat'])) {
                echo '<br><span class="text-[10px] text-indigo-500 font-mono">📍 ' . htmlspecialchars($row['koordinat']) . '</span>';
            }
            echo '</td>';
            echo '<td class="p-4 text-xs text-slate-500 whitespace-nowrap">';
            echo 'Mulai: ' . date('d M Y', strtotime($row['tanggal_mulai'])) . '<br>';
            if (!empty($row['tanggal_selesai'])) {
                echo 'Selesai: ' . date('d M Y', strtotime($row['tanggal_selesai']));
            }
            echo '</td>';
            echo '<td class="p-4 whitespace-nowrap"><span class="' . $badge_status . ' px-2.5 py-1 rounded-full text-xs font-bold">' . htmlspecialchars($row['status']) . '</span></td>';
            
            echo '<td class="p-4 text-center space-x-1 whitespace-nowrap">';
            echo '<button onclick=\'openDetailModal(' . json_encode($row) . ')\' class="inline-block bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">👁️ Detail</button>';
            
            if (!$is_koordinator) {
                echo ' <button onclick=\'editPelanggan(' . json_encode($row) . ')\' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button> ';
                echo '<a href="pelanggan.php?hapus=' . $row['id'] . '" onclick="return confirm(\'Yakin ingin menghapus data pelanggan ini?\');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium">Tidak ada data pelanggan yang ditemukan.</td></tr>';
    }
    exit;
}

// 4. Muat Konfigurasi
require_once '../config.php';

// Pastikan tabel pelanggan memiliki kolom koordinat
$check_col = $conn->query("SHOW COLUMNS FROM pelanggan LIKE 'koordinat'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE pelanggan ADD COLUMN koordinat VARCHAR(100) DEFAULT NULL AFTER alamat");
}

$sukses = "";
$error = "";

// Tangkap status dari URL setelah redirect (PRG Pattern)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "Data pelanggan berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_update') {
        $sukses = "Data pelanggan berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Data pelanggan berhasil dihapus.";
    } elseif ($_GET['status'] == 'gagal_simpan') {
        $error = "Gagal menyimpan data pelanggan.";
    } elseif ($_GET['status'] == 'gagal_update') {
        $error = "Gagal memperbarui data pelanggan.";
    } elseif ($_GET['status'] == 'data_tidak_lengkap') {
        $error = "Mohon lengkapi data yang wajib diisi.";
    } elseif ($_GET['status'] == 'akses_ditolak') {
        $error = "Akses ditolak: Koordinator tidak memiliki izin untuk melakukan aksi tersebut.";
    }
}

// ==========================================
// PROSES TAMBAH & EDIT PELANGGAN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($is_koordinator) {
        header("Location: pelanggan.php?status=akses_ditolak");
        exit;
    }

    $action = $_POST['action'];
    $nama_pelanggan = trim($_POST['nama_pelanggan']);
    $telepon = trim($_POST['telepon']);
    
    $provinsi = trim($_POST['provinsi_nama'] ?? '');
    $kabupaten = trim($_POST['kabupaten_nama'] ?? '');
    $kecamatan = trim($_POST['kecamatan_nama'] ?? '');
    $alamat_detail = trim($_POST['alamat_detail']);
    $alamat_lengkap = "$alamat_detail, Kec. $kecamatan, Kab/Kota $kabupaten, Prov. $provinsi";

    $layanan_dipilih = trim($_POST['layanan_dipilih']);
    $tanggal_mulai = trim($_POST['tanggal_mulai']);
    $tanggal_selesai = !empty($_POST['tanggal_selesai']) ? trim($_POST['tanggal_selesai']) : null;
    $status = trim($_POST['status']);
    
    $koordinat = trim($_POST['koordinat'] ?? '');

    if (!empty($nama_pelanggan) && !empty($telepon) && !empty($layanan_dipilih)) {
        if ($action == 'tambah') {
            $stmt = $conn->prepare("INSERT INTO pelanggan (nama_pelanggan, telepon, alamat, koordinat, layanan_dipilih, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $nama_pelanggan, $telepon, $alamat_lengkap, $koordinat, $layanan_dipilih, $tanggal_mulai, $tanggal_selesai, $status);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: pelanggan.php?status=sukses_tambah");
                exit;
            } else {
                $stmt->close();
                header("Location: pelanggan.php?status=gagal_simpan");
                exit;
            }
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE pelanggan SET nama_pelanggan=?, telepon=?, alamat=?, koordinat=?, layanan_dipilih=?, tanggal_mulai=?, tanggal_selesai=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $nama_pelanggan, $telepon, $alamat_lengkap, $koordinat, $layanan_dipilih, $tanggal_mulai, $tanggal_selesai, $status, $id);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: pelanggan.php?status=sukses_update");
                exit;
            } else {
                $stmt->close();
                header("Location: pelanggan.php?status=gagal_update");
                exit;
            }
        }
    } else {
        header("Location: pelanggan.php?status=data_tidak_lengkap");
        exit;
    }
}

// ==========================================
// PROSES HAPUS PELANGGAN (GET)
// ==========================================
if (isset($_GET['hapus'])) {
    if ($is_koordinator) {
        header("Location: pelanggan.php?status=akses_ditolak");
        exit;
    }
    $id_hapus = intval($_GET['hapus']);
    $conn->query("DELETE FROM pelanggan WHERE id = $id_hapus");
    header("Location: pelanggan.php?status=sukses_hapus");
    exit;
}

require_once 'header.php';

// Ambil data awal pelanggan
$result_pelanggan = $conn->query("SELECT * FROM pelanggan ORDER BY id DESC");
?>

<!-- Panggil CSS & JS Leaflet untuk Peta Interaktif -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- ================= PEMBUNGKUS UTAMA AGAR TIDAK TERTUTUP SIDEBAR ================= -->
<div class="md:ml-64 min-h-screen flex flex-col justify-between transition-all duration-300">

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">
        
        <!-- Header Title & Tombol Tambah -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <span class="bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Manajemen Klien</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold mt-2 text-slate-900">Kelola Data Pelanggan</h1>
                <p class="text-slate-600 text-sm mt-1">Daftar pelanggan pengguna layanan LPK lengkap dengan titik koordinat peta lokasi.</p>
            </div>
            <?php if (!$is_koordinator): ?>
                <button onclick="openModal('tambah')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-3 rounded-xl text-sm shadow-md transition inline-flex items-center gap-2 cursor-pointer">
                    <span>➕ Tambah Pelanggan Baru</span>
                </button>
            <?php endif; ?>
        </div>

        <!-- Notifikasi Otomatis Hilang dalam 3 Detik dengan ID "notif-box" -->
        <?php if (!empty($sukses)): ?>
            <div id="notif-box" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm transition-opacity duration-500">✅ <?= $sukses; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div id="notif-box" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm transition-opacity duration-500">❌ <?= $error; ?></div>
        <?php endif; ?>

            <!-- Live Search Box -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Cari Pelanggan (Nama / No Telepon / Layanan / Alamat)</label>
                <input type="text" id="searchKeyword" onkeyup="filterPelanggan()" placeholder="Ketik nama, telepon, layanan, atau alamat..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Tabel Data Pelanggan -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="p-4 font-semibold">No</th>
                            <th class="p-4 font-semibold">Nama & Kontak</th>
                            <th class="p-4 font-semibold">Layanan Dipilih</th>
                            <th class="p-4 font-semibold">Alamat Lengkap</th>
                            <th class="p-4 font-semibold">Periode Layanan</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-slate-100 text-sm">
                        <?php if ($result_pelanggan && $result_pelanggan->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result_pelanggan->fetch_assoc()): ?>
                                <!-- Tambahkan kelas search-item dan data-search -->
                                <?php 
                                    $searchString = strtolower($row['nama_pelanggan'] . ' ' . $row['telepon'] . ' ' . $row['layanan_dipilih'] . ' ' . $row['alamat']);
                                ?>
                                <tr class="search-item hover:bg-slate-50/50 transition" data-search="<?= htmlspecialchars($searchString); ?>">
                                    <td class="p-4 font-medium text-slate-500"><?= $no++; ?></td>
                                    <td class="p-4">
                                        <span class="font-bold block text-slate-900"><?= htmlspecialchars($row['nama_pelanggan']); ?></span>
                                        <span class="text-xs text-slate-500">📞 <?= htmlspecialchars($row['telepon']); ?></span>
                                    </td>
                                    <td class="p-4 text-xs font-semibold text-indigo-600"><?= htmlspecialchars($row['layanan_dipilih']); ?></td>
                                    <td class="p-4 text-xs text-slate-600 max-w-xs">
                                        <?= htmlspecialchars($row['alamat']); ?>
                                        <?php if (!empty($row['koordinat'])): ?>
                                            <br><span class="text-[10px] text-indigo-500 font-mono">📍 <?= htmlspecialchars($row['koordinat']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-xs text-slate-500 whitespace-nowrap">
                                        Mulai: <?= date('d M Y', strtotime($row['tanggal_mulai'])); ?><br>
                                        <?php if (!empty($row['tanggal_selesai'])): ?>
                                            Selesai: <?= date('d M Y', strtotime($row['tanggal_selesai'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 whitespace-nowrap">
                                        <span class="<?= ($row['status'] == 'Aktif') ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'; ?> px-2.5 py-1 rounded-full text-xs font-bold">
                                            <?= htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                        <button onclick='openDetailModal(<?= json_encode($row); ?>)' class="inline-block bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">👁️ Detail</button>
                                        
                                        <?php if (!$is_koordinator): ?>
                                            <button onclick='editPelanggan(<?= json_encode($row); ?>)' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button>
                                            <a href="pelanggan.php?hapus=<?= $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus data pelanggan ini?');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr id="emptyRow"><td colspan="7" class="p-12 text-center text-slate-400 font-medium">Belum ada data pelanggan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER ADMIN -->
    <?php require_once 'footer.php'; ?>

</div>

<!-- MODAL DETAIL PELANGGAN -->
<div id="detailModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="bg-emerald-600 px-6 py-4 flex justify-between items-center text-white flex-shrink-0">
            <h3 class="font-bold text-lg">Detail Informasi Pelanggan</h3>
            <button onclick="closeDetailModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <div class="p-6 space-y-4 overflow-y-auto flex-grow text-sm">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase block">Nama Pelanggan</span>
                    <span id="detailNama" class="text-slate-900 font-bold text-base"></span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-slate-400 font-semibold uppercase block">Nomor Telepon</span>
                        <span id="detailTelepon" class="text-slate-800 font-medium"></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-semibold uppercase block">Status Layanan</span>
                        <span id="detailStatus" class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold mt-0.5"></span>
                    </div>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase block">Layanan Dipilih</span>
                    <span id="detailLayanan" class="text-indigo-600 font-semibold"></span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-slate-400 font-semibold uppercase block">Tanggal Mulai</span>
                        <span id="detailMulai" class="text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-semibold uppercase block">Tanggal Selesai</span>
                        <span id="detailSelesai" class="text-slate-800"></span>
                    </div>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase block">Alamat Lengkap</span>
                    <span id="detailAlamat" class="text-slate-700"></span>
                </div>
            </div>

            <!-- Peta Pratinjau Detail -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Titik Lokasi Peta</label>
                <div id="detailMapContainer" class="w-full h-40 rounded-xl border border-slate-300"></div>
                <span id="detailKoordinatText" class="text-[11px] text-slate-500 font-mono mt-1 block"></span>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 flex justify-end">
            <button onclick="closeDetailModal()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-5 py-2 rounded-xl text-xs transition">Tutup</button>
        </div>
    </div>
</div>

<?php if (!$is_koordinator): ?>
<!-- MODAL FORM TAMBAH / EDIT PELANGGAN DENGAN PETA -->
<div id="pelangganModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white flex-shrink-0">
            <h3 id="modalTitle" class="font-bold text-lg">Tambah Pelanggan Baru</h3>
            <button onclick="closeModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <form action="" method="POST" class="p-6 space-y-4 overflow-y-auto flex-grow">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="formId">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pelanggan *</label>
                    <input type="text" name="nama_pelanggan" id="namaPelanggan" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Nama lengkap klien">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon / WhatsApp *</label>
                    <input type="tel" name="telepon" id="telepon" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Contoh: 081234567890">
                </div>
            </div>

            <!-- DROPDOWN WILAYAH (API EMSIFA) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Provinsi *</label>
                    <select id="provinsi" name="provinsi_id" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                        <option value="">-- Pilih Provinsi --</option>
                    </select>
                    <input type="hidden" name="provinsi_nama" id="provinsi_nama">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kabupaten / Kota *</label>
                    <select id="kabupaten" name="kabupaten_id" required disabled class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs disabled:opacity-50">
                        <option value="">-- Pilih Kabupaten --</option>
                    </select>
                    <input type="hidden" name="kabupaten_nama" id="kabupaten_nama">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kecamatan *</label>
                    <select id="kecamatan" name="kecamatan_id" required disabled class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs disabled:opacity-50">
                        <option value="">-- Pilih Kecamatan --</option>
                    </select>
                    <input type="hidden" name="kecamatan_nama" id="kecamatan_nama">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Detail (Jalan / Kampung / RT / RW)</label>
                <textarea name="alamat_detail" id="alamatDetail" rows="2" placeholder="Contoh: Kp. Rancabali RT 01/02" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-sm"></textarea>
            </div>

            <!-- PETA INTERAKTIF LEAFLET -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Titik Lokasi (Klik pada peta untuk menandai titik koordinat)</label>
                <div id="mapContainer" class="w-full h-48 rounded-xl border border-slate-300 z-10"></div>
                <input type="text" name="koordinat" id="koordinat" readonly placeholder="Koordinat (Latitude, Longitude)" class="mt-2 w-full bg-slate-100 border border-slate-300 rounded-xl px-4 py-2 text-xs font-mono text-slate-600">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Layanan Dipilih *</label>
                    <select name="layanan_dipilih" id="layananDipilih" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        <option value="">-- Pilih Layanan --</option>
                        <option value="PBK Daycare">PBK Daycare</option>
                        <option value="PBK Babycare">PBK Babycare</option>
                        <option value="PBK Homecare">PBK Homecare</option>
                        <option value="PBK Caregiver">PBK Caregiver</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai *</label>
                    <input type="date" name="tanggal_mulai" id="tanggalMulai" value="<?= date('Y-m-d'); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Pelanggan *</label>
                    <select name="status" id="status" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        <option value="Aktif">Aktif</option>
                        <option value="Selesai">Selesai</option>
                        <option value="Dibatalkan">Dibatalkan</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition text-sm cursor-pointer mt-4">Simpan Data Pelanggan</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- SCRIPT JAVASCRIPT -->
<script>
    // Skrip untuk menghilangkan notifikasi secara otomatis dalam 3 detik & membersihkan URL parameter
    setTimeout(function() {
        const notifBox = document.getElementById('notif-box');
        if (notifBox) {
            notifBox.style.opacity = '0';
            setTimeout(function() {
                notifBox.style.display = 'none';
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 500);
        }
    }, 3000);

    // Modal Detail Pelanggan & Peta Pratinjau
    const detailModal = document.getElementById('detailModal');
    let detailMap, detailMarker;

    function openDetailModal(data) {
        document.getElementById('detailNama').textContent = data.nama_pelanggan;
        document.getElementById('detailTelepon').textContent = data.telepon;
        document.getElementById('detailLayanan').textContent = data.layanan_dipilih;
        document.getElementById('detailMulai').textContent = data.tanggal_mulai;
        document.getElementById('detailSelesai').textContent = data.tanggal_selesai || '-';
        document.getElementById('detailAlamat').textContent = data.alamat;

        const statusBadge = document.getElementById('detailStatus');
        statusBadge.textContent = data.status;
        statusBadge.className = "inline-block px-2.5 py-0.5 rounded-full text-xs font-bold mt-0.5 " + (data.status === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600');

        const koordText = document.getElementById('detailKoordinatText');
        koordText.textContent = data.koordinat ? "Koordinat: " + data.koordinat : "Koordinat tidak dicantumkan.";

        detailModal.classList.remove('hidden');

        let lat = -6.8244, lng = 107.1415;
        if (data.koordinat) {
            const coords = data.koordinat.split(',');
            if (coords.length === 2) {
                lat = parseFloat(coords[0]);
                lng = parseFloat(coords[1]);
            }
        }

        setTimeout(() => {
            if (!detailMap) {
                detailMap = L.map('detailMapContainer').setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(detailMap);
                detailMarker = L.marker([lat, lng]).addTo(detailMap);
            } else {
                detailMap.setView([lat, lng], 14);
                detailMarker.setLatLng([lat, lng]);
            }
            detailMap.invalidateSize();
        }, 200);
    }

    function closeDetailModal() {
        detailModal.classList.add('hidden');
    }

    <?php if (!$is_koordinator): ?>
    const modal = document.getElementById('pelangganModal');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formId');
    const namaPelanggan = document.getElementById('namaPelanggan');
    const telepon = document.getElementById('telepon');
    const alamatDetail = document.getElementById('alamatDetail');
    const layananDipilih = document.getElementById('layananDipilih');
    const tanggalMulai = document.getElementById('tanggalMulai');
    const status = document.getElementById('status');
    const koordinatInput = document.getElementById('koordinat');

    let map, marker;
    function initMap(lat = -6.8244, lng = 107.1415) {
        if (!map) {
            map = L.map('mapContainer').setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            map.on('click', function(e) {
                const clickedLat = e.latlng.lat.toFixed(6);
                const clickedLng = e.latlng.lng.toFixed(6);
                koordinatInput.value = `${clickedLat}, ${clickedLng}`;

                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
            });
        } else {
            map.setView([lat, lng], 13);
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }
        }
        setTimeout(() => { map.invalidateSize(); }, 300);
    }

    function openModal(mode, data = null) {
        if (mode === 'tambah') {
            modalTitle.textContent = 'Tambah Pelanggan Baru';
            formAction.value = 'tambah';
            formId.value = '';
            namaPelanggan.value = '';
            telepon.value = '';
            alamatDetail.value = '';
            layananDipilih.value = '';
            tanggalMulai.value = '<?= date('Y-m-d'); ?>';
            status.value = 'Aktif';
            koordinatInput.value = '';
            
            if (marker && map) {
                map.removeLayer(marker);
                marker = null;
            }
            modal.classList.remove('hidden');
            initMap(-6.8244, 107.1415);
        } else if (mode === 'edit' && data) {
            modalTitle.textContent = 'Edit Data Pelanggan';
            formAction.value = 'edit';
            formId.value = data.id;
            namaPelanggan.value = data.nama_pelanggan;
            telepon.value = data.telepon;
            alamatDetail.value = data.alamat;
            layananDipilih.value = data.layanan_dipilih;
            tanggalMulai.value = data.tanggal_mulai;
            status.value = data.status;
            koordinatInput.value = data.koordinat || '';

            modal.classList.remove('hidden');

            if (data.koordinat) {
                const coords = data.koordinat.split(',');
                if (coords.length === 2) {
                    const lat = parseFloat(coords[0]);
                    const lng = parseFloat(coords[1]);
                    initMap(lat, lng);
                    if (marker) {
                        marker.setLatLng([lat, lng]);
                    } else {
                        marker = L.marker([lat, lng]).addTo(map);
                    }
                } else {
                    initMap();
                }
            } else {
                initMap();
            }
        }
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function editPelanggan(data) {
        openModal('edit', data);
    }

    // API Wilayah Indonesia (Dropdown Provinsi, Kabupaten, Kecamatan)
    const selectProvinsi = document.getElementById('provinsi');
    const inputProvinsiNama = document.getElementById('provinsi_nama');
    const selectKabupaten = document.getElementById('kabupaten');
    const inputKabupatenNama = document.getElementById('kabupaten_nama');
    const selectKecamatan = document.getElementById('kecamatan');
    const inputKecamatanNama = document.getElementById('kecamatan_nama');

    fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
        .then(response => response.json())
        .then(provinces => {
            provinces.forEach(prov => {
                let opt = document.createElement('option');
                opt.value = prov.id;
                opt.textContent = prov.name;
                selectProvinsi.appendChild(opt);
            });
        });

    selectProvinsi.addEventListener('change', function() {
        let provId = this.value;
        inputProvinsiNama.value = this.options[this.selectedIndex].text;
        selectKabupaten.innerHTML = '<option value="">-- Pilih Kabupaten --</option>';
        selectKecamatan.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        selectKabupaten.disabled = true;
        selectKecamatan.disabled = true;

        if (provId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
                .then(response => response.json())
                .then(regencies => {
                    regencies.forEach(reg => {
                        let opt = document.createElement('option');
                        opt.value = reg.id;
                        opt.textContent = reg.name;
                        selectKabupaten.appendChild(opt);
                    });
                    selectKabupaten.disabled = false;
                });
        }
    });

    selectKabupaten.addEventListener('change', function() {
        let regId = this.value;
        inputKabupatenNama.value = this.options[this.selectedIndex].text;
        selectKecamatan.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        selectKecamatan.disabled = true;

        if (regId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regId}.json`)
                .then(response => response.json())
                .then(districts => {
                    districts.forEach(dist => {
                        let opt = document.createElement('option');
                        opt.value = dist.id;
                        opt.textContent = dist.name;
                        selectKecamatan.appendChild(opt);
                    });
                    selectKecamatan.disabled = false;
                });
        }
    });

    selectKecamatan.addEventListener('change', function() {
        inputKecamatanNama.value = this.options[this.selectedIndex].text;
    });
    <?php endif; ?>

    // AJAX Live Search Pelanggan (Mendukung Nama, Telepon, Layanan, dan Alamat)
    const searchInput = document.getElementById('searchKeyword');
    const tableBody = document.getElementById('tableBody');

    searchInput.addEventListener('input', function() {
        const query = this.value;
        // Mengirim parameter pencarian ke file handler PHP
        fetch(`pelanggan.php?ajax_search=${encodeURIComponent(query)}`)
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            });
    });
</script>