<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';

$id_booking = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_booking <= 0) {
    echo "<script>alert('ID Booking tidak valid!'); window.location='booking.php';</script>";
    exit;
}

$stmt = $conn->prepare("SELECT b.*, p.nama_pelanggan, p.telepon as telepon_pelanggan, pk.nama_lengkap as nama_pekerja 
                        FROM booking b 
                        LEFT JOIN pelanggan p ON b.pelanggan_id = p.id 
                        LEFT JOIN pekerja pk ON b.pekerja_id = pk.id 
                        WHERE b.id = ?");
$stmt->bind_param("i", $id_booking);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "<script>alert('Data booking tidak ditemukan!'); window.location='booking.php';</script>";
    exit;
}

$st = $row['status_booking'];
$badge_bg = 'bg-slate-100 text-slate-700 border-slate-200';
if ($st == 'Dikonfirmasi') $badge_bg = 'bg-sky-50 text-sky-700 border-sky-200';
elseif ($st == 'Sedang Berjalan') $badge_bg = 'bg-amber-50 text-amber-700 border-amber-200';
elseif ($st == 'Selesai') $badge_bg = 'bg-emerald-50 text-emerald-700 border-emerald-200';
elseif ($st == 'Dibatalkan') $badge_bg = 'bg-rose-50 text-rose-700 border-rose-200';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Kosongkan title saat dicetak agar tidak terbawa -->
    <title id="pageTitle">Struk Pesanan - <?php echo htmlspecialchars($row['kode_booking']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        @media print {
            @page {
                size: A5 portrait;
                margin: 5mm;
            }
            body {
                background: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            #strukContainer {
                box-shadow: none !important;
                border: none !important;
                margin: 0 auto !important;
                padding: 10px !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col items-center justify-center p-4">

    <!-- Tombol Navigasi & Aksi (Tidak ikut tercetak) -->
    <div class="no-print mb-4 flex gap-3 w-full max-w-md justify-between items-center">
        <a href="booking.php" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition shadow-sm inline-flex items-center gap-1.5">
            ⬅️ Kembali
        </a>
        <button onclick="printStrukClean()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-xs font-semibold transition shadow-sm inline-flex items-center gap-2">
            🖨️ Cetak / Unduh PDF Struk
        </button>
    </div>

    <!-- Kotak Struk Pesanan -->
    <div id="strukContainer" class="bg-white w-full max-w-md rounded-3xl shadow-xl border border-slate-200 p-6 space-y-5 text-slate-800">
        <!-- Header Struk -->
        <div class="text-center border-b border-dashed border-slate-300 pb-4">
            <h2 class="text-base font-black uppercase tracking-wider text-indigo-900">LPK BHAKTI KENCANA</h2>
            <p class="text-[11px] text-slate-500 mt-0.5">Pusat Layanan Daycare, Babycare, Homecare & Caregiver</p>
            <p class="text-[10px] text-slate-400">Cianjur, Jawa Barat</p>
        </div>

        <!-- Info Kode & Status -->
        <div class="flex justify-between items-center text-xs">
            <div>
                <span class="text-slate-400 block font-semibold uppercase text-[10px]">No. Transaksi / Kode:</span>
                <span class="font-bold text-indigo-600 text-sm"><?php echo htmlspecialchars($row['kode_booking']); ?></span>
            </div>
            <div class="text-right">
                <span class="text-slate-400 block font-semibold uppercase text-[10px]">Status:</span>
                <span class="inline-block mt-0.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?php echo $badge_bg; ?>">
                    <?php echo htmlspecialchars($st); ?>
                </span>
            </div>
        </div>

        <!-- Detail Data -->
        <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200 space-y-2.5 text-xs">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <span class="text-slate-400 block uppercase text-[10px] font-semibold">Nama Pelanggan:</span>
                    <span class="font-bold text-slate-900"><?php echo htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block uppercase text-[10px] font-semibold">No. Telepon:</span>
                    <span class="font-medium text-slate-700"><?php echo htmlspecialchars($row['telepon_pelanggan'] ?? '-'); ?></span>
                </div>
            </div>
            <div class="border-t border-slate-200 pt-2 grid grid-cols-2 gap-2">
                <div>
                    <span class="text-slate-400 block uppercase text-[10px] font-semibold">Layanan:</span>
                    <span class="font-semibold text-indigo-700"><?php echo htmlspecialchars($row['layanan']); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block uppercase text-[10px] font-semibold">Pekerja Ditugaskan:</span>
                    <span class="font-semibold text-emerald-700"><?php echo htmlspecialchars($row['nama_pekerja'] ?? 'Belum Ditugaskan'); ?></span>
                </div>
            </div>
            <div class="border-t border-slate-200 pt-2">
                <span class="text-slate-400 block uppercase text-[10px] font-semibold">Periode Kontrak Layanan:</span>
                <span class="font-bold text-slate-800">
                    <?php echo date('d/m/Y', strtotime($row['tanggal_mulai'])); ?> s/d <?php echo date('d/m/Y', strtotime($row['tanggal_selesai'])); ?>
                </span>
            </div>
        </div>

        <!-- Catatan -->
        <div class="text-xs border-t border-dashed border-slate-300 pt-3">
            <span class="text-slate-400 block uppercase text-[10px] font-semibold mb-1">Catatan / Permintaan Khusus:</span>
            <p class="text-slate-600 italic bg-amber-50/50 p-2.5 rounded-xl border border-amber-100 leading-relaxed text-[11px]">
                <?php echo !empty($row['catatan']) ? htmlspecialchars($row['catatan']) : 'Tidak ada catatan khusus.'; ?>
            </p>
        </div>

        <!-- Footer Struk -->
        <div class="text-center text-[10px] text-slate-400 pt-2 border-t border-slate-200">
            <p>Terima kasih telah mempercayakan layanan profesional bersama kami.</p>
            <p class="font-semibold text-slate-500 mt-0.5">Unggul, Profesional, Humanis</p>
        </div>
    </div>

    <!-- Script Cetak Bersih (Menghilangkan URL & Title Dokumen via iframe tersembunyi) -->
    <script>
        function printStrukClean() {
            const strukContent = document.getElementById('strukContainer').outerHTML;
            const printWindow = window.open('', '_blank', 'width=600,height=700');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="UTF-8">
                    <title></title>
                    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"><\/script>
                    <style>
                        @page { size: A5 portrait; margin: 10mm; }
                        body { background: white; font-family: ui-sans-serif, system-ui, sans-serif; }
                    </style>
                </head>
                <body class="p-4 flex justify-center">
                    <div style="width: 100%; max-width: 420px;">
                        ${strukContent}
                    </div>
                    <script>
                        setTimeout(() => {
                            window.print();
                            window.close();
                        }, 500);
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    </script>
</body>
</html>