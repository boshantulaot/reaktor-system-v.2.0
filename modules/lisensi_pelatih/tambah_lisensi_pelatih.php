<?php
// File: modules/lisensi_pelatih/tambah_lisensi_pelatih.php

$page_title = "Tambah Lisensi Pelatih Baru";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    'assets/adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css', // Untuk Datepicker
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/moment/moment.min.js', // Untuk Datepicker
    'assets/adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js', // Untuk Datepicker
    // bsCustomFileInput diinisialisasi global di footer.php atau perlu di sini
];

require_once(__DIR__ . '/../../core/header.php');

// Definisi konstanta ukuran file (ambil dari daftar_lisensi_pelatih.php atau file konfigurasi)
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB')) { 
    define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB', 2); // Misal 2MB untuk sertifikat
}
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES')) {
    define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES', MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB * 1024 * 1024);
}

// 1. Pengecekan Sesi & Hak Akses
if (!isset($user_nik, $user_role_utama, $app_base_path, $pdo)) { /* Redirect atau error */ exit("Sesi tidak valid."); }

$can_add_for_others = in_array($user_role_utama, ['super_admin', 'admin_koni']);
$is_pengurus_cabor = ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama));
$is_pelatih_role = ($user_role_utama === 'pelatih');

if (!($can_add_for_others || $is_pengurus_cabor || $is_pelatih_role)) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk menambah data lisensi pelatih.";
    header("Location: daftar_lisensi_pelatih.php");
    exit();
}

// 2. Pengambilan Data untuk Dropdown (Cabor)
$cabor_options_lp_form = [];
try {
    $stmt_cabor_all = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
    $cabor_options_lp_form = $stmt_cabor_all->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error tambah_lisensi_pelatih.php - fetch cabor: " . $e->getMessage());
    // Tidak fatal, form tetap bisa tampil
}
$nama_cabor_pengurus_lp_form = '';
if($is_pengurus_cabor){
    foreach($cabor_options_lp_form as $c){ if($c['id_cabor'] == $id_cabor_pengurus_utama) {$nama_cabor_pengurus_lp_form = $c['nama_cabor']; break;}}
}


// 3. Ambil data dari GET request (jika ada default)
$default_nik_pelatih_from_get = isset($_GET['nik_pelatih_default']) && preg_match('/^\d{1,16}$/', $_GET['nik_pelatih_default']) ? $_GET['nik_pelatih_default'] : null;
$default_id_cabor_from_get = isset($_GET['id_cabor_default']) && filter_var($_GET['id_cabor_default'], FILTER_VALIDATE_INT) ? (int)$_GET['id_cabor_default'] : null;
$nama_pelatih_default_display = '';

if($is_pelatih_role && !$default_nik_pelatih_from_get) {
    $default_nik_pelatih_from_get = $user_nik; // Pelatih menambah untuk dirinya sendiri
}
if ($default_nik_pelatih_from_get) {
    try {
        $stmt_nama_def_p = $pdo->prepare("SELECT p.nama_lengkap FROM pengguna p JOIN pelatih plt ON p.nik = plt.nik WHERE p.nik = :nik_default");
        $stmt_nama_def_p->bindParam(':nik_default', $default_nik_pelatih_from_get);
        $stmt_nama_def_p->execute();
        $nama_pelatih_default_display = $stmt_nama_def_p->fetchColumn();
    } catch (PDOException $e) { /* Abaikan jika gagal */ }
}


// 4. Ambil data form & error dari session (jika ada redirect dari proses_tambah)
$form_data_lp = $_SESSION['form_data_lisensi_tambah'] ?? [];
$form_errors_lp = $_SESSION['errors_lisensi_tambah'] ?? [];
$form_error_fields_lp = $_SESSION['error_fields_lisensi_tambah'] ?? [];

unset($_SESSION['form_data_lisensi_tambah']);
unset($_SESSION['errors_lisensi_tambah']);
unset($_SESSION['error_fields_lisensi_tambah']);

// Menentukan nilai default akhir untuk form
$val_nik_pelatih_form = $form_data_lp['nik_pelatih'] ?? $default_nik_pelatih_from_get ?? '';
$val_id_cabor_form = $form_data_lp['id_cabor'] ?? ($is_pengurus_cabor ? $id_cabor_pengurus_utama : $default_id_cabor_from_get ?? '');

?>

