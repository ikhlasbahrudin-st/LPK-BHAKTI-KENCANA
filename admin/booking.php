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

// 3. VALIDASI ROLE: Izinkan super_admin dan admin untuk mengakses halaman ini
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
if (!in_array($admin_role, ['super_admin', 'admin'])) {
    echo "<script>
            alert('Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.');
            window.location.href = 'index.php';
          </script>";
    exit;
}
require_once '../config.php';
require_once 'header.php';

$sukses = "";
$error = "";

// 1. PROSES TAMBAH BOOKING
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_booking'])) {
    $pelanggan_id = intval($_POST['pelanggan_id']);
    $pekerja_id = !empty($_POST['pekerja_id']) ? intval($_POST['pekerja_id']) : null;
    $layanan = trim($_POST['layanan']);
    $tanggal_mulai = trim($_POST['tanggal_mulai']);
    $tanggal_selesai = trim($_POST['tanggal_selesai']);
    $status_booking = trim($_POST['status_booking']);
    $catatan = trim($_POST['catatan']);

    if ($pelanggan_id <= 0) {
        $error = "Gagal: Silakan pilih pelanggan yang valid dari daftar pencarian.";
    } else {
        $kode_booking = "BKG-" . date('Ymd') . "-" . strtoupper(substr(uniqid(), -4));

        $stmt = $conn->prepare("INSERT INTO booking (kode_booking, pelanggan_id, pekerja_id, layanan, tanggal_mulai, tanggal_selesai, status_booking, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siisssss", $kode_booking, $pelanggan_id, $pekerja_id, $layanan, $tanggal_mulai, $tanggal_selesai, $status_booking, $catatan);
        
        if ($stmt->execute()) {
            if (!empty($pekerja_id)) {
                $status_pekerja_baru = ($status_booking === 'Sedang Berjalan' || $status_booking === 'Dikonfirmasi') ? 'Sedang Bertugas' : 'Tersedia';
                $stmt_up_pekerja = $conn->prepare("UPDATE pekerja SET status_pekerja = ?, tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
                $stmt_up_pekerja->bind_param("sssi", $status_pekerja_baru, $tanggal_mulai, $tanggal_selesai, $pekerja_id);
                $stmt_up_pekerja->execute();
                $stmt_up_pekerja->close();
            }

            // PANGGIL FUNGSI PENCATATAN LOG AKTIVITAS
            catatLogAktivitas($conn, "Menambahkan data booking baru dengan kode: " . $kode_booking, "Booking");

            echo "<script>window.location='booking.php?status=sukses_tambah';</script>";
            exit;
        } else {
            $error = "Gagal menambahkan data booking ke database.";
        }
        $stmt->close();
    }
}

