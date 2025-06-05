<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_export_tabel.php

// Sertakan file inisialisasi inti
if (file_exists(__DIR__ . '/../../core/init_core.php')) { // Path dari admin/manajemen_data/ ke core/
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    $_SESSION['export_import_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_EXPORT_INIT_CORE_MISSING).";
    $_SESSION['export_import_feedback_type'] = "danger";
    error_log("PROSES_EXPORT_TABEL_FATAL: init_core.php tidak ditemukan.");
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
    error_log("PROSES_EXPORT_TABEL_ERROR: Koneksi PDO atau DB_NAME tidak valid.");
    header("Location: manajemen_data.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_table_data'], $_POST['table_name_export'])) {
    $table_name_to_export = trim($_POST['table_name_export']);

    if (empty($table_name_to_export)) {
        $_SESSION['export_import_feedback'] = "Nama tabel tidak boleh kosong untuk export data.";
        $_SESSION['export_import_feedback_type'] = "warning";
        header("Location: manajemen_data.php");
        exit();
    }

    // Validasi nama tabel (untuk keamanan tambahan)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name_to_export)) {
        $_SESSION['export_import_feedback'] = "Nama tabel tidak valid (karakter tidak diizinkan).";
        $_SESSION['export_import_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    }

    $table_exists_export = false;
    try {
        $stmt_check_table_export = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name");
        $db_name_const_export = DB_NAME;
        $stmt_check_table_export->bindParam(':db_name', $db_name_const_export, PDO::PARAM_STR);
        $stmt_check_table_export->bindParam(':table_name', $table_name_to_export, PDO::PARAM_STR);
        $stmt_check_table_export->execute();
        if ($stmt_check_table_export->fetchColumn() > 0) {
            $table_exists_export = true;
        }
    } catch (PDOException $e_check_export) {
        error_log("PROSES_EXPORT_TABEL_ERROR: Gagal memvalidasi nama tabel '{$table_name_to_export}'. Pesan: " . $e_check_export->getMessage());
        $_SESSION['export_import_feedback'] = "Terjadi kesalahan saat memvalidasi tabel untuk export.";
        $_SESSION['export_import_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    }
    
    if (!$table_exists_export) {
        $_SESSION['export_import_feedback'] = "Nama tabel '" . htmlspecialchars($table_name_to_export) . "' tidak valid atau tidak ditemukan di database untuk diexport.";
        $_SESSION['export_import_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    }

    try {
        // Ambil nama kolom untuk header CSV
        $stmt_columns_export = $pdo->query("SHOW COLUMNS FROM `" . DB_NAME . "`.`" . $table_name_to_export . "`");
        if (!$stmt_columns_export) {
             throw new Exception("Gagal mendapatkan informasi kolom untuk tabel export: " . htmlspecialchars($table_name_to_export));
        }
        $column_headers_export = $stmt_columns_export->fetchAll(PDO::FETCH_COLUMN);
        if (empty($column_headers_export)) {
            throw new Exception("Tidak ada kolom yang ditemukan untuk tabel export: " . htmlspecialchars($table_name_to_export));
        }

        // Ambil semua data dari tabel
        // PERHATIAN: Untuk tabel yang sangat besar, ini bisa memakan banyak memori.
        // Pertimbangkan untuk mengambil data secara bertahap (chunking) jika perlu.
        $stmt_data_export = $pdo->query("SELECT * FROM `" . DB_NAME . "`.`" . $table_name_to_export . "`");
        if (!$stmt_data_export) {
            throw new Exception("Gagal mengambil data dari tabel: " . htmlspecialchars($table_name_to_export));
        }

        // Buat nama file CSV untuk export
        $csv_export_filename = "export_data_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $table_name_to_export) . "_" . date("Ymd_His") . ".csv";

        // Set header HTTP untuk download file CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_export_filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        $output_stream_export = fopen('php://output', 'w');

        // Tulis baris header (nama kolom) ke CSV
        fputcsv($output_stream_export, $column_headers_export);

        // Tulis baris data ke CSV
        while ($row = $stmt_data_export->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output_stream_export, $row);
        }

        fclose($output_stream_export);

        // Audit Log
        try {
            if ($user_nik) {
                $aksi_log_export = "EXPORT DATA TABEL";
                $log_stmt_export = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                $log_stmt_export->execute(['un' => $user_nik, 'a' => $aksi_log_export, 'detail' => "Tabel: " . htmlspecialchars($table_name_to_export) . ", File: " . $csv_export_filename]);
            }
        } catch (PDOException $e_log_export) { error_log("PROSES_EXPORT_TABEL_AUDIT_ERROR: " . $e_log_export->getMessage()); }

        exit;

    } catch (PDOException $e_pdo_export) {
        error_log("PROSES_EXPORT_TABEL_PDO_ERROR: Tabel '" . $table_name_to_export . "'. Pesan: " . $e_pdo_export->getMessage());
        $_SESSION['export_import_feedback'] = "Error database saat export data dari tabel '" . htmlspecialchars($table_name_to_export) . "'.";
        $_SESSION['export_import_feedback_type'] = "danger";
    } catch (Exception $e_general_export) {
        error_log("PROSES_EXPORT_TABEL_GENERAL_ERROR: Tabel '" . $table_name_to_export . "'. Pesan: " . $e_general_export->getMessage());
        $_SESSION['export_import_feedback'] = "Terjadi kesalahan saat export data: " . htmlspecialchars($e_general_export->getMessage());
        $_SESSION['export_import_feedback_type'] = "danger";
    }

    header("Location: manajemen_data.php");
    exit();

} else {
    $_SESSION['export_import_feedback'] = "Aksi tidak valid atau parameter tidak lengkap untuk export data tabel.";
    $_SESSION['export_import_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
// Hapus tag ?> jika file hanya berisi PHP
?>