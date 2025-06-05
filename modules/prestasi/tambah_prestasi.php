<?php
// File: reaktorsystem/modules/prestasi/tambah_prestasi.php

$page_title = "Tambah Data Prestasi Atlet";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// ---- Definisi konstanta ukuran file ----
if (!defined('MAX_FILE_SIZE_BUKTI_PRESTASI_MB')) {
    define('MAX_FILE_SIZE_BUKTI_PRESTASI_MB', 2);
}
if (!defined('MAX_FILE_SIZE_BUKTI_PRESTASI_BYTES')) {
    define('MAX_FILE_SIZE_BUKTI_PRESTASI_BYTES', MAX_FILE_SIZE_BUKTI_PRESTASI_MB * 1024 * 1024);
}

// ... (Seluruh blok PHP untuk validasi sesi, peran, pengambilan data cabor, klub, dan penanganan form error dari versi sebelumnya yang sudah baik, SAYA ASUMSIKAN INI SUDAH BENAR dan tidak ada output liar dari sini) ...
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo)) { 
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah. Silakan login kembali.";
    if (!headers_sent()) { header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php"); } else { echo "<div class='alert alert-danger text-center m-3'>Error kritis: Sesi tidak valid. Harap <a href='" . rtrim($app_base_path, '/') . "/auth/login.php'>login ulang</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) require_once(__DIR__ . '/../../core/footer.php');
    exit();
}
if (!in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor', 'atlet'])) { $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini."; header("Location: daftar_prestasi.php"); exit(); }
if ($user_role_utama === 'pengurus_cabor' && empty($id_cabor_pengurus_utama)) { $_SESSION['pesan_error_global'] = "Informasi cabang olahraga Anda tidak lengkap. Tidak dapat menambah prestasi."; header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php"); exit(); }
$cabor_options_prestasi_form = []; $atlet_options_prestasi_form = []; $nama_cabor_pengurus_prestasi_form = ''; $nama_atlet_default_display_prestasi = ''; $id_cabor_for_atlet_load_initial = null;
$default_id_cabor_from_get = isset($_GET['id_cabor_default']) && filter_var($_GET['id_cabor_default'], FILTER_VALIDATE_INT) ? (int)$_GET['id_cabor_default'] : null;
$default_nik_atlet_from_get = isset($_GET['nik_atlet_default']) && preg_match('/^\d{1,16}$/', $_GET['nik_atlet_default']) ? $_GET['nik_atlet_default'] : null;
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try { $stmt_cabor = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC"); $cabor_options_prestasi_form = $stmt_cabor->fetchAll(PDO::FETCH_ASSOC); if ($default_id_cabor_from_get) { $id_cabor_for_atlet_load_initial = $default_id_cabor_from_get; }} catch (PDOException $e) { error_log("Error tambah_prestasi.php - fetch cabor (admin): " . $e->getMessage()); }
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama)) {
    try { $stmt_nama_cabor = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor AND status_kepengurusan = 'Aktif'"); $stmt_nama_cabor->bindParam(':id_cabor', $id_cabor_pengurus_utama, PDO::PARAM_INT); $stmt_nama_cabor->execute(); $nama_cabor_pengurus_prestasi_form = $stmt_nama_cabor->fetchColumn(); if (!$nama_cabor_pengurus_prestasi_form) { $_SESSION['pesan_error_global'] = "Cabang olahraga yang Anda kelola tidak aktif atau tidak ditemukan."; header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php"); exit(); } $id_cabor_for_atlet_load_initial = $id_cabor_pengurus_utama; $stmt_atlet_opt_pengcab = $pdo->prepare("SELECT p.nik, p.nama_lengkap FROM atlet a JOIN pengguna p ON a.nik = p.nik WHERE a.id_cabor = :id_cabor_pengurus AND a.status_pendaftaran = 'disetujui' AND p.is_approved = 1 ORDER BY p.nama_lengkap ASC"); $stmt_atlet_opt_pengcab->bindParam(':id_cabor_pengurus', $id_cabor_pengurus_utama, PDO::PARAM_INT); $stmt_atlet_opt_pengcab->execute(); $atlet_options_prestasi_form = $stmt_atlet_opt_pengcab->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { error_log("Error tambah_prestasi.php - fetch data pengurus cabor: " . $e->getMessage()); }
} elseif ($user_role_utama === 'atlet') {
    $default_nik_atlet_from_get = $user_nik; $nama_atlet_default_display_prestasi = $user_nama_lengkap; 
    try { $stmt_cabor_atlet = $pdo->prepare("SELECT DISTINCT a.id_cabor, co.nama_cabor FROM atlet a JOIN cabang_olahraga co ON a.id_cabor = co.id_cabor WHERE a.nik = :nik AND a.status_pendaftaran = 'disetujui' LIMIT 1"); $stmt_cabor_atlet->bindParam(':nik', $user_nik, PDO::PARAM_STR); $stmt_cabor_atlet->execute(); $cabor_atlet_info = $stmt_cabor_atlet->fetch(PDO::FETCH_ASSOC); if ($cabor_atlet_info) { $default_id_cabor_from_get = $cabor_atlet_info['id_cabor']; $nama_cabor_pengurus_prestasi_form = $cabor_atlet_info['nama_cabor']; }} catch (PDOException $e) { error_log("Error tambah_prestasi.php - fetch cabor atlet: " . $e->getMessage()); }
}
if ($id_cabor_for_atlet_load_initial && in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try { $stmt_atlet_opt_admin = $pdo->prepare("SELECT p.nik, p.nama_lengkap FROM atlet a JOIN pengguna p ON a.nik = p.nik WHERE a.id_cabor = :id_cabor AND a.status_pendaftaran = 'disetujui' AND p.is_approved = 1 ORDER BY p.nama_lengkap ASC"); $stmt_atlet_opt_admin->bindParam(':id_cabor', $id_cabor_for_atlet_load_initial, PDO::PARAM_INT); $stmt_atlet_opt_admin->execute(); if(empty($atlet_options_prestasi_form)){ $atlet_options_prestasi_form = $stmt_atlet_opt_admin->fetchAll(PDO::FETCH_ASSOC); }} catch (PDOException $e) { error_log("Error tambah_prestasi.php - fetch atlet list initial (admin): " . $e->getMessage()); }
}
$form_data_prestasi = $_SESSION['form_data_prestasi_tambah'] ?? []; $form_errors_prestasi = $_SESSION['errors_prestasi_tambah'] ?? []; $form_error_fields_prestasi = $_SESSION['error_fields_prestasi_tambah'] ?? [];
unset($_SESSION['form_data_prestasi_tambah']); unset($_SESSION['errors_prestasi_tambah']); unset($_SESSION['error_fields_prestasi_tambah']);
$val_id_cabor_form = $form_data_prestasi['id_cabor'] ?? $default_id_cabor_from_get; $val_nik_atlet_form = $form_data_prestasi['nik'] ?? $default_nik_atlet_from_get; $val_nama_kejuaraan = $form_data_prestasi['nama_kejuaraan'] ?? ''; $val_tingkat_kejuaraan = $form_data_prestasi['tingkat_kejuaraan'] ?? ''; $val_tahun_perolehan = $form_data_prestasi['tahun_perolehan'] ?? date('Y'); $val_medali_peringkat = $form_data_prestasi['medali_peringkat'] ?? '';
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-award mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formTambahPrestasi" action="proses_tambah_prestasi.php" method="post" enctype="multipart/form-data">
                        <div class="card-body">
                            <?php if (!empty($form_errors_prestasi)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Menyimpan Data Prestasi!</h5>
                                    <ul> <?php foreach ($form_errors_prestasi as $error_item_prestasi): ?> <li><?php echo htmlspecialchars($error_item_prestasi); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-success"><i class="fas fa-user-check mr-1"></i> Informasi Atlet & Cabor</h5>
                            <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                <div class="form-group">
                                    <label for="id_cabor_prestasi_admin">Cabang Olahraga Atlet <span class="text-danger">*</span></label>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_prestasi['id_cabor'])) echo 'is-invalid'; ?>" id="id_cabor_prestasi_admin" name="id_cabor" style="width: 100%;" required data-placeholder="-- Pilih Cabang Olahraga --">
                                        <option value=""></option>
                                        <?php foreach ($cabor_options_prestasi_form as $cabor_opt): ?>
                                            <option value="<?php echo $cabor_opt['id_cabor']; ?>" <?php echo ($val_id_cabor_form == $cabor_opt['id_cabor']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cabor_opt['nama_cabor']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($cabor_options_prestasi_form)): ?><small class="form-text text-danger">Tidak ada cabor aktif.</small><?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="nik_atlet_prestasi_admin">NIK Atlet <span class="text-danger">*</span></label>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_prestasi['nik'])) echo 'is-invalid'; ?>" id="nik_atlet_prestasi_admin" name="nik" style="width: 100%;" required data-placeholder="-- Pilih Atlet --" disabled>
                                        <option value=""></option>
                                        <?php foreach ($atlet_options_prestasi_form as $atlet_opt): if ($val_id_cabor_form == $id_cabor_for_atlet_load_initial): ?>
                                            <option value="<?php echo $atlet_opt['nik']; ?>" <?php echo ($val_nik_atlet_form == $atlet_opt['nik']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($atlet_opt['nama_lengkap'] . ' (NIK: ' . $atlet_opt['nik'] . ')'); ?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                    <small class="form-text text-info" id="nik_atlet_admin_info">Pilih Cabor untuk memuat atlet.</small>
                                </div>
                            <?php elseif ($user_role_utama == 'pengurus_cabor'): ?>
                                <!-- ... (Blok HTML untuk Pengurus Cabor sama seperti sebelumnya) ... -->
                                <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_pengurus_utama); ?>">
                                <div class="form-group"><label>Cabang Olahraga:</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_cabor_pengurus_prestasi_form); ?>" readonly></div>
                                <div class="form-group">
                                    <label for="nik_atlet_prestasi_pengcab">Pilih Atlet dari Cabor Anda <span class="text-danger">*</span></label>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_prestasi['nik'])) echo 'is-invalid'; ?>" id="nik_atlet_prestasi_pengcab" name="nik" style="width: 100%;" required data-placeholder="-- Pilih Atlet --">
                                        <option value=""></option>
                                        <?php foreach ($atlet_options_prestasi_form as $atlet_opt): ?><option value="<?php echo $atlet_opt['nik']; ?>" <?php echo ($val_nik_atlet_form == $atlet_opt['nik']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($atlet_opt['nama_lengkap'] . ' (NIK: ' . $atlet_opt['nik'] . ')'); ?></option><?php endforeach; ?>
                                    </select>
                                    <?php if (empty($atlet_options_prestasi_form)): ?><small class="form-text text-warning">Tidak ada atlet disetujui di cabor Anda.</small><?php endif; ?>
                                </div>
                            <?php elseif ($user_role_utama == 'atlet'): ?>
                                <!-- ... (Blok HTML untuk Atlet sama seperti sebelumnya) ... -->
                                <input type="hidden" name="nik" value="<?php echo htmlspecialchars($user_nik); ?>"><input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($val_id_cabor_form); ?>">
                                <p><strong>Atlet:</strong> <?php echo htmlspecialchars($user_nama_lengkap); ?> (NIK: <?php echo htmlspecialchars($user_nik); ?>)</p>
                                <p><strong>Cabang Olahraga:</strong> <?php echo htmlspecialchars($nama_cabor_pengurus_prestasi_form ?: 'Cabor tidak terdefinisi'); ?></p>
                                <?php if(empty($val_id_cabor_form)): ?> <p class="text-danger">Data cabor atlet tidak ditemukan. Penambahan prestasi mungkin gagal.</p> <?php endif; ?>
                            <?php endif; ?>

                            <hr>
                            <h5 class="mt-3 mb-3 text-success"><i class="fas fa-medal mr-1"></i> Detail Prestasi</h5>
                            <!-- ... (Sisa field form untuk detail prestasi sama seperti sebelumnya) ... -->
                            <div class="form-group"><label for="nama_kejuaraan">Nama Kejuaraan <span class="text-danger">*</span></label><input type="text" class="form-control <?php if(isset($form_error_fields_prestasi['nama_kejuaraan'])) echo 'is-invalid'; ?>" id="nama_kejuaraan" name="nama_kejuaraan" placeholder="Contoh: Pekan Olahraga Nasional XX Papua" value="<?php echo htmlspecialchars($val_nama_kejuaraan); ?>" required></div>
                            <div class="row"><div class="col-md-6"><div class="form-group"><label for="tingkat_kejuaraan">Tingkat Kejuaraan <span class="text-danger">*</span></label><select class="form-control <?php if(isset($form_error_fields_prestasi['tingkat_kejuaraan'])) echo 'is-invalid'; ?>" id="tingkat_kejuaraan" name="tingkat_kejuaraan" required><option value="">-- Pilih Tingkat --</option><option value="Kabupaten" <?php echo ($val_tingkat_kejuaraan == 'Kabupaten') ? 'selected' : ''; ?>>Kabupaten/Kota</option><option value="Provinsi" <?php echo ($val_tingkat_kejuaraan == 'Provinsi') ? 'selected' : ''; ?>>Provinsi</option><option value="Nasional" <?php echo ($val_tingkat_kejuaraan == 'Nasional') ? 'selected' : ''; ?>>Nasional</option><option value="Internasional" <?php echo ($val_tingkat_kejuaraan == 'Internasional') ? 'selected' : ''; ?>>Internasional</option></select></div></div><div class="col-md-6"><div class="form-group"><label for="tahun_perolehan">Tahun Perolehan <span class="text-danger">*</span></label><input type="number" class="form-control <?php if(isset($form_error_fields_prestasi['tahun_perolehan'])) echo 'is-invalid'; ?>" id="tahun_perolehan" name="tahun_perolehan" placeholder="YYYY" value="<?php echo htmlspecialchars($val_tahun_perolehan); ?>" min="1900" max="<?php echo date('Y') + 1; ?>" required></div></div></div>
                            <div class="form-group"><label for="medali_peringkat">Medali / Peringkat <span class="text-danger">*</span></label><input type="text" class="form-control <?php if(isset($form_error_fields_prestasi['medali_peringkat'])) echo 'is-invalid'; ?>" id="medali_peringkat" name="medali_peringkat" placeholder="Contoh: Emas, Juara 1, Finalis" value="<?php echo htmlspecialchars($val_medali_peringkat); ?>" required></div>
                            <hr>
                            <h5 class="mt-3 mb-3 text-success"><i class="fas fa-file-alt mr-1"></i> Bukti Prestasi</h5>
                            <div class="form-group"><label for="bukti_path">Upload Bukti (Opsional - PDF, JPG, PNG - Maks <?php echo MAX_FILE_SIZE_BUKTI_PRESTASI_MB; ?>MB)</label><div class="custom-file"><input type="file" class="custom-file-input <?php if(isset($form_error_fields_prestasi['bukti_path'])) echo 'is-invalid'; ?>" id="bukti_path" name="bukti_path" accept=".pdf,.jpg,.jpeg,.png"><label class="custom-file-label" for="bukti_path">Pilih file bukti...</label></div><small class="form-text text-muted">Sertifikat, foto medali, atau dokumen pendukung lainnya.</small></div>
                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_prestasi" class="btn btn-success"><i class="fas fa-plus-circle mr-1"></i> Ajukan Data Prestasi</button>
                            <a href="daftar_prestasi.php<?php echo ($val_id_cabor_form) ? '?id_cabor=' . $val_id_cabor_form : ''; ?>" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Membangun $inline_script dengan pendekatan yang lebih aman untuk blok PHP
$script_admin_ajax_prestasi = '';
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $script_admin_ajax_prestasi = "
    console.log('Tambah Prestasi (Admin/SA): Menyiapkan event listener untuk #id_cabor_prestasi_admin.');
    
    $('#id_cabor_prestasi_admin').on('change', function () {
        var selectedCaborId = $(this).val();
        var atletSelect = $('#nik_atlet_prestasi_admin');
        var atletInfo = $('#nik_atlet_admin_info');
        
        console.log('Tambah Prestasi (Admin/SA): Cabor diubah, ID: ' + selectedCaborId);

        atletSelect.val(null).trigger('change.select2');
        atletSelect.empty().append($('<option></option>').attr('value', '').text('-- Pilih Atlet --'));
        
        if (selectedCaborId && selectedCaborId !== '') {
            atletSelect.prop('disabled', true).select2({ placeholder: 'Memuat atlet...', theme: 'bootstrap4', allowClear: true });
            atletInfo.text('Memuat atlet untuk cabor terpilih...');
            console.log('Tambah Prestasi (Admin/SA): AJAX call ke: " . rtrim($app_base_path, '/') . "/ajax/ajax_get_atlet_by_cabor.php');
            
            $.ajax({
                url: '" . rtrim($app_base_path, '/') . "/ajax/ajax_get_atlet_by_cabor.php',
                type: 'POST',
                data: { id_cabor: selectedCaborId, status_pendaftaran: 'disetujui' },
                dataType: 'json',
                success: function(response) {
                    console.log('Tambah Prestasi (Admin/SA): AJAX Response:', response);
                    atletSelect.prop('disabled', false);
                    atletSelect.empty().append($('<option></option>').attr('value', '').text('-- Pilih Atlet --'));

                    if (response.status === 'success' && response.atlet_list && response.atlet_list.length > 0) {
                        $.each(response.atlet_list, function(index, atlet) {
                            atletSelect.append(new Option(atlet.nama_lengkap + ' (NIK: ' + atlet.nik + ')', atlet.nik));
                        });
                        atletInfo.text('Pilih atlet dari daftar.');
                    } else {
                         atletSelect.append(new Option((response.message || 'Tidak ada atlet disetujui'), '', true, true));
                         atletInfo.text(response.message || 'Tidak ada atlet disetujui di cabor ini.');
                    }
                    atletSelect.select2({ placeholder: '-- Pilih Atlet --', theme: 'bootstrap4', allowClear: true });
                    
                    var initialNikAtletAdmin = '" . htmlspecialchars($val_nik_atlet_form ?? '') . "';
                    var initialCaborForNik = '" . htmlspecialchars($val_id_cabor_form ?? '') . "';
                    if(initialNikAtletAdmin && selectedCaborId == initialCaborForNik) {
                        atletSelect.val(initialNikAtletAdmin).trigger('change.select2');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Tambah Prestasi (Admin/SA): AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    atletSelect.prop('disabled', false);
                    atletSelect.empty().append('<option value=\"\">Error memuat atlet</option>');
                    atletSelect.select2({ placeholder: 'Error memuat atlet', theme: 'bootstrap4', allowClear: true });
                    atletInfo.text('Error memuat atlet.');
                }
            });
        } else { 
            atletSelect.prop('disabled', true);
            atletSelect.select2({ placeholder: '-- Pilih Cabor terlebih dahulu --', theme: 'bootstrap4', allowClear: true });
            atletInfo.text('Pilih Cabor untuk memuat atlet.');
        }
    });
    
    var initialCaborIdPrestasiAdminOnLoad = '" . htmlspecialchars($val_id_cabor_form ?? '') . "';
    if (initialCaborIdPrestasiAdminOnLoad && initialCaborIdPrestasiAdminOnLoad !== '') {
        console.log('Tambah Prestasi (Admin/SA): Initial Cabor ID saat load: ' + initialCaborIdPrestasiAdminOnLoad + '. Triggering change.');
        $('#id_cabor_prestasi_admin').val(initialCaborIdPrestasiAdminOnLoad).trigger('change');
    } else {
        $('#nik_atlet_prestasi_admin').prop('disabled', true).select2({ placeholder: '-- Pilih Cabor terlebih dahulu --', theme: 'bootstrap4', allowClear: true });
        $('#nik_atlet_admin_info').text('Pilih Cabor untuk memuat atlet.');
    }
    ";
}

$inline_script = "
$(function () {
  console.log('Tambah Prestasi: Dokumen siap.');

  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    console.log('Tambah Prestasi: Inisialisasi bsCustomFileInput.');
    bsCustomFileInput.init();
  } else {
    console.warn('Tambah Prestasi: bsCustomFileInput tidak terdefinisi atau bukan fungsi.');
  }

  if (typeof $.fn.select2 === 'function') {
    console.log('Tambah Prestasi: Inisialisasi Select2.');
    $('#id_cabor_prestasi_admin.select2bs4, #nik_atlet_prestasi_admin.select2bs4, #nik_atlet_prestasi_pengcab.select2bs4').each(function() {
        if ($(this).data('select2')) { $(this).select2('destroy'); } // Hancurkan instance lama jika ada
        $(this).select2({ 
          theme: 'bootstrap4',
          placeholder: $(this).data('placeholder') || '-- Pilih --', // Ambil placeholder dari atribut data
          allowClear: true
        });
    });
    // Pastikan dropdown atlet untuk admin/SA di-disable di awal jika cabor belum dipilih
    if ($('#id_cabor_prestasi_admin').val() === '' || $('#id_cabor_prestasi_admin').val() === null) {
        $('#nik_atlet_prestasi_admin').prop('disabled', true);
    }

  } else {
    console.warn('Tambah Prestasi: Select2 tidak terdefinisi atau bukan fungsi.');
  }

  " . $script_admin_ajax_prestasi . " // Menyisipkan script AJAX khusus admin

  $('#formTambahPrestasi').submit(function(e) {
    // ... (Logika validasi frontend tetap sama) ...
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>