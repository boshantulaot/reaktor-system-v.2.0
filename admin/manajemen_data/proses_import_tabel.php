<?php
// File: public_html/reaktorsystem/admin/manajemen_data/proses_import_tabel.php

if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else { /* ... (Error init_core.php) ... */ }

// --- Proteksi Akses Skrip ---
if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') { /* ... (Proteksi akses) ... */ }
if (!isset($pdo) || !$pdo instanceof PDO || !defined('DB_NAME')) { /* ... (Cek PDO & DB_NAME) ... */ }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_table_data'], $_POST['table_name_import'], $_POST['import_option'])) {

    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $table_name_import = trim($_POST['table_name_import']);
    $import_option = trim($_POST['import_option']);
    $errors_import = [];
    $success_count = 0;
    $failure_count = 0;
    $line_number = 0;

    if (empty($table_name_import)) {
        $_SESSION['export_import_feedback'] = "Nama tabel tujuan tidak boleh kosong untuk import data.";
        $_SESSION['export_import_feedback_type'] = "warning";
        header("Location: manajemen_data.php");
        exit();
    }

    // Validasi nama tabel
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name_import)) { /* ... (Validasi karakter) ... */ }
    $table_exists_import = false;
    try {
        $stmt_check_table_import = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name");
        $db_name_const_import = DB_NAME;
        $stmt_check_table_import->bindParam(':db_name', $db_name_const_import, PDO::PARAM_STR);
        $stmt_check_table_import->bindParam(':table_name', $table_name_import, PDO::PARAM_STR);
        $stmt_check_table_import->execute();
        if ($stmt_check_table_import->fetchColumn() > 0) $table_exists_import = true;
    } catch (PDOException $e_check_import) { /* ... (Error handling) ... */ }
    if (!$table_exists_import) { /* ... (Error handling jika tabel tidak ada) ... */ }

    // Proses file CSV yang diupload
    if (isset($_FILES['csv_file_import']) && $_FILES['csv_file_import']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path_import = $_FILES['csv_file_import']['tmp_name'];
        $file_name_import = $_FILES['csv_file_import']['name'];
        $file_ext_import = strtolower(pathinfo($file_name_import, PATHINFO_EXTENSION));

        if ($file_ext_import !== 'csv') {
            $_SESSION['export_import_feedback'] = "Format file tidak valid. Hanya file .csv yang diizinkan untuk import.";
            $_SESSION['export_import_feedback_type'] = "danger";
            header("Location: manajemen_data.php");
            exit();
        }

        // Ambil kolom aktual dari tabel database
        $actual_table_columns = [];
        try {
            $stmt_cols = $pdo->query("SHOW COLUMNS FROM `" . DB_NAME . "`.`" . $table_name_import . "`");
            if ($stmt_cols) {
                $actual_table_columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
            } else {
                throw new Exception("Tidak dapat mengambil struktur kolom dari tabel target.");
            }
        } catch (Exception $e) {
            $_SESSION['export_import_feedback'] = "Error mengambil struktur tabel: " . htmlspecialchars($e->getMessage());
            $_SESSION['export_import_feedback_type'] = "danger";
            header("Location: manajemen_data.php");
            exit();
        }


        $file_handle_import = fopen($file_tmp_path_import, 'r');
        if (!$file_handle_import) {
            $_SESSION['export_import_feedback'] = "Gagal membuka file CSV yang diupload.";
            $_SESSION['export_import_feedback_type'] = "danger";
            header("Location: manajemen_data.php");
            exit();
        }

        try {
            $pdo->beginTransaction();

            if ($import_option === 'truncate_insert') {
                // Nonaktifkan foreign key checks sementara jika TRUNCATE
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
                $pdo->exec("TRUNCATE TABLE `" . DB_NAME . "`.`" . $table_name_import . "`");
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;'); // Aktifkan lagi setelah truncate
                error_log("PROSES_IMPORT_INFO: Tabel {$table_name_import} telah di-TRUNCATE sebelum import.");
            }

            $csv_headers = fgetcsv($file_handle_import); // Baca baris pertama sebagai header
            $line_number++;

            if (!$csv_headers) {
                throw new Exception("File CSV kosong atau tidak dapat membaca header.");
            }

            // Validasi sederhana: jumlah kolom CSV harus sama dengan jumlah kolom tabel target
            // Untuk import yang lebih canggih, Anda perlu pemetaan kolom.
            if (count($csv_headers) !== count($actual_table_columns)) {
                // throw new Exception("Jumlah kolom di file CSV (" . count($csv_headers) . ") tidak cocok dengan jumlah kolom di tabel '" . $table_name_import . "' (" . count($actual_table_columns) . ").");
            }
            // Untuk sekarang, kita asumsikan urutan kolom di CSV sama dengan di tabel.
            // Jika tidak, perlu mekanisme mapping. Kita akan menggunakan $actual_table_columns untuk membangun query.

            $placeholders = implode(',', array_fill(0, count($actual_table_columns), '?'));
            $column_names_for_sql = implode('`, `', $actual_table_columns);

            $sql_prefix = "";
            if ($import_option === 'insert') {
                $sql_prefix = "INSERT INTO `" . $table_name_import . "` (`" . $column_names_for_sql . "`) VALUES ";
            } elseif ($import_option === 'replace') {
                $sql_prefix = "REPLACE INTO `" . $table_name_import . "` (`" . $column_names_for_sql . "`) VALUES ";
            } elseif ($import_option === 'update') {
                // Opsi update lebih kompleks, perlu primary key atau unique key untuk WHERE clause.
                // Untuk versi awal ini, kita fokus pada insert dan replace.
                throw new Exception("Opsi import 'update' belum diimplementasikan sepenuhnya di versi ini.");
            } else { // Default ke insert (termasuk untuk truncate_insert setelah truncate)
                 $sql_prefix = "INSERT INTO `" . $table_name_import . "` (`" . $column_names_for_sql . "`) VALUES ";
            }
            
            $stmt_import = $pdo->prepare($sql_prefix . "(" . $placeholders . ")");

            while (($data_row = fgetcsv($file_handle_import)) !== FALSE) {
                $line_number++;
                if (count($data_row) !== count($actual_table_columns)) {
                    $errors_import[] = "Baris {$line_number}: Jumlah data tidak cocok dengan jumlah kolom tabel.";
                    $failure_count++;
                    continue; 
                }
                // Ganti string kosong dengan NULL jika kolom memperbolehkan NULL
                // Ini adalah asumsi sederhana, tipe data sebenarnya perlu dicek
                $processed_row = array_map(function($value) {
                    return ($value === '' || strtolower($value) === 'null') ? null : $value;
                }, $data_row);

                try {
                    if ($stmt_import->execute($processed_row)) {
                        $success_count++;
                    } else {
                        $errors_import[] = "Baris {$line_number}: Gagal dieksekusi. " . implode(", ", $stmt_import->errorInfo());
                        $failure_count++;
                    }
                } catch (PDOException $e_row) {
                    $errors_import[] = "Baris {$line_number}: Error DB - " . $e_row->getMessage();
                    $failure_count++;
                }
            }
            fclose($file_handle_import);

            if ($failure_count > 0 && $import_option !== 'truncate_insert') { // Jika truncate_insert, error mungkin sudah terjadi sebelumnya
                // Jika ada kegagalan dan bukan truncate_insert (yang sudah dikosongkan), rollback.
                // Untuk truncate_insert, data yang sudah masuk sebelum error tetap ada.
                if ($pdo->inTransaction()) $pdo->rollBack();
                $feedback_msg_detail = "Proses import dihentikan karena ada {$failure_count} baris gagal. Tidak ada data yang diimpor (rollback). Detail error pertama: " . ($errors_import[0] ?? '');
                 $_SESSION['export_import_feedback'] = $feedback_msg_detail;
                $_SESSION['export_import_feedback_type'] = "danger";
            } else {
                if ($pdo->inTransaction()) $pdo->commit();
                $feedback_msg_detail = "Proses import selesai. Berhasil: {$success_count} baris. Gagal: {$failure_count} baris.";
                if (!empty($errors_import)) {
                    $feedback_msg_detail .= "<br>Detail error pertama: " . htmlspecialchars($errors_import[0]);
                }
                $_SESSION['export_import_feedback'] = $feedback_msg_detail;
                $_SESSION['export_import_feedback_type'] = ($failure_count > 0) ? "warning" : "success";

                // Audit Log
                if ($user_nik) { /* ... (Logika audit) ... */ }
            }

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['export_import_feedback'] = "Gagal import data: " . htmlspecialchars($e->getMessage());
            $_SESSION['export_import_feedback_type'] = "danger";
            error_log("PROSES_IMPORT_TABEL_EXCEPTION: " . $e->getMessage());
        } finally {
            if (isset($file_handle_import) && is_resource($file_handle_import)) fclose($file_handle_import);
            if (isset($_FILES['csv_file_import']['tmp_name']) && file_exists($_FILES['csv_file_import']['tmp_name'])) {
                @unlink($_FILES['csv_file_import']['tmp_name']); // Hapus file temporary upload
            }
        }
    } else { /* ... (Error upload file) ... */ }

    header("Location: manajemen_data.php");
    exit();
} else { /* ... (Aksi tidak valid) ... */ }
?>