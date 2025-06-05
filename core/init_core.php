<?php
// File: public_html/reaktorsystem/core/init_core.php

// ========================================================================
// AWAL: Deteksi ENVIRONMENT dan Konfigurasi Awal
// ========================================================================
if (!defined('ENVIRONMENT')) {
    if (isset($_SERVER['HTTP_HOST']) && (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'], '.test') !== false || strpos($_SERVER['HTTP_HOST'], '.dev') !== false)) {
        define('ENVIRONMENT', 'development');
    } else {
        define('ENVIRONMENT', 'production');
    }
}

// Path dasar aplikasi di server (filesystem) - DEFINISIKAN DI AWAL UNTUK DIGUNAKAN GLOBAL
if (!defined('APP_PATH_BASE')) {
    define('APP_PATH_BASE', dirname(dirname(__FILE__))); // /path/to/public_html/reaktorsystem
}
// ========================================================================
// AKHIR: Deteksi ENVIRONMENT dan Konfigurasi Awal
// ========================================================================


// ========================================================================
// AWAL: Kredensial Database dan Konfigurasi Path/URL Manual (Jika Ada)
// ========================================================================
// Sertakan Kredensial Database & Potensi Konfigurasi Manual dari database_credentials.php
if (file_exists(APP_PATH_BASE . '/database_credentials.php')) { // Menggunakan APP_PATH_BASE yang sudah didefinisi
    require_once(APP_PATH_BASE . '/database_credentials.php');
} else {
    $db_creds_error_msg = "INIT_CORE_FATAL: File database_credentials.php tidak ditemukan di " . APP_PATH_BASE . "/database_credentials.php.";
    error_log($db_creds_error_msg);
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Kesalahan Kritis: File database_credentials.php tidak ditemukan. Cek path: " . APP_PATH_BASE . "/database_credentials.php");
    } else {
        die("Kesalahan konfigurasi sistem inti (INIT_DB_CREDS_MISSING). Silakan hubungi administrator.");
    }
}
// ========================================================================
// AKHIR: Kredensial Database
// ========================================================================


// ========================================================================
// AWAL: Konfigurasi Path dan URL Aplikasi
// Prioritaskan konfigurasi manual jika didefinisikan di database_credentials.php
// ========================================================================
global $app_base_path; // Path web aplikasi, contoh: /reaktorsystem/ atau /

if (defined('APP_CONFIG_MANUAL_WEB_ROOT_PATH') && !empty(APP_CONFIG_MANUAL_WEB_ROOT_PATH)) {
    $app_base_path = APP_CONFIG_MANUAL_WEB_ROOT_PATH;
} else {
    // Deteksi otomatis $app_base_path (path web)
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // /path/ke/folder/aplikasi/core atau /path/ke/folder/aplikasi
        // Asumsi init_core.php ada di dalam folder 'core' dan 'core' adalah subfolder dari root aplikasi web.
        // Jika init_core.php ada di APP_PATH_BASE/core/init_core.php
        $app_server_root_norm = str_replace('\\', '/', APP_PATH_BASE);
        $doc_root_norm = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')) : $app_server_root_norm;

        // Jika APP_PATH_BASE sama dengan DOCUMENT_ROOT, maka aplikasi ada di root domain
        if (rtrim($app_server_root_norm, '/') === $doc_root_norm) {
            $app_base_path = '/';
        } else {
            // Aplikasi ada di subfolder dari DOCUMENT_ROOT
            $web_path_from_doc_root = str_replace($doc_root_norm, '', $app_server_root_norm);
            $app_base_path = rtrim($web_path_from_doc_root, '/') . '/';
        }
    } else {
        $app_base_path = '/'; // Default untuk CLI atau jika SCRIPT_NAME tidak tersedia
    }
}
$app_base_path = preg_replace('/\/+/', '/', $app_base_path); // Normalisasi: /folder// jadi /folder/
if ($app_base_path === '//') $app_base_path = '/'; // Handle jika hasil normalisasi adalah //

