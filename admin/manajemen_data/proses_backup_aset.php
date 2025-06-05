<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_backup_aset.php

// Sertakan file inisialisasi inti
if (file_exists(__DIR__ . '/../../core/init_core.php')) { // Path dari admin/manajemen_data/ ke core/
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    // Gunakan key feedback yang berbeda untuk backup aset agar tidak tertimpa/tertukar dengan backup DB
    $_SESSION['asset_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_BACKUP_ASET_INIT_CORE_MISSING).";
    $_SESSION['asset_feedback_type'] = "danger";
    error_log("PROSES_BACKUP_ASET_FATAL: init_core.php tidak ditemukan.");
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

// Pastikan kelas ZipArchive ada (ekstensi PHP Zip harus aktif)
if (!class_exists('ZipArchive')) {
    $_SESSION['asset_feedback'] = "Kesalahan Server: Ekstensi PHP Zip (ZipArchive) tidak aktif. Tidak dapat membuat backup aset.";
    $_SESSION['asset_feedback_type'] = "danger";
    error_log("PROSES_BACKUP_ASET_ERROR: Kelas ZipArchive tidak ditemukan. Ekstensi ZIP PHP mungkin tidak diaktifkan.");
    header("Location: manajemen_data.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['backup_assets_now'])) {

    @set_time_limit(0); // Proses ZIP bisa lama, 0 untuk tanpa batas jika diizinkan
    @ini_set('memory_limit', '1024M'); // Untuk menangani banyak file atau file besar

    // Path ke folder uploads (relatif dari root aplikasi Anda, yaitu dari dalam folder 'reaktorsystem')
    $folder_to_backup_app_relative = 'assets/uploads';
    
    // Bangun path absolut di server ke folder uploads
    // $app_base_path sudah ada dari init_core.php (misal: /reaktorsystem/)
    // $_SERVER['DOCUMENT_ROOT'] adalah root dokumen web server (misal: /home/user/public_html)
    $server_document_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $path_aplikasi_di_server = $server_document_root . rtrim($app_base_path, '/\\'); // Path ke folder 'reaktorsystem'
    $path_aplikasi_di_server = preg_replace('/\/+/', '/', $path_aplikasi_di_server);
    
    $source_folder_path_server = rtrim($path_aplikasi_di_server, '/') . '/' . ltrim($folder_to_backup_app_relative, '/');
    $source_folder_path_server = preg_replace('/\/+/', '/', $source_folder_path_server);

    if (!is_dir($source_folder_path_server)) {
        $_SESSION['asset_feedback'] = "Error: Folder uploads ('" . htmlspecialchars($folder_to_backup_app_relative) . "') tidak ditemukan di server pada path: " . htmlspecialchars($source_folder_path_server);
        $_SESSION['asset_feedback_type'] = "danger";
        error_log("PROSES_BACKUP_ASET_ERROR: Folder sumber tidak ditemukan: " . $source_folder_path_server);
        header("Location: manajemen_data.php");
        exit();
    }

    // Nama file ZIP backup
    $zip_filename = "backup_aset_uploads_" . date("Y-m-d_H-i-s") . ".zip";
    
    // Path untuk menyimpan file ZIP sementara di server sebelum di-download
    // Buat folder 'temp_backups' di dalam 'assets/uploads/' jika belum ada
    $temp_zip_dir_app_relative = 'assets/uploads/temp_backups/';
    $temp_zip_dir_server = rtrim($path_aplikasi_di_server, '/') . '/' . ltrim($temp_zip_dir_app_relative, '/');
    $temp_zip_dir_server = preg_replace('/\/+/', '/', $temp_zip_dir_server);

    if (!file_exists($temp_zip_dir_server)) {
        if (!@mkdir($temp_zip_dir_server, 0755, true)) { // Buat secara rekursif
            $_SESSION['asset_feedback'] = "Gagal membuat direktori sementara ('" . htmlspecialchars($temp_zip_dir_app_relative) . "') untuk backup aset. Periksa izin tulis pada folder assets/uploads/.";
            $_SESSION['asset_feedback_type'] = "danger";
            error_log("PROSES_BACKUP_ASET_ERROR: Gagal membuat direktori: " . $temp_zip_dir_server);
            header("Location: manajemen_data.php");
            exit();
        }
    }
     if (!is_writable($temp_zip_dir_server)) { // Periksa lagi apakah bisa ditulis setelah @mkdir
        $_SESSION['asset_backup_feedback'] = "Direktori sementara ('" . htmlspecialchars($temp_zip_dir_app_relative) . "') untuk backup aset tidak dapat ditulis. Periksa izin folder.";
        $_SESSION['asset_feedback_type'] = "danger";
        error_log("PROSES_BACKUP_ASET_ERROR: Direktori tidak dapat ditulis: " . $temp_zip_dir_server);
        header("Location: manajemen_data.php");
        exit();
    }

    $zip_file_path_server = $temp_zip_dir_server . $zip_filename;

    $zip = new ZipArchive();
    $zip_open_status = $zip->open($zip_file_path_server, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($zip_open_status !== TRUE) {
        $_SESSION['asset_feedback'] = "Gagal membuat file arsip ZIP. Kode Error ZipArchive: " . $zip_open_status;
        $_SESSION['asset_feedback_type'] = "danger";
        error_log("PROSES_BACKUP_ASET_ERROR: Gagal membuka/membuat file ZIP: " . $zip_file_path_server . " - Kode Status: " . $zip_open_status);
        header("Location: manajemen_data.php");
        exit();
    }

    // Tambahkan file dan folder secara rekursif ke ZIP
    $source_folder_real_path = realpath($source_folder_path_server); // Dapatkan path absolut yang sudah di-resolve

    $files_iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_folder_real_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $file_added_count = 0;
    foreach ($files_iterator as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            // Buat path relatif di dalam ZIP, relatif terhadap folder $folder_to_backup_app_relative
            // Contoh: jika $source_folder_real_path adalah /path/to/reaktorsystem/assets/uploads
            // dan $filePath adalah /path/to/reaktorsystem/assets/uploads/foto_profil/saya.jpg
            // maka $relativePath akan menjadi foto_profil/saya.jpg
            $relativePath = substr($filePath, strlen($source_folder_real_path) + 1);

            if ($zip->addFile($filePath, $relativePath)) {
                $file_added_count++;
            } else {
                error_log("PROSES_BACKUP_ASET_WARNING: Gagal menambahkan file ke ZIP: " . $filePath . " sebagai " . $relativePath);
            }
        }
    }

    $zip_status_string_after_add = $zip->getStatusString(); // Dapatkan status setelah addFile
    $zip_close_status = $zip->close();

    if ($zip_close_status && $file_added_count > 0) {
        // Audit Log
        try {
            if ($pdo && $user_nik) { // $pdo dan $user_nik dari init_core.php
                $aksi_log = "BACKUP ASET (FOLDER UPLOADS) BERHASIL";
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                $log_stmt->execute(['un' => $user_nik, 'a' => $aksi_log, 'detail' => "File: " . $zip_filename . ", Jumlah file diarsip: " . $file_added_count]);
            }
        } catch (PDOException $e_log) { error_log("PROSES_BACKUP_ASET_AUDIT_ERROR: " . $e_log->getMessage()); }

        // Kirim file ZIP untuk diunduh
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip_filename) . '"'); // Gunakan basename($zip_filename) bukan $zip_file_path_server
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zip_file_path_server));
        
        while (ob_get_level() > 0) { ob_end_clean(); }
        readfile($zip_file_path_server);
        
        @unlink($zip_file_path_server); // Hapus file ZIP sementara setelah diunduh
        exit;

    } elseif ($file_added_count == 0) {
        $_SESSION['asset_backup_feedback'] = "Folder uploads ('" . htmlspecialchars($folder_to_backup_app_relative) . "') kosong atau tidak ada file yang dapat diakses untuk dibackup.";
        $_SESSION['asset_backup_feedback_type'] = "warning";
        error_log("PROSES_BACKUP_ASET_INFO: Tidak ada file yang ditambahkan ke ZIP dari " . $source_folder_path_server);
        if (file_exists($zip_file_path_server)) @unlink($zip_file_path_server);
    } else {
        $_SESSION['asset_backup_feedback'] = "Gagal menyelesaikan pembuatan arsip ZIP. Status ZipArchive: " . $zip_status_string_after_add;
        $_SESSION['asset_backup_feedback_type'] = "danger";
        error_log("PROSES_BACKUP_ASET_ERROR: Gagal menutup file ZIP. Status setelah add: " . $zip_status_string_after_add . ". Status close: " . $zip_close_status);
        if (file_exists($zip_file_path_server)) @unlink($zip_file_path_server);
    }
    header("Location: manajemen_data.php");
    exit();

} else {
    $_SESSION['asset_backup_feedback'] = "Aksi tidak valid untuk backup aset.";
    $_SESSION['asset_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
// Hapus tag ?> jika ini akhir file
?>