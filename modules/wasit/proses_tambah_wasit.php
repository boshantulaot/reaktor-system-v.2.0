<?php
// File: reaktorsystem/modules/wasit/proses_tambah_wasit.php

// Inisialisasi inti (termasuk session_start(), koneksi DB, variabel global, fungsi)
require_once(__DIR__ . '/../../core/init_core.php');

// 1. Pengecekan Akses & Sesi Pengguna
if (!isset($user_nik) || !isset($user_role_utama) || 
    !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk menambah data wasit.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Definisi konstanta ukuran file jika belum ada
if (!defined('MAX_FILE_SIZE_KTP_KK_WASIT_MB')) { define('MAX_FILE_SIZE_KTP_KK_WASIT_MB', 2); }
if (!defined('MAX_FILE_SIZE_LISENSI_WASIT_MB')) { define('MAX_FILE_SIZE_LISENSI_WASIT_MB', 2); }
if (!defined('MAX_FILE_SIZE_FOTO_WASIT_MB')) { define('MAX_FILE_SIZE_FOTO_WASIT_MB', 1); }
if (!defined('MAX_FILE_SIZE_KTP_KK_WASIT_BYTES')) { define('MAX_FILE_SIZE_KTP_KK_WASIT_BYTES', MAX_FILE_SIZE_KTP_KK_WASIT_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_LISENSI_WASIT_BYTES')) { define('MAX_FILE_SIZE_LISENSI_WASIT_BYTES', MAX_FILE_SIZE_LISENSI_WASIT_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_FOTO_WASIT_BYTES')) { define('MAX_FILE_SIZE_FOTO_WASIT_BYTES', MAX_FILE_SIZE_FOTO_WASIT_MB * 1024 * 1024); }


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_tambah_wasit'])) { // Cek juga nama tombol submit
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau data tidak dikirim dengan benar.";
    header("Location: tambah_wasit.php");
    exit();
}

// 3. Ambil dan Bersihkan Data Form
$nik_w = trim($_POST['nik'] ?? '');
$id_cabor_w = filter_var($_POST['id_cabor'] ?? '', FILTER_SANITIZE_NUMBER_INT);
$nomor_lisensi_w = isset($_POST['nomor_lisensi']) ? trim($_POST['nomor_lisensi']) : null; // Boleh null
$kontak_wasit_w = isset($_POST['kontak_wasit']) ? trim($_POST['kontak_wasit']) : null;   // Boleh null

$_SESSION['form_data_wasit_tambah'] = $_POST; // Simpan untuk repopulate jika error
$errors_w_tambah = [];
$error_fields_w_tambah = [];

// 4. Validasi Server Side
if (empty($nik_w) || !preg_match('/^\d{16}$/', $nik_w)) { 
    $errors_w_tambah[] = "NIK Wasit wajib diisi dan harus 16 digit angka."; 
    $error_fields_w_tambah[] = 'nik';
} else {
    try {
        $stmt_cek_pengguna_w_t = $pdo->prepare("SELECT nama_lengkap FROM pengguna WHERE nik = :nik AND is_approved = 1");
        $stmt_cek_pengguna_w_t->bindParam(':nik', $nik_w); 
        $stmt_cek_pengguna_w_t->execute();
        $pengguna_wasit_found = $stmt_cek_pengguna_w_t->fetch(PDO::FETCH_ASSOC);
        if (!$pengguna_wasit_found) { 
            $errors_w_tambah[] = "NIK Wasit '" . htmlspecialchars($nik_w) . "' tidak terdaftar sebagai pengguna aktif di sistem. Daftarkan sebagai pengguna terlebih dahulu."; 
            $error_fields_w_tambah[] = 'nik';
        } else {
            $stmt_cek_duplikat_w_t = $pdo->prepare("SELECT COUNT(*) FROM wasit WHERE nik = :nik AND id_cabor = :id_cabor");
            $stmt_cek_duplikat_w_t->bindParam(':nik', $nik_w); 
            $stmt_cek_duplikat_w_t->bindParam(':id_cabor', $id_cabor_w, PDO::PARAM_INT);
            $stmt_cek_duplikat_w_t->execute();
            if ($stmt_cek_duplikat_w_t->fetchColumn() > 0) { 
                $errors_w_tambah[] = "Wasit dengan NIK '" . htmlspecialchars($nik_w) . "' sudah terdaftar di cabang olahraga ini."; 
                $error_fields_w_tambah[] = 'nik';
                $error_fields_w_tambah[] = 'id_cabor';
            }
        }
    } catch (PDOException $e) {
        error_log("Proses Tambah Wasit - Validasi NIK Error DB: " . $e->getMessage());
        $errors_w_tambah[] = "Terjadi kesalahan saat memvalidasi NIK. Silakan coba lagi.";
    }
}
if (empty($id_cabor_w)) { 
    $errors_w_tambah[] = "Cabang Olahraga wajib dipilih."; 
    $error_fields_w_tambah[] = 'id_cabor';
}
// Tambahkan validasi untuk nomor lisensi jika diperlukan (misal, tidak boleh kosong jika file diupload)