// URL Dasar Aplikasi
if (!defined('APP_URL_BASE')) {
    if (defined('APP_CONFIG_MANUAL_URL_BASE') && !empty(APP_CONFIG_MANUAL_URL_BASE)) {
        define('APP_URL_BASE', rtrim(APP_CONFIG_MANUAL_URL_BASE, '/'));
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Gabungkan host dengan $app_base_path yang sudah benar
        // rtrim(..., '/') untuk menghapus trailing slash dari $host atau $app_base_path jika salah satunya adalah '/'
        // lalu pastikan ada satu trailing slash jika $app_base_path bukan hanya '/'
        $base_url_combined = rtrim($host, '/') . ($app_base_path === '/' ? '' : rtrim($app_base_path, '/'));
        $base_url_combined = preg_replace_callback('/([^:])\/+/', function($m) { return $m[1] . '/'; }, $base_url_combined); // Hapus duplikasi slash
        if (substr($base_url_combined, -1) === '/' && strlen($base_url_combined) > 1 && $app_base_path !== '/') {
             // Ini mungkin tidak perlu jika rtrim di atas sudah benar
        }
        define('APP_URL_BASE', rtrim($protocol . $base_url_combined, '/')); // Hapus trailing slash terakhir dari URL Base
    }
}
// ========================================================================
// AKHIR: Konfigurasi Path dan URL Aplikasi
// ========================================================================


// ========================================================================
// AWAL: Konfigurasi Sesi PHP
// Dilakukan sebelum session_start()
// ========================================================================
if (php_sapi_name() !== 'cli') { // Hanya untuk akses web
    $cookie_path_setting = ($app_base_path === '//' || empty($app_base_path)) ? '/' : $app_base_path; // Pastikan path cookie benar
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookie_path_setting,
        'domain' => $cookieParams['domain'],
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
// Mulai Sesi
if (php_sapi_name() !== 'cli' && session_status() == PHP_SESSION_NONE) {
    session_start();
    // Catatan: session_regenerate_id(true); sebaiknya dipanggil setelah login sukses (di proses_login.php)
}
// ========================================================================
// AKHIR: Konfigurasi Sesi PHP
// ========================================================================


// Pengaturan Zona Waktu Default untuk PHP
if (!date_default_timezone_set('Asia/Jakarta')) {
    if (!(defined('ENVIRONMENT') && ENVIRONMENT === 'development')) {
        error_log("INIT_CORE_WARNING: Gagal mengatur default timezone PHP ke Asia/Jakarta.");
    }
}

// Pengaturan Error Reporting PHP
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    $log_dir = APP_PATH_BASE . '/logs'; // Menggunakan APP_PATH_BASE
    if (!file_exists($log_dir)) {
        if (!@mkdir($log_dir, 0755, true)) { // Coba buat jika belum ada
             error_log("INIT_CORE_WARNING: Gagal membuat direktori log '{$log_dir}'.");
        }
    }
    if (is_writable($log_dir)) {
        ini_set('error_log', $log_dir . '/php-error.log');
    } else {
        error_log("INIT_CORE_WARNING: Direktori log '{$log_dir}' tidak dapat ditulis. Error PHP mungkin tidak tercatat di file kustom.");
    }
}

// Konstanta Batas Waktu Inaktivitas Sesi
if (!defined('MAX_SESSION_INACTIVITY_SECONDS')) {
    define('MAX_SESSION_INACTIVITY_SECONDS', 30 * 60); // 30 menit
}

// ========================================================================
// AWAL: Konstanta Batas Ukuran File Upload (Dipusatkan di sini)
// Pastikan definisi ini tidak ada lagi di database_credentials.php untuk jenis file ini
// ========================================================================
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_MB')) { define('MAX_FILE_SIZE_FOTO_PROFIL_MB', 2); } // MB
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_BYTES')) { define('MAX_FILE_SIZE_FOTO_PROFIL_BYTES', MAX_FILE_SIZE_FOTO_PROFIL_MB * 1024 * 1024); }

