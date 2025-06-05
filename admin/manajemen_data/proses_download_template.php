<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_download_template.php

// Sertakan file inisialisasi inti
if (file_exists(__DIR__ . '/../../core/init_core.php')) { // Path dari admin/manajemen_data/ ke core/
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    $_SESSION['export_import_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_TEMPLATE_INIT_CORE_MISSING).";
    $_SESSION['export_import_feedback_type'] = "danger";
    error_log("PROSES_DOWNLOAD_TEMPLATE_FATAL: init_core.php tidak ditemukan.");
    header("Location: manajemen_data.php");
    exit();
}

// --- PENTING: Proteksi Akses Skrip ---
if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['export_import_feedback'] = "Anda tidak memiliki izin untuk melakukan operasi ini.";
    $_SESSION['export_import_feedback_type'] = "danger";
    header("Location: manajemen_data.php");
    exit();
}

// Pastikan koneksi PDO ($pdo) dan nama DB (DB_NAME) tersedia
if (!isset($pdo) || !$pdo instanceof PDO || !defined('DB_NAME')) {
    $_SESSION['export_import_feedback'] = "Koneksi database atau konfigurasi nama database tidak tersedia.";
    $_SESSION['export_import_feedback_type'] = "danger";
    error_log("PROSES_DOWNLOAD_TEMPLATE_ERROR: Koneksi PDO atau DB_NAME tidak valid.");
    header("Location: manajemen_data.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['download_template'], $_POST['table_name_template'])) {
    $table_name = trim($_POST['table_name_template']);

    if (empty($table_name)) {
        $_SESSION['export_import_feedback'] = "Nama tabel tidak boleh kosong untuk membuat template.";
        $_SESSION['export_import_feedback_type'] = "warning";
        header("Location: manajemen_data.php");
        exit();
    }

    // 1. Validasi karakter dasar untuk nama tabel
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        $_SESSION['export_import_feedback'] = "Nama tabel tidak valid (karakter tidak diizinkan).";
        $_SESSION['export_import_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    }

    // 2. Cek apakah tabel benar-benar ada menggunakan information_schema
    $table_exists = false;
    try {
        $stmt_check_table = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name");
        
        // --- PERBAIKAN UNTUK bindParam ---
        $db_name_const = DB_NAME; // Simpan konstanta ke variabel
        $stmt_check_table->bindParam(':db_name', $db_name_const, PDO::PARAM_STR);
        // --- AKHIR PERBAIKAN ---
        
        $stmt_check_table->bindParam(':table_name', $table_name, PDO::PARAM_STR);
        $stmt_check_table->execute();
        if ($stmt_check_table->fetchColumn() > 0) {
            $table_exists = true;
        }
    } catch (PDOException $e_check) {
        error_log("PROSES_DOWNLOAD_TEMPLATE_ERROR: Gagal memvalidasi nama tabel '{$table_name}'. Pesan: " . $e_check->getMessage());
        $_SESSION['export_import_feedback'] = "Terjadi kesalahan saat memvalidasi tabel. Silakan coba lagi.";
        $_SESSION['export_import_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    }
    
    if (!$table_exists) {
        $_SESSION['export_import_feedback'] = "Nama tabel '" . htmlspecialchars($table_name) . "' tidak valid atau tidak ditemukan di database.";
        $_SESSION['export_import_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    }

    try {
        // Ambil nama kolom dari tabel yang dipilih
        $stmt_columns = $pdo->query("SHOW COLUMNS FROM `" . DB_NAME . "`.`" . $table_name . "`");

        if (!$stmt_columns) {
             throw new Exception("Gagal mendapatkan informasi kolom untuk tabel: " . htmlspecialchars($table_name));
        }
        
        $column_headers = $stmt_columns->fetchAll(PDO::FETCH_COLUMN);

        if (empty($column_headers)) {
            throw new Exception("Tidak ada kolom yang ditemukan untuk tabel: " . htmlspecialchars($table_name));
        }

        $csv_filename = "template_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $table_name) . "_" . date("Ymd") . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        $output_stream = fopen('php://output', 'w');
        fputcsv($output_stream, $column_headers);
        fclose($output_stream);

        try {
            if ($user_nik) {
                $aksi_log = "DOWNLOAD TEMPLATE TABEL";
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                $log_stmt->execute(['un' => $user_nik, 'a' => $aksi_log, 'detail' => "Tabel: " . htmlspecialchars($table_name) . ", File: " . $csv_filename]);
            }
        } catch (PDOException $e_log) { error_log("PROSES_DOWNLOAD_TEMPLATE_AUDIT_ERROR: " . $e_log->getMessage()); }

        exit;

    } catch (PDOException $e) {
        error_log("PROSES_DOWNLOAD_TEMPLATE_PDO_ERROR: Tabel '" . $table_name . "'. Pesan: " . $e->getMessage());
        $_SESSION['export_import_feedback'] = "Error database saat membuat template untuk tabel '" . htmlspecialchars($table_name) . "'.";
        $_SESSION['export_import_feedback_type'] = "danger";
    } catch (Exception $e) {
        error_log("PROSES_DOWNLOAD_TEMPLATE_GENERAL_ERROR: Tabel '" . $table_name . "'. Pesan: " . $e->getMessage());
        $_SESSION['export_import_feedback'] = "Terjadi kesalahan saat membuat template: " . htmlspecialchars($e->getMessage());
        $_SESSION['export_import_feedback_type'] = "danger";
    }

    header("Location: manajemen_data.php");
    exit();

} else {
    $_SESSION['export_import_feedback'] = "Aksi tidak valid atau parameter tidak lengkap untuk download template.";
    $_SESSION['export_import_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
// Hapus tag ?> jika file hanya berisi PHP
?>