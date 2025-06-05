<?php
// File: modules/lisensi_pelatih/proses_edit_lisensi_pelatih.php

require_once(__DIR__ . '/../../core/init_core.php');

// Pastikan konstanta sudah terdefinisi
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB')) { define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB', 2); }
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES')) { define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES', MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB * 1024 * 1024); }

$redirect_list = 'daftar_lisensi_pelatih.php';

// 1. Pengecekan Sesi & Hak Akses Awal
if (!isset($user_nik, $user_role_utama, $app_base_path, $pdo)) {
    $_SESSION['pesan_error_global'] = "Sesi tidak valid.";
    header("Location: " . ($app_base_path ?? '.') . "/auth/login.php");
    exit();
}

// 2. Pengecekan Request Method & Parameter Kunci
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['pesan_error_global'] = "Akses tidak sah.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}

if (!isset($_POST['id_lisensi_pelatih']) || !filter_var($_POST['id_lisensi_pelatih'], FILTER_VALIDATE_INT)) {
    $_SESSION['pesan_error_global'] = "ID Lisensi tidak valid untuk diproses.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}
$id_lisensi_to_process = (int)$_POST['id_lisensi_pelatih'];
$redirect_form_edit = 'edit_lisensi_pelatih.php?id_lisensi=' . $id_lisensi_to_process;


// 3. Pengambilan Data Lisensi Saat Ini dari DB
$current_lisensi = null;
try {
    $stmt_curr_lp = $pdo->prepare("SELECT lp.* FROM lisensi_pelatih lp WHERE lp.id_lisensi_pelatih = :id_lisensi");
    $stmt_curr_lp->bindParam(':id_lisensi', $id_lisensi_to_process, PDO::PARAM_INT);
    $stmt_curr_lp->execute();
    $current_lisensi = $stmt_curr_lp->fetch(PDO::FETCH_ASSOC);
    if (!$current_lisensi) {
        $_SESSION['pesan_error_global'] = "Data lisensi yang akan diproses tidak ditemukan.";
        header("Location: " . $app_base_path . "/" . $redirect_list);
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetch current lisensi: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Gagal mengambil data lisensi saat ini.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}
$data_lama_json = json_encode($current_lisensi); // Untuk audit log

// 4. Pengecekan Hak Akses Edit/Approval (disesuaikan dengan logika di daftar dan edit form)
$id_cabor_pengurus_session = $id_cabor_pengurus_utama ?? null;
$can_process_this = false;
$is_quick_action = isset($_POST['quick_action_approval_lisensi']);

if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $can_process_this = true;
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_session) && $current_lisensi['id_cabor'] == $id_cabor_pengurus_session) {
    if ($is_quick_action && in_array($_POST['status_approval'], ['disetujui_pengcab', 'ditolak_pengcab'])) { $can_process_this = true; } // Pengcab hanya bisa setujui/tolak pengcab
    elseif (!$is_quick_action && in_array($current_lisensi['status_approval'], ['pending', 'revisi', 'ditolak_pengcab'])) { $can_process_this = true; }
} elseif ($user_role_utama === 'pelatih' && $current_lisensi['nik_pelatih'] == $user_nik) {
    if (!$is_quick_action && in_array($current_lisensi['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) { $can_process_this = true; }
}

if (!$can_process_this) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk memproses data lisensi ini.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}


// 5. Pengambilan Data POST & FILES
$errors = [];
$error_fields = [];
$data_to_update = [];
$new_path_file_sertifikat = $current_lisensi['path_file_sertifikat']; // Default ke file lama

// Mode Edit Penuh (dari form edit_lisensi_pelatih.php)
if (isset($_POST['submit_edit_lisensi_pelatih'])) {
    $nama_lisensi_sertifikat = trim($_POST['nama_lisensi_sertifikat'] ?? '');
    $nomor_sertifikat = trim($_POST['nomor_sertifikat'] ?? '');
    $lembaga_penerbit = trim($_POST['lembaga_penerbit'] ?? '');
    $tingkat_lisensi = trim($_POST['tingkat_lisensi'] ?? '');
    $tanggal_terbit_input = trim($_POST['tanggal_terbit'] ?? '');
    $tanggal_kadaluarsa_input = trim($_POST['tanggal_kadaluarsa'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $file_sertifikat_upload = $_FILES['path_file_sertifikat'] ?? null;
    $current_path_file_sertifikat_hidden = $_POST['current_path_file_sertifikat'] ?? null;

    $_SESSION['form_data_lisensi_edit_' . $id_lisensi_to_process] = $_POST; // Simpan data form

    // Validasi Data (mirip tambah, tapi NIK dan Cabor tidak diubah)
    if (empty($nama_lisensi_sertifikat)) { $errors[] = "Nama Lisensi wajib diisi."; $error_fields[] = 'nama_lisensi_sertifikat'; }
    // ... validasi panjang field lain ...

    $tanggal_terbit_db = $current_lisensi['tanggal_terbit']; // Default ke data lama
    if (!empty($tanggal_terbit_input)) {
        try { $dt_terbit = new DateTime($tanggal_terbit_input); $tanggal_terbit_db = $dt_terbit->format('Y-m-d'); } 
        catch (Exception $e) { $errors[] = "Format Tanggal Terbit tidak valid."; $error_fields[] = 'tanggal_terbit'; }
    } elseif ($tanggal_terbit_input === '') { $tanggal_terbit_db = null; } // Jika dikosongkan

    $tanggal_kadaluarsa_db = $current_lisensi['tanggal_kadaluarsa']; // Default ke data lama
    if (!empty($tanggal_kadaluarsa_input)) {
        try { 
            $dt_kadaluarsa = new DateTime($tanggal_kadaluarsa_input); $tanggal_kadaluarsa_db = $dt_kadaluarsa->format('Y-m-d');
            if ($tanggal_terbit_db && $dt_kadaluarsa < (new DateTime($tanggal_terbit_db))) { $errors[] = "Tgl Kadaluarsa tidak boleh sebelum Tgl Terbit."; $error_fields[] = 'tanggal_kadaluarsa';}
        } catch (Exception $e) { $errors[] = "Format Tanggal Kadaluarsa tidak valid."; $error_fields[] = 'tanggal_kadaluarsa'; }
    } elseif ($tanggal_kadaluarsa_input === '') { $tanggal_kadaluarsa_db = null; }

    // Proses Upload File Baru
    if (isset($file_sertifikat_upload) && $file_sertifikat_upload['error'] == UPLOAD_ERR_OK) {
        $upload_dir_sertifikat_edit = 'assets/uploads/lisensi_sertifikat/';
        $allowed_mime_types_sertifikat_edit = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_size_sertifikat_edit = MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES;
        
        $file_info_sert_edit = uploadFileGeneral($file_sertifikat_upload, $upload_dir_sertifikat_edit, $allowed_mime_types_sertifikat_edit, $max_size_sertifikat_edit, "sert_lisensi_edit_" . $current_lisensi['nik_pelatih'] . "_" . time());
        if ($file_info_sert_edit['status'] === 'error') {
            $errors[] = "Upload File Sertifikat Baru: " . $file_info_sert_edit['message'];
            $error_fields[] = 'path_file_sertifikat';
        } else {
            $new_path_file_sertifikat = $file_info_sert_edit['filepath'];
            // Hapus file lama jika upload baru sukses DAN file lama ada
            if ($current_path_file_sertifikat_hidden && $new_path_file_sertifikat != $current_path_file_sertifikat_hidden && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $current_path_file_sertifikat_hidden)) {
                unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $current_path_file_sertifikat_hidden);
            }
        }
    } elseif (isset($file_sertifikat_upload) && $file_sertifikat_upload['error'] != UPLOAD_ERR_NO_FILE) {
        $errors[] = "Kesalahan upload file sertifikat baru (Error code: " . $file_sertifikat_upload['error'] . ").";
        $error_fields[] = 'path_file_sertifikat';
    } else {
        $new_path_file_sertifikat = $current_path_file_sertifikat_hidden; // Tidak ada file baru, gunakan path lama
    }


    if (empty($errors)) {
        $data_to_update['nama_lisensi_sertifikat'] = $nama_lisensi_sertifikat;
        $data_to_update['nomor_sertifikat'] = !empty($nomor_sertifikat) ? $nomor_sertifikat : null;
        $data_to_update['lembaga_penerbit'] = !empty($lembaga_penerbit) ? $lembaga_penerbit : null;
        $data_to_update['tingkat_lisensi'] = !empty($tingkat_lisensi) ? $tingkat_lisensi : null;
        $data_to_update['tanggal_terbit'] = $tanggal_terbit_db;
        $data_to_update['tanggal_kadaluarsa'] = $tanggal_kadaluarsa_db;
        $data_to_update['path_file_sertifikat'] = $new_path_file_sertifikat;
        $data_to_update['catatan'] = !empty($catatan) ? $catatan : null;
        $data_to_update['updated_by_nik'] = $user_nik;
        $data_to_update['updated_at'] = date('Y-m-d H:i:s'); // Atau biarkan DB handle via ON UPDATE CURRENT_TIMESTAMP

        // Jika diedit oleh pelatih/pengcab setelah ditolak/revisi, status kembali ke pending (atau pending_pengcab)
        if (in_array($current_lisensi['status_approval'], ['revisi', 'ditolak_pengcab', 'ditolak_admin'])) {
             if ($user_role_utama === 'pelatih' || ($user_role_utama === 'pengurus_cabor' && $current_lisensi['id_cabor'] == $id_cabor_pengurus_session)) {
                 $data_to_update['status_approval'] = 'pending'; 
                 // Kosongkan approval sebelumnya jika status direset ke pending
                 $data_to_update['approved_by_nik_pengcab'] = null; $data_to_update['approval_at_pengcab'] = null; $data_to_update['alasan_penolakan_pengcab'] = null;
                 $data_to_update['approved_by_nik_admin'] = null; $data_to_update['approval_at_admin'] = null; $data_to_update['alasan_penolakan_admin'] = null;
             }
        }
    }
} 
// Mode Quick Action (dari tombol di halaman daftar_lisensi_pelatih.php)
elseif ($is_quick_action) {
    $new_status_approval = $_POST['status_approval'] ?? null;
    $allowed_quick_statuses = ['disetujui_pengcab', 'ditolak_pengcab', 'disetujui_admin', 'ditolak_admin', 'revisi'];
    
    if (!$new_status_approval || !in_array($new_status_approval, $allowed_quick_statuses)) {
        $errors[] = "Status approval untuk aksi cepat tidak valid.";
    } else {
        // Validasi hak untuk set status
        if ($new_status_approval === 'disetujui_pengcab' || $new_status_approval === 'ditolak_pengcab') {
            if (! (in_array($user_role_utama, ['super_admin', 'admin_koni']) || ($user_role_utama === 'pengurus_cabor' && $current_lisensi['id_cabor'] == $id_cabor_pengurus_session))) {
                $errors[] = "Anda tidak berhak melakukan approval tahap Pengcab.";
            } else {
                $data_to_update['status_approval'] = $new_status_approval;
                $data_to_update['approved_by_nik_pengcab'] = $user_nik;
                $data_to_update['approval_at_pengcab'] = date('Y-m-d H:i:s');
                if ($new_status_approval === 'ditolak_pengcab') {
                    $data_to_update['alasan_penolakan_pengcab'] = trim($_POST['alasan_penolakan_pengcab'] ?? 'Ditolak oleh Pengcab.');
                } else {
                    $data_to_update['alasan_penolakan_pengcab'] = null; // Hapus alasan jika disetujui
                }
            }
        } elseif ($new_status_approval === 'disetujui_admin' || $new_status_approval === 'ditolak_admin' || $new_status_approval === 'revisi') {
            if (!in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                $errors[] = "Anda tidak berhak melakukan approval tahap Admin KONI.";
            } else {
                $data_to_update['status_approval'] = $new_status_approval;
                $data_to_update['approved_by_nik_admin'] = $user_nik;
                $data_to_update['approval_at_admin'] = date('Y-m-d H:i:s');
                if ($new_status_approval === 'ditolak_admin' || $new_status_approval === 'revisi') {
                    $data_to_update['alasan_penolakan_admin'] = trim($_POST['alasan_penolakan_admin'] ?? ($new_status_approval === 'revisi' ? 'Perlu revisi dari Admin.' : 'Ditolak oleh Admin KONI.'));
                } else {
                     $data_to_update['alasan_penolakan_admin'] = null; // Hapus alasan jika disetujui
                }
            }
        }
        $data_to_update['updated_by_nik'] = $user_nik;
        $data_to_update['updated_at'] = date('Y-m-d H:i:s');
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak dikenali.";
    header("Location: " . $app_base_path . "/" . $redirect_list);
    exit();
}


// Jika Ada Error Validasi, Redirect Kembali
if (!empty($errors)) {
    if (isset($_POST['submit_edit_lisensi_pelatih'])) { // Hanya untuk mode edit penuh
        $_SESSION['errors_lisensi_edit_' . $id_lisensi_to_process] = $errors;
        $_SESSION['error_fields_lisensi_edit_' . $id_lisensi_to_process] = array_unique($error_fields);
        header("Location: " . $app_base_path . "/" . $redirect_form_edit);
    } else { // Untuk quick action atau error umum
        $_SESSION['pesan_error_global'] = implode("<br>", $errors);
        header("Location: " . $app_base_path . "/" . $redirect_list . ($current_lisensi['id_cabor'] ? "?id_cabor=".$current_lisensi['id_cabor'] : ""));
    }
    exit();
}


// Jika Validasi Sukses dan ada data untuk diupdate, Simpan ke Database
if (!empty($data_to_update)) {
    try {
        $pdo->beginTransaction();

        $update_fields_sql = [];
        foreach (array_keys($data_to_update) as $field) {
            $update_fields_sql[] = "`{$field}` = :{$field}";
        }
        $sql_update_lp = "UPDATE lisensi_pelatih SET " . implode(", ", $update_fields_sql) . " WHERE id_lisensi_pelatih = :id_lisensi_pelatih_pk";
        
        $stmt_update_lp = $pdo->prepare($sql_update_lp);
        $data_to_update['id_lisensi_pelatih_pk'] = $id_lisensi_to_process; // Tambahkan PK untuk binding
        
        $stmt_update_lp->execute($data_to_update);

        // Audit Log
        $keterangan_audit_edit = "Mengubah data lisensi pelatih ID: {$id_lisensi_to_process}. NIK Pelatih: {$current_lisensi['nik_pelatih']}.";
        if($is_quick_action) { $keterangan_audit_edit .= " Aksi cepat ubah status ke: " . htmlspecialchars($_POST['status_approval']); }
        $data_baru_json_edit = json_encode($data_to_update); 
        catatAuditLog($user_nik, 'EDIT', 'lisensi_pelatih', $id_lisensi_to_process, $data_lama_json, $data_baru_json_edit, $keterangan_audit_edit, $pdo);

        $pdo->commit();

        unset($_SESSION['form_data_lisensi_edit_' . $id_lisensi_to_process]);
        $_SESSION['pesan_sukses_global'] = "Data lisensi pelatih berhasil diperbarui.";
        header("Location: " . $app_base_path . "/" . $redirect_list . ($current_lisensi['id_cabor'] ? "?id_cabor=".$current_lisensi['id_cabor'] : ""));
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        // Hapus file baru jika ada error DB dan file baru sudah terupload (dan berbeda dari yang lama)
        if (isset($file_info_sert_edit) && $file_info_sert_edit['status'] === 'success' && $new_path_file_sertifikat != $current_lisensi['path_file_sertifikat']) {
            if (file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $new_path_file_sertifikat)) {
                unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $new_path_file_sertifikat);
            }
        }
        error_log("PDOError Proses Edit Lisensi: " . $e->getMessage());
        $_SESSION['errors_lisensi_edit_' . $id_lisensi_to_process] = ["Terjadi kesalahan database: " . $e->getMessage()];
        header("Location: " . $app_base_path . "/" . $redirect_form_edit);
        exit();
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        if (isset($file_info_sert_edit) && $file_info_sert_edit['status'] === 'success' && $new_path_file_sertifikat != $current_lisensi['path_file_sertifikat']) {
             if (file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $new_path_file_sertifikat)) {
                unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . $new_path_file_sertifikat);
            }
        }
        error_log("GeneralError Proses Edit Lisensi: " . $e->getMessage());
        $_SESSION['errors_lisensi_edit_' . $id_lisensi_to_process] = ["Terjadi kesalahan sistem: " . $e->getMessage()];
        header("Location: " . $app_base_path . "/" . $redirect_form_edit);
        exit();
    }
} else {
    // Tidak ada data yang diupdate (mungkin hanya submit form edit tanpa perubahan) atau error validasi awal
    if (isset($_POST['submit_edit_lisensi_pelatih']) && empty($errors)) {
         $_SESSION['pesan_info_global'] = "Tidak ada perubahan data yang dilakukan.";
    } elseif (!empty($errors) && isset($_POST['submit_edit_lisensi_pelatih'])) {
        $_SESSION['errors_lisensi_edit_' . $id_lisensi_to_process] = $errors;
        $_SESSION['error_fields_lisensi_edit_' . $id_lisensi_to_process] = array_unique($error_fields);
    }
    header("Location: " . $app_base_path . "/" . (isset($_POST['submit_edit_lisensi_pelatih']) ? $redirect_form_edit : $redirect_list));
    exit();
}
?>