// --- Proses Upload File ---
$ktp_path_final_w_t = null;
$kk_path_final_w_t = null;
$lisensi_path_final_w_t = null;
$foto_path_final_w_t = null;
$upload_dir_base_wasit_t = "assets/uploads/wasit/";

// Hanya proses upload jika tidak ada error validasi awal pada field lain
if (empty($errors_w_tambah)) {
    // KTP
    if (isset($_FILES['ktp_path']) && $_FILES['ktp_path']['error'] == UPLOAD_ERR_OK) {
        $ktp_path_final_w_t = uploadFileGeneral('ktp_path', $upload_dir_base_wasit_t . "ktp/", 'ktp_wasit_' . $nik_w, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_KTP_KK_WASIT_MB, $errors_w_tambah, null, false); // false = not required
        if ($ktp_path_final_w_t === false) $error_fields_w_tambah[] = 'ktp_path';
    }
    // KK
    if (isset($_FILES['kk_path']) && $_FILES['kk_path']['error'] == UPLOAD_ERR_OK) {
        $kk_path_final_w_t = uploadFileGeneral('kk_path', $upload_dir_base_wasit_t . "kk/", 'kk_wasit_' . $nik_w, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_KTP_KK_WASIT_MB, $errors_w_tambah, null, false);
        if ($kk_path_final_w_t === false) $error_fields_w_tambah[] = 'kk_path';
    }
    // Lisensi
    if (isset($_FILES['path_file_lisensi']) && $_FILES['path_file_lisensi']['error'] == UPLOAD_ERR_OK) {
        $lisensi_path_final_w_t = uploadFileGeneral('path_file_lisensi', $upload_dir_base_wasit_t . "lisensi/", 'lisensi_wasit_' . $nik_w, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_LISENSI_WASIT_MB, $errors_w_tambah, null, false);
        if ($lisensi_path_final_w_t === false) $error_fields_w_tambah[] = 'path_file_lisensi';
    }
    // Foto Wasit
    if (isset($_FILES['foto_wasit']) && $_FILES['foto_wasit']['error'] == UPLOAD_ERR_OK) {
        $foto_path_final_w_t = uploadFileGeneral('foto_wasit', $upload_dir_base_wasit_t . "foto/", 'foto_wasit_' . $nik_w, ['jpg', 'jpeg', 'png'], MAX_FILE_SIZE_FOTO_WASIT_MB, $errors_w_tambah, null, false);
        if ($foto_path_final_w_t === false) $error_fields_w_tambah[] = 'foto_wasit';
    }
}

if (!empty($errors_w_tambah)) {
    $_SESSION['errors_wasit_tambah'] = $errors_w_tambah;
    $_SESSION['error_fields_wasit_tambah'] = array_unique($error_fields_w_tambah); // Pastikan field unik
    
    // Hapus file yang mungkin sudah terlanjur terupload jika ada error
    if ($ktp_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $ktp_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $ktp_path_final_w_t);
    if ($kk_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $kk_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $kk_path_final_w_t);
    if ($lisensi_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $lisensi_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $lisensi_path_final_w_t);
    if ($foto_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $foto_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $foto_path_final_w_t);

    $redirect_url_w_tambah = 'tambah_wasit.php' . ($id_cabor_w ? '?id_cabor_default=' . $id_cabor_w : '');
    header("Location: " . $redirect_url_w_tambah);
    exit();
}

// --- Logika Status Approval Awal ---
$status_approval_awal_w = 'pending'; 
$approved_by_nik_awal_w = null;
$approval_at_awal_w = null;
$alasan_penolakan_awal_w = null; // Biasanya null saat tambah

if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $status_approval_awal_w = 'disetujui'; 
    $approved_by_nik_awal_w = $user_nik; 
    $approval_at_awal_w = date('Y-m-d H:i:s');
}
// Pengurus Cabor akan selalu 'pending' saat tambah

