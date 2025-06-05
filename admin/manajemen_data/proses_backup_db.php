<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_backup_db.php

// Sertakan file inisialisasi inti (untuk sesi, DB, path, var sesi dasar)
// Path dari admin/manajemen_data/ ke core/init_core.php adalah ../../
if (file_exists(__DIR__ . '/../../core/init_core.php')) { // PERBAIKAN PATH DI SINI
    require_once(__DIR__ . '/../../core/init_core.php'); // PERBAIKAN PATH DI SINI
} else {
    // Ini adalah error fatal jika init_core.php tidak ditemukan.
    // Mulai sesi secara manual untuk menyimpan pesan error jika memungkinkan.
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['backup_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_BACKUP_INIT_CORE_MISSING). Path yang dicoba: " . __DIR__ . '/../../core/init_core.php';
    $_SESSION['backup_feedback_type'] = "danger";
    error_log("PROSES_BACKUP_DB_FATAL: init_core.php tidak ditemukan pada path " . __DIR__ . '/../../core/init_core.php');
    
    // Redirect kembali ke halaman manajemen data (asumsi ada di folder yang sama)
    // Jika tidak, skrip akan berhenti di sini jika header sudah terkirim.
    header("Location: manajemen_data.php");
    exit();
}

// --- PENTING: Proteksi Akses Skrip ---
// Pastikan hanya Super Admin yang bisa menjalankan skrip ini
// $user_role_utama dan $user_nik sudah tersedia dari init_core.php
if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['backup_feedback'] = "Anda tidak memiliki izin untuk melakukan operasi backup database.";
    $_SESSION['backup_feedback_type'] = "danger";
    header("Location: manajemen_data.php");
    exit();
}

// Pastikan konstanta untuk mysqldump dan detail DB sudah terdefinisi
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME') || !defined('MYSQLDUMP_PATH')) {
    $_SESSION['backup_feedback'] = "Konfigurasi untuk backup database tidak lengkap. Harap periksa pengaturan sistem (database_credentials.php).";
    $_SESSION['backup_feedback_type'] = "danger";
    error_log("PROSES_BACKUP_DB_ERROR: Konstanta database atau MYSQLDUMP_PATH tidak terdefinisi.");
    header("Location: manajemen_data.php");
    exit();
}

// Proses hanya jika tombol backup ditekan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['backup_db_now'])) {

    @set_time_limit(600); 
    @ini_set('memory_limit', '512M'); 

    $backup_filename_base = "backup_db_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', DB_NAME);
    $backup_filename = $backup_filename_base . "_" . date("Y-m-d_H-i-s") . ".sql";
    
    $command = escapeshellcmd(MYSQLDUMP_PATH) .
               " --host=" . escapeshellarg(DB_HOST) . 
               " --user=" . escapeshellarg(DB_USER);
    if (DB_PASS !== '') {
        $command .= " --password=" . escapeshellarg(DB_PASS);
    }
    $command .= " " . escapeshellarg(DB_NAME);
    $command .= " --routines --triggers --events --add-drop-table --skip-comments --no-tablespaces --single-transaction";

    $use_compression = true; 
    if ($use_compression && function_exists('gzencode')) {
        $backup_filename .= ".gz";
    } else {
        $use_compression = false;
        if ($use_compression) { 
             error_log("PROSES_BACKUP_DB_WARNING: Ekstensi Zlib (gzencode) tidak tersedia. Backup tidak akan dikompres.");
        }
    }

    $output_array = [];
    $return_var = null;
    exec($command . " 2>&1", $output_array, $return_var);
    $sql_dump_content = implode("\n", $output_array);

    if ($return_var === 0) {
        $file_content_to_send = $sql_dump_content;
        if ($use_compression) {
            $compressed_content = gzencode($sql_dump_content, 9);
            if ($compressed_content === false) {
                error_log("PROSES_BACKUP_DB_ERROR: Gagal mengompresi data SQL. Mengirim tanpa kompresi.");
                $use_compression = false;
                $backup_filename = str_replace(".gz", "", $backup_filename);
            } else {
                $file_content_to_send = $compressed_content;
            }
        }
        
        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($use_compression ? 'application/gzip' : 'application/sql'));
        header('Content-Disposition: attachment; filename="' . basename($backup_filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($file_content_to_send));
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo $file_content_to_send;

        try {
            if ($pdo && $user_nik) {
                $aksi_log = "BACKUP DATABASE BERHASIL";
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                $log_stmt->execute(['un' => $user_nik, 'a' => $aksi_log, 'detail' => "File: " . basename($backup_filename)]);
            }
        } catch (PDOException $e_log) {
            error_log("PROSES_BACKUP_DB_AUDIT_ERROR: Gagal mencatat audit log. Pesan: " . $e_log->getMessage());
        }
        exit;

    } else {
        $_SESSION['backup_feedback'] = "Gagal membuat backup database. Pesan dari server: " . (!empty($sql_dump_content) ? nl2br(htmlspecialchars($sql_dump_content)) : "Tidak ada output error spesifik.") . " (Kode Error: " . $return_var . ")";
        $_SESSION['backup_feedback_type'] = "danger";
        $logged_command = preg_replace('/--password=([^\s]+)/', '--password=*****', $command);
        error_log("PROSES_BACKUP_DB_ERROR: mysqldump gagal. Output: " . $sql_dump_content . " | Return code: " . $return_var . " | Command: " . $logged_command);
        header("Location: manajemen_data.php");
        exit();
    }
} else {
    $_SESSION['backup_feedback'] = "Aksi tidak valid atau permintaan tidak lengkap untuk backup database.";
    $_SESSION['backup_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
// Pastikan tidak ada spasi atau output lain setelah tag PHP terakhir jika file hanya berisi PHP.
// Sebaiknya hilangkan tag ?> jika ini adalah akhir file dan hanya berisi PHP.
?>