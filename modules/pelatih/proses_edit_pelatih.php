<?php
// File: reaktorsystem/modules/pelatih/proses_edit_pelatih.php (REVISI dengan uploadFileGeneral & catatAuditLog dari kode Anda)

if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $_SESSION['pesan_error_global'] = "Kesalahan konfigurasi sistem inti: init_core.php tidak ditemukan.";
    error_log("PROSES_EDIT_PROFIL_PELATIH_FATAL: init_core.php tidak ditemukan.");
    $fallback_redirect_edit_pp = isset($app_base_path) ? rtrim($app_base_path, '/') . '/dashboard.php' : '../../dashboard.php';
    header("Location: " . $fallback_redirect_edit_pp);
    exit();
}

// Konstanta ukuran file untuk foto profil pelatih
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB')) { define('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB', 1); }
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_BYTES')) { define('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_BYTES', MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB * 1024 * 1024); }

// Pengecekan Akses & Sesi
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik) ||
    !in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Hanya Administrator yang dapat memproses data profil pelatih.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['form_data_profil_pelatih_edit_'.($_POST['id_pelatih'] ?? 'err')] = $_POST ?? [];
    $_SESSION['errors_profil_pelatih_edit_'.($_POST['id_pelatih'] ?? 'err')] = ["Koneksi Database Gagal!"];
    error_log("PROSES_EDIT_PROFIL_PELATIH_ERROR: PDO tidak valid.");
    $id_pelatih_redirect_err_pdo_edit = $_POST['id_pelatih'] ?? null;
    header("Location: " . rtrim($app_base_path, '/') . "/modules/pelatih/edit_pelatih.php" . ($id_pelatih_redirect_err_pdo_edit ? '?id_pelatih='.$id_pelatih_redirect_err_pdo_edit : '' ));
    exit();
}

