<?php
// File: reaktorsystem/modules/wasit/proses_edit_wasit.php

// Inisialisasi inti (termasuk session_start(), koneksi DB, variabel global)
require_once(__DIR__ . '/../../core/init_core.php');

// Pengecekan Akses & Sesi Pengguna
if (!isset($user_nik) || !isset($user_role_utama) || 
    !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk memproses data wasit.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Definisi konstanta ukuran file jika belum ada (sebaiknya ini di init_core.php)
if (!defined('MAX_FILE_SIZE_KTP_KK_WASIT_MB')) { define('MAX_FILE_SIZE_KTP_KK_WASIT_MB', 2); }
if (!defined('MAX_FILE_SIZE_LISENSI_WASIT_MB')) { define('MAX_FILE_SIZE_LISENSI_WASIT_MB', 2); }
if (!defined('MAX_FILE_SIZE_FOTO_WASIT_MB')) { define('MAX_FILE_SIZE_FOTO_WASIT_MB', 1); }
if (!defined('MAX_FILE_SIZE_KTP_KK_WASIT_BYTES')) { define('MAX_FILE_SIZE_KTP_KK_WASIT_BYTES', MAX_FILE_SIZE_KTP_KK_WASIT_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_LISENSI_WASIT_BYTES')) { define('MAX_FILE_SIZE_LISENSI_WASIT_BYTES', MAX_FILE_SIZE_LISENSI_WASIT_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_FOTO_WASIT_BYTES')) { define('MAX_FILE_SIZE_FOTO_WASIT_BYTES', MAX_FILE_SIZE_FOTO_WASIT_MB * 1024 * 1024); }


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_wasit']) || !isset($_POST['nik_wasit_original'])) {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau data tidak lengkap.";
    header("Location: daftar_wasit.php");
    exit();
}

$id_wasit_edit = filter_var($_POST['id_wasit'], FILTER_SANITIZE_NUMBER_INT);
$nik_wasit_db_original = trim($_POST['nik_wasit_original']);

// --- Ambil data lama wasit untuk perbandingan dan audit log ---
try {
    $stmt_old_w = $pdo->prepare("SELECT w.*, p.nama_lengkap FROM wasit w JOIN pengguna p ON w.nik = p.nik WHERE w.id_wasit = :id_wasit AND w.nik = :nik");
    $stmt_old_w->bindParam(':id_wasit', $id_wasit_edit, PDO::PARAM_INT);
    $stmt_old_w->bindParam(':nik', $nik_wasit_db_original, PDO::PARAM_STR);
    $stmt_old_w->execute();
    $data_lama_w = $stmt_old_w->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_w) {
        $_SESSION['pesan_error_global'] = "Data Wasit yang akan diedit tidak ditemukan.";
        header("Location: daftar_wasit.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Proses Edit Wasit - Gagal ambil data lama: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data wasit. Silakan coba lagi.";
    header("Location: edit_wasit.php?id_wasit=" . $id_wasit_edit);
    exit();
}

// Pembatasan akses untuk pengurus cabor
if ($user_role_utama === 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) != $data_lama_w['id_cabor']) {
    $_SESSION['pesan_error_global'] = "Anda tidak diizinkan mengedit data wasit dari cabang olahraga lain.";
    header("Location: daftar_wasit.php" . ($id_cabor_pengurus_utama ? "?id_cabor=" . $id_cabor_pengurus_utama : ""));
    exit();
}

// --- Ambil dan Validasi Data dari Form ---
$form_data_input_w = $_POST; // Untuk repopulate jika error
$_SESSION['form_data_wasit_edit'] = $form_data_input_w; // Simpan untuk repopulate
$errors_w_edit = [];
$error_fields_w_edit = [];

$id_cabor_w = (in_array($user_role_utama, ['super_admin', 'admin_koni'])) 
              ? filter_var($form_data_input_w['id_cabor'] ?? $data_lama_w['id_cabor'], FILTER_SANITIZE_NUMBER_INT) 
              : $data_lama_w['id_cabor'];
$nomor_lisensi_w = isset($form_data_input_w['nomor_lisensi']) ? trim($form_data_input_w['nomor_lisensi']) : '';
$kontak_wasit_w = isset($form_data_input_w['kontak_wasit']) ? trim($form_data_input_w['kontak_wasit']) : '';
$status_approval_form_w = $form_data_input_w['status_approval'] ?? $data_lama_w['status_approval'];
$alasan_penolakan_form_w = isset($form_data_input_w['alasan_penolakan']) ? trim($form_data_input_w['alasan_penolakan']) : '';

