<?php
// Bersihkan buffer output sebelumnya agar tidak ada spasi atau error HTML yang merusak JSON
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

// Panggil file konfigurasi database
include 'config.php';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

if (empty($keyword)) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan masukkan Nama atau NIK terlebih dahulu!']);
    exit;
}

// Pastikan koneksi $conn aktif
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}

// Gunakan prepared statement untuk mencari data berdasarkan nama_lengkap atau nik
$stmt = $conn->prepare("SELECT nama_lengkap, nik, telepon, program_pilihan, tanggal_daftar, status FROM pendaftaran WHERE nama_lengkap LIKE ? OR nik LIKE ?");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan Database: ' . $conn->error]);
    exit;
}

$searchTerm = "%" . $keyword . "%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'nama_lengkap'    => htmlspecialchars($row['nama_lengkap']),
            'nik'             => htmlspecialchars($row['nik']),
            'telepon'         => htmlspecialchars($row['telepon']),
            'program_pilihan' => htmlspecialchars($row['program_pilihan']),
            'tanggal_daftar'  => htmlspecialchars($row['tanggal_daftar']),
            'status'          => htmlspecialchars($row['status'])
        ];
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data peserta tidak ditemukan dengan Nama atau NIK tersebut.']);
}

$stmt->close();
$conn->close();
exit;
?>