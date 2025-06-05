<?php
// File: modules/lisensi_pelatih/proses_tambah_lisensi_pelatih.php

require_once(__DIR__ . '/../../core/init_core.php');

// Pastikan konstanta sudah terdefinisi (bisa juga di init_core.php)
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB')) { define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB', 2); }
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES')) { define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES', MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB * 1024 * 1024); }

$redirect_form = 'tambah_lisensi_pelatih.php'; // Halaman form tambah
$redirect_list = 'daftar_lisensi_pelatih.php'; // Halaman daftar

// 1. Pengecekan Sesi & Hak Akses
if (!isset($user_nik, $user_role_utama, $app_base_path, $pdo)) {
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah.";
    header("Location: " . ($app_base_path ?? '.') . "/auth/login.php");
    exit();
}

$can_add_for_others_proses = in_array($user_role_utama, ['super_admin', 'admin_koni']);
$is_pengurus_cabor_proses = ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama));
$is_pelatih_role_proses = ($user_role_utama === 'pelatih');

if (!($can_add_for_others_proses || $is_pengurus_cabor_proses || $is_pelatih_role_proses)) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk melakukan tindakan ini.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}

// 2. Pengecekan Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_tambah_lisensi_pelatih'])) {
    $_SESSION['pesan_error_global'] = "Akses tidak sah.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}

