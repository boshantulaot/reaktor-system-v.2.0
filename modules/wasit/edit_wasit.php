<?php
// File: reaktorsystem/modules/wasit/edit_wasit.php

$page_title = "Edit Data Wasit";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

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
    if (!headers_sent()) { header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php"); } 
    else { echo "<div class='alert alert-danger text-center m-3'>Error kritis: Sesi tidak valid. Harap <a href='" . rtrim($app_base_path, '/') . "/auth/login.php'>login ulang</a>.</div>"; }
    exit();
}

if (!in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: daftar_wasit.php");
    exit();
}

$id_wasit_to_edit = null;
$wasit_data_edit = null; 

if (isset($_GET['id_wasit']) && filter_var($_GET['id_wasit'], FILTER_VALIDATE_INT) && (int)$_GET['id_wasit'] > 0) {
    $id_wasit_to_edit = (int)$_GET['id_wasit'];
    try {
        $sql_edit_w = "SELECT w.*, p.nama_lengkap, p.email 
                       FROM wasit w 
                       JOIN pengguna p ON w.nik = p.nik 
                       WHERE w.id_wasit = :id_wasit";
        $stmt_edit_w = $pdo->prepare($sql_edit_w);
        $stmt_edit_w->bindParam(':id_wasit', $id_wasit_to_edit, PDO::PARAM_INT);
        $stmt_edit_w->execute();
        $wasit_data_edit = $stmt_edit_w->fetch(PDO::FETCH_ASSOC);

        if (!$wasit_data_edit) {
            $_SESSION['pesan_error_global'] = "Data Wasit tidak ditemukan.";
            header("Location: daftar_wasit.php");
            exit();
        }
        // Pembatasan akses untuk pengurus cabor
        if ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) != $wasit_data_edit['id_cabor']) {
            $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengedit wasit dari cabang olahraga lain.";
            header("Location: daftar_wasit.php" . ($id_cabor_pengurus_utama ? "?id_cabor=" . $id_cabor_pengurus_utama : ""));
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error edit_wasit.php - fetch wasit data: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data wasit.";
        header("Location: daftar_wasit.php");
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "ID Wasit tidak valid atau tidak disediakan untuk diedit.";
    header("Location: daftar_wasit.php");
    exit();
}

$cabor_options_edit_wasit_form = [];
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_cabor_ew = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
        $cabor_options_edit_wasit_form = $stmt_cabor_ew->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* Biarkan kosong jika gagal, error akan ditangani di form */ }
}

// Mengambil data dari session jika ada redirect karena error validasi
$form_data_w_edit = $_SESSION['form_data_wasit_edit'] ?? $wasit_data_edit; // Prioritaskan session
$form_errors_w_edit = $_SESSION['errors_wasit_edit'] ?? [];
$form_error_fields_w_edit = $_SESSION['error_fields_wasit_edit'] ?? [];

unset($_SESSION['form_data_wasit_edit'], $_SESSION['errors_wasit_edit'], $_SESSION['error_fields_wasit_edit']);

