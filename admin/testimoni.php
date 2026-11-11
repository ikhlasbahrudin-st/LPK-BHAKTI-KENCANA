<?php
// 1. Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek sesi login admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php"); 
    exit;
}

require_once '../config.php';

$sukses = "";
$error = "";

// Tangkap status dari Redirect URL
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $sukses = "Testimoni baru berhasil ditambahkan!";
    } elseif ($_GET['status'] == 'sukses_edit') {
        $sukses = "Testimoni berhasil diperbarui!";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $sukses = "Testimoni berhasil dihapus.";
    } elseif ($_GET['status'] == 'gagal') {
        $error = "Terjadi kesalahan pada sistem database.";
    }
}

// ==========================================
// PROSES TAMBAH & EDIT DATA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $nama = trim($_POST['nama']);
    $pekerjaan_lokasi = trim($_POST['pekerjaan_lokasi']);
    $rating = intval($_POST['rating']);
    $pesan = trim($_POST['pesan']);
    $status = trim($_POST['status']);
    
    // Handle Upload Foto
    $foto = $_POST['foto_lama'] ?? '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = 'testi_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], '../assets/uploads/' . $foto);
    }

    if ($action == 'tambah') {
        $stmt = $conn->prepare("INSERT INTO testimoni (nama, pekerjaan_lokasi, rating, pesan, foto, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisss", $nama, $pekerjaan_lokasi, $rating, $pesan, $foto, $status);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: testimoni.php?status=sukses_tambah");
            exit;
        } else {
            $stmt->close();
            header("Location: testimoni.php?status=gagal");
            exit;
        }
    } elseif ($action == 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE testimoni SET nama=?, pekerjaan_lokasi=?, rating=?, pesan=?, foto=?, status=? WHERE id=?");
        $stmt->bind_param("ssisssi", $nama, $pekerjaan_lokasi, $rating, $pesan, $foto, $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: testimoni.php?status=sukses_edit");
            exit;
        } else {
            $stmt->close();
            header("Location: testimoni.php?status=gagal");
            exit;
        }
    }
}

// ==========================================
// PROSES HAPUS DATA (GET)
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    if ($conn->query("DELETE FROM testimoni WHERE id = $id_hapus")) {
        header("Location: testimoni.php?status=sukses_hapus");
        exit;
    } else {
        header("Location: testimoni.php?status=gagal");
        exit;
    }
}

// Ambil semua data testimoni
$result_testi = $conn->query("SELECT * FROM testimoni ORDER BY id DESC");

require_once 'header.php';
?>

<div class="min-h-screen flex flex-col justify-between bg-slate-50 md:ml-64 transition-all duration-300">
    <main class="p-6 md:p-8 w-full max-w-7xl mx-auto flex-grow">
        
        <!-- Header & Tombol Tambah -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <div>
                <span class="bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Ulasan Pengguna</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold mt-2 text-slate-900">Kelola Testimoni & Ulasan</h1>
                <p class="text-slate-600 text-sm mt-1">Tambah, edit, atau sembunyikan testimoni yang tampil di halaman utama website.</p>
            </div>
            <button onclick="openModal('tambah')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-3 rounded-xl text-sm shadow-md transition inline-flex items-center gap-2 cursor-pointer">
                <span>➕ Tambah Testimoni</span>
            </button>
        </div>

        <!-- Notifikasi Sukses / Gagal (Otomatis hilang dalam 3 detik) -->
        <?php if (!empty($sukses)): ?>
            <div id="notifAlert" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-sm flex justify-between items-center shadow-xs transition-opacity duration-500">
                <span>✅ <?= $sukses; ?></span>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold hover:text-emerald-900 cursor-pointer">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div id="notifAlert" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm flex justify-between items-center shadow-xs transition-opacity duration-500">
                <span>❌ <?= $error; ?></span>
                <button onclick="this.parentElement.remove()" class="text-rose-700 font-bold hover:text-rose-900 cursor-pointer">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Tabel Data Testimoni -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            <th class="p-4 w-12">No</th>
                            <th class="p-4">Foto</th>
                            <th class="p-4">Pengulas</th>
                            <th class="p-4">Rating</th>
                            <th class="p-4">Pesan</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-slate-700">
                        <?php if ($result_testi && $result_testi->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result_testi->fetch_assoc()): 
                                $foto_path = !empty($row['foto']) ? '../assets/uploads/' . $row['foto'] : 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop&crop=faces';
                            ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-medium text-slate-500"><?= $no++; ?></td>
                                    <td class="p-4">
                                        <img src="<?= htmlspecialchars($foto_path); ?>" alt="Foto" class="w-10 h-10 rounded-full object-cover border-2 border-indigo-100 shadow-xs">
                                    </td>
                                    <td class="p-4">
                                        <span class="font-bold block text-slate-900"><?= htmlspecialchars($row['nama']); ?></span>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars($row['pekerjaan_lokasi']); ?></span>
                                    </td>
                                    <td class="p-4 text-amber-500 text-xs whitespace-nowrap"><?= str_repeat('★', $row['rating']); ?></td>
                                    <td class="p-4 text-xs text-slate-600 max-w-xs italic">"<?= htmlspecialchars($row['pesan']); ?>"</td>
                                    <td class="p-4 text-center whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= ($row['status'] == 'Aktif') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                                            <?= $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center space-x-1 whitespace-nowrap">
                                        <button onclick='editTestimoni(<?= json_encode($row); ?>)' class="inline-block bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer">Edit</button>
                                        <a href="testimoni.php?hapus=<?= $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus testimoni ini?');" class="inline-block bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="p-12 text-center text-slate-400 font-medium">Belum ada data testimoni.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php require_once 'footer.php'; ?>
