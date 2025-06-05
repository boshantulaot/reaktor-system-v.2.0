<?php
// File: reaktorsystem/modules/wasit/tambah_wasit.php

$page_title = "Tambah Data Wasit Baru";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php'); // header.php sudah include init_core.php

// Definisi konstanta ukuran file untuk wasit
if (!defined('MAX_FILE_SIZE_KTP_KK_WASIT_MB')) { define('MAX_FILE_SIZE_KTP_KK_WASIT_MB', 2); }
if (!defined('MAX_FILE_SIZE_LISENSI_WASIT_MB')) { define('MAX_FILE_SIZE_LISENSI_WASIT_MB', 2); }
if (!defined('MAX_FILE_SIZE_FOTO_WASIT_MB')) { define('MAX_FILE_SIZE_FOTO_WASIT_MB', 1); }
if (!defined('MAX_FILE_SIZE_KTP_KK_WASIT_BYTES')) { define('MAX_FILE_SIZE_KTP_KK_WASIT_BYTES', MAX_FILE_SIZE_KTP_KK_WASIT_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_LISENSI_WASIT_BYTES')) { define('MAX_FILE_SIZE_LISENSI_WASIT_BYTES', MAX_FILE_SIZE_LISENSI_WASIT_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_FOTO_WASIT_BYTES')) { define('MAX_FILE_SIZE_FOTO_WASIT_BYTES', MAX_FILE_SIZE_FOTO_WASIT_MB * 1024 * 1024); }

// Pengecekan Sesi & Peran Pengguna
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo)) { 
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah. Silakan login kembali.";
    if (!headers_sent()) {
        header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php");
    } else {
        echo "<div class='alert alert-danger text-center m-3'>Error kritis: Sesi tidak valid. Harap <a href='" . rtrim($app_base_path, '/') . "/auth/login.php'>login ulang</a>.</div>";
    }
    exit();
}

if (!in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: daftar_wasit.php");
    exit();
}

