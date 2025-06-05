<?php
// File: reaktorsystem/admin/users/proses_tambah_pengguna.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/header.php'); // header.php sudah me-require init_core.php

// 2. Pengecekan Akses & Session
// Pastikan variabel dari init_core.php sudah tersedia
if (!isset($user_login_status) || $user_login_status !== true || 
    !isset($user_role_utama) || !in_array($user_role_utama, ['super_admin', 'admin_koni']) ||
    !isset($user_nik) || !isset($app_base_path) || !isset($pdo) || !$pdo instanceof PDO ||
    !defined('MAX_FILE_SIZE_FOTO_PROFIL_BYTES') || !isset($default_avatar_path_relative)) { // Tambahan pengecekan konstanta/variabel penting
    
    if (!isset($_SESSION['pesan_error_global'])) {
        $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    }
    $fallback_login_url_proses = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : rtrim($app_base_path ?? '/', '/')) . "/auth/login.php?reason=invalid_session_or_config_ptp";
    if (!headers_sent()) {
        header("Location: " . $fallback_login_url_proses);
    } else {
        echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($fallback_login_url_proses, ENT_QUOTES, 'UTF-8') . "';</script>";
        echo "<noscript><p>Error: Sesi tidak valid atau konfigurasi inti bermasalah. Silakan <a href='" . htmlspecialchars($fallback_login_url_proses, ENT_QUOTES, 'UTF-8') . "'>login ulang</a>.</p></noscript>";
    }
    exit();
}
$user_nik_pelaku_proses = $user_nik;

