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

// 3. VALIDASI ROLE: Izinkan super_admin dan admin untuk mengakses halaman ini
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
if (!in_array($admin_role, ['super_admin', 'admin'])) {
    echo "<script>
            alert('Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.');
            window.location.href = 'index.php';
          </script>";
    exit;
}

// 4. Handle AJAX Request Pencarian / Filter jika ada
if (isset($_GET['ajax_search'])) {
    require_once '../config.php';

    $keyword = "%" . trim($_GET['ajax_search']) . "%";
    $filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

    $query = "SELECT t.*, p.nama_pelanggan, p.layanan_dipilih 
              FROM transaksi_keuangan t 
              JOIN pelanggan p ON t.pelanggan_id = p.id 
              WHERE (p.nama_pelanggan LIKE ? OR t.kategori LIKE ? OR t.keterangan LIKE ?)";

    if (!empty($filter_status)) {
        $query .= " AND t.status_pembayaran = ?";
        $stmt = $conn->prepare($query . " ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC");
        $stmt->bind_param("ssss", $keyword, $keyword, $keyword, $filter_status);
    } else {
        $stmt = $conn->prepare($query . " ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC");
        $stmt->bind_param("sss", $keyword, $keyword, $keyword);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            echo '<tr class="hover:bg-slate-50/50 transition">';
            echo '<td class="p-4 font-medium text-slate-500">' . $no++ . '</td>';
            echo '<td class="p-4 text-xs text-slate-500 whitespace-nowrap">' . date('d M Y', strtotime($row['tanggal_transaksi'])) . '</td>';
            echo '<td class="p-4">';
            echo '<span class="font-bold block text-slate-900">' . htmlspecialchars($row['nama_pelanggan']) . '</span>';
            echo '<span class="text-xs text-indigo-600 font-medium">' . htmlspecialchars($row['layanan_dipilih']) . '</span>';
            echo '</td>';
            echo '<td class="p-4 text-xs font-semibold text-slate-800">' . htmlspecialchars($row['kategori']) . '</td>';
            
            // Jenis Transaksi & Jumlah
            $jenisColor = ($row['jenis_transaksi'] == 'Uang Masuk') ? 'text-emerald-600' : 'text-rose-600';
            echo '<td class="p-4 text-xs ' . $jenisColor . ' font-bold whitespace-nowrap">' . $row['jenis_transaksi'] . '<br>Rp ' . number_format($row['jumlah'], 0, ',', '.') . '</td>';
            
            echo '<td class="p-4 text-xs text-rose-600 font-semibold whitespace-nowrap">Rp ' . number_format($row['tunggakan'], 0, ',', '.') . '</td>';
            
            // Badge Status Pembayaran
            $badgeBg = 'bg-slate-100 text-slate-700';
            if ($row['status_pembayaran'] == 'Lunas') $badgeBg = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
            elseif ($row['status_pembayaran'] == 'Sebagian') $badgeBg = 'bg-amber-50 text-amber-700 border border-amber-200';
            elseif ($row['status_pembayaran'] == 'Jatuh Tempo') $badgeBg = 'bg-rose-50 text-rose-700 border border-rose-200';
            elseif ($row['status_pembayaran'] == 'Belum Lunas') $badgeBg = 'bg-slate-100 text-slate-700 border border-slate-200';
            
            echo '<td class="p-4 text-center"><span class="text-xs px-2.5 py-1 rounded-full font-semibold ' . $badgeBg . '">' . $row['status_pembayaran'] . '</span></td>';
            
            echo '<td class="p-4 text-center space-x-1 whitespace-nowrap">';
            echo '<button onclick=\'editKeuangan(' . json_encode($row) . ')\' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button> ';
            echo '<a href="keuangan.php?hapus=' . $row['id_transaksi'] . '" onclick="return confirm(\'Yakin ingin menghapus data transaksi keuangan ini?\');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" class="p-12 text-center text-slate-400 font-medium">Tidak ada data transaksi keuangan yang ditemukan.</td></tr>';
    }
    exit;
}

