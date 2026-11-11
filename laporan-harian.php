<?php
// 1. Mulai sesi (Wajib ada di baris paling atas sebelum output apa pun)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CEK SESI: Jika peserta belum login, tendang kembali ke login-peserta.php
$peserta_id = $_SESSION['peserta_id'] ?? null;

if (!$peserta_id) {
    header("Location: login-peserta.php");
    exit;
}

require_once 'config.php';
include 'header.php';

$sukses = "";
$error = "";

// 1. Ambil status terbaru peserta dari tabel pendaftaran
$stmt_status = $conn->prepare("SELECT status, nama_lengkap FROM pendaftaran WHERE id = ?");
$stmt_status->bind_param("i", $peserta_id);
$stmt_status->execute();
$data_peserta = $stmt_status->get_result()->fetch_assoc();
$stmt_status->close();

$status_peserta = $data_peserta['status'] ?? 'Diperiksa';

// 2. Cek apakah peserta yang sedang login sudah terdaftar di tabel `pekerja` dan ambil data spesialisasi
$pekerja_id = null;
$spesialisasi_pekerja = "";
$stmt_pekerja = $conn->prepare("SELECT id, spesialisasi FROM pekerja WHERE pendaftaran_id = ? OR id = ? LIMIT 1");
$stmt_pekerja->bind_param("ii", $peserta_id, $peserta_id);
$stmt_pekerja->execute();
$res_pekerja = $stmt_pekerja->get_result();
if ($res_pekerja->num_rows > 0) {
    $row_pekerja = $res_pekerja->fetch_assoc();
    $pekerja_id = $row_pekerja['id'];
    $spesialisasi_pekerja = $row_pekerja['spesialisasi'] ?? '';
}
$stmt_pekerja->close();

// Tangkap notifikasi sukses dari URL redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses') {
        $sukses = "Laporan harian berhasil dikirim!";
    } elseif ($_GET['status'] == 'update_sukses') {
        $sukses = "Laporan harian berhasil diperbarui!";
    }
}

