<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_kosongkan_db.php

if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    $_SESSION['empty_db_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_EMPTY_INIT_CORE_MISSING). Path: " . __DIR__ . '/../../core/init_core.php';
    $_SESSION['empty_db_feedback_type'] = "danger";
    error_log("PROSES_KOSONGKAN_DB_FATAL: init_core.php tidak ditemukan pada path " . __DIR__ . '/../../core/init_core.php');
    header("Location: manajemen_data.php");
    exit();
}

if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['empty_db_feedback'] = "Anda tidak memiliki izin untuk melakukan operasi ini.";
    $_SESSION['empty_db_feedback_type'] = "danger";
    header("Location: manajemen_data.php");
    exit();
}

if (!isset($pdo) || !$pdo instanceof PDO || !defined('DB_NAME')) {
    $_SESSION['empty_db_feedback'] = "Koneksi database atau konfigurasi nama database tidak tersedia.";
    $_SESSION['empty_db_feedback_type'] = "danger";
    error_log("PROSES_KOSONGKAN_DB_ERROR: Koneksi PDO atau DB_NAME tidak valid.");
    header("Location: manajemen_data.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['empty_db_now'])) {
    $transaction_active = false; // Flag untuk melacak status transaksi

    try {
        $pdo->beginTransaction();
        $transaction_active = true; // Transaksi dimulai

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');

        $stmt_tables = $pdo->prepare("SHOW TABLES FROM `" . DB_NAME . "`");
        $stmt_tables->execute();
        $tables = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            $_SESSION['empty_db_feedback'] = "Tidak ada tabel yang ditemukan di database '" . DB_NAME . "' untuk dikosongkan.";
            $_SESSION['empty_db_feedback_type'] = "info";
            if ($transaction_active) $pdo->rollBack(); // Rollback jika transaksi aktif
            $transaction_active = false;
            header("Location: manajemen_data.php");
            exit();
        }

        $dropped_tables_count = 0;
        foreach ($tables as $table_name) {
            $pdo->exec("DROP TABLE IF EXISTS `" . DB_NAME . "`.`" . $table_name . "`");
            $dropped_tables_count++;
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
        $pdo->commit();
        $transaction_active = false; // Transaksi selesai

        $_SESSION['empty_db_feedback'] = "Semua ({$dropped_tables_count}) tabel di database '" . DB_NAME . "' berhasil dihapus (dikosongkan).";
        $_SESSION['empty_db_feedback_type'] = "success";

        try {
            if ($user_nik) { // $pdo sudah pasti ada di sini
                $aksi_log = "KOSONGKAN DATABASE (SEMUA TABEL DIHAPUS)";
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                $log_stmt->execute(['un' => $user_nik, 'a' => $aksi_log, 'detail' => "Database: " . DB_NAME]);
            }
        } catch (PDOException $e_log) { error_log("PROSES_KOSONGKAN_DB_AUDIT_ERROR: " . $e_log->getMessage()); }

    } catch (PDOException $e) {
        if ($transaction_active && $pdo && $pdo->inTransaction()) { // Cek sebelum rollback
            $pdo->rollBack();
        }
        $transaction_active = false;
        try { if ($pdo) $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;'); } catch (PDOException $efk) {}
        $_SESSION['empty_db_feedback'] = "Gagal mengosongkan database. Error PDO: " . htmlspecialchars($e->getMessage());
        $_SESSION['empty_db_feedback_type'] = "danger";
        error_log("PROSES_KOSONGKAN_DB_PDO_ERROR: " . $e->getMessage());
    } catch (Exception $e) {
        if ($transaction_active && $pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $transaction_active = false;
        try { if ($pdo) $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;'); } catch (PDOException $efk) {}
        $_SESSION['empty_db_feedback'] = "Terjadi kesalahan umum saat proses pengosongan database: " . htmlspecialchars($e->getMessage());
        $_SESSION['empty_db_feedback_type'] = "danger";
        error_log("PROSES_KOSONGKAN_DB_GENERAL_ERROR: " . $e->getMessage());
    } finally {
        // Pastikan foreign key checks selalu diaktifkan kembali jika koneksi masih ada
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                // Cek apakah koneksi masih aktif sebelum menjalankan query
                $pdo->query('SELECT 1'); // Query sederhana untuk cek koneksi
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
            } catch (PDOException $efk_final) {
                error_log("PROSES_KOSONGKAN_DB_FINALLY_ERROR: Gagal set FOREIGN_KEY_CHECKS=1. " . $efk_final->getMessage());
            }
        }
    }

    header("Location: manajemen_data.php");
    exit();

} else {
    $_SESSION['empty_db_feedback'] = "Aksi tidak valid untuk mengosongkan database.";
    $_SESSION['empty_db_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
?>