$redirect_list_pelatih = rtrim($app_base_path, '/') . "/modules/pelatih/daftar_pelatih.php";
$default_redirect_error_page_edit_pp = $redirect_list_pelatih; // Default ke daftar jika ID tidak ada
if (isset($_POST['id_pelatih']) && filter_var($_POST['id_pelatih'], FILTER_VALIDATE_INT)) {
    $id_pelatih_to_process = (int)$_POST['id_pelatih'];
    $default_redirect_error_page_edit_pp = rtrim($app_base_path, '/') . "/modules/pelatih/edit_pelatih.php?id_pelatih=" . $id_pelatih_to_process;
} else {
     $_SESSION['pesan_error_global'] = "ID Profil Pelatih tidak valid atau tidak disertakan.";
    header("Location: " . $redirect_list_pelatih);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') { // ID Pelatih sudah divalidasi di atas
    
    $stmt_old_pp_data = $pdo->prepare("SELECT plt.*, p.nama_lengkap 
                                     FROM pelatih plt 
                                     JOIN pengguna p ON plt.nik = p.nik 
                                     WHERE plt.id_pelatih = :id_pelatih");
    $stmt_old_pp_data->bindParam(':id_pelatih', $id_pelatih_to_process, PDO::PARAM_INT);
    $stmt_old_pp_data->execute();
    $data_lama_profil_pelatih = $stmt_old_pp_data->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_profil_pelatih) {
        $_SESSION['pesan_error_global'] = "Profil Pelatih yang akan diproses tidak ditemukan.";
        header("Location: " . $redirect_list_pelatih);
        exit();
    }
    $data_lama_profil_pelatih_json = json_encode($data_lama_profil_pelatih);

    $errors_edit_pp = [];
    $error_fields_edit_pp = [];
    $data_to_update_profil = [];
    $new_foto_profil_pelatih_db = $data_lama_profil_pelatih['foto_pelatih_profil'];
    $base_upload_dir_proses_edit_pp = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/');
    $current_datetime_proses = date('Y-m-d H:i:s');

    if (isset($_POST['submit_edit_profil_pelatih'])) {
        $_SESSION['form_data_profil_pelatih_edit_' . $id_pelatih_to_process] = $_POST;

        $kontak_alternatif_form = isset($_POST['kontak_pelatih_alternatif']) ? trim($_POST['kontak_pelatih_alternatif']) : null;
        $status_approval_profil_form = $_POST['status_approval'] ?? $data_lama_profil_pelatih['status_approval'];
        $alasan_penolakan_profil_form = isset($_POST['alasan_penolakan']) ? trim($_POST['alasan_penolakan']) : null;

        $allowed_statuses_profil_edit = ['pending', 'disetujui', 'ditolak', 'revisi'];
        if (!in_array($status_approval_profil_form, $allowed_statuses_profil_edit)) {
            $errors_edit_pp[] = "Status approval profil tidak valid.";
            $error_fields_edit_pp['status_approval'] = true;
        }
        if (in_array($status_approval_profil_form, ['ditolak', 'revisi']) && empty($alasan_penolakan_profil_form)) {
            $errors_edit_pp[] = "Alasan penolakan/revisi wajib diisi jika status adalah 'Ditolak' atau 'Revisi'.";
            $error_fields_edit_pp['alasan_penolakan'] = true;
        }

        if (isset($_FILES['foto_pelatih_profil']) && $_FILES['foto_pelatih_profil']['error'] == UPLOAD_ERR_OK && $_FILES['foto_pelatih_profil']['size'] > 0) {
            // Menggunakan logika uploadFileGeneral dari kode Anda
            $new_foto_uploaded = uploadFileGeneral('foto_pelatih_profil', 'foto_profil_pelatih', 'fotoprofil_pelatih_' . $data_lama_profil_pelatih['nik'] . '_' . time(), ['jpg', 'jpeg', 'png'], MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB, $errors_edit_pp, $data_lama_profil_pelatih['foto_pelatih_profil']);
            if ($new_foto_uploaded) {
                $new_foto_profil_pelatih_db = $new_foto_uploaded;
            } elseif (!isset($error_fields_edit_pp['foto_pelatih_profil'])) {
                // $errors_edit_pp sudah diisi oleh uploadFileGeneral jika gagal
                if(empty($errors_edit_pp['foto_pelatih_profil'])) $errors_edit_pp[] = "Gagal memproses upload Foto Profil Pelatih.";
                $error_fields_edit_pp['foto_pelatih_profil'] = true;
            }
        } elseif (isset($_FILES['foto_pelatih_profil']) && $_FILES['foto_pelatih_profil']['error'] != UPLOAD_ERR_NO_FILE && !isset($error_fields_edit_pp['foto_pelatih_profil'])) {
            $errors_edit_pp[] = "Terjadi masalah saat upload Foto Profil (Error Code: ".$_FILES['foto_pelatih_profil']['error'].").";
            $error_fields_edit_pp['foto_pelatih_profil'] = true;
        }

        if (empty($errors_edit_pp)) {
            if ($kontak_alternatif_form !== $data_lama_profil_pelatih['kontak_pelatih_alternatif']) { $data_to_update_profil['kontak_pelatih_alternatif'] = $kontak_alternatif_form; }
            if ($new_foto_profil_pelatih_db !== $data_lama_profil_pelatih['foto_pelatih_profil']) { $data_to_update_profil['foto_pelatih_profil'] = $new_foto_profil_pelatih_db; }
            
            if ($status_approval_profil_form != $data_lama_profil_pelatih['status_approval']) {
                $data_to_update_profil['status_approval'] = $status_approval_profil_form;
                $data_to_update_profil['approved_by_nik'] = $user_nik;
                $data_to_update_profil['approval_at'] = $current_datetime_proses;
                $data_to_update_profil['alasan_penolakan'] = (in_array($status_approval_profil_form, ['ditolak', 'revisi'])) ? $alasan_penolakan_profil_form : null;
            } elseif (in_array($status_approval_profil_form, ['ditolak', 'revisi']) && $alasan_penolakan_profil_form != $data_lama_profil_pelatih['alasan_penolakan']) {
                 $data_to_update_profil['alasan_penolakan'] = $alasan_penolakan_profil_form;
            }
        }
    }
    elseif (isset($_POST['quick_action_approval_profil']) && in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        $status_quick_action_profil = $_POST['status_approval'] ?? null;
        $alasan_quick_action_profil = isset($_POST['alasan_penolakan']) ? trim($_POST['alasan_penolakan']) : null;
        $allowed_statuses_profil_quick = ['pending', 'disetujui', 'ditolak', 'revisi'];

        if (in_array($status_quick_action_profil, $allowed_statuses_profil_quick)) {
            $data_to_update_profil['status_approval'] = $status_quick_action_profil;
            $data_to_update_profil['approved_by_nik'] = $user_nik;
            $data_to_update_profil['approval_at'] = $current_datetime_proses;
            $data_to_update_profil['alasan_penolakan'] = (in_array($status_quick_action_profil, ['ditolak', 'revisi'])) ? $alasan_quick_action_profil : null;
            if (in_array($status_quick_action_profil, ['ditolak', 'revisi']) && empty($alasan_quick_action_profil)) {
                $errors_edit_pp[] = "Alasan/Catatan wajib diisi untuk status " . ucfirst($status_quick_action_profil) . ".";
            }
        } else {
            $errors_edit_pp[] = "Status approval profil tidak valid untuk aksi cepat.";
        }
    } else {
        $_SESSION['pesan_error_global'] = "Aksi tidak dikenali atau tidak diizinkan.";
        header("Location: " . $default_redirect_error_page_edit_pp);
        exit();
    }


    if (!empty($errors_edit_pp)) {
        $_SESSION['errors_profil_pelatih_edit_' . $id_pelatih_to_process] = $errors_edit_pp;
        $_SESSION['error_fields_profil_pelatih_edit_' . $id_pelatih_to_process] = array_unique($error_fields_edit_pp);
        if ($new_foto_profil_pelatih_db != $data_lama_profil_pelatih['foto_pelatih_profil'] && $new_foto_profil_pelatih_db && file_exists($base_upload_dir_proses_edit_pp . '/' . ltrim($new_foto_profil_pelatih_db, '/'))) {
            @unlink($base_upload_dir_proses_edit_pp . '/' . ltrim($new_foto_profil_pelatih_db, '/'));
        }
        header("Location: " . $default_redirect_error_page_edit_pp);
        exit();
    }

    if (!empty($data_to_update_profil)) {
        $data_to_update_profil['updated_by_nik'] = $user_nik;
        // $data_to_update_profil['last_updated_process_at'] = $current_datetime_proses; // Kolom ini sudah tidak ada di tabel pelatih baru
        
        try {
            $pdo->beginTransaction();
            $update_fields_sql_pp = [];
            foreach (array_keys($data_to_update_profil) as $field) {
                $update_fields_sql_pp[] = "`{$field}` = :{$field}";
            }
            // Kolom updated_at akan diupdate otomatis oleh MySQL `ON UPDATE CURRENT_TIMESTAMP`
            // Jika tidak ada ON UPDATE, maka tambahkan `updated_at = NOW()` ke $update_fields_sql_pp
            // atau set $data_to_update_profil['updated_at'] = $current_datetime_proses;

            $sql_update_pp = "UPDATE pelatih SET " . implode(", ", $update_fields_sql_pp) . " WHERE id_pelatih = :id_pelatih_pk";
            
            $stmt_update_pp = $pdo->prepare($sql_update_pp);
            $data_to_update_profil_final = $data_to_update_profil;
            $data_to_update_profil_final['id_pelatih_pk'] = $id_pelatih_to_process;
            
            $stmt_update_pp->execute($data_to_update_profil_final);

            // Audit Log (menggunakan logika dari kode Anda)
            $stmt_new_pelatih_log_after_update = $pdo->prepare("SELECT * FROM pelatih WHERE id_pelatih = :id_pelatih");
            $stmt_new_pelatih_log_after_update->bindParam(':id_pelatih', $id_pelatih_to_process, PDO::PARAM_INT);
            $stmt_new_pelatih_log_after_update->execute();
            $data_baru_pelatih_for_log = $stmt_new_pelatih_log_after_update->fetch(PDO::FETCH_ASSOC);
            
            $aksi_log_pp = isset($_POST['quick_action_approval_profil']) ? "QUICK ACTION PROFIL PELATIH" : "EDIT PROFIL PELATIH";
            if (isset($data_to_update_profil['status_approval']) && $data_to_update_profil['status_approval'] != $data_lama_profil_pelatih['status_approval']) {
                $aksi_log_pp .= " (STATUS -> " . strtoupper($data_to_update_profil['status_approval']) . ")";
            }
            $keterangan_log_pp = "Memperbarui profil pelatih NIK: " . $data_lama_profil_pelatih['nik'] . " (" . htmlspecialchars($data_lama_profil_pelatih['nama_lengkap']) . ").";

            // Pastikan fungsi catatAuditLog ada atau gunakan cara manual seperti di kode Anda
            if (function_exists('catatAuditLog')) {
                 catatAuditLog($user_nik, $aksi_log_pp, 'pelatih', $id_pelatih_to_process, $data_lama_profil_pelatih_json, json_encode($data_baru_pelatih_for_log), $keterangan_log_pp, $pdo);
            } else {
                // Fallback ke cara manual jika fungsi tidak ada
                $log_stmt_pp_edit = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:un, :a, 'pelatih', :id, :dl, :db, :ket)");
                $log_stmt_pp_edit->execute([
                    ':un' => $user_nik, 
                    ':a' => $aksi_log_pp, 
                    ':id' => $id_pelatih_to_process, 
                    ':dl' => $data_lama_profil_pelatih_json,
                    ':db' => json_encode($data_baru_pelatih_for_log), 
                    ':ket' => $keterangan_log_pp
                ]);
            }

            $pdo->commit();
            unset($_SESSION['form_data_profil_pelatih_edit_' . $id_pelatih_to_process]);
            $_SESSION['pesan_sukses_global'] = "Profil Pelatih '" . htmlspecialchars($data_lama_profil_pelatih['nama_lengkap']) . "' berhasil diperbarui.";
            header("Location: " . $redirect_list_pelatih);
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($new_foto_profil_pelatih_db != $data_lama_profil_pelatih['foto_pelatih_profil'] && $new_foto_profil_pelatih_db && file_exists($base_upload_dir_proses_edit_pp . '/' . ltrim($new_foto_profil_pelatih_db, '/'))) {
                @unlink($base_upload_dir_proses_edit_pp . '/' . ltrim($new_foto_profil_pelatih_db, '/'));
            }
            error_log("PROSES_EDIT_PROFIL_PELATIH_DB_ERROR: " . $e->getMessage());
            $_SESSION['errors_profil_pelatih_edit_' . $id_pelatih_to_process] = ["Database Error: Gagal memperbarui data."]; // Pesan lebih umum
            header("Location: " . $default_redirect_error_page_edit_pp);
            exit();
        }
    } else {
        $_SESSION['pesan_info_global'] = "Tidak ada perubahan data yang dilakukan pada profil pelatih.";
        header("Location: " . $default_redirect_error_page_edit_pp);
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau data yang dibutuhkan tidak lengkap.";
    header("Location: " . $redirect_list_pelatih);
    exit();
}

// Tidak ada require_once footer.php di sini
?>