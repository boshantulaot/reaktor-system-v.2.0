<?php
// File: reaktorsystem/modules/cabor/edit_cabor.php

$page_title = "Edit Cabang Olahraga";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Pastikan konstanta dan variabel global tersedia
if (!isset($pdo) || !$pdo instanceof PDO || !isset($user_role_utama) || !isset($user_nik) || !isset($app_base_path) || !defined('APP_PATH_BASE') ||
    !defined('MAX_FILE_SIZE_SK_CABOR_MB') || !defined('MAX_FILE_SIZE_LOGO_MB') || 
    !defined('MAX_FILE_SIZE_SK_CABOR_BYTES') || !defined('MAX_FILE_SIZE_LOGO_BYTES') ||
    !isset($default_avatar_path_relative) ) { // $default_avatar_path_relative untuk placeholder jika diperlukan
    
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah untuk form edit cabor.";
    $redirect_url_edit_cbr_err = rtrim($app_base_path ?? '/', '/') . "/dashboard.php";
    if (!isset($user_login_status) || $user_login_status !== true) {
         $redirect_url_edit_cbr_err = rtrim($app_base_path ?? '/', '/') . "/auth/login.php";
    }
    if (!headers_sent()) { header("Location: " . $redirect_url_edit_cbr_err); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error kritis. <a href='" . htmlspecialchars($redirect_url_edit_cbr_err, ENT_QUOTES, 'UTF-8') . "'>Kembali</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

if ($user_role_utama != 'super_admin' && $user_role_utama != 'admin_koni') {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: " . rtrim($app_base_path, '/') . "/modules/cabor/daftar_cabor.php");
    exit();
}

$id_cabor_to_edit_val = null; 
$cabor_data_current = null;
$daftar_cabor_page_url_edit = "daftar_cabor.php";

if (isset($_GET['id_cabor'])) {
    $id_cabor_to_edit_val = filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT);
    if ($id_cabor_to_edit_val === false || $id_cabor_to_edit_val <= 0) { 
        $_SESSION['pesan_error_global'] = "ID Cabang Olahraga tidak valid."; 
        header("Location: " . $daftar_cabor_page_url_edit); exit(); 
    }
    try {
        $stmt_cbr_edit = $pdo->prepare("SELECT * FROM cabang_olahraga WHERE id_cabor = :id_cabor_param");
        $stmt_cbr_edit->bindParam(':id_cabor_param', $id_cabor_to_edit_val, PDO::PARAM_INT); 
        $stmt_cbr_edit->execute(); 
        $cabor_data_current = $stmt_cbr_edit->fetch(PDO::FETCH_ASSOC);
        if (!$cabor_data_current) { 
            $_SESSION['pesan_error_global'] = "Data Cabor ID " . htmlspecialchars($id_cabor_to_edit_val) . " tidak ditemukan."; 
            header("Location: " . $daftar_cabor_page_url_edit); exit(); 
        }
    } catch (PDOException $e_fetch_cbr_edit) { 
        $_SESSION['pesan_error_global'] = "Gagal ambil data cabor: " . $e_fetch_cbr_edit->getMessage(); 
        error_log("EDIT_CABOR_FETCH_ERROR: " . $e_fetch_cbr_edit->getMessage()); 
        header("Location: " . $daftar_cabor_page_url_edit); exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "ID Cabor tidak disertakan."; 
    header("Location: " . $daftar_cabor_page_url_edit); exit(); 
}

// Ambil daftar pengguna untuk dropdown
$pengguna_select_options_edit_cabor = [];
try {
    $stmt_pengguna_edit_cbr = $pdo->query("SELECT nik, nama_lengkap FROM pengguna WHERE is_approved = 1 ORDER BY nama_lengkap ASC");
    $pengguna_select_options_edit_cabor = $stmt_pengguna_edit_cbr->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e_pgn_edit_cbr) { error_log("Error ambil pengguna form edit cabor: " . $e_pgn_edit_cbr->getMessage()); }

// Repopulate form data
$form_data_cabor_repopulate_edit = $_SESSION['form_data_cabor_edit'] ?? [];
$form_errors_cabor_repopulate_edit = $_SESSION['errors_edit_cabor'] ?? [];
$form_error_fields_cabor_repopulate_edit = $_SESSION['error_fields_edit_cabor'] ?? []; // Jika Anda menggunakan ini
unset($_SESSION['form_data_cabor_edit'], $_SESSION['errors_edit_cabor'], $_SESSION['error_fields_edit_cabor']);

$val_nama_cabor_form_edit = $form_data_cabor_repopulate_edit['nama_cabor'] ?? ($cabor_data_current['nama_cabor'] ?? '');
$val_kode_cabor_display_edit = $cabor_data_current['kode_cabor'] ?? '-';
$val_ketua_cabor_nik_form_edit = $form_data_cabor_repopulate_edit['ketua_cabor_nik'] ?? ($cabor_data_current['ketua_cabor_nik'] ?? '');
$val_sekretaris_cabor_nik_form_edit = $form_data_cabor_repopulate_edit['sekretaris_cabor_nik'] ?? ($cabor_data_current['sekretaris_cabor_nik'] ?? '');
$val_bendahara_cabor_nik_form_edit = $form_data_cabor_repopulate_edit['bendahara_cabor_nik'] ?? ($cabor_data_current['bendahara_cabor_nik'] ?? '');
$val_alamat_sekretariat_form_edit = $form_data_cabor_repopulate_edit['alamat_sekretariat'] ?? ($cabor_data_current['alamat_sekretariat'] ?? '');
$val_kontak_cabor_form_edit = $form_data_cabor_repopulate_edit['kontak_cabor'] ?? ($cabor_data_current['kontak_cabor'] ?? '');
$val_email_cabor_form_edit = $form_data_cabor_repopulate_edit['email_cabor'] ?? ($cabor_data_current['email_cabor'] ?? '');
$val_nomor_sk_provinsi_form_edit = $form_data_cabor_repopulate_edit['nomor_sk_provinsi'] ?? ($cabor_data_current['nomor_sk_provinsi'] ?? '');
$val_tanggal_sk_provinsi_form_edit = $form_data_cabor_repopulate_edit['tanggal_sk_provinsi'] ?? ($cabor_data_current['tanggal_sk_provinsi'] ?? '');
$val_periode_mulai_form_edit = $form_data_cabor_repopulate_edit['periode_mulai'] ?? ($cabor_data_current['periode_mulai'] ?? '');
$val_periode_selesai_form_edit = $form_data_cabor_repopulate_edit['periode_selesai'] ?? ($cabor_data_current['periode_selesai'] ?? '');
$val_status_kepengurusan_form_edit = $form_data_cabor_repopulate_edit['status_kepengurusan'] ?? ($cabor_data_current['status_kepengurusan'] ?? 'Aktif');

$default_cabor_logo_path_rel = 'assets/img_default/cabor_default.png'; // Definisikan path default logo cabor Anda
?>

<div class="content-header">
  <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0"><?php echo htmlspecialchars($page_title); ?></h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/dashboard.php">Home</a></li><li class="breadcrumb-item"><a href="<?php echo $daftar_cabor_page_url_edit; ?>">Manajemen Cabor</a></li><li class="breadcrumb-item active">Edit</li></ol></div></div></div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card card-warning card-outline"> <?php // Warna warning untuk edit, dengan outline ?>
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit: <strong><?php echo htmlspecialchars($cabor_data_current['nama_cabor']); ?></strong> (<?php echo htmlspecialchars($cabor_data_current['kode_cabor']); ?>)</h3></div>
                    <form action="proses_edit_cabor.php" method="post" enctype="multipart/form-data" id="formEditCabor">
                        <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_to_edit_val); ?>">
                        <input type="hidden" name="logo_cabor_lama" value="<?php echo htmlspecialchars($cabor_data_current['logo_cabor'] ?? ''); ?>">
                        <input type="hidden" name="path_file_sk_provinsi_lama" value="<?php echo htmlspecialchars($cabor_data_current['path_file_sk_provinsi'] ?? ''); ?>">

                        <div class="card-body">
                            <?php if (!empty($form_errors_cabor_repopulate_edit)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Validasi Gagal!</h5>
                                    <ul> <?php foreach ($form_errors_cabor_repopulate_edit as $err_cbr_edit_item): ?> <li><?php echo htmlspecialchars($err_cbr_edit_item); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-orange"><i class="fas fa-info-circle mr-1"></i> Informasi Dasar Cabor</h5>
                            <div class="form-group"><label>Kode Cabor:</label><p class="form-control-static"><strong><?php echo htmlspecialchars($val_kode_cabor_display_edit); ?></strong> <small>(Tidak dapat diubah)</small></p></div>
                            <div class="form-group"><label for="nama_cabor_edit_input">Nama Cabor <span class="text-danger">*</span></label><input type="text" class="form-control <?php if(in_array('nama_cabor', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="nama_cabor_edit_input" name="nama_cabor" value="<?php echo htmlspecialchars($val_nama_cabor_form_edit); ?>" required></div>

                            <hr><h5 class="mt-3 mb-3 text-orange"><i class="fas fa-users mr-1"></i> Struktur Kepengurusan</h5>
                            <div class="row">
                                <div class="col-md-4 form-group"><label for="ketua_cabor_nik_edit">Ketua Cabor (NIK)</label><select class="form-control select2bs4" id="ketua_cabor_nik_edit" name="ketua_cabor_nik" data-placeholder="-- Pilih Ketua --" style="width:100%;"><option value=""></option><?php foreach ($pengguna_select_options_edit_cabor as $pgn_opt):?><option value="<?php echo htmlspecialchars($pgn_opt['nik']); ?>"<?php if($val_ketua_cabor_nik_form_edit == $pgn_opt['nik']) echo ' selected'; ?>><?php echo htmlspecialchars($pgn_opt['nama_lengkap'] . ' (' . $pgn_opt['nik'] . ')'); ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-4 form-group"><label for="sekretaris_cabor_nik_edit">Sekretaris Cabor (NIK)</label><select class="form-control select2bs4" id="sekretaris_cabor_nik_edit" name="sekretaris_cabor_nik" data-placeholder="-- Pilih Sekretaris --" style="width:100%;"><option value=""></option><?php foreach ($pengguna_select_options_edit_cabor as $pgn_opt):?><option value="<?php echo htmlspecialchars($pgn_opt['nik']); ?>"<?php if($val_sekretaris_cabor_nik_form_edit == $pgn_opt['nik']) echo ' selected'; ?>><?php echo htmlspecialchars($pgn_opt['nama_lengkap'] . ' (' . $pgn_opt['nik'] . ')'); ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-4 form-group"><label for="bendahara_cabor_nik_edit">Bendahara Cabor (NIK)</label><select class="form-control select2bs4" id="bendahara_cabor_nik_edit" name="bendahara_cabor_nik" data-placeholder="-- Pilih Bendahara --" style="width:100%;"><option value=""></option><?php foreach ($pengguna_select_options_edit_cabor as $pgn_opt):?><option value="<?php echo htmlspecialchars($pgn_opt['nik']); ?>"<?php if($val_bendahara_cabor_nik_form_edit == $pgn_opt['nik']) echo ' selected'; ?>><?php echo htmlspecialchars($pgn_opt['nama_lengkap'] . ' (' . $pgn_opt['nik'] . ')'); ?></option><?php endforeach; ?></select></div>
                            </div>

                            <hr><h5 class="mt-3 mb-3 text-orange"><i class="fas fa-map-signs mr-1"></i> Kontak & Sekretariat</h5>
                            <div class="form-group"><label for="alamat_sekretariat_edit">Alamat Sekretariat</label><textarea class="form-control" id="alamat_sekretariat_edit" name="alamat_sekretariat" rows="2"><?php echo htmlspecialchars($val_alamat_sekretariat_form_edit); ?></textarea></div>
                            <div class="row">
                                <div class="col-md-6 form-group"><label for="kontak_cabor_edit">Kontak Cabor</label><input type="text" class="form-control" id="kontak_cabor_edit" name="kontak_cabor" value="<?php echo htmlspecialchars($val_kontak_cabor_form_edit); ?>"></div>
                                <div class="col-md-6 form-group"><label for="email_cabor_edit">Email Cabor</label><input type="email" class="form-control <?php if(in_array('email_cabor', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="email_cabor_edit" name="email_cabor" value="<?php echo htmlspecialchars($val_email_cabor_form_edit); ?>"></div>
                            </div>

                            <hr><h5 class="mt-3 mb-3 text-orange"><i class="fas fa-file-alt mr-1"></i> Informasi SK Provinsi</h5>
                            <div class="row">
                                <div class="col-md-6 form-group"><label for="nomor_sk_provinsi_edit">Nomor SK</label><input type="text" class="form-control" id="nomor_sk_provinsi_edit" name="nomor_sk_provinsi" value="<?php echo htmlspecialchars($val_nomor_sk_provinsi_form_edit); ?>"></div>
                                <div class="col-md-6 form-group"><label for="tanggal_sk_provinsi_edit">Tanggal SK</label><input type="date" class="form-control <?php if(in_array('tanggal_sk_provinsi', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="tanggal_sk_provinsi_edit" name="tanggal_sk_provinsi" value="<?php echo htmlspecialchars($val_tanggal_sk_provinsi_form_edit); ?>"></div>
                            </div>
                            <div class="form-group"><label for="path_file_sk_provinsi_edit">Ganti File SK (PDF/Gambar)</label><small class="form-text text-muted d-block mb-1">Kosongkan jika tidak ingin mengubah file SK.</small><div class="custom-file"><input type="file" class="custom-file-input <?php if(in_array('path_file_sk_provinsi', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="path_file_sk_provinsi_edit" name="path_file_sk_provinsi" accept=".pdf,image/jpeg,image/png"><label class="custom-file-label" for="path_file_sk_provinsi_edit">Pilih file SK baru...</label></div><small class="form-text text-muted">Maks <?php echo MAX_FILE_SIZE_SK_CABOR_MB; ?>MB.</small>
                            <?php
                                $sk_display_url = ''; $sk_display_message = '<p class="text-muted text-sm mt-1">File SK belum pernah diupload.</p>';
                                if (!empty($cabor_data_current['path_file_sk_provinsi'])) {
                                    $path_sk_rel_edit = ltrim($cabor_data_current['path_file_sk_provinsi'], '/');
                                    $path_sk_server_edit = rtrim(APP_PATH_BASE, '/\\') . '/' . $path_sk_rel_edit;
                                    if (file_exists(preg_replace('/\/+/', '/', $path_sk_server_edit)) && is_file(preg_replace('/\/+/', '/', $path_sk_server_edit))) {
                                        $sk_display_url = rtrim($app_base_path, '/') . '/' . $path_sk_rel_edit;
                                        $sk_display_message = '<small class="form-text text-muted d-block mt-1">File SK saat ini: <a href="'.htmlspecialchars(preg_replace('/\/+/', '/', $sk_display_url)).'" target="_blank">'.htmlspecialchars(basename($cabor_data_current['path_file_sk_provinsi'])).'</a></small>';
                                    } else { $sk_display_message = '<p class="text-danger text-sm mt-1">File SK saat ini ('.htmlspecialchars(basename($cabor_data_current['path_file_sk_provinsi'])).') tidak ditemukan.</p>'; }
                                } echo $sk_display_message;
                            ?>
                            <?php if (!empty($cabor_data_current['path_file_sk_provinsi'])): // Tampilkan checkbox hapus hanya jika ada file SK lama ?>
                            <div class="form-check mt-1"><input class="form-check-input" type="checkbox" value="1" id="hapus_sk_provinsi_current" name="hapus_sk_provinsi_current"><label class="form-check-label text-danger" for="hapus_sk_provinsi_current">Hapus file SK saat ini.</label></div>
                            <?php endif; ?>
                            </div>

                            <hr><h5 class="mt-3 mb-3 text-orange"><i class="fas fa-calendar-check mr-1"></i> Periode Kepengurusan & Status</h5>
                             <div class="row">
                                <div class="col-md-4 form-group"><label for="periode_mulai_edit">Periode Mulai</label><input type="date" class="form-control <?php if(in_array('periode_mulai', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="periode_mulai_edit" name="periode_mulai" value="<?php echo htmlspecialchars($val_periode_mulai_form_edit); ?>"></div>
                                <div class="col-md-4 form-group"><label for="periode_selesai_edit">Periode Selesai</label><input type="date" class="form-control <?php if(in_array('periode_selesai', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="periode_selesai_edit" name="periode_selesai" value="<?php echo htmlspecialchars($val_periode_selesai_form_edit); ?>"></div>
                                <div class="col-md-4 form-group"><label for="status_kepengurusan_edit">Status Kepengurusan <span class="text-danger">*</span></label><select class="form-control" id="status_kepengurusan_edit" name="status_kepengurusan" required><option value="Aktif" <?php if ($val_status_kepengurusan_form_edit == 'Aktif') echo 'selected'; ?>>Aktif</option><option value="Tidak Aktif" <?php if ($val_status_kepengurusan_form_edit == 'Tidak Aktif') echo 'selected'; ?>>Tidak Aktif</option><option value="Masa Tenggang" <?php if ($val_status_kepengurusan_form_edit == 'Masa Tenggang') echo 'selected'; ?>>Masa Tenggang</option><option value="Dibekukan" <?php if ($val_status_kepengurusan_form_edit == 'Dibekukan') echo 'selected'; ?>>Dibekukan</option></select></div>
                            </div>
                            
                            <hr><h5 class="mt-3 mb-3 text-orange"><i class="fas fa-image mr-1"></i> Logo Cabor</h5>
                            <div class="form-group"><label for="logo_cabor_edit">Ganti Logo Cabor (Opsional)</label><small class="form-text text-muted d-block mb-1">Kosongkan jika tidak ingin mengubah logo.</small><div class="custom-file"><input type="file" class="custom-file-input <?php if(in_array('logo_cabor', $form_error_fields_cabor_repopulate_edit)) echo 'is-invalid'; ?>" id="logo_cabor_edit" name="logo_cabor" accept="image/jpeg,image/png,image/gif"><label class="custom-file-label" for="logo_cabor_edit">Pilih file logo baru...</label></div><small class="form-text text-muted">JPG, PNG, GIF. Maks <?php echo MAX_FILE_SIZE_LOGO_MB; ?>MB.</small>
                            <?php
                                $logo_display_url_edit = rtrim($app_base_path, '/') . '/' . ltrim($default_cabor_logo_path_rel, '/'); // Gunakan default cabor logo
                                $logo_display_message_edit = '<p class="text-muted text-sm mt-1">Logo default akan digunakan jika tidak ada logo spesifik.</p>';
                                if (!empty($cabor_data_current['logo_cabor'])) {
                                    $path_logo_rel_edit = ltrim($cabor_data_current['logo_cabor'], '/');
                                    $path_logo_server_edit = rtrim(APP_PATH_BASE, '/\\') . '/' . $path_logo_rel_edit;
                                    if (file_exists(preg_replace('/\/+/', '/', $path_logo_server_edit)) && is_file(preg_replace('/\/+/', '/', $path_logo_server_edit))) {
                                        $logo_display_url_edit = rtrim($app_base_path, '/') . '/' . $path_logo_rel_edit;
                                        $logo_display_message_edit = '<small class="form-text text-muted d-block mt-1">Logo saat ini:</small>';
                                    } else { $logo_display_message_edit = '<p class="text-danger text-sm mt-1">File logo saat ini ('.htmlspecialchars(basename($cabor_data_current['logo_cabor'])).') tidak ditemukan. Akan menggunakan default jika tidak diganti.</p>'; }
                                }
                                echo $logo_display_message_edit;
                            ?>
                            <img src="<?php echo htmlspecialchars(preg_replace('/\/+/', '/', $logo_display_url_edit)); ?>" alt="Logo Saat Ini" style="max-height: 80px; max-width:200px; margin-top: 5px; border:1px solid #ddd; padding:3px; background-color: #f8f9fa;">
                            <?php if (!empty($cabor_data_current['logo_cabor']) && $cabor_data_current['logo_cabor'] !== $default_cabor_logo_path_rel): // Tampilkan checkbox hapus hanya jika ada logo kustom ?>
                            <div class="form-check mt-1"><input class="form-check-input" type="checkbox" value="1" id="hapus_logo_cabor_current" name="hapus_logo_cabor_current"><label class="form-check-label text-danger" for="hapus_logo_cabor_current">Hapus logo saat ini (akan diganti default jika tidak ada logo baru).</label></div>
                            <?php endif; ?>
                            </div>
                            
                            <p class="text-muted text-sm mt-3"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_cabor" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                            <a href="daftar_cabor.php" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    bsCustomFileInput.init();
  }
  $('.select2bs4').each(function () {
    $(this).select2({
      theme: 'bootstrap4',
      placeholder: $(this).data('placeholder') || '-- Pilih --',
      allowClear: true
    });
  });

  // Validasi frontend sederhana sebelum submit (mirip form tambah cabor)
  $('#formEditCabor').submit(function(e) {
    let isValidEditCabor = true;
    let focusFieldEditCabor = null;
    $('.form-control.is-invalid').removeClass('is-invalid');
    $('.select2-selection--single.is-invalid').removeClass('is-invalid');
    $('.custom-file-label.text-danger').removeClass('text-danger border-danger');


    if ($('#nama_cabor_edit_input').val().trim() === '') { // Ganti ID jika perlu
        isValidEditCabor = false; if (!focusFieldEditCabor) focusFieldEditCabor = $('#nama_cabor_edit_input');
        $('#nama_cabor_edit_input').addClass('is-invalid');
    }
    // Ketua Cabor tidak wajib di form edit ini, bisa dikosongkan. Jika wajib, tambahkan validasinya.
    // if ($('#ketua_cabor_nik_edit').val() === '' || $('#ketua_cabor_nik_edit').val() === null) {
    //     isValidEditCabor = false; if (!focusFieldEditCabor) focusFieldEditCabor = $('#ketua_cabor_nik_edit');
    //     $('#ketua_cabor_nik_edit').next('.select2-container').find('.select2-selection--single').addClass('is-invalid');
    // }
    if ($('#status_kepengurusan_input').val() === '') { // Ganti ID jika perlu (status_kepengurusan_edit)
         isValidEditCabor = false; if (!focusFieldEditCabor) focusFieldEditCabor = $('#status_kepengurusan_input');
        $('#status_kepengurusan_input').addClass('is-invalid');
    }

    let emailCaborEditVal = $('#email_cabor_edit').val().trim(); // Ganti ID jika perlu
    if (emailCaborEditVal !== '') {
        var emailPatternCaborEdit = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,6}$/;
        if (!emailPatternCaborEdit.test(emailCaborEditVal)) {
            isValidEditCabor = false; if (!focusFieldEditCabor) focusFieldEditCabor = $('#email_cabor_edit');
            $('#email_cabor_edit').addClass('is-invalid');
        }
    }
    
    let periodeMulaiEditVal = $('#periode_mulai_edit').val(); // Ganti ID
    let periodeSelesaiEditVal = $('#periode_selesai_edit').val(); // Ganti ID
    if (periodeMulaiEditVal && periodeSelesaiEditVal && periodeSelesaiEditVal < periodeMulaiEditVal) {
        isValidEditCabor = false; if (!focusFieldEditCabor) focusFieldEditCabor = $('#periode_selesai_edit');
        $('#periode_mulai_edit').addClass('is-invalid');
        $('#periode_selesai_edit').addClass('is-invalid');
        alert('Tanggal Periode Selesai tidak boleh sebelum Tanggal Periode Mulai.');
    }

    let fileSKEdit = $('#path_file_sk_provinsi_edit')[0].files[0]; // Ganti ID
    if(fileSKEdit && fileSKEdit.size > (" . MAX_FILE_SIZE_SK_CABOR_BYTES . ")){ 
       isValidEditCabor = false; if(!focusFieldEditCabor) focusFieldEditCabor = $('#path_file_sk_provinsi_edit');
       $('#path_file_sk_provinsi_edit').closest('.custom-file').find('.custom-file-label').addClass('text-danger border-danger');
       alert('Ukuran file SK Provinsi tidak boleh lebih dari " . MAX_FILE_SIZE_SK_CABOR_MB . "MB.');
    }

    let fileLogoEdit = $('#logo_cabor_edit')[0].files[0]; // Ganti ID
    if(fileLogoEdit && fileLogoEdit.size > (" . MAX_FILE_SIZE_LOGO_BYTES . ")){ 
       isValidEditCabor = false; if(!focusFieldEditCabor) focusFieldEditCabor = $('#logo_cabor_edit');
       $('#logo_cabor_edit').closest('.custom-file').find('.custom-file-label').addClass('text-danger border-danger');
       alert('Ukuran file Logo tidak boleh lebih dari " . MAX_FILE_SIZE_LOGO_MB . "MB.');
    }
    
    if (!isValidEditCabor) {
        e.preventDefault(); 
        if(focusFieldEditCabor) {
            if (focusFieldEditCabor.hasClass('select2bs4')) {
                $('html, body').animate({ scrollTop: focusFieldEditCabor.next('.select2-container').offset().top - 70 }, 500);
                focusFieldEditCabor.select2('open');
            } else {
                 $('html, body').animate({ scrollTop: focusFieldEditCabor.closest('.form-group').offset().top - 70 }, 500);
                focusFieldEditCabor.focus();
            }
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>