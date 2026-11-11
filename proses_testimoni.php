<?php
// Mulai sesi jika diperlukan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Panggil file konfigurasi database
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kirim_testimoni'])) {
    $nama = trim($_POST['nama']);
    $pekerjaan_lokasi = trim($_POST['pekerjaan_lokasi']);
    $rating = intval($_POST['rating']);
    $pesan = trim($_POST['pesan']);
    
    // Status diatur otomatis 'Tidak Aktif' agar harus ditinjau / dimoderasi admin terlebih dahulu
    $status = 'Tidak Aktif'; 
    
    // Tangkap dan simpan file foto jika diunggah oleh warga
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = 'testi_' . time() . '.' . $ext;
        
        // Pastikan folder assets/uploads/ ada
        if (!is_dir('assets/uploads/')) {
            mkdir('assets/uploads/', 0777, true);
        }
        
        move_uploaded_file($_FILES['foto']['tmp_name'], 'assets/uploads/' . $foto);
    }

    // Gunakan Prepared Statement untuk keamanan database
    $stmt = $conn->prepare("INSERT INTO testimoni (nama, pekerjaan_lokasi, rating, pesan, foto, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisss", $nama, $pekerjaan_lokasi, $rating, $pesan, $foto, $status);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo "<script>
                alert('Terima kasih! Ulasan dan testimoni Anda berhasil dikirim. Testimoni akan ditampilkan setelah ditinjau oleh admin.');
                window.location.href = 'index.php#testimoni';
              </script>";
        exit;
    } else {
        $stmt->close();
        echo "<script>
                alert('Maaf, terjadi kesalahan saat mengirim ulasan. Silakan coba kembali.');
                window.location.href = 'index.php#testimoni';
              </script>";
        exit;
    }
} else {
    // Jika diakses langsung tanpa lewat form submit
    header("Location: index.php");
    exit;
}