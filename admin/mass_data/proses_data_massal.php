<?php
// home/htdocs/admin/mass_data/proses_data_massal.php
session_start();
ini_set('display_errors', 1); // Aktifkan hanya untuk debugging, NONAKTIFKAN di produksi
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600); // Setel batas waktu eksekusi skrip (misalnya 10 menit) untuk import/export besar

// === Pastikan Anda memiliki autoload Composer atau include PhpSpreadsheet secara manual ===
// Cara paling mudah jika pakai Composer:
require __DIR__ . '/../../vendor/autoload.php'; // PATH BARU ke folder vendor

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\IOFactory; // Untuk membaca file


require_once(__DIR__ . '/../../core/header.php'); // PATH BARU

// Hanya Super Admin yang boleh mengakses
if ($user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk melakukan tindakan ini.";
    header("Location: ../../dashboard.php"); // PATH BARU
    exit();
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_data_massal'] = "Koneksi Database Gagal!";
    header("Location: data_massal.php"); // Tetap di folder yang sama
    exit();
}

$user_nik_pelaku = $_SESSION['user_nik'];

// Definisikan ulang konfigurasi modul (agar tidak ambil dari GET/POST yang mungkin dimanipulasi)
// Semua modul ini akan tersedia untuk Super Admin
$export_import_modules = [
    'cabor' => [
        'name' => 'Data Cabang Olahraga',
        'table' => 'cabang_olahraga',
        'query_export' => "
            SELECT co.id_cabor, co.nama_cabor, co.kode_cabor, co.ketua_cabor_nik, co.sekretaris_cabor_nik, co.bendahara_cabor_nik, co.alamat_sekretariat, co.kontak_cabor, co.email_cabor, co.nomor_sk_provinsi, co.tanggal_sk_provinsi, co.periode_mulai, co.periode_selesai, co.status_kepengurusan
            FROM cabang_olahraga co
        ",
        'export_headers' => ['ID Cabor (Abaikan saat import)', 'Nama Cabor', 'Kode Cabor (Abaikan saat import)', 'NIK Ketua', 'NIK Sekretaris', 'NIK Bendahara', 'Alamat Sekretariat', 'Kontak Cabor', 'Email Cabor', 'Nomor SK Provinsi', 'Tanggal SK Provinsi (YYYY-MM-DD)', 'Periode Mulai (YYYY-MM-DD)', 'Periode Selesai (YYYY-MM-DD)', 'Status Kepengurusan (Aktif/Tidak Aktif/Masa Tenggang)'],
        'import_columns_map' => [ // Mapping header spreadsheet ke nama kolom DB
            'nama_cabor' => 'nama_cabor', 'nik_ketua' => 'ketua_cabor_nik', 'nik_sekretaris' => 'sekretaris_cabor_nik', 'nik_bendahara' => 'bendahara_cabor_nik',
            'alamat_sekretariat' => 'alamat_sekretariat', 'kontak_cabor' => 'kontak_cabor', 'email_cabor' => 'email_cabor', 'nomor_sk_provinsi' => 'nomor_sk_provinsi',
            'tanggal_sk_provinsi' => 'tanggal_sk_provinsi', 'periode_mulai' => 'periode_mulai', 'periode_selesai' => 'periode_selesai', 'status_kepengurusan' => 'status_kepengurusan'
        ],
        'required_import_fields' => ['nama_cabor'],
        'unique_import_fields' => ['nama_cabor'], // Kunci unik untuk UPDATE
    ],
    'klub' => [
        'name' => 'Data Klub',
        'table' => 'klub',
        'query_export' => "
            SELECT k.id_klub, k.nama_klub, k.id_cabor, co.nama_cabor, k.ketua_klub, k.alamat_sekretariat, k.kontak_klub, k.email_klub, k.nomor_sk_klub, k.tanggal_sk_klub
            FROM klub k
            JOIN cabang_olahraga co ON k.id_cabor = co.id_cabor
        ",
        'export_headers' => ['ID Klub (Abaikan saat import)', 'Nama Klub', 'ID Cabor', 'Nama Cabor', 'Ketua Klub', 'Alamat Sekretariat', 'Kontak Klub', 'Email Klub', 'Nomor SK Klub', 'Tanggal SK Klub (YYYY-MM-DD)'],
        'import_columns_map' => [
            'nama_klub' => 'nama_klub', 'id_cabor' => 'id_cabor', 'ketua_klub' => 'ketua_klub',
            'alamat_sekretariat' => 'alamat_sekretariat', 'kontak_klub' => 'kontak_klub',
            'email_klub' => 'email_klub', 'nomor_sk_klub' => 'nomor_sk_klub', 'tanggal_sk_klub' => 'tanggal_sk_klub'
        ],
        'required_import_fields' => ['nama_klub', 'id_cabor'],
        'unique_import_fields' => ['nama_klub', 'id_cabor'],
    ],
    'atlet' => [
        'name' => 'Data Atlet',
        'table' => 'atlet',
        'query_export' => "
            SELECT a.nik, p.nama_lengkap, a.id_cabor, co.nama_cabor, a.id_klub, k.nama_klub AS nama_klub,
                   p.tanggal_lahir, p.jenis_kelamin, p.nomor_telepon, p.email, p.alamat
            FROM atlet a
            JOIN pengguna p ON a.nik = p.nik
            JOIN cabang_olahraga co ON a.id_cabor = co.id_cabor
            LEFT JOIN klub k ON a.id_klub = k.id_klub
        ",
        'export_headers' => ['NIK', 'Nama Lengkap', 'ID Cabor', 'Nama Cabor', 'ID Klub', 'Nama Klub', 'Tanggal Lahir (YYYY-MM-DD)', 'Jenis Kelamin (Laki-laki/Perempuan)', 'No. Telepon', 'Email', 'Alamat'],
        'import_columns_map' => [
            'nik' => 'nik', 'id_cabor' => 'id_cabor', 'id_klub' => 'id_klub',
            'tanggal_lahir' => 'tanggal_lahir', 'jenis_kelamin' => 'jenis_kelamin',
            'nomor_telepon' => 'nomor_telepon', 'email' => 'email', 'alamat' => 'alamat'
        ],
        'required_import_fields' => ['nik', 'id_cabor'],
        'unique_import_fields' => ['nik', 'id_cabor'],
    ],
    'pelatih' => [
        'name' => 'Data Pelatih',
        'table' => 'pelatih',
        'query_export' => "
            SELECT pl.nik, p.nama_lengkap, pl.id_cabor, co.nama_cabor, pl.nomor_lisensi, pl.id_klub_afiliasi, k.nama_klub AS nama_klub_afiliasi, pl.kontak_pelatih, p.email
            FROM pelatih pl
            JOIN pengguna p ON pl.nik = p.nik
            JOIN cabang_olahraga co ON pl.id_cabor = co.id_cabor
            LEFT JOIN klub k ON pl.id_klub_afiliasi = k.id_klub
        ",
        'export_headers' => ['NIK', 'Nama Lengkap', 'ID Cabor', 'Nama Cabor', 'Nomor Lisensi', 'ID Klub Afiliasi', 'Nama Klub Afiliasi', 'Kontak Pelatih', 'Email Pengguna'],
        'import_columns_map' => [
            'nik' => 'nik', 'id_cabor' => 'id_cabor', 'nomor_lisensi' => 'nomor_lisensi',
            'id_klub_afiliasi' => 'id_klub_afiliasi', 'kontak_pelatih' => 'kontak_pelatih'
        ],
        'required_import_fields' => ['nik', 'id_cabor'],
        'unique_import_fields' => ['nik', 'id_cabor'],
    ],
    'wasit' => [
        'name' => 'Data Wasit',
        'table' => 'wasit',
        'query_export' => "
            SELECT w.nik, p.nama_lengkap, w.id_cabor, co.nama_cabor, w.nomor_lisensi, w.kontak_wasit, p.email
            FROM wasit w
            JOIN pengguna p ON w.nik = p.nik
            JOIN cabang_olahraga co ON w.id_cabor = co.id_cabor
        ",
        'export_headers' => ['NIK', 'Nama Lengkap', 'ID Cabor', 'Nama Cabor', 'Nomor Lisensi', 'Kontak Wasit', 'Email Pengguna'],
        'import_columns_map' => [
            'nik' => 'nik', 'id_cabor' => 'id_cabor', 'nomor_lisensi' => 'nomor_lisensi', 'kontak_wasit' => 'kontak_wasit'
        ],
        'required_import_fields' => ['nik', 'id_cabor'],
        'unique_import_fields' => ['nik', 'id_cabor'],
    ],
    'prestasi' => [
        'name' => 'Data Prestasi',
        'table' => 'prestasi',
        'query_export' => "
            SELECT pr.id_prestasi, pr.nik, p.nama_lengkap AS nama_atlet, pr.id_cabor, co.nama_cabor,
                   pr.nama_kejuaraan, pr.tingkat_kejuaraan, pr.tahun_perolehan, pr.medali_peringkat
            FROM prestasi pr
            JOIN pengguna p ON pr.nik = p.nik
            JOIN cabang_olahraga co ON pr.id_cabor = co.id_cabor
        ",
        'export_headers' => ['ID Prestasi (Abaikan saat import)', 'NIK Atlet', 'Nama Atlet', 'ID Cabor', 'Nama Cabor', 'Nama Kejuaraan', 'Tingkat Kejuaraan (Kabupaten/Provinsi/Nasional/Internasional)', 'Tahun Perolehan (YYYY)', 'Medali/Peringkat'],
        'import_columns_map' => [
            'nik' => 'nik', 'id_cabor' => 'id_cabor', 'nama_kejuaraan' => 'nama_kejuaraan',
            'tingkat_kejuaraan' => 'tingkat_kejuaraan', 'tahun_perolehan' => 'tahun_perolehan',
            'medali_peringkat' => 'medali_peringkat'
        ],
        'required_import_fields' => ['nik', 'id_cabor', 'nama_kejuaraan', 'tingkat_kejuaraan', 'tahun_perolehan', 'medali_peringkat'],
        'unique_import_fields' => ['nik', 'id_cabor', 'nama_kejuaraan', 'tingkat_kejuaraan', 'tahun_perolehan'],
    ],
];


