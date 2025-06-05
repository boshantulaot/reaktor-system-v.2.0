<?php
// File: reaktorsystem/admin/roles/hapus_anggota.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
if ($user_login_status !== true || $user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Operasi ini hanya untuk Super Admin.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_proses = $user_nik; // NIK Super Admin yang melakukan aksi

// Pastikan PDO tersedia
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: daftar_anggota.php");
    exit();
}

// 3. Validasi Parameter GET
$id_anggota_to_delete_proc = $_GET['id_anggota'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !$id_anggota_to_delete_proc || !filter_var($id_anggota_to_delete_proc, FILTER_VALIDATE_INT) || (int)$id_anggota_to_delete_proc <= 0) {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Peran Anggota tidak disediakan dengan benar.";
    header("Location: daftar_anggota.php");
    exit();
}
$id_anggota_to_delete_proc = (int)$id_anggota_to_delete_proc; // Pastikan integer

// 4. Ambil Data Anggota Lama (untuk audit dan validasi)
try {
    $stmt_old_anggota = $pdo->prepare("
        SELECT ang.id_anggota, ang.nik, ang.jabatan, ang.role, ang.id_cabor, p.nama_lengkap 
        FROM anggota ang
        JOIN pengguna p ON ang.nik = p.nik
        WHERE ang.id_anggota = :id_anggota
    ");
    $stmt_old_anggota->bindParam(':id_anggota', $id_anggota_to_delete_proc, PDO::PARAM_INT);
    $stmt_old_anggota->execute();
    $data_lama_anggota_db = $stmt_old_anggota->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_anggota_db) {
        $_SESSION['pesan_error_global'] = "Peran Anggota dengan ID " . htmlspecialchars($id_anggota_to_delete_proc) . " tidak ditemukan.";
        header("Location: daftar_anggota.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Hapus Anggota - Gagal ambil data lama: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data peran anggota.";
    header("Location: daftar_anggota.php");
    exit();
}

// 5. Pencegahan Penghapusan Peran Super Admin Utama
// Asumsikan NIK_SUPER_ADMIN_UTAMA didefinisikan di init_core.php atau konfigurasi
if (defined('NIK_SUPER_ADMIN_UTAMA') && $data_lama_anggota_db['nik'] == NIK_SUPER_ADMIN_UTAMA && $data_lama_anggota_db['role'] == 'super_admin') {
    $_SESSION['pesan_error_global'] = "Peran Super Admin utama tidak dapat dihapus dari sistem.";
    header("Location: daftar_anggota.php");
    exit();
}

// 6. Proses Penghapusan dengan Transaksi
try {
    $pdo->beginTransaction();

    // Hapus record dari tabel anggota
    $stmt_delete_anggota = $pdo->prepare("DELETE FROM anggota WHERE id_anggota = :id_anggota");
    $stmt_delete_anggota->bindParam(':id_anggota', $id_anggota_to_delete_proc, PDO::PARAM_INT);
    $stmt_delete_anggota->execute();

    if ($stmt_delete_anggota->rowCount() > 0) {
        // 7. Audit Log
        $keterangan_log_hapus = "Menghapus peran: '" . htmlspecialchars($data_lama_anggota_db['jabatan']) . 
                                "' (Role: " . htmlspecialchars($data_lama_anggota_db['role']) . ") " .
                                "untuk pengguna: " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . 
                                " (NIK: " . htmlspecialchars($data_lama_anggota_db['nik']) . "). ID Anggota: " . $id_anggota_to_delete_proc;
        
        $log_stmt_hapus_agt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_lama, :keterangan)");
        $log_stmt_hapus_agt->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => 'HAPUS PERAN ANGGOTA',
            ':tabel' => 'anggota',
            ':id_data' => $id_anggota_to_delete_proc,
            ':data_lama' => json_encode($data_lama_anggota_db), // Simpan data yang dihapus
            ':keterangan' => $keterangan_log_hapus
        ]);

        $pdo->commit();
        $_SESSION['pesan_sukses_global'] = "Peran '" . htmlspecialchars($data_lama_anggota_db['jabatan']) . "' untuk pengguna " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . " berhasil dihapus.";
    } else {
        $pdo->rollBack();
        $_SESSION['pesan_error_global'] = "Gagal menghapus peran anggota. Data mungkin sudah dihapus atau ID tidak valid.";
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Hapus Anggota - DB Execute Error: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Error Database saat menghapus peran anggota. Pesan: " . htmlspecialchars($e->getMessage());
}

// 8. Redirect
header("Location: daftar_anggota.php");
exit();
?>