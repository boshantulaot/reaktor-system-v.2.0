<?php
// File: public_html/reaktorsystem/ajax/ajax_get_atlet_by_cabor.php

// Sertakan file inisialisasi inti (untuk sesi, DB, path, dll.)
// PENTING: INI HARUS init_core.php, BUKAN header.php
if (file_exists(__DIR__ . '/../core/init_core.php')) {
    require_once(__DIR__ . '/../core/init_core.php');
} else {
    // Jika init_core.php tidak ada, ini adalah error fatal untuk skrip AJAX
    // Keluarkan respons JSON error dan catat ke log.
    header('Content-Type: application/json');
    error_log("AJAX_GET_ATLET_FATAL: init_core.php tidak ditemukan.");
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan konfigurasi server (INIT_CORE_AJAX_MISSING).']);
    exit;
}

header('Content-Type: application/json'); // Set header output ke JSON harus setelah semua include PHP murni

$response = ['status' => 'error', 'message' => 'Gagal memproses permintaan.', 'atlet_list' => []];

// Pastikan $pdo sudah terdefinisi dari init_core.php dan koneksi berhasil
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("AJAX_GET_ATLET_ERROR: Koneksi PDO tidak valid dari init_core.php.");
    $response['message'] = "Koneksi database tidak tersedia.";
    echo json_encode($response);
    exit();
}

// Pengecekan Akses & Session (menggunakan variabel dari init_core.php)
// Anda mungkin ingin menambahkan pengecekan peran di sini jika hanya peran tertentu yang boleh akses
if ($user_login_status !== true || !isset($user_nik)) {
    $response['message'] = "Akses ditolak. Sesi tidak valid atau belum login.";
    // Mungkin tidak perlu redirect di AJAX, cukup kirim error
    // header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php"); // Hindari redirect di AJAX
    echo json_encode($response);
    exit();
}


if (isset($_POST['id_cabor'])) {
    $id_cabor_ajax_raw = $_POST['id_cabor'];
    
    // Validasi input id_cabor
    if (filter_var($id_cabor_ajax_raw, FILTER_VALIDATE_INT) && (int)$id_cabor_ajax_raw > 0) {
        $id_cabor_ajax = (int)$id_cabor_ajax_raw;

        // Keamanan tambahan: Jika yang akses pengurus cabor, pastikan dia hanya akses cabornya
        if (isset($_SESSION['user_role_utama']) && $_SESSION['user_role_utama'] == 'pengurus_cabor') {
            if (!isset($_SESSION['id_cabor_pengurus_utama']) || $_SESSION['id_cabor_pengurus_utama'] != $id_cabor_ajax) {
                $response['message'] = 'Akses ditolak. Anda tidak memiliki izin untuk melihat data atlet cabor ini.';
                echo json_encode($response);
                exit();
            }
        }

        try {
            $stmt = $pdo->prepare("SELECT p.nik, p.nama_lengkap 
                                   FROM atlet a 
                                   JOIN pengguna p ON a.nik = p.nik 
                                   WHERE a.id_cabor = :id_cabor AND a.status_pendaftaran = 'disetujui' AND p.is_approved = 1
                                   ORDER BY p.nama_lengkap ASC");
            $stmt->bindParam(':id_cabor', $id_cabor_ajax, PDO::PARAM_INT);
            $stmt->execute();
            $atlet_list_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($atlet_list_ajax) {
                $response['status'] = 'success';
                $response['atlet_list'] = $atlet_list_ajax;
                $response['message'] = count($atlet_list_ajax) . ' atlet ditemukan.';
            } else {
                $response['status'] = 'success'; // Tetap success, tapi list kosong
                $response['message'] = 'Tidak ada atlet aktif yang ditemukan untuk cabor ini.';
                $response['atlet_list'] = []; // Pastikan atlet_list adalah array kosong
            }
        } catch (PDOException $e) {
            error_log("AJAX_GET_ATLET_DB_ERROR: " . $e->getMessage() . " untuk Cabor ID: " . $id_cabor_ajax);
            $response['message'] = 'Kesalahan query database saat mengambil data atlet.';
        }
    } else {
        $response['message'] = 'ID Cabang Olahraga tidak valid atau kosong.';
    }
} else {
    $response['message'] = 'Parameter ID Cabang Olahraga tidak diterima.';
}

echo json_encode($response);
// Tidak perlu exit() lagi karena echo json_encode() adalah output terakhir yang valid
// Pastikan tidak ada spasi atau output lain setelah ini