if ($user_role_utama === 'pengurus_cabor' && empty($id_cabor_pengurus_utama)) {
    $_SESSION['pesan_error_global'] = "Informasi cabang olahraga Anda tidak lengkap. Tidak dapat menambah wasit.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Logika pengambilan data cabor
$cabor_options_wasit_form = [];
$nama_cabor_pengurus_wasit_form = '';
$default_id_cabor_from_get_w = isset($_GET['id_cabor_default']) && filter_var($_GET['id_cabor_default'], FILTER_VALIDATE_INT) ? (int)$_GET['id_cabor_default'] : null;

if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_cabor_w_form = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
        $cabor_options_wasit_form = $stmt_cabor_w_form->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error tambah_wasit.php - fetch cabor list (admin): " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Gagal memuat daftar cabang olahraga.";
    }
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama)) {
    try {
        $stmt_nama_cabor_w_form = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor AND status_kepengurusan = 'Aktif'");
        $stmt_nama_cabor_w_form->bindParam(':id_cabor', $id_cabor_pengurus_utama, PDO::PARAM_INT);
        $stmt_nama_cabor_w_form->execute();
        $nama_cabor_pengurus_wasit_form = $stmt_nama_cabor_w_form->fetchColumn();
        
        if (!$nama_cabor_pengurus_wasit_form) {
            $_SESSION['pesan_error_global'] = "Cabang olahraga yang Anda kelola saat ini tidak aktif atau tidak ditemukan. Tidak dapat menambah wasit.";
            header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error tambah_wasit.php - fetch nama cabor pengurus: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Gagal memuat informasi cabang olahraga Anda.";
    }
}

// Pengambilan data form dari session
$form_data_w_tambah = $_SESSION['form_data_wasit_tambah'] ?? [];
$form_errors_w_tambah = $_SESSION['errors_wasit_tambah'] ?? [];
$form_error_fields_w_tambah = $_SESSION['error_fields_wasit_tambah'] ?? [];

unset($_SESSION['form_data_wasit_tambah'], $_SESSION['errors_wasit_tambah'], $_SESSION['error_fields_wasit_tambah']);

// Menentukan nilai default untuk form
$default_id_cabor_form_w = $form_data_w_tambah['id_cabor'] ?? ($default_id_cabor_from_get_w ?: ($user_role_utama === 'pengurus_cabor' ? $id_cabor_pengurus_utama : ''));
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-plus mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formTambahWasit" action="proses_tambah_wasit.php" method="post" enctype="multipart/form-data">
                        <div class="card-body">
                            <?php if (!empty($form_errors_w_tambah)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Mendaftarkan Wasit!</h5>
                                    <ul>
                                        <?php foreach ($form_errors_w_tambah as $error_item_w): ?>
                                            <li><?php echo htmlspecialchars($error_item_w); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-danger"><i class="fas fa-id-card mr-1"></i> Informasi Dasar Wasit</h5>
                            <div class="form-group">
                                <label for="nik_wasit_form">NIK Wasit <span class="text-danger">*</span></label>
                                <input type="text" class="form-control nik-input-ajax <?php if(isset($form_error_fields_w_tambah['nik'])) echo 'is-invalid'; ?>" id="nik_wasit_form" name="nik"
                                       placeholder="Ketik NIK Wasit (16 digit)"
                                       value="<?php echo htmlspecialchars($form_data_w_tambah['nik'] ?? ''); ?>" maxlength="16"
                                       pattern="\d{16}" data-display-target="nama_wasit_display_tambah" required>
                                <small id="nama_wasit_display_tambah" class="form-text nik-nama-display"></small>
                                <small class="form-text text-muted">Pastikan NIK sudah terdaftar di sistem pengguna dan akunnya telah disetujui.</small>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-flag mr-1"></i> Spesialisasi Cabang Olahraga</h5>
                            <div class="form-group">
                                <label for="id_cabor_wasit_form">Cabang Olahraga <span class="text-danger">*</span></label>
                                <?php if ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama)): ?>
                                    <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_pengurus_utama); ?>">
                                    <input type="text" class="form-control"
                                           value="<?php echo htmlspecialchars($nama_cabor_pengurus_wasit_form ?: 'Cabor Tidak Ditemukan/Tidak Aktif'); ?>"
                                           readonly>
                                    <?php if(!empty($nama_cabor_pengurus_wasit_form)): ?>
                                    <small class="form-text text-muted">Anda akan mendaftarkan wasit untuk cabor <?php echo htmlspecialchars($nama_cabor_pengurus_wasit_form); ?>.</small>
                                    <?php endif; ?>
                                <?php elseif (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_w_tambah['id_cabor'])) echo 'is-invalid'; ?>" id="id_cabor_wasit_form" name="id_cabor" style="width: 100%;" required data-placeholder="-- Pilih Cabang Olahraga --">
                                        <option value=""></option>
                                        <?php foreach ($cabor_options_wasit_form as $cabor_opt_w): ?>
                                            <option value="<?php echo $cabor_opt_w['id_cabor']; ?>" <?php echo ($default_id_cabor_form_w == $cabor_opt_w['id_cabor']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cabor_opt_w['nama_cabor']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($cabor_options_wasit_form)): ?>
                                        <small class="form-text text-danger">Tidak ada cabor aktif. Silakan tambahkan/aktifkan cabor.</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-certificate mr-1"></i> Informasi Kewasitan & Kontak</h5>
                            <div class="form-group">
                                <label for="nomor_lisensi_form_w">Nomor Lisensi</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_w_tambah['nomor_lisensi'])) echo 'is-invalid'; ?>" id="nomor_lisensi_form_w" name="nomor_lisensi" placeholder="Contoh: LIS-WAS-001" value="<?php echo htmlspecialchars($form_data_w_tambah['nomor_lisensi'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="kontak_wasit_form_w">Kontak Wasit Tambahan (Opsional)</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_w_tambah['kontak_wasit'])) echo 'is-invalid'; ?>" id="kontak_wasit_form_w" name="kontak_wasit" placeholder="Nomor telepon alternatif" value="<?php echo htmlspecialchars($form_data_w_tambah['kontak_wasit'] ?? ''); ?>">
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-folder-open mr-1"></i> Berkas Pendukung Wasit</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ktp_path_form_w">Upload Scan KTP (Maks <?php echo MAX_FILE_SIZE_KTP_KK_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_tambah['ktp_path'])) echo 'is-invalid'; ?>" id="ktp_path_form_w" name="ktp_path" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="ktp_path_form_w">Pilih file KTP...</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kk_path_form_w">Upload Scan KK (Maks <?php echo MAX_FILE_SIZE_KTP_KK_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_tambah['kk_path'])) echo 'is-invalid'; ?>" id="kk_path_form_w" name="kk_path" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="kk_path_form_w">Pilih file KK...</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                             <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="path_file_lisensi_form_w">File Lisensi (Maks <?php echo MAX_FILE_SIZE_LISENSI_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_tambah['path_file_lisensi'])) echo 'is-invalid'; ?>" id="path_file_lisensi_form_w" name="path_file_lisensi" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="path_file_lisensi_form_w">Pilih file lisensi...</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="foto_wasit_form_w">Foto Wasit (Maks <?php echo MAX_FILE_SIZE_FOTO_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_tambah['foto_wasit'])) echo 'is-invalid'; ?>" id="foto_wasit_form_w" name="foto_wasit" accept=".jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="foto_wasit_form_w">Pilih foto wasit...</label>
                                        </div>
                                        <small class="form-text text-muted">Pas foto formal atau foto representatif.</small>
                                    </div>
                                </div>
                            </div>

                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_wasit" class="btn bg-danger text-white"><i class="fas fa-user-plus mr-1"></i> Daftarkan Wasit</button>
                            <a href="daftar_wasit.php<?php echo ($default_id_cabor_form_w) ? '?id_cabor=' . $default_id_cabor_form_w : ''; ?>" class="btn btn-secondary float-right">
                                <i class="fas fa-times mr-1"></i> Batal
                            </a>
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
    $('#id_cabor_wasit_form.select2bs4').select2({ // ID disesuaikan
      theme: 'bootstrap4',
      placeholder: '-- Pilih Cabang Olahraga --',
      allowClear: true
    });
  }

  // AJAX untuk cek NIK (sama seperti di tambah_atlet.php/tambah_pelatih.php)
  $('.nik-input-ajax').on('keyup change', function() {
    var nikValue = $(this).val();
    var displayTargetId = $(this).data('display-target');
    var displayElement = $('#' + displayTargetId);
    displayElement.html('<small class=\"form-text text-info\"><i class=\"fas fa-spinner fa-spin\"></i> Mengecek NIK...</small>');

    if (nikValue.length === 16) {
        $.ajax({
            url: '" . rtrim($app_base_path, '/') . "/ajax/cek_nama_nik.php',
            type: 'POST',
            data: { nik: nikValue },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.nama_lengkap) { 
                    displayElement.html('<small class=\"form-text text-success\"><i class=\"fas fa-check-circle\"></i> Pengguna ditemukan: <strong>' + response.nama_lengkap + '</strong></small>');
                } else if (response.status === 'not_found') {
                    displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-times-circle\"></i> ' + (response.message || 'NIK tidak terdaftar sebagai pengguna.') + '</small>');
                } else if (response.status === 'pending_approval') { 
                    displayElement.html('<small class=\"form-text text-warning\"><i class=\"fas fa-user-clock\"></i> ' + (response.message || 'Akun pengguna ini menunggu persetujuan.') + (response.nama_lengkap ? ' Nama: <strong>' + response.nama_lengkap + '</strong>' : '') + '</small>');
                } else { 
                    displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> ' + (response.message || 'Tidak dapat memverifikasi NIK saat ini.') + '</small>');
                }
            },
            error: function() {
                displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Error saat menghubungi server untuk cek NIK.</small>');
            }
        });
    } else if (nikValue.length > 0) {
        displayElement.html('<small class=\"form-text text-muted\">NIK harus 16 digit.</small>');
    } else {
        displayElement.html('');
    }
  });
  if ($('.nik-input-ajax').val().length === 16) {
      $('.nik-input-ajax').trigger('change');
  }

  // Validasi Frontend Sederhana sebelum submit
  $('#formTambahWasit').submit(function(e) {
    let isValidFormW = true;
    let focusFieldW = null;
    $('.is-invalid').removeClass('is-invalid'); 

    let nikW = $('#nik_wasit_form').val().trim();
    if (nikW.length !== 16 || !/^\\d{16}$/.test(nikW)) {
        if(!focusFieldW) focusFieldW = $('#nik_wasit_form');
        $('#nik_wasit_form').addClass('is-invalid');
        isValidFormW = false;
    }
    
    let idCaborWVal = $('#id_cabor_wasit_form').val();
    if (" . json_encode($user_role_utama !== 'pengurus_cabor') . " && (idCaborWVal === '' || idCaborWVal === null)) {
        if(!focusFieldW) focusFieldW = $('#id_cabor_wasit_form');
         $('#id_cabor_wasit_form').next('.select2-container').find('.select2-selection--single').addClass('is-invalid');
        isValidFormW = false;
    }
    
    // Validasi ukuran file (hanya jika file dipilih)
    let ktpWFile = $('#ktp_path_form_w')[0].files[0];
    if(ktpWFile && ktpWFile.size > " . MAX_FILE_SIZE_KTP_KK_WASIT_BYTES . "){ 
       if(!focusFieldW) focusFieldW = $('#ktp_path_form_w');
       $('#ktp_path_form_w').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       isValidFormW = false;
       alert('Ukuran file KTP tidak boleh lebih dari " . MAX_FILE_SIZE_KTP_KK_WASIT_MB . "MB.');
    }
    let kkWFile = $('#kk_path_form_w')[0].files[0];
    if(kkWFile && kkWFile.size > " . MAX_FILE_SIZE_KTP_KK_WASIT_BYTES . "){ 
       if(!focusFieldW) focusFieldW = $('#kk_path_form_w');
       $('#kk_path_form_w').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       isValidFormW = false;
       alert('Ukuran file Kartu Keluarga tidak boleh lebih dari " . MAX_FILE_SIZE_KTP_KK_WASIT_MB . "MB.');
    }
    let lisensiWFile = $('#path_file_lisensi_form_w')[0].files[0];
    if(lisensiWFile && lisensiWFile.size > " . MAX_FILE_SIZE_LISENSI_WASIT_BYTES . "){ 
       if(!focusFieldW) focusFieldW = $('#path_file_lisensi_form_w');
       $('#path_file_lisensi_form_w').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       isValidFormW = false;
       alert('Ukuran file Lisensi tidak boleh lebih dari " . MAX_FILE_SIZE_LISENSI_WASIT_MB . "MB.');
    }
    let fotoWFile = $('#foto_wasit_form_w')[0].files[0];
    if(fotoWFile && fotoWFile.size > " . MAX_FILE_SIZE_FOTO_WASIT_BYTES . "){ 
       if(!focusFieldW) focusFieldW = $('#foto_wasit_form_w');
       $('#foto_wasit_form_w').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       isValidFormW = false;
       alert('Ukuran file Foto Wasit tidak boleh lebih dari " . MAX_FILE_SIZE_FOTO_WASIT_MB . "MB.');
    }
    
    if (!isValidFormW) {
        e.preventDefault(); 
        if(focusFieldW) {
            focusFieldW.focus();
             if (!focusFieldW.is(':visible') || focusFieldW.offset().top < $(window).scrollTop() || focusFieldW.offset().top + focusFieldW.outerHeight() > $(window).scrollTop() + $(window).height()) {
                $('html, body').animate({ scrollTop: focusFieldW.closest('.form-group').offset().top - 70 }, 500);
            }
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php'); 
?>