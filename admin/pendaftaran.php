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

$is_super_admin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin');

// Handle AJAX Request TERLEBIH DAHULU sebelum ada output HTML/header apapun
if (isset($_GET['ajax_search']) || isset($_GET['ajax_program'])) {
    require_once '../config.php';
    $is_sa = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin');

    $keyword = isset($_GET['ajax_search']) ? "%" . trim($_GET['ajax_search']) . "%" : "%";
    $program = isset($_GET['ajax_program']) ? trim($_GET['ajax_program']) : "";

    if (!empty($program)) {
        $stmt_search = $conn->prepare("SELECT * FROM pendaftaran WHERE nama_lengkap LIKE ? AND program_pilihan = ? ORDER BY tanggal_daftar DESC");
        $stmt_search->bind_param("ss", $keyword, $program);
    } else {
        $stmt_search = $conn->prepare("SELECT * FROM pendaftaran WHERE nama_lengkap LIKE ? ORDER BY tanggal_daftar DESC");
        $stmt_search->bind_param("s", $keyword);
    }
    
    $stmt_search->execute();
    $result_pendaftaran = $stmt_search->get_result();
    
    if ($result_pendaftaran->num_rows > 0) {
        $no = 1; 
        while ($row = $result_pendaftaran->fetch_assoc()) {
            $badge_color = "bg-amber-50 text-amber-700 border-amber-200";
            if ($row['status'] == 'Diterima') $badge_color = "bg-emerald-50 text-emerald-700 border-emerald-200";
            if ($row['status'] == 'Ditolak') $badge_color = "bg-rose-50 text-rose-700 border-rose-200";
            
            echo '<tr class="hover:bg-slate-50/50 transition">';
            echo '<td class="p-4 font-medium text-slate-500">' . $no++ . '</td>';
            echo '<td class="p-4 text-xs text-slate-500 whitespace-nowrap">' . htmlspecialchars($row['tanggal_daftar']) . '</td>';
            echo '<td class="p-4">';
            echo '<span class="font-bold block text-slate-900">' . htmlspecialchars($row['nama_lengkap']) . '</span>';
            echo '<span class="text-xs text-indigo-600">@' . htmlspecialchars($row['username']) . '</span>';
            echo '<span class="text-xs text-slate-500 block">📞 ' . htmlspecialchars($row['telepon']) . '</span>';
            echo '</td>';
            echo '<td class="p-4 text-xs font-medium">' . htmlspecialchars($row['program_pilihan']) . '</td>';
            echo '<td class="p-4 text-xs space-y-1 whitespace-nowrap">';
            if (!empty($row['foto_kk'])) {
                echo '<a href="../assets/uploads/' . $row['foto_kk'] . '" target="_blank" class="block text-indigo-600 hover:underline">📄 Lihat KK</a>';
            }
            if (!empty($row['pasfoto'])) {
                echo '<a href="../assets/uploads/' . $row['pasfoto'] . '" target="_blank" class="block text-indigo-600 hover:underline">🖼️ Lihat Pasfoto</a>';
            }
            if (!empty($row['berkas_sk'])) {
                echo '<a href="../assets/uploads/' . $row['berkas_sk'] . '" target="_blank" class="block text-emerald-600 font-semibold hover:underline">📜 Lihat Ijazah</a>';
            }
            if (!empty($row['sertifikat'])) {
                echo '<a href="../assets/uploads/' . $row['sertifikat'] . '" target="_blank" class="block text-sky-600 font-semibold hover:underline">🏆 Lihat Sertifikat</a>';
            } else {
                echo '<span class="text-slate-400 block italic text-[11px]">Sertifikat belum ada</span>';
            }
            echo '</td>';
            echo '<td class="p-4 whitespace-nowrap"><span class="' . $badge_color . ' border text-xs font-semibold px-2.5 py-1 rounded-full inline-block">' . htmlspecialchars($row['status']) . '</span></td>';
            echo '<td class="p-4 text-center space-x-1 whitespace-nowrap">';
            echo '<a href="pendaftaran.php?detail=' . $row['id'] . '" class="inline-block bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Detail</a> ';
            echo '<a href="pendaftaran.php?edit=' . $row['id'] . '" class="inline-block bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit / Sertifikat</a> ';
            if ($is_sa) {
                echo '<a href="pendaftaran.php?hapus=' . $row['id'] . '" onclick="return confirm(\'Yakin ingin menghapus data pendaftar ini beserta seluruh berkasnya?\');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium">Tidak ada data pendaftar yang sesuai.</td></tr>';
    }
    exit;
}

// Muat konfigurasi database
require_once '../config.php';

// 1. Proses Hapus Data & File Fisiknya (HANYA SUPER ADMIN)
if (isset($_GET['hapus'])) {
    if (!$is_super_admin) {
        header("Location: pendaftaran.php?status=akses_ditolak");
        exit;
    }

    $id_hapus = intval($_GET['hapus']);
    
    $q_nama_del = $conn->prepare("SELECT nama_lengkap FROM pendaftaran WHERE id = ?");
    $q_nama_del->bind_param("i", $id_hapus);
    $q_nama_del->execute();
    $res_nd = $q_nama_del->get_result()->fetch_assoc();
    $nama_pendaftar_del = $res_nd ? $res_nd['nama_lengkap'] : "ID: " . $id_hapus;
    $q_nama_del->close();

    $query_file = $conn->prepare("SELECT foto_kk, pasfoto, berkas_sk, sertifikat FROM pendaftaran WHERE id = ?");
    $query_file->bind_param("i", $id_hapus);
    $query_file->execute();
    $res_file = $query_file->get_result();
    if ($row_file = $res_file->fetch_assoc()) {
        if (!empty($row_file['foto_kk']) && file_exists("../assets/uploads/" . $row_file['foto_kk'])) {
            unlink("../assets/uploads/" . $row_file['foto_kk']);
        }
        if (!empty($row_file['pasfoto']) && file_exists("../assets/uploads/" . $row_file['pasfoto'])) {
            unlink("../assets/uploads/" . $row_file['pasfoto']);
        }
        if (!empty($row_file['berkas_sk']) && file_exists("../assets/uploads/" . $row_file['berkas_sk'])) {
            unlink("../assets/uploads/" . $row_file['berkas_sk']);
        }
        if (!empty($row_file['sertifikat']) && file_exists("../assets/uploads/" . $row_file['sertifikat'])) {
            unlink("../assets/uploads/" . $row_file['sertifikat']);
        }
    }
    $query_file->close();

    $stmt_del = $conn->prepare("DELETE FROM pendaftaran WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    if ($stmt_del->execute()) {
        $stmt_del->close();
        catatLogAktivitas($conn, "Menghapus data pendaftar atas nama: " . $nama_pendaftar_del, "Pendaftaran");
        header("Location: pendaftaran.php?status=sukses_hapus");
        exit;
    } else {
        $stmt_del->close();
        header("Location: pendaftaran.php?status=gagal_hapus");
        exit;
    }
}

// 2. Proses Update Status & Edit Data Pendaftar (Termasuk Upload Sertifikat)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pendaftaran'])) {
    $id = intval($_POST['id']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $nik = !empty($_POST['nik']) ? trim($_POST['nik']) : null;
    $telepon = trim($_POST['telepon']);
    $usia = intval($_POST['usia']);
    $jenis_kelamin = trim($_POST['jenis_kelamin']);
    $program_pilihan = trim($_POST['program_pilihan']);
    $lulusan_sekolah = trim($_POST['lulusan_sekolah']);
    $nama_sekolah_asal = trim($_POST['nama_sekolah_asal']);
    $alamat = trim($_POST['alamat']);
    $catatan_admin = trim($_POST['catatan_admin']);
    $status = trim($_POST['status']);

    $upload_dir = "../assets/uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $q_old = $conn->prepare("SELECT foto_kk, pasfoto, berkas_sk, sertifikat FROM pendaftaran WHERE id = ?");
    $q_old->bind_param("i", $id);
    $q_old->execute();
    $old_data = $q_old->get_result()->fetch_assoc();
    $q_old->close();

    $foto_kk_name = $old_data['foto_kk'];
    $pasfoto_name = $old_data['pasfoto'];
    $berkas_sk_name = $old_data['berkas_sk'];
    $sertifikat_name = $old_data['sertifikat'];

    if (isset($_FILES['foto_kk']) && $_FILES['foto_kk']['error'] == 0) {
        $ext_kk = pathinfo($_FILES['foto_kk']['name'], PATHINFO_EXTENSION);
        $foto_kk_name = "kk_" . time() . "_" . uniqid() . "." . $ext_kk;
        if (!empty($old_data['foto_kk']) && file_exists($upload_dir . $old_data['foto_kk'])) {
            unlink($upload_dir . $old_data['foto_kk']);
        }
        move_uploaded_file($_FILES['foto_kk']['tmp_name'], $upload_dir . $foto_kk_name);
    }

    if (isset($_FILES['pasfoto']) && $_FILES['pasfoto']['error'] == 0) {
        $ext_pasfoto = pathinfo($_FILES['pasfoto']['name'], PATHINFO_EXTENSION);
        $pasfoto_name = "pasfoto_" . time() . "_" . uniqid() . "." . $ext_pasfoto;
        if (!empty($old_data['pasfoto']) && file_exists($upload_dir . $old_data['pasfoto'])) {
            unlink($upload_dir . $old_data['pasfoto']);
        }
        move_uploaded_file($_FILES['pasfoto']['tmp_name'], $upload_dir . $pasfoto_name);
    }

    if (isset($_FILES['berkas_sk']) && $_FILES['berkas_sk']['error'] == 0) {
        $ext_sk = pathinfo($_FILES['berkas_sk']['name'], PATHINFO_EXTENSION);
        $berkas_sk_name = "sk_" . time() . "_" . uniqid() . "." . $ext_sk;
        if (!empty($old_data['berkas_sk']) && file_exists($upload_dir . $old_data['berkas_sk'])) {
            unlink($upload_dir . $old_data['berkas_sk']);
        }
        move_uploaded_file($_FILES['berkas_sk']['tmp_name'], $upload_dir . $berkas_sk_name);
    }

    if (isset($_FILES['sertifikat']) && $_FILES['sertifikat']['error'] == 0) {
        $ext_sertifikat = pathinfo($_FILES['sertifikat']['name'], PATHINFO_EXTENSION);
        $sertifikat_name = "sertifikat_" . time() . "_" . uniqid() . "." . $ext_sertifikat;
        if (!empty($old_data['sertifikat']) && file_exists($upload_dir . $old_data['sertifikat'])) {
            unlink($upload_dir . $old_data['sertifikat']);
        }
        move_uploaded_file($_FILES['sertifikat']['tmp_name'], $upload_dir . $sertifikat_name);
    }

    $stmt_up = $conn->prepare("UPDATE pendaftaran SET nama_lengkap = ?, nik = ?, telepon = ?, usia = ?, jenis_kelamin = ?, program_pilihan = ?, lulusan_sekolah = ?, nama_sekolah_asal = ?, alamat = ?, foto_kk = ?, pasfoto = ?, berkas_sk = ?, sertifikat = ?, catatan_admin = ?, status = ? WHERE id = ?");
    $stmt_up->bind_param("sssisssssssssssi", $nama_lengkap, $nik, $telepon, $usia, $jenis_kelamin, $program_pilihan, $lulusan_sekolah, $nama_sekolah_asal, $alamat, $foto_kk_name, $pasfoto_name, $berkas_sk_name, $sertifikat_name, $catatan_admin, $status, $id);
    
    if ($stmt_up->execute()) {
        $stmt_up->close();
        catatLogAktivitas($conn, "Memperbarui data dan sertifikat pendaftar atas nama: " . $nama_lengkap, "Pendaftaran");
        header("Location: pendaftaran.php?status=sukses_update");
        exit;
    } else {
        $stmt_up->close();
        header("Location: pendaftaran.php?status=gagal_update");
        exit;
    }
}

// Tangkap pesan status dari URL hasil redirect
$sukses = "";
$error = "";
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_update') {
        $sukses = "Data pendaftar dan sertifikat berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Data pendaftar berhasil dihapus dari sistem.";
    } elseif ($_GET['status'] == 'gagal_update') {
        $error = "Gagal memperbarui data pendaftar.";
    } elseif ($_GET['status'] == 'gagal_hapus') {
        $error = "Gagal menghapus data pendaftar.";
    } elseif ($_GET['status'] == 'akses_ditolak') {
        $error = "Akses ditolak! Hanya Super Admin yang memiliki hak untuk menghapus data.";
    }
}

