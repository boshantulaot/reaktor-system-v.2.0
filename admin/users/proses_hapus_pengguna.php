<?php
// File: reaktorsystem/admin/users/proses_hapus_pengguna.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/header.php'); // header.php sudah me-require init_core.php

// 2. Pengecekan Akses & Session (Hanya Super Admin yang Boleh Hapus)
if (!isset($user_login_status) || $user_login_status !== true || 
    !isset($user_role_utama) || $user_role_utama !== 'super_admin' || // Hanya Super Admin
    !isset($user_nik) || !isset($app_base_path) || !isset($pdo) || !$pdo instanceof PDO ||
    !defined('APP_PATH_BASE')) { // Pastikan APP_PATH_BASE ada untuk hapus file
    
    $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    $fallback_login_url_hapus = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : rtrim($app_base_path ?? '/', '/')) . "/auth/login.php?reason=invalid_session_hapus";
    if (!headers_sent()) { header("Location: " . $fallback_login_url_hapus); }
    else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($fallback_login_url_hapus, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Error. <a href='" . htmlspecialchars($fallback_login_url_hapus, ENT_QUOTES, 'UTF-8') . "'>Login ulang</a>.</p></noscript>"; }
    exit();
}
$user_nik_pelaku_penghapusan = $user_nik; // NIK Super Admin yang melakukan aksi
$daftar_pengguna_page_redirect_hapus = "daftar_pengguna.php";

// 3. Validasi Input GET atau POST (Lebih aman menggunakan POST untuk aksi destruktif, tapi GET juga umum untuk link)
// Untuk contoh ini, kita gunakan GET, tapi pastikan ada konfirmasi JS yang kuat.
$nik_to_delete = $_GET['nik'] ?? null;

if (empty($nik_to_delete) || !preg_match('/^\d{1,16}$/', $nik_to_delete)) {
    $_SESSION['pesan_error_global'] = "Permintaan tidak valid atau NIK pengguna tidak lengkap untuk dihapus.";
    header("Location: " . $daftar_pengguna_page_redirect_hapus);
    exit();
}

// 4. Validasi Tambahan: Super Admin tidak boleh menghapus dirinya sendiri
if ($nik_to_delete === $user_nik_pelaku_penghapusan) {
    $_SESSION['pesan_error_global'] = "Anda tidak dapat menghapus akun Anda sendiri.";
    header("Location: " . $daftar_pengguna_page_redirect_hapus);
    exit();
}

