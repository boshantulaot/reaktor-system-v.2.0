<?php
// File: logout.php (atau path yang sesuai)

// 1. Inisialisasi Inti (PENTING untuk koneksi $pdo dan variabel sesi)
// Pastikan init_core.php sudah memulai sesi dan membuat koneksi $pdo
// Jika logout.php Anda berada di root dan init_core.php di /core/, pathnya:
require_once(__DIR__ . '/core/init_core.php'); 
// Jika logout.php berada di dalam folder seperti /auth/ dan init_core.php di /core/, pathnya:
// require_once(__DIR__ . '/../core/init_core.php'); 
// SESUAIKAN PATH DI ATAS DENGAN STRUKTUR DIREKTORI ANDA

// Pastikan $pdo ada dan pengguna memang sedang login
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Jika $pdo tidak ada, ini masalah serius. Redirect saja ke login tanpa logging.
    // Anda bisa tambahkan error_log di sini jika mau.
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    $login_page = isset($app_base_path) ? rtrim($app_base_path, '/') . '/auth/login.php' : 'auth/login.php';
    header("Location: " . $login_page);
    exit();
}

$user_nik_logout = $_SESSION['user_nik'] ?? null; // Ambil NIK sebelum sesi dihancurkan
$nama_pengguna_logout = $_SESSION['nama_pengguna'] ?? 'N/A'; // Ambil nama untuk pesan/log

// 2. Catat Aksi Logout ke Audit Log (JIKA NIK ADA)
if ($user_nik_logout) {
    try {
        $aksi_logout = "LOGOUT";
        $tabel_logout = "pengguna"; // Atau bisa juga 'sistem' jika tidak merujuk ke tabel spesifik
        $id_data_logout = $user_nik_logout; // ID data bisa NIK pengguna yang logout
        $keterangan_logout = "Pengguna '" . htmlspecialchars($nama_pengguna_logout) . "' (NIK: " . htmlspecialchars($user_nik_logout) . ") telah logout.";

        $stmt_log_logout = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, keterangan, waktu_aksi) VALUES (:user_nik, :aksi, :tabel, :id_data, :keterangan, NOW())");
        // Menggunakan NOW() dari MySQL agar konsisten dengan default kolom waktu_aksi jika ada
        // Jika kolom waktu_aksi tidak ada default NOW() atau CURRENT_TIMESTAMP, sediakan dari PHP: date('Y-m-d H:i:s')
        
        $stmt_log_logout->execute([
            ':user_nik' => $user_nik_logout,
            ':aksi' => $aksi_logout,
            ':tabel' => $tabel_logout,
            ':id_data' => $id_data_logout,
            ':keterangan' => $keterangan_logout
        ]);
    } catch (PDOException $e) {
        error_log("Gagal mencatat logout ke audit_log untuk NIK " . $user_nik_logout . ": " . $e->getMessage());
        // Proses logout tetap dilanjutkan meskipun logging gagal
    }
}

// 3. Hapus Variabel Sesi Spesifik Aplikasi
unset($_SESSION['user_nik']);
unset($_SESSION['nama_pengguna']);
unset($_SESSION['user_role_utama']);
unset($_SESSION['id_cabor_pengurus_utama']);
unset($_SESSION['user_login_status']);
unset($_SESSION['user_foto']);
unset($_SESSION['roles_data']);
// Tambahkan unset untuk variabel sesi lain yang Anda buat saat login

// 4. Hapus Cookie "Remember Me" (Jika Ada)
if (isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_validator'])) {
    // Logika untuk menghapus token dari database (jika Anda menyimpan token remember me di DB)
    // ...
    setcookie('remember_selector', '', time() - 3600, $app_base_path ?? '/', "", false, true); // Hapus cookie selector
    setcookie('remember_validator', '', time() - 3600, $app_base_path ?? '/', "", false, true); // Hapus cookie validator
}

// 5. Hancurkan Sesi Sepenuhnya
if (session_status() == PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 6. Arahkan ke Halaman Login dengan Pesan
// Pastikan $app_base_path sudah terdefinisi dari init_core.php
$login_page_url = (isset($app_base_path) ? rtrim($app_base_path, '/') : '.') . '/auth/login.php';

// Gunakan session baru sementara untuk pesan logout karena session lama sudah dihancurkan
session_start(); // Mulai sesi baru untuk pesan
$_SESSION['pesan_logout'] = "Anda telah berhasil logout.";
if (!headers_sent()) {
    header("Location: " . $login_page_url);
} else {
    // Fallback jika header sudah terkirim
    echo "<script>window.location.href='" . $login_page_url . "';</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=" . $login_page_url . "'></noscript>";
    echo "Anda telah logout. Mengarahkan ke halaman login...";
}
exit();
?>