<section class="content">
    <div class="container-fluid">
        <?php // Pesan global dihandle footer.php ?>

        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-purple">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formTambahLisensiPelatih" action="proses_tambah_lisensi_pelatih.php" method="post" enctype="multipart/form-data">
                        <div class="card-body">
                            <?php if (!empty($form_errors_lp)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Menyimpan Data!</h5>
                                    <ul>
                                        <?php foreach ($form_errors_lp as $error_item_lp): ?>
                                            <li><?php echo htmlspecialchars($error_item_lp); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-purple"><i class="fas fa-user-shield mr-1"></i> Identitas Pelatih & Cabor</h5>
                            
                            <div class="form-group">
                                <label for="nik_pelatih_form_tambah">Pelatih (NIK) <span class="text-danger">*</span></label>
                                <?php if ($is_pelatih_role): ?>
                                    <input type="hidden" name="nik_pelatih" value="<?php echo htmlspecialchars($user_nik); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_nama_lengkap . ' (' . $user_nik . ')'); ?>" readonly>
                                    <small class="form-text text-muted">Anda menambah lisensi untuk diri sendiri.</small>
                                <?php else: // Admin, Super Admin, Pengurus Cabor ?>
                                    <input type="text" class="form-control nik-input-ajax-pelatih <?php if(isset($form_error_fields_lp['nik_pelatih'])) echo 'is-invalid'; ?>" 
                                           id="nik_pelatih_form_tambah" name="nik_pelatih"
                                           placeholder="Ketik NIK Pelatih (16 digit) yang sudah terdaftar sebagai pelatih"
                                           value="<?php echo htmlspecialchars($val_nik_pelatih_form); ?>" maxlength="16" pattern="\d{16}" 
                                           data-display-target="nama_pelatih_display_form_tambah" required>
                                    <small id="nama_pelatih_display_form_tambah" class="form-text nik-nama-display"><?php if($nama_pelatih_default_display) echo "<i class='fas fa-check-circle text-success'></i> Pengguna ditemukan: <strong>".htmlspecialchars($nama_pelatih_default_display)."</strong>"; ?></small>
                                    <small class="form-text text-muted">Pastikan NIK adalah pengguna yang sudah terdaftar sebagai pelatih & akunnya aktif.</small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="id_cabor_lisensi_form_tambah">Cabang Olahraga Lisensi <span class="text-danger">*</span></label>
                                <?php if ($is_pengurus_cabor): ?>
                                    <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_pengurus_utama); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_cabor_pengurus_lp_form ?: 'Cabor Pengurus Tidak Valid'); ?>" readonly>
                                <?php else: // Admin, Super Admin, Pelatih ?>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_lp['id_cabor'])) echo 'is-invalid'; ?>" 
                                            id="id_cabor_lisensi_form_tambah" name="id_cabor" style="width: 100%;" required 
                                            data-placeholder="-- Pilih Cabang Olahraga untuk Lisensi Ini --">
                                        <option value=""></option>
                                        <?php foreach ($cabor_options_lp_form as $cabor_opt): ?>
                                            <option value="<?php echo $cabor_opt['id_cabor']; ?>" <?php echo ($val_id_cabor_form == $cabor_opt['id_cabor']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cabor_opt['nama_cabor']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($cabor_options_lp_form)): ?>
                                        <small class="form-text text-danger">Tidak ada data cabor aktif.</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-certificate mr-1"></i> Detail Lisensi/Sertifikat</h5>

                            <div class="form-group">
                                <label for="nama_lisensi_sertifikat_form_tambah">Nama Lisensi/Sertifikat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_lp['nama_lisensi_sertifikat'])) echo 'is-invalid'; ?>" 
                                       id="nama_lisensi_sertifikat_form_tambah" name="nama_lisensi_sertifikat" 
                                       placeholder="Contoh: Lisensi Pelatih C Nasional, Sertifikat Wasit Daerah" 
                                       value="<?php echo htmlspecialchars($form_data_lp['nama_lisensi_sertifikat'] ?? ''); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nomor_sertifikat_form_tambah">Nomor Sertifikat (Opsional)</label>
                                        <input type="text" class="form-control <?php if(isset($form_error_fields_lp['nomor_sertifikat'])) echo 'is-invalid'; ?>" 
                                               id="nomor_sertifikat_form_tambah" name="nomor_sertifikat" 
                                               placeholder="Nomor pada sertifikat" 
                                               value="<?php echo htmlspecialchars($form_data_lp['nomor_sertifikat'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lembaga_penerbit_form_tambah">Lembaga Penerbit (Opsional)</label>
                                        <input type="text" class="form-control <?php if(isset($form_error_fields_lp['lembaga_penerbit'])) echo 'is-invalid'; ?>" 
                                               id="lembaga_penerbit_form_tambah" name="lembaga_penerbit" 
                                               placeholder="Contoh: PSSI, KONI Provinsi, Federasi XYZ" 
                                               value="<?php echo htmlspecialchars($form_data_lp['lembaga_penerbit'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                             <div class="form-group">
                                <label for="tingkat_lisensi_form_tambah">Tingkat Lisensi (Opsional)</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_lp['tingkat_lisensi'])) echo 'is-invalid'; ?>" 
                                       id="tingkat_lisensi_form_tambah" name="tingkat_lisensi" 
                                       placeholder="Contoh: Dasar, Madya, Nasional, Level 1" 
                                       value="<?php echo htmlspecialchars($form_data_lp['tingkat_lisensi'] ?? ''); ?>">
                            </div>
                             <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_terbit_form_tambah">Tanggal Terbit (Opsional)</label>
                                        <div class="input-group date" id="datepickerTanggalTerbitLP" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input <?php if(isset($form_error_fields_lp['tanggal_terbit'])) echo 'is-invalid'; ?>" 
                                                   data-target="#datepickerTanggalTerbitLP" name="tanggal_terbit" placeholder="Pilih tanggal"
                                                   value="<?php echo htmlspecialchars($form_data_lp['tanggal_terbit'] ?? ''); ?>"/>
                                            <div class="input-group-append" data-target="#datepickerTanggalTerbitLP" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_kadaluarsa_form_tambah">Tanggal Kadaluarsa (Opsional)</label>
                                        <div class="input-group date" id="datepickerTanggalKadaluarsaLP" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input <?php if(isset($form_error_fields_lp['tanggal_kadaluarsa'])) echo 'is-invalid'; ?>" 
                                                   data-target="#datepickerTanggalKadaluarsaLP" name="tanggal_kadaluarsa" placeholder="Kosongkan jika tidak ada"
                                                   value="<?php echo htmlspecialchars($form_data_lp['tanggal_kadaluarsa'] ?? ''); ?>"/>
                                            <div class="input-group-append" data-target="#datepickerTanggalKadaluarsaLP" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="path_file_sertifikat_form_tambah">Upload File Sertifikat (PDF, JPG, PNG - Maks <?php echo MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB; ?>MB)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(isset($form_error_fields_lp['path_file_sertifikat'])) echo 'is-invalid'; ?>" 
                                           id="path_file_sertifikat_form_tambah" name="path_file_sertifikat" accept=".pdf,.jpg,.jpeg,.png">
                                    <label class="custom-file-label" for="path_file_sertifikat_form_tambah">Pilih file...</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="catatan_form_tambah">Catatan (Opsional)</label>
                                <textarea class="form-control <?php if(isset($form_error_fields_lp['catatan'])) echo 'is-invalid'; ?>" 
                                          id="catatan_form_tambah" name="catatan" rows="3" 
                                          placeholder="Catatan tambahan mengenai lisensi ini..."><?php echo htmlspecialchars($form_data_lp['catatan'] ?? ''); ?></textarea>
                            </div>
                            
                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_lisensi_pelatih" class="btn btn-purple"><i class="fas fa-save mr-1"></i> Simpan Lisensi</button>
                            <a href="daftar_lisensi_pelatih.php<?php echo ($val_id_cabor_form) ? '?id_cabor=' . $val_id_cabor_form : ''; ?>" class="btn btn-secondary float-right">
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

  $('.select2bs4').select2({ theme: 'bootstrap4', placeholder: $(this).data('placeholder'), allowClear: true });

  // Datepicker Tanggal Terbit
  $('#datepickerTanggalTerbitLP').datetimepicker({
      format: 'YYYY-MM-DD', // Format MySQL DATE
      useCurrent: false, // Jangan otomatis set tanggal hari ini
      icons: { time: 'far fa-clock' }
  });
  // Datepicker Tanggal Kadaluarsa
  $('#datepickerTanggalKadaluarsaLP').datetimepicker({
      format: 'YYYY-MM-DD',
      useCurrent: false,
      icons: { time: 'far fa-clock' }
  });

  // AJAX untuk cek NIK Pelatih (jika diinput oleh admin/pengcab)
  $('.nik-input-ajax-pelatih').on('keyup change', function() {
    var nikValue = $(this).val();
    var displayTargetId = $(this).data('display-target');
    var displayElement = $('#' + displayTargetId);
    displayElement.html('<small class=\"form-text text-info\"><i class=\"fas fa-spinner fa-spin\"></i> Mengecek NIK Pelatih...</small>');

    if (nikValue.length === 16) {
        $.ajax({
            url: '" . rtrim($app_base_path, '/') . "/ajax/cek_nama_nik.php', // Gunakan endpoint yang sama
            type: 'POST',
            data: { nik: nikValue, role_check: 'pelatih' }, // Tambah parameter role_check jika endpoint bisa handle
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.is_pelatih && response.nama_lengkap) { // Pastikan NIK adalah pelatih
                    displayElement.html('<small class=\"form-text text-success\"><i class=\"fas fa-check-circle\"></i> Pelatih ditemukan: <strong>' + response.nama_lengkap + '</strong></small>');
                } else if (response.status === 'success' && !response.is_pelatih && response.nama_lengkap) {
                     displayElement.html('<small class=\"form-text text-warning\"><i class=\"fas fa-exclamation-triangle\"></i> Pengguna ditemukan (<strong>' + response.nama_lengkap + '</strong>), tapi belum terdaftar sebagai pelatih.</small>');
                } else if (response.status === 'not_found') {
                    displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-times-circle\"></i> ' + (response.message || 'NIK tidak terdaftar sebagai pengguna.') + '</small>');
                } else if (response.status === 'pending_approval') { 
                    displayElement.html('<small class=\"form-text text-warning\"><i class=\"fas fa-user-clock\"></i> ' + (response.message || 'Akun pengguna ini menunggu persetujuan.') + (response.nama_lengkap ? ' Nama: <strong>' + response.nama_lengkap + '</strong>' : '') + '</small>');
                } else { 
                    displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> ' + (response.message || 'Tidak dapat memverifikasi NIK pelatih saat ini.') + '</small>');
                }
            },
            error: function() {
                displayElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Error saat menghubungi server.</small>');
            }
        });
    } else if (nikValue.length > 0) {
        displayElement.html('<small class=\"form-text text-muted\">NIK harus 16 digit.</small>');
    } else {
        displayElement.html('');
    }
  });
  // Trigger jika ada NIK default
  if ($('#nik_pelatih_form_tambah').val().length === 16 && " . json_encode(!$is_pelatih_role) . ") {
      $('#nik_pelatih_form_tambah').trigger('change');
  }


  // Validasi Frontend Sederhana
  $('#formTambahLisensiPelatih').submit(function(e) {
    let isValidForm = true;
    let focusField = null;
    $('.is-invalid').removeClass('is-invalid'); 

    // Validasi NIK Pelatih (jika bukan diinput oleh pelatih sendiri)
    if (" . json_encode(!$is_pelatih_role) . ") {
        let nikPelatih = $('#nik_pelatih_form_tambah').val().trim();
        if (nikPelatih.length !== 16 || !/^\\d{16}$/.test(nikPelatih)) {
            if(!focusField) focusField = $('#nik_pelatih_form_tambah');
            $('#nik_pelatih_form_tambah').addClass('is-invalid'); isValidForm = false;
        }
    }
    // Validasi Cabor (jika bukan diinput oleh pengurus cabor)
    if (" . json_encode(!$is_pengurus_cabor) . ") {
        let idCaborLisensi = $('#id_cabor_lisensi_form_tambah').val();
        if (idCaborLisensi === '' || idCaborLisensi === null) {
            if(!focusField) focusField = $('#id_cabor_lisensi_form_tambah');
            $('#id_cabor_lisensi_form_tambah').next('.select2-container').find('.select2-selection--single').addClass('is-invalid'); isValidForm = false;
        }
    }
    // Validasi Nama Lisensi
    if ($('#nama_lisensi_sertifikat_form_tambah').val().trim() === '') {
        if(!focusField) focusField = $('#nama_lisensi_sertifikat_form_tambah');
        $('#nama_lisensi_sertifikat_form_tambah').addClass('is-invalid'); isValidForm = false;
    }
    // Validasi ukuran file sertifikat (jika diisi)
    let fileSertifikat = $('#path_file_sertifikat_form_tambah')[0].files[0];
    if(fileSertifikat && fileSertifikat.size > " . MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES . "){ 
       if(!focusField) focusField = $('#path_file_sertifikat_form_tambah');
       $('#path_file_sertifikat_form_tambah').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#path_file_sertifikat_form_tambah').addClass('is-invalid');
       alert('Ukuran file Sertifikat tidak boleh lebih dari " . MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB . "MB.');
       isValidForm = false;
    }
    
    if (!isValidForm) {
        e.preventDefault(); 
        if(focusField) {
            focusField.focus();
            $('html, body').animate({ scrollTop: focusField.closest('.form-group').offset().top - 70 }, 500);
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>