if (!defined('MAX_FILE_SIZE_SK_KLUB_MB')) { define('MAX_FILE_SIZE_SK_KLUB_MB', 5); } // MB
if (!defined('MAX_FILE_SIZE_SK_KLUB_BYTES')) { define('MAX_FILE_SIZE_SK_KLUB_BYTES', MAX_FILE_SIZE_SK_KLUB_MB * 1024 * 1024); }

if (!defined('MAX_FILE_SIZE_LISENSI_MB')) { define('MAX_FILE_SIZE_LISENSI_MB', 2); } // MB (Untuk pelatih & wasit)
if (!defined('MAX_FILE_SIZE_LISENSI_BYTES')) { define('MAX_FILE_SIZE_LISENSI_BYTES', MAX_FILE_SIZE_LISENSI_MB * 1024 * 1024); }

if (!defined('MAX_FILE_SIZE_FOTO_INDIVIDU_MB')) { define('MAX_FILE_SIZE_FOTO_INDIVIDU_MB', 1); } // MB (Pas foto atlet, foto profil pelatih/wasit jika beda dari pengguna.foto)
if (!defined('MAX_FILE_SIZE_FOTO_INDIVIDU_BYTES')) { define('MAX_FILE_SIZE_FOTO_INDIVIDU_BYTES', MAX_FILE_SIZE_FOTO_INDIVIDU_MB * 1024 * 1024); }

if (!defined('MAX_FILE_SIZE_KTP_KK_MB')) { define('MAX_FILE_SIZE_KTP_KK_MB', 2); } // MB
if (!defined('MAX_FILE_SIZE_KTP_KK_BYTES')) { define('MAX_FILE_SIZE_KTP_KK_BYTES', MAX_FILE_SIZE_KTP_KK_MB * 1024 * 1024); }

if (!defined('MAX_FILE_SIZE_BUKTI_PRESTASI_MB')) { define('MAX_FILE_SIZE_BUKTI_PRESTASI_MB', 2); } // MB
if (!defined('MAX_FILE_SIZE_BUKTI_PRESTASI_BYTES')) { define('MAX_FILE_SIZE_BUKTI_PRESTASI_BYTES', MAX_FILE_SIZE_BUKTI_PRESTASI_MB * 1024 * 1024); }

// Tambahkan konstanta lain jika ada, seperti logo cabor, dll.
if (!defined('MAX_FILE_SIZE_LOGO_MB')) { define('MAX_FILE_SIZE_LOGO_MB', 1); } // MB (Untuk logo cabor, klub)
if (!defined('MAX_FILE_SIZE_LOGO_BYTES')) { define('MAX_FILE_SIZE_LOGO_BYTES', MAX_FILE_SIZE_LOGO_MB * 1024 * 1024); }

if (!defined('MAX_FILE_SIZE_SK_CABOR_MB')) { define('MAX_FILE_SIZE_SK_CABOR_MB', 5); } // MB (Untuk SK Cabor dari Provinsi)
if (!defined('MAX_FILE_SIZE_SK_CABOR_BYTES')) { define('MAX_FILE_SIZE_SK_CABOR_BYTES', MAX_FILE_SIZE_SK_CABOR_MB * 1024 * 1024); }
// ========================================================================
// AKHIR: Konstanta Batas Ukuran File Upload
// ========================================================================


// Pastikan Konstanta Kredensial DB Terdefinisi (dicek setelah include database_credentials.php)
$required_db_consts = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required_db_consts as $const_name) {
    if (!defined($const_name)) {
        $db_const_error = "INIT_CORE_FATAL: Konstanta Database {$const_name} tidak terdefinisi. Pastikan ada di database_credentials.php.";
        error_log($db_const_error);
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            die("Kesalahan Kritis: Konstanta Database {$const_name} tidak terdefinisi.");
        } else {
            die("Kesalahan konfigurasi sistem inti (INIT_DB_CONST_MISSING: {$const_name}). Silakan hubungi administrator.");
        }
    }
}

