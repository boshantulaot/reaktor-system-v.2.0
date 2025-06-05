<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_restore_db.php

// Sertakan file inisialisasi inti
if (file_exists(__DIR__ . '/../../core/init_core.php')) { // Path sudah diperbaiki
    require_once(__DIR__ . '/../../core/init_core.php'); // Path sudah diperbaiki
} else {
    session_start(); 
    $_SESSION['backup_feedback'] = "Kesalahan konfigurasi sistem inti (PROSES_RESTORE_INIT_CORE_MISSING). Path yang dicoba: " . __DIR__ . '/../../core/init_core.php'; // Tambahkan path yang dicoba untuk debug
    $_SESSION['backup_feedback_type'] = "danger";
    error_log("PROSES_RESTORE_DB_FATAL: init_core.php tidak ditemukan pada path " . __DIR__ . '/../../core/init_core.php');
    header("Location: manajemen_data.php"); // Redirect kembali ke halaman manajemen data
    exit();
}

// --- PENTING: Proteksi Akses Skrip ---
if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['backup_feedback'] = "Anda tidak memiliki izin untuk melakukan operasi restore database.";
    $_SESSION['backup_feedback_type'] = "danger";
    header("Location: manajemen_data.php");
    exit();
}

// Pastikan koneksi PDO ($pdo) tersedia dari init_core.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['backup_feedback'] = "Koneksi database tidak tersedia untuk proses restore.";
    $_SESSION['backup_feedback_type'] = "danger";
    error_log("PROSES_RESTORE_DB_ERROR: Koneksi PDO tidak valid.");
    header("Location: manajemen_data.php");
    exit();
}

