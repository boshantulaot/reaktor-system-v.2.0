<?php
// File: modules/lisensi_pelatih/edit_lisensi_pelatih.php

$page_title = "Edit Data Lisensi Pelatih";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    'assets/adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/moment/moment.min.js',
    'assets/adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Definisi konstanta ukuran file
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB')) { define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB', 2); }
if (!defined('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES')) { define('MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES', MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB * 1024 * 1024); }

// 1. Pengecekan Sesi & ID Lisensi
if (!isset($user_nik, $user_role_utama, $app_base_path, $pdo)) { /* Redirect atau error */ exit("Sesi tidak valid."); }

if (!isset($_GET['id_lisensi']) || !filter_var($_GET['id_lisensi'], FILTER_VALIDATE_INT)) {
    $_SESSION['pesan_error_global'] = "ID Lisensi tidak valid.";
    header("Location: daftar_lisensi_pelatih.php");
    exit();
}
$id_lisensi_to_edit = (int)$_GET['id_lisensi'];

// 2. Pengambilan Data Lisensi dari DB
$lisensi = null;
$nama_pelatih_lisensi = '';
$nama_cabor_lisensi = '';
try {
    $stmt_get_lp = $pdo->prepare("SELECT lp.*, p.nama_lengkap AS nama_pelatih_current, co.nama_cabor AS nama_cabor_current
                                 FROM lisensi_pelatih lp
                                 JOIN pengguna p ON lp.nik_pelatih = p.nik
                                 JOIN cabang_olahraga co ON lp.id_cabor = co.id_cabor
                                 WHERE lp.id_lisensi_pelatih = :id_lisensi");
    $stmt_get_lp->bindParam(':id_lisensi', $id_lisensi_to_edit, PDO::PARAM_INT);
    $stmt_get_lp->execute();
    $lisensi = $stmt_get_lp->fetch(PDO::FETCH_ASSOC);

    if (!$lisensi) {
        $_SESSION['pesan_error_global'] = "Data lisensi tidak ditemukan.";
        header("Location: daftar_lisensi_pelatih.php");
        exit();
    }
    $nama_pelatih_lisensi = $lisensi['nama_pelatih_current'];
    $nama_cabor_lisensi = $lisensi['nama_cabor_current'];

} catch (PDOException $e) {
    error_log("Error edit_lisensi_pelatih.php - fetch lisensi: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Gagal memuat data lisensi.";
    header("Location: daftar_lisensi_pelatih.php");
    exit();
}

// 3. Hak Akses Edit
$can_edit_this = false;
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $can_edit_this = true;
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama) && $lisensi['id_cabor'] == $id_cabor_pengurus_utama) {
    // Pengurus cabor bisa edit jika status belum final atau ditolak/revisi olehnya
    if (in_array($lisensi['status_approval'], ['pending', 'revisi', 'ditolak_pengcab'])) {
        $can_edit_this = true;
    }
} elseif ($user_role_utama === 'pelatih' && $lisensi['nik_pelatih'] == $user_nik) {
    // Pelatih bisa edit jika statusnya memungkinkan (belum final atau ditolak/revisi)
    if (in_array($lisensi['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) {
        $can_edit_this = true;
    }
}

if (!$can_edit_this) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengedit data lisensi ini.";
    header("Location: daftar_lisensi_pelatih.php" . ($lisensi['id_cabor'] ? '?id_cabor='.$lisensi['id_cabor'] : ''));
    exit();
}

// Ambil data cabor untuk dropdown jika admin (jika cabor mau dibuat bisa diedit, biasanya tidak)
// $cabor_options_lp_form = []; (seperti di tambah_lisensi_pelatih.php jika cabor bisa diedit)


// 4. Ambil data form & error dari session (jika ada redirect dari proses_edit)
$form_data_lp = $_SESSION['form_data_lisensi_edit_' . $id_lisensi_to_edit] ?? $lisensi; // Prioritaskan data session, fallback ke data DB
$form_errors_lp = $_SESSION['errors_lisensi_edit_' . $id_lisensi_to_edit] ?? [];
$form_error_fields_lp = $_SESSION['error_fields_lisensi_edit_' . $id_lisensi_to_edit] ?? [];

unset($_SESSION['form_data_lisensi_edit_' . $id_lisensi_to_edit]);
unset($_SESSION['errors_lisensi_edit_' . $id_lisensi_to_edit]);
unset($_SESSION['error_fields_lisensi_edit_' . $id_lisensi_to_edit]);

?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-purple">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formEditLisensiPelatih" action="proses_edit_lisensi_pelatih.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_lisensi_pelatih" value="<?php echo $id_lisensi_to_edit; ?>">
                        <input type="hidden" name="current_path_file_sertifikat" value="<?php echo htmlspecialchars($lisensi['path_file_sertifikat'] ?? ''); ?>">

                        <div class="card-body">
                            <?php if (!empty($form_errors_lp)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Menyimpan Perubahan!</h5>
                                    <ul> <?php foreach ($form_errors_lp as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-purple"><i class="fas fa-user-shield mr-1"></i> Identitas Pelatih & Cabor (Read-only)</h5>
                            
                            <div class="form-group">
                                <label>Pelatih (NIK)</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_pelatih_lisensi . ' (' . $lisensi['nik_pelatih'] . ')'); ?>" readonly>
                                <input type="hidden" name="nik_pelatih" value="<?php echo htmlspecialchars($lisensi['nik_pelatih']); ?>">
                            </div>

                            <div class="form-group">
                                <label>Cabang Olahraga Lisensi</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_cabor_lisensi); ?>" readonly>
                                <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($lisensi['id_cabor']); ?>">
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-certificate mr-1"></i> Detail Lisensi/Sertifikat</h5>

                            <div class="form-group">
                                <label for="nama_lisensi_sertifikat_form_edit">Nama Lisensi/Sertifikat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_lp['nama_lisensi_sertifikat'])) echo 'is-invalid'; ?>" 
                                       id="nama_lisensi_sertifikat_form_edit" name="nama_lisensi_sertifikat" 
                                       placeholder="Nama lisensi atau sertifikat" 
                                       value="<?php echo htmlspecialchars($form_data_lp['nama_lisensi_sertifikat'] ?? ''); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nomor_sertifikat_form_edit">Nomor Sertifikat (Opsional)</label>
                                        <input type="text" class="form-control <?php if(isset($form_error_fields_lp['nomor_sertifikat'])) echo 'is-invalid'; ?>" 
                                               id="nomor_sertifikat_form_edit" name="nomor_sertifikat" 
                                               value="<?php echo htmlspecialchars($form_data_lp['nomor_sertifikat'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lembaga_penerbit_form_edit">Lembaga Penerbit (Opsional)</label>
                                        <input type="text" class="form-control <?php if(isset($form_error_fields_lp['lembaga_penerbit'])) echo 'is-invalid'; ?>" 
                                               id="lembaga_penerbit_form_edit" name="lembaga_penerbit" 
                                               value="<?php echo htmlspecialchars($form_data_lp['lembaga_penerbit'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                             <div class="form-group">
                                <label for="tingkat_lisensi_form_edit">Tingkat Lisensi (Opsional)</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_lp['tingkat_lisensi'])) echo 'is-invalid'; ?>" 
                                       id="tingkat_lisensi_form_edit" name="tingkat_lisensi" 
                                       value="<?php echo htmlspecialchars($form_data_lp['tingkat_lisensi'] ?? ''); ?>">
                            </div>
                             <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_terbit_form_edit">Tanggal Terbit (Opsional)</label>
                                        <div class="input-group date" id="datepickerTanggalTerbitLPEdit" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input <?php if(isset($form_error_fields_lp['tanggal_terbit'])) echo 'is-invalid'; ?>" 
                                                   data-target="#datepickerTanggalTerbitLPEdit" name="tanggal_terbit"
                                                   value="<?php echo htmlspecialchars($form_data_lp['tanggal_terbit'] ? date('Y-m-d', strtotime($form_data_lp['tanggal_terbit'])) : ''); ?>"/>
                                            <div class="input-group-append" data-target="#datepickerTanggalTerbitLPEdit" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_kadaluarsa_form_edit">Tanggal Kadaluarsa (Opsional)</label>
                                        <div class="input-group date" id="datepickerTanggalKadaluarsaLPEdit" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input <?php if(isset($form_error_fields_lp['tanggal_kadaluarsa'])) echo 'is-invalid'; ?>" 
                                                   data-target="#datepickerTanggalKadaluarsaLPEdit" name="tanggal_kadaluarsa"
                                                   value="<?php echo htmlspecialchars($form_data_lp['tanggal_kadaluarsa'] ? date('Y-m-d', strtotime($form_data_lp['tanggal_kadaluarsa'])) : ''); ?>"/>
                                            <div class="input-group-append" data-target="#datepickerTanggalKadaluarsaLPEdit" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="path_file_sertifikat_form_edit">Ganti File Sertifikat (PDF, JPG, PNG - Maks <?php echo MAX_FILE_SIZE_LISENSI_SERTIFIKAT_MB; ?>MB)</label>
                                <?php if (!empty($lisensi['path_file_sertifikat']) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . ltrim($lisensi['path_file_sertifikat'], '/'))): ?>
                                    <p class="mb-1">File saat ini: 
                                        <a href="<?php echo $app_base_path . '/' . ltrim($lisensi['path_file_sertifikat'], '/'); ?>" target="_blank">
                                            <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars(basename($lisensi['path_file_sertifikat'])); ?>
                                        </a>
                                    </p>
                                <?php elseif (!empty($lisensi['path_file_sertifikat'])): ?>
                                    <p class="mb-1 text-danger">File saat ini (<?php echo htmlspecialchars(basename($lisensi['path_file_sertifikat'])); ?>) tidak ditemukan.</p>
                                <?php endif; ?>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(isset($form_error_fields_lp['path_file_sertifikat'])) echo 'is-invalid'; ?>" 
                                           id="path_file_sertifikat_form_edit" name="path_file_sertifikat" accept=".pdf,.jpg,.jpeg,.png">
                                    <label class="custom-file-label" for="path_file_sertifikat_form_edit">Pilih file baru jika ingin ganti...</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="catatan_form_edit">Catatan (Opsional)</label>
                                <textarea class="form-control <?php if(isset($form_error_fields_lp['catatan'])) echo 'is-invalid'; ?>" 
                                          id="catatan_form_edit" name="catatan" rows="3"><?php echo htmlspecialchars($form_data_lp['catatan'] ?? ''); ?></textarea>
                            </div>
                            
                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_lisensi_pelatih" class="btn btn-purple"><i class="fas fa-save mr-1"></i> Update Lisensi</button>
                            <a href="daftar_lisensi_pelatih.php<?php echo ($lisensi['id_cabor']) ? '?id_cabor=' . $lisensi['id_cabor'] : ''; ?>" class="btn btn-secondary float-right">
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
  // Untuk Select2, di halaman edit ini tidak ada Select2 yang perlu diinisialisasi ulang jika NIK dan Cabor readonly.
  // Jika ada field Select2 lain, inisialisasikan di sini.
  // $('.select2bs4-edit').select2({ theme: 'bootstrap4', ... });


  $('#datepickerTanggalTerbitLPEdit').datetimepicker({ format: 'YYYY-MM-DD', useCurrent: false, icons: { time: 'far fa-clock' } });
  $('#datepickerTanggalKadaluarsaLPEdit').datetimepicker({ format: 'YYYY-MM-DD', useCurrent: false, icons: { time: 'far fa-clock' } });

  // Validasi Frontend Sederhana
  $('#formEditLisensiPelatih').submit(function(e) {
    let isValidForm = true;
    let focusField = null;
    $('.is-invalid').removeClass('is-invalid'); 

    if ($('#nama_lisensi_sertifikat_form_edit').val().trim() === '') {
        if(!focusField) focusField = $('#nama_lisensi_sertifikat_form_edit');
        $('#nama_lisensi_sertifikat_form_edit').addClass('is-invalid'); isValidForm = false;
    }
    
    let fileSertifikat = $('#path_file_sertifikat_form_edit')[0].files[0];
    if(fileSertifikat && fileSertifikat.size > " . MAX_FILE_SIZE_LISENSI_SERTIFIKAT_BYTES . "){ 
       if(!focusField) focusField = $('#path_file_sertifikat_form_edit');
       $('#path_file_sertifikat_form_edit').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#path_file_sertifikat_form_edit').addClass('is-invalid');
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