// 5. Muat file konfigurasi database dan Header Admin
require_once '../config.php';

$sukses = "";
$error = "";

// ==========================================
// PROSES TAMBAH & EDIT DATA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $pelanggan_id = intval($_POST['pelanggan_id']);
    $tanggal_transaksi = trim($_POST['tanggal_transaksi']);
    $jenis_transaksi = trim($_POST['jenis_transaksi']);
    $jumlah = floatval($_POST['jumlah']);
    $kategori = trim($_POST['kategori']);
    $tunggakan = floatval($_POST['tunggakan']);
    $status_pembayaran = trim($_POST['status_pembayaran']);
    $metode_pembayaran = trim($_POST['metode_pembayaran']);
    $keterangan = trim($_POST['keterangan']);

    if ($action == 'tambah') {
        $stmt = $conn->prepare("INSERT INTO transaksi_keuangan (pelanggan_id, tanggal_transaksi, jenis_transaksi, jumlah, kategori, tunggakan, status_pembayaran, metode_pembayaran, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdsdsss", $pelanggan_id, $tanggal_transaksi, $jenis_transaksi, $jumlah, $kategori, $tunggakan, $status_pembayaran, $metode_pembayaran, $keterangan);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // CATAT LOG AKTIVITAS
            $format_jumlah = number_format($jumlah, 0, ',', '.');
            catatLogAktivitas($conn, "Menambahkan transaksi {$jenis_transaksi} baru - Kategori: {$kategori} (Rp {$format_jumlah})", "Keuangan");

            header("Location: keuangan.php?status=sukses_tambah");
            exit;
        } else {
            $error = "Gagal menyimpan data keuangan.";
        }
        $stmt->close();
    } elseif ($action == 'edit') {
        $id_transaksi = intval($_POST['id_transaksi']);
        $stmt = $conn->prepare("UPDATE transaksi_keuangan SET pelanggan_id=?, tanggal_transaksi=?, jenis_transaksi=?, jumlah=?, kategori=?, tunggakan=?, status_pembayaran=?, metode_pembayaran=?, keterangan=? WHERE id_transaksi=?");
        $stmt->bind_param("issdsdsssi", $pelanggan_id, $tanggal_transaksi, $jenis_transaksi, $jumlah, $kategori, $tunggakan, $status_pembayaran, $metode_pembayaran, $keterangan, $id_transaksi);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // CATAT LOG AKTIVITAS
            catatLogAktivitas($conn, "Memperbarui data transaksi keuangan ID: {$id_transaksi} (Kategori: {$kategori})", "Keuangan");

            header("Location: keuangan.php?status=sukses_edit");
            exit;
        } else {
            $error = "Gagal memperbarui data keuangan.";
        }
        $stmt->close();
    }
}

// Tangkap status dari Redirect URL
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "Data keuangan berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_edit') {
        $sukses = "Data keuangan berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Data keuangan berhasil dihapus!";
    }
}

// ==========================================
// PROSES HAPUS DATA (GET)
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);

    // Ambil info kategori/jenis transaksi sebelum dihapus untuk log
    $q_log_del = $conn->prepare("SELECT jenis_transaksi, kategori, jumlah FROM transaksi_keuangan WHERE id_transaksi = ?");
    $q_log_del->bind_param("i", $id_hapus);
    $q_log_del->execute();
    $res_ld = $q_log_del->get_result()->fetch_assoc();
    $q_log_del->close();

    $detail_del = $res_ld ? "({$res_ld['jenis_transaksi']} - {$res_ld['kategori']})" : "ID: {$id_hapus}";

    if ($conn->query("DELETE FROM transaksi_keuangan WHERE id_transaksi = $id_hapus")) {
        // CATAT LOG AKTIVITAS
        catatLogAktivitas($conn, "Menghapus data transaksi keuangan {$detail_del}", "Keuangan");
    }

    header("Location: keuangan.php?status=sukses_hapus");
    exit;
}

// Muat Header Admin setelah logika Redirect selesai
require_once 'header.php';

