<?php
// File: reaktorsystem/modules/cabor/tambah_cabor.php

$page_title = "Tambah Cabang Olahraga Baru";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    // Untuk Datepicker AdminLTE
    'assets/adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/moment/moment.min.js', // Diperlukan oleh Tempus Dominus
    'assets/adminlte/plugins/moment/locale/id.js', // Lokal Bahasa Indonesia untuk Moment.js
    'assets/adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Pengecekan sesi & peran, serta variabel inti
if (!isset($user_login_status) || $user_login_status !== true || 
    !isset($user_role_utama) || !in_array($user_role_utama, ['super_admin', 'admin_koni']) ||
    !isset($pdo) || !$pdo instanceof PDO ||
    !defined('MAX_FILE_SIZE_SK_CABOR_MB') || !defined('MAX_FILE_SIZE_LOGO_MB') ||
    !defined('MAX_FILE_SIZE_SK_CABOR_BYTES') || !defined('MAX_FILE_SIZE_LOGO_BYTES') || !defined('APP_PATH_BASE') ) {
    
    $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti bermasalah.";
    $redirect_url_tc_final_form = rtrim($app_base_path ?? '/', '/') . "/dashboard.php";
    if (!isset($user_login_status) || $user_login_status !== true) {
         $redirect_url_tc_final_form = rtrim($app_base_path ?? '/', '/') . "/auth/login.php";
    }
    if (!headers_sent()) { header("Location: " . $redirect_url_tc_final_form); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error. <a href='" . htmlspecialchars($redirect_url_tc_final_form, ENT_QUOTES, 'UTF-8') . "'>Kembali</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

// Ambil SEMUA pengguna aktif untuk dropdown NIK pengurus.
$pengguna_options_for_cabor_form_all_final = [];
try {
    $stmt_all_pengguna_cbr_final_form = $pdo->query("SELECT nik, nama_lengkap FROM pengguna WHERE is_approved = 1 ORDER BY nama_lengkap ASC");
    $pengguna_options_for_cabor_form_all_final = $stmt_all_pengguna_cbr_final_form->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e_all_pgn_cbr_tc_final_form) { 
    error_log("Gagal ambil semua pengguna untuk form tambah cabor: " . $e_all_pgn_cbr_tc_final_form->getMessage());
}

// Repopulate form data
$form_data_cbr_add_repop_js_final = $_SESSION['form_data_cabor_tambah'] ?? [];
$form_errors_cbr_add_repop_js_final = $_SESSION['errors_tambah_cabor'] ?? [];
$form_error_fields_cbr_add_repop_js_final = $_SESSION['error_fields_tambah_cabor'] ?? [];

unset($_SESSION['form_data_cabor_tambah'], $_SESSION['errors_tambah_cabor'], $_SESSION['error_fields_tambah_cabor']);

// Inisialisasi nilai form
$val_nama_cabor_form_final = $form_data_cbr_add_repop_js_final['nama_cabor'] ?? '';
$val_ketua_nik_form_final = $form_data_cbr_add_repop_js_final['ketua_cabor_nik'] ?? '';
$val_sekretaris_nik_form_final = $form_data_cbr_add_repop_js_final['sekretaris_cabor_nik'] ?? '';
$val_bendahara_nik_form_final = $form_data_cbr_add_repop_js_final['bendahara_cabor_nik'] ?? '';
$val_alamat_sekretariat_form_final = $form_data_cbr_add_repop_js_final['alamat_sekretariat'] ?? '';
$val_kontak_cabor_form_final = $form_data_cbr_add_repop_js_final['kontak_cabor'] ?? '';
$val_email_cabor_form_final = $form_data_cbr_add_repop_js_final['email_cabor'] ?? '';
$val_nomor_sk_form_final = $form_data_cbr_add_repop_js_final['nomor_sk_provinsi'] ?? '';
$val_tanggal_sk_form_final = $form_data_cbr_add_repop_js_final['tanggal_sk_provinsi'] ?? '';
$val_periode_mulai_form_final = $form_data_cbr_add_repop_js_final['periode_mulai'] ?? '';
$val_periode_selesai_form_final = $form_data_cbr_add_repop_js_final['periode_selesai'] ?? '';
$val_status_kepengurusan_form_final = $form_data_cbr_add_repop_js_final['status_kepengurusan'] ?? 'Aktif';
?>

<div class="content-header">
  <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0"><?php echo htmlspecialchars($page_title); ?></h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/dashboard.php">Home</a></li><li class="breadcrumb-item"><a href="daftar_cabor.php">Manajemen Cabor</a></li><li class="breadcrumb-item active">Tambah Baru</li></ol></div></div></div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card card-success card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> Formulir Tambah Cabang Olahraga Baru</h3></div>
                    <form action="proses_tambah_cabor.php" method="post" enctype="multipart/form-data" id="formTambahCabor">
                        <div class="card-body">
                            <?php if (!empty($form_errors_cbr_add_repop_js_final)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Validasi Gagal! Periksa kembali input Anda:</h5>
                                    <ul> <?php foreach ($form_errors_cbr_add_repop_js_final as $err_cbr_loop_html): ?> <li><?php echo htmlspecialchars($err_cbr_loop_html); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-olive"><i class="fas fa-info-circle mr-1"></i> Informasi Dasar Cabor</h5>
                            <div class="form-group">
                                <label for="nama_cabor_input_add_final_id">Nama Cabang Olahraga <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(in_array('nama_cabor', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="nama_cabor_input_add_final_id" name="nama_cabor" placeholder="Contoh: Persatuan Atletik Seluruh Indonesia (PASI)" value="<?php echo htmlspecialchars($val_nama_cabor_add_final); ?>" required>
                            </div>
                            <div class="alert alert-light alert-sm"><strong>Info:</strong> Kode Cabang Olahraga akan dibuat otomatis oleh sistem.</div>

                            <hr><h5 class="mt-3 mb-3 text-olive"><i class="fas fa-users mr-1"></i> Struktur Kepengurusan</h5>
                            <p class="text-muted text-sm mb-2">Pilih pengguna untuk jabatan inti. Satu NIK hanya bisa menjabat di satu cabor dan tidak boleh merangkap jabatan inti (Ketua/Sekretaris/Bendahara) di cabor yang sama.</p>
                            <div id="global-nik-pengurus-error-container-js" class="alert alert-danger mt-2 py-1 px-2 text-sm" style="display:none;"></div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="ketua_cabor_nik_add_id">Ketua Cabor (NIK)</label> <?php // Ketua bisa jadi opsional saat tambah, required di proses ?>
                                    <select class="form-control select2bs4 nik-pengurus-cabor-js-input <?php if(in_array('ketua_cabor_nik', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="ketua_cabor_nik_add_id" name="ketua_cabor_nik" style="width: 100%;" data-placeholder="-- Pilih Ketua --" data-jabatan="Ketua">
                                        <option value=""></option>
                                        <?php foreach ($pengguna_options_for_cabor_form_all_final as $pgn_opt_loop_add): ?><option value="<?php echo htmlspecialchars($pgn_opt_loop_add['nik']); ?>"<?php if($val_ketua_nik_add_final == $pgn_opt_loop_add['nik']) echo ' selected'; ?>><?php echo htmlspecialchars($pgn_opt_loop_add['nama_lengkap'] . ' (' . $pgn_opt_loop_add['nik'] . ')'); ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="nik-pengurus-feedback invalid-feedback"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="sekretaris_cabor_nik_add_id">Sekretaris Cabor (NIK)</label>
                                    <select class="form-control select2bs4 nik-pengurus-cabor-js-input <?php if(in_array('sekretaris_cabor_nik', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="sekretaris_cabor_nik_add_id" name="sekretaris_cabor_nik" style="width: 100%;" data-placeholder="-- Pilih Sekretaris --" data-jabatan="Sekretaris">
                                        <option value=""></option>
                                        <?php foreach ($pengguna_options_for_cabor_form_all_final as $pgn_opt_loop_add): ?><option value="<?php echo htmlspecialchars($pgn_opt_loop_add['nik']); ?>"<?php if($val_sekretaris_nik_add_final == $pgn_opt_loop_add['nik']) echo ' selected'; ?>><?php echo htmlspecialchars($pgn_opt_loop_add['nama_lengkap'] . ' (' . $pgn_opt_loop_add['nik'] . ')'); ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="nik-pengurus-feedback invalid-feedback"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="bendahara_cabor_nik_add_id">Bendahara Cabor (NIK)</label>
                                    <select class="form-control select2bs4 nik-pengurus-cabor-js-input <?php if(in_array('bendahara_cabor_nik', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="bendahara_cabor_nik_add_id" name="bendahara_cabor_nik" style="width: 100%;" data-placeholder="-- Pilih Bendahara --" data-jabatan="Bendahara">
                                        <option value=""></option>
                                        <?php foreach ($pengguna_options_for_cabor_form_all_final as $pgn_opt_loop_add): ?><option value="<?php echo htmlspecialchars($pgn_opt_loop_add['nik']); ?>"<?php if($val_bendahara_nik_add_final == $pgn_opt_loop_add['nik']) echo ' selected'; ?>><?php echo htmlspecialchars($pgn_opt_loop_add['nama_lengkap'] . ' (' . $pgn_opt_loop_add['nik'] . ')'); ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="nik-pengurus-feedback invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <hr><h5 class="mt-3 mb-3 text-olive"><i class="fas fa-map-signs mr-1"></i> Kontak & Sekretariat</h5>
                            <div class="form-group"><label for="alamat_sekretariat_add_id">Alamat Sekretariat</label><textarea class="form-control" id="alamat_sekretariat_add_id" name="alamat_sekretariat" rows="2" placeholder="Alamat Lengkap Sekretariat Cabor"><?php echo htmlspecialchars($val_alamat_sekretariat_add_final); ?></textarea></div>
                            <div class="row">
                                <div class="col-md-6 form-group"><label for="kontak_cabor_add_id">Kontak Cabor</label><input type="text" class="form-control" id="kontak_cabor_add_id" name="kontak_cabor" placeholder="No. Telepon/HP Cabor" value="<?php echo htmlspecialchars($val_kontak_cabor_add_final); ?>"></div>
                                <div class="col-md-6 form-group"><label for="email_cabor_add_id">Email Cabor</label><input type="email" class="form-control <?php if(in_array('email_cabor', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="email_cabor_add_id" name="email_cabor" placeholder="Alamat Email Resmi Cabor" value="<?php echo htmlspecialchars($val_email_cabor_add_final); ?>"></div>
                            </div>

                            <hr><h5 class="mt-3 mb-3 text-olive"><i class="fas fa-file-alt mr-1"></i> Informasi SK Provinsi</h5>
                            <div class="row">
                                <div class="col-md-6 form-group"><label for="nomor_sk_provinsi_add_id">Nomor SK</label><input type="text" class="form-control" id="nomor_sk_provinsi_add_id" name="nomor_sk_provinsi" value="<?php echo htmlspecialchars($val_nomor_sk_add_final); ?>"></div>
                                <div class="col-md-6 form-group"><label for="tanggal_sk_provinsi_add_id">Tanggal SK</label><input type="text" class="form-control datetimepicker-input <?php if(in_array('tanggal_sk_provinsi', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="tanggal_sk_provinsi_add_id" name="tanggal_sk_provinsi" data-toggle="datetimepicker" data-target="#tanggal_sk_provinsi_add_id" value="<?php echo htmlspecialchars($val_tanggal_sk_add_final); ?>" placeholder="YYYY-MM-DD" autocomplete="off"></div>
                            </div>
                            <div class="form-group"><label for="path_file_sk_provinsi_add_id">Upload File SK</label><div class="custom-file"><input type="file" class="custom-file-input <?php if(in_array('path_file_sk_provinsi', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="path_file_sk_provinsi_add_id" name="path_file_sk_provinsi" accept=".pdf,image/jpeg,image/png"><label class="custom-file-label" for="path_file_sk_provinsi_add_id">Pilih file SK...</label></div><small class="form-text text-muted">Maks <?php echo MAX_FILE_SIZE_SK_CABOR_MB; ?>MB.</small></div>

                            <hr><h5 class="mt-3 mb-3 text-olive"><i class="fas fa-calendar-check mr-1"></i> Periode Kepengurusan & Status</h5>
                             <div class="row">
                                <div class="col-md-4 form-group"><label for="periode_mulai_add_id">Periode Mulai</label><input type="text" class="form-control datetimepicker-input <?php if(in_array('periode_mulai', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="periode_mulai_add_id" name="periode_mulai" data-toggle="datetimepicker" data-target="#periode_mulai_add_id" value="<?php echo htmlspecialchars($val_periode_mulai_add_final); ?>" placeholder="YYYY-MM-DD" autocomplete="off"></div>
                                <div class="col-md-4 form-group"><label for="periode_selesai_add_id">Periode Selesai</label><input type="text" class="form-control datetimepicker-input <?php if(in_array('periode_selesai', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="periode_selesai_add_id" name="periode_selesai" data-toggle="datetimepicker" data-target="#periode_selesai_add_id" value="<?php echo htmlspecialchars($val_periode_selesai_add_final); ?>" placeholder="YYYY-MM-DD" autocomplete="off"></div>
                                <div class="col-md-4 form-group"><label for="status_kepengurusan_add_id">Status Kepengurusan <span class="text-danger">*</span></label><select class="form-control select2bs4 <?php if(in_array('status_kepengurusan', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="status_kepengurusan_add_id" name="status_kepengurusan" required data-placeholder="-- Pilih Status --" style="width:100%;"><option value="Aktif" <?php if ($val_status_kepengurusan_add_final == 'Aktif') echo 'selected'; ?>>Aktif</option><option value="Tidak Aktif" <?php if ($val_status_kepengurusan_add_final == 'Tidak Aktif') echo 'selected'; ?>>Tidak Aktif</option><option value="Masa Tenggang" <?php if ($val_status_kepengurusan_add_final == 'Masa Tenggang') echo 'selected'; ?>>Masa Tenggang</option><option value="Dibekukan" <?php if ($val_status_kepengurusan_add_final == 'Dibekukan') echo 'selected'; ?>>Dibekukan</option></select></div>
                            </div>
                            
                            <hr><h5 class="mt-3 mb-3 text-olive"><i class="fas fa-image mr-1"></i> Logo Cabang Olahraga</h5>
                            <div class="form-group"><label for="logo_cabor_add_id">Upload Logo Cabor</label><div class="custom-file"><input type="file" class="custom-file-input <?php if(in_array('logo_cabor', $form_error_fields_cbr_add_repop_js_final)) echo 'is-invalid'; ?>" id="logo_cabor_add_id" name="logo_cabor" accept="image/jpeg,image/png,image/gif"><label class="custom-file-label" for="logo_cabor_add_id">Pilih file logo...</label></div><small class="form-text text-muted">JPG, PNG, GIF. Kosongkan untuk menggunakan logo KONI default. Maks <?php echo MAX_FILE_SIZE_LOGO_MB; ?>MB.</small></div>
                            
                            <p class="text-muted text-sm mt-3"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_cabor" id="submitTambahCaborBtnId" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Data Cabor</button>
                            <a href="daftar_cabor.php" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$max_sk_bytes_js_cbr = defined('MAX_FILE_SIZE_SK_CABOR_BYTES') ? MAX_FILE_SIZE_SK_CABOR_BYTES : (5 * 1024 * 1024);
$max_logo_bytes_js_cbr = defined('MAX_FILE_SIZE_LOGO_BYTES') ? MAX_FILE_SIZE_LOGO_BYTES : (2 * 1024 * 1024);

$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) { bsCustomFileInput.init(); }
  $('.select2bs4').each(function () { $(this).select2({ theme: 'bootstrap4', placeholder: $(this).data('placeholder') || '-- Pilih --', allowClear: true }); });

  // Inisialisasi Tempus Dominus Datepicker
  $('#tanggal_sk_provinsi_add_id, #periode_mulai_add_id, #periode_selesai_add_id').datetimepicker({
      format: 'YYYY-MM-DD',
      locale: 'id', // Menggunakan lokal Bahasa Indonesia dari moment.js
      buttons: { showClear: true, showClose: true, showToday: true },
      icons: { time: 'far fa-clock', date: 'far fa-calendar-alt', up: 'fas fa-chevron-up', down: 'fas fa-chevron-down', previous: 'fas fa-chevron-left', next: 'fas fa-chevron-right', today: 'far fa-calendar-check', clear: 'far fa-trash-alt', close: 'fas fa-times' },
      useCurrent: false // Jangan otomatis set tanggal hari ini saat load
  });
  // Sinkronisasi datepicker periode
  $('#periode_mulai_add_id').on('change.datetimepicker', function (e) {
      $('#periode_selesai_add_id').datetimepicker('minDate', e.date);
  });
  $('#periode_selesai_add_id').on('change.datetimepicker', function (e) {
      $('#periode_mulai_add_id').datetimepicker('maxDate', e.date);
  });


  var nikFieldsCbr = {
      ketua: $('#ketua_cabor_nik_add_id'),
      sekretaris: $('#sekretaris_cabor_nik_add_id'),
      bendahara: $('#bendahara_cabor_nik_add_id')
  };
  var submitBtnCbr = $('#submitTambahCaborBtnId');
  var nikPengurusAjaxUrlCbr = '" . rtrim($app_base_path, '/') . "/ajax/cek_nik_pengurus_cabor.php';
  var globalNikErrorDivCbr = $('#global-nik-pengurus-error-container-js');

  function validateAllNikPengurusCabor() {
    let isFormValidNik = true;
    let selectedNiks = {}; 
    let niksForAjax = {}; 
    globalNikErrorDivCbr.hide().empty();

    $.each(nikFieldsCbr, function(jabatan, field) {
        field.removeClass('is-invalid is-valid');
        field.closest('.form-group').find('.nik-pengurus-feedback').hide().text('');
    });

    let internalDuplicatesArr = [];
    $.each(nikFieldsCbr, function(jabatan, field) {
        let currentNikVal = field.val();
        if (currentNikVal && currentNikVal !== '') {
            if (selectedNiks[currentNikVal]) {
                let jabatanLamaVal = selectedNiks[currentNikVal];
                internalDuplicatesArr.push('NIK ' + currentNikVal + ' ('+field.find('option:selected').text().split(' (')[0]+') tidak bisa untuk ' + jabatan + ' karena sudah dipilih sebagai ' + jabatanLamaVal + '.');
                field.addClass('is-invalid');
                nikFieldsCbr[jabatanLamaVal].addClass('is-invalid');
                isFormValidNik = false;
            } else {
                selectedNiks[currentNikVal] = jabatan;
                niksForAjax[currentNikVal] = { field: field, jabatan: jabatan };
            }
        }
    });

    if (internalDuplicatesArr.length > 0) {
        globalNikErrorDivCbr.html('<ul>' + internalDuplicatesArr.map(function(msg){ return '<li>'+msg+'</li>';}).join('') + '</ul>').show();
    }
    
    let ajaxPromisesArrJS = [];
    if (isFormValidNik && Object.keys(niksForAjax).length > 0) { // Cek jika ada NIK untuk divalidasi AJAX
        $.each(niksForAjax, function(nik, data) {
            let fieldElemJS = data.field;
            let feedbackDivElemJS = fieldElemJS.closest('.form-group').find('.nik-pengurus-feedback');
            feedbackDivElemJS.html('<small class=\"text-info\"><i class=\"fas fa-spinner fa-spin\"></i> Cek NIK ' + nik + '...</small>').show();
            
            let ajaxPromiseItemJS = $.ajax({
                url: nikPengurusAjaxUrlCbr, type: 'POST', data: { nik: nik }, dataType: 'json'
            }).done(function(response) {
                if (response.status === 'exists') {
                    feedbackDivElemJS.html('<small class=\"text-danger\"><i class=\"fas fa-times-circle\"></i> ' + response.message + '</small>').show();
                    fieldElemJS.addClass('is-invalid').removeClass('is-valid');
                } else if (response.status === 'available') {
                    feedbackDivElemJS.html('<small class=\"text-success\"><i class=\"fas fa-check-circle\"></i> NIK tersedia.</small>').show();
                    fieldElemJS.removeClass('is-invalid').addClass('is-valid');
                } else {
                    feedbackDivElemJS.html('<small class=\"text-warning\"><i class=\"fas fa-exclamation-triangle\"></i> ' + (response.message || 'Respon tidak dikenal.') + '</small>').show();
                }
            }).fail(function() {
                feedbackDivElemJS.html('<small class=\"text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Gagal cek NIK (server error).</small>').show();
            }).always(function(){
                updateSubmitButtonCbrStatus(); 
            });
            ajaxPromisesArrJS.push(ajaxPromiseItemJS);
        });
    }
    
    function updateSubmitButtonCbrStatus() {
        let allNiksCurrentlyOkay = true;
        if (internalDuplicatesArr.length > 0) {
            allNiksCurrentlyOkay = false;
        } else {
            $('.nik-pengurus-cabor-js-input').each(function(){
                if($(this).hasClass('is-invalid')){
                    allNiksCurrentlyOkay = false; return false; 
                }
            });
        }
        submitBtnCbr.prop('disabled', !allNiksCurrentlyOkay);
    }

    if (ajaxPromisesArrJS.length > 0) {
        submitBtnCbr.prop('disabled', true); 
        $.when.apply(null, ajaxPromisesArrJS).always(function() {
            updateSubmitButtonCbrStatus();
        });
    } else {
        updateSubmitButtonCbrStatus();
    }
  }

  $('.nik-pengurus-cabor-js-input').on('change select2:select select2:unselect select2:clear', function() {
    validateAllNikPengurusCabor();
  });

  var isNikPreFilledOnLoadCbr = false;
  $('.nik-pengurus-cabor-js-input').each(function(){ if ($(this).val() && $(this).val() !== '') { isNikPreFilledOnLoadCbr = true; return false; } });
  if(isNikPreFilledOnLoadCbr){ validateAllNikPengurusCabor(); }

  $('#formTambahCabor').submit(function(e) {
    let isValidSubmitCbrFinal = true;
    let focusTargetSubmitCbrFinal = null;
    $(this).find('.form-control.is-invalid').removeClass('is-invalid');
    $(this).find('.select2-container .select2-selection--single').removeClass('is-invalid');
    $(this).find('.custom-file-label.text-danger').removeClass('text-danger border-danger');

    if ($('#nama_cabor_input_add_final_id').val().trim() === '') {
        isValidSubmitCbrFinal = false; if (!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#nama_cabor_input_add_final_id');
        $('#nama_cabor_input_add_final_id').addClass('is-invalid');
    }
    // if ($('#ketua_cabor_nik_add_id').val() === '' || $('#ketua_cabor_nik_add_id').val() === null) { // Jika Ketua wajib
    //     isValidSubmitCbrFinal = false; if (!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#ketua_cabor_nik_add_id');
    //     $('#ketua_cabor_nik_add_id').next('.select2-container').find('.select2-selection--single').addClass('is-invalid');
    // }
    if ($('#status_kepengurusan_add_id').val() === '') {
        isValidSubmitCbrFinal = false; if (!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#status_kepengurusan_add_id');
        $('#status_kepengurusan_add_id').addClass('is-invalid');
    }

    let emailCbrSubmitVal = $('#email_cabor_add_id').val().trim();
    if (emailCbrSubmitVal !== '') {
        var emailPatternCbrSubmit = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,6}$/;
        if (!emailPatternCbrSubmit.test(emailCbrSubmitVal)) {
            isValidSubmitCbrFinal = false; if (!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#email_cabor_add_id');
            $('#email_cabor_add_id').addClass('is-invalid');
        }
    }
    
    let periodeMulaiSubVal = $('#periode_mulai_add_id').val();
    let periodeSelesaiSubVal = $('#periode_selesai_add_id').val();
    if (periodeMulaiSubVal && periodeSelesaiSubVal && periodeSelesaiSubVal < periodeMulaiSubVal) {
        isValidSubmitCbrFinal = false; if (!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#periode_selesai_add_id');
        $('#periode_mulai_add_id').addClass('is-invalid'); $('#periode_selesai_add_id').addClass('is-invalid');
        if(isValidSubmitCbrFinal) alert('Tanggal Periode Selesai tidak boleh sebelum Tanggal Periode Mulai.');
    }

    let fileSKSubmit = $('#path_file_sk_provinsi_add_id')[0].files[0];
    if(fileSKSubmit && fileSKSubmit.size > (" . $max_sk_bytes_js_cbr_final . ")){ 
       isValidSubmitCbrFinal = false; if(!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#path_file_sk_provinsi_add_id');
       $('#path_file_sk_provinsi_add_id').closest('.custom-file').find('.custom-file-label').addClass('text-danger border-danger');
       if(isValidSubmitCbrFinal) alert('Ukuran file SK Provinsi tidak boleh lebih dari " . MAX_FILE_SIZE_SK_CABOR_MB . "MB.');
    }

    let fileLogoSubmit = $('#logo_cabor_add_id')[0].files[0];
    if(fileLogoSubmit && fileLogoSubmit.size > (" . $max_logo_bytes_js_cbr_final . ")){ 
       isValidSubmitCbrFinal = false; if(!focusTargetSubmitCbrFinal) focusTargetSubmitCbrFinal = $('#logo_cabor_add_id');
       $('#logo_cabor_add_id').closest('.custom-file').find('.custom-file-label').addClass('text-danger border-danger');
       if(isValidSubmitCbrFinal) alert('Ukuran file Logo tidak boleh lebih dari " . MAX_FILE_SIZE_LOGO_MB . "MB.');
    }
    
    if (submitBtnCbrJS.is(':disabled') && isValidSubmitCbrFinal) {
        isValidSubmitCbrFinal = false; 
        if (!focusTargetSubmitCbrFinal) { $('.nik-pengurus-cabor-js-input.is-invalid').first().each(function(){ focusTargetSubmitCbrFinal = $(this); }); }
        if (globalNikErrorDivCbrJS.is(':visible') && globalNikErrorDivCbrJS.text() !== ''){ /* Biarkan pesan global */ } 
        else if ($('.nik-pengurus-feedback.invalid-feedback:visible').length > 0 && $('.nik-pengurus-feedback.invalid-feedback:visible').first().text() !== ''){ /* Biarkan pesan field */ } 
        else { alert('Harap perbaiki isian NIK pengurus yang bermasalah.'); }
    }
    
    if (!isValidSubmitCbrFinal) {
        e.preventDefault(); 
        if(focusTargetSubmitCbrFinal) { /* ... (Logika fokus field error seperti sebelumnya) ... */ }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>