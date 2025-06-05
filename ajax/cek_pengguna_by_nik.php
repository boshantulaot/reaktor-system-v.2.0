<?php
// File: public_html/reaktorsystem/ajax/cek_pengguna_by_nik.php

if (file_exists(__DIR__ . '/../core/init_core.php')) {
    require_once(__DIR__ . '/../core/init_core.php');
} else {
    header('Content-Type: application/json');
    error_log("AJAX_CEK_PENGGUNA_FATAL: init_core.php tidak ditemukan.");
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan konfigurasi server (INIT_CORE_AJAX_MISSING).']);
    exit;
}

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("AJAX_CEK_PENGGUNA_ERROR: Koneksi PDO tidak valid.");
    $response['message'] = "Kesalahan koneksi database.";
    echo json_encode($response);
    exit();
}

if (isset($_POST['nik'])) {
    $nik_to_check = trim($_POST['nik']);
    $id_cabor_selected = isset($_POST['id_cabor']) && filter_var($_POST['id_cabor'], FILTER_VALIDATE_INT) && (int)$_POST['id_cabor'] > 0 ? (int)$_POST['id_cabor'] : null;
    
    // ========================================================================
    // PENAMBAHAN: Terima id_atlet_edit (opsional)
    // ========================================================================
    $id_atlet_being_edited = isset($_POST['id_atlet_edit']) && filter_var($_POST['id_atlet_edit'], FILTER_VALIDATE_INT) && (int)$_POST['id_atlet_edit'] > 0 ? (int)$_POST['id_atlet_edit'] : null;
    // ========================================================================
    // AKHIR PENAMBAHAN
    // ========================================================================

    // $context = $_POST['context'] ?? 'default_check'; // Konteks bisa digunakan untuk debugging atau logika yang lebih spesifik jika perlu

    if (preg_match('/^\d{16}$/', $nik_to_check)) {
        try {
            $stmt_user = $pdo->prepare("SELECT nik, nama_lengkap, is_approved FROM pengguna WHERE nik = :nik");
            $stmt_user->bindParam(':nik', $nik_to_check, PDO::PARAM_STR);
            $stmt_user->execute();
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user_data) {
                if ($user_data['is_approved'] == 1) {
                    // Cek apakah NIK sudah terdaftar sebagai atlet di cabor manapun
                    $stmt_atlet_any_cabor = $pdo->prepare("SELECT id_atlet FROM atlet WHERE nik = :nik_atlet_check LIMIT 1");
                    $stmt_atlet_any_cabor->bindParam(':nik_atlet_check', $nik_to_check, PDO::PARAM_STR);
                    $stmt_atlet_any_cabor->execute();
                    $is_already_atlet_in_any_cabor = $stmt_atlet_any_cabor->fetch() ? true : false;

                    // Cek apakah NIK sudah terdaftar di CABOR YANG DIPILIH
                    $is_already_atlet_in_selected_cabor = false; 
                    if ($id_cabor_selected !== null) {
                        // ========================================================================
                        // PENYESUAIAN: Modifikasi query untuk mengecualikan id_atlet_being_edited jika ada
                        // ========================================================================
                        $sql_check_specific_cabor = "SELECT id_atlet FROM atlet WHERE nik = :nik AND id_cabor = :id_cabor";
                        $params_check_specific_cabor = [':nik' => $nik_to_check, ':id_cabor' => $id_cabor_selected];
                        
                        if ($id_atlet_being_edited !== null) {
                            // Jika ini dari form EDIT, jangan hitung record yang sedang diedit sebagai duplikat untuk cabor yang sama
                            // (kecuali jika NIK diubah, tapi NIK tidak bisa diubah di form edit atlet)
                            // Pengecekan ini relevan jika pengguna MENGUBAH cabor di form edit ke cabor lain
                            // di mana NIK tersebut sudah terdaftar (dengan id_atlet yang berbeda).
                            $sql_check_specific_cabor .= " AND id_atlet != :id_atlet_exclude";
                            $params_check_specific_cabor[':id_atlet_exclude'] = $id_atlet_being_edited;
                        }
                        $sql_check_specific_cabor .= " LIMIT 1";
                        // ========================================================================
                        // AKHIR PENYESUAIAN
                        // ========================================================================

                        $stmt_atlet_specific_cabor = $pdo->prepare($sql_check_specific_cabor);
                        $stmt_atlet_specific_cabor->execute($params_check_specific_cabor);
                        if ($stmt_atlet_specific_cabor->fetch()) {
                            $is_already_atlet_in_selected_cabor = true;
                        }
                    }
                    
                    // Cek KTP/KK (Logika Anda sudah benar, dipertahankan)
                    $existing_ktp_path = null;
                    $existing_kk_path = null;
                    $stmt_docs = $pdo->prepare("SELECT ktp_path, kk_path FROM atlet WHERE nik = :nik_docs AND ktp_path IS NOT NULL AND kk_path IS NOT NULL LIMIT 1");
                    $stmt_docs->execute([':nik_docs' => $nik_to_check]);
                    $docs_data = $stmt_docs->fetch(PDO::FETCH_ASSOC);
                    if ($docs_data) {
                        $existing_ktp_path = $docs_data['ktp_path'];
                        $existing_kk_path = $docs_data['kk_path'];
                    }

                    $response = [
                        'status' => 'success',
                        'message' => 'Pengguna ditemukan dan aktif.',
                        'data_pengguna' => [
                            'nama_lengkap' => htmlspecialchars($user_data['nama_lengkap']),
                            'is_atlet_any_cabor' => $is_already_atlet_in_any_cabor,
                            'is_atlet_selected_cabor' => $is_already_atlet_in_selected_cabor,
                            'has_ktp' => !empty($existing_ktp_path),
                            'has_kk' => !empty($existing_kk_path)
                        ]
                    ];
                } else {
                    $response = ['status' => 'error', 'message' => 'Akun pengguna ini ditemukan tetapi belum disetujui/aktif.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'NIK tidak terdaftar sebagai pengguna di sistem.'];
            }
        } catch (PDOException $e) {
            error_log("AJAX_CEK_PENGGUNA_DB_ERROR (NIK: " . $nik_to_check . ", Cabor: " . ($id_cabor_selected ?? 'N/A') . ", EditID: " . ($id_atlet_being_edited ?? 'N/A') . "): " . $e->getMessage());
            $response['message'] = 'Kesalahan database saat memvalidasi NIK.';
        }
    } else {
        $response['message'] = 'Format NIK tidak valid (harus 16 digit angka).';
    }
} else {
    $response['message'] = 'Parameter NIK tidak diterima.';
}

echo json_encode($response);
exit();
?>