// Ambil data dropdown Pelanggan
$result_pelanggan = $conn->query("SELECT id, nama_pelanggan, layanan_dipilih FROM pelanggan ORDER BY nama_pelanggan ASC");

// Ambil data awal transaksi keuangan untuk ditampilkan di tabel utama
$result_transaksi = $conn->query("SELECT t.*, p.nama_pelanggan, p.layanan_dipilih 
                                FROM transaksi_keuangan t 
                                JOIN pelanggan p ON t.pelanggan_id = p.id 
                                ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC");
?>

<!-- ================= PEMBUNGKUS UTAMA AGAR TIDAK TERTUTUP SIDEBAR ================= -->
<div class="md:ml-64 min-h-screen flex flex-col justify-between transition-all duration-300">

    <!-- KONTEN UTAMA HALAMAN KELOLA KEUANGAN -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">
        
        <!-- Header Title & Tombol Aksi -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <span class="bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Manajemen Keuangan</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold mt-2 text-slate-900">Kelola Transaksi Keuangan</h1>
                <p class="text-slate-600 text-sm mt-1">Pantau uang masuk, uang keluar, tunggakan, dan status pembayaran pelanggan.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Tombol Cetak / Download PDF -->
                <button onclick="cetakPDF()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-3 rounded-xl text-sm shadow-md transition inline-flex items-center gap-2 cursor-pointer">
                    <span>🖨️ Cetak / Unduh PDF</span>
                </button>
                <button onclick="openModal('tambah')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-3 rounded-xl text-sm shadow-md transition inline-flex items-center gap-2 cursor-pointer">
                    <span>➕ Tambah Transaksi</span>
                </button>
            </div>
        </div>

        <!-- Notifikasi Sukses / Gagal -->
        <?php if (!empty($sukses)): ?>
            <div id="notifAlert" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex justify-between items-center shadow-xs transition-opacity duration-500">
                <span>✅ <?= $sukses; ?></span>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold hover:text-emerald-900 cursor-pointer">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm">❌ <?= $error; ?></div>
        <?php endif; ?>

        <!-- Filter & Live Search Box -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Cari Transaksi (Nama Pelanggan / Kategori / Keterangan)</label>
                    <input type="text" id="searchKeyword" placeholder="Ketik nama atau kata kunci..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Status Pembayaran</label>
                    <select id="filterStatus" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Semua Status --</option>
                        <option value="Lunas">Lunas</option>
                        <option value="Sebagian">Sebagian</option>
                        <option value="Belum Lunas">Belum Lunas</option>
                        <option value="Jatuh Tempo">Jatuh Tempo</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabel Data Transaksi Keuangan -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="p-4 font-semibold">No</th>
                            <th class="p-4 font-semibold">Tanggal</th>
                            <th class="p-4 font-semibold">Pelanggan & Layanan</th>
                            <th class="p-4 font-semibold">Kategori</th>
                            <th class="p-4 font-semibold">Jenis & Jumlah</th>
                            <th class="p-4 font-semibold">Tunggakan</th>
                            <th class="p-4 font-semibold text-center">Status</th>
                            <th class="p-4 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-slate-100 text-sm">
                        <?php if ($result_transaksi && $result_transaksi->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result_transaksi->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-medium text-slate-500"><?= $no++; ?></td>
                                    <td class="p-4 text-xs text-slate-500 whitespace-nowrap"><?= date('d M Y', strtotime($row['tanggal_transaksi'])); ?></td>
                                    <td class="p-4">
                                        <span class="font-bold block text-slate-900"><?= htmlspecialchars($row['nama_pelanggan']); ?></span>
                                        <span class="text-xs text-indigo-600 font-medium"><?= htmlspecialchars($row['layanan_dipilih']); ?></span>
                                    </td>
                                    <td class="p-4 text-xs font-semibold text-slate-800"><?= htmlspecialchars($row['kategori']); ?></td>
                                    <td class="p-4 text-xs <?= ($row['jenis_transaksi'] == 'Uang Masuk') ? 'text-emerald-600' : 'text-rose-600'; ?> font-bold whitespace-nowrap">
                                        <?= $row['jenis_transaksi']; ?><br>
                                        Rp <?= number_format($row['jumlah'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="p-4 text-xs text-rose-600 font-semibold whitespace-nowrap">Rp <?= number_format($row['tunggakan'], 0, ',', '.'); ?></td>
                                    <td class="p-4 text-center">
                                        <?php 
                                            $badgeBg = 'bg-slate-100 text-slate-700 border border-slate-200';
                                            if ($row['status_pembayaran'] == 'Lunas') $badgeBg = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                            elseif ($row['status_pembayaran'] == 'Sebagian') $badgeBg = 'bg-amber-50 text-amber-700 border border-amber-200';
                                            elseif ($row['status_pembayaran'] == 'Jatuh Tempo') $badgeBg = 'bg-rose-50 text-rose-700 border border-rose-200';
                                        ?>
                                        <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $badgeBg; ?>"><?= $row['status_pembayaran']; ?></span>
                                    </td>
                                    <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                        <button onclick='editKeuangan(<?= json_encode($row); ?>)' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button>
                                        <a href="keuangan.php?hapus=<?= $row['id_transaksi']; ?>" onclick="return confirm('Yakin ingin menghapus data transaksi keuangan ini?');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="p-12 text-center text-slate-400 font-medium">Belum ada data transaksi keuangan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER ADMIN -->
    <?php require_once 'footer.php'; ?>

</div> <!-- END PEMBUNGKUS UTAMA -->

<!-- MODAL FORM TAMBAH / EDIT KEUANGAN -->
<div id="keuanganModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white">
            <h3 id="modalTitle" class="font-bold text-lg">Tambah Transaksi Keuangan</h3>
            <button onclick="closeModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id_transaksi" id="formId">

            <!-- PILIH PELANGGAN & LAYANAN (SATU KOLOM DENGAN FITUR SEARCH / DATALIST) -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Cari & Pilih Pelanggan / Layanan *</label>
                
                <!-- Input tunggal untuk mengetik nama sekaligus memilih -->
                <input type="text" id="pelangganSearchInput" list="pelangganList" placeholder="🔍 Ketik nama pelanggan untuk mencari..." autocomplete="off" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                
                <!-- Hidden input untuk menyimpan ID pelanggan asli yang akan dikirim ke database via POST -->
                <input type="hidden" name="pelanggan_id" id="pelangganId" required>

                <!-- Datalist sebagai sumber opsi pencarian -->
                <datalist id="pelangganList">
                    <?php 
                    $result_pelanggan->data_seek(0);
                    while ($pl = $result_pelanggan->fetch_assoc()): 
                        $displayText = htmlspecialchars($pl['nama_pelanggan']) . " (" . htmlspecialchars($pl['layanan_dipilih']) . ")";
                    ?>
                        <option value="<?= $displayText; ?>" data-id="<?= $pl['id']; ?>"></option>
                    <?php endwhile; ?>
                </datalist>
                <span class="text-[10px] text-slate-400 mt-1 block">Ketik sebagian nama, lalu klik atau pilih dari daftar yang muncul.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Transaksi *</label>
                    <input type="date" name="tanggal_transaksi" id="tanggalTransaksi" value="<?= date('Y-m-d'); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Transaksi *</label>
                    <select name="jenis_transaksi" id="jenisTransaksi" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        <option value="Uang Masuk">Uang Masuk</option>
                        <option value="Uang Keluar">Uang Keluar</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Jumlah (Rp) *</label>
                    <input type="number" step="0.01" name="jumlah" id="jumlah" placeholder="Contoh: 500000" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tunggakan (Rp) *</label>
                    <input type="number" step="0.01" name="tunggakan" id="tunggakan" placeholder="0 jika lunas" value="0" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori *</label>
                    <select name="kategori" id="kategori" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="" disabled>-- Pilih Kategori Transaksi --</option>
                        <option value="Pembayaran Layanan" selected>Pembayaran Layanan</option>
                        <option value="Pembayaran Awal">Pembayaran Awal (DP)</option>
                        <option value="Cicilan">Cicilan</option>
                        <option value="Pelunasan">Pelunasan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Pembayaran *</label>
                    <select name="status_pembayaran" id="statusPembayaran" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        <option value="Belum Lunas">Belum Lunas</option>
                        <option value="Sebagian">Sebagian</option>
                        <option value="Lunas">Lunas</option>
                        <option value="Jatuh Tempo">Jatuh Tempo</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Metode Pembayaran</label>
                <select name="metode_pembayaran" id="metodePembayaran" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="" disabled>-- Pilih Metode Pembayaran --</option>
                    <option value="Transfer Bank" selected>Transfer Bank</option>
                    <option value="Cash / Tunai">Cash / Tunai</option>
                    <option value="QRIS">QRIS</option>
                    <option value="E-Wallet">E-Wallet</option>
                    <option value="Virtual Account">Virtual Account</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan / Catatan</label>
                <textarea name="keterangan" id="keterangan" rows="2" placeholder="Catatan tambahan..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm"></textarea>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition text-sm cursor-pointer">Simpan Data Keuangan</button>
        </form>
    </div>
</div>

<!-- SCRIPT JAVASCRIPT, AJAX LIVE SEARCH & PRINT PDF -->
<script>
    // 1. Bersihkan parameter status dari URL setelah dimuat agar tidak terduplikasi saat di-refresh (F5)
    if (window.history.replaceState) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            urlParams.delete('status');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({path: newUrl}, '', newUrl);
        }
    }

    // 2. Timer otomatis menghilangkan alert sukses dalam 3 detik
    setTimeout(() => {
        const alertBox = document.getElementById('notifAlert');
        if (alertBox) {
            alertBox.style.transition = "opacity 0.5s ease";
            alertBox.style.opacity = "0";
            setTimeout(() => alertBox.remove(), 500);
        }
    }, 3000);

    const modal = document.getElementById('keuanganModal');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formId');
    const searchInputEl = document.getElementById('pelangganSearchInput');
    const hiddenIdEl = document.getElementById('pelangganId');
    const tanggalTransaksi = document.getElementById('tanggalTransaksi');
    const jenisTransaksi = document.getElementById('jenisTransaksi');
    const jumlah = document.getElementById('jumlah');
    const kategori = document.getElementById('kategori');
    const tunggakan = document.getElementById('tunggakan');
    const statusPembayaran = document.getElementById('statusPembayaran');
    const metodePembayaran = document.getElementById('metodePembayaran');
    const keterangan = document.getElementById('keterangan');

    // Event listener untuk menangkap ID pelanggan dari datalist pencarian
    searchInputEl.addEventListener('input', function() {
        const val = this.value;
        const datalistOptions = document.getElementById('pelangganList').options;
        hiddenIdEl.value = '';

        for (let i = 0; i < datalistOptions.length; i++) {
            if (datalistOptions[i].value === val) {
                hiddenIdEl.value = datalistOptions[i].getAttribute('data-id');
                break;
            }
        }
    });

    function openModal(mode) {
        if (mode === 'tambah') {
            modalTitle.textContent = 'Tambah Transaksi Keuangan';
            formAction.value = 'tambah';
            formId.value = '';
            hiddenIdEl.value = '';
            searchInputEl.value = '';
            tanggalTransaksi.value = '<?= date('Y-m-d'); ?>';
            jenisTransaksi.value = 'Uang Masuk';
            jumlah.value = '';
            kategori.value = 'Pembayaran Layanan';
            tunggakan.value = '0';
            statusPembayaran.value = 'Belum Lunas';
            metodePembayaran.value = 'Transfer Bank';
            keterangan.value = '';
        }
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function editKeuangan(data) {
        modalTitle.textContent = 'Edit Transaksi Keuangan';
        formAction.value = 'edit';
        formId.value = data.id_transaksi;
        hiddenIdEl.value = data.pelanggan_id;
        
        // Cari nama pelanggan yang sesuai dengan ID untuk diisikan ke input pencarian
        const datalistOptions = document.getElementById('pelangganList').options;
        for (let i = 0; i < datalistOptions.length; i++) {
            if (datalistOptions[i].getAttribute('data-id') == data.pelanggan_id) {
                searchInputEl.value = datalistOptions[i].value;
                break;
            }
        }

        tanggalTransaksi.value = data.tanggal_transaksi;
        jenisTransaksi.value = data.jenis_transaksi;
        jumlah.value = data.jumlah;
        kategori.value = data.kategori;
        tunggakan.value = data.tunggakan;
        statusPembayaran.value = data.status_pembayaran;
        metodePembayaran.value = data.metode_pembayaran || '';
        keterangan.value = data.keterangan || '';
        modal.classList.remove('hidden');
    }

    // Fungsi Cetak / Download PDF khusus Tanggal, Uang Masuk, Tunggakan, Status
    function cetakPDF() {
        const originalContent = document.body.innerHTML;
        const rows = document.querySelectorAll('#tableBody tr');
        let tableRowsHTML = '';
        
        rows.forEach((row, index) => {
            const cols = row.querySelectorAll('td');
            if (cols.length >= 7) {
                const tanggal = cols[1].innerText;
                const jenisJumlahText = cols[4].innerText; 
                const lines = jenisJumlahText.split('\n');
                const uangMasuk = lines.length > 1 ? lines[1] : jenisJumlahText;
                const tunggakan = cols[5].innerText;
                const status = cols[6].innerText;
                
                tableRowsHTML += `
                    <tr>
                        <td style="padding: 8px; border: 1px solid #cbd5e1; text-align: center;">${index + 1}</td>
                        <td style="padding: 8px; border: 1px solid #cbd5e1;">${tanggal}</td>
                        <td style="padding: 8px; border: 1px solid #cbd5e1;">${uangMasuk}</td>
                        <td style="padding: 8px; border: 1px solid #cbd5e1;">${tunggakan}</td>
                        <td style="padding: 8px; border: 1px solid #cbd5e1; text-align: center;">${status}</td>
                    </tr>
                `;
            }
        });

        const printHTML = `
            <div style="font-family: Arial, sans-serif; padding: 20px; color: #1e293b;">
                <h2 style="text-align: center; margin-bottom: 5px; color: #312e81;">LAPORAN KEUANGAN KELOMPOK / LPK</h2>
                <p style="text-align: center; font-size: 12px; color: #64748b; margin-bottom: 20px;">Dicetak pada: ${new Date().toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'})}</p>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background-color: #f1f5f9; color: #334155;">
                            <th style="padding: 10px; border: 1px solid #cbd5e1; width: 40px;">No</th>
                            <th style="padding: 10px; border: 1px solid #cbd5e1;">Tanggal</th>
                            <th style="padding: 10px; border: 1px solid #cbd5e1;">Uang Masuk</th>
                            <th style="padding: 10px; border: 1px solid #cbd5e1;">Tunggakan</th>
                            <th style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRowsHTML}
                    </tbody>
                </table>
            </div>
        `;

        document.body.innerHTML = printHTML;
        window.print();
        document.body.innerHTML = originalContent;
        window.location.reload(); 
    }

    // Skrip AJAX Live Search & Filter Status
    const searchInput = document.getElementById('searchKeyword');
    const filterStatusSelect = document.getElementById('filterStatus');
    const tableBody = document.getElementById('tableBody');

    function fetchKeuangan() {
        const query = searchInput.value;
        const statusFilter = filterStatusSelect.value;

        fetch(`keuangan.php?ajax_search=${encodeURIComponent(query)}&filter_status=${encodeURIComponent(statusFilter)}`)
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            });
    }

    searchInput.addEventListener('input', fetchKeuangan);
    filterStatusSelect.addEventListener('change', fetchKeuangan);
</script>