<?php
// home/htdocs/admin/db_management/proses_pengaturan_database.php
ini_set('display_errors', 1); // Aktifkan hanya untuk debugging, NONAKTIFKAN di produksi
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600); // Setel batas waktu eksekusi skrip (misalnya 10 menit) untuk operasi besar

// Pastikan header.php di-include untuk koneksi DB dan variabel sesi
require_once(__DIR__ . '/../../core/header.php'); // PATH BARU

// Hanya Super Admin yang boleh mengakses
if ($user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak.";
    header("Location: ../../dashboard.php"); // PATH BARU
    exit();
}

// Pastikan koneksi PDO tersedia dari header.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_db_mgmt'] = "Koneksi Database Gagal!";
    header("Location: pengaturan_database.php"); // Tetap di folder yang sama
    exit();
}

$user_nik_pelaku = $_SESSION['user_nik'];

// Folder untuk backup database SQL
$db_backup_dir = 'assets/uploads/database_backups/'; // Path relatif dari htdocs root
$absolute_db_backup_dir = __DIR__ . '/../../' . $db_backup_dir; // Path absolut di server

// Folder untuk backup berkas (file uploads)
$file_backup_dir = 'assets/uploads/file_backups/'; // Path relatif dari htdocs root
$absolute_file_backup_dir = __DIR__ . '/../../' . $file_backup_dir; // Path absolut di server

// Pastikan direktori backup ada dan dapat ditulis
if (!is_dir($absolute_db_backup_dir)) { @mkdir($absolute_db_backup_dir, 0755, true); }
if (!is_writable($absolute_db_backup_dir)) {
    $_SESSION['pesan_error_db_mgmt'] = "Direktori backup database (" . htmlspecialchars($db_backup_dir) . ") tidak dapat ditulis. Periksa izin folder.";
    header("Location: pengaturan_database.php");
    exit();
}
if (!is_dir($absolute_file_backup_dir)) { @mkdir($absolute_file_backup_dir, 0755, true); }
if (!is_writable($absolute_file_backup_dir)) {
    $_SESSION['pesan_error_db_mgmt'] = "Direktori backup berkas (" . htmlspecialchars($file_backup_dir) . ") tidak dapat ditulis. Periksa izin folder.";
    header("Location: pengaturan_database.php");
    exit();
}


// Dapatkan detail koneksi database dari konstanta global
$host = DB_HOST;
$dbname = DB_NAME;
$db_username = DB_USER;
$db_password = DB_PASS; // Password perlu diakses di sini untuk mysqldump/mysql

// Path ke utilitas command-line MySQL
$mysqldump_path = MYSQLDUMP_PATH;
$mysql_path = MYSQL_PATH;