// Path file lama dari input hidden
$ktp_path_lama_w = $_POST['ktp_path_lama'] ?? ($data_lama_w['ktp_path'] ?? null);
$kk_path_lama_w = $_POST['kk_path_lama'] ?? ($data_lama_w['kk_path'] ?? null);
$lisensi_path_lama_w = $_POST['path_file_lisensi_lama'] ?? ($data_lama_w['path_file_lisensi'] ?? null);
$foto_path_lama_w = $_POST['foto_wasit_lama'] ?? ($data_lama_w['foto_wasit'] ?? null);

// Validasi input
if (empty($id_cabor_w)) { 
    $errors_w_edit[] = "Cabang Olahraga wajib dipilih."; 
    $error_fields_w_edit[] = 'id_cabor';
}
// Tambahkan validasi lain jika perlu (misal, nomor lisensi unik per cabor, dll.)

// Validasi untuk alasan penolakan jika statusnya adalah ditolak atau revisi (khusus admin)
if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && in_array($status_approval_form_w, ['ditolak', 'revisi']) && empty($alasan_penolakan_form_w)) {
    $errors_w_edit[] = "Alasan penolakan/revisi wajib diisi jika status adalah 'Ditolak' atau 'Perlu Revisi'.";
    $error_fields_w_edit[] = 'alasan_penolakan';
}


// --- Proses Upload File ---
// Inisialisasi path file baru dengan path lama sebagai default
$ktp_path_final_w = $ktp_path_lama_w;
$kk_path_final_w = $kk_path_lama_w;
$lisensi_path_final_w = $lisensi_path_lama_w;
$foto_path_final_w = $foto_path_lama_w;

$upload_dir_base_wasit = "assets/uploads/wasit/"; // Atau path yang lebih spesifik jika perlu

if (empty($errors_w_edit)) { // Hanya proses upload jika tidak ada error validasi awal
    // KTP
    if (isset($_FILES['ktp_path']) && $_FILES['ktp_path']['error'] == UPLOAD_ERR_OK) {
        $ktp_path_final_w = uploadFileGeneral('ktp_path', $upload_dir_base_wasit . "ktp/", 'ktp_wasit_' . $nik_wasit_db_original, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_KTP_KK_WASIT_MB, $errors_w_edit, $ktp_path_lama_w);
    }
    // KK
    if (isset($_FILES['kk_path']) && $_FILES['kk_path']['error'] == UPLOAD_ERR_OK) {
        $kk_path_final_w = uploadFileGeneral('kk_path', $upload_dir_base_wasit . "kk/", 'kk_wasit_' . $nik_wasit_db_original, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_KTP_KK_WASIT_MB, $errors_w_edit, $kk_path_lama_w);
    }
    // Lisensi
    if (isset($_FILES['path_file_lisensi']) && $_FILES['path_file_lisensi']['error'] == UPLOAD_ERR_OK) {
        $lisensi_path_final_w = uploadFileGeneral('path_file_lisensi', $upload_dir_base_wasit . "lisensi/", 'lisensi_wasit_' . $nik_wasit_db_original, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_LISENSI_WASIT_MB, $errors_w_edit, $lisensi_path_lama_w);
    }
    // Foto Wasit
    if (isset($_FILES['foto_wasit']) && $_FILES['foto_wasit']['error'] == UPLOAD_ERR_OK) {
        $foto_path_final_w = uploadFileGeneral('foto_wasit', $upload_dir_base_wasit . "foto/", 'foto_wasit_' . $nik_wasit_db_original, ['jpg', 'jpeg', 'png'], MAX_FILE_SIZE_FOTO_WASIT_MB, $errors_w_edit, $foto_path_lama_w);
    }
}

if (!empty($errors_w_edit)) {
    $_SESSION['errors_wasit_edit'] = $errors_w_edit;
    $_SESSION['error_fields_wasit_edit'] = $error_fields_w_edit;
    // Tidak perlu $_SESSION['pesan_error_global'] karena sudah ada $form_errors_w_edit di form
    header("Location: edit_wasit.php?id_wasit=" . $id_wasit_edit);
    exit();
}

// --- Logika Perubahan Status Approval ---
$status_db_final_w = $data_lama_w['status_approval'];
$approved_by_nik_db_final_w = $data_lama_w['approved_by_nik'];
$approval_at_db_final_w = $data_lama_w['approval_at'];
$alasan_penolakan_db_final_w = $data_lama_w['alasan_penolakan'];
$last_updated_by_w = $user_nik;
$last_updated_at_w = date('Y-m-d H:i:s');