// Variabel Global untuk Koneksi PDO
global $pdo;
$pdo = null;

// Fungsi untuk Mendapatkan Koneksi Database PDO
if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        global $pdo; // Pastikan $pdo di scope global diakses
        if ($pdo === null) { // Hanya buat koneksi jika belum ada
            try {
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ];
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo_conn = new PDO($dsn, DB_USER, DB_PASS, $options);
                $pdo_conn->exec("SET time_zone = '+07:00'"); // Set zona waktu MySQL untuk sesi ini
                $pdo = $pdo_conn; // Set variabel global $pdo
            } catch (PDOException $e) {
                error_log("INIT_CORE_DB_ERROR (getDbConnection): Koneksi Database Gagal. Pesan -> " . $e->getMessage());
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    die("Koneksi Database Gagal: " . $e->getMessage() . " (Cek database_credentials.php dan status server MySQL)");
                } else {
                    die("Tidak dapat terhubung ke layanan data penting. Sistem tidak dapat melanjutkan. (Error Code: INIT_PDO_CONN_FAILED)");
                }
            }
        }
        return $pdo;
    }
}

// Buat Koneksi Database Utama Sekarang
if ($pdo === null) {
    $pdo = getDbConnection();
}


// Tandai bahwa init_core.php bagian inti sudah dimuat (sebelum memuat helper)
if (!defined('INIT_CORE_LOADED')) {
    define('INIT_CORE_LOADED', true);
}

// Inisialisasi Variabel Sesi Pengguna dan Info Profil Dasar
global $user_nik, $nama_pengguna, $user_role_utama, $id_cabor_pengurus_utama, $user_login_status, $user_created_at_session, $roles_data_session, $default_avatar_path_relative, $user_foto_profil_path_relative_to_app_root;

$user_login_status = (isset($_SESSION['user_login_status']) && $_SESSION['user_login_status'] === true);
$user_nik = $_SESSION['user_nik'] ?? null;
$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Pengguna Tamu';
$user_role_utama = $_SESSION['user_role_utama'] ?? 'guest';
$id_cabor_pengurus_utama = $_SESSION['id_cabor_pengurus_utama'] ?? null;
$user_created_at_session = $_SESSION['user_created_at'] ?? null;
$roles_data_session = $_SESSION['roles_data'] ?? [];

$default_avatar_path_relative = 'assets/adminlte/dist/img/kepitran.jpg'; // Sesuaikan jika path default avatar berbeda
$user_foto_profil_path_relative_to_app_root = $default_avatar_path_relative;

if ($user_login_status === true && isset($_SESSION['user_foto']) && !empty($_SESSION['user_foto'])) {
    $foto_dari_sesi = $_SESSION['user_foto'];
    // APP_PATH_BASE sudah didefinisikan di atas
    $full_foto_path_on_server = APP_PATH_BASE . '/' . ltrim($foto_dari_sesi, '/');
    $full_foto_path_on_server = preg_replace('/\/+/', '/', $full_foto_path_on_server);

    if (file_exists($full_foto_path_on_server) && is_file($full_foto_path_on_server)) {
        $user_foto_profil_path_relative_to_app_root = $foto_dari_sesi;
    }
}