// 2. PROSES EDIT / UPDATE BOOKING
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_booking'])) {
    $id = intval($_POST['id']);
    $pelanggan_id = intval($_POST['pelanggan_id']);
    $pekerja_id = !empty($_POST['pekerja_id']) ? intval($_POST['pekerja_id']) : null;
    $layanan = trim($_POST['layanan']);
    $tanggal_mulai = trim($_POST['tanggal_mulai']);
    $tanggal_selesai = trim($_POST['tanggal_selesai']);
    $status_booking = trim($_POST['status_booking']);
    $catatan = trim($_POST['catatan']);

    // Ambil kode booking untuk keperluan log
    $q_kode = $conn->prepare("SELECT kode_booking, pekerja_id FROM booking WHERE id = ?");
    $q_kode->bind_param("i", $id);
    $q_kode->execute();
    $res_k = $q_kode->get_result()->fetch_assoc();
    $kode_bk_edit = $res_k ? $res_k['kode_booking'] : "ID: " . $id;
    $old_pekerja_id = $res_k ? $res_k['pekerja_id'] : null;
    $q_kode->close();

    $stmt_up = $conn->prepare("UPDATE booking SET pelanggan_id = ?, pekerja_id = ?, layanan = ?, tanggal_mulai = ?, tanggal_selesai = ?, status_booking = ?, catatan = ? WHERE id = ?");
    $stmt_up->bind_param("iisssssi", $pelanggan_id, $pekerja_id, $layanan, $tanggal_mulai, $tanggal_selesai, $status_booking, $catatan, $id);
    
    if ($stmt_up->execute()) {
        if (!empty($old_pekerja_id) && $old_pekerja_id != $pekerja_id) {
            $stmt_reset_old = $conn->prepare("UPDATE pekerja SET status_pekerja = 'Tersedia', tanggal_mulai = NULL, tanggal_selesai = NULL WHERE id = ?");
            $stmt_reset_old->bind_param("i", $old_pekerja_id);
            $stmt_reset_old->execute();
            $stmt_reset_old->close();
        }

        if (!empty($pekerja_id)) {
            if ($status_booking === 'Selesai' || $status_booking === 'Dibatalkan') {
                $stmt_p_up = $conn->prepare("UPDATE pekerja SET status_pekerja = 'Tersedia', tanggal_mulai = NULL, tanggal_selesai = NULL WHERE id = ?");
                $stmt_p_up->bind_param("i", $pekerja_id);
            } else {
                $status_p_baru = ($status_booking === 'Sedang Berjalan' || $status_booking === 'Dikonfirmasi') ? 'Sedang Bertugas' : 'Tersedia';
                $stmt_p_up = $conn->prepare("UPDATE pekerja SET status_pekerja = ?, tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
                $stmt_p_up->bind_param("sssi", $status_p_baru, $tanggal_mulai, $tanggal_selesai, $pekerja_id);
            }
            $stmt_p_up->execute();
            $stmt_p_up->close();
        }

        // PANGGIL FUNGSI PENCATATAN LOG AKTIVITAS
        catatLogAktivitas($conn, "Memperbarui data booking dengan kode: " . $kode_bk_edit, "Booking");

        echo "<script>window.location='booking.php?status=sukses_update';</script>";
        exit;
    } else {
        $error = "Gagal memperbarui data booking.";
    }
    $stmt_up->close();
}

// 3. PROSES HAPUS BOOKING
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);

    $q_del_check = $conn->prepare("SELECT kode_booking, pekerja_id FROM booking WHERE id = ?");
    $q_del_check->bind_param("i", $id_hapus);
    $q_del_check->execute();
    $res_dc = $q_del_check->get_result()->fetch_assoc();
    $q_del_check->close();

    $kode_bk_hapus = $res_dc ? $res_dc['kode_booking'] : "ID: " . $id_hapus;

    if ($res_dc && !empty($res_dc['pekerja_id'])) {
        $p_id_to_free = $res_dc['pekerja_id'];
        $stmt_free = $conn->prepare("UPDATE pekerja SET status_pekerja = 'Tersedia', tanggal_mulai = NULL, tanggal_selesai = NULL WHERE id = ?");
        $stmt_free->bind_param("i", $p_id_to_free);
        $stmt_free->execute();
        $stmt_free->close();
    }

    $stmt_del = $conn->prepare("DELETE FROM booking WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    if ($stmt_del->execute()) {
        // PANGGIL FUNGSI PENCATATAN LOG AKTIVITAS
        catatLogAktivitas($conn, "Menghapus data booking dengan kode: " . $kode_bk_hapus, "Booking");

        echo "<script>window.location='booking.php?status=sukses_hapus';</script>";
        exit;
    }
    $stmt_del->close();
}

// 4. PENANGANAN FILTER DATA BOOKING
$filter_status = isset($_GET['status_booking']) ? trim($_GET['status_booking']) : '';
$filter_layanan = isset($_GET['layanan']) ? trim($_GET['layanan']) : '';

$query_sql = "SELECT b.*, p.nama_pelanggan, p.telepon as telepon_pelanggan, pk.nama_lengkap as nama_pekerja 
              FROM booking b 
              LEFT JOIN pelanggan p ON b.pelanggan_id = p.id 
              LEFT JOIN pekerja pk ON b.pekerja_id = pk.id 
              WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_status)) {
    $query_sql .= " AND b.status_booking = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_layanan)) {
    $query_sql .= " AND b.layanan = ?";
    $params[] = $filter_layanan;
    $types .= "s";
}

$query_sql .= " ORDER BY b.id DESC";

if (!empty($params)) {
    $stmt_filter = $conn->prepare($query_sql);
    $stmt_filter->bind_param($types, ...$params);
    $stmt_filter->execute();
    $result_booking = $stmt_filter->get_result();
} else {
    $result_booking = $conn->query($query_sql);
}