// --- Insert ke Database ---
try {
    $pdo->beginTransaction();
    $sql_insert_w_db = "INSERT INTO wasit (
                        nik, id_cabor, nomor_lisensi, path_file_lisensi, 
                        kontak_wasit, foto_wasit,
                        ktp_path, kk_path, 
                        status_approval, approved_by_nik, approval_at, alasan_penolakan,
                        updated_by_nik, last_updated_process_at 
                    ) VALUES (
                        :nik, :id_cabor, :nomor_lisensi, :path_lisensi,
                        :kontak, :foto,
                        :ktp_path, :kk_path, 
                        :status_approval, :approved_by, :approval_at, :alasan_tolak,
                        :updated_by, :last_update
                    )";
    $stmt_insert_w_db = $pdo->prepare($sql_insert_w_db);

    $current_timestamp_w = date('Y-m-d H:i:s');

    $stmt_insert_w_db->execute([
        ':nik' => $nik_w,
        ':id_cabor' => $id_cabor_w,
        ':nomor_lisensi' => $nomor_lisensi_w ?: null,
        ':path_lisensi' => $lisensi_path_final_w_t ?: null,
        ':kontak' => $kontak_wasit_w ?: null,
        ':foto' => $foto_path_final_w_t ?: null,
        ':ktp_path' => $ktp_path_final_w_t ?: null,
        ':kk_path' => $kk_path_final_w_t ?: null,
        ':status_approval' => $status_approval_awal_w,
        ':approved_by' => $approved_by_nik_awal_w,
        ':approval_at' => $approval_at_awal_w,
        ':alasan_tolak' => $alasan_penolakan_awal_w,
        ':updated_by' => $user_nik, 
        ':last_update' => $current_timestamp_w
    ]);
    $new_wasit_id_db = $pdo->lastInsertId();

    if ($status_approval_awal_w == 'disetujui') {
        $stmt_update_cabor_w_count = $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = GREATEST(0, jumlah_wasit + 1) WHERE id_cabor = :id_cabor");
        $stmt_update_cabor_w_count->bindParam(':id_cabor', $id_cabor_w, PDO::PARAM_INT);
        $stmt_update_cabor_w_count->execute();
    }

    // Audit Log
    $nama_wasit_for_log = $pengguna_wasit_found['nama_lengkap'] ?? $nik_w;
    $keterangan_log_w_tambah = "Menambahkan Wasit: '" . htmlspecialchars($nama_wasit_for_log) . "' (ID: " . $new_wasit_id_db . ", NIK: " . htmlspecialchars($nik_w) . "). Status awal: " . $status_approval_awal_w . ".";
    
    $data_baru_log_arr_w = [
        'id_wasit' => $new_wasit_id_db, 'nik' => $nik_w, 'id_cabor' => $id_cabor_w, 
        'nomor_lisensi' => $nomor_lisensi_w, 'kontak_wasit' => $kontak_wasit_w, 
        'ktp_path' => $ktp_path_final_w_t, 'kk_path' => $kk_path_final_w_t,
        'path_file_lisensi' => $lisensi_path_final_w_t, 'foto_wasit' => $foto_path_final_w_t,
        'status_approval' => $status_approval_awal_w
    ];

    $log_stmt_w_tambah = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:un, :a, :t, :id, :db, :ket)");
    $log_stmt_w_tambah->execute([
        'un' => $user_nik, 
        'a' => 'TAMBAH WASIT BARU (' . strtoupper(str_replace('_', ' ', $user_role_utama)) . ')', 
        't' => 'wasit', 
        'id' => $new_wasit_id_db, 
        'db' => json_encode($data_baru_log_arr_w),
        'ket' => $keterangan_log_w_tambah
    ]);

    $pdo->commit();

    unset($_SESSION['form_data_wasit_tambah']);
    $_SESSION['pesan_sukses_global'] = "Data Wasit '" . htmlspecialchars($nama_wasit_for_log) . "' berhasil ditambahkan.";
    if ($status_approval_awal_w == 'pending') {
        $_SESSION['pesan_sukses_global'] .= " Pengajuan Anda menunggu approval Admin KONI.";
    }
    header("Location: daftar_wasit.php" . ($id_cabor_w ? '?id_cabor=' . $id_cabor_w : ''));
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Proses Tambah Wasit - Error DB: " . $e->getMessage());

    // Hapus file yang mungkin sudah terlanjur terupload jika ada error DB
    if ($ktp_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $ktp_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $ktp_path_final_w_t);
    if ($kk_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $kk_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $kk_path_final_w_t);
    if ($lisensi_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $lisensi_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $lisensi_path_final_w_t);
    if ($foto_path_final_w_t && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $foto_path_final_w_t)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . $foto_path_final_w_t);
    
    $_SESSION['errors_wasit_tambah'] = ["Error Database saat menyimpan data wasit: Terjadi masalah teknis."];
    if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false && 
        (strpos(strtolower($e->getMessage()), 'nik_id_cabor_unique_wasit') !== false || strpos(strtolower($e->getMessage()), "for key 'wasit.nik_id_cabor_unique_wasit'") !== false )) {
        $_SESSION['errors_wasit_tambah'] = ["Wasit dengan NIK ini sudah terdaftar di cabang olahraga yang sama."];
        $_SESSION['error_fields_wasit_tambah'] = ['nik', 'id_cabor'];
    }
    $redirect_url_w_t_err = 'tambah_wasit.php' . ($id_cabor_w ? '?id_cabor_default=' . $id_cabor_w : '');
    header("Location: " . $redirect_url_w_t_err);
    exit();
}
?>