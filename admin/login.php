<?php
session_start();
require_once '../config.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // Melakukan query ke tabel 'admins' (mengambil kolom role)
        $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, role FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            // Verifikasi password hash
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_username'] = $row['username'];
                $_SESSION['admin_nama'] = $row['nama_lengkap'];
                $_SESSION['admin_role'] = $row['role']; // Role disimpan: 'super_admin', 'koordinator', atau 'admin'
                
                header("Location: index.php");
                exit;
            } else {
                $error = "Password yang Anda masukkan salah!";
            }
        } else {
            $error = "Username admin tidak ditemukan!";
        }
        $stmt->close();
    } else {
        $error = "Mohon isi username dan password.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - LPK Bhakti Kencana</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white border border-slate-200 rounded-3xl p-8 shadow-xl">
        <div class="text-center mb-8">
            <div class="inline-block bg-indigo-600 text-white font-bold p-3 rounded-2xl text-xl mb-3">LPK</div>
            <h1 class="text-2xl font-extrabold text-slate-900">Admin Login</h1>
            <p class="text-slate-500 text-sm mt-1">Masuk untuk mengelola data pendaftaran</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-xl mb-4 text-sm text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username</label>
                <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Masukkan username admin">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="••••••••">
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition shadow-lg text-sm mt-2">
                Masuk ke Dashboard
            </button>
        </form>

<div class="text-center mt-6">
    <a href="/LPK_PBK/index.php" class="text-xs text-indigo-600 hover:underline">← Kembali ke Beranda Website</a>
</div>
    </div>

</body>
</html>