// Proses hanya jika metode POST dan tombol restore ditekan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_db_now'])) {

    // Tingkatkan batas waktu eksekusi dan memori (sangat penting untuk restore)
    @set_time_limit(0); // Tanpa batas waktu jika diizinkan
    @ini_set('memory_limit', '1024M'); // Tingkatkan memori, sesuaikan dengan ukuran file SQL

    if (isset($_FILES['sql_file_restore']) && $_FILES['sql_file_restore']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['sql_file_restore']['tmp_name'];
        $file_name = $_FILES['sql_file_restore']['name'];
        $file_size = $_FILES['sql_file_restore']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['sql', 'gz'];

        if (!in_array($file_ext, $allowed_ext)) {
            $_SESSION['backup_feedback'] = "Format file tidak valid. Hanya file .sql atau .sql.gz yang diizinkan.";
            $_SESSION['backup_feedback_type'] = "danger";
            header("Location: manajemen_data.php");
            exit();
        }

        // Buat path untuk menyimpan file SQL sementara jika dikompresi
        // Pastikan folder 'temp_uploads' di dalam 'assets/uploads/' ada dan bisa ditulis
        $temp_upload_dir_app_relative = 'assets/uploads/temp_uploads/';
        $temp_upload_dir_server = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($temp_upload_dir_app_relative, '/');
        $temp_upload_dir_server = preg_replace('/\/+/', '/', $temp_upload_dir_server);

        if (!file_exists($temp_upload_dir_server)) {
            if (!@mkdir($temp_upload_dir_server, 0755, true)) {
                $_SESSION['backup_feedback'] = "Gagal membuat direktori sementara untuk restore. Periksa izin folder.";
                $_SESSION['backup_feedback_type'] = "danger";
                error_log("PROSES_RESTORE_DB_ERROR: Gagal membuat direktori: " . $temp_upload_dir_server);
                header("Location: manajemen_data.php");
                exit();
            }
        }
        if (!is_writable($temp_upload_dir_server)) {
             $_SESSION['backup_feedback'] = "Direktori sementara untuk restore tidak dapat ditulis. Periksa izin folder.";
            $_SESSION['backup_feedback_type'] = "danger";
            error_log("PROSES_RESTORE_DB_ERROR: Direktori tidak dapat ditulis: " . $temp_upload_dir_server);
            header("Location: manajemen_data.php");
            exit();
        }

        $sql_file_to_process = $file_tmp_path;
        $is_gzipped = ($file_ext === 'gz');
        $decompressed_file_path = null;

        if ($is_gzipped) {
            if (!function_exists('gzopen')) {
                $_SESSION['backup_feedback'] = "Ekstensi Zlib (gzopen) tidak tersedia di server untuk dekompresi file .gz.";
                $_SESSION['backup_feedback_type'] = "danger";
                error_log("PROSES_RESTORE_DB_ERROR: Fungsi gzopen tidak tersedia.");
                header("Location: manajemen_data.php");
                exit();
            }
            // Buat nama file sementara untuk SQL yang didekompresi
            $decompressed_file_path = $temp_upload_dir_server . 'restore_temp_' . time() . '.sql';
            $gz_file = gzopen($file_tmp_path, 'rb');
            $output_file = fopen($decompressed_file_path, 'wb');
            if (!$gz_file || !$output_file) {
                $_SESSION['backup_feedback'] = "Gagal membuka file untuk dekompresi.";
                $_SESSION['backup_feedback_type'] = "danger";
                if ($gz_file) gzclose($gz_file);
                if ($output_file) fclose($output_file);
                if (file_exists($decompressed_file_path)) @unlink($decompressed_file_path);
                header("Location: manajemen_data.php");
                exit();
            }
            while (!gzeof($gz_file)) {
                fwrite($output_file, gzread($gz_file, 4096));
            }
            gzclose($gz_file);
            fclose($output_file);
            $sql_file_to_process = $decompressed_file_path;
        }

        // Mulai proses restore database
        $query_count = 0;
        $error_count = 0;
        $sql_query = '';

        try {
            $pdo->beginTransaction(); // MULAI TRANSAKSI

            $file_handle = fopen($sql_file_to_process, 'r');
            if (!$file_handle) {
                throw new Exception("Gagal membuka file SQL: " . basename($sql_file_to_process));
            }

            while (($line = fgets($file_handle)) !== false) {
                // Abaikan komentar dan baris kosong
                if (trim($line) == '' || strpos(trim($line), '--') === 0 || strpos(trim($line), '/*') === 0 || strpos(trim($line), '#') === 0) {
                    continue;
                }
                $sql_query .= $line;
                // Jika baris diakhiri dengan titik koma, itu adalah akhir dari satu statement SQL
                if (substr(trim($line), -1, 1) == ';') {
                    try {
                        $pdo->exec($sql_query);
                        $query_count++;
                    } catch (PDOException $e_exec) {
                        // Catat error spesifik query, tapi jangan hentikan transaksi dulu
                        error_log("PROSES_RESTORE_DB_QUERY_ERROR: Gagal eksekusi query. Error: " . $e_exec->getMessage() . " | Query: " . substr($sql_query, 0, 200) . "...");
                        $error_count++;
                        // Anda bisa memilih untuk menghentikan proses di sini jika satu query gagal,
                        // atau melanjutkan dan melaporkan jumlah error di akhir.
                        // Untuk keamanan data, lebih baik hentikan jika ada error.
                        // throw new Exception("Error saat eksekusi query: " . $e_exec->getMessage() . " | Query: " . substr($sql_query, 0, 100));
                    }
                    $sql_query = ''; // Reset query untuk statement berikutnya
                }
            }
            fclose($file_handle);

            if ($error_count > 0) {
                // Jika ada error saat eksekusi query, batalkan transaksi
                throw new Exception("Terjadi {$error_count} kesalahan saat menjalankan perintah SQL dari file backup. Proses restore dibatalkan.");
            }

            $pdo->commit(); // SELESAIKAN TRANSAKSI JIKA SEMUA BERHASIL

            $_SESSION['backup_feedback'] = "Database berhasil di-restore dari file '" . htmlspecialchars($file_name) . "'. Sebanyak {$query_count} perintah SQL dieksekusi.";
            $_SESSION['backup_feedback_type'] = "success";

            // Audit Log
            try {
                if ($pdo && $user_nik) {
                    $aksi_log = "RESTORE DATABASE BERHASIL";
                    $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, detail_aksi) VALUES (:un, :a, :detail)");
                    $log_stmt->execute(['un' => $user_nik, 'a' => $aksi_log, 'detail' => "Dari file: " . htmlspecialchars($file_name)]);
                }
            } catch (PDOException $e_log) { /* Abaikan error log audit */ }

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack(); // Batalkan transaksi jika terjadi error
            }
            $_SESSION['backup_feedback'] = "Gagal me-restore database. Error: " . htmlspecialchars($e->getMessage());
            $_SESSION['backup_feedback_type'] = "danger";
            error_log("PROSES_RESTORE_DB_EXCEPTION: " . $e->getMessage());
        } finally {
            // Hapus file SQL sementara jika tadi didekompresi
            if ($decompressed_file_path && file_exists($decompressed_file_path)) {
                @unlink($decompressed_file_path);
            }
            // Hapus file upload asli dari tmp jika ada (PHP biasanya menghapusnya otomatis, tapi untuk jaga-jaga)
            // if (isset($_FILES['sql_file_restore']['tmp_name']) && file_exists($_FILES['sql_file_restore']['tmp_name'])) {
            // @unlink($_FILES['sql_file_restore']['tmp_name']);
            // }
        }

        header("Location: manajemen_data.php");
        exit();

    } elseif (isset($_FILES['sql_file_restore']['error']) && $_FILES['sql_file_restore']['error'] != UPLOAD_ERR_NO_FILE) {
        // Tangani error upload file PHP
        $upload_errors = [ /* ... (daftar kode error upload PHP) ... */ ];
        $error_message = $upload_errors[$_FILES['sql_file_restore']['error']] ?? "Error tidak diketahui saat upload file (Kode: " . $_FILES['sql_file_restore']['error'] . ").";
        $_SESSION['backup_feedback'] = "Gagal mengupload file backup: " . htmlspecialchars($error_message);
        $_SESSION['backup_feedback_type'] = "danger";
        header("Location: manajemen_data.php");
        exit();
    } else {
        $_SESSION['backup_feedback'] = "Tidak ada file backup yang dipilih atau file kosong.";
        $_SESSION['backup_feedback_type'] = "warning";
        header("Location: manajemen_data.php");
        exit();
    }
} else {
    $_SESSION['backup_feedback'] = "Aksi tidak valid atau permintaan tidak lengkap untuk restore database.";
    $_SESSION['backup_feedback_type'] = "warning";
    header("Location: manajemen_data.php");
    exit();
}
// Hapus tag ?> jika ini akhir file
?>