// 5. Ambil Data Pengguna yang Akan Dihapus (Untuk Logging dan Hapus Foto)
try {
    $stmt_get_user_to_delete = $pdo->prepare("SELECT nik, nama_lengkap, email, foto, is_approved FROM pengguna WHERE nik = :nik");
    $stmt_get_user_to_delete->bindParam(':nik', $nik_to_delete, PDO::PARAM_STR);
    $stmt_get_user_to_delete->execute();
    $user_to_delete_data = $stmt_get_user_to_delete->fetch(PDO::FETCH_ASSOC);

    if (!$user_to_delete_data) {
        $_SESSION['pesan_error_global'] = "Pengguna dengan NIK " . htmlspecialchars($nik_to_delete) . " tidak ditemukan untuk dihapus.";
        header("Location: " . $daftar_pengguna_page_redirect_hapus);
        exit();
    }

    // Optional: Pengecekan apakah pengguna yang akan dihapus adalah Super Admin lain (jika ada kebijakan)
    // $stmt_check_is_sa = $pdo->prepare("SELECT COUNT(*) FROM anggota WHERE nik = :nik_to_check AND role = 'super_admin'");
    // $stmt_check_is_sa->execute([':nik_to_check' => $nik_to_delete]);
    // if ($stmt_check_is_sa->fetchColumn() > 0) {
    //     $_SESSION['pesan_error_global'] = "Akun Super Administrator lain tidak dapat dihapus melalui aksi ini untuk menjaga integritas sistem.";
    //     header("Location: " . $daftar_pengguna_page_redirect_hapus);
    //     exit();
    // }


    // 6. Proses Penghapusan
    $pdo->beginTransaction();

    // Simpan path foto untuk dihapus nanti setelah commit
    $path_foto_to_delete_fisik = $user_to_delete_data['foto'];

    // Hapus dari tabel pengguna (data terkait di atlet, pelatih, wasit, anggota akan ter-cascade jika FK di-setting ON DELETE CASCADE)
    $sql_delete_user = "DELETE FROM pengguna WHERE nik = :nik_to_delete_param";
    $stmt_delete = $pdo->prepare($sql_delete_user);
    $stmt_delete->bindParam(':nik_to_delete_param', $nik_to_delete, PDO::PARAM_STR);
    $stmt_delete->execute();
    $rowCount = $stmt_delete->rowCount();

    if ($rowCount > 0) {
        // 7. Audit Log
        if (function_exists('catatAuditLog')) {
            // Data lama yang akan disimpan di log (password tidak perlu)
            $data_lama_log_hapus = $user_to_delete_data;
            unset($data_lama_log_hapus['password']); // Biasanya password tidak disimpan di log
            
            catatAuditLog(
                $pdo,
                $user_nik_pelaku_penghapusan,
                'HAPUS PENGGUNA',
                'pengguna',
                $nik_to_delete,
                json_encode($data_lama_log_hapus), // Data yang dihapus
                null, // Tidak ada data baru
                'Pengguna: ' . htmlspecialchars($user_to_delete_data['nama_lengkap']) . ' (NIK: ' . htmlspecialchars($nik_to_delete) . ') telah dihapus dari sistem.'
            );
        }

        $pdo->commit();

        // 8. Hapus File Foto Fisik (setelah transaksi DB berhasil)
        if ($path_foto_to_delete_fisik && $path_foto_to_delete_fisik !== $default_avatar_path_relative) { // Jangan hapus foto default
            $full_path_foto_fisik = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($path_foto_to_delete_fisik, '/\\');
            $full_path_foto_fisik = preg_replace('/\/+/', '/', $full_path_foto_fisik);
            if (file_exists($full_path_foto_fisik) && is_file($full_path_foto_fisik)) {
                if (!@unlink($full_path_foto_fisik)) {
                    error_log("Proses Hapus Pengguna - Gagal hapus file foto fisik: " . $full_path_foto_fisik);
                    // Tetap lanjutkan, penghapusan pengguna dari DB lebih penting
                }
            }
        }
        $_SESSION['pesan_sukses_global'] = "Pengguna " . htmlspecialchars($user_to_delete_data['nama_lengkap']) . " (NIK: " . htmlspecialchars($nik_to_delete) . ") dan data terkait berhasil dihapus.";

    } else {
        // Seharusnya tidak terjadi jika pengguna ditemukan di awal, tapi sebagai pengaman
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['pesan_error_global'] = "Gagal menghapus pengguna. Tidak ada baris yang terpengaruh.";
    }

} catch (PDOException $e_delete_process) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Proses Hapus Pengguna Error: " . $e_delete_process->getMessage() . " | NIK: " . $nik_to_delete);
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan teknis saat menghapus akun pengguna.";
}

// 9. Redirect Kembali
if (!headers_sent()) {
    header("Location: " . $daftar_pengguna_page_redirect_hapus);
    exit();
} else {
    echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($daftar_pengguna_page_redirect_hapus, ENT_QUOTES, 'UTF-8') . "';</script>";
    echo "<noscript><p>Proses selesai. Silakan <a href='" . htmlspecialchars($daftar_pengguna_page_redirect_hapus, ENT_QUOTES, 'UTF-8') . "'>kembali ke daftar pengguna</a>.</p></noscript>";
    exit();
}
?>