$action = $_POST['action'] ?? $_GET['action'] ?? '';
$module_type = $_POST['module_type'] ?? $_GET['module_type'] ?? '';

// Validasi modul yang dipilih
if (!isset($export_import_modules[$module_type]) || $user_role_utama != 'super_admin') { // Hanya Super Admin
    $_SESSION['pesan_error_data_massal'] = "Modul tidak valid atau Anda tidak memiliki izin untuk mengakses modul ini.";
    header("Location: data_massal.php"); // Tetap di folder yang sama
    exit();
}

$current_module_config = $export_import_modules[$module_type];
$table_name = $current_module_config['table'];

// --- Fungsi Helper untuk Command Line MySQL (dari pengaturan_database.php) ---
// Dapatkan detail koneksi database dari konstanta global
$host = DB_HOST;
$dbname = DB_NAME;
$db_username = DB_USER;
$db_password = DB_PASS;

// Path ke mysqldump/mysql (dari konstanta global)
$mysqldump_path = MYSQLDUMP_PATH;
$mysql_path = MYSQL_PATH;

// Direktori backup database SQL (digunakan untuk backup otomatis)
$db_backup_dir = 'assets/uploads/database_backups/'; // Path relatif dari htdocs root
$absolute_db_backup_dir = __DIR__ . '/../../../' . $db_backup_dir; // Path absolut di server


// --- Backup Database Sebelum Import (Opsional tapi sangat disarankan) ---
function createDatabaseBackup($pdo_conn, $user_nik, $backup_dir_abs, $host_db, $dbname_db, $db_username_db, $db_password_db, $mysqldump_path_exec) {
    if (!is_dir($backup_dir_abs)) {
        @mkdir($backup_dir_abs, 0755, true);
    }
    if (!is_writable($backup_dir_abs)) {
        return ['status' => 'error', 'message' => "Direktori backup tidak dapat ditulis. Mohon atur izin folder: " . htmlspecialchars($backup_dir_abs)];
    }

    $filename = 'pre_import_backup_' . date('Ymd_His') . '.sql';
    $filepath = $backup_dir_abs . $filename;

    $command = sprintf(
        "%s -h %s -u %s %s %s > %s 2>&1",
        escapeshellarg($mysqldump_path_exec),
        escapeshellarg($host_db),
        escapeshellarg($db_username_db),
        (!empty($db_password_db) ? '-p' . escapeshellarg($db_password_db) : ''),
        escapeshellarg($dbname_db),
        escapeshellarg($filepath)
    );

    exec($command, $output, $return_var);

    if ($return_var === 0) { // return_var 0 menandakan perintah sukses
        // Audit Log backup
        $log_data_baru = ['filename' => $filename, 'filepath' => $filepath, 'size' => @filesize($filepath) ? round(@filesize($filepath) / (1024 * 1024), 2) . ' MB' : 'N/A'];
        $log_stmt = $pdo_conn->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
        $log_stmt->execute([
            'un' => $user_nik,
            'a' => 'BACKUP OTOMATIS (PRE-IMPORT)',
            't' => 'database',
            'id' => $filename,
            'db' => json_encode($log_data_baru),
            'ket' => 'Backup otomatis sebelum operasi import data massal.'
        ]);
        return ['status' => 'success', 'message' => "Backup otomatis berhasil dibuat: " . htmlspecialchars($filename)];
    } else {
        error_log("Pre-Import DB Backup Failed. Command: " . $command . "\nOutput: " . implode("\n", $output));
        return ['status' => 'error', 'message' => "Gagal membuat backup otomatis sebelum import. Output: " . implode("\n", $output)];
    }
}
// === Akhir Fungsi Helper Backup ===