// Direktori khusus upload bukti laporan
$upload_dir = "assets/uploads/laporan/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Tangani Proses Simpan (Insert) atau Update (Edit) via Modal Pop-up
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_laporan'])) {
    if (strtolower($status_peserta) === 'diperiksa') {
        $error = "Akun Anda masih dalam status 'Diperiksa'. Anda belum dapat mengunggah/mengedit laporan.";
    } elseif (!$pekerja_id) {
        $error = "Data Anda belum terdaftar di data pekerja/penempatan.";
    } else {
        $pelanggan_id       = !empty($_POST['pelanggan_id']) ? trim($_POST['pelanggan_id']) : 1;
        $tanggal_laporan    = trim($_POST['tanggal_laporan']);
        // Kategori tugas dipaksa mengambil dari data pekerja yang tersimpan agar aman dari manipulasi form
        $kategori_tugas     = $spesialisasi_pekerja; 
        $deskripsi_kegiatan = trim($_POST['deskripsi_kegiatan']);
        $kendala            = trim($_POST['kendala']);
        $latitude           = !empty($_POST['latitude']) ? trim($_POST['latitude']) : null;
        $longitude          = !empty($_POST['longitude']) ? trim($_POST['longitude']) : null;
        $laporan_id         = $_POST['laporan_id'] ?? null; 

        $foto_bukti = null;

        if (!empty($_POST['captured_image'])) {
            $data_uri = $_POST['captured_image'];
            $encoded_image = explode(",", $data_uri)[1];
            $decoded_image = base64_decode($encoded_image);
            $foto_bukti = "laporan_cam_" . time() . "_" . uniqid() . ".jpg";
            file_put_contents($upload_dir . $foto_bukti, $decoded_image);
        } elseif (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
            $ext = pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION);
            $foto_bukti = "laporan_" . time() . "_" . uniqid() . "." . $ext;
            move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $foto_bukti);
        }

        if (!empty($tanggal_laporan) && !empty($kategori_tugas) && !empty($deskripsi_kegiatan)) {
            if (!empty($laporan_id)) {
                // LOGIKA UPDATE
                if ($foto_bukti) {
                    $stmt_old = $conn->prepare("SELECT foto_bukti FROM laporan_harian WHERE id = ? AND pekerja_id = ?");
                    $stmt_old->bind_param("ii", $laporan_id, $pekerja_id);
                    $stmt_old->execute();
                    $old_data = $stmt_old->get_result()->fetch_assoc();
                    $stmt_old->close();

                    if ($old_data && !empty($old_data['foto_bukti']) && file_exists($upload_dir . $old_data['foto_bukti'])) {
                        unlink($upload_dir . $old_data['foto_bukti']);
                    }

                    $stmt = $conn->prepare("UPDATE laporan_harian SET tanggal_laporan = ?, kategori_tugas = ?, deskripsi_kegiatan = ?, kendala = ?, foto_bukti = ?, latitude = ?, longitude = ? WHERE id = ? AND pekerja_id = ?");
                    $stmt->bind_param("sssssssid", $tanggal_laporan, $kategori_tugas, $deskripsi_kegiatan, $kendala, $foto_bukti, $latitude, $longitude, $laporan_id, $pekerja_id);
                } else {
                    $stmt = $conn->prepare("UPDATE laporan_harian SET tanggal_laporan = ?, kategori_tugas = ?, deskripsi_kegiatan = ?, kendala = ?, latitude = ?, longitude = ? WHERE id = ? AND pekerja_id = ?");
                    $stmt->bind_param("ssssssii", $tanggal_laporan, $kategori_tugas, $deskripsi_kegiatan, $kendala, $latitude, $longitude, $laporan_id, $pekerja_id);
                }

                if ($stmt->execute()) {
                    $stmt->close();
                    echo "<script>window.location='laporan-harian.php?status=update_sukses';</script>";
                    exit;
                } else {
                    $error = "Gagal memperbarui laporan: " . $stmt->error;
                }
                $stmt->close();
            } else {
                // LOGIKA INSERT BARU
                $created_at = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO laporan_harian (pekerja_id, pelanggan_id, tanggal_laporan, kategori_tugas, deskripsi_kegiatan, kendala, foto_bukti, latitude, longitude, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissssssss", $pekerja_id, $pelanggan_id, $tanggal_laporan, $kategori_tugas, $deskripsi_kegiatan, $kendala, $foto_bukti, $latitude, $longitude, $created_at);

                if ($stmt->execute()) {
                    $stmt->close();
                    echo "<script>window.location='laporan-harian.php?status=sukses';</script>";
                    exit;
                } else {
                    $error = "Gagal menyimpan laporan ke database: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $error = "Mohon lengkapi kolom wajib (Tanggal, Kategori, Deskripsi).";
        }
    }
}

// Ambil daftar laporan berdasarkan pekerja_id yang sedang login beserta JOIN ke tabel admins untuk nama koordinator
$riwayat_laporan = [];
if ($pekerja_id) {
    $stmt_riwayat = $conn->prepare("SELECT l.*, a.nama_lengkap AS nama_admin_koordinator FROM laporan_harian l LEFT JOIN admins a ON l.admin_id = a.id WHERE l.pekerja_id = ? ORDER BY l.tanggal_laporan DESC, l.created_at DESC");
    $stmt_riwayat->bind_param("i", $pekerja_id);
    $stmt_riwayat->execute();
    $riwayat_laporan = $stmt_riwayat->get_result();
    $stmt_riwayat->close();
}
?>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Wrapper -->
<main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-6 md:px-8 py-4 flex justify-between items-center shadow-sm sticky top-0 z-30">
        <div class="flex items-center space-x-3">
            <button id="hamburgerToggle" class="md:hidden text-slate-700 focus:outline-none p-1.5 rounded-xl hover:bg-slate-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h2 class="text-xs md:text-sm font-bold text-slate-800">Form Laporan Harian Peserta</h2>
        </div>
        <span class="text-xs text-slate-500 font-medium hidden sm:inline">Halo, <b><?php echo htmlspecialchars($_SESSION['peserta_nama'] ?? 'Peserta'); ?></b></span>
    </header>

    <!-- Content Body -->
    <div class="p-6 md:p-8 max-w-5xl w-full space-y-6">
        
        <!-- Notifikasi & Status -->
        <div id="alertNotification">
            <?php if (strtolower($status_peserta) === 'diperiksa'): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-2xl mb-4 text-xs md:text-sm flex items-start shadow-sm gap-3">
                    <span class="text-base">⏳</span>
                    <div>
                        <b>Status Pendaftaran Anda: Diperiksa.</b><br>
                        Anda belum dapat mengirimkan laporan harian karena akun Anda masih menunggu verifikasi/persetujuan dari administrator LPK.
                    </div>
                </div>
            <?php elseif (!$pekerja_id): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl mb-4 text-xs md:text-sm flex items-start shadow-sm gap-3">
                    <span class="text-base">⚠️</span>
                    <div>
                        <b>Data Pekerja Belum Terdaftar.</b><br>
                        Akun Anda sudah diterima, namun data Anda belum dimasukkan ke dalam daftar penempatan pekerja oleh admin. Silakan hubungi admin LPK.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($sukses)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-4 text-xs flex items-center shadow-sm">
                    <span>✅ <?php echo $sukses; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-4 text-xs flex items-center shadow-sm">
                    <span>⚠️ <?php echo $error; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Halaman Utama: Hanya Menampilkan Header & Tabel Riwayat -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Riwayat Laporan Saya</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar laporan harian yang telah Anda kirimkan selama penempatan pelatihan.</p>
                </div>
                
                <?php if (strtolower($status_peserta) !== 'diperiksa' && $pekerja_id): ?>
                    <button type="button" onclick="openModalTambah()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs transition shadow-md inline-flex items-center gap-2 cursor-pointer">
                        <span>➕ Tambah Laporan Baru</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-700">
                            <th class="p-3 font-semibold">Tanggal</th>
                            <th class="p-3 font-semibold">Kategori</th>
                            <th class="p-3 font-semibold">Deskripsi Kegiatan</th>
                            <th class="p-3 font-semibold">Kendala</th>
                            <th class="p-3 font-semibold">Foto & Koordinat</th>
                            <th class="p-3 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <?php if ($riwayat_laporan && $riwayat_laporan->num_rows > 0): ?>
                            <?php while ($row = $riwayat_laporan->fetch_assoc()): ?>
                                <tr>
                                    <td class="p-3 whitespace-nowrap"><?php echo htmlspecialchars($row['tanggal_laporan']); ?></td>
                                    <td class="p-3 whitespace-nowrap">
                                        <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-lg font-medium">
                                            <?php echo htmlspecialchars($row['kategori_tugas']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 max-w-xs">
                                        <p><?php echo nl2br(htmlspecialchars($row['deskripsi_kegiatan'])); ?></p>
                                    </td>
                                    <td class="p-3"><?php echo !empty($row['kendala']) ? nl2br(htmlspecialchars($row['kendala'])) : '<span class="text-slate-400 italic">Tidak ada</span>'; ?></td>
                                    <td class="p-3 whitespace-nowrap space-y-1">
                                        <?php if (!empty($row['foto_bukti'])): ?>
                                            <div><a href="assets/uploads/laporan/<?php echo htmlspecialchars($row['foto_bukti']); ?>" target="_blank" class="text-emerald-600 hover:underline font-medium">🖼️ Lihat Foto</a></div>
                                        <?php else: ?>
                                            <div><span class="text-slate-400 italic">Tanpa Foto</span></div>
                                        <?php endif; ?>

                                        <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                            <div class="text-[11px] text-indigo-600 font-medium">📍 <?php echo htmlspecialchars($row['latitude']); ?>, <?php echo htmlspecialchars($row['longitude']); ?></div>
                                        <?php else: ?>
                                            <div class="text-[11px] text-slate-400 italic">📍 Tanpa Koordinat</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 whitespace-nowrap text-center space-x-1">
                                        <button type="button" onclick='bukaDetailModal(<?php echo json_encode($row); ?>)' class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg font-medium transition inline-flex items-center gap-1 cursor-pointer">
                                            👁️ Detail
                                        </button>
                                        <button type="button" onclick='openModalEdit(<?php echo json_encode($row); ?>)' class="bg-amber-50 text-amber-700 hover:bg-amber-100 px-2.5 py-1.5 rounded-lg font-medium transition inline-block cursor-pointer">
                                            ✏️ Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-6 text-center text-slate-400 italic">Belum ada laporan harian yang dikirimkan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- MODAL FORM TAMBAH / EDIT LAPORAN (POP-UP) -->
<div id="formModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white shrink-0">
            <h3 id="formModalTitle" class="font-bold text-base">Tambah Laporan Harian</h3>
            <button onclick="closeFormModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <form action="laporan-harian.php" method="POST" enctype="multipart/form-data" class="p-6 overflow-y-auto space-y-4 text-xs text-slate-700 flex-grow">
            <input type="hidden" name="laporan_id" id="modalLaporanId">
            <input type="hidden" name="captured_image" id="capturedImageInput">

            <!-- Balasan Koordinator (Tampil hanya dalam Mode Edit sebagai Informasi Baca Saja) -->
            <div id="modalEditBalasanContainer" class="hidden bg-indigo-50 border border-indigo-200 text-indigo-900 p-3 rounded-2xl space-y-1">
                <div class="font-bold flex items-center gap-1">
                    <span>💬 Balasan / Catatan dari Koordinator (<span id="modalEditNamaKoor">-</span>):</span>
                </div>
                <p id="modalEditTeksBalasan" class="italic text-indigo-800 mt-1">-</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Tanggal Laporan *</label>
                    <input type="date" name="tanggal_laporan" id="modalTanggal" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Kategori Tugas (Spesialisasi) *</label>
                    <!-- Input Kategori Tugas dikunci (Readonly / Disabled) menggunakan data spesialisasi dari tabel pekerja -->
                    <input type="text" id="modalKategoriDisplay" value="<?php echo htmlspecialchars($spesialisasi_pekerja); ?>" disabled class="w-full bg-slate-100 text-slate-600 border border-slate-300 rounded-xl px-3 py-2.5 font-medium cursor-not-allowed">
                    <input type="hidden" name="kategori_tugas" value="<?php echo htmlspecialchars($spesialisasi_pekerja); ?>">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">Deskripsi Kegiatan / Tindakan *</label>
                <textarea name="deskripsi_kegiatan" id="modalDeskripsi" rows="3" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500" placeholder="Tuliskan aktivitas atau tindakan di lapangan..."></textarea>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">Kendala / Catatan Khusus (Opsional)</label>
                <textarea name="kendala" id="modalKendala" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500" placeholder="Tuliskan kendala jika ada..."></textarea>
            </div>

            <!-- GPS -->
            <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                <div class="flex justify-between items-center">
                    <label class="font-semibold text-slate-700">Koordinat Lokasi Tugas</label>
                    <button type="button" onclick="ambilLokasiGPS()" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-semibold px-2.5 py-1 rounded-lg transition text-[11px] cursor-pointer">
                        📍 Ambil Lokasi
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="latitude" id="latitude" placeholder="Latitude" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs">
                    <input type="text" name="longitude" id="longitude" placeholder="Longitude" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs">
                </div>
                <p id="lokasiStatus" class="text-[11px] text-slate-500 italic"></p>
            </div>

            <!-- Foto Bukti & Pratinjau -->
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Foto Bukti / Dokumentasi</label>
                <div id="previewContainer" class="mb-2 hidden">
                    <div class="relative inline-block">
                        <img id="imagePreview" src="#" alt="Pratinjau" class="w-24 h-24 object-cover rounded-xl border border-slate-300">
                        <button type="button" onclick="clearPreview()" class="absolute -top-2 -right-2 bg-rose-600 text-white rounded-full w-5 h-5 font-bold text-[10px]">✕</button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label class="cursor-pointer bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-3 py-2 rounded-xl border border-slate-300">
                        📁 Pilih File
                        <input type="file" id="fotoBuktiInput" name="foto_bukti" accept="image/*" class="hidden">
                    </label>
                    <button type="button" onclick="openCameraModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-3 py-2 rounded-xl">
                        📸 Kamera
                    </button>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100">
                <button type="submit" name="simpan_laporan" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl transition cursor-pointer text-center">
                    Simpan Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETAIL & MAPS GOOGLE -->
<div id="detailModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white shrink-0">
            <h3 class="font-bold text-base">Detail Laporan & Balasan Koordinator</h3>
            <button onclick="tutupDetailModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto space-y-4 text-xs text-slate-700 flex-grow">
            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div>
                    <p class="font-semibold text-slate-400 uppercase">Tanggal</p>
                    <p id="modalDetailTanggal" class="font-medium text-slate-900 mt-0.5 text-sm">-</p>
                </div>
                <div>
                    <p class="font-semibold text-slate-400 uppercase">Kategori Tugas</p>
                    <p id="modalDetailKategori" class="font-medium text-emerald-600 mt-0.5 text-sm">-</p>
                </div>
            </div>

            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Deskripsi Kegiatan</p>
                <div id="modalDetailDeskripsi" class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-slate-800 whitespace-pre-line">-</div>
            </div>

            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Kendala / Catatan</p>
                <div id="modalDetailKendala" class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-slate-800 whitespace-pre-line">-</div>
            </div>

            <!-- Balasan Koordinator di Modal Detail -->
            <div id="modalDetailBalasanWrapper" class="hidden">
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Balasan / Masukan Koordinator</p>
                <div class="bg-indigo-50 p-3 rounded-xl border border-indigo-200 text-indigo-900 space-y-1">
                    <p class="font-bold text-xs" id="modalDetailNamaKoor">-</p>
                    <p id="modalDetailMasukan" class="italic whitespace-pre-line">-</p>
                </div>
            </div>

            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wider mb-1">Titik Lokasi (Google Maps)</p>
                <div id="mapContainer" class="w-full h-48 rounded-2xl overflow-hidden border border-slate-300 shadow-inner bg-slate-100 flex items-center justify-center">
                    <iframe id="googleMapFrame" class="w-full h-full border-0 hidden" loading="lazy" allowfullscreen></iframe>
                    <p id="noMapText" class="text-slate-400 italic text-xs hidden">Koordinat lokasi tidak disertakan pada laporan ini.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL KAMERA LANGSUNG -->
<div id="cameraModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl overflow-hidden flex flex-col p-6 text-center">
        <h3 class="font-bold text-slate-900 text-base mb-2">📸 Ambil Foto Kamera</h3>
        <p class="text-xs text-slate-500 mb-4">Pastikan posisi wajah/kegiatan Anda terlihat jelas di dalam kotak.</p>
        
        <div class="relative w-full h-64 bg-slate-900 rounded-2xl overflow-hidden mb-4 shadow-inner">
            <video id="cameraStream" autoplay playsinline class="w-full h-full object-cover"></video>
            <canvas id="cameraCanvas" class="hidden"></canvas>
        </div>

        <div class="flex gap-3">
            <button type="button" onclick="closeCameraModal()" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 rounded-xl text-xs transition cursor-pointer">
                Batal
            </button>
            <button type="button" onclick="capturePhoto()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl text-xs transition shadow-md cursor-pointer">
                📸 Ambil Foto
            </button>
        </div>
    </div>
</div>

<script>
    const formModal = document.getElementById('formModal');
    const formModalTitle = document.getElementById('formModalTitle');
    const modalLaporanId = document.getElementById('modalLaporanId');
    const modalTanggal = document.getElementById('modalTanggal');
    const modalDeskripsi = document.getElementById('modalDeskripsi');
    const modalKendala = document.getElementById('modalKendala');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const lokasiStatus = document.getElementById('lokasiStatus');

    const modalEditBalasanContainer = document.getElementById('modalEditBalasanContainer');
    const modalEditNamaKoor = document.getElementById('modalEditNamaKoor');
    const modalEditTeksBalasan = document.getElementById('modalEditTeksBalasan');

    const fotoBuktiInput = document.getElementById('fotoBuktiInput');
    const previewContainer = document.getElementById('previewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const capturedImageInput = document.getElementById('capturedImageInput');

    function openModalTambah() {
        formModalTitle.textContent = "Tambah Laporan Harian";
        modalLaporanId.value = "";
        modalTanggal.value = "<?php echo date('Y-m-d'); ?>";
        modalDeskripsi.value = "";
        modalKendala.value = "";
        latitudeInput.value = "";
        longitudeInput.value = "";
        lokasiStatus.textContent = "";
        clearPreview();
        
        modalEditBalasanContainer.classList.add('hidden');
        formModal.classList.remove('hidden');
    }

    function openModalEdit(data) {
        formModalTitle.textContent = "Edit Laporan Harian";
        modalLaporanId.value = data.id;
        modalTanggal.value = data.tanggal_laporan;
        modalDeskripsi.value = data.deskripsi_kegiatan;
        modalKendala.value = data.kendala || "";
        latitudeInput.value = data.latitude || "";
        longitudeInput.value = data.longitude || "";
        lokasiStatus.textContent = "";

        if (data.masukan_koordinator) {
            modalEditNamaKoor.textContent = data.nama_admin_koordinator || 'Koordinator';
            modalEditTeksBalasan.textContent = data.masukan_koordinator;
            modalEditBalasanContainer.classList.remove('hidden');
        } else {
            modalEditBalasanContainer.classList.add('hidden');
        }

        if (data.foto_bukti) {
            imagePreview.src = "assets/uploads/laporan/" + data.foto_bukti;
            previewContainer.classList.remove('hidden');
        } else {
            clearPreview();
        }

        formModal.classList.remove('hidden');
    }

    function closeFormModal() {
        formModal.classList.add('hidden');
    }

    const cameraModal = document.getElementById('cameraModal');
    const cameraStream = document.getElementById('cameraStream');
    const cameraCanvas = document.getElementById('cameraCanvas');
    let mediaStream = null;

    if (fotoBuktiInput) {
        fotoBuktiInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                    capturedImageInput.value = "";
                }
                reader.readAsDataURL(file);
            }
        });
    }

    async function openCameraModal() {
        cameraModal.classList.remove('hidden');
        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment' }, 
                audio: false 
            });
            cameraStream.srcObject = mediaStream;
        } catch (err) {
            alert("Tidak dapat mengakses kamera.");
            closeCameraModal();
        }
    }

    function closeCameraModal() {
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
        }
        cameraModal.classList.add('hidden');
    }

    function capturePhoto() {
        const width = cameraStream.videoWidth;
        const height = cameraStream.videoHeight;
        cameraCanvas.width = width;
        cameraCanvas.height = height;

        const ctx = cameraCanvas.getContext('2d');
        ctx.drawImage(cameraStream, 0, 0, width, height);

        const dataUrl = cameraCanvas.toDataURL('image/jpeg', 0.85);
        imagePreview.src = dataUrl;
        previewContainer.classList.remove('hidden');
        capturedImageInput.value = dataUrl;
        fotoBuktiInput.value = "";

        closeCameraModal();
    }

    function clearPreview() {
        imagePreview.src = "#";
        previewContainer.classList.add('hidden');
        capturedImageInput.value = "";
        fotoBuktiInput.value = "";
    }

    function ambilLokasiGPS() {
        if (!navigator.geolocation) {
            lokasiStatus.textContent = "❌ Browser tidak mendukung Geolocation.";
            return;
        }
        lokasiStatus.textContent = "⏳ Mendeteksi lokasi GPS...";
        navigator.geolocation.getCurrentPosition(
            (position) => {
                latitudeInput.value = position.coords.latitude;
                longitudeInput.value = position.coords.longitude;
                lokasiStatus.textContent = "✅ Lokasi berhasil didapatkan!";
            },
            (error) => {
                lokasiStatus.textContent = "❌ Gagal mendeteksi lokasi.";
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    const detailModal = document.getElementById('detailModal');
    const modalDetailTanggal = document.getElementById('modalDetailTanggal');
    const modalDetailKategori = document.getElementById('modalDetailKategori');
    const modalDetailDeskripsi = document.getElementById('modalDetailDeskripsi');
    const modalDetailKendala = document.getElementById('modalDetailKendala');
    const modalDetailBalasanWrapper = document.getElementById('modalDetailBalasanWrapper');
    const modalDetailNamaKoor = document.getElementById('modalDetailNamaKoor');
    const modalDetailMasukan = document.getElementById('modalDetailMasukan');
    const googleMapFrame = document.getElementById('googleMapFrame');
    const noMapText = document.getElementById('noMapText');

    function bukaDetailModal(data) {
        modalDetailTanggal.textContent = data.tanggal_laporan || '-';
        modalDetailKategori.textContent = data.kategori_tugas || '-';
        modalDetailDeskripsi.textContent = data.deskripsi_kegiatan || '-';
        modalDetailKendala.textContent = data.kendala ? data.kendala : 'Tidak ada kendala.';

        if (data.masukan_koordinator) {
            modalDetailNamaKoor.textContent = `💬 Oleh: ${data.nama_admin_koordinator || 'Koordinator'}`;
            modalDetailMasukan.textContent = data.masukan_koordinator;
            modalDetailBalasanWrapper.classList.remove('hidden');
        } else {
            modalDetailBalasanWrapper.classList.add('hidden');
        }

        if (data.latitude && data.longitude) {
            googleMapFrame.src = `https://maps.google.com/maps?q=${data.latitude},${data.longitude}&z=15&output=embed`;
            googleMapFrame.classList.remove('hidden');
            noMapText.classList.add('hidden');
        } else {
            googleMapFrame.src = '';
            googleMapFrame.classList.add('hidden');
            noMapText.classList.remove('hidden');
        }

        detailModal.classList.remove('hidden');
    }

    function tutupDetailModal() {
        googleMapFrame.src = '';
        detailModal.classList.add('hidden');
    }

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
</script>

<?php include 'footer.php'; ?>