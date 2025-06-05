<?php
// File: reaktorsystem/modules/klub/tambah_klub.php

$page_title = "Tambah Klub Olahraga Baru";

// ... (Blok $additional_css dan $additional_js tetap sama) ...
$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// ... (Blok PHP untuk validasi sesi, peran, pengambilan data cabor, dan penanganan form error TETAP SAMA PERSIS seperti yang Anda berikan) ...
if (!isset($user_nik) || !isset($user_role_utama)) { 
    $_SESSION['pesan_error_global'] = "Sesi Anda telah berakhir, silakan login kembali.";
    header("Location: " . rtrim($app_base_path, '/') . "auth/login.php");
    exit();
}
if (!in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki hak akses untuk menambah klub.";
    header("Location: daftar_klub.php");
    exit();
}
if ($user_role_utama === 'pengurus_cabor' && empty($id_cabor_pengurus_utama)) {
    $_SESSION['pesan_error_global'] = "Informasi cabang olahraga Anda tidak lengkap. Tidak dapat menambah klub.";
    header("Location: " . rtrim($app_base_path, '/') . "dashboard.php"); 
    exit();
}
$cabang_olahraga_options_form = [];
$nama_cabor_pengurus_form = '';
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_cabor = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC");
        $cabang_olahraga_options_form = $stmt_cabor->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Error tambah_klub.php - fetch cabor list: " . $e->getMessage()); }
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama)) {
    try {
        $stmt_nama_cabor = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor");
        $stmt_nama_cabor->bindParam(':id_cabor', $id_cabor_pengurus_utama, PDO::PARAM_INT);
        $stmt_nama_cabor->execute();
        $nama_cabor_pengurus_form = $stmt_nama_cabor->fetchColumn();
    } catch (PDOException $e) { error_log("Error tambah_klub.php - fetch nama cabor pengurus: " . $e->getMessage()); }
}
$form_data = $_SESSION['form_data_klub_tambah'] ?? [];
$form_errors = $_SESSION['errors_klub_tambah'] ?? []; 
$form_error_fields = $_SESSION['error_fields_klub_tambah'] ?? []; 
unset($_SESSION['form_data_klub_tambah']); unset($_SESSION['errors_klub_tambah']); unset($_SESSION['error_fields_klub_tambah']);
$default_id_cabor_form = $form_data['id_cabor'] ?? ($_GET['id_cabor_default'] ?? ($user_role_utama === 'pengurus_cabor' ? $id_cabor_pengurus_utama : ''));
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> <?php echo $page_title; ?></h3>
                    </div>
                    <form id="formTambahKlub" action="proses_tambah_klub.php" method="post" enctype="multipart/form-data">
                        <div class="card-body">
                            <?php if (!empty($form_errors)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Menyimpan!</h5>
                                    <ul>
                                        <?php foreach ($form_errors as $error_item): ?>
                                            <li><?php echo htmlspecialchars($error_item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- PENAMBAHAN: Sub-Judul Informasi Dasar Klub -->
                            <h5 class="mt-1 mb-3 text-olive"><i class="fas fa-shield-alt mr-1"></i> Informasi Dasar Klub</h5>

                            <div class="form-group">
                                <label for="nama_klub">Nama Klub <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields['nama_klub'])) echo 'is-invalid'; ?>" id="nama_klub" name="nama_klub"
                                       value="<?php echo htmlspecialchars($form_data['nama_klub'] ?? ''); ?>"
                                       placeholder="Masukkan Nama Klub Olahraga" required>
                                <small class="form-text text-muted">Contoh: Siantar Runner Club, Perkasa Badminton.</small>
                            </div>

                            <div class="form-group">
                                <label for="id_cabor">Cabang Olahraga Induk <span class="text-danger">*</span></label>
                                <?php if ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama)): ?>
                                    <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_pengurus_utama); ?>">
                                    <input type="text" class="form-control"
                                           value="<?php echo htmlspecialchars($nama_cabor_pengurus_form ?: 'Cabor Tidak Ditemukan'); ?>"
                                           readonly>
                                    <small class="form-text text-muted">Anda akan mendaftarkan klub untuk cabang olahraga <?php echo htmlspecialchars($nama_cabor_pengurus_form ?: 'Anda'); ?>.</small>
                                <?php elseif (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields['id_cabor'])) echo 'is-invalid'; ?>" id="id_cabor" name="id_cabor" style="width: 100%;" required data-placeholder="-- Pilih Cabang Olahraga --">
                                        <option value=""></option>
                                        <?php foreach ($cabang_olahraga_options_form as $cabor): ?>
                                            <option value="<?php echo $cabor['id_cabor']; ?>" <?php echo ($default_id_cabor_form == $cabor['id_cabor']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cabor['nama_cabor']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($cabang_olahraga_options_form)): ?>
                                        <small class="form-text text-danger">Tidak ada data cabang olahraga tersedia. Silakan tambahkan cabor terlebih dahulu.</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- PENAMBAHAN: Sub-Judul Detail Kontak dan Alamat -->
                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-address-card mr-1"></i> Detail Kontak & Alamat</h5>

                            <div class="form-group">
                                <label for="ketua_klub">Nama Ketua Klub</label>
                                <input type="text" class="form-control" id="ketua_klub" name="ketua_klub"
                                       value="<?php echo htmlspecialchars($form_data['ketua_klub'] ?? ''); ?>"
                                       placeholder="Nama lengkap ketua klub">
                            </div>

                            <div class="form-group">
                                <label for="alamat_sekretariat">Alamat Sekretariat</label>
                                <textarea class="form-control" id="alamat_sekretariat" name="alamat_sekretariat" rows="3"
                                          placeholder="Alamat lengkap sekretariat klub"><?php echo htmlspecialchars($form_data['alamat_sekretariat'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kontak_klub">Nomor Kontak Klub (Telepon/HP)</label>
                                        <input type="text" class="form-control" id="kontak_klub" name="kontak_klub"
                                               value="<?php echo htmlspecialchars($form_data['kontak_klub'] ?? ''); ?>"
                                               placeholder="Contoh: 081234567890">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email_klub">Alamat Email Klub</label>
                                        <input type="email" class="form-control <?php if(isset($form_error_fields['email_klub'])) echo 'is-invalid'; ?>" id="email_klub" name="email_klub"
                                               value="<?php echo htmlspecialchars($form_data['email_klub'] ?? ''); ?>"
                                               placeholder="Contoh: info@klubhebat.com">
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-file-contract mr-1"></i> Informasi Legalitas (SK Klub)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nomor_sk_klub">Nomor SK Klub</label>
                                        <input type="text" class="form-control" id="nomor_sk_klub" name="nomor_sk_klub"
                                               value="<?php echo htmlspecialchars($form_data['nomor_sk_klub'] ?? ''); ?>"
                                               placeholder="Nomor Surat Keputusan Klub">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tanggal_sk_klub">Tanggal SK Klub</label>
                                        <input type="date" class="form-control" id="tanggal_sk_klub" name="tanggal_sk_klub"
                                               value="<?php echo htmlspecialchars($form_data['tanggal_sk_klub'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- PENAMBAHAN: Sub-Judul untuk Upload Dokumen -->
                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-folder-open mr-1"></i> Upload Dokumen Klub</h5>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="path_sk_klub">Upload File Scan SK Klub (PDF, JPG, PNG, maks 2MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields['path_sk_klub'])) echo 'is-invalid'; ?>" id="path_sk_klub" name="path_sk_klub" accept=".pdf,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="path_sk_klub">Pilih file...</label>
                                        </div>
                                        <small class="form-text text-muted">Kosongkan jika belum ada.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="logo_klub">Upload Logo Klub (JPG, PNG, maks 1MB)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(isset($form_error_fields['logo_klub'])) echo 'is-invalid'; ?>" id="logo_klub" name="logo_klub" accept=".jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="logo_klub">Pilih file...</label>
                                        </div>
                                        <small class="form-text text-muted">Rekomendasi rasio 1:1 (persegi). Kosongkan jika belum ada.</small>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_klub" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Klub</button>
                            <a href="daftar_klub.php<?php // Perbaikan: $filter_id_cabor_get_page tidak terdefinisi di sini, gunakan $default_id_cabor_form
                                $filter_kembali = $default_id_cabor_form ?: ($user_role_utama === 'pengurus_cabor' ? $id_cabor_pengurus_utama : '');
                                if ($filter_kembali) { echo '?id_cabor=' . $filter_kembali; }
                            ?>" class="btn btn-secondary float-right">
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
// ... (Blok $inline_script JavaScript TETAP SAMA PERSIS seperti yang Anda berikan) ...
$is_admin_for_js = json_encode(in_array($user_role_utama, ['super_admin', 'admin_koni']));
$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    bsCustomFileInput.init();
  }

  if (typeof $.fn.select2 === 'function') {
    $('.select2bs4').select2({
      theme: 'bootstrap4',
      placeholder: $(this).data('placeholder') || '-- Pilih Opsi --',
      allowClear: true
    });
  }

  $('#formTambahKlub').submit(function(e) {
    let isValid = true;
    let focusField = null;

    let namaKlub = $('#nama_klub').val().trim();
    if (namaKlub === '') {
        if(!focusField) focusField = $('#nama_klub');
        isValid = false;
    }
    
    let isAdmin = $is_admin_for_js;
    if (isAdmin) {
        let idCabor = $('#id_cabor').val();
        if (idCabor === '' || idCabor === null) {
            if(!focusField) focusField = $('#id_cabor');
            isValid = false;
        }
    }
    
    let logoFile = $('#logo_klub')[0].files[0];
    if(logoFile && logoFile.size > 1 * 1024 * 1024){ // 1MB
       if(!focusField) focusField = $('#logo_klub');
       isValid = false;
       alert('Ukuran file Logo Klub tidak boleh lebih dari 1MB.');
    }

    let skFile = $('#path_sk_klub')[0].files[0];
    if(skFile && skFile.size > 2 * 1024 * 1024){ // 2MB
       if(!focusField) focusField = $('#path_sk_klub');
       isValid = false;
       alert('Ukuran file SK Klub tidak boleh lebih dari 2MB.');
    }
    
    if (!isValid) {
        e.preventDefault(); 
        if(focusField) {
            focusField.focus();
        }
    }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>