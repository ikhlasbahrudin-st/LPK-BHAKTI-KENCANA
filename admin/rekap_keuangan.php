<?php
// 1. Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CEK SESI
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 3. VALIDASI ROLE
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
if (!in_array($admin_role, ['super_admin', 'admin'])) {
    echo "<script>
            alert('Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.');
            window.location.href = 'index.php';
          </script>";
    exit;
}

require_once '../config.php';

// Ambil nilai filter bulan dan tahun dari parameter GET (jika ada)
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_status = isset($_GET['status_pembayaran']) ? $_GET['status_pembayaran'] : '';

// Susun Query SQL dengan Filter
$query = "SELECT t.*, p.nama_pelanggan, p.layanan_dipilih 
          FROM transaksi_keuangan t 
          JOIN pelanggan p ON t.pelanggan_id = p.id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($filter_bulan)) {
    $query .= " AND MONTH(t.tanggal_transaksi) = ?";
    $params[] = $filter_bulan;
    $types .= "i";
}

if (!empty($filter_tahun)) {
    $query .= " AND YEAR(t.tanggal_transaksi) = ?";
    $params[] = $filter_tahun;
    $types .= "i";
}

if (!empty($filter_status)) {
    $query .= " AND t.status_pembayaran = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$query .= " ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_transaksi = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Keuangan - LPK Bhakti Kencana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-container { box-shadow: none !important; border: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen py-10 px-4">

    <div class="max-w-5xl mx-auto bg-white rounded-3xl shadow-lg border border-slate-200 p-8 print-container">
        
        <!-- HEADER & TOMBOL KEMBALI / CETAK (Hilang saat diprint) -->
        <div class="flex justify-between items-center mb-6 pb-6 border-b border-slate-200 no-print">
            <a href="keuangan.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-4 py-2 rounded-xl text-sm transition inline-flex items-center gap-2">
                &larr; Kembali ke Kelola Keuangan
            </a>
            <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl text-sm shadow-md transition inline-flex items-center gap-2 cursor-pointer">
                🖨️ Cetak / Unduh Laporan
            </button>
        </div>

<!-- FORM FILTER BULAN & TAHUN (Hilang saat diprint) -->
        <form method="GET" action="" class="bg-slate-50 p-5 rounded-2xl border border-slate-200 mb-8 no-print grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Bulan</label>
                <select name="bulan" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">-- Semua Bulan --</option>
                    <?php 
                    $bulan_arr = [
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                    ];
                    foreach ($bulan_arr as $num => $nama): 
                    ?>
                        <option value="<?= $num; ?>" <?= ($filter_bulan == $num) ? 'selected' : ''; ?>><?= $nama; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Tahun</label>
                <select name="tahun" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <?php 
                    $current_year = date('Y');
                    for ($y = $current_year; $y >= $current_year - 5; $y--): 
                    ?>
                        <option value="<?= $y; ?>" <?= ($filter_tahun == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Pembayaran</label>
                <select name="status_pembayaran" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">-- Semua Status --</option>
                    <option value="Lunas" <?= ($filter_status == 'Lunas') ? 'selected' : ''; ?>>Lunas</option>
                    <option value="Sebagian" <?= ($filter_status == 'Sebagian') ? 'selected' : ''; ?>>Sebagian</option>
                    <option value="Belum Lunas" <?= ($filter_status == 'Belum Lunas') ? 'selected' : ''; ?>>Belum Lunas</option>
                    <option value="Jatuh Tempo" <?= ($filter_status == 'Jatuh Tempo') ? 'selected' : ''; ?>>Jatuh Tempo</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-xl text-sm transition cursor-pointer">
                    🔍 Terapkan Filter
                </button>
            </div>
        </form>

        <!-- KOP SURAT / LAPORAN -->
        <div class="text-center mb-8">
            <h2 class="text-xl font-extrabold text-slate-900 uppercase">Laporan Rekapitulasi Keuangan</h2>
            <p class="text-xs text-slate-500 mt-1">LPK Bhakti Kencana Cianjur</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">
                Periode: 
                <?= !empty($filter_bulan) ? $bulan_arr[$filter_bulan] . ' ' : 'Semua Bulan '; ?> 
                <?= !empty($filter_tahun) ? $filter_tahun : ''; ?>
            </p>
        </div>

        <!-- TABEL DATA -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse border border-slate-300 text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 uppercase">
                        <th class="p-3 border border-slate-300 text-center w-10">No</th>
                        <th class="p-3 border border-slate-300">Tanggal</th>
                        <th class="p-3 border border-slate-300">Pelanggan & Layanan</th>
                        <th class="p-3 border border-slate-300">Kategori</th>
                        <th class="p-3 border border-slate-300">Jenis & Jumlah</th>
                        <th class="p-3 border border-slate-300">Tunggakan</th>
                        <th class="p-3 border border-slate-300 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if ($result_transaksi && $result_transaksi->num_rows > 0): ?>
                        <?php 
                        $no = 1; 
                        $total_uang_masuk = 0;
                        $total_tunggakan = 0;
                        while ($row = $result_transaksi->fetch_assoc()): 
                            if ($row['jenis_transaksi'] == 'Uang Masuk') {
                                $total_uang_masuk += $row['jumlah'];
                            }
                            $total_tunggakan += $row['tunggakan'];
                        ?>
                            <tr>
                                <td class="p-3 border border-slate-300 text-center font-medium"><?= $no++; ?></td>
                                <td class="p-3 border border-slate-300 whitespace-nowrap"><?= date('d M Y', strtotime($row['tanggal_transaksi'])); ?></td>
                                <td class="p-3 border border-slate-300">
                                    <span class="font-bold block text-slate-900"><?= htmlspecialchars($row['nama_pelanggan']); ?></span>
                                    <span class="text-indigo-600 font-medium"><?= htmlspecialchars($row['layanan_dipilih']); ?></span>
                                </td>
                                <td class="p-3 border border-slate-300 font-semibold"><?= htmlspecialchars($row['kategori']); ?></td>
                                <td class="p-3 border border-slate-300 whitespace-nowrap <?= ($row['jenis_transaksi'] == 'Uang Masuk') ? 'text-emerald-600' : 'text-rose-600'; ?> font-bold">
                                    <?= $row['jenis_transaksi']; ?><br>Rp <?= number_format($row['jumlah'], 0, ',', '.'); ?>
                                </td>
                                <td class="p-3 border border-slate-300 text-rose-600 font-semibold whitespace-nowrap">Rp <?= number_format($row['tunggakan'], 0, ',', '.'); ?></td>
                                <td class="p-3 border border-slate-300 text-center font-semibold"><?= $row['status_pembayaran']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <!-- BARIS TOTAL -->
                        <tr class="bg-slate-50 font-bold">
                            <td colspan="4" class="p-3 border border-slate-300 text-right uppercase">Total Keseluruhan:</td>
                            <td colspan="3" class="p-3 border border-slate-300 text-indigo-700">
                                Total Uang Masuk: Rp <?= number_format($total_uang_masuk, 0, ',', '.'); ?><br>
                                Total Tunggakan: Rp <?= number_format($total_tunggakan, 0, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="7" class="p-8 text-center text-slate-400 font-medium border border-slate-300">Tidak ada data transaksi keuangan pada periode ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TANDA TANGAN -->
        <div class="mt-12 flex justify-end text-xs text-slate-700">
            <div class="text-center">
                <p>Cianjur, <?= date('d F Y'); ?></p>
                <p class="font-bold mt-1">Bagian Keuangan</p>
                <div class="h-16"></div>
                <p class="font-bold underline">Nazua Novandra</p>
            </div>
        </div>

    </div>

</body>
</html>