// 3. Pengambilan Data POST & FILES
$nik_pelatih_input = trim($_POST['nik_pelatih'] ?? '');
$id_cabor_input = trim($_POST['id_cabor'] ?? '');
$nama_lisensi_sertifikat = trim($_POST['nama_lisensi_sertifikat'] ?? '');
$nomor_sertifikat = trim($_POST['nomor_sertifikat'] ?? '');
$lembaga_penerbit = trim($_POST['lembaga_penerbit'] ?? '');
$tingkat_lisensi = trim($_POST['tingkat_lisensi'] ?? '');
$tanggal_terbit_input = trim($_POST['tanggal_terbit'] ?? '');
$tanggal_kadaluarsa_input = trim($_POST['tanggal_kadaluarsa'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
$file_sertifikat_upload = $_FILES['path_file_sertifikat'] ?? null;

// Simpan data form ke session untuk diisi kembali jika error
$_SESSION['form_data_lisensi_tambah'] = $_POST;

$errors = [];
$error_fields = [];

// 4. Validasi Data
// Validasi NIK Pelatih
if (empty($nik_pelatih_input)) {
    $errors[] = "NIK Pelatih wajib diisi.";
    $error_fields[] = 'nik_pelatih';
} elseif (!preg_match('/^\d{16}$/', $nik_pelatih_input)) {
    $errors[] = "Format NIK Pelatih tidak valid (harus 16 digit angka).";
    $error_fields[] = 'nik_pelatih';
} else {
    // Cek apakah NIK pelatih ada di tabel pengguna dan pelatih
    try {
        $stmt_cek_pelatih = $pdo->prepare("SELECT plt.id_pelatih FROM pengguna p JOIN pelatih plt ON p.nik = plt.nik WHERE p.nik = :nik AND p.is_approved = 1");
        $stmt_cek_pelatih->bindParam(':nik', $nik_pelatih_input);
        $stmt_cek_pelatih->execute();
        $pelatih_data = $stmt_cek_pelatih->fetch(PDO::FETCH_ASSOC);
        if (!$pelatih_data) {
            $errors[] = "NIK Pelatih tidak ditemukan atau belum terdaftar sebagai pelatih aktif.";
            $error_fields[] = 'nik_pelatih';
        } else {
            $id_pelatih_db = $pelatih_data['id_pelatih']; // id_pelatih yang akan disimpan
        }
    } catch (PDOException $e) {
        $errors[] = "Error validasi NIK Pelatih: " . $e->getMessage();
        $error_fields[] = 'nik_pelatih';
    }
}

// Validasi ID Cabor
if (empty($id_cabor_input)) {
    $errors[] = "Cabang Olahraga lisensi wajib dipilih.";
    $error_fields[] = 'id_cabor';
} elseif (!filter_var($id_cabor_input, FILTER_VALIDATE_INT)) {
    $errors[] = "Format Cabang Olahraga tidak valid.";
    $error_fields[] = 'id_cabor';
} else {
    // Jika pengurus cabor, pastikan cabor yang dipilih sesuai dengan cabornya
    if ($is_pengurus_cabor_proses && $id_cabor_input != $id_cabor_pengurus_utama) {
        $errors[] = "Anda hanya bisa menambah lisensi untuk cabor yang Anda kelola.";
        $error_fields[] = 'id_cabor';
    }
    // Cek apakah cabor ada
    try {
        $stmt_cek_cabor = $pdo->prepare("SELECT id_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor AND status_kepengurusan = 'Aktif'");
        $stmt_cek_cabor->bindParam(':id_cabor', $id_cabor_input, PDO::PARAM_INT);
        $stmt_cek_cabor->execute();
        if (!$stmt_cek_cabor->fetch()) {
            $errors[] = "Cabang Olahraga tidak ditemukan atau tidak aktif.";
            $error_fields[] = 'id_cabor';
        }
    } catch (PDOException $e) {
        $errors[] = "Error validasi Cabor: " . $e->getMessage();
        $error_fields[] = 'id_cabor';
    }
}

// Validasi Nama Lisensi
if (empty($nama_lisensi_sertifikat)) {
    $errors[] = "Nama Lisensi/Sertifikat wajib diisi.";
    $error_fields[] = 'nama_lisensi_sertifikat';
} elseif (strlen($nama_lisensi_sertifikat) > 255) {
    $errors[] = "Nama Lisensi/Sertifikat terlalu panjang (maks 255 karakter).";
    $error_fields[] = 'nama_lisensi_sertifikat';
}

// Validasi Tanggal (jika diisi)
$tanggal_terbit_db = null;
if (!empty($tanggal_terbit_input)) {
    try {
        $dt_terbit = new DateTime($tanggal_terbit_input);
        $tanggal_terbit_db = $dt_terbit->format('Y-m-d');
    } catch (Exception $e) {
        $errors[] = "Format Tanggal Terbit tidak valid.";
        $error_fields[] = 'tanggal_terbit';
    }
}
$tanggal_kadaluarsa_db = null;
if (!empty($tanggal_kadaluarsa_input)) {
    try {
        $dt_kadaluarsa = new DateTime($tanggal_kadaluarsa_input);
        $tanggal_kadaluarsa_db = $dt_kadaluarsa->format('Y-m-d');
        if ($tanggal_terbit_db && $dt_kadaluarsa < $dt_terbit) {
            $errors[] = "Tanggal Kadaluarsa tidak boleh sebelum Tanggal Terbit.";
            $error_fields[] = 'tanggal_kadaluarsa';
        }
    } catch (Exception $e) {
        $errors[] = "Format Tanggal Kadaluarsa tidak valid.";
        $error_fields[] = 'tanggal_kadaluarsa';
    }
}

// Validasi File Sertifikat (jika diunggah)
$path_file_sertifikat_db = null;
if (isset($file_sertifikat_upload) && $file_sertifikat_upload['error'] == UPLOAD_ERR_OK) {
    $upload_dir_sertifikat = 'assets/uploads/lisensi_sertifikat/'; // Pastikan folder ini ada dan writable
    $allowed_mime_types_sertifikat = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_size_sertifikat = MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES;

    // Cek direktori upload
    if (!is_dir(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $upload_dir_sertifikat)) {
        if (!mkdir(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $upload_dir_sertifikat, 0775, true)) {
            $errors[] = "Gagal membuat direktori upload sertifikat.";
            $error_fields[] = 'path_file_sertifikat';
        }
    }
    
    if (empty($errors)) { // Lanjutkan upload jika belum ada error lain
        $file_info_sertifikat = uploadFileGeneral($file_sertifikat_upload, $upload_dir_sertifikat, $allowed_mime_types_sertifikat, $max_size_sertifikat, "sertifikat_lisensi_" . $nik_pelatih_input . "_" . time());
        if ($file_info_sertifikat['status'] === 'error') {
            $errors[] = "Upload File Sertifikat: " . $file_info_sertifikat['message'];
            $error_fields[] = 'path_file_sertifikat';
        } else {
            $path_file_sertifikat_db = $file_info_sertifikat['filepath'];
        }
    }
} elseif (isset($file_sertifikat_upload) && $file_sertifikat_upload['error'] != UPLOAD_ERR_NO_FILE) {
    $errors[] = "Terjadi kesalahan saat mengupload File Sertifikat (Error code: " . $file_sertifikat_upload['error'] . ").";
    $error_fields[] = 'path_file_sertifikat';
}


// 5. Jika Ada Error Validasi, Redirect Kembali
if (!empty($errors)) {
    $_SESSION['errors_lisensi_tambah'] = $errors;
    $_SESSION['error_fields_lisensi_tambah'] = array_unique($error_fields);
    header("Location: " . $app_base_path . "/" . $redirect_form . ($val_nik_pelatih_form ? '?nik_pelatih_default='.$val_nik_pelatih_form : ''));
    exit();
}

// 6. Jika Validasi Sukses, Simpan ke Database
try {
    $pdo->beginTransaction();

    $sql_insert_lp = "INSERT INTO lisensi_pelatih 
                        (id_pelatih, nik_pelatih, id_cabor, nama_lisensi_sertifikat, nomor_sertifikat, lembaga_penerbit, tingkat_lisensi, tanggal_terbit, tanggal_kadaluarsa, path_file_sertifikat, catatan, status_approval, created_by_nik)
                      VALUES 
                        (:id_pelatih, :nik_pelatih, :id_cabor, :nama_lisensi_sertifikat, :nomor_sertifikat, :lembaga_penerbit, :tingkat_lisensi, :tanggal_terbit, :tanggal_kadaluarsa, :path_file_sertifikat, :catatan, :status_approval, :created_by_nik)";
    
    $stmt_insert_lp = $pdo->prepare($sql_insert_lp);
    
    $status_awal_approval = 'pending'; // Default
    // Jika diinput oleh Admin KONI/Super Admin, bisa langsung disetujui admin (atau tetap pending jika ingin alur sama)
    // if ($can_add_for_others_proses) { $status_awal_approval = 'disetujui_admin'; }
    
    $stmt_insert_lp->bindParam(':id_pelatih', $id_pelatih_db, PDO::PARAM_INT);
    $stmt_insert_lp->bindParam(':nik_pelatih', $nik_pelatih_input);
    $stmt_insert_lp->bindParam(':id_cabor', $id_cabor_input, PDO::PARAM_INT);
    $stmt_insert_lp->bindParam(':nama_lisensi_sertifikat', $nama_lisensi_sertifikat);
    $stmt_insert_lp->bindParam(':nomor_sertifikat', $nomor_sertifikat);
    $stmt_insert_lp->bindParam(':lembaga_penerbit', $lembaga_penerbit);
    $stmt_insert_lp->bindParam(':tingkat_lisensi', $tingkat_lisensi);
    $stmt_insert_lp->bindParam(':tanggal_terbit', $tanggal_terbit_db);
    $stmt_insert_lp->bindParam(':tanggal_kadaluarsa', $tanggal_kadaluarsa_db);
    $stmt_insert_lp->bindParam(':path_file_sertifikat', $path_file_sertifikat_db);
    $stmt_insert_lp->bindParam(':catatan', $catatan);
    $stmt_insert_lp->bindParam(':status_approval', $status_awal_approval);
    $stmt_insert_lp->bindParam(':created_by_nik', $user_nik); // NIK pengguna yang login
    
    $stmt_insert_lp->execute();
    $new_lisensi_id = $pdo->lastInsertId();

    // Audit Log
    $keterangan_audit = "Menambah lisensi baru untuk pelatih NIK: {$nik_pelatih_input}, Nama Lisensi: {$nama_lisensi_sertifikat}, ID Lisensi Baru: {$new_lisensi_id}";
    $data_baru_json = json_encode(['id_lisensi_pelatih' => $new_lisensi_id] + $_POST); // Log semua POST data + ID baru
    catatAuditLog($user_nik, 'TAMBAH', 'lisensi_pelatih', $new_lisensi_id, null, $data_baru_json, $keterangan_audit, $pdo);

    $pdo->commit();

    unset($_SESSION['form_data_lisensi_tambah']); // Hapus data form dari session jika sukses
    $_SESSION['pesan_sukses_global'] = "Data lisensi pelatih berhasil ditambahkan dan menunggu approval.";
    header("Location: " . $app_base_path . "/" . $redirect_list . "?status_approval=pending" . ($filter_id_cabor_lp ? "&id_cabor=".$filter_id_cabor_lp : ""));
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    // Hapus file yang sudah terupload jika transaksi gagal
    if ($path_file_sertifikat_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $path_file_sertifikat_db)) {
        unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $path_file_sertifikat_db);
    }
    error_log("PDOError Proses Tambah Lisensi: " . $e->getMessage());
    $_SESSION['errors_lisensi_tambah'] = ["Terjadi kesalahan database saat menyimpan data: " . $e->getMessage()];
    header("Location: " . $app_base_path . "/" . $redirect_form . ($val_nik_pelatih_form ? '?nik_pelatih_default='.$val_nik_pelatih_form : ''));
    exit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
     if ($path_file_sertifikat_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $path_file_sertifikat_db)) {
        unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $path_file_sertifikat_db);
    }
    error_log("GeneralError Proses Tambah Lisensi: " . $e->getMessage());
    $_SESSION['errors_lisensi_tambah'] = ["Terjadi kesalahan sistem: " . $e->getMessage()];
    header("Location: " . $app_base_path . "/" . $redirect_form . ($val_nik_pelatih_form ? '?nik_pelatih_default='.$val_nik_pelatih_form : ''));
    exit();
}
?>