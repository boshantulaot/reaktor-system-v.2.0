<?php
// File: reaktorsystem/admin/users/form_tambah_pengguna.php
$page_title = "Tambah Pengguna Baru";

// --- Definisi Aset CSS & JS ---
$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    // bsCustomFileInput akan diinisialisasi dari footer.php atau $inline_script
];

require_once(__DIR__ . '/../../core/header.php');

// ---- Definisi konstanta ukuran file ----
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_MB')) { define('MAX_FILE_SIZE_FOTO_PROFIL_MB', 1); } // Max 1MB untuk foto profil
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_BYTES')) { define('MAX_FILE_SIZE_FOTO_PROFIL_BYTES', MAX_FILE_SIZE_FOTO_PROFIL_MB * 1024 * 1024); }

// Pengecekan sesi & peran pengguna
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo)) { 
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah.";
    if (!headers_sent()) { header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php"); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error kritis: Sesi tidak valid. Harap <a href='" . rtrim($app_base_path, '/') . "/auth/login.php'>login ulang</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

if (!in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Ambil data form dari session jika ada (untuk repopulate saat error)
$form_data_pengguna_tambah = $_SESSION['form_data_pengguna_tambah'] ?? [];
$form_errors_pengguna_tambah = $_SESSION['errors_pengguna_tambah'] ?? [];
$form_error_fields_pengguna_tambah = $_SESSION['error_fields_pengguna_tambah'] ?? [];

unset($_SESSION['form_data_pengguna_tambah']);
unset($_SESSION['errors_pengguna_tambah']);
unset($_SESSION['error_fields_pengguna_tambah']);
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-plus mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formTambahPengguna" action="proses_tambah_pengguna.php" method="POST" enctype="multipart/form-data">
                        <div class="card-body">
                            <?php if (!empty($form_errors_pengguna_tambah)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Menyimpan Data Pengguna!</h5>
                                    <ul>
                                        <?php foreach ($form_errors_pengguna_tambah as $error_item_pgn): ?>
                                            <li><?php echo htmlspecialchars($error_item_pgn); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-primary"><i class="fas fa-id-card mr-1"></i> Informasi Identitas & Akun</h5>
                            <div class="form-group">
                                <label for="nik_tambah_pengguna">NIK (Nomor Induk Kependudukan) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control nik-input-ajax <?php if(in_array('nik', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="nik_tambah_pengguna" name="nik"
                                       placeholder="Ketik NIK Pengguna (16 digit)"
                                       value="<?php echo htmlspecialchars($form_data_pengguna_tambah['nik'] ?? ''); ?>" maxlength="16"
                                       pattern="\d{16}" data-display-target="nik_info_display_tambah" required>
                                <small id="nik_info_display_tambah" class="form-text"></small>
                                <small class="form-text text-muted">NIK akan menjadi username untuk login.</small>
                            </div>
                            <div class="form-group">
                                <label for="nama_lengkap_tambah_pengguna">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(in_array('nama_lengkap', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="nama_lengkap_tambah_pengguna" name="nama_lengkap"
                                       placeholder="Nama Lengkap Sesuai KTP"
                                       value="<?php echo htmlspecialchars($form_data_pengguna_tambah['nama_lengkap'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email_tambah_pengguna">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?php if(in_array('email', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="email_tambah_pengguna" name="email"
                                       placeholder="Alamat Email Aktif"
                                       value="<?php echo htmlspecialchars($form_data_pengguna_tambah['email'] ?? ''); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password_tambah_pengguna">Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control <?php if(in_array('password', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="password_tambah_pengguna" name="password"
                                               placeholder="Minimal 6 karakter" minlength="6" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="konfirmasi_password_tambah_pengguna">Konfirmasi Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control <?php if(in_array('konfirmasi_password', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="konfirmasi_password_tambah_pengguna" name="konfirmasi_password"
                                               placeholder="Ulangi password" required>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-user-circle mr-1"></i> Informasi Pribadi (Opsional)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_lahir_tambah_pengguna">Tanggal Lahir</label>
                                        <input type="date" class="form-control <?php if(in_array('tanggal_lahir', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="tanggal_lahir_tambah_pengguna" name="tanggal_lahir"
                                               value="<?php echo htmlspecialchars($form_data_pengguna_tambah['tanggal_lahir'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="jenis_kelamin_tambah_pengguna">Jenis Kelamin</label>
                                        <select class="form-control select2bs4 <?php if(in_array('jenis_kelamin', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="jenis_kelamin_tambah_pengguna" name="jenis_kelamin" style="width: 100%;" data-placeholder="-- Pilih Jenis Kelamin --">
                                            <option value=""></option>
                                            <option value="Laki-laki" <?php echo (($form_data_pengguna_tambah['jenis_kelamin'] ?? '') == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                            <option value="Perempuan" <?php echo (($form_data_pengguna_tambah['jenis_kelamin'] ?? '') == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="alamat_tambah_pengguna">Alamat Lengkap</label>
                                <textarea class="form-control <?php if(in_array('alamat', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="alamat_tambah_pengguna" name="alamat" rows="3" placeholder="Alamat Lengkap Sesuai KTP"><?php echo htmlspecialchars($form_data_pengguna_tambah['alamat'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="nomor_telepon_tambah_pengguna">Nomor Telepon/HP</label>
                                <input type="text" class="form-control <?php if(in_array('nomor_telepon', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="nomor_telepon_tambah_pengguna" name="nomor_telepon"
                                       placeholder="Format: 08xxxxxxxxxx" value="<?php echo htmlspecialchars($form_data_pengguna_tambah['nomor_telepon'] ?? ''); ?>">
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-image mr-1"></i> Foto Profil (Opsional)</h5>
                            <div class="form-group">
                                <label for="foto_tambah_pengguna">Upload Foto</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(in_array('foto', $form_error_fields_pengguna_tambah)) echo 'is-invalid'; ?>" id="foto_tambah_pengguna" name="foto" accept="image/jpeg, image/png, image/gif">
                                    <label class="custom-file-label" for="foto_tambah_pengguna">Pilih file foto...</label>
                                </div>
                                <small class="form-text text-muted">Maks <?php echo MAX_FILE_SIZE_FOTO_PROFIL_MB; ?>MB (JPG, PNG, GIF). Rasio 1:1 direkomendasikan.</small>
                            </div>

                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_pengguna" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Pengguna</button>
                            <a href="daftar_pengguna.php" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
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

  if (typeof $.fn.select2 === 'function') {
    $('#jenis_kelamin_tambah_pengguna.select2bs4').select2({
      theme: 'bootstrap4',
      placeholder: $(this).data('placeholder') || '-- Pilih Jenis Kelamin --',
      allowClear: true
    });
  }

  $('.nik-input-ajax').on('keyup change focusout', function() {
    var nikInput = $(this);
    var nikValue = nikInput.val().trim();
    var displayTargetId = nikInput.data('display-target');
    var displayElement = $('#' + displayTargetId);
    
    nikInput.removeClass('is-invalid is-valid');
    displayElement.html('');

    if (nikValue.length === 0) {
        return; 
    }

    if (nikValue.length === 16 && /^\\d{16}$/.test(nikValue)) {
        displayElement.html('<small class=\"form-text text-info\"><i class=\"fas fa-spinner fa-spin\"></i> Mengecek ketersediaan NIK...</small>');
        $.ajax({
            url: '" . rtrim($app_base_path, '/') . "/ajax/cek_nama_nik.php',
            type: 'POST',
            data: { 
                nik: nikValue, 
                context: 'pengguna_baru' // Mengirimkan konteks
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'exists') { 
                    displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-times-circle\"></i> ' + (response.message || 'NIK sudah terdaftar.') + (response.nama_lengkap ? ' Atas nama: <strong>' + response.nama_lengkap + '</strong>' : '') + '</small>');
                    nikInput.addClass('is-invalid').removeClass('is-valid');
                } else if (response.status === 'available') {
                    displayElement.html('<small class=\"form-text text-success\"><i class=\"fas fa-check-circle\"></i> ' + (response.message || 'NIK ini tersedia untuk didaftarkan.') + '</small>');
                    nikInput.addClass('is-valid').removeClass('is-invalid');
                } else { 
                    displayElement.html('<small class=\"form-text text-warning\"><i class=\"fas fa-exclamation-triangle\"></i> ' + (response.message || 'Tidak dapat memverifikasi NIK saat ini.') + '</small>');
                    nikInput.removeClass('is-valid is-invalid');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Error: ' + textStatus + '. Tidak bisa cek NIK.</small>');
                console.error('AJAX Cek NIK Error:', textStatus, errorThrown, jqXHR.responseText);
                nikInput.removeClass('is-valid is-invalid');
            }
        });
    } else if (nikValue.length > 0) {
        displayElement.html('<small class=\"form-text text-muted\">NIK harus terdiri dari 16 digit angka.</small>');
        nikInput.addClass('is-invalid').removeClass('is-valid');
    }
  });

  if ($('.nik-input-ajax').val().length === 16) {
      $('.nik-input-ajax').trigger('change');
  }

  $('#formTambahPengguna').submit(function(e) {
    let isValidPgn = true;
    let focusFieldPgn = null;
    $('.is-invalid').removeClass('is-invalid');

    let nikInputTambah = $('#nik_tambah_pengguna');
    if (nikInputTambah.hasClass('is-invalid')) {
        let nikErrorMsg = $('#nik_info_display_tambah').find('.text-danger').text();
        if (nikErrorMsg.toLowerCase().includes('sudah terdaftar')) {
            alert('NIK yang Anda masukkan sudah terdaftar atau tidak valid. Harap perbaiki.');
            if(!focusFieldPgn) focusFieldPgn = nikInputTambah;
            isValidPgn = false;
        }
    }
    if (nikInputTambah.val().trim().length !== 16 || !/^\\d{16}$/.test(nikInputTambah.val().trim())) {
        if(!focusFieldPgn) focusFieldPgn = nikInputTambah;
        nikInputTambah.addClass('is-invalid');
        $('#nik_info_display_tambah').html('<small class=\"form-text text-danger\">NIK wajib diisi dan harus 16 digit angka.</small>');
        isValidPgn = false;
    }

    const requiredFieldsPgn = ['nama_lengkap_tambah_pengguna', 'email_tambah_pengguna', 'password_tambah_pengguna', 'konfirmasi_password_tambah_pengguna'];
    requiredFieldsPgn.forEach(function(fieldId) {
        let field = $('#' + fieldId);
        if (field.val().trim() === '') {
            if(!focusFieldPgn) focusFieldPgn = field;
            field.addClass('is-invalid');
            isValidPgn = false;
        }
    });
    
    let emailPgn = $('#email_tambah_pengguna').val().trim();
    var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,6}$/;
    if (emailPgn !== '' && !emailPattern.test(emailPgn)) {
        if(!focusFieldPgn) focusFieldPgn = $('#email_tambah_pengguna');
        $('#email_tambah_pengguna').addClass('is-invalid');
        isValidPgn = false;
    }

    let passwordPgn = $('#password_tambah_pengguna').val();
    let konfirmasiPasswordPgn = $('#konfirmasi_password_tambah_pengguna').val();
    if (passwordPgn !== '' && passwordPgn.length < 6) {
        if(!focusFieldPgn) focusFieldPgn = $('#password_tambah_pengguna');
        $('#password_tambah_pengguna').addClass('is-invalid');
        isValidPgn = false;
    }
    if (passwordPgn !== konfirmasiPasswordPgn) {
        if(!focusFieldPgn) focusFieldPgn = $('#konfirmasi_password_tambah_pengguna');
        $('#konfirmasi_password_tambah_pengguna').addClass('is-invalid');
        $('#password_tambah_pengguna').addClass('is-invalid');
        isValidPgn = false;
    }
    
    let fotoFilePgn = $('#foto_tambah_pengguna')[0].files[0];
    if(fotoFilePgn && fotoFilePgn.size > " . MAX_FILE_SIZE_FOTO_PROFIL_BYTES . "){ 
       if(!focusFieldPgn) focusFieldPgn = $('#foto_tambah_pengguna');
       $('#foto_tambah_pengguna').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#foto_tambah_pengguna').addClass('is-invalid');
       alert('Ukuran file Foto Profil tidak boleh lebih dari " . MAX_FILE_SIZE_FOTO_PROFIL_MB . "MB.');
       isValidPgn = false;
    }
    
    if (!isValidPgn) {
        e.preventDefault(); 
        if(focusFieldPgn) {
            focusFieldPgn.focus();
            if (focusFieldPgn.is('select.select2bs4')) {
                $('html, body').animate({ scrollTop: focusFieldPgn.next('.select2-container').offset().top - 70 }, 500);
                focusFieldPgn.select2('open');
            } else if (!focusFieldPgn.is(':visible') || focusFieldPgn.offset().top < $(window).scrollTop() || focusFieldPgn.offset().top + focusFieldPgn.outerHeight() > $(window).scrollTop() + $(window).height()) {
                $('html, body').animate({ scrollTop: focusFieldPgn.closest('.form-group').offset().top - 70 }, 500);
            }
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>