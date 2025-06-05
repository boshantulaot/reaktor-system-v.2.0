<?php
// File: public_html/reaktorsystem/profile/proses_ubah_password.php

// Aktifkan error reporting di paling atas untuk debugging jika masih ada masalah tak terduga
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../core/init_core.php');

// Pengecekan Akses & Sesi
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik)) {
    // Jika init_core.php sudah mengatur pesan global, itu akan digunakan.
    // Jika tidak, kita set di sini.
    if (session_status() == PHP_SESSION_ACTIVE && !isset($_SESSION['pesan_error_global'])) {
        $_SESSION['pesan_error_global'] = "Akses ditolak. Silakan login terlebih dahulu.";
    }
    $login_path = isset($app_base_path) ? rtrim($app_base_path, '/') . '/auth/login.php' : '../auth/login.php';
    if (!headers_sent()) { header("Location: " . $login_path); }
    exit();
}

// Pastikan $pdo sudah terdefinisi
if (!isset($pdo) || !$pdo instanceof PDO) {
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION['pesan_error_profil_password'] = "Koneksi Database Gagal saat akan ubah password!";
        $_SESSION['last_profil_tab'] = 'ubahpassword'; // Untuk kembali ke tab yang benar
    }
    $profil_path = isset($app_base_path) ? rtrim($app_base_path, '/') . '/profile/profil_saya.php#ubahpassword' : 'profil_saya.php#ubahpassword';
    if (!headers_sent()) { header("Location: " . $profil_path); }
    exit();
}

$redirect_url = (isset($app_base_path) ? rtrim($app_base_path, '/') : '.') . '/profile/profil_saya.php#ubahpassword';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ubah_password']) && isset($_POST['nik_ubah_pass'])) {
    $nik_target = trim($_POST['nik_ubah_pass']);
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password_baru = $_POST['konfirmasi_password_baru'] ?? '';
    
    $errors_pass = [];

    if ($nik_target !== $user_nik) {
        $_SESSION['pesan_error_profil_password'] = "Operasi tidak diizinkan (percobaan ubah password pengguna lain).";
        $_SESSION['last_profil_tab'] = 'ubahpassword';
        if (!headers_sent()) { header("Location: " . $redirect_url); }
        exit();
    }

    if (empty($password_lama)) { $errors_pass[] = "Password lama wajib diisi."; }
    if (empty($password_baru)) { $errors_pass[] = "Password baru wajib diisi."; }
    else if (strlen($password_baru) < (defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 6) ) {
        $errors_pass[] = "Password baru minimal harus ".(defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 6)." karakter.";
    }
    if (empty($konfirmasi_password_baru)) { $errors_pass[] = "Konfirmasi password baru wajib diisi."; }
    if (!empty($password_baru) && $password_baru !== $konfirmasi_password_baru) {
        $errors_pass[] = "Password baru dan konfirmasi password tidak cocok.";
    }
    
    if (!empty($errors_pass)) {
        $_SESSION['pesan_error_profil_password'] = implode("<br>", array_map('htmlspecialchars', $errors_pass));
        $_SESSION['last_profil_tab'] = 'ubahpassword';
        if (!headers_sent()) { header("Location: " . $redirect_url); }
        exit();
    }

    try {
        $stmt_cek_pass = $pdo->prepare("SELECT password FROM pengguna WHERE nik = :nik");
        $stmt_cek_pass->bindParam(':nik', $nik_target, PDO::PARAM_STR);
        $stmt_cek_pass->execute();
        $user_data_pass_db = $stmt_cek_pass->fetch(PDO::FETCH_ASSOC);

        if (!$user_data_pass_db) {
            $_SESSION['pesan_error_profil_password'] = "Pengguna tidak ditemukan untuk melakukan perubahan password.";
            $_SESSION['last_profil_tab'] = 'ubahpassword';
            if (!headers_sent()) { header("Location: " . $redirect_url); }
            exit();
        }

        if (password_verify($password_lama, $user_data_pass_db['password'])) {
            $hash_password_baru_db = password_hash($password_baru, PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();
            
            $stmt_update_pass_db = $pdo->prepare("UPDATE pengguna SET password = :password_baru, updated_at = NOW() WHERE nik = :nik");
            $stmt_update_pass_db->bindParam(':password_baru', $hash_password_baru_db);
            $stmt_update_pass_db->bindParam(':nik', $nik_target, PDO::PARAM_STR);
            $stmt_update_pass_db->execute();

            $log_keterangan_pass = "Pengguna (NIK: " . htmlspecialchars($nik_target) . ") mengubah passwordnya.";
            $log_stmt_pass = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, keterangan, data_baru, waktu_aksi) VALUES (:un, :a, :t, :id, :ket, :db, NOW())");
            $log_stmt_pass->execute([
                ':un' => $user_nik, 
                ':a' => 'UBAH PASSWORD PRIBADI',
                ':t' => 'pengguna', 
                ':id' => $nik_target,
                ':ket' => $log_keterangan_pass,
                ':db' => json_encode(['status_perubahan' => 'Password diubah oleh pengguna'])
            ]);
            
            $pdo->commit(); 
            
            $_SESSION['pesan_sukses_profil_password'] = "Password berhasil diubah.";
            $_SESSION['last_profil_tab'] = 'ubahpassword';
            if (!headers_sent()) { header("Location: " . $redirect_url); }
            exit();

        } else {
            $_SESSION['pesan_error_profil_password'] = "Password lama yang Anda masukkan salah.";
            $_SESSION['last_profil_tab'] = 'ubahpassword';
            if (!headers_sent()) { header("Location: " . $redirect_url); }
            exit();
        }
    } catch (PDOException $e) {
        if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("PROSES_UBAH_PASSWORD_ERROR: NIK {$nik_target}. Pesan DB: " . $e->getMessage());
        $_SESSION['pesan_error_profil_password'] = "Terjadi kesalahan internal saat mencoba mengubah password. Silakan coba lagi.";
        $_SESSION['last_profil_tab'] = 'ubahpassword';
        if (!headers_sent()) { header("Location: " . $redirect_url); }
        exit();
    }
} else {
    // Jika bukan POST atau data POST tidak lengkap
    $_SESSION['pesan_error_profil_password'] = "Aksi tidak valid atau data tidak lengkap untuk proses ubah password.";
    $_SESSION['last_profil_tab'] = 'ubahpassword';
    if (!headers_sent()) { header("Location: " . $redirect_url); }
    exit();
}
?>