?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data Wasit: <?php echo htmlspecialchars($wasit_data_edit['nama_lengkap']); ?> <small>(NIK: <?php echo htmlspecialchars($wasit_data_edit['nik']); ?>)</small></h3>
                    </div>
                    <form id="formEditWasit" action="proses_edit_wasit.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_wasit" value="<?php echo htmlspecialchars($id_wasit_to_edit); ?>">
                        <input type="hidden" name="nik_wasit_original" value="<?php echo htmlspecialchars($wasit_data_edit['nik']); ?>">
                        
                        <?php // Input hidden untuk path file lama ?>
                        <input type="hidden" name="ktp_path_lama" value="<?php echo htmlspecialchars($wasit_data_edit['ktp_path'] ?? ''); ?>">
                        <input type="hidden" name="kk_path_lama" value="<?php echo htmlspecialchars($wasit_data_edit['kk_path'] ?? ''); ?>">
                        <input type="hidden" name="path_file_lisensi_lama" value="<?php echo htmlspecialchars($wasit_data_edit['path_file_lisensi'] ?? ''); ?>">
                        <input type="hidden" name="foto_wasit_lama" value="<?php echo htmlspecialchars($wasit_data_edit['foto_wasit'] ?? ''); ?>">

                        <div class="card-body">
                            <?php if (!empty($form_errors_w_edit)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Memperbarui Data Wasit!</h5>
                                    <ul>
                                        <?php foreach ($form_errors_w_edit as $error_item_w): ?>
                                            <li><?php echo htmlspecialchars($error_item_w); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-danger"><i class="fas fa-user-tag mr-1"></i> Informasi Pribadi Wasit</h5>
                            <div class="callout callout-info">
                                <p class="mb-1"><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($wasit_data_edit['nama_lengkap']); ?></p>
                                <p class="mb-1"><strong>NIK:</strong> <?php echo htmlspecialchars($wasit_data_edit['nik']); ?> (Tidak dapat diubah)</p>
                                <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($wasit_data_edit['email'] ?? '-'); ?></p>
                                <small>Untuk mengubah data di atas, silakan edit melalui Manajemen Pengguna.</small>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-flag mr-1"></i> Spesialisasi Cabang Olahraga</h5>
                            <div class="form-group">
                                <label for="id_cabor_wasit_edit_form">Cabang Olahraga <span class="text-danger">*</span></label>
                                <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_w_edit['id_cabor'])) echo 'is-invalid'; ?>" id="id_cabor_wasit_edit_form" name="id_cabor" required data-placeholder="-- Pilih Cabang Olahraga --">
                                        <option value=""></option>
                                        <?php foreach ($cabor_options_edit_wasit_form as $cabor_opt_w): ?>
                                            <option value="<?php echo $cabor_opt_w['id_cabor']; ?>" <?php echo ($form_data_w_edit['id_cabor'] == $cabor_opt_w['id_cabor']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cabor_opt_w['nama_cabor']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: // Pengurus Cabor ?>
                                    <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($wasit_data_edit['id_cabor']); ?>">
                                    <?php 
                                        $nama_cabor_current_wasit = '';
                                        try {
                                            $stmt_nama_c_w_cur = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id");
                                            $stmt_nama_c_w_cur->execute([':id' => $wasit_data_edit['id_cabor']]);
                                            $nama_cabor_current_wasit = $stmt_nama_c_w_cur->fetchColumn();
                                        } catch (PDOException $e) {}
                                    ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_cabor_current_wasit ?: 'N/A'); ?>" readonly>
                                <?php endif; ?>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-certificate mr-1"></i> Informasi Kewasitan & Kontak</h5>
                             <div class="form-group">
                                <label for="nomor_lisensi_edit_form">Nomor Lisensi</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_w_edit['nomor_lisensi'])) echo 'is-invalid'; ?>" id="nomor_lisensi_edit_form" name="nomor_lisensi" placeholder="Contoh: LIS-WAS-001" value="<?php echo htmlspecialchars($form_data_w_edit['nomor_lisensi'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="kontak_wasit_edit_form">Kontak Wasit Tambahan (Opsional)</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_w_edit['kontak_wasit'])) echo 'is-invalid'; ?>" id="kontak_wasit_edit_form" name="kontak_wasit" placeholder="Nomor telepon alternatif" value="<?php echo htmlspecialchars($form_data_w_edit['kontak_wasit'] ?? ''); ?>">
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-folder-open mr-1"></i> Berkas Pendukung (Upload baru jika ingin mengganti)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ktp_path_edit_form">Scan KTP Baru (Maks <?php echo MAX_FILE_SIZE_KTP_KK_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_edit['ktp_path'])) echo 'is-invalid'; ?>" id="ktp_path_edit_form" name="ktp_path" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="ktp_path_edit_form">Pilih file KTP baru...</label>
                                        </div>
                                        <?php if(!empty($wasit_data_edit['ktp_path'])): ?> <small class="form-text text-muted">File saat ini: <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($wasit_data_edit['ktp_path'],'/')); ?>" target="_blank"><?php echo htmlspecialchars(basename($wasit_data_edit['ktp_path'])); ?></a></small> <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kk_path_edit_form">Scan KK Baru (Maks <?php echo MAX_FILE_SIZE_KTP_KK_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_edit['kk_path'])) echo 'is-invalid'; ?>" id="kk_path_edit_form" name="kk_path" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="kk_path_edit_form">Pilih file KK baru...</label>
                                        </div>
                                        <?php if(!empty($wasit_data_edit['kk_path'])): ?> <small class="form-text text-muted">File saat ini: <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($wasit_data_edit['kk_path'],'/')); ?>" target="_blank"><?php echo htmlspecialchars(basename($wasit_data_edit['kk_path'])); ?></a></small> <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                             <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="path_file_lisensi_edit_form">File Lisensi Baru (Maks <?php echo MAX_FILE_SIZE_LISENSI_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_edit['path_file_lisensi'])) echo 'is-invalid'; ?>" id="path_file_lisensi_edit_form" name="path_file_lisensi" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="path_file_lisensi_edit_form">Pilih file lisensi baru...</label>
                                        </div>
                                        <?php if(!empty($wasit_data_edit['path_file_lisensi'])): ?> <small class="form-text text-muted">File saat ini: <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($wasit_data_edit['path_file_lisensi'],'/')); ?>" target="_blank"><?php echo htmlspecialchars(basename($wasit_data_edit['path_file_lisensi'])); ?></a></small> <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="foto_wasit_edit_form">Foto Wasit Baru (Maks <?php echo MAX_FILE_SIZE_FOTO_WASIT_MB; ?>MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields_w_edit['foto_wasit'])) echo 'is-invalid'; ?>" id="foto_wasit_edit_form" name="foto_wasit" accept=".jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="foto_wasit_edit_form">Pilih foto wasit baru...</label>
                                        </div>
                                        <?php if(!empty($wasit_data_edit['foto_wasit'])): ?> 
                                            <small class="form-text text-muted">Foto saat ini: 
                                                <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($wasit_data_edit['foto_wasit'],'/')); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($wasit_data_edit['foto_wasit'],'/')); ?>" alt="Foto Lama" style="max-height: 30px; border-radius: 3px;" class="ml-1">
                                                </a>
                                            </small> 
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                <hr>
                                <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-user-shield mr-1"></i> Administrasi & Approval</h5>
                                <div class="form-group">
                                    <label for="status_approval_wasit_edit_form">Status Approval</label>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_w_edit['status_approval'])) echo 'is-invalid'; ?>" id="status_approval_wasit_edit_form" name="status_approval" data-placeholder="-- Pilih Status --">
                                        <option value="pending" <?php if (($form_data_w_edit['status_approval'] ?? '') == 'pending') echo 'selected'; ?>>Pending</option>
                                        <option value="disetujui" <?php if (($form_data_w_edit['status_approval'] ?? '') == 'disetujui') echo 'selected'; ?>>Disetujui</option>
                                        <option value="ditolak" <?php if (($form_data_w_edit['status_approval'] ?? '') == 'ditolak') echo 'selected'; ?>>Ditolak</option>
                                        <option value="revisi" <?php if (($form_data_w_edit['status_approval'] ?? '') == 'revisi') echo 'selected'; ?>>Perlu Revisi</option>
                                    </select>
                                </div>
                                <div class="form-group alasan-toggle-group-wasit" style="<?php echo (!in_array($form_data_w_edit['status_approval'] ?? '', ['ditolak', 'revisi'])) ? 'display:none;' : ''; ?>">
                                    <label for="alasan_penolakan_wasit_edit_form">Alasan Penolakan/Revisi</label>
                                    <textarea class="form-control <?php if(isset($form_error_fields_w_edit['alasan_penolakan'])) echo 'is-invalid'; ?>" id="alasan_penolakan_wasit_edit_form" name="alasan_penolakan" rows="2" placeholder="Isi jika status Ditolak atau Perlu Revisi"><?php echo htmlspecialchars($form_data_w_edit['alasan_penolakan'] ?? ''); ?></textarea>
                                </div>
                            <?php else: // Pengurus Cabor tidak bisa mengubah status approval secara langsung dari form edit ?>
                                <input type="hidden" name="status_approval" value="<?php echo htmlspecialchars($wasit_data_edit['status_approval'] ?? 'pending'); ?>">
                                <input type="hidden" name="alasan_penolakan" value="<?php echo htmlspecialchars($wasit_data_edit['alasan_penolakan'] ?? ''); ?>">
                                <p class="text-info mt-3"><i class="fas fa-info-circle"></i> Status approval saat ini: <strong><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$wasit_data_edit['status_approval'] ?? 'Pending'))); ?></strong>. Perubahan data akan memerlukan approval ulang dari Admin KONI jika status sebelumnya adalah 'Disetujui'.</p>
                            <?php endif; ?>

                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_wasit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                            <a href="daftar_wasit.php<?php echo $wasit_data_edit['id_cabor'] ? '?id_cabor=' . $wasit_data_edit['id_cabor'] : ''; ?>" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a> 
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
  $('.select2bs4').select2({
    theme: 'bootstrap4',
    allowClear: true
    // Placeholder diatur via data-placeholder di HTML
  });

  var statusSelectWasitEdit = $('#status_approval_wasit_edit_form');
  var alasanGroupWasitEdit = $('.alasan-toggle-group-wasit');

  function toggleAlasanWasitEdit() {
    if (statusSelectWasitEdit.length && alasanGroupWasitEdit.length) {
        var selectedStatus = statusSelectWasitEdit.val();
        if (selectedStatus === 'ditolak' || selectedStatus === 'revisi') {
            alasanGroupWasitEdit.slideDown();
            $('#alasan_penolakan_wasit_edit_form').prop('required', true);
        } else {
            alasanGroupWasitEdit.slideUp();
            $('#alasan_penolakan_wasit_edit_form').prop('required', false);
        }
    }
  }

  if (statusSelectWasitEdit.length) {
    statusSelectWasitEdit.on('change', toggleAlasanWasitEdit);
    toggleAlasanWasitEdit(); // Panggil saat load
  }
  
  // Validasi Frontend Sederhana sebelum submit
  $('#formEditWasit').submit(function(e) {
    let isValidForm = true;
    let focusField = null;
    $('.is-invalid').removeClass('is-invalid'); 

    // Admin bisa ganti cabor, jadi validasi jika role-nya admin
    if (" . json_encode(in_array($user_role_utama, ['super_admin', 'admin_koni'])) . ") {
        let idCaborWasitVal = $('#id_cabor_wasit_edit_form').val();
        if (idCaborWasitVal === '' || idCaborWasitVal === null) {
            if(!focusField) focusField = $('#id_cabor_wasit_edit_form');
             $('#id_cabor_wasit_edit_form').next('.select2-container').find('.select2-selection--single').addClass('is-invalid');
            isValidForm = false;
        }
    }
    
    // Validasi ukuran file (hanya jika file baru dipilih)
    let ktpWFile = $('#ktp_path_edit_form')[0].files[0];
    if(ktpWFile && ktpWFile.size > " . MAX_FILE_SIZE_KTP_KK_WASIT_BYTES . "){ 
       if(!focusField) focusField = $('#ktp_path_edit_form');
       $('#ktp_path_edit_form').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#ktp_path_edit_form').addClass('is-invalid');
       alert('Ukuran file KTP tidak boleh lebih dari " . MAX_FILE_SIZE_KTP_KK_WASIT_MB . "MB.');
       isValidForm = false;
    }
    let kkWFile = $('#kk_path_edit_form')[0].files[0];
    if(kkWFile && kkWFile.size > " . MAX_FILE_SIZE_KTP_KK_WASIT_BYTES . "){ 
       if(!focusField) focusField = $('#kk_path_edit_form');
       $('#kk_path_edit_form').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#kk_path_edit_form').addClass('is-invalid');
       alert('Ukuran file Kartu Keluarga tidak boleh lebih dari " . MAX_FILE_SIZE_KTP_KK_WASIT_MB . "MB.');
       isValidForm = false;
    }
    let lisensiWFile = $('#path_file_lisensi_edit_form')[0].files[0];
    if(lisensiWFile && lisensiWFile.size > " . MAX_FILE_SIZE_LISENSI_WASIT_BYTES . "){ 
       if(!focusField) focusField = $('#path_file_lisensi_edit_form');
       $('#path_file_lisensi_edit_form').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#path_file_lisensi_edit_form').addClass('is-invalid');
       alert('Ukuran file Lisensi tidak boleh lebih dari " . MAX_FILE_SIZE_LISENSI_WASIT_MB . "MB.');
       isValidForm = false;
    }
    let fotoWFile = $('#foto_wasit_edit_form')[0].files[0];
    if(fotoWFile && fotoWFile.size > " . MAX_FILE_SIZE_FOTO_WASIT_BYTES . "){ 
       if(!focusField) focusField = $('#foto_wasit_edit_form');
       $('#foto_wasit_edit_form').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#foto_wasit_edit_form').addClass('is-invalid');
       alert('Ukuran file Foto Wasit tidak boleh lebih dari " . MAX_FILE_SIZE_FOTO_WASIT_MB . "MB.');
       isValidForm = false;
    }

    if (statusSelectWasitEdit.length) {
        var selectedStatusVal = statusSelectWasitEdit.val();
        if ((selectedStatusVal === 'ditolak' || selectedStatusVal === 'revisi') && $('#alasan_penolakan_wasit_edit_form').val().trim() === '') {
            if(!focusField) focusField = $('#alasan_penolakan_wasit_edit_form');
            $('#alasan_penolakan_wasit_edit_form').addClass('is-invalid');
            alert('Alasan penolakan/revisi wajib diisi jika status Ditolak atau Perlu Revisi.');
            isValidForm = false;
        }
    }
    
    if (!isValidForm) {
        e.preventDefault(); 
        if(focusField) {
            focusField.focus();
             if (!focusField.is(':visible') || focusField.offset().top < $(window).scrollTop() || focusField.offset().top + focusField.outerHeight() > $(window).scrollTop() + $(window).height()) {
                $('html, body').animate({ scrollTop: focusField.closest('.form-group').offset().top - 70 }, 500);
            }
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php'); 
?>