// Fungsi Upload File General
if (!function_exists('uploadFileGeneral')) {
    function uploadFileGeneral($file_input_name, $upload_subdir, $file_prefix, $allowed_extensions, $max_size_bytes, &$errors_array, $old_file_path = null, $is_required_if_empty_old = false) {
        // global $app_base_path; // Dihilangkan karena tidak digunakan untuk path server
        
        if (!defined('APP_PATH_BASE')) {
             $errors_array[] = "Konfigurasi path dasar aplikasi (APP_PATH_BASE) belum diatur untuk fungsi upload.";
             return $old_file_path; // Kembalikan path lama jika konfigurasi dasar tidak ada
        }
        
        $app_root_on_server = APP_PATH_BASE;
        $upload_dir_base_app_relative = 'assets/uploads/'; // Path relatif dari root aplikasi
        $target_dir_app_relative = $upload_dir_base_app_relative . rtrim($upload_subdir, '/\\') . '/';
        $target_dir_server_absolute = rtrim($app_root_on_server, '/') . '/' . ltrim($target_dir_app_relative, '/');
        $target_dir_server_absolute = preg_replace('/\/+/', '/', $target_dir_server_absolute); // Normalisasi path
        
        $db_path_new_file = $old_file_path; // Default ke path lama jika tidak ada upload baru atau gagal

        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK && $_FILES[$file_input_name]['size'] > 0) {
            // Cek dan buat direktori jika belum ada
            if (!file_exists($target_dir_server_absolute)) {
                if (!@mkdir($target_dir_server_absolute, 0755, true)) { // @ untuk menekan warning jika direktori sudah dibuat oleh proses lain
                    $errors_array[] = "Gagal membuat direktori upload: " . htmlspecialchars($target_dir_app_relative);
                    return $old_file_path; // Kembalikan path lama
                }
            }
            // Cek izin tulis
            if (!is_writable($target_dir_server_absolute)) {
                $errors_array[] = "Direktori " . htmlspecialchars($target_dir_app_relative) . " tidak dapat ditulis oleh server.";
                return $old_file_path;
            }

            $original_filename = basename($_FILES[$file_input_name]['name']);
            $tmp_filename = $_FILES[$file_input_name]['tmp_name'];
            $file_size = $_FILES[$file_input_name]['size'];
            $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                if ($file_size <= $max_size_bytes) { // Gunakan $max_size_bytes yang dilewatkan
                    // Hapus file lama jika ada dan file baru berhasil diupload
                    if ($old_file_path && $old_file_path !== $default_avatar_path_relative) { // Tambahan: Jangan hapus default avatar
                        $old_file_server_path_full = rtrim($app_root_on_server, '/') . '/' . ltrim($old_file_path, '/');
                        $old_file_server_path_full = preg_replace('/\/+/', '/', $old_file_server_path_full);
                        if (file_exists($old_file_server_path_full) && is_file($old_file_server_path_full)) {
                            // Penundaan penghapusan file lama sampai file baru berhasil dipindahkan
                        }
                    }
                    
                    $safe_original_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
                    $new_filename_unique_part = time() . "_" . substr(md5(uniqid((string)rand(), true)), 0, 8); // Pastikan rand adalah string
                    $new_filename = $file_prefix . "_" . $safe_original_name . "_" . $new_filename_unique_part . "." . $file_ext;
                    
                    $destination_app_relative_for_db = ltrim($target_dir_app_relative, '/') . $new_filename;
                    $destination_server_absolute = rtrim($target_dir_server_absolute, '/') . '/' . $new_filename;

                    if (move_uploaded_file($tmp_filename, $destination_server_absolute)) {
                        // File baru berhasil dipindahkan, sekarang aman untuk menghapus file lama jika ada
                        if ($old_file_path && $old_file_path !== $default_avatar_path_relative) { // Periksa lagi default avatar
                            $old_file_server_path_full_check = rtrim($app_root_on_server, '/') . '/' . ltrim($old_file_path, '/');
                            $old_file_server_path_full_check = preg_replace('/\/+/', '/', $old_file_server_path_full_check);
                             if (file_exists($old_file_server_path_full_check) && is_file($old_file_server_path_full_check)) {
                                @unlink($old_file_server_path_full_check);
                            }
                        }
                        $db_path_new_file = $destination_app_relative_for_db; // Set path baru untuk DB
                    } else {
                        $php_upload_error = $_FILES[$file_input_name]['error'] ?? 'Tidak diketahui';
                        $errors_array[] = "Gagal memindahkan file terupload '" . htmlspecialchars($original_filename) . "'. Error PHP: " . $php_upload_error;
                        // Tidak mengubah $db_path_new_file, jadi tetap path lama
                    }
                } else {
                    $errors_array[] = htmlspecialchars($original_filename) . ": Ukuran file (" . round($file_size / (1024*1024), 2) . "MB) melebihi batas maksimum (" . round($max_size_bytes/(1024*1024),2) . "MB).";
                }
            } else {
                $errors_array[] = htmlspecialchars($original_filename) . ": Format file (." . $file_ext . ") tidak valid. Hanya " . implode(", ", $allowed_extensions) . " yang diizinkan.";
            }
        } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_OK) {
            $phpFileUploadErrors = [0 => 'There is no error, the file uploaded with success', 1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini', 2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 3 => 'The uploaded file was only partially uploaded', 4 => 'No file was uploaded', 6 => 'Missing a temporary folder', 7 => 'Failed to write file to disk.', 8 => 'A PHP extension stopped the file upload.'];
            $error_code = $_FILES[$file_input_name]['error'];
            $error_message = $phpFileUploadErrors[$error_code] ?? 'Error upload tidak diketahui (Kode PHP: ' . $error_code . ')';
            $errors_array[] = "Error upload " . htmlspecialchars(str_replace('_', ' ', $file_input_name)) . ": " . $error_message;
        } elseif ($is_required_if_empty_old && empty($old_file_path) && (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE || (isset($_FILES[$file_input_name]['size']) && $_FILES[$file_input_name]['size'] == 0) )) {
            $errors_array[] = "File untuk " . htmlspecialchars(str_replace('_', ' ', $file_input_name)) . " wajib diupload.";
        }
        return $db_path_new_file; // Kembalikan path file (baru atau lama)
    }
}