$is_data_deskriptif_berubah_w = false;
if ($id_cabor_w != $data_lama_w['id_cabor'] ||
    $nomor_lisensi_w != $data_lama_w['nomor_lisensi'] ||
    ($kontak_wasit_w !== null && $kontak_wasit_w != ($data_lama_w['kontak_wasit'] ?? null)) || // Handle null
    $lisensi_path_final_w != $lisensi_path_lama_w ||
    $foto_path_final_w != $foto_path_lama_w ||
    $ktp_path_final_w != $ktp_path_lama_w ||
    $kk_path_final_w != $kk_path_lama_w
) {
    $is_data_deskriptif_berubah_w = true;
}

// QUICK ACTION APPROVAL dari daftar_wasit.php
if (isset($_POST['quick_action_approval_wasit']) && $_POST['quick_action_approval_wasit'] == '1' && in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $status_db_final_w = $status_approval_form_w; // Ambil status dari form (disetujui, ditolak, revisi)
    if (in_array($status_db_final_w, ['disetujui', 'ditolak', 'revisi'])) {
        $approved_by_nik_db_final_w = $user_nik;
        $approval_at_db_final_w = $last_updated_at_w;
        $alasan_penolakan_db_final_w = (in_array($status_db_final_w, ['ditolak', 'revisi'])) ? $alasan_penolakan_form_w : null;
        if ($status_db_final_w == 'disetujui') $alasan_penolakan_db_final_w = null;
    }
}
// REGULAR EDIT FORM
else {
    if ($user_role_utama === 'pengurus_cabor') {
        if ($is_data_deskriptif_berubah_w && $data_lama_w['status_approval'] === 'disetujui') {
            $status_db_final_w = 'pending'; // atau 'revisi_pengcab'
            $approved_by_nik_db_final_w = null;
            $approval_at_db_final_w = null;
            $alasan_penolakan_db_final_w = null;
        } // Jika status lama 'pending' atau 'revisi' atau 'ditolak', biarkan statusnya. Admin yg akan proses.
    } elseif (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        // Jika admin mengubah status secara eksplisit dari form edit
        if ($status_approval_form_w != $data_lama_w['status_approval']) {
            $status_db_final_w = $status_approval_form_w;
            if (in_array($status_db_final_w, ['disetujui', 'ditolak', 'revisi', 'pending'])) {
                $approved_by_nik_db_final_w = $user_nik;
                $approval_at_db_final_w = $last_updated_at_w;
                $alasan_penolakan_db_final_w = (in_array($status_db_final_w, ['ditolak', 'revisi'])) ? $alasan_penolakan_form_w : null;
                if ($status_db_final_w == 'disetujui') $alasan_penolakan_db_final_w = null;
            }
        } elseif (in_array($data_lama_w['status_approval'], ['pending', 'revisi']) && $is_data_deskriptif_berubah_w) {
            // Jika admin hanya edit data deskriptif & status lama pending/revisi, otomatis setujui
            $status_db_final_w = 'disetujui';
            $approved_by_nik_db_final_w = $user_nik;
            $approval_at_db_final_w = $last_updated_at_w;
            $alasan_penolakan_db_final_w = null;
        }
        // Jika status lama sudah 'disetujui' dan admin hanya ubah data deskriptif, status tetap 'disetujui'
        // dan data approval lama dipertahankan kecuali jika status diubah eksplisit.
    }
}


