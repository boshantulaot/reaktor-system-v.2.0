<?php
// File: reaktorsystem/modules/cabor/proses_edit_cabor.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/header.php'); 

// 2. Pengecekan Akses & Session
if (!isset($user_login_status) || $user_login_status !== true || 
    !isset($user_role_utama) || !in_array($user_role_utama, ['super_admin', 'admin_koni']) ||
    !isset($user_nik) || !isset($app_base_path) || !isset($pdo) || !$pdo instanceof PDO ||
    !defined('MAX_FILE_SIZE_SK_CABOR_BYTES') || !defined('MAX_FILE_SIZE_LOGO_BYTES') || !defined('APP_PATH_BASE')) {
    
    $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    $fallback_login_url_pec = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : rtrim($app_base_path ?? '/', '/')) . "/auth/login.php?reason=invalid_session_or_config_pec";
    if (!headers_sent()) { header("Location: " . $fallback_login_url_pec); }
    else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($fallback_login_url_pec, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Error. <a href='" . htmlspecialchars($fallback_login_url_pec, ENT_QUOTES, 'UTF-8') . "'>Login ulang</a>.</p></noscript>"; }
    exit();
}
$user_nik_pelaku_edit_cabor = $user_nik;
$daftar_cabor_redirect_url_edit = "daftar_cabor.php";


