<?php
// File: public_html/reaktorsystem/modules/cabor/hapus_cabor.php

if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    session_start();
    $_SESSION['pesan_error_global'] = "Kesalahan konfigurasi sistem inti.";
    error_log("HAPUS_CABOR_FATAL: init_core.php tidak ditemukan.");
    // Fallback path jika $app_base_path belum tentu tersedia
    $fallback_url = isset($app_base_path) ? rtrim($app_base_path, '/') . "/dashboard.php" : '../../dashboard.php';
    header("Location: " . $fallback_url);
    exit();
}

// Proteksi Akses Halaman
if (!isset($user_role_utama) || ($user_role_utama != 'super_admin' && $user_role_utama != 'admin_koni')) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk melakukan tindakan ini.";
    header("Location: " . APP_URL_BASE . "/dashboard.php"); // Menggunakan APP_URL_BASE
    exit();
}
if (empty($user_nik)) {
    $_SESSION['pesan_error_global'] = "Sesi pengguna tidak valid atau NIK tidak ditemukan.";
    header("Location: " . APP_URL_BASE . "/auth/login.php");
    exit();
}
$user_nik_pelaku = $user_nik;

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    error_log("HAPUS_CABOR_FATAL: Objek PDO tidak tersedia.");
    header("Location: daftar_cabor.php");
    exit();
}