</div>

<!-- ================= MODAL POPUP TAMBAH / EDIT TESTIMONI ================= -->
<div id="testimoniModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white">
            <h3 id="modalTitle" class="font-bold text-lg">Tambah Testimoni Baru</h3>
            <button onclick="closeModal()" class="text-white text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <form action="testimoni.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="foto_lama" id="formFotoLama">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pemberi Ulasan *</label>
                <input type="text" name="nama" id="nama" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: Ibu Siti Nurhaliza">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pekerjaan / Lokasi *</label>
                <input type="text" name="pekerjaan_lokasi" id="pekerjaan_lokasi" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: Warga Bojong, Cianjur">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Rating Bintang (1-5) *</label>
                    <select name="rating" id="rating" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="5">★★★★★ (5 Bintang)</option>
                        <option value="4">★★★★☆ (4 Bintang)</option>
                        <option value="3">★★★☆☆ (3 Bintang)</option>
                        <option value="2">★★☆☆☆ (2 Bintang)</option>
                        <option value="1">★☆☆☆☆ (1 Bintang)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Tampil *</label>
                    <select name="status" id="status" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Aktif">Aktif (Tampil)</option>
                        <option value="Tidak Aktif">Tidak Aktif (Sembunyikan)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Foto Profil (Opsional)</label>
                <input type="file" name="foto" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-sm bg-slate-50">
                <span class="text-[10px] text-slate-400 mt-1 block">Biarkan kosong jika tidak ingin mengubah foto saat edit.</span>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Isi Pesan Testimoni *</label>
                <textarea name="pesan" id="pesan" rows="3" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Tuliskan ulasan pelanggan..."></textarea>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition text-sm cursor-pointer">Simpan Testimoni</button>
        </form>
    </div>
</div>

<!-- SCRIPT JAVASCRIPT -->
<script>
    // 1. Bersihkan parameter status dari URL setelah dimuat agar tidak terduplikasi saat F5
    if (window.history.replaceState) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            urlParams.delete('status');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({path: newUrl}, '', newUrl);
        }
    }

    // 2. Timer otomatis menghilangkan alert sukses/gagal dalam 3 detik
    setTimeout(() => {
        const alertBox = document.getElementById('notifAlert');
        if (alertBox) {
            alertBox.style.transition = "opacity 0.5s ease";
            alertBox.style.opacity = "0";
            setTimeout(() => alertBox.remove(), 500);
        }
    }, 3000);

    const modal = document.getElementById('testimoniModal');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formId');
    const formFotoLama = document.getElementById('formFotoLama');
    const namaInput = document.getElementById('nama');
    const lokasiInput = document.getElementById('pekerjaan_lokasi');
    const ratingInput = document.getElementById('rating');
    const statusInput = document.getElementById('status');
    const pesanInput = document.getElementById('pesan');

    function openModal(mode) {
        if (mode === 'tambah') {
            modalTitle.textContent = 'Tambah Testimoni Baru';
            formAction.value = 'tambah';
            formId.value = '';
            formFotoLama.value = '';
            namaInput.value = '';
            lokasiInput.value = '';
            ratingInput.value = '5';
            statusInput.value = 'Aktif';
            pesanInput.value = '';
        }
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function editTestimoni(data) {
        modalTitle.textContent = 'Edit Testimoni Ulasan';
        formAction.value = 'edit';
        formId.value = data.id;
        formFotoLama.value = data.foto || '';
        namaInput.value = data.nama;
        lokasiInput.value = data.pekerjaan_lokasi;
        ratingInput.value = data.rating;
        statusInput.value = data.status;
        pesanInput.value = data.pesan;
        
        modal.classList.remove('hidden');
    }
</script>