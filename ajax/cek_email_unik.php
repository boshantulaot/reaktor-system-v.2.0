<?php
// File: reaktorsystem/ajax/cek_email_unik.php

// Set header ke JSON
header('Content-Type: application/json');

// Inisialisasi inti (untuk koneksi DB dan variabel dasar)
// Kita butuh $pdo dan mungkin $app_base_path jika ada path yang perlu di-construct
// Untuk AJAX yang ringan, kita bisa buat koneksi DB manual atau include init_core.php
// namun init_core.php mungkin terlalu berat.
// Pilihan: include file konfigurasi DB saja atau init_core.php yang lebih ramping khusus AJAX.
// Untuk saat ini, kita asumsikan init_core.php sudah cukup optimal atau kita akan include bagian penting saja.

if (file_exists(dirname(__DIR__) . '/core/init_core.php')) {
    require_once(dirname(__DIR__) . '/core/init_core.php');
} else {
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan konfigurasi: init_core.php tidak ditemukan.']);
    exit;
}

// Pastikan koneksi PDO ada
if (!isset($pdo) || !$pdo instanceof PDO) {
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan database: Tidak dapat terhubung.']);
    exit;
}

// Hanya proses jika request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit;
}

$email_to_check = isset($_POST['email']) ? trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)) : null;
$current_nik = isset($_POST['nik_current_check']) ? trim($_POST['nik_current_check']) : null;

$response = ['status' => 'error', 'message' => 'Data tidak lengkap atau email tidak valid.'];

if (empty($email_to_check) || !filter_var($email_to_check, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($response);
    exit;
}

try {
    // Query untuk cek apakah email sudah ada, KECUALI untuk NIK pengguna saat ini (jika sedang edit)
    $sql_cek_email = "SELECT nik, nama_lengkap FROM pengguna WHERE email = :email";
    $params_cek_email = [':email' => $email_to_check];

    if (!empty($current_nik)) {
        $sql_cek_email .= " AND nik != :current_nik";
        $params_cek_email[':current_nik'] = $current_nik;
    }

    $stmt_cek_email_ajax = $pdo->prepare($sql_cek_email);
    $stmt_cek_email_ajax->execute($params_cek_email);
    $existing_user_with_email = $stmt_cek_email_ajax->fetch(PDO::FETCH_ASSOC);

    if ($existing_user_with_email) {
        $response = [
            'status' => 'exists', 
            'message' => 'Email ini sudah digunakan oleh pengguna lain.',
            'used_by_nik' => $existing_user_with_email['nik'], // Opsional, untuk debugging atau info tambahan
            'used_by_name' => $existing_user_with_email['nama_lengkap'] // Opsional
        ];
    } else {
        $response = ['status' => 'available', 'message' => 'Email tersedia.'];
    }

} catch (PDOException $e) {
    error_log("AJAX Cek Email Unik Error: " . $e->getMessage() . " | Email: " . $email_to_check . " | NIK Current: " . $current_nik);
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan pada server saat memeriksa email.'];
}

echo json_encode($response);
exit;
?>