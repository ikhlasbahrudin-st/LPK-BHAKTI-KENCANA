<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'db_lpk_homecare';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Fungsi untuk mencatat aktivitas admin secara otomatis
function catatLogAktivitas($conn, $aktivitas, $kategori) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $admin_id = null;
    $admin_nama = "System Admin";

    // Cek data admin dari session yang sedang login
    if (isset($_SESSION['admin_id'])) {
        $admin_id = intval($_SESSION['admin_id']);
    }

    // Ambil nama lengkap dan ID langsung dari database berdasarkan session username / id untuk akurasi tinggi
    if ($admin_id) {
        $stmt_adm = $conn->prepare("SELECT id, nama_lengkap FROM admins WHERE id = ?");
        $stmt_adm->bind_param("i", $admin_id);
    } elseif (isset($_SESSION['admin_user'])) {
        $stmt_adm = $conn->prepare("SELECT id, nama_lengkap FROM admins WHERE username = ?");
        $stmt_adm->bind_param("s", $_SESSION['admin_user']);
    }

    if (isset($stmt_adm)) {
        $stmt_adm->execute();
        $res_adm = $stmt_adm->get_result()->fetch_assoc();
        if ($res_adm) {
            $admin_id = $res_adm['id'];
            $admin_nama = $res_adm['nama_lengkap'];
        }
        $stmt_adm->close();
    } elseif (isset($_SESSION['admin_nama'])) {
        $admin_nama = $_SESSION['admin_nama'];
    }

    // Masukkan log ke database
    $stmt_log = $conn->prepare("INSERT INTO aktivitas_log (admin_id, admin_nama, aktivitas, kategori) VALUES (?, ?, ?, ?)");
    if ($stmt_log) {
        $stmt_log->bind_param("isss", $admin_id, $admin_nama, $aktivitas, $kategori);
        $stmt_log->execute();
        $stmt_log->close();
    }
}
?>

