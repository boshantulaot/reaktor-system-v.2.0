<?php
// File: modules/atlet/proses_tambah_atlet.php

if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    $_SESSION['pesan_error_global'] = "Kesalahan konfigurasi sistem inti (INIT_CORE_MISSING).";
    error_log("PROSES_TAMBAH_ATLET_FATAL: init_core.php tidak ditemukan.");
    header("Location: tambah_atlet.php");
    exit();
}

// Proteksi Akses & User NIK Pelaku
if (!isset($user_role_utama) || !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk melakukan tindakan ini.";
    header("Location: " . APP_URL_BASE . "/dashboard.php");
    exit();
}
if (empty($user_nik)) {
    $_SESSION['pesan_error_global'] = "Sesi pengguna tidak valid atau NIK tidak ditemukan.";
    header("Location: " . APP_URL_BASE . "/auth/login.php");
    exit();
}
$nik_pelaku_aksi = $user_nik; // NIK yang melakukan aksi pendaftaran

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    error_log("PROSES_TAMBAH_ATLET_FATAL: Objek PDO tidak tersedia.");
    header("Location: tambah_atlet.php");
    exit();
}

if (isset($_POST['submit_tambah_atlet'])) {
    // Ambil data dari form
    $nik_atlet = trim($_POST['nik'] ?? '');
    $id_cabor = filter_var($_POST['id_cabor'] ?? null, FILTER_VALIDATE_INT);
    $id_klub = !empty($_POST['id_klub']) ? filter_var($_POST['id_klub'], FILTER_VALIDATE_INT) : null;

    $errors = [];

    // 1. Validasi Input Dasar
    if (empty($nik_atlet) || !preg_match('/^\d{16}$/', $nik_atlet)) {
        $errors[] = "NIK Calon Atlet wajib diisi dan harus 16 digit angka.";
    }
    if (empty($id_cabor)) {
        $errors[] = "Cabang Olahraga wajib dipilih.";
    }

    // Cek apakah NIK ada di tabel pengguna dan sudah disetujui
    if (empty($errors)) { 
        try {
            $stmt_cek_pengguna = $pdo->prepare("SELECT nik, is_approved FROM pengguna WHERE nik = :nik");
            $stmt_cek_pengguna->execute([':nik' => $nik_atlet]);
            $data_pengguna = $stmt_cek_pengguna->fetch(PDO::FETCH_ASSOC);

            if (!$data_pengguna) {
                $errors[] = "NIK '{$nik_atlet}' tidak terdaftar sebagai pengguna di sistem.";
            } elseif ($data_pengguna['is_approved'] != 1) {
                $errors[] = "Akun pengguna dengan NIK '{$nik_atlet}' belum disetujui oleh administrator.";
            }
        } catch (PDOException $e) {
            $errors[] = "Gagal memvalidasi NIK pengguna. Coba lagi.";
            error_log("PROSES_TAMBAH_ATLET_VALIDASI_PENGGUNA_ERROR: " . $e->getMessage());
        }
    }

    // Cek apakah NIK dan Cabor sudah terdaftar sebagai atlet (UNIQUE KEY `nik_id_cabor_unique_atlet`)
    if (empty($errors) && $id_cabor) {
        try {
            $stmt_cek_atlet = $pdo->prepare("SELECT id_atlet FROM atlet WHERE nik = :nik AND id_cabor = :id_cabor");
            $stmt_cek_atlet->execute([':nik' => $nik_atlet, ':id_cabor' => $id_cabor]);
            if ($stmt_cek_atlet->fetch()) {
                $errors[] = "Atlet dengan NIK '{$nik_atlet}' sudah terdaftar untuk cabang olahraga ini.";
            }
        } catch (PDOException $e) {
            $errors[] = "Gagal memvalidasi duplikasi atlet. Coba lagi.";
            error_log("PROSES_TAMBAH_ATLET_VALIDASI_DUPLIKAT_ERROR: " . $e->getMessage());
        }
    }
    
    if ($user_role_utama === 'pengurus_cabor') {
        $id_cabor_pengurus_session = $_SESSION['id_cabor_pengurus_utama'] ?? null;
        if ($id_cabor != $id_cabor_pengurus_session) {
            $errors[] = "Anda hanya dapat mendaftarkan atlet untuk cabang olahraga yang Anda kelola.";
        }
    }

    // Cek dan Ambil Path KTP/KK yang Sudah Ada untuk NIK Ini
    $existing_ktp_path_from_db = null;
    $existing_kk_path_from_db = null;
    $should_upload_ktp_new = true; // Asumsi perlu upload KTP baru
    $should_upload_kk_new = true;  // Asumsi perlu upload KK baru

    if (empty($errors)) { 
        try {
            $stmt_check_existing_docs = $pdo->prepare(
                "SELECT ktp_path, kk_path 
                 FROM atlet 
                 WHERE nik = :nik AND (ktp_path IS NOT NULL OR kk_path IS NOT NULL) 
                 ORDER BY created_at DESC 
                 LIMIT 1"
            );
            $stmt_check_existing_docs->execute([':nik' => $nik_atlet]);
            $found_docs = $stmt_check_existing_docs->fetch(PDO::FETCH_ASSOC);

            if ($found_docs) {
                if (!empty($found_docs['ktp_path'])) {
                    $existing_ktp_path_from_db = $found_docs['ktp_path'];
                    $should_upload_ktp_new = false; // KTP sudah ada
                }
                if (!empty($found_docs['kk_path'])) {
                    $existing_kk_path_from_db = $found_docs['kk_path'];
                    $should_upload_kk_new = false; // KK sudah ada
                }
            }
        } catch (PDOException $e) {
            error_log("PROSES_TAMBAH_ATLET_CEK_EXISTING_DOCS_ERROR: " . $e->getMessage());
        }
    }
    
    $path_pas_foto_db = null;
    $path_ktp_db = $existing_ktp_path_from_db; // Default ke path lama jika ada
    $path_kk_db = $existing_kk_path_from_db;   // Default ke path lama jika ada

    if (empty($errors)) {
        $file_prefix = "atlet_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nik_atlet);

        // Upload Pas Foto (Selalu diproses jika ada file baru)
        if (isset($_FILES['pas_foto_path']) && $_FILES['pas_foto_path']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['pas_foto_path']['size'] > 0) {
            // Untuk pas foto, kita tidak menggunakan path lama karena ini foto spesifik atlet-cabor
            $path_pas_foto_db = uploadFileGeneral('pas_foto_path', 'pas_foto_atlet', $file_prefix . "_pasfoto", ['jpg', 'jpeg', 'png', 'gif'], MAX_FILE_SIZE_FOTO_PROFIL_MB, $errors, null, false);
        }

        // Upload KTP
        // Hanya proses upload jika (memang belum ada KTP LAMA ATAU pengguna secara eksplisit upload file KTP baru)
        if ($should_upload_ktp_new || (isset($_FILES['ktp_path']) && $_FILES['ktp_path']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['ktp_path']['size'] > 0) ) {
            if (isset($_FILES['ktp_path']) && $_FILES['ktp_path']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['ktp_path']['size'] > 0) {
                // Jika ada KTP lama dan diupload baru, fungsi uploadFileGeneral akan menghapus yang lama jika path lama diberikan.
                // Namun, karena KTP ini berlaku untuk semua profil atlet NIK ini, penghapusan file lama harus hati-hati jika pathnya sama persis.
                // Untuk saat ini, jika ada file baru, kita anggap menggantikan.
                $path_ktp_db = uploadFileGeneral('ktp_path', 'ktp_kk_atlet', $file_prefix . "_ktp", ['jpg', 'jpeg', 'png', 'pdf'], MAX_FILE_SIZE_KTP_KK_MB, $errors, $existing_ktp_path_from_db, false);
            }
        }
        
        // Upload KK
        if ($should_upload_kk_new || (isset($_FILES['kk_path']) && $_FILES['kk_path']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['kk_path']['size'] > 0) ) {
            if (isset($_FILES['kk_path']) && $_FILES['kk_path']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['kk_path']['size'] > 0) {
                $path_kk_db = uploadFileGeneral('kk_path', 'ktp_kk_atlet', $file_prefix . "_kk", ['jpg', 'jpeg', 'png', 'pdf'], MAX_FILE_SIZE_KTP_KK_MB, $errors, $existing_kk_path_from_db, false);
            }
        }
    }

    // Validasi tambahan jika KTP/KK masih kosong (sesuaikan dengan kebijakan wajib atau tidak)
    // if (empty($path_ktp_db)) { $errors[] = "Scan KTP wajib diupload."; }
    // if (empty($path_kk_db)) { $errors[] = "Scan KK wajib diupload."; }
    // if (empty($path_pas_foto_db)) { $errors[] = "Pas Foto wajib diupload."; }


    if (!empty($errors)) {
        $_SESSION['errors_tambah_atlet'] = $errors;
        $_SESSION['form_data_tambah_atlet'] = $_POST; 
        header("Location: tambah_atlet.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $sql_insert_atlet = "INSERT INTO atlet (
                                nik, id_cabor, id_klub, 
                                status_pendaftaran, 
                                pas_foto_path, ktp_path, kk_path,
                                created_by_nik, updated_by_nik, 
                                created_at, last_updated_process_at 
                             ) VALUES (
                                :nik, :id_cabor, :id_klub,
                                :status_pendaftaran,
                                :pas_foto_path, :ktp_path, :kk_path,
                                :created_by_nik, :updated_by_nik,
                                NOW(), NOW() 
                             )";
        $stmt_insert_atlet = $pdo->prepare($sql_insert_atlet);

        $status_pendaftaran_awal = 'pending'; 
        if ($user_role_utama === 'pengurus_cabor') {
            $status_pendaftaran_awal = 'verifikasi_pengcab';
        }
        // if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        //     $status_pendaftaran_awal = 'disetujui'; // Opsional jika admin bisa langsung setujui
        // }

        $data_atlet_to_insert = [
            ':nik' => $nik_atlet,
            ':id_cabor' => $id_cabor,
            ':id_klub' => $id_klub,
            ':status_pendaftaran' => $status_pendaftaran_awal,
            ':pas_foto_path' => $path_pas_foto_db, 
            ':ktp_path' => $path_ktp_db, // Akan menggunakan path KTP yang sudah ada atau yang baru diupload
            ':kk_path' => $path_kk_db,   // Akan menggunakan path KK yang sudah ada atau yang baru diupload
            ':created_by_nik' => $nik_pelaku_aksi,
            ':updated_by_nik' => $nik_pelaku_aksi
        ];

        $stmt_insert_atlet->execute($data_atlet_to_insert);
        $lastInsertIdAtlet = $pdo->lastInsertId();

        if ($lastInsertIdAtlet) {
            $data_baru_untuk_log = [];
            foreach ($data_atlet_to_insert as $key => $value) {
                $data_baru_untuk_log[ltrim($key, ':')] = $value;
            }
            $data_baru_untuk_log['id_atlet'] = $lastInsertIdAtlet;

            if (function_exists('catatAuditLog')) {
                catatAuditLog(
                    $pdo,
                    $nik_pelaku_aksi,
                    'TAMBAH_PROFIL_ATLET',
                    'atlet',
                    $lastInsertIdAtlet,
                    null, 
                    json_encode($data_baru_untuk_log),
                    "Profil atlet baru untuk NIK {$nik_atlet} (Cabor ID: {$id_cabor}) berhasil didaftarkan."
                );
            }

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Profil atlet baru untuk NIK {$nik_atlet} berhasil didaftarkan. Status: {$status_pendaftaran_awal}.";
            unset($_SESSION['form_data_tambah_atlet']);
            header("Location: daftar_atlet.php" . ($id_cabor ? "?id_cabor=" . $id_cabor : "")); 
            exit();

        } else {
            $pdo->rollBack();
            if ($path_pas_foto_db && defined('APP_PATH_BASE') && file_exists(APP_PATH_BASE . '/' . $path_pas_foto_db)) @unlink(APP_PATH_BASE . '/' . $path_pas_foto_db);
            // Hanya hapus KTP/KK baru jika memang diupload di proses ini, bukan yang lama
            if ($should_upload_ktp_new && $path_ktp_db && $path_ktp_db != $existing_ktp_path_from_db && defined('APP_PATH_BASE') && file_exists(APP_PATH_BASE . '/' . $path_ktp_db)) @unlink(APP_PATH_BASE . '/' . $path_ktp_db);
            if ($should_upload_kk_new && $path_kk_db && $path_kk_db != $existing_kk_path_from_db && defined('APP_PATH_BASE') && file_exists(APP_PATH_BASE . '/' . $path_kk_db)) @unlink(APP_PATH_BASE . '/' . $path_kk_db);
            
            $_SESSION['errors_tambah_atlet'] = ["Gagal menyimpan data atlet ke database (lastInsertId tidak valid)."];
            $_SESSION['form_data_tambah_atlet'] = $_POST;
            header("Location: tambah_atlet.php");
            exit();
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($path_pas_foto_db && defined('APP_PATH_BASE') && file_exists(APP_PATH_BASE . '/' . $path_pas_foto_db)) @unlink(APP_PATH_BASE . '/' . $path_pas_foto_db);
        if ($should_upload_ktp_new && $path_ktp_db && $path_ktp_db != $existing_ktp_path_from_db && defined('APP_PATH_BASE') && file_exists(APP_PATH_BASE . '/' . $path_ktp_db)) @unlink(APP_PATH_BASE . '/' . $path_ktp_db);
        if ($should_upload_kk_new && $path_kk_db && $path_kk_db != $existing_kk_path_from_db && defined('APP_PATH_BASE') && file_exists(APP_PATH_BASE . '/' . $path_kk_db)) @unlink(APP_PATH_BASE . '/' . $path_kk_db);

        error_log("PROSES_TAMBAH_ATLET_DB_ERROR: " . $e->getMessage());
        $db_error_message = (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? " (" . $e->getMessage() . ")" : "";
        $_SESSION['errors_tambah_atlet'] = ["Error Database: Terjadi kesalahan saat menyimpan data atlet." . $db_error_message];
        $_SESSION['form_data_tambah_atlet'] = $_POST;
        header("Location: tambah_atlet.php");
        exit();
    }

} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau tidak ada data yang dikirim.";
    header("Location: tambah_atlet.php");
    exit();
}
?>