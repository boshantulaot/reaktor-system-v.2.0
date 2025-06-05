<?php
// File: public_html/reaktorsystem/ajax/cek_nama_nik.php

if (file_exists(__DIR__ . '/../core/init_core.php')) {
    require_once(__DIR__ . '/../core/init_core.php');
} else {
    header('Content-Type: application/json');
    error_log("AJAX_CEK_NIK_FATAL: init_core.php tidak ditemukan.");
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan konfigurasi server (INIT_CORE_AJAX_MISSING).']);
    exit;
}

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Gagal memproses permintaan.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("AJAX_CEK_NIK_ERROR: Koneksi PDO tidak valid dari init_core.php.");
    $response['message'] = "Kesalahan koneksi database.";
    echo json_encode($response);
    exit();
}

if (isset($_POST['nik'])) {
    $nik_to_check = trim($_POST['nik']);
    // Ambil konteks; default ke 'cek_pengguna_umum' jika tidak ada, 
    // ini akan menjalankan logika lama untuk kompatibilitas.
    $context = isset($_POST['context']) ? trim($_POST['context']) : 'cek_pengguna_umum'; 

    if (preg_match('/^\d{16}$/', $nik_to_check)) {
        try {
            // Cek dulu apakah NIK ada di tabel pengguna
            $stmt_check_exist = $pdo->prepare("SELECT nik, nama_lengkap, is_approved FROM pengguna WHERE nik = :nik");
            $stmt_check_exist->bindParam(':nik', $nik_to_check, PDO::PARAM_STR);
            $stmt_check_exist->execute();
            $user_data = $stmt_check_exist->fetch(PDO::FETCH_ASSOC);

            if ($context === 'pengguna_baru') { // Konteks untuk form tambah pengguna baru
                if ($user_data) { // NIK sudah ada
                    $response = [
                        'status' => 'exists', 
                        'message' => 'NIK ini sudah terdaftar di sistem.',
                        'nama_lengkap' => htmlspecialchars($user_data['nama_lengkap'])
                    ];
                } else { // NIK belum ada
                    $response = [
                        'status' => 'available',
                        'message' => 'NIK ini tersedia untuk didaftarkan.'
                    ];
                }
            } elseif ($context === 'pengguna_edit_email_check') { // Konteks untuk cek email saat edit pengguna (berbeda file AJAX)
                // Logika ini seharusnya ada di file ajax/cek_email_unik.php
                // Jika tetap di sini, perlu parameter tambahan seperti nik_current
                $response['message'] = 'Konteks tidak sesuai untuk file ini (gunakan cek_email_unik.php).';

            } else { // Konteks default atau 'cek_pengguna_umum' (logika lama untuk form atlet, dll.)
                if ($user_data) { // NIK ditemukan
                    if ($user_data['is_approved'] == 1) {
                        $response = [
                            'status' => 'success', 
                            'nama_lengkap' => htmlspecialchars($user_data['nama_lengkap']),
                            'message' => 'Pengguna ditemukan dan aktif.'
                        ];
                    } else {
                        $response = [
                            'status' => 'pending_approval', 
                            'nama_lengkap' => htmlspecialchars($user_data['nama_lengkap']),
                            'message' => 'Akun pengguna ini ditemukan tetapi belum disetujui/aktif.'
                        ];
                    }
                } else { // NIK tidak ditemukan
                    $response = [
                        'status' => 'not_found',
                        'message' => 'NIK tidak terdaftar sebagai pengguna di sistem.'
                    ];
                }
            }
        } catch (PDOException $e) {
            error_log("AJAX_CEK_NIK_DB_ERROR: " . $e->getMessage() . " untuk NIK: " . $nik_to_check . " Konteks: " . $context);
            $response['message'] = 'Kesalahan query database saat memvalidasi NIK.';
        }
    } else {
        $response['message'] = 'Format NIK tidak valid (harus 16 digit angka).';
    }
} else {
    $response['message'] = 'Parameter NIK tidak diterima.';
}

echo json_encode($response);
// Tidak perlu exit() setelah ini
?>