$result_pelanggan = $conn->query("SELECT id, nama_pelanggan, layanan_dipilih FROM pelanggan ORDER BY nama_pelanggan ASC");
$result_pekerja = $conn->query("SELECT id, nama_lengkap, spesialisasi, status_pekerja FROM pekerja ORDER BY nama_lengkap ASC");
?>

<!-- Style khusus untuk cetak struk agar bersih saat di-save ke PDF -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #strukArea, #strukArea * {
        visibility: visible;
    }
    #strukArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
        box-shadow: none !important;
        border: none !important;
    }
}

@media print {
    /* Sembunyikan seluruh isi halaman website secara total */
    body * {
        visibility: hidden !important;
    }
    
    /* Hanya tampilkan area struk beserta seluruh isinya */
    #strukArea, #strukArea * {
        visibility: visible !important;
    }
    
    /* Atur posisi struk agar berada di tengah dan rapi saat dicetak */
    #strukArea {
        position: fixed !important;
        left: 50% !important;
        top: 20px !important;
        transform: translateX(-50%) !important;
        width: 100% !important;
        max-width: 450px !important;
        margin: 0 !important;
        padding: 20px !important;
        background: white !important;
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
        z-index: 999999 !important;
    }

    /* Sembunyikan tombol aksi modal saat dicetak */
    #detailModal button, 
    #detailModal .bg-slate-50.border-t {
        display: none !important;
    }
}
</style>