// === Export Logic ===
if ($action == 'export_template' || $action == 'export_data') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set header
    $headers = $current_module_config['export_headers'];
    $sheet->fromArray([$headers], NULL, 'A1');

    // Jika export data
    if ($action == 'export_data') {
        $query = $current_module_config['query_export'];
        $params = [];

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_NUM); // Ambil data sebagai array numerik
            $sheet->fromArray($data, NULL, 'A2'); // Tulis data mulai dari baris kedua
        } catch (PDOException $e) {
            $_SESSION['pesan_error_data_massal'] = "Gagal mengambil data untuk export: " . htmlspecialchars($e->getMessage());
            header("Location: data_massal.php"); // Tetap di folder yang sama
            exit();
        }
    }

    $writer = new Xlsx($spreadsheet); // Default ke XLSX
    $filename = str_replace(' ', '_', $current_module_config['name']) . ($action == 'export_template' ? '_template' : '_data') . '_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');

    // Audit Log Export
    $log_action = ($action == 'export_template' ? 'EXPORT TEMPLATE DATA' : 'EXPORT DATA');
    $log_message = ($action == 'export_template' ? 'Template kosong' : 'Data aktual') . ' modul ' . $current_module_config['name'] . ' diekspor.';
    $log_data = ['module' => $module_type, 'filename' => $filename, 'action_type' => $action];

    // Perlu pastikan $pdo ada untuk audit log
    if (isset($pdo) && $pdo instanceof PDO) {
        $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
        $log_stmt->execute([
            'un' => $user_nik_pelaku,
            'a' => $log_action,
            't' => $table_name,
            'id' => $filename, // ID bisa jadi filename untuk log export
            'db' => json_encode($log_data),
            'ket' => $log_message
        ]);
    }
    exit();
}

