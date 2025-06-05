<?php
// File: reaktorsystem/admin/users/form_edit_pengguna.php
$page_title = "Edit Data Pengguna";

// --- Definisi Aset CSS & JS ---
$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php'); // header.php sudah me-require init_core.php

// ---- Definisi konstanta ukuran file (jika belum ada di init_core.php, sebagai fallback) ----
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_MB')) { define('MAX_FILE_SIZE_FOTO_PROFIL_MB', 1); }
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_BYTES')) { define('MAX_FILE_SIZE_FOTO_PROFIL_BYTES', MAX_FILE_SIZE_FOTO_PROFIL_MB * 1024 * 1024); }
// Pastikan $default_avatar_path_relative juga ada dari init_core.php
$default_avatar_to_use = $default_avatar_path_relative ?? 'assets/adminlte/dist/img/kepitran.jpg';


// Pengecekan sesi & peran pengguna
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo) || !defined('APP_PATH_BASE')) { 
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah.";
    $login_url_redirect_edit_form = rtrim($app_base_path ?? '/', '/') . "/auth/login.php";
    if (!headers_sent()) { header("Location: " . $login_url_redirect_edit_form); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error kritis: Sesi tidak valid. Harap <a href='" . htmlspecialchars($login_url_redirect_edit_form, ENT_QUOTES, 'UTF-8') . "'>login ulang</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

if (!in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

$nik_to_edit_pgn_val_form = $_GET['nik'] ?? null;
$pengguna_data_to_edit_form = null;
$daftar_pengguna_url_form = "daftar_pengguna.php"; 

if (!$nik_to_edit_pgn_val_form || !preg_match('/^\d{1,16}$/', $nik_to_edit_pgn_val_form)) {
    $_SESSION['pesan_error_global'] = "NIK pengguna tidak valid atau tidak disediakan untuk diedit.";
    header("Location: " . $daftar_pengguna_url_form);
    exit();
}

try {
    $stmt_pgn_to_edit_form = $pdo->prepare("SELECT * FROM pengguna WHERE nik = :nik_val_param");
    $stmt_pgn_to_edit_form->bindParam(':nik_val_param', $nik_to_edit_pgn_val_form, PDO::PARAM_STR);
    $stmt_pgn_to_edit_form->execute();
    $pengguna_data_to_edit_form = $stmt_pgn_to_edit_form->fetch(PDO::FETCH_ASSOC);

    if (!$pengguna_data_to_edit_form) {
        $_SESSION['pesan_error_global'] = "Data pengguna dengan NIK " . htmlspecialchars($nik_to_edit_pgn_val_form) . " tidak ditemukan.";
        header("Location: " . $daftar_pengguna_url_form);
        exit();
    }
} catch (PDOException $e_fetch_form) {
    error_log("Form Edit Pengguna - Gagal ambil data pengguna: " . $e_fetch_form->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data pengguna untuk diedit.";
    header("Location: " . $daftar_pengguna_url_form);
    exit();
}

$form_data_pgn_edit_sess_val_form = $_SESSION['form_data_pengguna_edit'] ?? [];
$form_errors_pgn_edit_val_form = $_SESSION['errors_pengguna_edit'] ?? [];
$form_error_fields_pgn_edit_val_form = $_SESSION['error_fields_pengguna_edit'] ?? [];

unset($_SESSION['form_data_pengguna_edit']);
unset($_SESSION['errors_pengguna_edit']);
unset($_SESSION['error_fields_pengguna_edit']);

$val_nama_lengkap_edit_form_final = $form_data_pgn_edit_sess_val_form['nama_lengkap'] ?? ($pengguna_data_to_edit_form['nama_lengkap'] ?? '');
$val_email_edit_form_final = $form_data_pgn_edit_sess_val_form['email'] ?? ($pengguna_data_to_edit_form['email'] ?? '');
$val_tanggal_lahir_edit_form_final = $form_data_pgn_edit_sess_val_form['tanggal_lahir'] ?? ($pengguna_data_to_edit_form['tanggal_lahir'] ?? '');
$val_jenis_kelamin_edit_form_final = $form_data_pgn_edit_sess_val_form['jenis_kelamin'] ?? ($pengguna_data_to_edit_form['jenis_kelamin'] ?? '');
$val_alamat_edit_form_final = $form_data_pgn_edit_sess_val_form['alamat'] ?? ($pengguna_data_to_edit_form['alamat'] ?? '');
$val_nomor_telepon_edit_form_final = $form_data_pgn_edit_sess_val_form['nomor_telepon'] ?? ($pengguna_data_to_edit_form['nomor_telepon'] ?? '');
$val_is_approved_edit_form_final = isset($form_data_pgn_edit_sess_val_form['is_approved']) ? $form_data_pgn_edit_sess_val_form['is_approved'] : ($pengguna_data_to_edit_form['is_approved'] ?? '0');
$current_foto_path_form_val = $pengguna_data_to_edit_form['foto'] ?? '';
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-warning"> <?php // Kelas diubah di sini (menghilangkan card-outline) ?>
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-edit mr-1"></i> <?php echo htmlspecialchars($page_title); ?>: <strong><?php echo htmlspecialchars($pengguna_data_to_edit_form['nama_lengkap'] ?? $nik_to_edit_pgn_val_form); ?></strong></h3>
                    </div>
                    <form id="formEditPengguna" action="proses_edit_pengguna.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="nik_original" value="<?php echo htmlspecialchars($pengguna_data_to_edit_form['nik']); ?>">
                        <input type="hidden" name="current_foto_path" value="<?php echo htmlspecialchars($current_foto_path_form_val); ?>">

                        <div class="card-body">
                            <?php if (!empty($form_errors_pgn_edit_val_form)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Mengupdate Data!</h5>
                                    <ul> <?php foreach ($form_errors_pgn_edit_val_form as $err_item_pgn_e_html_loop): ?> <li><?php echo htmlspecialchars($err_item_pgn_e_html_loop); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-orange"><i class="fas fa-id-card mr-1"></i> Informasi Identitas & Akun</h5>
                            <div class="form-group">
                                <label>NIK (Nomor Induk Kependudukan)</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($pengguna_data_to_edit_form['nik']); ?>" readonly disabled>
                                <small class="form-text text-muted">NIK tidak dapat diubah.</small>
                            </div>
                            <div class="form-group">
                                <label for="nama_lengkap_edit_pengguna">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(in_array('nama_lengkap', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="nama_lengkap_edit_pengguna" name="nama_lengkap" value="<?php echo htmlspecialchars($val_nama_lengkap_edit_form_final); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email_edit_pengguna">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?php if(in_array('email', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="email_edit_pengguna" name="email" value="<?php echo htmlspecialchars($val_email_edit_form_final); ?>" required>
                                <small id="email_info_display_edit" class="form-text"></small>
                            </div>
                            <div class="form-group">
                                <label for="password_edit_pengguna">Password Baru (Opsional)</label>
                                <input type="password" class="form-control <?php if(in_array('password', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="password_edit_pengguna" name="password" placeholder="Isi jika ingin ganti (min. 6 karakter)" minlength="6">
                                <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password saat ini.</small>
                            </div>
                             <div class="form-group">
                                <label for="konfirmasi_password_edit_pengguna">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control <?php if(in_array('konfirmasi_password', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="konfirmasi_password_edit_pengguna" name="konfirmasi_password" placeholder="Ulangi password baru (jika diisi)">
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-orange"><i class="fas fa-user-circle mr-1"></i> Informasi Pribadi (Opsional)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_lahir_edit_pengguna">Tanggal Lahir</label>
                                        <input type="date" class="form-control <?php if(in_array('tanggal_lahir', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="tanggal_lahir_edit_pengguna" name="tanggal_lahir" value="<?php echo htmlspecialchars($val_tanggal_lahir_edit_form_final); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="jenis_kelamin_edit_pengguna">Jenis Kelamin</label>
                                        <select class="form-control select2bs4 <?php if(in_array('jenis_kelamin', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="jenis_kelamin_edit_pengguna" name="jenis_kelamin" style="width: 100%;" data-placeholder="-- Pilih Jenis Kelamin --">
                                            <option value=""></option>
                                            <option value="Laki-laki" <?php echo ($val_jenis_kelamin_edit_form_final == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                            <option value="Perempuan" <?php echo ($val_jenis_kelamin_edit_form_final == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="alamat_edit_pengguna">Alamat Lengkap</label>
                                <textarea class="form-control <?php if(in_array('alamat', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="alamat_edit_pengguna" name="alamat" rows="3" placeholder="Alamat lengkap sesuai KTP"><?php echo htmlspecialchars($val_alamat_edit_form_final); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="nomor_telepon_edit_pengguna">Nomor Telepon/HP</label>
                                <input type="text" class="form-control <?php if(in_array('nomor_telepon', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="nomor_telepon_edit_pengguna" name="nomor_telepon" placeholder="Format: 08xxxxxxxxxx" value="<?php echo htmlspecialchars($val_nomor_telepon_edit_form_final); ?>">
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-orange"><i class="fas fa-image mr-1"></i> Foto Profil</h5>
                            <div class="form-group">
                                <label for="foto_edit_pengguna">Ganti Foto Profil (Opsional)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(in_array('foto', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="foto_edit_pengguna" name="foto" accept="image/jpeg, image/png, image/gif">
                                    <label class="custom-file-label" for="foto_edit_pengguna">Pilih file foto baru...</label>
                                </div>
                                <small class="form-text text-muted">Maks <?php echo MAX_FILE_SIZE_FOTO_PROFIL_MB; ?>MB. Kosongkan jika tidak ingin mengubah foto.</small>
                                <?php 
                                $foto_profil_untuk_ditampilkan_edit = $default_avatar_to_use;
                                if (!empty($current_foto_path_form_val)) {
                                    $path_foto_server_edit = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($current_foto_path_form_val, '/\\');
                                    if (file_exists(preg_replace('/\/+/', '/', $path_foto_server_edit)) && is_file(preg_replace('/\/+/', '/', $path_foto_server_edit))) {
                                        $foto_profil_untuk_ditampilkan_edit = $current_foto_path_form_val;
                                    }
                                }
                                $url_foto_tampil_edit = rtrim($app_base_path, '/') . '/' . ltrim($foto_profil_untuk_ditampilkan_edit, '/');
                                $url_foto_tampil_edit = preg_replace('/\/+/', '/', $url_foto_tampil_edit);
                                ?>
                                <div class="mt-2">
                                    <small>Foto Saat Ini:</small><br>
                                    <img src="<?php echo htmlspecialchars($url_foto_tampil_edit); ?>" alt="Foto Profil Saat Ini" style="max-height: 80px; width: auto; border:1px solid #ddd; padding:3px; border-radius: 4px;">
                                    <?php if ($foto_profil_untuk_ditampilkan_edit !== $default_avatar_to_use): ?>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" value="1" id="hapus_foto_current" name="hapus_foto_current">
                                            <label class="form-check-label text-danger" for="hapus_foto_current">
                                                Hapus foto profil saat ini (akan diganti default jika tidak ada foto baru).
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($user_role_utama == 'super_admin' && $pengguna_data_to_edit_form['nik'] != $user_nik): ?>
                            <hr>
                            <h5 class="mt-3 mb-3 text-orange"><i class="fas fa-user-shield mr-1"></i> Status Akun</h5>
                            <div class="form-group">
                                <label for="is_approved_edit_pengguna">Status Persetujuan Akun</label>
                                <select class="form-control select2bs4 <?php if(in_array('is_approved', $form_error_fields_pgn_edit_val_form)) echo 'is-invalid'; ?>" id="is_approved_edit_pengguna" name="is_approved" style="width: 100%;" data-placeholder="-- Pilih Status --">
                                    <option value="1" <?php echo ($val_is_approved_edit_form_final == 1) ? 'selected' : ''; ?>>Disetujui</option>
                                    <option value="0" <?php echo ($val_is_approved_edit_form_final == 0) ? 'selected' : ''; ?>>Pending / Ditangguhkan</option>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="is_approved" value="<?php echo htmlspecialchars($pengguna_data_to_edit_form['is_approved']); ?>">
                            <?php endif; ?>

                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_pengguna" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                            <a href="<?php echo $daftar_pengguna_url_form; ?>" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Inline script dipindahkan ke variabel agar bisa di-echo oleh footer.php
$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    bsCustomFileInput.init();
  }

  if (typeof $.fn.select2 === 'function') {
    $('#jenis_kelamin_edit_pengguna.select2bs4, #is_approved_edit_pengguna.select2bs4').each(function() {
        $(this).select2({ 
            theme: 'bootstrap4', 
            placeholder: $(this).data('placeholder'), 
            allowClear: ($(this).find('option[value=\"\"]').length > 0 && $(this).find('option[value=\"\"]:selected').length === 0) // Allow clear jika ada opsi kosong dan belum terpilih
        });
    });
  }

  var originalEmailEditPgn = '" . htmlspecialchars($pengguna_data_to_edit_form['email'] ?? '', ENT_QUOTES, 'UTF-8') . "';
  $('#email_edit_pengguna').on('keyup change focusout', function() {
    var emailInputElPgn = $(this);
    var emailValueEditPgn = emailInputElPgn.val().trim();
    var displayElementEditPgn = $('#email_info_display_edit');
    
    emailInputElPgn.removeClass('is-invalid is-valid');
    displayElementEditPgn.html('');

    if (emailValueEditPgn === '' || emailValueEditPgn === originalEmailEditPgn) {
        return;
    }
    
    var emailPatternCheckPgn = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,6}$/;
    if (!emailPatternCheckPgn.test(emailValueEditPgn)) {
        displayElementEditPgn.html('<small class=\"form-text text-danger\"><i class=\"fas fa-times-circle\"></i> Format email tidak valid.</small>');
        emailInputElPgn.addClass('is-invalid');
        return;
    }

    displayElementEditPgn.html('<small class=\"form-text text-info\"><i class=\"fas fa-spinner fa-spin\"></i> Mengecek email...</small>');
    $.ajax({
        url: '" . rtrim($app_base_path, '/') . "/ajax/cek_email_unik.php',
        type: 'POST',
        data: { email: emailValueEditPgn, nik_current_check: '" . htmlspecialchars($nik_to_edit_pgn_val_form, ENT_QUOTES, 'UTF-8') . "' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'exists') { 
                displayElementEditPgn.html('<small class=\"form-text text-danger\"><i class=\"fas fa-times-circle\"></i> ' + (response.message || 'Email sudah digunakan.') + '</small>');
                emailInputElPgn.addClass('is-invalid').removeClass('is-valid');
            } else if (response.status === 'available') {
                displayElementEditPgn.html('<small class=\"form-text text-success\"><i class=\"fas fa-check-circle\"></i> Email tersedia.</small>');
                emailInputElPgn.addClass('is-valid').removeClass('is-invalid');
            } else { 
                displayElementEditPgn.html('<small class=\"form-text text-warning\"><i class=\"fas fa-exclamation-triangle\"></i> ' + (response.message || 'Tidak dapat cek email.') + '</small>');
                emailInputElPgn.removeClass('is-valid is-invalid');
            }
        },
        error: function() {
            displayElementEditPgn.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Error koneksi cek email.</small>');
            emailInputElPgn.removeClass('is-valid is-invalid');
        }
    });
  });
  // Trigger cek email jika nilai awal berbeda (misal dari repopulate error)
  if ($('#email_edit_pengguna').val().trim() !== originalEmailEditPgn && $('#email_edit_pengguna').val().trim() !== '') {
      $('#email_edit_pengguna').trigger('change');
  }

  $('#formEditPengguna').submit(function(e) {
    let isValidFormEditPgn = true;
    let focusFirstErrorFieldPgn = null;
    $('.is-invalid').removeClass('is-invalid'); 
    $('.custom-file-label').removeClass('text-danger border-danger');


    const requiredFieldsEditArr = ['nama_lengkap_edit_pengguna', 'email_edit_pengguna'];
    requiredFieldsEditArr.forEach(function(fieldIdValPgn) {
        let fieldElement = $('#' + fieldIdValPgn);
        if (fieldElement.val().trim() === '') {
            if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = fieldElement;
            fieldElement.addClass('is-invalid');
            isValidFormEditPgn = false;
        }
    });
    
    let emailToCheckSubmit = $('#email_edit_pengguna').val().trim();
    var emailPatternForSubmit = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,6}$/;
    if (emailToCheckSubmit === '' || !emailPatternForSubmit.test(emailToCheckSubmit)) {
        if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = $('#email_edit_pengguna');
        $('#email_edit_pengguna').addClass('is-invalid');
        isValidFormEditPgn = false;
    }
    if ($('#email_info_display_edit').find('.text-danger').length > 0){
        if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = $('#email_edit_pengguna');
        $('#email_edit_pengguna').addClass('is-invalid');
        isValidFormEditPgn = false;
    }

    let passwordValEditPgn = $('#password_edit_pengguna').val();
    if (passwordValEditPgn !== '') {
        if (passwordValEditPgn.length < 6) {
            if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = $('#password_edit_pengguna');
            $('#password_edit_pengguna').addClass('is-invalid');
            isValidFormEditPgn = false;
        }
        let konfirmasiPasswordValEditPgn = $('#konfirmasi_password_edit_pengguna').val();
        if (konfirmasiPasswordValEditPgn === '') {
            if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = $('#konfirmasi_password_edit_pengguna');
            $('#konfirmasi_password_edit_pengguna').addClass('is-invalid');
            isValidFormEditPgn = false;
        } else if (passwordValEditPgn !== konfirmasiPasswordValEditPgn) {
            if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = $('#konfirmasi_password_edit_pengguna');
            $('#konfirmasi_password_edit_pengguna').addClass('is-invalid');
            $('#password_edit_pengguna').addClass('is-invalid');
            isValidFormEditPgn = false;
        }
    }
    
    let fotoFileValEditPgn = $('#foto_edit_pengguna')[0].files[0];
    if(fotoFileValEditPgn && fotoFileValEditPgn.size > " . MAX_FILE_SIZE_FOTO_PROFIL_BYTES . "){ 
       if(!focusFirstErrorFieldPgn) focusFirstErrorFieldPgn = $('#foto_edit_pengguna');
       $('#foto_edit_pengguna').closest('.custom-file').find('.custom-file-label').addClass('text-danger border-danger');
       alert('Ukuran file Foto Profil tidak boleh lebih dari " . MAX_FILE_SIZE_FOTO_PROFIL_MB . "MB.');
       isValidFormEditPgn = false;
    }
    
    if (!isValidFormEditPgn) {
        e.preventDefault(); 
        if(focusFirstErrorFieldPgn) {
            $('html, body').animate({ scrollTop: focusFirstErrorFieldPgn.closest('.form-group').offset().top - 70 }, 500);
            focusFirstErrorFieldPgn.focus();
            if (focusFirstErrorFieldPgn.is('select.select2bs4')) {
                focusFirstErrorFieldPgn.select2('open');
            }
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>