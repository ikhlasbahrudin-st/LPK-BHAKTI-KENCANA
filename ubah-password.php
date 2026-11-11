<?php
require_once 'config.php';
include 'header.php';

$sukses = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ubah_password'])) {
    $peserta_id   = $_SESSION['peserta_id'];
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi    = $_POST['konfirmasi_password'];

    if (!empty($password_lama) && !empty($password_baru) && !empty($konfirmasi)) {
        if ($password_baru === $konfirmasi) {
            // Ambil password lama dari database
            $stmt = $conn->prepare("SELECT password FROM pendaftaran WHERE id = ?");
            $stmt->bind_param("i", $peserta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($password_lama, $row['password'])) {
                // Update ke password baru yang di-hash
                $new_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE pendaftaran SET password = ? WHERE id = ?");
                $update->bind_param("si", $new_hash, $peserta_id);

                if ($update->execute()) {
                    $sukses = "Password berhasil diperbarui!";
                } else {
                    $error = "Terjadi kesalahan sistem saat memperbarui password.";
                }
                $update->close();
            } else {
                $error = "Password lama yang Anda masukkan salah!";
            }
        } else {
            $error = "Konfirmasi password baru tidak cocok!";
        }
    } else {
        $error = "Semua kolom password wajib diisi!";
    }
}
?>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Wrapper -->
<main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-6 md:px-8 py-4 flex justify-between items-center shadow-sm sticky top-0 z-30">
        <div class="flex items-center space-x-3">
            <!-- Tombol Hamburger (Hanya muncul di HP / layar kecil) -->
            <button id="hamburgerToggle" class="md:hidden text-slate-700 focus:outline-none p-1.5 rounded-xl hover:bg-slate-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h2 class="text-xs md:text-sm font-bold text-slate-800">Keamanan Akun</h2>
        </div>
        <span class="text-xs text-slate-500 font-medium hidden sm:inline">Halo, <b><?php echo htmlspecialchars($_SESSION['peserta_nama']); ?></b></span>
    </header>

    <!-- Content Body -->
    <div class="p-6 md:p-8 max-w-md w-full">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm">
            
            <h1 class="text-lg font-bold text-slate-900 mb-1">Ubah Password</h1>
            <p class="text-xs text-slate-500 mb-6">Perbarui password akun pendaftaran Anda secara berkala.</p>

            <?php if (!empty($sukses)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-6 text-xs flex items-center shadow-sm">
                    <span>✅ <?php echo $sukses; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-xs flex items-center shadow-sm">
                    <span>⚠️ <?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form action="ubah-password.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Password Lama *</label>
                    <input type="password" name="password_lama" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="••••••••">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Password Baru *</label>
                    <input type="password" name="password_baru" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="••••••••">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Password Baru *</label>
                    <input type="password" name="konfirmasi_password" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="••••••••">
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" name="ubah_password" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-xl text-xs transition shadow-lg shadow-emerald-200 text-center">
                        🔒 Simpan Password Baru
                    </button>
                </div>
            </form>

        </div>
    </div>
</main>

<?php include 'footer.php'; ?>