<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">
    <div>
        <!-- Topbar Mobile -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30 md:hidden">
            <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-lg hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="font-bold text-sm text-slate-800">Kelola Booking</span>
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
                            <span>✅ Data booking baru berhasil ditambahkan dan status pekerja diperbarui!</span>
                        </div>
                    <?php elseif ($_GET['status'] == 'sukses_update'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                            <span>✅ Data booking berhasil diperbarui dan status pekerja disesuaikan!</span>
                        </div>
                    <?php elseif ($_GET['status'] == 'sukses_hapus'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center shadow-sm">
                            <span>✅ Data booking berhasil dihapus dan pekerja dikembalikan menjadi tersedia.</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">Kelola Data Booking / Pemesanan</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola jadwal pemesanan layanan, penugasan pekerja, dan sinkronisasi status otomatis.</p>
                </div>
                <button onclick="openAddModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition shadow-sm inline-flex items-center gap-2">
                    <span>➕</span> Tambah Booking Baru
                </button>
            </div>

            <!-- FILTER DATA -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm mb-6">
                <form method="GET" action="booking.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Status Booking</label>
                        <select name="status_booking" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Semua Status --</option>
                            <option value="Pending" <?php echo ($filter_status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Dikonfirmasi" <?php echo ($filter_status === 'Dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                            <option value="Sedang Berjalan" <?php echo ($filter_status === 'Sedang Berjalan') ? 'selected' : ''; ?>>Sedang Berjalan</option>
                            <option value="Selesai" <?php echo ($filter_status === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="Dibatalkan" <?php echo ($filter_status === 'Dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Layanan</label>
                        <select name="layanan" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Semua Layanan --</option>
                            <option value="Daycare" <?php echo ($filter_layanan === 'Daycare') ? 'selected' : ''; ?>>Daycare</option>
                            <option value="Babycare" <?php echo ($filter_layanan === 'Babycare') ? 'selected' : ''; ?>>Babycare</option>
                            <option value="Homecare" <?php echo ($filter_layanan === 'Homecare') ? 'selected' : ''; ?>>Homecare</option>
                            <option value="Caregiver" <?php echo ($filter_layanan === 'Caregiver') ? 'selected' : ''; ?>>Caregiver</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-xl text-sm transition shadow-sm">
                            🔍 Filter Data
                        </button>
                        <a href="booking.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium py-2 px-4 rounded-xl text-sm transition flex items-center justify-center">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- TABEL BOOKING -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="p-6 border-b border-slate-200 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Daftar Seluruh Pemesanan (Booking)</h2>
                        <p class="text-xs text-slate-500">Daftar transaksi dan penugasan mitra kerja kepada klien.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-4 text-center w-12">No</th>
                                <th class="p-4">Kode / Tanggal</th>
                                <th class="p-4">Pelanggan / Klien</th>
                                <th class="p-4">Layanan</th>
                                <th class="p-4">Pekerja Ditugaskan</th>
                                <th class="p-4">Jadwal Layanan</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700">
                            <?php if ($result_booking->num_rows > 0): ?>
                                <?php 
                                $no = 1; 
                                $today = new DateTime();
                                while ($row = $result_booking->fetch_assoc()): 
                                    $tgl_selesai_obj = new DateTime($row['tanggal_selesai']);
                                    $interval = $today->diff($tgl_selesai_obj);
                                    $sisa_hari = (int)$interval->format('%r%a'); 
                                    $is_aktif = ($row['status_booking'] === 'Sedang Berjalan' || $row['status_booking'] === 'Dikonfirmasi');
                                ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4 font-medium text-slate-500 text-center"><?php echo $no++; ?></td>
                                        <td class="p-4 whitespace-nowrap">
                                            <span class="font-bold text-indigo-600 block"><?php echo htmlspecialchars($row['kode_booking']); ?></span>
                                            <span class="text-[11px] text-slate-400"><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                                        </td>
                                        <td class="p-4 font-bold text-slate-900">
                                            <?php echo htmlspecialchars($row['nama_pelanggan'] ?? 'Pelanggan Dihapus'); ?>
                                            <?php if (!empty($row['telepon_pelanggan'])): ?>
                                                <span class="block text-[11px] font-normal text-slate-500">📞 <?php echo htmlspecialchars($row['telepon_pelanggan']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-indigo-50 text-indigo-700 text-xs font-semibold px-2.5 py-1 rounded-full uppercase">
                                                <?php echo htmlspecialchars($row['layanan']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 font-medium text-slate-800">
                                            <?php if (!empty($row['nama_pekerja'])): ?>
                                                <span class="text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg text-xs font-semibold border border-emerald-200 inline-block">
                                                    👤 <?php echo htmlspecialchars($row['nama_pekerja']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-amber-600 bg-amber-50 px-2.5 py-1 rounded-lg text-xs font-medium border border-amber-200 inline-block italic">
                                                    ⚠️ Belum Ditugaskan
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-xs whitespace-nowrap text-slate-600">
                                            📅 <?php echo date('d/m/Y', strtotime($row['tanggal_mulai'])); ?> s/d <br>
                                            📅 <?php echo date('d/m/Y', strtotime($row['tanggal_selesai'])); ?>
                                        </td>
                                        <td class="p-4 whitespace-nowrap">
                                            <?php 
                                                $st = $row['status_booking'];
                                                $badge_bg = 'bg-slate-100 text-slate-700 border-slate-200';
                                                if ($st == 'Dikonfirmasi') $badge_bg = 'bg-sky-50 text-sky-700 border-sky-200';
                                                elseif ($st == 'Sedang Berjalan') $badge_bg = 'bg-amber-50 text-amber-700 border-amber-200';
                                                elseif ($st == 'Selesai') $badge_bg = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                                elseif ($st == 'Dibatalkan') $badge_bg = 'bg-rose-50 text-rose-700 border-rose-200';
                                            ?>
                                            <div class="flex flex-col gap-1">
                                                <span class="border text-xs font-semibold px-2.5 py-1 rounded-full w-fit <?php echo $badge_bg; ?>">
                                                    <?php echo $st; ?>
                                                </span>
                                                <!-- Script Peringatan Kontrak Segera Habis (<= 2 hari & status masih aktif) -->
                                                <?php if ($is_aktif && $sisa_hari >= 0 && $sisa_hari <= 2): ?>
                                                    <span class="bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold px-2 py-0.5 rounded animate-pulse">
                                                        ⚠️ Kontrak Segera Habis (<?php echo $sisa_hari === 0 ? 'Hari ini' : $sisa_hari . ' hari lagi'; ?>)
                                                    </span>
                                                <?php elseif ($is_aktif && $sisa_hari < 0): ?>
                                                    <span class="bg-slate-200 text-slate-700 border border-slate-300 text-[10px] font-bold px-2 py-0.5 rounded">
                                                        ⌛ Masa Kontrak Telah Lewat
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <a href="detail.php?id=<?php echo $row['id']; ?>" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg transition inline-flex items-center gap-1">
                                                    👁️ Detail
                                                </a>
                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg transition">
                                                    ✏️ Edit
                                                </button>
                                                <a href="booking.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus data booking ini? Status pekerja yang bertugas akan otomatis dikembalikan menjadi Tersedia.');" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg transition">
                                                    🗑️ Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-slate-400">Tidak ada data booking yang sesuai dengan filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL FORM (TAMBAH & EDIT) -->
    <div id="bookingModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all max-h-[90vh] flex flex-col">
            <div class="bg-indigo-900 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
                <h3 id="modalTitle" class="font-bold text-sm">➕ Tambah Booking Baru</h3>
                <button onclick="closeModal()" class="text-slate-300 hover:text-white p-1 rounded-lg transition">✕</button>
            </div>

            <form id="bookingForm" action="booking.php" method="POST" class="p-6 space-y-4 overflow-y-auto flex-1">
                <input type="hidden" id="formAction" name="tambah_booking" value="1">
                <input type="hidden" id="bookingId" name="id" value="">
                <input type="hidden" id="pelangganIdHidden" name="pelanggan_id" value="">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Pelanggan / Klien *</label>
                        <input type="text" id="pelangganSearchInput" list="pelangganListOptions" placeholder="Ketik nama pelanggan untuk mencari..." required autocomplete="off" onchange="setPelangganId(this)" oninput="setPelangganId(this)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed">
                        
                        <datalist id="pelangganListOptions">
                            <?php 
                            if ($result_pelanggan->num_rows > 0) {
                                $result_pelanggan->data_seek(0);
                                while ($p_row = $result_pelanggan->fetch_assoc()): 
                            ?>
                                    <option value="<?php echo htmlspecialchars($p_row['nama_pelanggan'] . ' (Layanan: ' . $p_row['layanan_dipilih'] . ')'); ?>" data-id="<?php echo $p_row['id']; ?>"></option>
                            <?php 
                                endwhile; 
                            } 
                            ?>
                        </datalist>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Layanan *</label>
                        <select id="layananSelect" name="layanan" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled selected>-- Pilih Layanan --</option>
                            <option value="Daycare">Daycare</option>
                            <option value="Babycare">Babycare</option>
                            <option value="Homecare">Homecare</option>
                            <option value="Caregiver">Caregiver</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tugaskan Pekerja / Mitra</label>
                        <select id="pekerjaIdSelect" name="pekerja_id" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Belum Ditugaskan (Pending Assign) --</option>
                            <?php 
                            if ($result_pekerja->num_rows > 0) {
                                $result_pekerja->data_seek(0);
                                while ($pk_row = $result_pekerja->fetch_assoc()): 
                            ?>
                                    <option value="<?php echo $pk_row['id']; ?>">
                                        <?php echo htmlspecialchars($pk_row['nama_lengkap']); ?> (<?php echo htmlspecialchars($pk_row['spesialisasi']); ?> - <?php echo $pk_row['status_pekerja']; ?>)
                                    </option>
                            <?php 
                                endwhile; 
                            } 
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status Booking *</label>
                        <select id="statusBookingSelect" name="status_booking" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="Pending">Pending</option>
                            <option value="Dikonfirmasi">Dikonfirmasi</option>
                            <option value="Sedang Berjalan">Sedang Berjalan</option>
                            <option value="Selesai">Selesai (Kontrak Selesai)</option>
                            <option value="Dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai Layanan *</label>
                        <input type="date" id="tglMulaiInput" name="tanggal_mulai" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai Layanan *</label>
                        <input type="date" id="tglSelesaiInput" name="tanggal_selesai" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan / Keterangan Khusus</label>
                    <textarea id="catatanInput" name="catatan" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Tuliskan catatan khusus dari klien..."></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-medium transition">Batal</button>
                    <button type="submit" id="submitBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DETAIL (STRUK / INVOICE PESANAN) -->
    <div id="detailModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm transition-all">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all flex flex-col max-h-[95vh]">
            <div class="bg-indigo-900 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
                <h3 class="font-bold text-sm">📄 Detail Struk / Invoice Pesanan</h3>
                <button onclick="closeDetailModal()" class="text-slate-300 hover:text-white p-1 rounded-lg transition">✕</button>
            </div>

            <!-- Area Struk yang akan dicetak/diunduh ke PDF -->
            <div id="strukArea" class="p-8 overflow-y-auto space-y-6 text-slate-800 bg-white flex-1">
                <div class="text-center border-b border-dashed border-slate-300 pb-5">
                    <h2 class="text-lg font-black uppercase tracking-wider text-indigo-900">LPK BHAKTI KENCANA</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Pusat Layanan Daycare, Babycare, Homecare & Caregiver</p>
                    <p class="text-[11px] text-slate-400">Cianjur, Jawa Barat</p>
                </div>

                <div class="flex justify-between items-center text-xs">
                    <div>
                        <span class="text-slate-400 block font-semibold uppercase">No. Transaksi / Kode:</span>
                        <span id="detailKodeBadge" class="font-bold text-indigo-600 text-sm"></span>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400 block font-semibold uppercase">Status Pemesanan:</span>
                        <span id="detailStatusBadge" class="inline-block mt-0.5"></span>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-slate-400 block font-semibold uppercase">Nama Pelanggan:</span>
                            <span id="detailNamaPelanggan" class="font-bold text-slate-900 text-sm"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold uppercase">No. Telepon:</span>
                            <span id="detailTeleponPelanggan" class="font-medium text-slate-700"></span>
                        </div>
                    </div>
                    <div class="border-t border-slate-200 pt-2 grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-slate-400 block font-semibold uppercase">Layanan:</span>
                            <span id="detailLayanan" class="font-semibold text-indigo-700"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold uppercase">Pekerja Ditugaskan:</span>
                            <span id="detailPekerja" class="font-semibold text-emerald-700"></span>
                        </div>
                    </div>
                    <div class="border-t border-slate-200 pt-2">
                        <span class="text-slate-400 block font-semibold uppercase">Periode Kontrak Layanan:</span>
                        <span id="detailPeriode" class="font-bold text-slate-800"></span>
                    </div>
                </div>

                <div class="text-xs border-t border-dashed border-slate-300 pt-3">
                    <span class="text-slate-400 block font-semibold uppercase mb-1">Catatan / Permintaan Khusus:</span>
                    <p id="detailCatatan" class="text-slate-600 italic bg-amber-50/50 p-3 rounded-xl border border-amber-100 leading-relaxed"></p>
                </div>

                <div class="text-center text-[10px] text-slate-400 pt-2 border-t border-slate-200">
                    <p>Terima kasih telah mempercayakan layanan profesional bersama kami.</p>
                    <p class="font-semibold text-slate-500 mt-0.5">Unggul, Profesional, Humanis</p>
                </div>
            </div>

            <!-- Tombol Aksi Modal -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex gap-3 flex-shrink-0">
                <button onclick="downloadPDF()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-sm inline-flex items-center justify-center gap-2">
                    📥 Cetak / Simpan PDF Struk
                </button>
                <button onclick="closeDetailModal()" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 py-2.5 rounded-xl text-xs font-semibold transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        const pelangganData = [
            <?php 
            if ($result_pelanggan->num_rows > 0) {
                $result_pelanggan->data_seek(0);
                while ($p_row = $result_pelanggan->fetch_assoc()) {
                    $display_str = $p_row['nama_pelanggan'] . ' (Layanan: ' . $p_row['layanan_dipilih'] . ')';
                    echo "{ id: " . $p_row['id'] . ", display: " . json_encode($display_str) . " },";
                }
            }
            ?>
        ];

        function setPelangganId(inputEl) {
            const val = inputEl.value;
            const hiddenInput = document.getElementById('pelangganIdHidden');
            let foundId = "";

            for (let p of pelangganData) {
                if (val === p.display) {
                    foundId = p.id;
                    break;
                }
            }
            hiddenInput.value = foundId;
        }

        const bookingModal = document.getElementById('bookingModal');
        const detailModal = document.getElementById('detailModal');
        
        const formAction = document.getElementById('formAction');
        const bookingId = document.getElementById('bookingId');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');

        const pelangganSearchInput = document.getElementById('pelangganSearchInput');
        const pelangganIdHidden = document.getElementById('pelangganIdHidden');
        const layananSelect = document.getElementById('layananSelect');
        const pekerjaIdSelect = document.getElementById('pekerjaIdSelect');
        const statusBookingSelect = document.getElementById('statusBookingSelect');
        const tglMulaiInput = document.getElementById('tglMulaiInput');
        const tglSelesaiInput = document.getElementById('tglSelesaiInput');
        const catatanInput = document.getElementById('catatanInput');

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

        function openAddModal() {
            modalTitle.innerText = "➕ Tambah Booking Baru";
            formAction.name = "tambah_booking";
            formAction.value = "1";
            bookingId.value = "";
            
            pelangganSearchInput.value = "";
            pelangganSearchInput.disabled = false; 
            pelangganIdHidden.value = "";

            layananSelect.value = "";
            pekerjaIdSelect.value = "";
            statusBookingSelect.value = "Pending";
            tglMulaiInput.value = "";
            tglSelesaiInput.value = "";
            catatanInput.value = "";

            submitBtn.innerText = "Tambah Booking";
            bookingModal.classList.remove('hidden');
        }

        function openEditModal(booking) {
            modalTitle.innerText = "✏️ Edit Booking: " + booking.kode_booking;
            formAction.name = "update_booking";
            formAction.value = "1";
            bookingId.value = booking.id;
            
            pelangganIdHidden.value = booking.pelanggan_id;
            
            let found = pelangganData.find(p => p.id == booking.pelanggan_id);
            if (found) {
                pelangganSearchInput.value = found.display;
            } else {
                pelangganSearchInput.value = booking.nama_pelanggan || "Pelanggan ID: " + booking.pelanggan_id;
            }

            pelangganSearchInput.disabled = true;

            layananSelect.value = booking.layanan;
            pekerjaIdSelect.value = booking.pekerja_id || "";
            statusBookingSelect.value = booking.status_booking;
            tglMulaiInput.value = booking.tanggal_mulai;
            tglSelesaiInput.value = booking.tanggal_selesai;
            catatanInput.value = booking.catatan || "";

            submitBtn.innerText = "Simpan Perubahan";
            bookingModal.classList.remove('hidden');
        }

        function openDetailModal(booking) {
            document.getElementById('detailKodeBadge').innerText = booking.kode_booking || "Transaksi";
            document.getElementById('detailNamaPelanggan').innerText = booking.nama_pelanggan || 'Pelanggan Tidak Ditemukan';
            document.getElementById('detailTeleponPelanggan').innerText = booking.telepon_pelanggan ? booking.telepon_pelanggan : "-";
            document.getElementById('detailLayanan').innerText = booking.layanan || "-";
            document.getElementById('detailPekerja').innerText = booking.nama_pekerja ? booking.nama_pekerja : "Belum Ditugaskan";
            document.getElementById('detailPeriode').innerText = (booking.tanggal_mulai || "") + " s/d " + (booking.tanggal_selesai || "");
            document.getElementById('detailCatatan').innerText = booking.catatan ? booking.catatan : "Tidak ada catatan khusus.";

            const sBadge = document.getElementById('detailStatusBadge');
            let st = booking.status_booking || 'Pending';
            let badgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
            if (st === 'Dikonfirmasi') badgeClass = 'bg-sky-50 text-sky-700 border-sky-200';
            else if (st === 'Sedang Berjalan') badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
            else if (st === 'Selesai') badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            else if (st === 'Dibatalkan') badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';

            sBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold border ${badgeClass}">${st}</span>`;

            detailModal.classList.remove('hidden');
        }

        function closeModal() {
            pelangganSearchInput.disabled = false; 
            bookingModal.classList.add('hidden');
        }

        function closeDetailModal() {
            detailModal.classList.add('hidden');
        }

        // Fungsi Cetak / Simpan ke PDF menggunakan Print Native Browser (100% Work Offline)
        function downloadPDF() {
            window.print();
        }

        window.onclick = function(event) {
            if (event.target == bookingModal) closeModal();
            if (event.target == detailModal) closeDetailModal();
        }

        document.getElementById('bookingForm').addEventListener('submit', function() {
            if (pelangganSearchInput.disabled) {
                pelangganSearchInput.disabled = false;
            }
        });
    </script>

</div>

</body>
</html>