if (isset($_GET['id_cabor'])) {
    $id_cabor_to_delete = filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT);

    if ($id_cabor_to_delete === false || $id_cabor_to_delete <= 0) {
        $_SESSION['pesan_error_global'] = "ID Cabang Olahraga tidak valid untuk dihapus."; // Diubah ke pesan_error_global
        header("Location: daftar_cabor.php");
        exit();
    }

    try {
        // Ambil data cabor yang akan dihapus (untuk log dan path file)
        $stmt_old = $pdo->prepare("SELECT * FROM cabang_olahraga WHERE id_cabor = :id_cabor");
        $stmt_old->bindParam(':id_cabor', $id_cabor_to_delete, PDO::PARAM_INT);
        $stmt_old->execute();
        $data_lama_cabor_arr = $stmt_old->fetch(PDO::FETCH_ASSOC); // Digunakan untuk $data_lama_json

        if (!$data_lama_cabor_arr) {
            $_SESSION['pesan_error_global'] = "Cabang Olahraga yang akan dihapus tidak ditemukan (ID: {$id_cabor_to_delete})."; // Diubah ke pesan_error_global
            header("Location: daftar_cabor.php");
            exit();
        }
        $data_lama_json = json_encode($data_lama_cabor_arr); // Siapkan data lama untuk log

        // Pengecekan relasi aktif (Kode Anda sudah bagus, dipertahankan)
        $relasi_tables_check = [
            'klub' => 'id_cabor',
            'atlet' => 'id_cabor',
            // 'pelatih' => 'id_cabor', // Dulu, sekarang pelatih terikat lisensi, bukan profil utama
            // 'wasit' => 'id_cabor',   // Dulu, sekarang wasit terikat sertifikasi
            'lisensi_pelatih' => 'id_cabor', // Cek di lisensi pelatih
            'sertifikasi_wasit' => 'id_cabor', // Cek di sertifikasi wasit
            'prestasi' => 'id_cabor', // Jika tabel prestasi punya kolom id_cabor
            // Pertimbangkan anggota jika pengurus cabor harus dikosongkan id_cabornya
        ];
        $ada_relasi_aktif = false;
        $pesan_relasi = [];

        foreach ($relasi_tables_check as $tabel_relasi => $kolom_fk) {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $tabel_relasi) && preg_match('/^[a-zA-Z0-9_]+$/', $kolom_fk)) {
                try {
                    $stmt_cek_relasi = $pdo->prepare("SELECT COUNT(*) FROM `" . $tabel_relasi . "` WHERE `" . $kolom_fk . "` = :id_cabor_cek");
                    $stmt_cek_relasi->bindParam(':id_cabor_cek', $id_cabor_to_delete, PDO::PARAM_INT);
                    $stmt_cek_relasi->execute();
                    if ($stmt_cek_relasi->fetchColumn() > 0) {
                        $ada_relasi_aktif = true;
                        $pesan_relasi[] = ucfirst(str_replace('_', ' ', $tabel_relasi));
                    }
                } catch (PDOException $e_rel) {
                    // Jika tabel tidak ada atau query error, log tapi jangan hentikan proses utama
                    error_log("HAPUS_CABOR_CEK_RELASI_ERROR: Gagal cek relasi untuk tabel {$tabel_relasi}. Pesan: " . $e_rel->getMessage());
                }
            }
        }

        if ($ada_relasi_aktif) {
            $_SESSION['pesan_error_global'] = "Cabang Olahraga '" . htmlspecialchars($data_lama_cabor_arr['nama_cabor']) . "' tidak dapat dihapus karena masih memiliki data terkait di: " . implode(", ", $pesan_relasi) . ". Harap hapus atau pindahkan data terkait terlebih dahulu."; // Diubah ke pesan_error_global
            header("Location: daftar_cabor.php");
            exit();
        }

        $pdo->beginTransaction();

        // 1. Hapus dari tabel cabang_olahraga
        $stmt_delete = $pdo->prepare("DELETE FROM cabang_olahraga WHERE id_cabor = :id_cabor");
        $stmt_delete->bindParam(':id_cabor', $id_cabor_to_delete, PDO::PARAM_INT);
        $berhasil_delete = $stmt_delete->execute();
        $jumlah_baris_terhapus = $stmt_delete->rowCount();

        if ($jumlah_baris_terhapus > 0) { // Pastikan ada baris yang benar-benar terhapus
            // 2. Hapus file fisik (Logo dan SK)
            // APP_PATH_BASE sudah didefinisikan di init_core.php
            if (!empty($data_lama_cabor_arr['logo_cabor']) && defined('APP_PATH_BASE')) {
                $file_logo_to_delete = APP_PATH_BASE . '/' . ltrim($data_lama_cabor_arr['logo_cabor'], '/');
                if (file_exists(preg_replace('/\/+/', '/', $file_logo_to_delete)) && is_file(preg_replace('/\/+/', '/', $file_logo_to_delete))) {
                    @unlink(preg_replace('/\/+/', '/', $file_logo_to_delete));
                }
            }
            if (!empty($data_lama_cabor_arr['path_file_sk_provinsi']) && defined('APP_PATH_BASE')) {
                $file_sk_to_delete = APP_PATH_BASE . '/' . ltrim($data_lama_cabor_arr['path_file_sk_provinsi'], '/');
                if (file_exists(preg_replace('/\/+/', '/', $file_sk_to_delete)) && is_file(preg_replace('/\/+/', '/', $file_sk_to_delete))) {
                    @unlink(preg_replace('/\/+/', '/', $file_sk_to_delete));
                }
            }

            // 3. ** PENYESUAIAN AUDIT LOG **
            if (function_exists('catatAuditLog')) {
                catatAuditLog(
                    $pdo,
                    $user_nik_pelaku,
                    'HAPUS_CABOR',
                    'cabang_olahraga',
                    $id_cabor_to_delete,
                    $data_lama_json, // Data lama yang sudah di-JSON encode sebelumnya
                    null, // Tidak ada data baru untuk aksi hapus
                    "Cabor '" . htmlspecialchars($data_lama_cabor_arr['nama_cabor']) . "' (Kode: " . htmlspecialchars($data_lama_cabor_arr['kode_cabor']) . ") berhasil dihapus."
                );
            }
            // ** AKHIR PENYESUAIAN AUDIT LOG **

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Cabang Olahraga '" . htmlspecialchars($data_lama_cabor_arr['nama_cabor']) . "' berhasil dihapus.";
        } else {
            $pdo->rollBack();
            $_SESSION['pesan_error_global'] = "Gagal menghapus Cabang Olahraga. Data mungkin sudah tidak ada atau tidak ada baris yang terpengaruh.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("HAPUS_CABOR_PDO_ERROR: " . $e->getMessage());
        $db_error_message = (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? " (" . $e->getMessage() . ")" : "";
        $_SESSION['pesan_error_global'] = "Error Database saat menghapus cabor." . $db_error_message;
    }
    header("Location: daftar_cabor.php");
    exit();

} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Cabang Olahraga tidak disediakan untuk dihapus.";
    header("Location: daftar_cabor.php");
    exit();
}
// Hapus tag ?> jika ini akhir file
// Tidak ada tag penutup PHP jika ini adalah file murni PHP.