<?php
// File: reaktorsystem/ajax/cek_nik_pengurus_cabor.php

// Set header respons ke JSON
header('Content-Type: application/json');

// Path ke init_core.php (sesuaikan jika struktur folder Anda berbeda)
// Asumsi: ajax/ ada di root aplikasi, dan core/ juga di root aplikasi
$path_to_init_core = dirname(__DIR__) . '/core/init_core.php';

if (file_exists($path_to_init_core)) {
    require_once($path_to_init_core);
} else {
    // Jika init_core.php tidak ditemukan, ini adalah error konfigurasi fatal untuk script ini
    echo json_encode([
        'status' => 'error_config', 
        'message' => 'Kesalahan konfigurasi server: File inisialisasi inti tidak ditemukan.'
    ]);
    exit;
}

// Pastikan koneksi PDO ($pdo) dan variabel $app_base_path sudah ada dari init_core.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    echo json_encode([
        'status' => 'error_db_connection',
        'message' => 'Kesalahan database: Koneksi tidak tersedia.'
    ]);
    exit;
}

// Hanya proses jika request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error_method', 'message' => 'Metode request tidak valid. Hanya POST yang diizinkan.']);
    exit;
}

// Ambil dan sanitasi input
$nik_to_check_ajax = isset($_POST['nik']) ? trim(filter_var($_POST['nik'], FILTER_SANITIZE_STRING)) : null;
// id_cabor_editing bersifat opsional, hanya dikirim saat proses edit cabor
$id_cabor_editing_ajax = isset($_POST['id_cabor_editing']) ? filter_var($_POST['id_cabor_editing'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;

// Default response
$response_ajax = ['status' => 'error_input', 'message' => 'NIK tidak valid atau tidak diberikan.'];

if (empty($nik_to_check_ajax) || !preg_match('/^\d{16}$/', $nik_to_check_ajax)) {
    $response_ajax['message'] = 'Format NIK tidak valid. Harus 16 digit angka.';
    echo json_encode($response_ajax);
    exit;
}

try {
    // Query untuk mengecek apakah NIK sudah digunakan sebagai Ketua, Sekretaris, atau Bendahara di tabel cabang_olahraga
    $sql_check_nik_pengurus = "SELECT 
                                   c.id_cabor, 
                                   c.nama_cabor,
                                   p.nama_lengkap AS nama_pengguna_terdaftar,
                                   CASE 
                                       WHEN c.ketua_cabor_nik = :nik_param THEN 'Ketua'
                                       WHEN c.sekretaris_cabor_nik = :nik_param THEN 'Sekretaris'
                                       WHEN c.bendahara_cabor_nik = :nik_param THEN 'Bendahara'
                                       ELSE 'Tidak Diketahui' 
                                   END as jabatan_di_cabor_lain
                               FROM cabang_olahraga c
                               LEFT JOIN pengguna p ON 
                                   (c.ketua_cabor_nik = :nik_param AND p.nik = c.ketua_cabor_nik) OR
                                   (c.sekretaris_cabor_nik = :nik_param AND p.nik = c.sekretaris_cabor_nik) OR
                                   (c.bendahara_cabor_nik = :nik_param AND p.nik = c.bendahara_cabor_nik)
                               WHERE (:nik_param IN (c.ketua_cabor_nik, c.sekretaris_cabor_nik, c.bendahara_cabor_nik))";
    
    $params_check_nik_sql = [':nik_param' => $nik_to_check_ajax];

    // Jika sedang dalam proses edit cabor, kecualikan cabor yang sedang diedit dari pengecekan
    if ($id_cabor_editing_ajax !== null && $id_cabor_editing_ajax > 0) {
        $sql_check_nik_pengurus .= " AND c.id_cabor != :id_cabor_editing_param";
        $params_check_nik_sql[':id_cabor_editing_param'] = $id_cabor_editing_ajax;
    }

    $stmt_check_nik_exec = $pdo->prepare($sql_check_nik_pengurus);
    $stmt_check_nik_exec->execute($params_check_nik_sql);
    $existing_cabor_role_data = $stmt_check_nik_exec->fetch(PDO::FETCH_ASSOC);

    if ($existing_cabor_role_data) {
        $nama_pengguna_info = !empty($existing_cabor_role_data['nama_pengguna_terdaftar']) ? $existing_cabor_role_data['nama_pengguna_terdaftar'] . " (NIK: " . $nik_to_check_ajax . ")" : "NIK " . $nik_to_check_ajax;
        $response_ajax = [
            'status' => 'exists', 
            'message' => htmlspecialchars($nama_pengguna_info) . " sudah terdaftar sebagai " . 
                         htmlspecialchars($existing_cabor_role_data['jabatan_di_cabor_lain']) . 
                         " di Cabor \"" . htmlspecialchars($existing_cabor_role_data['nama_cabor']) . "\".",
            'nik_checked' => $nik_to_check_ajax,
            'cabor_name' => $existing_cabor_role_data['nama_cabor'],
            'jabatan' => $existing_cabor_role_data['jabatan_di_cabor_lain']
        ];
    } else {
        $response_ajax = [
            'status' => 'available', 
            'message' => 'NIK ' . htmlspecialchars($nik_to_check_ajax) . ' tersedia untuk dijadikan pengurus inti cabor.',
            'nik_checked' => $nik_to_check_ajax
        ];
    }

} catch (PDOException $e_ajax_check) {
    error_log("AJAX Cek NIK Pengurus Cabor Error: " . $e_ajax_check->getMessage() . " | Input NIK: " . $nik_to_check_ajax . " | ID Cabor Editing: " . ($id_cabor_editing_ajax ?? 'N/A'));
    $response_ajax = ['status' => 'error_server', 'message' => 'Kesalahan pada server saat memeriksa ketersediaan NIK. Silakan coba lagi.'];
}

// Kirim respons JSON
echo json_encode($response_ajax);
exit;
?>