// Logika Timeout Sesi PHP-Side (Pengecekan pada setiap request)
// Dijalankan setelah variabel sesi pengguna diinisialisasi
if ($user_login_status === true) { // Hanya jika pengguna sudah login
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > MAX_SESSION_INACTIVITY_SECONDS)) {
        // Timeout terjadi, lakukan logout
        $nama_pengguna_logout_on_timeout = $_SESSION['nama_pengguna'] ?? 'Pengguna';
        
        // Hapus cookie remember_me jika ada
        if (isset($_COOKIE['remember_me_reaktor'])) {
            setcookie('remember_me_reaktor', '', time() - 3600, $cookie_path_setting, "", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), true);
        }
        
        session_unset();     // Hapus semua variabel sesi
        session_destroy();   // Hancurkan data sesi di server
        
        session_start(); // Mulai sesi baru untuk menyimpan pesan error
        $_SESSION['login_error'] = "Sesi untuk " . htmlspecialchars($nama_pengguna_logout_on_timeout) . " telah berakhir karena tidak ada aktivitas. Silakan login kembali.";
        
        // Redirect ke halaman login
        // Pastikan APP_URL_BASE sudah terdefinisi
        $login_redirect_url = (defined('APP_URL_BASE') ? APP_URL_BASE : '') . '/auth/login.php?reason=inactive_timeout_server';
        header("Location: " . $login_redirect_url);
        exit();
    } else {
        $_SESSION['last_activity'] = time(); // Perbarui waktu aktivitas terakhir jika sesi masih aktif
    }
}

// Muat fungsi untuk Audit Log
if (file_exists(APP_PATH_BASE . '/core/audit_helper.php')) {
    require_once(APP_PATH_BASE . '/core/audit_helper.php');
} else {
    $audit_helper_error_msg_init = "INIT_CORE_WARNING: File audit_helper.php tidak ditemukan atau APP_PATH_BASE tidak terdefinisi. Fungsi audit log tidak akan tersedia.";
    error_log($audit_helper_error_msg_init);
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        // echo "<p style='color:red; background:white; padding:5px; border:1px solid red; position:fixed; top:0; left:0; z-index:9999;'><b>Peringatan init_core:</b> {$audit_helper_error_msg_init}</p>";
    }
}

// JANGAN ADA TAG PHP PENUTUP ATAU SPASI SETELAH BLOK PHP INI