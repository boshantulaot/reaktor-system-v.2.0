<?php
// File: reaktorsystem/modules/klub/edit_klub.php

$page_title = "Edit Data Klub Olahraga";

// ... (Blok $additional_css dan $additional_js tetap sama) ...
$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// ... (Blok PHP untuk validasi sesi, peran, pengambilan data klub, dan penanganan form error TETAP SAMA PERSIS seperti yang Anda berikan) ...
if (!isset($_SESSION['user_nik']) || !isset($_SESSION['user_role_utama']) || !in_array($_SESSION['user_role_utama'], ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak untuk mengedit data klub.";
    header("Location: " . rtrim($app_base_path, '/') . "dashboard.php");
    exit();
}
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal! Tidak dapat memuat form edit klub.";
    error_log("EDIT_KLUB_ERROR: PDO tidak valid.");
    header("Location: " . rtrim($app_base_path, '/') . "/modules/klub/daftar_klub.php");
    exit();
}
$id_klub_to_edit = null; $klub_data = null; $nama_cabor_klub_saat_ini = '';
if (isset($_GET['id_klub']) && filter_var($_GET['id_klub'], FILTER_VALIDATE_INT)) {
    $id_klub_to_edit = (int)$_GET['id_klub'];
    if ($id_klub_to_edit <= 0) { /* ... redirect ... */ exit(); }
    try {
        $stmt = $pdo->prepare("SELECT k.*, co.nama_cabor FROM klub k JOIN cabang_olahraga co ON k.id_cabor = co.id_cabor WHERE k.id_klub = :id_klub");
        $stmt->bindParam(':id_klub', $id_klub_to_edit, PDO::PARAM_INT); $stmt->execute(); $klub_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$klub_data) { /* ... redirect ... */ exit(); }
        $nama_cabor_klub_saat_ini = $klub_data['nama_cabor'];
        if ($user_role_utama == 'pengurus_cabor' && ($_SESSION['id_cabor_pengurus_utama'] ?? null) != $klub_data['id_cabor']) { /* ... redirect ... */ exit(); }
    } catch (PDOException $e) { /* ... redirect ... */ exit(); }
} else { /* ... redirect ... */ exit(); }
$val_nama_klub = $_SESSION['form_data_klub_edit']['nama_klub'] ?? ($klub_data['nama_klub'] ?? '');
$val_id_cabor = $_SESSION['form_data_klub_edit']['id_cabor'] ?? ($klub_data['id_cabor'] ?? '');
// ... (inisialisasi $val_ lainnya tetap sama)
$val_ketua_klub = $_SESSION['form_data_klub_edit']['ketua_klub'] ?? ($klub_data['ketua_klub'] ?? '');
$val_alamat_klub = $_SESSION['form_data_klub_edit']['alamat_sekretariat'] ?? ($klub_data['alamat_sekretariat'] ?? '');
$val_kontak_klub = $_SESSION['form_data_klub_edit']['kontak_klub'] ?? ($klub_data['kontak_klub'] ?? '');
$val_email_klub = $_SESSION['form_data_klub_edit']['email_klub'] ?? ($klub_data['email_klub'] ?? '');
$val_nomor_sk_klub = $_SESSION['form_data_klub_edit']['nomor_sk_klub'] ?? ($klub_data['nomor_sk_klub'] ?? '');
$val_tanggal_sk_klub = $_SESSION['form_data_klub_edit']['tanggal_sk_klub'] ?? ($klub_data['tanggal_sk_klub'] ?? '');
$val_status_approval = $_SESSION['form_data_klub_edit']['status_approval_admin'] ?? ($klub_data['status_approval_admin'] ?? 'pending');
$val_alasan_penolakan = $_SESSION['form_data_klub_edit']['alasan_penolakan_admin'] ?? ($klub_data['alasan_penolakan_admin'] ?? '');
$errors_from_session = $_SESSION['errors_edit_klub'] ?? [];
$error_fields_from_session = $_SESSION['error_fields_klub_edit'] ?? [];
unset($_SESSION['form_data_klub_edit']); unset($_SESSION['errors_edit_klub']); unset($_SESSION['error_fields_klub_edit']);
$cabor_options_for_form = [];
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_cabor_options = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC");
        $cabor_options_for_form = $stmt_cabor_options->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Error mengambil daftar cabor untuk form edit klub: " . $e->getMessage()); }
}
$url_logo_display = ''; $logo_display_message = '<p class="text-muted text-sm mt-1">Logo belum diupload.</p>';
if (!empty($klub_data['logo_klub'])) { /* ... logika file_exists logo ... */ 
    $path_logo_db = $klub_data['logo_klub']; $full_path_logo_server = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($path_logo_db, '/');
    if (file_exists(preg_replace('/\/+/', '/', $full_path_logo_server))) { $url_logo_display = htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($path_logo_db, '/')); $logo_display_message = '';
    } else { $logo_display_message = '<p class="text-danger text-sm mt-1">File logo (<code class="text-danger">'.basename(htmlspecialchars($path_logo_db)).'</code>) tidak ditemukan di server.</p>'; }
}
$url_sk_display = ''; $sk_display_message = '<p class="text-muted text-sm mt-1">File SK belum diupload.</p>';
if (!empty($klub_data['path_sk_klub'])) { /* ... logika file_exists SK ... */ 
    $path_sk_db = $klub_data['path_sk_klub']; $full_path_sk_server = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($path_sk_db, '/');
     if (file_exists(preg_replace('/\/+/', '/', $full_path_sk_server))) { $url_sk_display = htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($path_sk_db, '/')); $sk_display_message = '';
    } else { $sk_display_message = '<p class="text-danger text-sm mt-1">File SK (<code class="text-danger">'.basename(htmlspecialchars($path_sk_db)).'</code>) tidak ditemukan di server.</p>'; }
}
?>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($errors_from_session)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong><i class="icon fas fa-ban"></i> Input tidak valid:</strong>
                    <ul>
                        <?php foreach ($errors_from_session as $error_msg_item): ?>
                            <li><?php echo htmlspecialchars($error_msg_item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-10 offset-md-1">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data Klub:
                                <strong><?php echo htmlspecialchars($klub_data['nama_klub']); ?></strong>
                                <small>(Cabor: <?php echo htmlspecialchars($nama_cabor_klub_saat_ini); ?>)</small>
                            </h3>
                        </div>
                        <form action="proses_edit_klub.php" method="post" enctype="multipart/form-data" id="formEditKlub">
                            <input type="hidden" name="id_klub" value="<?php echo htmlspecialchars($id_klub_to_edit); ?>">
                            <input type="hidden" name="path_sk_klub_lama" value="<?php echo htmlspecialchars($klub_data['path_sk_klub'] ?? ''); ?>">
                            <input type="hidden" name="logo_klub_lama" value="<?php echo htmlspecialchars($klub_data['logo_klub'] ?? ''); ?>">

                            <div class="card-body">
                                <!-- PENAMBAHAN: Sub-Judul Informasi Dasar Klub -->
                                <h5 class="mt-1 mb-3 text-olive"><i class="fas fa-shield-alt mr-1"></i> Informasi Dasar Klub</h5>

                                <div class="form-group">
                                    <label for="nama_klub_edit">Nama Klub <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php if(isset($error_fields_from_session['nama_klub'])) echo 'is-invalid'; ?>" id="nama_klub_edit" name="nama_klub" placeholder="Masukkan Nama Klub" value="<?php echo htmlspecialchars($val_nama_klub); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="id_cabor_edit">Cabang Olahraga <span class="text-danger">*</span></label>
                                    <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                        <select class="form-control select2bs4edit <?php if(isset($error_fields_from_session['id_cabor'])) echo 'is-invalid'; ?>" id="id_cabor_edit" name="id_cabor" style="width: 100%;" required data-placeholder="-- Pilih Cabang Olahraga --">
                                            <option value=""></option>
                                            <?php foreach ($cabor_options_for_form as $cabor_opt): ?>
                                                <option value="<?php echo htmlspecialchars($cabor_opt['id_cabor']); ?>" <?php echo ($val_id_cabor == $cabor_opt['id_cabor']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cabor_opt['nama_cabor']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_cabor_klub_saat_ini); ?>" readonly>
                                        <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($klub_data['id_cabor']); ?>">
                                    <?php endif; ?>
                                </div>

                                <!-- PENAMBAHAN: Sub-Judul Detail Kontak dan Alamat -->
                                <hr>
                                <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-address-card mr-1"></i> Detail Pengurus & Kontak</h5>

                                <div class="form-group">
                                    <label for="ketua_klub_edit">Nama Ketua Klub</label>
                                    <input type="text" class="form-control" id="ketua_klub_edit" name="ketua_klub" placeholder="Masukkan Nama Ketua Klub" value="<?php echo htmlspecialchars($val_ketua_klub); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="alamat_sekretariat_edit">Alamat Sekretariat Klub</label>
                                    <textarea class="form-control" id="alamat_sekretariat_edit" name="alamat_sekretariat" rows="3" placeholder="Alamat Lengkap Sekretariat Klub"><?php echo htmlspecialchars($val_alamat_klub); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="kontak_klub_edit">Kontak Klub (Telepon/HP)</label>
                                            <input type="text" class="form-control" id="kontak_klub_edit" name="kontak_klub" placeholder="Contoh: 08123456789" value="<?php echo htmlspecialchars($val_kontak_klub); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email_klub_edit">Email Klub</label>
                                            <input type="email" class="form-control" id="email_klub_edit" name="email_klub" placeholder="Contoh: info@namaklub.com" value="<?php echo htmlspecialchars($val_email_klub); ?>">
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-file-contract mr-1"></i> Informasi Legalitas Klub</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nomor_sk_klub_edit">Nomor SK Klub</label>
                                            <input type="text" class="form-control" id="nomor_sk_klub_edit" name="nomor_sk_klub" placeholder="Nomor SK Pendirian/Pengesahan" value="<?php echo htmlspecialchars($val_nomor_sk_klub); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tanggal_sk_klub_edit">Tanggal SK Klub</label>
                                            <input type="date" class="form-control" id="tanggal_sk_klub_edit" name="tanggal_sk_klub" value="<?php echo htmlspecialchars($val_tanggal_sk_klub); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- PENAMBAHAN: Sub-Judul untuk Upload Dokumen -->
                                <hr>
                                <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-folder-open mr-1"></i> Upload Dokumen Klub (SK & Logo)</h5>
                                
                                <div class="form-group">
                                    <label for="path_sk_klub_edit">Upload File SK Klub Baru (PDF, JPG, PNG, maks 2MB)</label>
                                    <small class="form-text text-muted d-block mb-1">Kosongkan jika tidak ingin mengubah file SK yang sudah ada.</small>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="path_sk_klub_edit" name="path_sk_klub" accept=".pdf,.jpg,.jpeg,.png">
                                        <label class="custom-file-label" for="path_sk_klub_edit">Pilih file SK baru...</label>
                                    </div>
                                    <?php if (!empty($url_sk_display)): ?>
                                        <div class="mt-2"><small class="form-text text-muted d-block">File SK saat ini: <a href="<?php echo $url_sk_display; ?>" target="_blank" class="text-primary"><?php echo basename(htmlspecialchars($klub_data['path_sk_klub'])); ?></a></small></div>
                                    <?php else: echo $sk_display_message; endif; ?>
                                </div>

                                <div class="form-group"> <!-- Logo Klub dipisahkan agar lebih jelas -->
                                    <label for="logo_klub_edit">Upload Logo Klub Baru (JPG, PNG, maks 1MB)</label>
                                    <small class="form-text text-muted d-block mb-1">Kosongkan jika tidak ingin mengubah logo yang sudah ada.</small>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="logo_klub_edit" name="logo_klub" accept="image/jpeg,image/png">
                                        <label class="custom-file-label" for="logo_klub_edit">Pilih file logo baru...</label>
                                    </div>
                                    <?php if (!empty($url_logo_display)): ?>
                                        <div class="mt-2"><small class="form-text text-muted d-block">Logo saat ini:</small><img src="<?php echo $url_logo_display; ?>" alt="Logo Klub <?php echo htmlspecialchars($klub_data['nama_klub']); ?>" style="max-height: 70px; margin-top: 5px; border:1px solid #dee2e6; padding:3px; border-radius: .25rem;"></div>
                                    <?php else: echo $logo_display_message; endif; ?>
                                </div>

                                <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                    <hr>
                                    <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-check-circle mr-1"></i> Status Approval (Oleh Admin KONI)</h5>
                                    <div class="form-group">
                                        <label for="status_approval_admin_edit">Ubah Status Approval</label>
                                        <select class="form-control" id="status_approval_admin_edit" name="status_approval_admin">
                                            <option value="pending" <?php echo ($val_status_approval == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="disetujui" <?php echo ($val_status_approval == 'disetujui') ? 'selected' : ''; ?>>Disetujui</option>
                                            <option value="ditolak" <?php echo ($val_status_approval == 'ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="form_group_alasan_penolakan_edit" style="<?php echo ($val_status_approval != 'ditolak') ? 'display:none;' : ''; ?>">
                                        <label for="alasan_penolakan_admin_edit">Alasan Penolakan (Wajib diisi jika status 'Ditolak')</label>
                                        <textarea class="form-control" id="alasan_penolakan_admin_edit" name="alasan_penolakan_admin" rows="2" placeholder="Isi alasan jika menolak approval"><?php echo htmlspecialchars($val_alasan_penolakan); ?></textarea>
                                    </div>
                                <?php else: ?>
                                    <hr>
                                    <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-info-circle mr-1"></i> Status Approval Saat Ini</h5>
                                    <p>Status: <span class="badge badge-<?php echo ($val_status_approval == 'disetujui' ? 'success' : ($val_status_approval == 'pending' ? 'warning' : 'danger')); ?> p-1"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$val_status_approval))); ?></span></p>
                                    <?php if ($val_status_approval == 'ditolak' && !empty($val_alasan_penolakan)): ?>
                                        <p><strong>Alasan Penolakan:</strong><br><?php echo nl2br(htmlspecialchars($val_alasan_penolakan)); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted text-sm"><em>Jika Anda melakukan perubahan data pada form ini, status approval mungkin akan direset menjadi 'Pending' dan memerlukan persetujuan ulang dari Admin KONI.</em></p>
                                <?php endif; ?>
                                <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="submit_edit_klub" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Update Data Klub</button>
                                <a href="daftar_klub.php<?php echo $klub_data['id_cabor'] ? '?id_cabor=' . $klub_data['id_cabor'] : ''; ?>" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
// ... (Blok $inline_script JavaScript TETAP SAMA PERSIS seperti yang Anda berikan) ...
$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    bsCustomFileInput.init();
  }

  if (typeof $.fn.select2 === 'function') {
    $('.select2bs4edit').select2({
      theme: 'bootstrap4',
      placeholder: $(this).data('placeholder') || '-- Pilih Opsi --', 
      allowClear: true 
    });
  }

  var statusApprovalSelectEdit = $('#status_approval_admin_edit');
  var alasanPenolakanGroupEdit = $('#form_group_alasan_penolakan_edit');
  var alasanTextareaEdit = $('#alasan_penolakan_admin_edit');

  function toggleAlasanPenolakanEdit() {
    if (statusApprovalSelectEdit.length && alasanPenolakanGroupEdit.length) { 
        if (statusApprovalSelectEdit.val() === 'ditolak') {
            alasanPenolakanGroupEdit.slideDown(); 
        } else {
            alasanPenolakanGroupEdit.slideUp(); 
        }
    }
  }

  if (statusApprovalSelectEdit.length) {
    statusApprovalSelectEdit.on('change', toggleAlasanPenolakanEdit);
    toggleAlasanPenolakanEdit(); 
  }
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>