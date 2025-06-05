<?php
// File: reaktorsystem/modules/prestasi/proses_edit_prestasi.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/init_core.php');

// Aktifkan pelaporan error untuk debugging (hapus atau set ke 0 di produksi)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// 2. Pengecekan Akses & Session
if ($user_login_status !== true || !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor', 'atlet'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak atau sesi tidak valid untuk memproses data prestasi.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_proses = $user_nik;

// Pastikan PDO tersedia
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    $redirect_fallback_url = isset($_POST['id_prestasi']) ? "edit_prestasi.php?id_prestasi=" . htmlspecialchars($_POST['id_prestasi']) : "daftar_prestasi.php";
    header("Location: " . $redirect_fallback_url);
    exit();
}

$allowed_statuses_prestasi = ['pending', 'disetujui_pengcab', 'disetujui_admin', 'ditolak_pengcab', 'ditolak_admin', 'revisi'];

// Cek apakah ini dari form edit lengkap atau dari aksi cepat
$is_quick_action = isset($_POST['quick_action_approval_prestasi']) && $_POST['quick_action_approval_prestasi'] == '1';
$is_full_form_submit = isset($_POST['submit_edit_prestasi']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_prestasi']) && filter_var($_POST['id_prestasi'], FILTER_VALIDATE_INT) && ($is_quick_action || $is_full_form_submit)) {
    $id_prestasi_to_edit_proc = (int)$_POST['id_prestasi'];
    
    // 3. Ambil Data Prestasi Lama dari Database
    try {
        $stmt_old = $pdo->prepare("SELECT * FROM prestasi WHERE id_prestasi = :id_prestasi");
        $stmt_old->bindParam(':id_prestasi', $id_prestasi_to_edit_proc, PDO::PARAM_INT);
        $stmt_old->execute();
        $data_lama_prestasi_db = $stmt_old->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Proses Edit Prestasi - Gagal ambil data lama: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Gagal mengambil data prestasi yang akan diedit.";
        header("Location: daftar_prestasi.php");
        exit();
    }

    if (!$data_lama_prestasi_db) {
        $_SESSION['pesan_error_global'] = "Data prestasi yang akan diedit tidak ditemukan di database.";
        header("Location: daftar_prestasi.php");
        exit();
    }

    // 4. Validasi Hak Akses Edit/Proses
    $can_process_this_edit_prestasi = false;
    if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_process_this_edit_prestasi = true; } 
    elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $data_lama_prestasi_db['id_cabor']) { $can_process_this_edit_prestasi = true; } 
    elseif ($user_role_utama == 'atlet' && $user_nik_pelaku_proses == $data_lama_prestasi_db['nik']) {
        if ($is_full_form_submit && in_array($data_lama_prestasi_db['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) {
            $can_process_this_edit_prestasi = true; // Atlet bisa edit form jika status memungkinkan
        }
        // Atlet tidak bisa melakukan quick action approval
    }
    if (!$can_process_this_edit_prestasi) {
        $_SESSION['pesan_error_global'] = "Anda tidak diizinkan memproses perubahan untuk prestasi ini.";
        header("Location: daftar_prestasi.php");
        exit();
    }

    // 5. Ambil dan Bersihkan Data Baru dari Form
    $_SESSION['form_data_prestasi_edit'] = $_POST; // Simpan semua POST untuk diisi kembali jika error
    $errors_edit_prestasi = [];
    $error_fields_edit_prestasi = [];

    // Inisialisasi dengan data lama, akan ditimpa jika form lengkap disubmit
    $nik_atlet_input = $data_lama_prestasi_db['nik'];
    $id_cabor_input = $data_lama_prestasi_db['id_cabor'];
    $id_atlet_baru_db = $data_lama_prestasi_db['id_atlet'];
    $nama_kejuaraan_input = $data_lama_prestasi_db['nama_kejuaraan'];
    $tingkat_kejuaraan_input = $data_lama_prestasi_db['tingkat_kejuaraan'];
    $tahun_perolehan_input = $data_lama_prestasi_db['tahun_perolehan'];
    $medali_peringkat_input = $data_lama_prestasi_db['medali_peringkat'];
    $bukti_path_final_prestasi = $data_lama_prestasi_db['bukti_path'];
    
    $status_approval_input = $_POST['status_approval'] ?? $data_lama_prestasi_db['status_approval']; // Ini selalu dari POST jika aksi cepat
    $alasan_penolakan_pengcab_input = $_POST['alasan_penolakan_pengcab'] ?? $data_lama_prestasi_db['alasan_penolakan_pengcab'];
    $alasan_penolakan_admin_input = $_POST['alasan_penolakan_admin'] ?? $data_lama_prestasi_db['alasan_penolakan_admin'];
    
    if ($is_full_form_submit) {
        // Ambil data lengkap dari form jika ini bukan aksi cepat
        $nik_atlet_input = (in_array($user_role_utama, ['super_admin', 'admin_koni']) && isset($_POST['nik']) && !empty(trim($_POST['nik']))) ? trim($_POST['nik']) : $data_lama_prestasi_db['nik'];
        $id_cabor_input = (in_array($user_role_utama, ['super_admin', 'admin_koni']) && isset($_POST['id_cabor']) && !empty(trim($_POST['id_cabor']))) ? filter_var($_POST['id_cabor'], FILTER_SANITIZE_NUMBER_INT) : $data_lama_prestasi_db['id_cabor'];
        $nama_kejuaraan_input = trim($_POST['nama_kejuaraan'] ?? '');
        $tingkat_kejuaraan_input = trim($_POST['tingkat_kejuaraan'] ?? '');
        $tahun_perolehan_input = trim($_POST['tahun_perolehan'] ?? '');
        $medali_peringkat_input = trim($_POST['medali_peringkat'] ?? '');
        // status dan alasan sudah diambil di atas
    }

    // 6. Validasi Server Side
    // Validasi dasar untuk semua jenis submit (termasuk aksi cepat untuk status)
    if (empty($status_approval_input) || !in_array($status_approval_input, $allowed_statuses_prestasi)) {
        $errors_edit_prestasi[] = "Status approval yang dikirim tidak valid.";
        $error_fields_edit_prestasi[] = 'status_approval';
    }
    // Jika aksi cepat dan statusnya butuh alasan, alasan dari POST (via prompt JS) wajib ada
    if ($is_quick_action) {
        $approval_level_js = $_POST['approval_level'] ?? '';
        if (($status_approval_input == 'ditolak_pengcab' || ($status_approval_input == 'revisi' && $approval_level_js == 'pengcab')) && empty(trim($alasan_penolakan_pengcab_input))) {
            $errors_edit_prestasi[] = "Alasan penolakan/revisi dari Pengcab wajib diisi untuk status ini.";
            $error_fields_edit_prestasi[] = 'alasan_penolakan_pengcab'; // Meskipun tidak ada field di form, ini untuk notif
        }
        if (($status_approval_input == 'ditolak_admin' || ($status_approval_input == 'revisi' && $approval_level_js == 'admin')) && empty(trim($alasan_penolakan_admin_input))) {
            $errors_edit_prestasi[] = "Alasan penolakan/revisi dari Admin wajib diisi untuk status ini.";
            $error_fields_edit_prestasi[] = 'alasan_penolakan_admin';
        }
    }

    if ($is_full_form_submit) { // Validasi field lengkap hanya jika dari form edit
        if (empty($nik_atlet_input) || !preg_match('/^\d{16}$/', $nik_atlet_input)) { $errors_edit_prestasi[] = "NIK Atlet tidak valid."; $error_fields_edit_prestasi[] = 'nik';}
        if (empty($id_cabor_input) || !filter_var($id_cabor_input, FILTER_VALIDATE_INT)) { $errors_edit_prestasi[] = "Cabang Olahraga tidak valid."; $error_fields_edit_prestasi[] = 'id_cabor';}
        if (empty($nama_kejuaraan_input)) { $errors_edit_prestasi[] = "Nama Kejuaraan wajib diisi."; $error_fields_edit_prestasi[] = 'nama_kejuaraan'; }
        if (empty($tingkat_kejuaraan_input) || !in_array($tingkat_kejuaraan_input, ['Kabupaten','Provinsi','Nasional','Internasional'])) { $errors_edit_prestasi[] = "Tingkat Kejuaraan tidak valid."; $error_fields_edit_prestasi[] = 'tingkat_kejuaraan'; }
        if (empty($tahun_perolehan_input) || !filter_var($tahun_perolehan_input, FILTER_VALIDATE_INT) || $tahun_perolehan_input < 1900 || $tahun_perolehan_input > (date('Y') + 5)) { $errors_edit_prestasi[] = "Tahun Perolehan tidak valid."; $error_fields_edit_prestasi[] = 'tahun_perolehan'; }
        if (empty($medali_peringkat_input)) { $errors_edit_prestasi[] = "Medali/Peringkat wajib diisi."; $error_fields_edit_prestasi[] = 'medali_peringkat'; }

        if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && ($nik_atlet_input != $data_lama_prestasi_db['nik'] || $id_cabor_input != $data_lama_prestasi_db['id_cabor'])) {
            try { /* ... (validasi NIK & Cabor baru jika diubah Admin) ... */ } catch (PDOException $e) { /* ... */ }
        }
    }
    
    $temp_new_bukti_path = null;
    if ($is_full_form_submit && empty($errors_edit_prestasi) && isset($_FILES['bukti_path']) && $_FILES['bukti_path']['error'] == UPLOAD_ERR_OK && $_FILES['bukti_path']['size'] > 0) {
        $temp_new_bukti_path = uploadFileGeneral('bukti_path', 'bukti_prestasi', 'bukti_' . $nik_atlet_input, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_BUKTI_PRESTASI_MB, $errors_edit_prestasi, $data_lama_prestasi_db['bukti_path'], false);
        if ($temp_new_bukti_path !== null) {
            $bukti_path_final_prestasi = $temp_new_bukti_path;
        } elseif (!empty($_FILES['bukti_path']['name'])) { 
            $error_fields_edit_prestasi[] = 'bukti_path';
        }
    }

    if (!empty($errors_edit_prestasi)) {
        $_SESSION['errors_prestasi_edit'] = $errors_edit_prestasi;
        $_SESSION['error_fields_prestasi_edit'] = array_unique($error_fields_edit_prestasi);
        if ($temp_new_bukti_path && $temp_new_bukti_path !== $data_lama_prestasi_db['bukti_path'] && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_new_bukti_path)) {
            @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_new_bukti_path);
        }
        header("Location: edit_prestasi.php?id_prestasi=" . $id_prestasi_to_edit_proc);
        exit();
    }

    // 8. Logika Status Approval dan Data yang Akan Diupdate
    $current_timestamp_update = date('Y-m-d H:i:s');
    $new_approved_by_nik_pengcab = $data_lama_prestasi_db['approved_by_nik_pengcab'];
    $new_approval_at_pengcab = $data_lama_prestasi_db['approval_at_pengcab'];
    $new_alasan_penolakan_pengcab = $data_lama_prestasi_db['alasan_penolakan_pengcab']; // Default ke data lama
    $new_approved_by_nik_admin = $data_lama_prestasi_db['approved_by_nik_admin'];
    $new_approval_at_admin = $data_lama_prestasi_db['approval_at_admin'];
    $new_alasan_penolakan_admin = $data_lama_prestasi_db['alasan_penolakan_admin']; // Default ke data lama
    
    $status_approval_final_db = $status_approval_input; // Ini adalah status yang dikirim dari form/JS

    $data_deskriptif_berubah_oleh_user = false; // Hanya true jika user edit form lengkap
    if ($is_full_form_submit) {
        if ($nik_atlet_input != $data_lama_prestasi_db['nik'] || $id_cabor_input != $data_lama_prestasi_db['id_cabor'] ||
            $nama_kejuaraan_input != $data_lama_prestasi_db['nama_kejuaraan'] || $tingkat_kejuaraan_input != $data_lama_prestasi_db['tingkat_kejuaraan'] ||
            $tahun_perolehan_input != $data_lama_prestasi_db['tahun_perolehan'] || $medali_peringkat_input != $data_lama_prestasi_db['medali_peringkat'] ||
            $bukti_path_final_prestasi != $data_lama_prestasi_db['bukti_path']) {
            $data_deskriptif_berubah_oleh_user = true;
        }
    }

    // Logika penentuan siapa yang approve/reject dan kapan
    if ($user_role_utama == 'atlet' && $is_full_form_submit && $data_deskriptif_berubah_oleh_user) {
        $status_approval_final_db = 'pending'; // Jika atlet edit, status kembali ke pending
        $new_approved_by_nik_pengcab = null; $new_approval_at_pengcab = null; $new_alasan_penolakan_pengcab = null;
        $new_approved_by_nik_admin = null; $new_approval_at_admin = null; $new_alasan_penolakan_admin = null;
    } elseif ($user_role_utama == 'pengurus_cabor') {
        if ($is_quick_action) { // Dari tombol aksi cepat
            if ($status_approval_input == 'disetujui_pengcab') {
                $new_approved_by_nik_pengcab = $user_nik_pelaku_proses; $new_approval_at_pengcab = $current_timestamp_update; $new_alasan_penolakan_pengcab = null;
                $new_approved_by_nik_admin = null; $new_approval_at_admin = null; $new_alasan_penolakan_admin = null; // Reset admin approval
            } elseif ($status_approval_input == 'ditolak_pengcab') {
                $new_approved_by_nik_pengcab = $user_nik_pelaku_proses; $new_approval_at_pengcab = $current_timestamp_update; $new_alasan_penolakan_pengcab = trim($alasan_penolakan_pengcab_input);
            }
        } elseif ($is_full_form_submit && $data_deskriptif_berubah_oleh_user) { // Dari form edit oleh pengcab
            $status_approval_final_db = 'disetujui_pengcab'; // Perlu approval ulang admin
            $new_approved_by_nik_pengcab = $user_nik_pelaku_proses; $new_approval_at_pengcab = $current_timestamp_update; $new_alasan_penolakan_pengcab = null;
            $new_approved_by_nik_admin = null; $new_approval_at_admin = null; $new_alasan_penolakan_admin = null;
        }
    } elseif (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        if ($status_approval_input != $data_lama_prestasi_db['status_approval'] || $data_deskriptif_berubah_oleh_user) { // Ada perubahan status atau data oleh admin
            if ($status_approval_input == 'disetujui_admin') {
                $new_approved_by_nik_admin = $user_nik_pelaku_proses; $new_approval_at_admin = $current_timestamp_update; $new_alasan_penolakan_admin = null;
                if (empty($new_approved_by_nik_pengcab)) { // Jika belum diapprove pengcab, admin juga approve sbg pengcab
                    $new_approved_by_nik_pengcab = $user_nik_pelaku_proses; $new_approval_at_pengcab = $current_timestamp_update;
                }
                $new_alasan_penolakan_pengcab = null;
            } elseif ($status_approval_input == 'ditolak_admin') {
                $new_approved_by_nik_admin = $user_nik_pelaku_proses; $new_approval_at_admin = $current_timestamp_update; $new_alasan_penolakan_admin = trim($alasan_penolakan_admin_input);
            } elseif ($status_approval_input == 'revisi') {
                 $new_alasan_penolakan_admin = trim($alasan_penolakan_admin_input); // Admin yang minta revisi
            } elseif ($status_approval_input == 'disetujui_pengcab') { // Admin bisa set ke status ini
                 $new_approved_by_nik_pengcab = $user_nik_pelaku_proses; $new_approval_at_pengcab = $current_timestamp_update; $new_alasan_penolakan_pengcab = trim($alasan_penolakan_pengcab_input);
                 $new_approved_by_nik_admin = null; $new_approval_at_admin = null; $new_alasan_penolakan_admin = null;
            } elseif ($status_approval_input == 'ditolak_pengcab') {
                 $new_approved_by_nik_pengcab = $user_nik_pelaku_proses; $new_approval_at_pengcab = $current_timestamp_update; $new_alasan_penolakan_pengcab = trim($alasan_penolakan_pengcab_input);
                 $new_approved_by_nik_admin = null; $new_approval_at_admin = null; $new_alasan_penolakan_admin = null;
            } elseif ($status_approval_input == 'pending') {
                $new_approved_by_nik_pengcab = null; $new_approval_at_pengcab = null; $new_alasan_penolakan_pengcab = null;
                $new_approved_by_nik_admin = null; $new_approval_at_admin = null; $new_alasan_penolakan_admin = null;
            }
        }
    }


    // 9. Update ke Database
    try {
        $pdo->beginTransaction();
        // ... (SQL UPDATE dan bindParam seperti versi sebelumnya, pastikan field dan variabelnya sesuai)
        $sql_update_prestasi = "UPDATE prestasi SET 
                                nik = :nik, id_cabor = :id_cabor, id_atlet = :id_atlet, 
                                nama_kejuaraan = :nama_kejuaraan, tingkat_kejuaraan = :tingkat_kejuaraan, tahun_perolehan = :tahun_perolehan, 
                                medali_peringkat = :medali_peringkat, bukti_path = :bukti_path, 
                                status_approval = :status_approval, 
                                approved_by_nik_pengcab = :approved_by_nik_pengcab, approval_at_pengcab = :approval_at_pengcab, alasan_penolakan_pengcab = :alasan_penolakan_pengcab,
                                approved_by_nik_admin = :approved_by_nik_admin, approval_at_admin = :approval_at_admin, alasan_penolakan_admin = :alasan_penolakan_admin,
                                updated_by_nik = :updated_by_nik, last_updated_process_at = :last_updated_process_at
                            WHERE id_prestasi = :id_prestasi_where";
        $stmt_update = $pdo->prepare($sql_update_prestasi);
        
        $stmt_update->bindParam(':nik', $nik_atlet_input, PDO::PARAM_STR);
        $stmt_update->bindParam(':id_cabor', $id_cabor_input, PDO::PARAM_INT);
        $stmt_update->bindParam(':id_atlet', $id_atlet_baru_db, ($id_atlet_baru_db === null || $id_atlet_baru_db === '') ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt_update->bindParam(':nama_kejuaraan', $nama_kejuaraan_input, PDO::PARAM_STR);
        $stmt_update->bindParam(':tingkat_kejuaraan', $tingkat_kejuaraan_input, PDO::PARAM_STR);
        $stmt_update->bindParam(':tahun_perolehan', $tahun_perolehan_input, PDO::PARAM_INT);
        $stmt_update->bindParam(':medali_peringkat', $medali_peringkat_input, PDO::PARAM_STR);
        $stmt_update->bindParam(':bukti_path', $bukti_path_final_prestasi, ($bukti_path_final_prestasi === null || $bukti_path_final_prestasi === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':status_approval', $status_approval_final_db, PDO::PARAM_STR);
        
        $stmt_update->bindParam(':approved_by_nik_pengcab', $new_approved_by_nik_pengcab, ($new_approved_by_nik_pengcab === null || $new_approved_by_nik_pengcab === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':approval_at_pengcab', $new_approval_at_pengcab, ($new_approval_at_pengcab === null || $new_approval_at_pengcab === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':alasan_penolakan_pengcab', $new_alasan_penolakan_pengcab, ($new_alasan_penolakan_pengcab === null || $new_alasan_penolakan_pengcab === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':approved_by_nik_admin', $new_approved_by_nik_admin, ($new_approved_by_nik_admin === null || $new_approved_by_nik_admin === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':approval_at_admin', $new_approval_at_admin, ($new_approval_at_admin === null || $new_approval_at_admin === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':alasan_penolakan_admin', $new_alasan_penolakan_admin, ($new_alasan_penolakan_admin === null || $new_alasan_penolakan_admin === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        $stmt_update->bindParam(':updated_by_nik', $user_nik_pelaku_proses, PDO::PARAM_STR);
        $stmt_update->bindParam(':last_updated_process_at', $current_timestamp_update, PDO::PARAM_STR);
        $stmt_update->bindParam(':id_prestasi_where', $id_prestasi_to_edit_proc, PDO::PARAM_INT);
        
        $stmt_update->execute();

        // 10. Audit Log
        $stmt_new_data = $pdo->prepare("SELECT * FROM prestasi WHERE id_prestasi = :id_prestasi");
        $stmt_new_data->bindParam(':id_prestasi', $id_prestasi_to_edit_proc, PDO::PARAM_INT);
        $stmt_new_data->execute();
        $data_baru_prestasi_for_log = $stmt_new_data->fetch(PDO::FETCH_ASSOC);

        $aksi_log_edit_prestasi = "EDIT DATA PRESTASI";
        if ($status_approval_final_db != $data_lama_prestasi_db['status_approval']) {
            $aksi_log_edit_prestasi = "UPDATE STATUS PRESTASI (MENJADI " . strtoupper($status_approval_final_db) . ")";
        }
        
        $log_stmt_edit = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_lama, :data_baru, :keterangan)");
        $log_stmt_edit->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => $aksi_log_edit_prestasi,
            ':tabel' => 'prestasi',
            ':id_data' => $id_prestasi_to_edit_proc,
            ':data_lama' => json_encode($data_lama_prestasi_db),
            ':data_baru' => json_encode($data_baru_prestasi_for_log),
            ':keterangan' => 'Perubahan data prestasi: ' . htmlspecialchars($nama_kejuaraan_input) . ($is_quick_action ? ' (Aksi Cepat)' : '')
        ]);

        $pdo->commit();
        unset($_SESSION['form_data_prestasi_edit']);
        $_SESSION['pesan_sukses_global'] = "Data prestasi '" . htmlspecialchars($nama_kejuaraan_input) . "' berhasil diperbarui.";
        
        $redirect_params_daftar_sukses = [];
        if (!empty($id_cabor_input)) $redirect_params_daftar_sukses['id_cabor'] = $id_cabor_input;
        if (!empty($nik_atlet_input)) $redirect_params_daftar_sukses['nik_atlet'] = $nik_atlet_input;
        $query_string_daftar_sukses = !empty($redirect_params_daftar_sukses) ? '?' . http_build_query($redirect_params_daftar_sukses) : '';
        header("Location: daftar_prestasi.php" . $query_string_daftar_sukses);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Edit Prestasi - DB Execute Error: " . $e->getMessage());
        if ($temp_new_bukti_path && $temp_new_bukti_path !== $data_lama_prestasi_db['bukti_path'] && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_new_bukti_path)) {
            @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_new_bukti_path);
        }
        
        $_SESSION['errors_prestasi_edit'] = ["Terjadi kesalahan teknis saat memperbarui data."]; // Pesan lebih umum untuk user
        // Pesan spesifik bisa untuk log atau debug internal
        // if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false) {
        //      $_SESSION['errors_prestasi_edit'] = ["Prestasi dengan detail serupa mungkin sudah ada."];
        // }
        
        header("Location: edit_prestasi.php?id_prestasi=" . $id_prestasi_to_edit_proc);
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau parameter ID Prestasi tidak lengkap.";
    header("Location: daftar_prestasi.php");
    exit();
}
?>