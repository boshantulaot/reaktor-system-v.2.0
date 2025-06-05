<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_restore_aset.php

// Sertakan file inisialisasi inti
if (file_exists(__DIR__ . '/../../core/init_core.php')) { // Path dari admin/manajemen_data/ ke core/
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    $_SESSION['asset_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_RESTORE_ASET_INIT_CORE_MISSING).";
    $_SESSION['asset_feedback_type'] = "danger";
    error_log("PROSES_RESTORE_ASET_FATAL: init_core.php tidak ditemukan.");
    header("Location: manajemen_data.php");
    exit();
}

// --- PENTING: Proteksi Akses Skrip ---
if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['asset_feedback'] = "Anda tidak memiliki izin untuk melakukan operasi ini.";
    $_SESSION['asset_feedback_type'] = "danger";
    header("Location: manajemen_data.php");
    exit();
}

// Pastikan kelas ZipArchive ada
if (!class_exists('ZipArchive')) {
    $_SESSION['asset_feedback'] = "Kesalahan Server: Ekstensi PHP Zip (ZipArchive) tidak aktif. Tidak dapat melakukan restore aset.";
    $_SESSION['asset_feedback_type'] = "danger";
    error_log("PROSES_RESTORE_ASET_ERROR: Kelas ZipArchive tidak ditemukan.");
    header("Location: manajemen_data.php");
    exit();
}

// Pastikan $pdo dan $app_base_path tersedia dari init_core.php
if (!isset($pdo) || !$pdo instanceof PDO || !isset($app_base_path)) {
    $_SESSION['asset_feedback'] = "Konfigurasi sistem tidak lengkap untuk restore aset.";
    $_SESSION['asset_feedback_type'] = "danger";
    error_log("PROSES_RESTORE_ASET_ERROR: Variabel PDO atau APP_BASE_PATH tidak terdefinisi.");
    header("Location: manajemen_data.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_assets_now'])) {

    @set_time_limit(0); // Proses ekstrak bisa lama
    @ini_set('memory_limit', '1024M');

    if (isset($_FILES['zip_file_asset_restore']) && $_FILES['zip_file_asset_restore']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['zip_file_asset_restore']['tmp_name'];
        $file_name = $_FILES['zip_file_asset_restore']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'zip') {
            $_SESSION['asset_feedback'] = "Format file tidak valid. Hanya file .zip yang diizinkan untuk restore aset.";
            $_SESSION['asset_feedback_type'] = "danger";
            header("Location: manajemen_data.php");
            exit();
        }

        // Path tujuan ekstrak (folder uploads utama)
        $target_extract_path_app_relative = 'assets/uploads';
        $server_document_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
        $path_aplikasi_di_server = $server_document_root . rtrim($app_base_path, '/\\');
        $target_extract_path_server = rtrim($path_aplikasi_di_server, '/') . '/' . ltrim($target_extract_path_app_relative, '/');
        $target_extract_path_server = preg_replace('/\/+/', '/', $target_extract_path_server);

        if (!is_dir($target_extract_path_server) || !is_writable($target_extract_path_server)) {
            $_SESSION['asset_feedback'] = "Error: Folder tujuan restore ('" . htmlspecialchars($target_extract_path_app_relative) . "') tidak ditemukan atau tidak dapat ditulis di server.";
            $_SESSION['asset_feedback_type'] = "danger";
            error_log("PROSES_RESTORE_ASET_ERROR: Folder target ekstrak tidak valid atau tidak bisa ditulis: " . $target_extract_path_server);
            header("Location: manajemen_data.php");
            exit();
        }

        $zip = new ZipArchive();
        $res = $zip->open($file_tmp_path);

        if ($res === TRUE) {
            // Ekstrak file ZIP ke folder uploads
            // Ini akan menimpa file yang ada jika namanya sama.
            if ($zip->extractTo($target_extract_path_server)) {
                $extracted_files_count = $zip->numFiles;
                $zip->close();

                $_SESSION['asset_feedback'] = "Aset berhasil di-restore dari file '" . htmlspecialchars($file_name) . "'. Sebanyak {$extracted_files_count} item diekstrak.";
                $_SESSION['asset_feedback_type'] = "success";

                // Audit Log
                try {
                    if ($pdo && $user_nik) {
                        $aksi_log = "RESTORE ASET (FOLDER UPLOADS) BERHASIL";
                        $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                        $log_stmt->execute(['un' => $user_nik, 'a' => $aksi_log, 'detail' => "Dari file: " . htmlspecialchars($file_name) . ", Item diekstrak: " . $extracted_files_count]);
                    }
                } catch (PDOException $e_log) { error_log("PROSES_RESTORE_ASET_AUDIT_ERROR: " . $e_log->getMessage()); }

            } else {
                $zip_status_string = $zip->getStatusString(); // Dapatkan status error dari ZipArchive
                $zip->close();
                $_SESSION['asset_feedback'] = "Gagal mengekstrak file dari arsip ZIP. Pastikan file ZIP valid dan folder tujuan bisa ditulis. Status ZipArchive: " . htmlspecialchars($zip_status_string);
                $_SESSION['asset_feedback_type'] = "danger";
                error_log("PROSES_RESTORE_ASET_ERROR: Gagal extractTo. File ZIP: " . $file_name . ". Target: " . $target_extract_path_server . ". Status Zip: " . $zip_status_string);
            }
        } else {
            $_SESSION['asset_feedback'] = "Gagal membuka file arsip ZIP. Kode error: " . $res;
            $_SESSION['asset_feedback_type'] = "danger";
            error_log("PROSES_RESTORE_ASET_ERROR: Gagal membuka file ZIP '" . $file_name . "'. Kode error ZipArchive: " . $res);
        }
        
        // Hapus file temporary upload PHP
        if (file_exists($file_tmp_path)) {
            @unlink($file_tmp_path);
        }
        
        header("Location: manajemen_data.php");
        exit();

    } elseif (isset($_FILES['zip_file_asset_restore']['error']) && $_FILES['zip_file_asset_restore']['error'] != UPLOAD_ERR_NO_FILE) {
        $upload_errors = [ /* ... (daftar kode error upload PHP) ... */ ];
        $error_message = $upload_errors[$_FILES['zip_file_asset_restore']['error']] ?? "Error tidak diketahui (Kode: " . $_FILES['zip_file_asset_restore']['error'] . ").";
        $_SESSION['asset_feedback'] = "Gagal mengupload file ZIP backup aset: " . htmlspecialchars($error_message);
        $_SESSION['asset_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    } else {
        $_SESSION['asset_feedback'] = "Tidak ada file ZIP yang dipilih atau file kosong untuk restore aset.";
        $_SESSION['asset_feedback_type'] = "warning";
        header("Location: manajemen_data.php");
        exit();
    }
} else {
    $_SESSION['asset_feedback'] = "Aksi tidak valid untuk restore aset.";
    $_SESSION['asset_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
// Hapus tag ?> jika ini akhir file
?>