// === Import Logic ===
if ($action == 'import_data') {
    $_SESSION['form_data_massal'] = $_POST; // Simpan data form untuk repopulate

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] != UPLOAD_ERR_OK) {
        $_SESSION['pesan_error_data_massal'] = "File import tidak diupload atau terjadi kesalahan: " . $_FILES['import_file']['error'];
        header("Location: data_massal.php"); // Tetap di folder yang sama
        exit();
    }

    $inputFileName = $_FILES['import_file']['tmp_name'];
    $fileExtension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);
    $reader = null;

    if ($fileExtension == 'xlsx') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    } elseif ($fileExtension == 'csv') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        // Konfigurasi CSV reader jika diperlukan (delimiter, enclosure, escape)
        $reader->setDelimiter(','); // Sesuaikan jika delimiter CSV Anda beda
    } else {
        $_SESSION['pesan_error_data_massal'] = "Format file tidak didukung. Hanya .xlsx atau .csv yang diizinkan.";
        header("Location: data_massal.php"); // Tetap di folder yang sama
        exit();
    }

    // Batasan ukuran file spreadsheet (Max 10MB seperti di form)
    $max_spreadsheet_size_mb = 10;
    if ($_FILES['import_file']['size'] > $max_spreadsheet_size_mb * 1024 * 1024) {
        $_SESSION['pesan_error_data_massal'] = "Ukuran file spreadsheet melebihi batas " . $max_spreadsheet_size_mb . "MB.";
        header("Location: data_massal.php"); // Tetap di folder yang sama
        exit();
    }

    try {
        $spreadsheet = $reader->load($inputFileName);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $data_from_file = $sheet->toArray(null, true, true, true); // Baca semua data ke array

        if (count($data_from_file) < 2) {
            $_SESSION['pesan_error_data_massal'] = "File kosong atau hanya berisi header.";
            header("Location: data_massal.php"); // Tetap di folder yang sama
            exit();
        }

        $headers_raw = array_values($data_from_file[1]); // Header dari baris pertama file
        $headers_clean = array_map(function($h){ return strtolower(str_replace([' ', '/', '(', ')', '.', '-'], '_', trim($h))); }, $headers_raw);

        $import_errors = [];
        $processed_count = 0;
        $skipped_count = 0;

        // --- Backup Database Otomatis Sebelum Import ---
        // Menggunakan fungsi yang sudah ada
        $backup_result = createDatabaseBackup($pdo, $user_nik_pelaku, $absolute_db_backup_dir, $host, $dbname, $db_username, $db_password, $mysqldump_path);
        if ($backup_result['status'] == 'error') {
            $_SESSION['pesan_error_data_massal'] = "Import dibatalkan: " . $backup_result['message'];
            header("Location: data_massal.php"); // Tetap di folder yang sama
            exit();
        } else {
            $_SESSION['pesan_info_data_massal'] = $backup_result['message']; // Tambahkan pesan info backup
        }
        // --- Akhir Backup Otomatis ---

        $pdo->beginTransaction(); // Mulai transaksi untuk import

        // Loop melalui setiap baris data (mulai dari baris kedua, setelah header)
        for ($row = 2; $row <= $highestRow; $row++) {
            $row_data_raw = array_combine($headers_clean, array_values($data_from_file[$row]));
            $row_data_clean = [];
            $row_errors = [];

            // Mapping dan sanitasi dasar per baris
            foreach ($current_module_config['import_columns_map'] as $header_spreadsheet => $db_column_name) {
                $value = $row_data_raw[$header_spreadsheet] ?? null;
                // Sanitasi lebih lanjut di sini jika diperlukan (misal strip_tags, validasi email, dll)
                $row_data_clean[$db_column_name] = trim($value);
            }

            // Validasi kolom wajib
            foreach ($current_module_config['required_import_fields'] as $field) {
                if (empty($row_data_clean[$field])) {
                    $row_errors[] = "Kolom '" . htmlspecialchars($field) . "' wajib diisi.";
                }
            }

            // Validasi NIK pengguna (harus ada di tabel pengguna & aktif)
            if (isset($row_data_clean['nik'])) {
                if (!preg_match('/^\d{16}$/', $row_data_clean['nik'])) {
                    $row_errors[] = "Format NIK '" . htmlspecialchars($row_data_clean['nik']) . "' tidak valid (harus 16 digit angka).";
                } else {
                    $stmt_check_user = $pdo->prepare("SELECT nik FROM pengguna WHERE nik = :nik AND is_approved = 1");
                    $stmt_check_user->execute([':nik' => $row_data_clean['nik']]);
                    if (!$stmt_check_user->fetch()) {
                        $row_errors[] = "NIK '" . htmlspecialchars($row_data_clean['nik']) . "' tidak terdaftar sebagai pengguna aktif.";
                    }
                }
            }
            // Validasi NIK pengurus (ketua_cabor_nik, dll. untuk cabor)
            foreach(['ketua_cabor_nik', 'sekretaris_cabor_nik', 'bendahara_cabor_nik'] as $nik_col) {
                if(isset($row_data_clean[$nik_col]) && !empty($row_data_clean[$nik_col])) {
                    if (!preg_match('/^\d{16}$/', $row_data_clean[$nik_col])) {
                        $row_errors[] = "Format NIK {$nik_col} '" . htmlspecialchars($row_data_clean[$nik_col]) . "' tidak valid (harus 16 digit angka).";
                    } else {
                        $stmt_check_user = $pdo->prepare("SELECT nik FROM pengguna WHERE nik = :nik AND is_approved = 1");
                        $stmt_check_user->execute([':nik' => $row_data_clean[$nik_col]]);
                        if (!$stmt_check_user->fetch()) {
                            $row_errors[] = "NIK {$nik_col} '" . htmlspecialchars($row_data_clean[$nik_col]) . "' tidak terdaftar sebagai pengguna aktif.";
                        }
                    }
                }
            }


            // Validasi ID Cabor
            if (isset($row_data_clean['id_cabor'])) {
                if (!filter_var($row_data_clean['id_cabor'], FILTER_VALIDATE_INT)) {
                    $row_errors[] = "ID Cabor '" . htmlspecialchars($row_data_clean['id_cabor']) . "' tidak valid (harus angka).";
                } else {
                    $stmt_check_cabor = $pdo->prepare("SELECT id_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor AND status_kepengurusan = 'Aktif'");
                    $stmt_check_cabor->execute([':id_cabor' => $row_data_clean['id_cabor']]);
                    if (!$stmt_check_cabor->fetch()) {
                        $row_errors[] = "ID Cabor '" . htmlspecialchars($row_data_clean['id_cabor']) . "' tidak valid atau tidak aktif.";
                    }
                }
            }

            // Validasi ID Klub (jika ada) / ID Klub Afiliasi
            if (isset($row_data_clean['id_klub']) && !empty($row_data_clean['id_klub'])) {
                if (!filter_var($row_data_clean['id_klub'], FILTER_VALIDATE_INT)) {
                    $row_errors[] = "ID Klub '" . htmlspecialchars($row_data_clean['id_klub']) . "' tidak valid (harus angka).";
                } elseif (isset($row_data_clean['id_cabor']) && filter_var($row_data_clean['id_cabor'], FILTER_VALIDATE_INT)) {
                    $stmt_check_klub = $pdo->prepare("SELECT id_klub FROM klub WHERE id_klub = :id_klub AND id_cabor = :id_cabor AND status_approval_admin = 'disetujui'");
                    $stmt_check_klub->execute([':id_klub' => $row_data_clean['id_klub'], ':id_cabor' => $row_data_clean['id_cabor']]);
                    if (!$stmt_check_klub->fetch()) {
                        $row_errors[] = "ID Klub '" . htmlspecialchars($row_data_clean['id_klub']) . "' tidak valid untuk Cabor ini atau belum disetujui.";
                    }
                } else {
                    $row_errors[] = "ID Cabor tidak valid untuk validasi ID Klub.";
                }
            }
            if (isset($row_data_clean['id_klub_afiliasi']) && !empty($row_data_clean['id_klub_afiliasi'])) { // Untuk pelatih
                if (!filter_var($row_data_clean['id_klub_afiliasi'], FILTER_VALIDATE_INT)) {
                    $row_errors[] = "ID Klub Afiliasi '" . htmlspecialchars($row_data_clean['id_klub_afiliasi']) . "' tidak valid (harus angka).";
                } elseif (isset($row_data_clean['id_cabor']) && filter_var($row_data_clean['id_cabor'], FILTER_VALIDATE_INT)) {
                    $stmt_check_klub_afiliasi = $pdo->prepare("SELECT id_klub FROM klub WHERE id_klub = :id_klub AND id_cabor = :id_cabor AND status_approval_admin = 'disetujui'");
                    $stmt_check_klub_afiliasi->execute([':id_klub' => $row_data_clean['id_klub_afiliasi'], ':id_cabor' => $row_data_clean['id_cabor']]);
                    if (!$stmt_check_klub_afiliasi->fetch()) {
                        $row_errors[] = "ID Klub Afiliasi '" . htmlspecialchars($row_data_clean['id_klub_afiliasi']) . "' tidak valid untuk Cabor ini atau belum disetujui.";
                    }
                } else {
                    $row_errors[] = "ID Cabor tidak valid untuk validasi ID Klub Afiliasi.";
                }
            }

            // Validasi Tanggal (jika ada, misal tanggal_lahir, tanggal_sk_klub, periode_mulai, periode_selesai)
            foreach (['tanggal_lahir', 'tanggal_sk_klub', 'periode_mulai', 'periode_selesai'] as $date_col) {
                if (isset($row_data_clean[$date_col]) && !empty($row_data_clean[$date_col])) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row_data_clean[$date_col]) || !strtotime($row_data_clean[$date_col])) {
                        $row_errors[] = "Format {$date_col} tidak valid (YYYY-MM-DD).";
                    }
                }
            }

            // Validasi Tahun (jika ada, misal tahun_perolehan)
            if (isset($row_data_clean['tahun_perolehan']) && !empty($row_data_clean['tahun_perolehan'])) {
                if (!filter_var($row_data_clean['tahun_perolehan'], FILTER_VALIDATE_INT) || $row_data_clean['tahun_perolehan'] < 1900 || $row_data_clean['tahun_perolehan'] > (date('Y') + 1)) {
                    $row_errors[] = "Tahun Perolehan tidak valid.";
                }
            }

            // Validasi ENUM (misal jenis_kelamin, tingkat_kejuaraan, status_kepengurusan)
            if (isset($row_data_clean['jenis_kelamin']) && !in_array($row_data_clean['jenis_kelamin'], ['Laki-laki', 'Perempuan'])) {
                $row_errors[] = "Jenis Kelamin tidak valid. Pilih 'Laki-laki' atau 'Perempuan'.";
            }
            if (isset($row_data_clean['tingkat_kejuaraan']) && !in_array($row_data_clean['tingkat_kejuaraan'], ['Kabupaten', 'Provinsi', 'Nasional', 'Internasional'])) {
                $row_errors[] = "Tingkat Kejuaraan tidak valid. Pilih Kabupaten, Provinsi, Nasional, atau Internasional.";
            }
            if (isset($row_data_clean['status_kepengurusan']) && !in_array($row_data_clean['status_kepengurusan'], ['Aktif', 'Tidak Aktif', 'Masa Tenggang'])) {
                $row_errors[] = "Status Kepengurusan tidak valid. Pilih Aktif, Tidak Aktif, atau Masa Tenggang.";
            }


            // Jika ada error pada baris ini, catat dan lewati
            if (!empty($row_errors)) {
                $import_errors[] = "Baris " . $row . ": " . implode("; ", $row_errors);
                $skipped_count++;
                continue; // Lanjut ke baris berikutnya
            }

            // === Proses INSERT/UPDATE per baris ===
            try {
                $is_update = false;
                $current_record_id = null; // ID untuk audit log

                // Cek apakah data sudah ada (untuk UPDATE) atau perlu INSERT
                $select_query_parts = [];
                $unique_params_check = [];
                $where_clause_update_id = ""; // Untuk klausa WHERE di UPDATE
                $id_column_name = ""; // Nama kolom ID unik di tabel (misal id_atlet, id_klub)

                foreach($current_module_config['unique_import_fields'] as $field) {
                    $select_query_parts[] = "{$field} = :{$field}";
                    $unique_params_check[":{$field}"] = $row_data_clean[$field];
                }

                switch ($module_type) {
                    case 'cabor': $id_column_name = 'id_cabor'; break;
                    case 'klub': $id_column_name = 'id_klub'; break;
                    case 'atlet': $id_column_name = 'id_atlet'; break;
                    case 'pelatih': $id_column_name = 'id_pelatih'; break;
                    case 'wasit': $id_column_name = 'id_wasit'; break;
                    case 'prestasi': $id_column_name = 'id_prestasi'; break;
                }

                $select_query = "SELECT {$id_column_name} FROM {$table_name} WHERE " . implode(' AND ', $select_query_parts);
                $stmt_check = $pdo->prepare($select_query);
                $stmt_check->execute($unique_params_check);
                $existing_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($existing_data) {
                    $is_update = true;
                    $current_record_id = $existing_data[$id_column_name];
                    $where_clause_update_id = "{$id_column_name} = :current_id_for_update";
                }

                $update_sets = [];
                $insert_columns_sql = [];
                $insert_placeholders_sql = [];
                $insert_values_sql = [];
                $update_values_sql = [];

                // Status approval awal untuk import oleh Super Admin
                $status_approval_import = 'disetujui'; // Default untuk Super Admin
                $approved_by_nik_import = $user_nik_pelaku;
                $approval_at_import = date('Y-m-d H:i:s');
                $alasan_penolakan_import = null;


                // === Proses INSERT atau UPDATE ===
                if (!$is_update) {
                    // Logika INSERT
                    $insert_columns_sql = array_keys($current_module_config['import_columns_map']);
                    $insert_placeholders_sql = array_map(fn($col) => ":{$col}", $insert_columns_sql);
                    $insert_values_sql = $row_data_clean;

                    // Tambahkan kolom approval dan histori untuk INSERT
                    switch ($module_type) {
                        case 'cabor':
                            // Generate kode_cabor untuk data baru cabor
                            $nama_cabor_for_code = $row_data_clean['nama_cabor'];
                            $prefix_code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nama_cabor_for_code), 0, 3));
                            if (strlen($prefix_code) < 3) $prefix_code = str_pad($prefix_code, 3, 'X');
                            if (empty($prefix_code)) $prefix_code = "CBR";

                            $stmt_last_kode = $pdo->prepare("SELECT kode_cabor FROM cabang_olahraga WHERE kode_cabor LIKE :prefix ORDER BY kode_cabor DESC LIMIT 1");
                            $search_prefix = $prefix_code . "%";
                            $stmt_last_kode->bindParam(':prefix', $search_prefix);
                            $stmt_last_kode->execute();
                            $last_kode_cabor = $stmt_last_kode->fetchColumn();

                            $nomor_urut = 1;
                            if ($last_kode_cabor) {
                                $nomor_urut = (int)substr($last_kode_cabor, strlen($prefix_code)) + 1;
                            }
                            $kode_cabor_generated = $prefix_code . str_pad($nomor_urut, 2, '0', STR_PAD_LEFT);
                            $insert_columns_sql[] = 'kode_cabor'; $insert_placeholders_sql[] = ':kode_cabor'; $insert_values_sql['kode_cabor'] = $kode_cabor_generated;
                            // Jumlah statistik awal 0
                            $insert_columns_sql[] = 'jumlah_klub'; $insert_placeholders_sql[] = ':jumlah_klub'; $insert_values_sql['jumlah_klub'] = 0;
                            $insert_columns_sql[] = 'jumlah_atlet'; $insert_placeholders_sql[] = ':jumlah_atlet'; $insert_values_sql['jumlah_atlet'] = 0;
                            $insert_columns_sql[] = 'jumlah_pelatih'; $insert_placeholders_sql[] = ':jumlah_pelatih'; $insert_values_sql['jumlah_pelatih'] = 0;
                            $insert_columns_sql[] = 'jumlah_wasit'; $insert_placeholders_sql[] = ':jumlah_wasit'; $insert_values_sql['jumlah_wasit'] = 0;

                            break;
                        case 'atlet':
                            $id_atlet_for_insert = null;
                            $stmt_get_id_atlet_pengguna = $pdo->prepare("SELECT id_atlet FROM atlet WHERE nik = :nik AND id_cabor = :id_cabor");
                            $stmt_get_id_atlet_pengguna->execute([':nik' => $row_data_clean['nik'], ':id_cabor' => $row_data_clean['id_cabor']]);
                            $existing_atlet_id_in_db = $stmt_get_id_atlet_pengguna->fetchColumn();
                            if (!$existing_atlet_id_in_db) {
                                // Ini benar-benar INSERT baru
                            } else {
                                // Ini seharusnya jadi UPDATE, bukan INSERT, error jika masuk sini
                                $row_errors[] = "Atlet NIK " . htmlspecialchars($row_data_clean['nik']) . " sudah terdaftar di cabor ini, seharusnya di-update.";
                                throw new Exception("Duplicate entry on insert attempt."); // Paksa error agar baris dilewati
                            }

                            $insert_columns_sql[] = 'status_pendaftaran'; $insert_placeholders_sql[] = ':status_pendaftaran'; $insert_values_sql['status_pendaftaran'] = 'disetujui'; // SA langsung disetujui
                            $insert_columns_sql[] = 'approved_by_nik_pengcab'; $insert_placeholders_sql[] = ':app_pengcab_nik'; $insert_values_sql['app_pengcab_nik'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at_pengcab'; $insert_placeholders_sql[] = ':app_pengcab_at'; $insert_values_sql['app_pengcab_at'] = $approval_at_import;
                            $insert_columns_sql[] = 'approved_by_nik_admin'; $insert_placeholders_sql[] = ':app_admin_nik'; $insert_values_sql['app_admin_nik'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at_admin'; $insert_placeholders_sql[] = ':app_admin_at'; $insert_values_sql['app_admin_at'] = $approval_at_import;
                            $insert_columns_sql[] = 'updated_by_nik'; $insert_placeholders_sql[] = ':ubn'; $insert_values_sql['ubn'] = $user_nik_pelaku;
                            $insert_columns_sql[] = 'last_updated_process_at'; $insert_placeholders_sql[] = ':lup'; $insert_values_sql['lup'] = date('Y-m-d H:i:s');
                            $insert_columns_sql[] = 'created_at'; $insert_placeholders_sql[] = ':ca'; $insert_values_sql['ca'] = date('Y-m-d H:i:s');
                            $insert_columns_sql[] = 'updated_at'; $insert_placeholders_sql[] = ':ua'; $insert_values_sql['ua'] = date('Y-m-d H:i:s');

                            // ID Atlet dari tabel atlet
                            $insert_columns_sql[] = 'id_atlet'; $insert_placeholders_sql[] = ':id_atlet_val';
                            // Buat id_atlet secara manual atau ambil dari sequence jika perlu.
                            // Sebaiknya kolom id_atlet AUTO_INCREMENT. Jika AUTO_INCREMENT, jangan masukkan ke INSERT.
                            // Berdasarkan SQL dump Anda, id_atlet adalah AUTO_INCREMENT, jadi jangan sertakan.
                            // Jika Anda ingin mengupdate `pengguna` juga, ini dilakukan terpisah:
                            $update_pengguna_fields = [];
                            $update_pengguna_values = [];
                            if (isset($row_data_clean['tanggal_lahir'])) { $update_pengguna_fields[] = 'tanggal_lahir = :tanggal_lahir'; $update_pengguna_values[':tanggal_lahir'] = $row_data_clean['tanggal_lahir']; }
                            if (isset($row_data_clean['jenis_kelamin'])) { $update_pengguna_fields[] = 'jenis_kelamin = :jenis_kelamin'; $update_pengguna_values[':jenis_kelamin'] = $row_data_clean['jenis_kelamin']; }
                            if (isset($row_data_clean['nomor_telepon'])) { $update_pengguna_fields[] = 'nomor_telepon = :nomor_telepon'; $update_pengguna_values[':nomor_telepon'] = $row_data_clean['nomor_telepon']; }
                            if (isset($row_data_clean['email'])) { $update_pengguna_fields[] = 'email = :email_upd'; $update_pengguna_values[':email_upd'] = $row_data_clean['email']; }
                            if (isset($row_data_clean['alamat'])) { $update_pengguna_fields[] = 'alamat = :alamat_upd'; $update_pengguna_values[':alamat_upd'] = $row_data_clean['alamat']; }
                            if (!empty($update_pengguna_fields)) {
                                $update_pengguna_values[':nik_pengguna_update'] = $row_data_clean['nik'];
                                $stmt_update_pengguna_atlet = $pdo->prepare("UPDATE pengguna SET " . implode(', ', $update_pengguna_fields) . " WHERE nik = :nik_pengguna_update");
                                $stmt_update_pengguna_atlet->execute($update_pengguna_values);
                            }
                            break;
                        case 'pelatih':
                            $insert_columns_sql[] = 'status_approval'; $insert_placeholders_sql[] = ':status_approval'; $insert_values_sql['status_approval'] = 'disetujui';
                            $insert_columns_sql[] = 'approved_by_nik'; $insert_placeholders_sql[] = ':app_by'; $insert_values_sql['app_by'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at'; $insert_placeholders_sql[] = ':app_at'; $insert_values_sql['app_at'] = $approval_at_import;
                            $insert_columns_sql[] = 'updated_by_nik'; $insert_placeholders_sql[] = ':ubn'; $insert_values_sql['ubn'] = $user_nik_pelaku;
                            $insert_columns_sql[] = 'last_updated_process_at'; $insert_placeholders_sql[] = ':lup'; $insert_values_sql['lup'] = date('Y-m-d H:i:s');
                            break;
                        case 'wasit':
                            $insert_columns_sql[] = 'status_approval'; $insert_placeholders_sql[] = ':status_approval'; $insert_values_sql['status_approval'] = 'disetujui';
                            $insert_columns_sql[] = 'approved_by_nik'; $insert_placeholders_sql[] = ':app_by'; $insert_values_sql['app_by'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at'; $insert_placeholders_sql[] = ':app_at'; $insert_values_sql['app_at'] = $approval_at_import;
                            $insert_columns_sql[] = 'updated_by_nik'; $insert_placeholders_sql[] = ':ubn'; $insert_values_sql['ubn'] = $user_nik_pelaku;
                            $insert_columns_sql[] = 'last_updated_process_at'; $insert_placeholders_sql[] = ':lup'; $insert_values_sql['lup'] = date('Y-m-d H:i:s');
                            break;
                        case 'klub':
                            $insert_columns_sql[] = 'status_approval_admin'; $insert_placeholders_sql[] = ':status_app'; $insert_values_sql['status_app'] = 'disetujui';
                            $insert_columns_sql[] = 'approved_by_nik_admin'; $insert_placeholders_sql[] = ':app_by_admin'; $insert_values_sql['app_by_admin'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at_admin'; $insert_placeholders_sql[] = ':app_at_admin'; $insert_values_sql['app_at_admin'] = $approval_at_import;
                            $insert_columns_sql[] = 'created_by_nik_pengcab'; $insert_placeholders_sql[] = ':created_by_pengcab'; $insert_values_sql['created_by_pengcab'] = $user_nik_pelaku; // SA sebagai pengaju
                            $insert_columns_sql[] = 'updated_by_nik'; $insert_placeholders_sql[] = ':ubn'; $insert_values_sql['ubn'] = $user_nik_pelaku;
                            $insert_columns_sql[] = 'last_updated_process_at'; $insert_placeholders_sql[] = ':lup'; $insert_values_sql['lup'] = date('Y-m-d H:i:s');
                            break;
                        case 'prestasi':
                            // Dapatkan id_atlet dari tabel atlet berdasarkan nik dan id_cabor
                            $stmt_get_id_atlet = $pdo->prepare("SELECT id_atlet FROM atlet WHERE nik = :nik AND id_cabor = :id_cabor AND status_pendaftaran = 'disetujui'");
                            $stmt_get_id_atlet->execute([':nik' => $row_data_clean['nik'], ':id_cabor' => $row_data_clean['id_cabor']]);
                            $id_atlet_for_prestasi = $stmt_get_id_atlet->fetchColumn();
                            if (!$id_atlet_for_prestasi) {
                                $row_errors[] = "Atlet NIK ".htmlspecialchars($row_data_clean['nik'])." tidak ditemukan di cabor yang dipilih atau belum disetujui.";
                                throw new Exception("Atlet tidak valid."); // Dilempar untuk ditangkap di catch bawah
                            }
                            $insert_columns_sql[] = 'id_atlet'; $insert_placeholders_sql[] = ':id_atlet'; $insert_values_sql['id_atlet'] = $id_atlet_for_prestasi;

                            $insert_columns_sql[] = 'status_approval'; $insert_placeholders_sql[] = ':status_approval'; $insert_values_sql['status_approval'] = 'disetujui_admin';
                            $insert_columns_sql[] = 'approved_by_nik_pengcab'; $insert_placeholders_sql[] = ':app_pengcab_nik'; $insert_values_sql['app_pengcab_nik'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at_pengcab'; $insert_placeholders_sql[] = ':app_pengcab_at'; $insert_values_sql['app_pengcab_at'] = $approval_at_import;
                            $insert_columns_sql[] = 'approved_by_nik_admin'; $insert_placeholders_sql[] = ':app_admin_nik'; $insert_values_sql['app_admin_nik'] = $approved_by_nik_import;
                            $insert_columns_sql[] = 'approval_at_admin'; $insert_placeholders_sql[] = ':app_admin_at'; $insert_values_sql['app_admin_at'] = $approval_at_import;
                            $insert_columns_sql[] = 'updated_by_nik'; $insert_placeholders_sql[] = ':ubn'; $insert_values_sql['ubn'] = $user_nik_pelaku;
                            $insert_columns_sql[] = 'last_updated_process_at'; $insert_placeholders_sql[] = ':lup'; $insert_values_sql['lup'] = date('Y-m-d H:i:s');
                            break;
                    }

                    $insert_sql = "INSERT INTO {$table_name} (" . implode(', ', $insert_columns_sql) . ") VALUES (" . implode(', ', $insert_placeholders_sql) . ")";
                    $stmt_insert = $pdo->prepare($insert_sql);
                    $stmt_insert->execute($insert_values_sql);
                    $current_record_id = $pdo->lastInsertId();

                    // Update jumlah di cabor jika status diapprove saat insert (oleh Super Admin)
                    if ($module_type == 'atlet' && $insert_values_sql['status_pendaftaran'] == 'disetujui') {
                        $pdo->prepare("UPDATE cabang_olahraga SET jumlah_atlet = jumlah_atlet + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]);
                    } elseif ($module_type == 'pelatih' && $insert_values_sql['status_approval'] == 'disetujui') {
                        $pdo->prepare("UPDATE cabang_olahraga SET jumlah_pelatih = jumlah_pelatih + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]);
                    } elseif ($module_type == 'wasit' && $insert_values_sql['status_approval'] == 'disetujui') {
                        $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = jumlah_wasit + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]);
                    } elseif ($module_type == 'klub' && $insert_values_sql['status_app'] == 'disetujui') {
                        $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = jumlah_klub + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]);
                    }
                    // Cabor tidak perlu update jumlah karena dia induk

                    // Audit Log INSERT
                    $stmt_new_data = $pdo->prepare("SELECT * FROM {$table_name} WHERE {$id_column_name} = :id");
                    $stmt_new_data->bindParam(':id', $current_record_id);
                    $stmt_new_data->execute();
                    $new_record_data = $stmt_new_data->fetch(PDO::FETCH_ASSOC);

                    $log_action = "IMPORT DATA (ADD) " . strtoupper($module_type);
                    $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
                    $log_stmt->execute([
                        'un' => $user_nik_pelaku, 'a' => $log_action, 't' => $table_name, 'id' => $current_record_id,
                        'db' => json_encode($new_record_data), 'ket' => "Data diimport via spreadsheet (baru)."
                    ]);

                } else { // Jika akan melakukan UPDATE
                    // Ambil data lama untuk audit log (sebelum update)
                    $stmt_old_data_log = $pdo->prepare("SELECT * FROM {$table_name} WHERE {$id_column_name} = :id");
                    $stmt_old_data_log->bindParam(':id', $current_record_id);
                    $stmt_old_data_log->execute();
                    $old_record_data = $stmt_old_data_log->fetch(PDO::FETCH_ASSOC);

                    // Siapkan kolom-kolom yang akan diupdate
                    foreach ($row_data_clean as $col => $val) {
                        $update_sets[] = "{$col} = :{$col}";
                    }
                    $update_values = $row_data_clean;
                    $update_values[':current_id_for_update'] = $current_record_id;

                    // Tambahkan kolom histori update proses
                    $update_sets[] = "updated_by_nik = :ubn";
                    $update_values[':ubn'] = $user_nik_pelaku;
                    $update_sets[] = "last_updated_process_at = :lup";
                    $update_values[':lup'] = date('Y-m-d H:i:s');

                    // Atur status approval saat update oleh SA (jika belum disetujui final)
                    // Untuk kasus import massal oleh SA, semua dianggap final disetujui.
                    $is_status_changed_by_import = false;
                    $old_status = $old_record_data['status_approval'] ?? ($old_record_data['status_pendaftaran'] ?? ($old_record_data['status_approval_admin'] ?? null));
                    $new_status_for_update = null; // Status yang akan diterapkan saat update

                    switch($module_type) {
                        case 'atlet':
                            $new_status_for_update = 'disetujui';
                            $update_sets[] = 'status_pendaftaran = :new_status'; $update_values[':new_status'] = $new_status_for_update;
                            $update_sets[] = 'approved_by_nik_admin = :app_admin_nik'; $update_values[':app_admin_nik'] = $user_nik_pelaku;
                            $update_sets[] = 'approval_at_admin = :app_admin_at'; $update_values[':app_admin_at'] = date('Y-m-d H:i:s');
                            $update_sets[] = 'alasan_penolakan_pengcab = NULL'; // Bersihkan jika disetujui
                            $update_sets[] = 'alasan_penolakan_admin = NULL';
                            break;
                        case 'pelatih':
                        case 'wasit':
                            $new_status_for_update = 'disetujui';
                            $update_sets[] = 'status_approval = :new_status'; $update_values[':new_status'] = $new_status_for_update;
                            $update_sets[] = 'approved_by_nik = :app_by'; $update_values[':app_by'] = $user_nik_pelaku;
                            $update_sets[] = 'approval_at = :app_at'; $update_values[':app_at'] = date('Y-m-d H:i:s');
                            $update_sets[] = 'alasan_penolakan = NULL';
                            break;
                        case 'klub':
                            $new_status_for_update = 'disetujui';
                            $update_sets[] = 'status_approval_admin = :new_status'; $update_values[':new_status'] = $new_status_for_update;
                            $update_sets[] = 'approved_by_nik_admin = :app_by_admin'; $update_values[':app_by_admin'] = $user_nik_pelaku;
                            $update_sets[] = 'approval_at_admin = :app_at_admin'; $update_values[':app_at_admin'] = date('Y-m-d H:i:s');
                            $update_sets[] = 'alasan_penolakan_admin = NULL';
                            break;
                        case 'prestasi':
                            $new_status_for_update = 'disetujui_admin';
                            $update_sets[] = 'status_approval = :new_status'; $update_values[':new_status'] = $new_status_for_update;
                            $update_sets[] = 'approved_by_nik_pengcab = :app_pengcab_nik'; $update_values[':app_pengcab_nik'] = $user_nik_pelaku; // Dianggap disetujui pengcab juga
                            $update_sets[] = 'approval_at_pengcab = :app_pengcab_at'; $update_values[':app_pengcab_at'] = date('Y-m-d H:i:s');
                            $update_sets[] = 'approved_by_nik_admin = :app_admin_nik'; $update_values[':app_admin_nik'] = $user_nik_pelaku;
                            $update_sets[] = 'approval_at_admin = :app_admin_at'; $update_values[':app_admin_at'] = date('Y-m-d H:i:s');
                            $update_sets[] = 'alasan_penolakan_pengcab = NULL';
                            $update_sets[] = 'alasan_penolakan_admin = NULL';
                            break;
                        case 'cabor': // Cabor tidak punya approval
                            // No specific approval fields for Cabor, just update existing fields
                            break;
                    }

                    // Lakukan update database
                    $update_sql = "UPDATE {$table_name} SET " . implode(', ', $update_sets) . " WHERE {$id_column_name} = :current_id_for_update";
                    $stmt_update = $pdo->prepare($update_sql);
                    $stmt_update->execute($update_values);

                    // Update jumlah di cabor jika status berubah dari tidak disetujui ke disetujui (atau sebaliknya)
                    // Periksa apakah status LAMA tidak disetujui DAN status BARU disetujui (oleh import)
                    if ($new_status_for_update && $old_status != $new_status_for_update) {
                        if (in_array($new_status_for_update, ['disetujui', 'disetujui_admin'])) { // Jika status baru adalah disetujui final
                            if (!in_array($old_status, ['disetujui', 'disetujui_admin'])) { // Jika status lama bukan disetujui final
                                switch ($module_type) {
                                    case 'atlet': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_atlet = jumlah_atlet + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                    case 'pelatih': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_pelatih = jumlah_pelatih + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                    case 'wasit': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = jumlah_wasit + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                    case 'klub': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = jumlah_klub + 1 WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                }
                            }
                        } elseif (in_array($old_status, ['disetujui', 'disetujui_admin'])) { // Jika status lama disetujui final, tapi sekarang berubah tidak disetujui final
                            switch ($module_type) {
                                case 'atlet': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_atlet = GREATEST(0, jumlah_atlet - 1) WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                case 'pelatih': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_pelatih = GREATEST(0, jumlah_pelatih - 1) WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                case 'wasit': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = GREATEST(0, jumlah_wasit - 1) WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                                case 'klub': $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = GREATEST(0, jumlah_klub - 1) WHERE id_cabor = ?")->execute([$row_data_clean['id_cabor']]); break;
                            }
                        }
                    }

                    // Audit Log UPDATE
                    $stmt_new_data = $pdo->prepare("SELECT * FROM {$table_name} WHERE {$id_column_name} = :id");
                    $stmt_new_data->bindParam(':id', $current_record_id);
                    $stmt_new_data->execute();
                    $new_record_data = $stmt_new_data->fetch(PDO::FETCH_ASSOC);

                    $log_action = "IMPORT DATA (UPDATE) " . strtoupper($module_type);
                    $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:un, :a, :t, :id, :dl, :db, :ket)");
                    $log_stmt->execute([
                        'un' => $user_nik_pelaku, 'a' => $log_action, 't' => $table_name, 'id' => $current_record_id,
                        'dl' => json_encode($old_record_data), 'db' => json_encode($new_record_data),
                        'ket' => "Data diperbarui via spreadsheet."
                    ]);
                }
                $processed_count++;

            } catch (Exception $e) { // Tangkap Exception dari validasi internal atau PDOException
                $import_errors[] = "Baris " . $row . ": Gagal memproses data. " . htmlspecialchars($e->getMessage());
                $skipped_count++;
            }
        }

        if (empty($import_errors)) {
            $pdo->commit();
            $_SESSION['pesan_sukses_data_massal'] = "Import data " . $current_module_config['name'] . " berhasil. " . $processed_count . " baris diproses.";
            if (isset($_SESSION['pesan_info_data_massal'])) {
                $_SESSION['pesan_sukses_data_massal'] .= " " . $_SESSION['pesan_info_data_massal'];
                unset($_SESSION['pesan_info_data_massal']);
            }
        } else {
            $pdo->rollBack();
            $_SESSION['pesan_error_data_massal'] = "Import data " . $current_module_config['name'] . " selesai dengan beberapa kesalahan. " . $processed_count . " baris diproses, " . $skipped_count . " baris dilewati.";
            $_SESSION['pesan_error_data_massal'] .= "<br>Detail:<ul><li>" . implode("</li><li>", $import_errors) . "</li></ul>";
            if (isset($_SESSION['pesan_info_data_massal'])) {
                $_SESSION['pesan_error_data_massal'] .= " " . $_SESSION['pesan_info_data_massal'];
                unset($_SESSION['pesan_info_data_massal']);
            }
        }
        break;

    default:
        $_SESSION['pesan_error_data_massal'] = "Aksi tidak valid.";
        break;
}

header("Location: data_massal.php"); // Tetap di folder yang sama
exit();