// Tentukan URL dasar untuk redirect form jika ada error validasi atau akses tidak valid
$form_page_url = "form_tambah_pengguna.php"; 


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_tambah_pengguna'])) {
    
    // 3. Ambil dan Bersihkan Data Input
    $nik_input = trim($_POST['nik'] ?? '');
    $nama_lengkap_input = trim($_POST['nama_lengkap'] ?? '');
    $email_input = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $password_plain_input = $_POST['password'] ?? '';
    $konfirmasi_password_input = $_POST['konfirmasi_password'] ?? '';
    $tanggal_lahir_input = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin_input = trim($_POST['jenis_kelamin'] ?? '');
    $alamat_input = trim($_POST['alamat'] ?? '');
    $nomor_telepon_input = trim($_POST['nomor_telepon'] ?? '');
    
    $_SESSION['form_data_pengguna_tambah'] = $_POST;
    $errors_pengguna = [];
    $error_fields_pengguna = [];

    // 4. Validasi Server-Side
    if (empty($nik_input) || !preg_match('/^\d{16}$/', $nik_input)) { $errors_pengguna[] = "NIK wajib diisi dan harus 16 digit angka."; $error_fields_pengguna[] = 'nik'; }
    if (empty($nama_lengkap_input)) { $errors_pengguna[] = "Nama Lengkap wajib diisi."; $error_fields_pengguna[] = 'nama_lengkap'; }
    if (empty($email_input) || !filter_var($email_input, FILTER_VALIDATE_EMAIL)) { $errors_pengguna[] = "Email wajib diisi dengan format yang valid."; $error_fields_pengguna[] = 'email'; }
    if (empty($password_plain_input)) { $errors_pengguna[] = "Password wajib diisi."; $error_fields_pengguna[] = 'password'; } 
    elseif (strlen($password_plain_input) < 6) { $errors_pengguna[] = "Password minimal 6 karakter."; $error_fields_pengguna[] = 'password'; }
    if ($password_plain_input !== $konfirmasi_password_input) { $errors_pengguna[] = "Password dan Konfirmasi Password tidak cocok."; $error_fields_pengguna[] = 'konfirmasi_password'; $error_fields_pengguna[] = 'password';}
    
    $tanggal_lahir_db = null;
    if (!empty($tanggal_lahir_input)) {
        $d_val = DateTime::createFromFormat('Y-m-d', $tanggal_lahir_input);
        if ($d_val && $d_val->format('Y-m-d') === $tanggal_lahir_input) { $tanggal_lahir_db = $tanggal_lahir_input; } 
        else { $errors_pengguna[] = "Format Tanggal Lahir tidak valid (YYYY-MM-DD)."; $error_fields_pengguna[] = 'tanggal_lahir'; }
    }
    $jenis_kelamin_db = null;
    if (!empty($jenis_kelamin_input)) {
        if (in_array($jenis_kelamin_input, ['Laki-laki', 'Perempuan'])) { $jenis_kelamin_db = $jenis_kelamin_input; } 
        else { $errors_pengguna[] = "Jenis Kelamin tidak valid."; $error_fields_pengguna[] = 'jenis_kelamin'; }
    }
    if (!empty($nomor_telepon_input) && !preg_match('/^[0-9\-\+\s\(\).#\*]{7,20}$/', $nomor_telepon_input)) { $errors_pengguna[] = "Format Nomor Telepon tidak valid."; $error_fields_pengguna[] = 'nomor_telepon'; }

    if (empty($errors_pengguna)) {
        try {
            $stmt_cek_nik_dup = $pdo->prepare("SELECT nik FROM pengguna WHERE nik = :nik_val");
            $stmt_cek_nik_dup->execute([':nik_val' => $nik_input]);
            if ($stmt_cek_nik_dup->fetch()) { $errors_pengguna[] = "NIK '" . htmlspecialchars($nik_input) . "' sudah terdaftar."; $error_fields_pengguna[] = 'nik'; }
            
            $stmt_cek_email_dup = $pdo->prepare("SELECT email FROM pengguna WHERE email = :email_val");
            $stmt_cek_email_dup->execute([':email_val' => $email_input]);
            if ($stmt_cek_email_dup->fetch()) { $errors_pengguna[] = "Email '" . htmlspecialchars($email_input) . "' sudah terdaftar."; $error_fields_pengguna[] = 'email'; }
        } catch (PDOException $e_dup_check) {
            error_log("Proses Tambah Pengguna - DB Cek Duplikat Error: " . $e_dup_check->getMessage());
            $errors_pengguna[] = "Terjadi kesalahan saat memvalidasi keunikan data pengguna.";
        }
    }
    
    // 5. Penanganan File Upload Foto
    $foto_db_path_pengguna_final = null; // Path yang akan disimpan ke DB
    $temp_foto_path_diupload = null;     // Path file yang baru diupload (jika ada, untuk dihapus jika DB gagal)
    
    if (empty($errors_pengguna)) { 
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK && $_FILES['foto']['size'] > 0) {
            // Pengguna mengunggah foto
            $path_hasil_upload_new = uploadFileGeneral(
                'foto',                           
                'foto_profil',                    
                'user_' . $nik_input,             
                ['jpg', 'jpeg', 'png', 'gif'],    
                MAX_FILE_SIZE_FOTO_PROFIL_BYTES, // Gunakan konstanta Bytes dari init_core.php                  
                $errors_pengguna                  
            );
            
            if ($path_hasil_upload_new !== null) {
                $foto_db_path_pengguna_final = $path_hasil_upload_new;
                $temp_foto_path_diupload = $path_hasil_upload_new; 
            } else {
                if (!empty($_FILES['foto']['name'])) { 
                     $error_fields_pengguna[] = 'foto';
                }
            }
        }
        
        // Jika tidak ada foto diupload atau upload gagal TAPI tidak ada error validasi lain, set ke default
        if ($foto_db_path_pengguna_final === null && empty($errors_pengguna)) {
            // $default_avatar_path_relative sudah didefinisikan di init_core.php (via header.php)
            $foto_db_path_pengguna_final = $default_avatar_path_relative; 
        }
    }

    // 6. Jika Ada Error Validasi (termasuk error upload), Kembali ke Form
    if (!empty($errors_pengguna)) {
        $_SESSION['errors_pengguna_tambah'] = $errors_pengguna;
        $_SESSION['error_fields_pengguna_tambah'] = array_unique($error_fields_pengguna); 
        
        // Hanya hapus file jika itu adalah file yang baru diupload (bukan path default)
        if ($temp_foto_path_diupload && $temp_foto_path_diupload !== $default_avatar_path_relative && defined('APP_PATH_BASE')) {
            $full_path_to_delete_on_validation_error = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_foto_path_diupload, '/\\');
            if (file_exists($full_path_to_delete_on_validation_error)) { @unlink($full_path_to_delete_on_validation_error); }
        }
        
        if (!headers_sent()) {
            header("Location: " . $form_page_url);
            exit();
        } else {
            echo "<script type='text/javascript'>window.location.href='" . htmlspecialchars($form_page_url, ENT_QUOTES, 'UTF-8') . "';</script>";
            echo "<noscript><p>Terjadi error validasi. Silakan <a href='" . htmlspecialchars($form_page_url, ENT_QUOTES, 'UTF-8') . "'>kembali ke form</a> dan perbaiki.</p></noscript>";
            exit();
        }
    }

    // 7. Proses Simpan ke Database
    try {
        $pdo->beginTransaction();
        $password_hashed_db_final = password_hash($password_plain_input, PASSWORD_DEFAULT);
        $is_approved_db_final = 0;

        $sql_insert_final = "INSERT INTO pengguna (nik, nama_lengkap, email, password, tanggal_lahir, jenis_kelamin, alamat, nomor_telepon, foto, is_approved, created_at, updated_at) VALUES (:nik, :nama_lengkap, :email, :password, :tanggal_lahir, :jenis_kelamin, :alamat, :nomor_telepon, :foto, :is_approved, NOW(), NOW())";
        $stmt_insert_final_exec = $pdo->prepare($sql_insert_final);

        $stmt_insert_final_exec->bindParam(':nik', $nik_input);
        $stmt_insert_final_exec->bindParam(':nama_lengkap', $nama_lengkap_input);
        $stmt_insert_final_exec->bindParam(':email', $email_input);
        $stmt_insert_final_exec->bindParam(':password', $password_hashed_db_final);
        $stmt_insert_final_exec->bindParam(':tanggal_lahir', $tanggal_lahir_db, $tanggal_lahir_db === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_final_exec->bindParam(':jenis_kelamin', $jenis_kelamin_db, $jenis_kelamin_db === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $alamat_db_final_val = empty($alamat_input) ? null : $alamat_input;
        $nomor_telepon_db_final_val = empty($nomor_telepon_input) ? null : $nomor_telepon_input;
        $stmt_insert_final_exec->bindParam(':alamat', $alamat_db_final_val, $alamat_db_final_val === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_final_exec->bindParam(':nomor_telepon', $nomor_telepon_db_final_val, $nomor_telepon_db_final_val === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        // $foto_db_path_pengguna_val akan berisi path upload atau path default
        $stmt_insert_final_exec->bindParam(':foto', $foto_db_path_pengguna_final, PDO::PARAM_STR); 
        $stmt_insert_final_exec->bindParam(':is_approved', $is_approved_db_final, PDO::PARAM_INT);
        $stmt_insert_final_exec->execute();

        if (function_exists('catatAuditLog')) {
            $data_baru_audit = ['nik' => $nik_input, 'nama_lengkap' => $nama_lengkap_input, 'email' => $email_input, 'is_approved_awal' => $is_approved_db_final, 'foto_tersimpan' => $foto_db_path_pengguna_final];
            // Tambahkan field opsional ke log jika ada nilainya
            if ($tanggal_lahir_db) $data_baru_audit['tanggal_lahir'] = $tanggal_lahir_db;
            if ($jenis_kelamin_db) $data_baru_audit['jenis_kelamin'] = $jenis_kelamin_db;
            if ($alamat_db_final_val) $data_baru_audit['alamat'] = $alamat_db_final_val;
            if ($nomor_telepon_db_final_val) $data_baru_audit['nomor_telepon'] = $nomor_telepon_db_final_val;
            
            catatAuditLog($pdo, $user_nik_pelaku_proses, 'TAMBAH PENGGUNA BARU (PENDING)', 'pengguna', $nik_input, null, json_encode($data_baru_audit), 'Pengguna baru: ' . htmlspecialchars($nama_lengkap_input) . ' (NIK: ' . htmlspecialchars($nik_input) . ') menunggu persetujuan.');
        }
        $pdo->commit();
        unset($_SESSION['form_data_pengguna_tambah']);
        $_SESSION['pesan_sukses_global'] = "Pengguna '" . htmlspecialchars($nama_lengkap_input) . "' (NIK: " . htmlspecialchars($nik_input) . ") berhasil ditambahkan dan menunggu persetujuan.";
        
        if (!headers_sent()) {
            header("Location: daftar_pengguna.php");
            exit();
        } else {
            echo "<script type='text/javascript'>window.location.href = 'daftar_pengguna.php';</script>";
            echo "<noscript><p>Pengguna berhasil ditambahkan. Silakan <a href='daftar_pengguna.php'>klik di sini</a>.</p></noscript>";
            exit(); 
        }

    } catch (PDOException $e_db_insert) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Tambah Pengguna - DB Execute Insert Error: " . $e_db_insert->getMessage() . " | NIK: " . $nik_input);
        
        if ($temp_foto_path_diupload && $temp_foto_path_diupload !== $default_avatar_path_relative && defined('APP_PATH_BASE')) {
            $full_path_to_delete_db_err = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_foto_path_diupload, '/\\');
            if (file_exists($full_path_to_delete_db_err)) { @unlink($full_path_to_delete_db_err); }
        }
        $_SESSION['errors_pengguna_tambah'] = ["Terjadi kesalahan teknis saat menyimpan data. Silakan coba lagi."];
        
        if (!headers_sent()) {
            header("Location: " . $form_page_url);
            exit();
        } else {
            echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($form_page_url, ENT_QUOTES, 'UTF-8') . "';</script>";
            echo "<noscript><p>Gagal menyimpan data. Silakan <a href='" . htmlspecialchars($form_page_url, ENT_QUOTES, 'UTF-8') . "'>coba lagi</a>.</p></noscript>";
            exit();
        }
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid.";
    $form_redirect_url_invalid_req = "form_tambah_pengguna.php";
    if(isset($app_base_path)) {
        // $form_redirect_url_invalid_req = rtrim($app_base_path, '/') . "/admin/users/form_tambah_pengguna.php";
    }
    if (!headers_sent()) {
        header("Location: " . $form_redirect_url_invalid_req);
        exit();
    } else {
         echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($form_redirect_url_invalid_req, ENT_QUOTES, 'UTF-8') . "';</script>";
         echo "<noscript><p>Permintaan tidak valid. Kembali ke <a href='" . htmlspecialchars($form_redirect_url_invalid_req, ENT_QUOTES, 'UTF-8') . "'>form</a>.</p></noscript>";
        exit();
    }
}
?>