if (isset($_POST['submit_edit_cabor']) && isset($_POST['id_cabor']) && !empty(trim($_POST['id_cabor']))) {
    $id_cabor_to_edit_val = filter_var($_POST['id_cabor'], FILTER_VALIDATE_INT);

    if (!$id_cabor_to_edit_val) {
        $_SESSION['pesan_error_global'] = "ID Cabang Olahraga tidak valid untuk diedit.";
        header("Location: " . $daftar_cabor_redirect_url_edit);
        exit();
    }

    // Ambil data lama SEBELUM ada perubahan untuk perbandingan dan logging
    try {
        $stmt_old_data_cbr = $pdo->prepare("SELECT * FROM cabang_olahraga WHERE id_cabor = :id_cabor_param");
        $stmt_old_data_cbr->bindParam(':id_cabor_param', $id_cabor_to_edit_val, PDO::PARAM_INT);
        $stmt_old_data_cbr->execute();
        $old_cabor_data_for_log_val = $stmt_old_data_cbr->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e_old_cbr) {
        error_log("Proses Edit Cabor - Gagal ambil data lama: " . $e_old_cbr->getMessage());
        $_SESSION['pesan_error_global'] = "Gagal mengambil data cabor yang akan diedit.";
        header("Location: " . $daftar_cabor_redirect_url_edit);
        exit();
    }

    if (!$old_cabor_data_for_log_val) {
        $_SESSION['pesan_error_global'] = "Data Cabor dengan ID " . htmlspecialchars($id_cabor_to_edit_val) . " tidak ditemukan.";
        header("Location: " . $daftar_cabor_redirect_url_edit);
        exit();
    }
    $data_lama_cabor_json = json_encode($old_cabor_data_for_log_val);

    // Ambil data dari POST
    $nama_cabor_new = trim($_POST['nama_cabor'] ?? '');
    $kode_cabor_current = $old_cabor_data_for_log_val['kode_cabor']; // Kode cabor tidak diubah
    $ketua_cabor_nik_new = trim($_POST['ketua_cabor_nik'] ?? '');
    $sekretaris_cabor_nik_new = trim($_POST['sekretaris_cabor_nik'] ?? '');
    $bendahara_cabor_nik_new = trim($_POST['bendahara_cabor_nik'] ?? '');
    $alamat_sekretariat_new = trim($_POST['alamat_sekretariat'] ?? '');
    $kontak_cabor_new = trim($_POST['kontak_cabor'] ?? '');
    $email_cabor_new = filter_var(trim($_POST['email_cabor'] ?? ''), FILTER_SANITIZE_EMAIL);
    $nomor_sk_provinsi_new = trim($_POST['nomor_sk_provinsi'] ?? '');
    $tanggal_sk_provinsi_new_input = trim($_POST['tanggal_sk_provinsi'] ?? '');
    $periode_mulai_new_input = trim($_POST['periode_mulai'] ?? '');
    $periode_selesai_new_input = trim($_POST['periode_selesai'] ?? '');
    $status_kepengurusan_new = trim($_POST['status_kepengurusan'] ?? 'Aktif');
    
    $current_logo_path_from_form = trim($_POST['logo_cabor_lama'] ?? ($old_cabor_data_for_log_val['logo_cabor'] ?? null));
    $current_sk_path_from_form = trim($_POST['path_file_sk_provinsi_lama'] ?? ($old_cabor_data_for_log_val['path_file_sk_provinsi'] ?? null));
    $hapus_logo_current_flag = isset($_POST['hapus_logo_cabor_current']) && $_POST['hapus_logo_cabor_current'] == '1';
    $hapus_sk_current_flag = isset($_POST['hapus_sk_provinsi_current']) && $_POST['hapus_sk_provinsi_current'] == '1';


    $_SESSION['form_data_cabor_edit'] = $_POST; 
    $errors_edit_cabor_arr = [];

    // Validasi Server-Side yang Disempurnakan
    if (empty($nama_cabor_new)) $errors_edit_cabor_arr[] = "Nama Cabang Olahraga wajib diisi.";
    // NIK Ketua bisa jadi opsional tergantung kebijakan, jika wajib:
    // if (empty($ketua_cabor_nik_new)) $errors_edit_cabor_arr[] = "NIK Ketua Cabor wajib diisi.";
    
    if (!empty($email_cabor_new) && !filter_var($email_cabor_new, FILTER_VALIDATE_EMAIL)) {
        $errors_edit_cabor_arr[] = "Format Email Cabor tidak valid.";
    }

    $tanggal_sk_provinsi_db_update = $old_cabor_data_for_log_val['tanggal_sk_provinsi'];
    if (array_key_exists('tanggal_sk_provinsi', $_POST)){ // Cek jika field dikirim
        if (!empty($tanggal_sk_provinsi_new_input)) {
            $d_sk = DateTime::createFromFormat('Y-m-d', $tanggal_sk_provinsi_new_input);
            if ($d_sk && $d_sk->format('Y-m-d') === $tanggal_sk_provinsi_new_input) { $tanggal_sk_provinsi_db_update = $tanggal_sk_provinsi_new_input; } 
            else { $errors_edit_cabor_arr[] = "Format Tanggal SK Provinsi tidak valid (YYYY-MM-DD)."; }
        } else { $tanggal_sk_provinsi_db_update = null; } // Izinkan pengosongan tanggal
    }

    $periode_mulai_db_update = $old_cabor_data_for_log_val['periode_mulai'];
    if (array_key_exists('periode_mulai', $_POST)){
        if(!empty($periode_mulai_new_input)) {
            $d_pm = DateTime::createFromFormat('Y-m-d', $periode_mulai_new_input);
            if ($d_pm && $d_pm->format('Y-m-d') === $periode_mulai_new_input) { $periode_mulai_db_update = $periode_mulai_new_input; } 
            else { $errors_edit_cabor_arr[] = "Format Tanggal Periode Mulai tidak valid."; }
        } else { $periode_mulai_db_update = null; }
    }

    $periode_selesai_db_update = $old_cabor_data_for_log_val['periode_selesai'];
    if (array_key_exists('periode_selesai', $_POST)){
        if (!empty($periode_selesai_new_input)) {
            $d_ps = DateTime::createFromFormat('Y-m-d', $periode_selesai_new_input);
            if ($d_ps && $d_ps->format('Y-m-d') === $periode_selesai_new_input) { $periode_selesai_db_update = $periode_selesai_new_input; } 
            else { $errors_edit_cabor_arr[] = "Format Tanggal Periode Selesai tidak valid."; }
        } else { $periode_selesai_db_update = null; }
    }
    
    if ($periode_mulai_db_update && $periode_selesai_db_update && $periode_selesai_db_update < $periode_mulai_db_update) {
        $errors_edit_cabor_arr[] = "Tanggal Periode Selesai tidak boleh sebelum Tanggal Periode Mulai.";
    }
    if (!in_array($status_kepengurusan_new, ['Aktif', 'Tidak Aktif', 'Masa Tenggang', 'Dibekukan'])) {
        $errors_edit_cabor_arr[] = "Status Kepengurusan tidak valid.";
    }

    if (empty($errors_edit_cabor_arr) && strtolower($nama_cabor_new) !== strtolower($old_cabor_data_for_log_val['nama_cabor'])) {
        try {
            $stmt_cek_nama_edit = $pdo->prepare("SELECT id_cabor FROM cabang_olahraga WHERE nama_cabor = :nama_cabor AND id_cabor != :id_cabor_edit_val");
            $stmt_cek_nama_edit->execute([':nama_cabor' => $nama_cabor_new, ':id_cabor_edit_val' => $id_cabor_to_edit_val]);
            if ($stmt_cek_nama_edit->fetch()) { $errors_edit_cabor_arr[] = "Nama Cabang Olahraga '" . htmlspecialchars($nama_cabor_new) . "' sudah digunakan oleh cabor lain."; }
        } catch (PDOException $e_cek_nama_edit) { $errors_edit_cabor_arr[] = "Gagal memvalidasi keunikan Nama Cabor."; error_log("PROSES_EDIT_CABOR_VALIDASI_NAMA_ERROR: " . $e_cek_nama_edit->getMessage());}
    }
    
    // Proses Upload File SK Provinsi (jika ada file baru atau ada permintaan hapus)
    $path_file_sk_provinsi_final = $current_sk_path_from_form; 
    $temp_new_sk_path_uploaded = null;
    $old_sk_fisik_perlu_dihapus = false;

    if (empty($errors_edit_cabor_arr)) {
        if ($hapus_sk_current_flag) {
            if ($current_sk_path_from_form && $current_sk_path_from_form !== ($default_cabor_logo_path_relative ?? '')) { // Jangan hapus jika pathnya adalah placeholder default
                $old_sk_fisik_perlu_dihapus = true;
            }
            $path_file_sk_provinsi_final = null; // Set path DB ke NULL jika dihapus
        }

        if (isset($_FILES['path_file_sk_provinsi']) && $_FILES['path_file_sk_provinsi']['error'] == UPLOAD_ERR_OK && $_FILES['path_file_sk_provinsi']['size'] > 0) {
            $file_prefix_sk_edit = "sk_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $kode_cabor_current);
            $uploaded_sk_path = uploadFileGeneral('path_file_sk_provinsi', 'sk_cabor', $file_prefix_sk_edit, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_SK_CABOR_BYTES, $errors_edit_cabor_arr, $current_sk_path_from_form, false);
            if ($uploaded_sk_path !== null) {
                $path_file_sk_provinsi_final = $uploaded_sk_path;
                $temp_new_sk_path_uploaded = $uploaded_sk_path;
                $old_sk_fisik_perlu_dihapus = false; // Sudah dihandle oleh uploadFileGeneral jika ada upload baru
            } elseif (!empty($_FILES['path_file_sk_provinsi']['name'])) { /* error ditangani uploadFileGeneral */ }
        }
    }

    // Proses Upload Logo Cabor (jika ada file baru atau ada permintaan hapus)
    $path_logo_cabor_final = $current_logo_path_from_form;
    $temp_new_logo_path_uploaded = null;
    $old_logo_fisik_perlu_dihapus = false;

    if (empty($errors_edit_cabor_arr)) {
        if ($hapus_logo_current_flag) {
            if ($current_logo_path_from_form && $current_logo_path_from_form !== $default_cabor_logo_path_relative) {
                $old_logo_fisik_perlu_dihapus = true;
            }
            $path_logo_cabor_final = null;
        }

        if (isset($_FILES['logo_cabor']) && $_FILES['logo_cabor']['error'] == UPLOAD_ERR_OK && $_FILES['logo_cabor']['size'] > 0) {
            $file_prefix_logo_edit = "logo_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $kode_cabor_current);
            $path_logo_cabor_upload = uploadFileGeneral('logo_cabor', 'logo_cabor', $file_prefix_logo_edit, ['jpg', 'jpeg', 'png', 'gif'], MAX_FILE_SIZE_LOGO_BYTES, $errors_edit_cabor_arr, $current_logo_path_from_form, false);
            if ($path_logo_cabor_upload !== null) {
                $path_logo_cabor_final = $path_logo_cabor_upload;
                $temp_new_logo_path_uploaded = $path_logo_cabor_upload;
                $old_logo_fisik_perlu_dihapus = false;
            } elseif (!empty($_FILES['logo_cabor']['name'])) { /* error ditangani uploadFileGeneral */ }
        }
    }

    if (!empty($errors_edit_cabor_arr)) {
        $_SESSION['errors_edit_cabor'] = $errors_edit_cabor_arr;
        // Hapus file baru yang mungkin sudah terupload jika validasi gagal
        if ($temp_new_sk_path_uploaded && defined('APP_PATH_BASE')) { @unlink(rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_new_sk_path_uploaded, '/\\')); }
        if ($temp_new_logo_path_uploaded && defined('APP_PATH_BASE')) { @unlink(rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_new_logo_path_uploaded, '/\\')); }
        header("Location: edit_cabor.php?id_cabor=" . $id_cabor_to_edit_val);
        exit();
    }

    // Update ke Database
    try {
        $pdo->beginTransaction();
        
        $sql_update_cabor_final = "UPDATE cabang_olahraga SET 
                        nama_cabor = :nama_cabor, ketua_cabor_nik = :ketua_cabor_nik, 
                        sekretaris_cabor_nik = :sekretaris_cabor_nik, bendahara_cabor_nik = :bendahara_cabor_nik, 
                        alamat_sekretariat = :alamat_sekretariat, kontak_cabor = :kontak_cabor, 
                        email_cabor = :email_cabor, logo_cabor = :logo_cabor, 
                        nomor_sk_provinsi = :nomor_sk_provinsi, tanggal_sk_provinsi = :tanggal_sk_provinsi, 
                        path_file_sk_provinsi = :path_file_sk_provinsi, periode_mulai = :periode_mulai, 
                        periode_selesai = :periode_selesai, status_kepengurusan = :status_kepengurusan, 
                        updated_by_nik = :updated_by_nik_param, updated_at = NOW(), last_updated_process_at = NOW() 
                      WHERE id_cabor = :id_cabor_val_param";
        $stmt_update_cabor = $pdo->prepare($sql_update_cabor_final);
        
        $params_update_cabor_db = [
            ':nama_cabor' => $nama_cabor_new, 
            ':ketua_cabor_nik' => empty($ketua_cabor_nik_new) ? null : $ketua_cabor_nik_new,
            ':sekretaris_cabor_nik' => empty($sekretaris_cabor_nik_new) ? null : $sekretaris_cabor_nik_new, 
            ':bendahara_cabor_nik' => empty($bendahara_cabor_nik_new) ? null : $bendahara_cabor_nik_new,
            ':alamat_sekretariat' => empty($alamat_sekretariat_new) ? null : $alamat_sekretariat_new, 
            ':kontak_cabor' => empty($kontak_cabor_new) ? null : $kontak_cabor_new, 
            ':email_cabor' => empty($email_cabor_new) ? null : $email_cabor_new,
            ':logo_cabor' => $path_logo_cabor_final, 
            ':nomor_sk_provinsi' => empty($nomor_sk_provinsi_new) ? null : $nomor_sk_provinsi_new,
            ':tanggal_sk_provinsi' => $tanggal_sk_provinsi_db_update, 
            ':path_file_sk_provinsi' => $path_file_sk_provinsi_final,
            ':periode_mulai' => $periode_mulai_db_update, 
            ':periode_selesai' => $periode_selesai_db_update,
            ':status_kepengurusan' => $status_kepengurusan_new, 
            ':updated_by_nik_param' => $user_nik_pelaku_edit_cabor,
            ':id_cabor_val_param' => $id_cabor_to_edit_val
        ];
        $stmt_update_cabor->execute($params_update_cabor_db);

        // Audit Log
        $stmt_get_new_data_cbr = $pdo->prepare("SELECT * FROM cabang_olahraga WHERE id_cabor = :id_cabor_param_log");
        $stmt_get_new_data_cbr->execute([':id_cabor_param_log' => $id_cabor_to_edit_val]);
        $data_baru_cabor_for_log = $stmt_get_new_data_cbr->fetch(PDO::FETCH_ASSOC);

        if (function_exists('catatAuditLog')) {
            catatAuditLog($pdo, $user_nik_pelaku_edit_cabor, 'EDIT_CABOR', 'cabang_olahraga', $id_cabor_to_edit_val, $data_lama_cabor_json, json_encode($data_baru_cabor_for_log), "Data Cabor '" . htmlspecialchars($nama_cabor_new) . "' (Kode: {$kode_cabor_current}) diperbarui.");
        }
        $pdo->commit();

        // Hapus file fisik lama jika ditandai dan tidak digantikan oleh upload baru
        if ($old_logo_fisik_perlu_dihapus && defined('APP_PATH_BASE')) {
            $full_path_old_logo = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($current_logo_path_from_form, '/\\');
            if (file_exists($full_path_old_logo) && is_file($full_path_old_logo)) @unlink($full_path_old_logo);
        }
        if ($old_sk_fisik_perlu_dihapus && defined('APP_PATH_BASE')) {
            $full_path_old_sk = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($current_sk_path_from_form, '/\\');
            if (file_exists($full_path_old_sk) && is_file($full_path_old_sk)) @unlink($full_path_old_sk);
        }

        unset($_SESSION['form_data_cabor_edit']);
        $_SESSION['pesan_sukses_global'] = "Data Cabang Olahraga '" . htmlspecialchars($nama_cabor_new) . "' berhasil diperbarui.";
        header("Location: detail_cabor.php?id_cabor=" . $id_cabor_to_edit_val);
        exit();

    } catch (PDOException $e_update_cbr_db) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("PROSES_EDIT_CABOR_DB_ERROR: " . $e_update_cbr_db->getMessage());
        // Hapus file baru yang mungkin sudah terupload jika terjadi error DB
        if ($temp_new_sk_path_uploaded && defined('APP_PATH_BASE')) { @unlink(rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_new_sk_path_uploaded, '/\\')); }
        if ($temp_new_logo_path_uploaded && defined('APP_PATH_BASE')) { @unlink(rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_new_logo_path_uploaded, '/\\')); }
        
        $db_error_msg_cbr_user_edit = (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? " (" . $e_update_cbr_db->getMessage() . ")" : "";
        $_SESSION['errors_edit_cabor'] = ["Error Database: Gagal memperbarui data." . $db_error_msg_cbr_user_edit];
        header("Location: edit_cabor.php?id_cabor=" . $id_cabor_to_edit_val);
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Cabor tidak disertakan.";
    header("Location: " . $daftar_cabor_redirect_url_edit);
    exit();
}
?>