$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'backup_db':
        $prefix = trim($_POST['prefix'] ?? '');
        $filename = (!empty($prefix) ? $prefix . '_' : 'backup_') . date('Ymd_His') . '.sql';
        $filepath = $absolute_db_backup_dir . $filename;

        // Perintah backup. Menggunakan escapeshellarg() sangat penting untuk keamanan.
        // 2>&1 mengalihkan stderr ke stdout
        $command = sprintf(
            "%s -h %s -u %s %s %s > %s 2>&1",
            escapeshellarg($mysqldump_path),
            escapeshellarg($host),
            escapeshellarg($db_username),
            (!empty($db_password) ? '-p' . escapeshellarg($db_password) : ''), // Hanya tambahkan -p jika ada password
            escapeshellarg($dbname),
            escapeshellarg($filepath)
        );

        exec($command, $output, $return_var);

        if ($return_var === 0) { // return_var 0 menandakan perintah sukses
            $_SESSION['pesan_sukses_db_mgmt'] = "Backup database berhasil dibuat: " . htmlspecialchars($filename);
            // Audit Log
            $log_data_baru = ['filename' => $filename, 'filepath' => $filepath, 'size' => @filesize($filepath) ? round(@filesize($filepath) / (1024 * 1024), 2) . ' MB' : 'N/A'];
            $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
            $log_stmt->execute([
                'un' => $user_nik_pelaku,
                'a' => 'BACKUP DATABASE',
                't' => 'database',
                'id' => $filename,
                'db' => json_encode($log_data_baru),
                'ket' => 'Backup database penuh dari sistem.'
            ]);
        } else {
            $_SESSION['pesan_error_db_mgmt'] = "Gagal membuat backup database. Output: " . implode("\n", $output);
            error_log("DB Backup Failed. Command: " . $command . "\nOutput: " . implode("\n", $output));
        }
        break;

    case 'restore_db': // Dari upload file baru
    case 'restore_db_from_existing': // Dari file yang sudah ada
        $filename_to_restore = '';
        $filepath_to_restore = '';

        if ($action == 'restore_db') {
            if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] != UPLOAD_ERR_OK || !preg_match('/\.sql$/', $_FILES['sql_file']['name'])) {
                $_SESSION['pesan_error_db_mgmt'] = "File SQL tidak valid atau tidak diupload.";
                header("Location: pengaturan_database.php");
                exit();
            }
            // Batasan ukuran file SQL untuk restore
            $max_sql_size_mb = 50; // Misalnya 50 MB
            if ($_FILES['sql_file']['size'] > $max_sql_size_mb * 1024 * 1024) {
                $_SESSION['pesan_error_db_mgmt'] = "Ukuran file SQL melebihi batas " . $max_sql_size_mb . "MB.";
                header("Location: pengaturan_database.php");
                exit();
            }

            $filename_to_restore = 'uploaded_restore_' . date('Ymd_His') . '.sql';
            $filepath_to_restore = $absolute_db_backup_dir . $filename_to_restore;
            if (!move_uploaded_file($_FILES['sql_file']['tmp_name'], $filepath_to_restore)) {
                $_SESSION['pesan_error_db_mgmt'] = "Gagal memindahkan file yang diupload.";
                header("Location: pengaturan_database.php");
                exit();
            }
        } elseif ($action == 'restore_db_from_existing') {
            $filename_to_restore = basename($_POST['filename'] ?? ''); // Pastikan hanya nama file
            $filepath_to_restore = $absolute_db_backup_dir . $filename_to_restore;
            if (!file_exists($filepath_to_restore)) {
                $_SESSION['pesan_error_db_mgmt'] = "File backup tidak ditemukan: " . htmlspecialchars($filename_to_restore);
                header("Location: pengaturan_database.php");
                exit();
            }
        }

        if (empty($filepath_to_restore) || !file_exists($filepath_to_restore)) {
            $_SESSION['pesan_error_db_mgmt'] = "File untuk restore tidak ditemukan.";
            break; // Keluar dari switch
        }

        // Perintah restore. Penting: Gunakan username dan password yang benar.
        // Input dari file backup menggunakan operator <
        $command = sprintf(
            "%s -h %s -u %s %s %s < %s 2>&1",
            escapeshellarg($mysql_path),
            escapeshellarg($host),
            escapeshellarg($db_username),
            (!empty($db_password) ? '-p' . escapeshellarg($db_password) : ''),
            escapeshellarg($dbname),
            escapeshellarg($filepath_to_restore)
        );

        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $_SESSION['pesan_sukses_db_mgmt'] = "Restore database dari file " . htmlspecialchars($filename_to_restore) . " berhasil.";
            // Audit Log
            $log_data_baru = ['filename' => $filename_to_restore, 'filepath' => $filepath_to_restore];
            $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
            $log_stmt->execute([
                'un' => $user_nik_pelaku,
                'a' => 'RESTORE DATABASE',
                't' => 'database',
                'id' => $filename_to_restore,
                'db' => json_encode($log_data_baru),
                'ket' => 'Database dipulihkan dari file SQL backup.'
            ]);
        } else {
            $_SESSION['pesan_error_db_mgmt'] = "Gagal melakukan restore database. Output: " . implode("\n", $output);
            error_log("DB Restore Failed. Command: " . $command . "\nOutput: " . implode("\n", $output));
        }
        // Hapus file SQL sementara yang diunggah jika itu dari upload baru
        if ($action == 'restore_db' && file_exists($filepath_to_restore)) {
            @unlink($filepath_to_restore);
        }
        break;

    case 'delete_backup': // Untuk backup database SQL
        $filename_to_delete = basename($_POST['filename'] ?? ''); // Pastikan hanya nama file
        $filepath_to_delete = $absolute_db_backup_dir . $filename_to_delete;

        if (file_exists($filepath_to_delete) && preg_match('/\.sql$/', $filename_to_delete)) { // Cek ekstensi lagi untuk keamanan
            if (unlink($filepath_to_delete)) {
                $_SESSION['pesan_sukses_db_mgmt'] = "File backup database " . htmlspecialchars($filename_to_delete) . " berhasil dihapus.";
                // Audit Log
                $log_data_lama = ['filename' => $filename_to_delete, 'filepath' => $filepath_to_delete];
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:un, :a, :t, :id, :dl, :ket)");
                $log_stmt->execute([
                    'un' => $user_nik_pelaku,
                    'a' => 'HAPUS FILE BACKUP DATABASE',
                    't' => 'database_file',
                    'id' => $filename_to_delete,
                    'dl' => json_encode($log_data_lama),
                    'ket' => 'File backup database dihapus dari server.'
                ]);
            } else {
                $_SESSION['pesan_error_db_mgmt'] = "Gagal menghapus file backup database.";
            }
        } else {
            $_SESSION['pesan_error_db_mgmt'] = "File backup database tidak ditemukan atau tidak valid: " . htmlspecialchars($filename_to_delete);
        }
        break;

    case 'backup_all_files': // Untuk backup folder uploads/
        $zip_filename = 'all_uploads_backup_' . date('Ymd_His') . '.zip';
        $zip_filepath = $absolute_file_backup_dir . $zip_filename; // Simpan di folder backup berkas

        // Pastikan path folder uploads/ yang benar relatif dari htdocs root
        $uploads_source_path_relative = 'assets/uploads'; // Path relatif dari htdocs root
        $uploads_source_path_absolute = __DIR__ . '/../../' . $uploads_source_path_relative; // Path absolut di server

        if (!is_dir($uploads_source_path_absolute)) {
            $_SESSION['pesan_error_db_mgmt'] = "Folder uploads/ tidak ditemukan: " . htmlspecialchars($uploads_source_path_relative);
            header("Location: pengaturan_database.php");
            exit();
        }

        // Perintah zip. PENTING: Jika folder uploads terlalu besar, ini bisa time out atau membebani server.
        // Perintah ini akan masuk ke direktori induk dari uploads (yaitu htdocs), lalu zip folder uploads itu sendiri
        $command = sprintf(
            "zip -r %s %s 2>&1",
            escapeshellarg($zip_filepath),
            escapeshellarg(basename($uploads_source_path_absolute)) // Hanya nama folder 'uploads'
        );
        // Jalankan perintah dari direktori induk dari uploads_source_path_absolute
        $old_cwd = getcwd(); // Simpan CWD saat ini
        chdir(dirname($uploads_source_path_absolute)); // Pindah CWD ke htdocs/
        exec($command, $output, $return_var);
        chdir($old_cwd); // Kembali ke CWD sebelumnya

        if ($return_var === 0) {
            if (file_exists($zip_filepath)) {
                // Header untuk download file ZIP
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                header('Content-Length: ' . filesize($zip_filepath));
                header('Cache-Control: max-age=0');
                readfile($zip_filepath);

                // Audit Log
                $log_data_baru = ['filename' => $zip_filename, 'filepath' => $zip_filepath, 'size' => @filesize($zip_filepath) ? round(@filesize($zip_filepath) / (1024 * 1024), 2) . ' MB' : 'N/A'];
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
                $log_stmt->execute([
                    'un' => $user_nik_pelaku,
                    'a' => 'BACKUP SEMUA BERKAS',
                    't' => 'file_system',
                    'id' => $zip_filename,
                    'db' => json_encode($log_data_baru),
                    'ket' => 'Semua berkas di folder assets/uploads/ di-backup dan diunduh.'
                ]);

                // Setelah download, hapus file zip dari server untuk membersihkan temp
                @unlink($zip_filepath);
                exit(); // Penting: Hentikan eksekusi setelah mengirim file
            } else {
                $_SESSION['pesan_error_db_mgmt'] = "Backup berkas berhasil dibuat tetapi file tidak ditemukan untuk diunduh.";
            }
        } else {
            $_SESSION['pesan_error_db_mgmt'] = "Gagal membuat backup berkas. Output: " . implode("\n", $output);
            error_log("Files Backup Failed. Command: " . $command . "\nOutput: " . implode("\n", $output));
        }
        break;

    case 'restore_all_files': // Untuk restore folder uploads/
        if (!isset($_FILES['zip_file_to_restore']) || $_FILES['zip_file_to_restore']['error'] != UPLOAD_ERR_OK || !preg_match('/\.zip$/', $_FILES['zip_file_to_restore']['name'])) {
            $_SESSION['pesan_error_db_mgmt'] = "File ZIP tidak valid atau tidak diupload.";
            header("Location: pengaturan_database.php");
            exit();
        }

        // Batasan ukuran file ZIP untuk restore berkas (misal 500 MB)
        $max_zip_size_mb = 500;
        if ($_FILES['zip_file_to_restore']['size'] > $max_zip_size_mb * 1024 * 1024) {
            $_SESSION['pesan_error_db_mgmt'] = "Ukuran file ZIP melebihi batas " . $max_zip_size_mb . "MB.";
            header("Location: pengaturan_database.php");
            exit();
        }

        $temp_zip_file = $_FILES['zip_file_to_restore']['tmp_name'];
        $target_uploads_dir_relative = 'assets/uploads'; // Path relatif dari htdocs root
        $target_uploads_dir_absolute = __DIR__ . '/../../' . $target_uploads_dir_relative; // Path absolut di server
        $extract_base_dir_absolute = dirname($target_uploads_dir_absolute); // Direktori induk dari assets/uploads, yaitu htdocs/assets/

        // Langkah 1: Hapus semua isi folder uploads/ yang ada saat ini
        // PERHATIAN: Perintah ini sangat destruktif! Pastikan ini adalah yang Anda inginkan.
        // Gunakan path absolut untuk keamanan.
        $command_clear = sprintf("rm -rf %s/* 2>&1", escapeshellarg($target_uploads_dir_absolute));
        exec($command_clear, $output_clear, $return_var_clear);

        if ($return_var_clear !== 0 && !empty($output_clear) && strpos(implode("\n", $output_clear), 'No such file or directory') === false) { // Abaikan jika direktori memang kosong/tidak ada
            $_SESSION['pesan_error_db_mgmt'] = "Gagal membersihkan folder uploads/ sebelum restore: " . implode("\n", $output_clear);
            error_log("Failed to clear uploads/ before restore. Output: " . implode("\n", $output_clear));
            header("Location: pengaturan_database.php");
            exit();
        }

        // Langkah 2: Buat ulang folder uploads jika terhapus semua atau tidak ada
        if (!is_dir($target_uploads_dir_absolute)) {
            @mkdir($target_uploads_dir_absolute, 0755, true);
        }
        if (!is_writable($target_uploads_dir_absolute)) {
            $_SESSION['pesan_error_db_mgmt'] = "Direktori uploads/ tidak dapat ditulis setelah pembersihan. Restore dibatalkan.";
            header("Location: pengaturan_database.php");
            exit();
        }

        // Langkah 3: Ekstrak file ZIP
        // zipfile harus berisi struktur yang benar, misal "uploads/foto_atlet/..."
        // Ekstrak ke direktori induk dari uploads, agar folder uploads itu sendiri muncul
        $command_unzip = sprintf(
            "unzip -o %s -d %s 2>&1", // -o untuk overwrite tanpa prompt
            escapeshellarg($temp_zip_file),
            escapeshellarg($extract_base_dir_absolute) // Ekstrak ke htdocs/assets/ agar folder "uploads" muncul di dalamnya
        );
        exec($command_unzip, $output_unzip, $return_var_unzip);

        // Langkah 4: Set ulang izin (opsional, tapi disarankan)
        // Set izin 0755 untuk folder dan 0644 untuk file
        $command_chmod_dir = sprintf("find %s -type d -exec chmod 0755 {} + 2>&1", escapeshellarg($target_uploads_dir_absolute));
        $command_chmod_file = sprintf("find %s -type f -exec chmod 0644 {} + 2>&1", escapeshellarg($target_uploads_dir_absolute));
        exec($command_chmod_dir, $output_chmod_dir, $return_var_chmod_dir);
        exec($command_chmod_file, $output_chmod_file, $return_var_chmod_file);


        if ($return_var_unzip === 0) {
            $_SESSION['pesan_sukses_db_mgmt'] = "Pulihkan semua berkas dari " . htmlspecialchars($_FILES['zip_file_to_restore']['name']) . " berhasil.";
            // Audit Log
            $log_data_baru = ['filename' => $_FILES['zip_file_to_restore']['name'], 'extracted_to' => $target_uploads_dir_absolute];
            $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
            $log_stmt->execute([
                'un' => $user_nik_pelaku,
                'a' => 'RESTORE SEMUA BERKAS',
                't' => 'file_system',
                'id' => $_FILES['zip_file_to_restore']['name'],
                'db' => json_encode($log_data_baru),
                'ket' => 'Semua berkas di folder assets/uploads/ dipulihkan dari ZIP.'
            ]);
        } else {
            $_SESSION['pesan_error_db_mgmt'] = "Gagal memulihkan berkas dari ZIP. Output: " . implode("\n", $output_unzip);
            error_log("Files Restore Failed. Command: " . $command_unzip . "\nOutput: " . implode("\n", $output_unzip));
        }
        // Hapus file zip sementara yang diunggah
        @unlink($temp_zip_file);
        break;

    case 'delete_file_backup': // Untuk menghapus file backup berkas (ZIP)
        $filename_to_delete = basename($_POST['filename'] ?? ''); // Pastikan hanya nama file
        $filepath_to_delete = $absolute_file_backup_dir . $filename_to_delete;

        if (file_exists($filepath_to_delete) && preg_match('/\.zip$/', $filename_to_delete)) { // Cek ekstensi lagi untuk keamanan
            if (unlink($filepath_to_delete)) {
                $_SESSION['pesan_sukses_db_mgmt'] = "File backup berkas " . htmlspecialchars($filename_to_delete) . " berhasil dihapus.";
                // Audit Log
                $log_data_lama = ['filename' => $filename_to_delete, 'filepath' => $filepath_to_delete];
                $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:un, :a, :t, :id, :dl, :ket)");
                $log_stmt->execute([
                    'un' => $user_nik_pelaku,
                    'a' => 'HAPUS FILE BACKUP BERKAS',
                    't' => 'file_system_backup',
                    'id' => $filename_to_delete,
                    'dl' => json_encode($log_data_lama),
                    'ket' => 'File backup berkas dihapus dari server.'
                ]);
            } else {
                $_SESSION['pesan_error_db_mgmt'] = "Gagal menghapus file backup berkas.";
            }
        } else {
            $_SESSION['pesan_error_db_mgmt'] = "File backup berkas tidak ditemukan atau tidak valid: " . htmlspecialchars($filename_to_delete);
        }
        break;

    default:
        $_SESSION['pesan_error_db_mgmt'] = "Aksi tidak valid.";
        break;
}

header("Location: pengaturan_database.php"); // Tetap di folder yang sama
exit();
?>