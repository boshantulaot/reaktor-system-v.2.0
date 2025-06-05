<?php
// File: modules/pelatih/hapus_pelatih.php (REVISI TOTAL)

if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $_SESSION['pesan_error_global'] = "Kesalahan konfigurasi sistem inti: init_core.php tidak ditemukan.";
    error_log("PROSES_HAPUS_PROFIL_PELATIH_FATAL: init_core.php tidak ditemukan.");
    $fallback_redirect_h_pp = isset($app_base_path) ? rtrim($app_base_path, '/') . '/dashboard.php' : '../../dashboard.php';
    header("Location: " . $fallback_redirect_h_pp);
    exit();
}

$redirect_list_profil_pelatih = rtrim($app_base_path, '/') . "/modules/pelatih/daftar_pelatih.php";

// Pengecekan Akses & Sesi (Hanya Super Admin yang boleh hapus profil pelatih)
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik) ||
    $user_role_utama !== 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk menghapus profil pelatih.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: " . $redirect_list_profil_pelatih);
    exit();
}

if (isset($_GET['id_pelatih']) && filter_var($_GET['id_pelatih'], FILTER_VALIDATE_INT)) {
    $id_pelatih_to_delete = (int)$_GET['id_pelatih'];

    if ($id_pelatih_to_delete <= 0) {
        $_SESSION['pesan_error_global'] = "ID Profil Pelatih tidak valid untuk dihapus.";
        header("Location: " . $redirect_list_profil_pelatih);
        exit();
    }
    
    // Ambil data profil pelatih lama untuk audit log dan path foto
    $stmt_old_pp_data_del = $pdo->prepare("SELECT plt.nik, plt.foto_pelatih_profil, p.nama_lengkap 
                                          FROM pelatih plt 
                                          JOIN pengguna p ON plt.nik = p.nik 
                                          WHERE plt.id_pelatih = :id_pelatih");
    $stmt_old_pp_data_del->bindParam(':id_pelatih', $id_pelatih_to_delete, PDO::PARAM_INT);
    $stmt_old_pp_data_del->execute();
    $data_lama_profil_pelatih_del = $stmt_old_pp_data_del->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_profil_pelatih_del) {
        $_SESSION['pesan_error_global'] = "Profil Pelatih yang akan dihapus tidak ditemukan.";
        header("Location: " . $redirect_list_profil_pelatih);
        exit();
    }
    $nama_pelatih_dihapus = $data_lama_profil_pelatih_del['nama_lengkap'] ?? ('NIK ' . $data_lama_profil_pelatih_del['nik']);

    try {
        $pdo->beginTransaction();

        // Data untuk audit log (sebelum record dihapus)
        $data_lama_json_for_audit_pp = json_encode($data_lama_profil_pelatih_del);

        // Lakukan penghapusan dari tabel pelatih
        // Karena ada ON DELETE CASCADE pada foreign key lisensi_pelatih.id_pelatih,
        // semua lisensi terkait akan otomatis terhapus juga.
        $stmt_delete_pp_db = $pdo->prepare("DELETE FROM pelatih WHERE id_pelatih = :id_pelatih");
        $stmt_delete_pp_db->bindParam(':id_pelatih', $id_pelatih_to_delete, PDO::PARAM_INT);
        $stmt_delete_pp_db->execute();

        if ($stmt_delete_pp_db->rowCount() > 0) {
            // Hapus foto profil khusus pelatih dari server (jika ada)
            $foto_profil_to_delete = $data_lama_profil_pelatih_del['foto_pelatih_profil'] ?? null;
            if (!empty($foto_profil_to_delete)) {
                $server_root_path_unlink_pp = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/');
                $full_path_foto_pp = $server_root_path_unlink_pp . '/' . ltrim($foto_profil_to_delete, '/');
                $full_path_foto_pp = preg_replace('/\/+/', '/', $full_path_foto_pp);
                if (file_exists($full_path_foto_pp)) {
                    @unlink($full_path_foto_pp);
                }
            }

            // Audit Log
            if (function_exists('catatAuditLog')) {
                catatAuditLog(
                    $user_nik, 
                    'HAPUS PROFIL PELATIH', 
                    'pelatih', 
                    $id_pelatih_to_delete, 
                    $data_lama_json_for_audit_pp, 
                    null, 
                    'Menghapus profil pelatih: ' . $nama_pelatih_dihapus . ' (ID Profil: ' . $id_pelatih_to_delete . ', NIK: ' . $data_lama_profil_pelatih_del['nik'] . '). Semua lisensi terkait juga terhapus (CASCADE).',
                    $pdo
                );
            } else {
                // Fallback manual audit log
                $log_stmt_h_pp = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:user_nik, 'HAPUS PROFIL PELATIH', 'pelatih', :id_data, :data_lama, :keterangan)");
                $log_stmt_h_pp->execute([
                    ':user_nik' => $user_nik,
                    ':id_data' => $id_pelatih_to_delete,
                    ':data_lama' => $data_lama_json_for_audit_pp,
                    ':keterangan' => 'Menghapus profil pelatih: ' . $nama_pelatih_dihapus . ' (ID Profil: ' . $id_pelatih_to_delete . ', NIK: ' . $data_lama_profil_pelatih_del['nik'] . '). Semua lisensi terkait juga terhapus (CASCADE).'
                ]);
            }

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Profil Pelatih '" . htmlspecialchars($nama_pelatih_dihapus) . "' dan semua lisensi terkait berhasil dihapus.";
        } else {
            $pdo->rollBack();
            $_SESSION['pesan_error_global'] = "Gagal menghapus profil Pelatih. Data mungkin sudah dihapus.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("PROSES_HAPUS_PROFIL_PELATIH_ERROR: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan database saat menghapus profil pelatih.";
    }
    header("Location: " . $redirect_list_profil_pelatih);
    exit();

} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Profil Pelatih tidak disediakan untuk dihapus.";
    header("Location: " . $redirect_list_profil_pelatih);
    exit();
}
// Tidak ada require_once footer.php
?>