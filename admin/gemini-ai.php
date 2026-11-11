<?php
if (function_exists('ob_clean')) {
    @ob_clean();
}

header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

// ========================================
// 1. KONEKSI DATABASE
// ========================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_lpk_homecare';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'reply' => 'Maaf Kak, koneksi database sedang terganggu.',
        'show_table' => false,
        'pekerja_data' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$conn->set_charset("utf8mb4");

// ========================================
// 2. AMBIL INPUT JSON & HISTORY
// ========================================
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    $input = $_POST;
}

$user_message = isset($input['message']) ? trim($input['message']) : '';
$client_history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

if ($user_message === '' && !empty($client_history)) {
    $last_item = end($client_history);
    $user_message = isset($last_item['message']) ? trim($last_item['message']) : '';
}

if ($user_message === '') {
    echo json_encode([
        'success' => true,
        'reply' => 'Halo! Selamat datang di LPK Bhakti Kencana Cianjur. Ada yang bisa Cika bantu? Ketik nama pekerja atau tanyakan jadwal yang tersedia 😊',
        'show_table' => false,
        'pekerja_data' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========================================
// 3. LOGIKA PENCARIAN DATA PEKERJA
// ========================================
// (Gunakan logika pencarian yang sama seperti script Anda, pastikan aman dari SQL Injection)
$lower_msg = mb_strtolower($user_message);
$where_clauses = [];
$filter_type = "all";
$is_asking_data = false;

$stmt_all = $conn->query("SELECT id, nama_lengkap FROM pekerja");
$matched_names = [];
if ($stmt_all) {
    while ($row_name = $stmt_all->fetch_assoc()) {
        $db_name_lower = mb_strtolower($row_name['nama_lengkap']);
        if (strpos($lower_msg, $db_name_lower) !== false) {
            $matched_names[] = $row_name['nama_lengkap'];
        }
    }
}
$matched_names = array_unique($matched_names);

if (count($matched_names) > 0) {
    $is_asking_data = true;
    $filter_type = "specific_name";
    $name_conditions = [];
    foreach ($matched_names as $name) {
        $name_conditions[] = " nama_lengkap LIKE '%" . $conn->real_escape_string($name) . "%' ";
    }
    $where_clauses[] = " (" . implode(" OR ", $name_conditions) . ") ";
} else {
    $is_asking_all = preg_match('/(semua|seluruh|daftar|semuanya|jadwal)/ui', $user_message);
    if ($is_asking_all) {
        $is_asking_data = true;
        $filter_type = "all";
    } else {
        if (strpos($lower_msg, 'tersedia') !== false || strpos($lower_msg, 'kosong') !== false) {
            $where_clauses[] = " status_pekerja LIKE '%Tersedia%' ";
            $filter_type = "tersedia";
            $is_asking_data = true;
        } elseif (strpos($lower_msg, 'bertugas') !== false) {
            $where_clauses[] = " status_pekerja LIKE '%Bertugas%' ";
            $filter_type = "bertugas";
            $is_asking_data = true;
        }
    }
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";
$pekerja_list = [];

if ($is_asking_data) {
    $query = "SELECT id, nama_lengkap, spesialisasi, deskripsi, pengalaman, status_pekerja, tanggal_mulai, tanggal_selesai, jam_kerja, foto FROM pekerja" . $where_sql;
    $result_pekerja = $conn->query($query);
    if ($result_pekerja && $result_pekerja->num_rows > 0) {
        while ($row = $result_pekerja->fetch_assoc()) {
            $pekerja_list[] = $row;
        }
    }
}

$show_table = (count($pekerja_list) > 0);

// ========================================
// 4. GEMINI API REQUEST
// ========================================
$gemini_api_key = "AIzaSyBm7JTgTcrv4RhXObJMabl0ENA2oyizQRw";
$model = "gemini-1.5-flash"; // Gunakan model stabil
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . urlencode($gemini_api_key);

$system_instruction = "Kamu adalah Asisten AI resmi LPK Bhakti Kencana Cianjur bernama CIKA. Bersikaplah ramah dan profesional.
-jika sudah menjawab jangan bilanh hallo atau menyapa
-jika user memperkenalkan diri sapa balik dengan menyebutkan nama";

$gemini_contents = [];
foreach ($client_history as $chat) {
    $role = (isset($chat['role']) && ($chat['role'] === 'assistant' || $chat['role'] === 'model')) ? 'model' : 'user';
    $text = isset($chat['message']) ? trim($chat['message']) : '';
    if ($text !== '') {
        $gemini_contents[] = [
            "role" => $role,
            "parts" => [["text" => $text]]
        ];
    }
}

$gemini_contents[] = [
    "role" => "user",
    "parts" => [["text" => $user_message]]
];

$data = [
    "systemInstruction" => ["parts" => [["text" => $system_instruction]]],
    "contents" => $gemini_contents,
    "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 250]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$ai_reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Halo, ada yang bisa saya bantu terkait jadwal pekerja?';

echo json_encode([
    'success' => true,
    'reply' => trim($ai_reply),
    'show_table' => $show_table,
    'filter_type' => $filter_type,
    'pekerja_data' => $pekerja_list
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>