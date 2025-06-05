<?php
// File: reaktorsystem/ajax/ajax_get_klub_by_cabor.php

// Path ke init_core.php relatif dari lokasi file ini
$init_core_path = __DIR__ . '/../core/init_core.php';

if (file_exists($init_core_path)) {
    require_once($init_core_path);
} else {
    // Jika init_core.php tidak ada, ini adalah error fatal untuk skrip AJAX
    header('Content-Type: application/json');
    error_log("AJAX_GET_KLUB_FATAL: init_core.php tidak ditemukan di " . $init_core_path);
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan konfigurasi server (INIT_CORE_AJAX_MISSING).', 'klub_list' => []]);
    exit;
}

// Set header output ke JSON harus setelah semua include PHP murni dan SEBELUM output apapun
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Gagal memproses permintaan.', 'klub_list' => []];

// Pastikan $pdo sudah terdefinisi dari init_core.php dan koneksi berhasil
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("AJAX_GET_KLUB_ERROR: Koneksi PDO tidak valid dari init_core.php.");
    $response['message'] = "Kesalahan koneksi database internal."; // Pesan lebih generik untuk user
    echo json_encode($response);
    exit();
}

// Pengecekan apakah pengguna sudah login (opsional, tergantung kebutuhan keamanan endpoint ini)
// Jika Anda ingin endpoint ini hanya bisa diakses oleh pengguna yang login:
/*
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik)) {
    $response['message'] = "Akses ditolak. Anda harus login untuk mengakses data ini.";
    // HTTP 401 Unauthorized atau 403 Forbidden bisa digunakan jika Anda mau
    // http_response_code(401); 
    echo json_encode($response);
    exit();
}
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Pastikan request adalah POST
    if (isset($_POST['id_cabor'])) {
        $id_cabor_req_raw = $_POST['id_cabor'];
        
        // Validasi input id_cabor
        if (filter_var($id_cabor_req_raw, FILTER_VALIDATE_INT) && (int)$id_cabor_req_raw > 0) {
            $id_cabor_req = (int)$id_cabor_req_raw;

            // Keamanan tambahan (opsional): Jika yang akses pengurus cabor, 
            // pastikan dia hanya akses cabornya atau jika admin, boleh semua.
            // Ini relevan jika Anda menambahkan pengecekan login di atas.
            /*
            if (isset($user_role_utama) && $user_role_utama == 'pengurus_cabor') {
                if (!isset($id_cabor_pengurus_utama) || $id_cabor_pengurus_utama != $id_cabor_req) {
                    $response['message'] = 'Akses ditolak. Anda tidak memiliki izin untuk data cabor ini.';
                    // http_response_code(403);
                    echo json_encode($response);
                    exit();
                }
            }
            */

            try {
                $stmt = $pdo->prepare("SELECT id_klub, nama_klub 
                                       FROM klub 
                                       WHERE id_cabor = :id_cabor AND status_approval_admin = 'disetujui' 
                                       ORDER BY nama_klub ASC");
                $stmt->bindParam(':id_cabor', $id_cabor_req, PDO::PARAM_INT);
                $stmt->execute();
                $klub_list_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($klub_list_ajax !== false) { // fetchAll mengembalikan array, atau false jika error (jarang terjadi jika query benar)
                    $response['status'] = 'success';
                    $response['klub_list'] = $klub_list_ajax; // Akan menjadi array kosong jika tidak ada hasil
                    $response['message'] = count($klub_list_ajax) . ' klub ditemukan untuk cabor ID ' . $id_cabor_req . '.';
                } else {
                    // Ini seharusnya tidak terjadi jika query valid, tapi sebagai fallback
                    error_log("AJAX_GET_KLUB_ERROR: fetchAll gagal untuk Cabor ID: " . $id_cabor_req);
                    $response['message'] = 'Gagal mengambil data klub dari database.';
                }

            } catch (PDOException $e) {
                error_log("AJAX_GET_KLUB_DB_ERROR: " . $e->getMessage() . " untuk Cabor ID: " . $id_cabor_req);
                $response['message'] = 'Terjadi kesalahan pada server saat mengambil data klub.'; // Pesan lebih generik
            }
        } else {
            $response['message'] = 'ID Cabang Olahraga yang diterima tidak valid.';
        }
    } else {
        $response['message'] = 'Parameter ID Cabang Olahraga tidak ditemukan dalam permintaan.';
    }
} else {
    $response['message'] = 'Metode permintaan tidak valid. Harap gunakan POST.';
    // http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
// Pastikan tidak ada output lain (spasi, HTML, dll.) sebelum atau sesudah ini
?>