// Ambil data untuk form edit jika parameter edit tersedia
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $q_edit = $conn->prepare("SELECT * FROM pendaftaran WHERE id = ?");
    $q_edit->bind_param("i", $id_edit);
    $q_edit->execute();
    $edit_data = $q_edit->get_result()->fetch_assoc();
    $q_edit->close();
}

// Ambil data untuk menu detail jika parameter detail tersedia
$detail_data = null;
if (isset($_GET['detail'])) {
    $id_detail = intval($_GET['detail']);
    $q_det = $conn->prepare("SELECT * FROM pendaftaran WHERE id = ?");
    $q_det->bind_param("i", $id_detail);
    $q_det->execute();
    $detail_data = $q_det->get_result()->fetch_assoc();
    $q_det->close();
}

$result_pendaftaran = $conn->query("SELECT * FROM pendaftaran ORDER BY tanggal_daftar DESC");

// Muat Header Admin
require_once 'header.php';
?>

<!-- Pembungkus Utama Layout -->
<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">

    <div>
        <!-- Topbar / Header Mobile -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 sticky top-0 z-30 md:hidden">
            <button onclick="toggleSidebar()" class="text-slate-600 hover:text-slate-900 focus:outline-none p-2 rounded-lg hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="font-bold text-sm text-slate-800">Data Pendaftaran & Sertifikat</span>
            <div class="w-6"></div>
        </header>

        <!-- Konten Utama -->
        <main class="p-6 md:p-8 w-full max-w-7xl mx-auto overflow-x-hidden">

            <!-- Notifikasi Sukses / Gagal -->
            <?php if (!empty($sukses)): ?>
                <div id="notifAlert" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex items-center justify-between shadow-sm transition-opacity duration-500">
                    <span>✅ <?php echo $sukses; ?></span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold hover:text-emerald-900">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div id="notifAlert" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm flex items-center justify-between shadow-sm transition-opacity duration-500">
                    <span>⚠️ <?php echo $error; ?></span>
                    <button onclick="this.parentElement.remove()" class="text-rose-700 font-bold hover:text-rose-900">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Modal / Card Detail Pendaftar -->
            <?php if ($detail_data): ?>
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 md:p-8 mb-8">
                <div class="flex justify-between items-center mb-6 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Detail Pendaftar: <?php echo htmlspecialchars($detail_data['nama_lengkap']); ?></h2>
                        <p class="text-xs text-slate-500 mt-0.5">Informasi lengkap biodata dan berkas persyaratan peserta.</p>
                    </div>
                    <a href="pendaftaran.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Tutup Detail ✕</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Username & Tanggal</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm">@<?php echo htmlspecialchars($detail_data['username']); ?></p>
                        <p class="text-slate-500 mt-0.5">Didaftarkan pada: <?php echo htmlspecialchars($detail_data['tanggal_daftar']); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">NIK</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($detail_data['nik'] ?: '-'); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">No. Telepon / WhatsApp</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($detail_data['telepon']); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Usia & Jenis Kelamin</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($detail_data['usia'] ?: '-'); ?> Tahun, <?php echo htmlspecialchars($detail_data['jenis_kelamin'] ?: '-'); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Program Pilihan</p>
                        <p class="font-medium text-indigo-600 mt-1 text-sm"><?php echo htmlspecialchars($detail_data['program_pilihan']); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Pendidikan Terakhir</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo htmlspecialchars($detail_data['lulusan_sekolah'] ?: '-'); ?> <?php echo htmlspecialchars($detail_data['nama_sekolah_asal'] ?: '-'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Alamat Sesuai KTP / Domisili</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm"><?php echo nl2br(htmlspecialchars($detail_data['alamat'])); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Status Saat Ini</p>
                        <p class="mt-1">
                            <span class="px-3 py-1 rounded-full font-semibold text-xs border 
                                <?php 
                                    if($detail_data['status'] == 'Diterima') echo 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                    elseif($detail_data['status'] == 'Ditolak') echo 'bg-rose-50 text-rose-700 border-rose-200';
                                    else echo 'bg-amber-50 text-amber-700 border-amber-200';
                                ?>">
                                <?php echo htmlspecialchars($detail_data['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="md:col-span-3">
                        <p class="font-semibold text-slate-400 uppercase tracking-wider">Catatan Admin</p>
                        <p class="font-medium text-slate-800 mt-1 text-sm bg-slate-50 p-3 rounded-xl border border-slate-200"><?php echo !empty($detail_data['catatan_admin']) ? nl2br(htmlspecialchars($detail_data['catatan_admin'])) : '<span class="text-slate-400 italic">Tidak ada catatan dari admin.</span>'; ?></p>
                    </div>
                </div>

                <!-- Lampiran Berkas -->
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <h3 class="text-xs font-bold text-slate-900 mb-3 uppercase tracking-wider">Berkas Persyaratan & Sertifikat</h3>
                    <div class="flex flex-wrap gap-4">
                        <?php if(!empty($detail_data['pasfoto'])): ?>
                            <a href="../assets/uploads/<?php echo $detail_data['pasfoto']; ?>" target="_blank" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-medium text-slate-700 transition">🖼️ Lihat Pasfoto</a>
                        <?php endif; ?>
                        <?php if(!empty($detail_data['foto_kk'])): ?>
                            <a href="../assets/uploads/<?php echo $detail_data['foto_kk']; ?>" target="_blank" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-medium text-slate-700 transition">📄 Lihat Foto KK</a>
                        <?php endif; ?>
                        <?php if(!empty($detail_data['berkas_sk'])): ?>
                            <a href="../assets/uploads/<?php echo $detail_data['berkas_sk']; ?>" target="_blank" class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-xl text-xs font-medium transition">📜 Lihat Berkas SK / Ijazah</a>
                        <?php endif; ?>
                        <?php if(!empty($detail_data['sertifikat'])): ?>
                            <a href="../assets/uploads/<?php echo $detail_data['sertifikat']; ?>" target="_blank" class="px-4 py-2 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-xl text-xs font-medium transition">🏆 Lihat Sertifikat</a>
                        <?php else: ?>
                            <span class="text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200 font-medium">⚠️ Sertifikat belum diunggah. Silakan klik tombol "Edit / Sertifikat" di tabel untuk mengunggah sertifikat.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form Edit / Input Sertifikat -->
            <?php if ($edit_data): ?>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 mb-8">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">Kelola Data & Sertifikat: <span class="text-indigo-600"><?php echo htmlspecialchars($edit_data['nama_lengkap']); ?></span></h2>
                    <a href="pendaftaran.php" class="text-xs text-rose-600 hover:underline font-semibold">Tutup Form ✕</a>
                </div>

                <form action="pendaftaran.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                    <input type="hidden" name="update_pendaftaran" value="1">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($edit_data['nama_lengkap']); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">NIK</label>
                            <input type="text" name="nik" value="<?php echo htmlspecialchars($edit_data['nik'] ?? ''); ?>" maxlength="16" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="telepon" value="<?php echo htmlspecialchars($edit_data['telepon']); ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Usia</label>
                            <input type="number" name="usia" value="<?php echo htmlspecialchars($edit_data['usia'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                                <option value="Laki-laki" <?php echo ($edit_data['jenis_kelamin'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?php echo ($edit_data['jenis_kelamin'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Status Pendaftaran</label>
                            <select name="status" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-indigo-600">
                                <option value="Diperiksa" <?php echo ($edit_data['status'] == 'Diperiksa') ? 'selected' : ''; ?>>Diperiksa</option>
                                <option value="Diterima" <?php echo ($edit_data['status'] == 'Diterima') ? 'selected' : ''; ?>>Diterima</option>
                                <option value="Ditolak" <?php echo ($edit_data['status'] == 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Program Pilihan</label>
                            <select name="program_pilihan" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-indigo-600">
                                <option value="HomeCare" <?php echo ($edit_data['program_pilihan'] == 'HomeCare') ? 'selected' : ''; ?>>HomeCare</option>
                                <option value="CareGriver" <?php echo ($edit_data['program_pilihan'] == 'CareGriver') ? 'selected' : ''; ?>>CareGriver</option>
                                <option value="BabyCare" <?php echo ($edit_data['program_pilihan'] == 'BabyCare') ? 'selected' : ''; ?>>BabyCare</option>
                                <option value="DayCare" <?php echo ($edit_data['program_pilihan'] == 'DayCare') ? 'selected' : ''; ?>>DayCare</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Lulusan Sekolah</label>
                            <input type="text" name="lulusan_sekolah" value="<?php echo htmlspecialchars($edit_data['lulusan_sekolah'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Sekolah Asal</label>
                            <input type="text" name="nama_sekolah_asal" value="<?php echo htmlspecialchars($edit_data['nama_sekolah_asal'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
                        <textarea name="alamat" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm"><?php echo htmlspecialchars($edit_data['alamat']); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Admin (Opsional)</label>
                        <textarea name="catatan_admin" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Catatan atau kekurangan berkas untuk peserta..."><?php echo htmlspecialchars($edit_data['catatan_admin'] ?? ''); ?></textarea>
                    </div>

                    <!-- Upload Berkas Termasuk Sertifikat -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-3 border-t border-slate-200">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ganti KK</label>
                            <input type="file" name="foto_kk" accept="image/*,.pdf" class="w-full text-xs text-slate-600 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white cursor-pointer">
                            <?php if (!empty($edit_data['foto_kk'])): ?>
                                <p class="text-xs text-slate-500 mt-1">Saat ini: <a href="../assets/uploads/<?php echo $edit_data['foto_kk']; ?>" target="_blank" class="text-indigo-600 underline">Lihat KK</a></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ganti Pasfoto</label>
                            <input type="file" name="pasfoto" accept="image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white cursor-pointer">
                            <?php if (!empty($edit_data['pasfoto'])): ?>
                                <p class="text-xs text-slate-500 mt-1">Saat ini: <a href="../assets/uploads/<?php echo $edit_data['pasfoto']; ?>" target="_blank" class="text-indigo-600 underline">Lihat Pasfoto</a></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Upload/Ganti Ijazah / SK</label>
                            <input type="file" name="berkas_sk" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white cursor-pointer">
                            <?php if (!empty($edit_data['berkas_sk'])): ?>
                                <p class="text-xs text-slate-500 mt-1">Saat ini: <a href="../assets/uploads/<?php echo $edit_data['berkas_sk']; ?>" target="_blank" class="text-indigo-600 underline">Lihat Ijazah</a></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-sky-700 mb-1">🏆 Upload / Update Sertifikat</label>
                            <input type="file" name="sertifikat" accept=".pdf,.doc,.docx,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-sky-600 file:text-white cursor-pointer">
                            <?php if (!empty($edit_data['sertifikat'])): ?>
                                <p class="text-xs text-sky-600 mt-1 font-semibold">Tersedia: <a href="../assets/uploads/<?php echo $edit_data['sertifikat']; ?>" target="_blank" class="underline">Lihat Sertifikat</a></p>
                            <?php else: ?>
                                <p class="text-xs text-amber-600 italic mt-1 font-medium">Sertifikat belum diunggah</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <a href="pendaftaran.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-medium transition">Batal</a>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition shadow-sm cursor-pointer">Simpan Perubahan & Sertifikat</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Tabel Daftar Pendaftar -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="p-6 border-b border-slate-200 grid grid-cols-1 md:grid-cols-12 items-center gap-4">
                    <div class="md:col-span-5">
                        <h2 class="text-base font-bold text-slate-900">Manajemen Data Pendaftaran & Sertifikat</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola status, upload sertifikat pelatihan, perbarui berkas, atau hapus data pendaftar.</p>
                    </div>
                    
                    <!-- Filter Berdasarkan Program Pilihan -->
                    <div class="md:col-span-3">
                        <select id="filterProgram" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                            <option value="">-- Semua Program --</option>
                            <option value="HomeCare">HomeCare</option>
                            <option value="CareGriver">CareGriver</option>
                            <option value="BabyCare">BabyCare</option>
                            <option value="DayCare">DayCare</option>
                        </select>
                    </div>

                    <!-- Input Pencarian Berdasarkan Nama -->
                    <div class="md:col-span-4">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 text-sm">🔍</span>
                            <input type="text" id="searchInput" placeholder="Cari berdasarkan nama lengkap..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto min-h-[350px]">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="p-4 w-12">No</th>
                                <th class="p-4 w-36">Tanggal Daftar</th>
                                <th class="p-4">Nama & Username</th>
                                <th class="p-4">Program Pilihan</th>
                                <th class="p-4">Berkas (KK, Pasfoto, SK, Sertifikat)</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="pendaftaranTableBody" class="divide-y divide-slate-200 text-slate-700">
                            <?php if ($result_pendaftaran->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result_pendaftaran->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4 font-medium text-slate-500"><?php echo $no++; ?></td>
                                        <td class="p-4 text-xs text-slate-500 whitespace-nowrap"><?php echo $row['tanggal_daftar']; ?></td>
                                        <td class="p-4">
                                            <span class="font-bold block text-slate-900"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
                                            <span class="text-xs text-indigo-600">@<?php echo htmlspecialchars($row['username']); ?></span>
                                            <span class="text-xs text-slate-500 block">📞 <?php echo htmlspecialchars($row['telepon']); ?></span>
                                        </td>
                                        <td class="p-4 text-xs font-medium">
                                            <?php echo htmlspecialchars($row['program_pilihan']); ?>
                                        </td>
                                        <td class="p-4 text-xs space-y-1 whitespace-nowrap">
                                            <?php if (!empty($row['foto_kk'])): ?>
                                                <a href="../assets/uploads/<?php echo $row['foto_kk']; ?>" target="_blank" class="block text-indigo-600 hover:underline">📄 Lihat KK</a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($row['pasfoto'])): ?>
                                                <a href="../assets/uploads/<?php echo $row['pasfoto']; ?>" target="_blank" class="block text-indigo-600 hover:underline">🖼️ Lihat Pasfoto</a>
                                            <?php endif; ?>

                                            <?php if (!empty($row['berkas_sk'])): ?>
                                                <a href="../assets/uploads/<?php echo $row['berkas_sk']; ?>" target="_blank" class="block text-emerald-600 font-semibold hover:underline">📜 Lihat Ijazah</a>
                                            <?php endif; ?>

                                            <?php if (!empty($row['sertifikat'])): ?>
                                                <a href="../assets/uploads/<?php echo $row['sertifikat']; ?>" target="_blank" class="block text-sky-600 font-semibold hover:underline">🏆 Lihat Sertifikat</a>
                                            <?php else: ?>
                                                <span class="text-amber-600 block italic text-[11px] font-medium">⚠️ Sertifikat belum ada</span>
                                            <?php endif; ?>
                                        </td>
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
                                            <a href="pendaftaran.php?detail=<?php echo $row['id']; ?>" class="inline-block bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                                Detail
                                            </a>
                                            <a href="pendaftaran.php?edit=<?php echo $row['id']; ?>" class="inline-block bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                                Edit / Sertifikat
                                            </a>
                                            <?php if ($is_super_admin): ?>
                                                <a href="pendaftaran.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus data pendaftar ini beserta seluruh berkasnya?');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                                    Hapus
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-slate-400 font-medium">Belum ada data pendaftaran.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>


</div>

<!-- Skrip JavaScript (Pencarian AJAX & Filter Program) -->
<script>
    if (window.history.replaceState) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            urlParams.delete('status');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({path: newUrl}, '', newUrl);
        }
    }

    setTimeout(() => {
        const alertBox = document.getElementById('notifAlert');
        if (alertBox) {
            alertBox.style.opacity = "0";
            setTimeout(() => alertBox.remove(), 500);
        }
    }, 3000);

    // Fungsi Gabungan Pencarian Nama & Filter Program via AJAX
    let searchTimeout;
    function fetchFilteredData() {
        let keyword = document.getElementById('searchInput').value;
        let program = document.getElementById('filterProgram').value;
        let tbody = document.getElementById('pendaftaranTableBody');

        tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium animate-pulse">Memuat data...</td></tr>';

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            let xhr = new XMLHttpRequest();
            let url = 'pendaftaran.php?ajax_search=' + encodeURIComponent(keyword) + '&ajax_program=' + encodeURIComponent(program);
            xhr.open('GET', url, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    tbody.innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }, 200);
    }

    document.getElementById('searchInput').addEventListener('input', fetchFilteredData);
    document.getElementById('filterProgram').addEventListener('change', fetchFilteredData);
</script>

<?php
require_once 'footer.php';
?>