// --- Update Database ---
try {
    $pdo->beginTransaction();

    $sql_update_w = "UPDATE wasit SET 
                        id_cabor = :id_cabor, 
                        nomor_lisensi = :nomor_lisensi, 
                        kontak_wasit = :kontak_wasit,
                        path_file_lisensi = :path_file_lisensi, 
                        foto_wasit = :foto_wasit,
                        ktp_path = :ktp_path,
                        kk_path = :kk_path,
                        status_approval = :status_approval, 
                        approved_by_nik = :approved_by_nik, 
                        approval_at = :approval_at, 
                        alasan_penolakan = :alasan_penolakan,
                        updated_by_nik = :updated_by_nik, 
                        last_updated_process_at = :last_updated_process_at
                    WHERE id_wasit = :id_wasit";
    $stmt_update_w = $pdo->prepare($sql_update_w);
    $stmt_update_w->execute([
        ':id_cabor' => $id_cabor_w,
        ':nomor_lisensi' => $nomor_lisensi_w ?: null,
        ':kontak_wasit' => $kontak_wasit_w ?: null,
        ':path_file_lisensi' => $lisensi_path_final_w ?: null,
        ':foto_wasit' => $foto_path_final_w ?: null,
        ':ktp_path' => $ktp_path_final_w ?: null,
        ':kk_path' => $kk_path_final_w ?: null,
        ':status_approval' => $status_db_final_w,
        ':approved_by_nik' => $approved_by_nik_db_final_w,
        ':approval_at' => $approval_at_db_final_w,
        ':alasan_penolakan' => $alasan_penolakan_db_final_w ?: null,
        ':updated_by_nik' => $last_updated_by_w,
        ':last_updated_process_at' => $last_updated_at_w,
        ':id_wasit' => $id_wasit_edit
    ]);

    // Update jumlah_wasit di tabel cabang_olahraga
    if ($status_db_final_w != $data_lama_w['status_approval'] || $id_cabor_w != $data_lama_w['id_cabor']) {
        // Jika status lama 'disetujui', kurangi dari cabor lama
        if ($data_lama_w['status_approval'] == 'disetujui') {
            $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = GREATEST(0, jumlah_wasit - 1) WHERE id_cabor = ?")->execute([$data_lama_w['id_cabor']]);
        }
        // Jika status baru 'disetujui', tambah ke cabor baru
        if ($status_db_final_w == 'disetujui') {
            $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = GREATEST(0, jumlah_wasit + 1) WHERE id_cabor = ?")->execute([$id_cabor_w]);
        }
    }

    // Audit Log
    $stmt_new_data_w = $pdo->prepare("SELECT w.*, p.nama_lengkap FROM wasit w JOIN pengguna p ON w.nik = p.nik WHERE w.id_wasit = :idw");
    $stmt_new_data_w->bindParam(':idw', $id_wasit_edit, PDO::PARAM_INT);
    $stmt_new_data_w->execute();
    $data_baru_w_log = $stmt_new_data_w->fetch(PDO::FETCH_ASSOC);

    $keterangan_log_w = "Mengubah data Wasit: '" . ($data_baru_w_log['nama_lengkap'] ?? $nik_wasit_db_original) . "' (ID: " . $id_wasit_edit . ").";
    if ($status_db_final_w != $data_lama_w['status_approval']) {
        $keterangan_log_w .= " Status diubah dari '" . $data_lama_w['status_approval'] . "' menjadi '" . $status_db_final_w . "'.";
    }

    $log_stmt_w = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:un, :a, :t, :id, :dl, :db, :ket)");
    $log_stmt_w->execute([
        'un' => $user_nik, 
        'a' => 'EDIT DATA WASIT', 
        't' => 'wasit', 
        'id' => $id_wasit_edit, 
        'dl' => json_encode($data_lama_w), 
        'db' => json_encode($data_baru_w_log),
        'ket' => $keterangan_log_w
    ]);

    $pdo->commit();
    unset($_SESSION['form_data_wasit_edit']); // Hapus data form dari session setelah sukses
    $_SESSION['pesan_sukses_global'] = "Data Wasit '" . htmlspecialchars($data_baru_w_log['nama_lengkap'] ?? $nik_wasit_db_original) . "' berhasil diperbarui.";
    if ($user_role_utama === 'pengurus_cabor' && $is_data_deskriptif_berubah_w && $status_db_final_w === 'pending') {
        $_SESSION['pesan_sukses_global'] .= " Perubahan Anda menunggu approval dari Admin KONI.";
    }
    header("Location: daftar_wasit.php" . ($id_cabor_w ? "?id_cabor=" . $id_cabor_w : ""));
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Proses Edit Wasit - Error DB: " . $e->getMessage());

    // Jika ada file yang terupload dan kemudian terjadi error DB, hapus file tersebut
    if ($ktp_path_final_w !== $ktp_path_lama_w && $ktp_path_final_w !== null && file_exists($doc_root . $app_base_path . $ktp_path_final_w)) @unlink($doc_root . $app_base_path . $ktp_path_final_w);
    if ($kk_path_final_w !== $kk_path_lama_w && $kk_path_final_w !== null && file_exists($doc_root . $app_base_path . $kk_path_final_w)) @unlink($doc_root . $app_base_path . $kk_path_final_w);
    if ($lisensi_path_final_w !== $lisensi_path_lama_w && $lisensi_path_final_w !== null && file_exists($doc_root . $app_base_path . $lisensi_path_final_w)) @unlink($doc_root . $app_base_path . $lisensi_path_final_w);
    if ($foto_path_final_w !== $foto_path_lama_w && $foto_path_final_w !== null && file_exists($doc_root . $app_base_path . $foto_path_final_w)) @unlink($doc_root . $app_base_path . $foto_path_final_w);
    
    $_SESSION['errors_wasit_edit'] = ["Error Database saat memperbarui data wasit: Terjadi masalah teknis."];
    // Tambahkan pesan error spesifik jika ada (misal, duplicate entry)
    // ... (logika deteksi duplicate entry bisa ditambahkan di sini jika diperlukan untuk NIK+Cabor wasit) ...
    header("Location: edit_wasit.php?id_wasit=" . $id_wasit_edit);
    exit();
}
?>