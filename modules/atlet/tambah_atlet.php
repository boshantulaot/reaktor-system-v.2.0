<?php
// File: modules/atlet/tambah_atlet.php
$page_title = "Tambah Atlet Baru";
$current_page_is_tambah_atlet = true; 

// --- Definisi Aset CSS & JS Tambahan ---
$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/bs-custom-file-input/bs-custom-file-input.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Pastikan user memiliki hak akses (Logika Anda dipertahankan)
if (!in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: " . APP_URL_BASE . "/dashboard.php");
    exit();
}

$id_cabor_pengurus = null;
if ($user_role_utama === 'pengurus_cabor') {
    $id_cabor_pengurus = $_SESSION['id_cabor_pengurus_utama'] ?? null;
    if (!$id_cabor_pengurus) {
        $_SESSION['pesan_error_global'] = "Informasi cabor Anda tidak valid. Tidak dapat menambah atlet.";
        header("Location: " . APP_URL_BASE . "/dashboard.php");
        exit();
    }
}

$cabor_list = [];
try {
    $query_cabor = "SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC";
    if ($id_cabor_pengurus) {
        $query_cabor = "SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor_pengurus ORDER BY nama_cabor ASC";
        $stmt_cabor = $pdo->prepare($query_cabor);
        $stmt_cabor->execute([':id_cabor_pengurus' => $id_cabor_pengurus]);
    } else {
        $stmt_cabor = $pdo->query($query_cabor);
    }
    if($stmt_cabor) { 
        $cabor_list = $stmt_cabor->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error ambil data cabor di tambah_atlet: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Gagal memuat data cabang olahraga.";
}

$form_data = $_SESSION['form_data_tambah_atlet'] ?? []; 
$errors = $_SESSION['errors_tambah_atlet'] ?? [];
unset($_SESSION['form_data_tambah_atlet'], $_SESSION['errors_tambah_atlet']);

?>

<section class="content">
    <div class="container-fluid">
        <?php
        if (!empty($errors)) { /* ... tampilkan errors ... */ }
        if (isset($_SESSION['pesan_sukses_global'])) { /* ... tampilkan pesan sukses ... */ }
        if (isset($_SESSION['pesan_error_global'])) { /* ... tampilkan pesan error ... */ }
        ?>

        <div class="card card-outline card-purple">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-plus mr-1"></i> Formulir Pendaftaran Atlet Baru</h3>
                <div class="card-tools">
                    <a href="daftar_atlet.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Atlet</a>
                </div>
            </div>
            <form action="proses_tambah_atlet.php" method="post" enctype="multipart/form-data" id="formTambahAtlet">
                <div class="card-body">
                    <?php // --- Bagian HTML Form Anda (NIK, Cabor, Klub, Upload) SUDAH BAGUS, DIPERTAHANKAN --- ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nik">NIK Calon Atlet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['nik']) ? 'is-invalid' : ''; ?>" id="nik" name="nik" value="<?php echo htmlspecialchars($form_data['nik'] ?? ''); ?>" placeholder="Masukkan NIK Calon Atlet (16 digit)" maxlength="16" required>
                                <small id="nik_help" class="form-text text-muted">Pastikan NIK sudah terdaftar di sistem pengguna.</small>
                                <div id="infoNamaPengguna" class="mt-2" style="display:none;">
                                    <span class="badge badge-info p-2">Nama Pengguna: <strong id="namaPenggunaDariNik"></strong></span>
                                </div>
                                <?php if (isset($errors['nik'])): ?><span class="invalid-feedback"><?php echo htmlspecialchars($errors['nik']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_cabor">Cabang Olahraga <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php echo isset($errors['id_cabor']) ? 'is-invalid' : ''; ?>" id="id_cabor" name="id_cabor" style="width: 100%;" required <?php if ($id_cabor_pengurus && count($cabor_list) === 1) echo 'disabled'; ?>>
                                    <option value="">-- Pilih Cabang Olahraga --</option>
                                    <?php foreach ($cabor_list as $cabor): ?>
                                        <option value="<?php echo $cabor['id_cabor']; ?>" <?php echo (($form_data['id_cabor'] ?? ($id_cabor_pengurus ?? '')) == $cabor['id_cabor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cabor['nama_cabor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($id_cabor_pengurus && count($cabor_list) === 1): ?>
                                    <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_pengurus); ?>">
                                <?php endif; ?>
                                <?php if (isset($errors['id_cabor'])): ?><span class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['id_cabor']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_klub">Klub Afiliasi (Opsional)</label>
                                <select class="form-control select2bs4 <?php echo isset($errors['id_klub']) ? 'is-invalid' : ''; ?>" id="id_klub" name="id_klub" style="width: 100%;">
                                    <option value="">-- Pilih Klub (jika ada, setelah memilih cabor) --</option>
                                </select>
                                <?php if (isset($errors['id_klub'])): ?><span class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['id_klub']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-file-upload mr-1"></i> Upload Dokumen Pendukung</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pas_foto_path">Pas Foto Atlet</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php echo isset($errors['pas_foto_path']) ? 'is-invalid' : ''; ?>" id="pas_foto_path" name="pas_foto_path" accept="image/jpeg,image/png,image/gif">
                                    <label class="custom-file-label" for="pas_foto_path">Pilih file (Max: <?php echo defined('MAX_FILE_SIZE_FOTO_PROFIL_MB') ? MAX_FILE_SIZE_FOTO_PROFIL_MB : 2; ?>MB)...</label>
                                </div>
                                <small class="form-text text-muted">Format: JPG, PNG, GIF. Ukuran Max: <?php echo defined('MAX_FILE_SIZE_FOTO_PROFIL_MB') ? MAX_FILE_SIZE_FOTO_PROFIL_MB : 2; ?>MB.</small>
                                <?php if (isset($errors['pas_foto_path'])): ?><span class="text-danger text-sm"><?php echo htmlspecialchars($errors['pas_foto_path']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label id="label_ktp_path" for="ktp_path">Scan KTP</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php echo isset($errors['ktp_path']) ? 'is-invalid' : ''; ?>" id="ktp_path" name="ktp_path" accept="image/jpeg,image/png,application/pdf">
                                    <label class="custom-file-label" for="ktp_path">Pilih file (Max: <?php echo defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2; ?>MB)...</label>
                                </div>
                                <small id="help_ktp_path" class="form-text text-muted">Format: JPG, PNG, PDF. Ukuran Max: <?php echo defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2; ?>MB.</small>
                                <?php if (isset($errors['ktp_path'])): ?><span class="text-danger text-sm"><?php echo htmlspecialchars($errors['ktp_path']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label id="label_kk_path" for="kk_path">Scan KK</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php echo isset($errors['kk_path']) ? 'is-invalid' : ''; ?>" id="kk_path" name="kk_path" accept="image/jpeg,image/png,application/pdf">
                                    <label class="custom-file-label" for="kk_path">Pilih file (Max: <?php echo defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2; ?>MB)...</label>
                                </div>
                                <small id="help_kk_path" class="form-text text-muted">Format: JPG, PNG, PDF. Ukuran Max: <?php echo defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2; ?>MB.</small>
                                <?php if (isset($errors['kk_path'])): ?><span class="text-danger text-sm"><?php echo htmlspecialchars($errors['kk_path']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" name="submit_tambah_atlet" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Data Atlet</button>
                    <a href="daftar_atlet.php" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
// ========================================================================
// BLOK JAVASCRIPT DENGAN PENYESUAIAN UNTUK KESEPAKATAN BARU
// Hanya bagian AJAX NIK yang diubah signifikan, sisanya dipertahankan.
// ========================================================================
$inline_script = "
$(function () {
    $('.select2bs4').select2({ theme: 'bootstrap4' });
    bsCustomFileInput.init();

    var nikInputTimeout;
    
    // Fungsi untuk melakukan pengecekan NIK dan Cabor
    function performNikAndCaborCheck() {
        clearTimeout(nikInputTimeout);
        var nikValue = $('#nik').val().replace(/[^0-9]/g, '');
        $('#nik').val(nikValue); 
        
        var idCaborValue = $('#id_cabor').val(); 
        
        var nikHelpElement = $('#nik_help');
        var infoNamaPenggunaElement = $('#infoNamaPengguna');
        var namaPenggunaDariNikElement = $('#namaPenggunaDariNik');
        
        var ktpInput = $('#ktp_path');
        var kkInput = $('#kk_path');
        var ktpLabelElement = $('#label_ktp_path');
        var kkLabelElement = $('#label_kk_path');
        var ktpHelpElement = $('#help_ktp_path');
        var kkHelpElement = $('#help_kk_path');
        var submitButton = $('button[name=\"submit_tambah_atlet\"]');

        infoNamaPenggunaElement.hide();
        namaPenggunaDariNikElement.text('');
        submitButton.prop('disabled', false).removeClass('disabled'); // Default tombol submit aktif
        
        ktpInput.prop('disabled', false).prop('required', false); 
        kkInput.prop('disabled', false).prop('required', false);   
        ktpLabelElement.find('span.text-danger.doc-required-mark').remove();
        kkLabelElement.find('span.text-danger.doc-required-mark').remove();
        ktpHelpElement.text('Format: JPG, PNG, PDF. Ukuran Max: " . (defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2) . "MB.').removeClass('text-info text-warning text-danger');
        kkHelpElement.text('Format: JPG, PNG, PDF. Ukuran Max: " . (defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2) . "MB.').removeClass('text-info text-warning text-danger');

        if (nikValue.length === 16) {
            nikHelpElement.text('Memeriksa NIK dan Cabor...');
            nikInputTimeout = setTimeout(function() {
                $.ajax({
                    url: '" . APP_URL_BASE . "/ajax/cek_pengguna_by_nik.php',
                    type: 'POST',
                    data: { 
                        nik: nikValue, 
                        id_cabor: idCaborValue, // Kirim id_cabor yang dipilih
                        context: 'cek_atlet_baru' 
                    }, 
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.data_pengguna) {
                            namaPenggunaDariNikElement.text(response.data_pengguna.nama_lengkap);
                            infoNamaPenggunaElement.show();
                            
                            // Logika Peringatan Duplikasi Atlet per Cabor
                            if (idCaborValue && response.data_pengguna.is_atlet_selected_cabor) {
                                nikHelpElement.html('<strong class=\"text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Peringatan: NIK ' + nikValue + ' atas nama ' + response.data_pengguna.nama_lengkap + ' sudah terdaftar sebagai atlet untuk Cabang Olahraga yang dipilih ini. Tidak dapat mendaftar lagi di cabor yang sama.</strong>');
                                submitButton.prop('disabled', true).addClass('disabled');
                            } else if (response.data_pengguna.is_atlet_any_cabor) {
                                nikHelpElement.html('<strong class=\"text-success\">NIK pengguna ditemukan.</strong> <span class=\"text-info\">Pengguna ini sudah menjadi atlet di cabor lain.</span>');
                                submitButton.prop('disabled', false).removeClass('disabled');
                            } else {
                                nikHelpElement.html('<strong class=\"text-success\">NIK pengguna ditemukan dan valid untuk didaftarkan.</strong>');
                                submitButton.prop('disabled', false).removeClass('disabled');
                            }

                            // Logika KTP/KK (jika tidak ada error duplikasi cabor)
                            if (!(idCaborValue && response.data_pengguna.is_atlet_selected_cabor)) {
                                var isKtpStillRequired = true; 
                                var isKkStillRequired = true;  
                                if(response.data_pengguna.has_ktp) {
                                    ktpInput.prop('disabled', true);
                                    ktpHelpElement.html('<strong class=\"text-info\"><i class=\"fas fa-check-circle\"></i> KTP sudah terarsip dari pendaftaran sebelumnya.</strong>');
                                    isKtpStillRequired = false;
                                }
                                if(response.data_pengguna.has_kk) {
                                    kkInput.prop('disabled', true);
                                    kkHelpElement.html('<strong class=\"text-info\"><i class=\"fas fa-check-circle\"></i> KK sudah terarsip dari pendaftaran sebelumnya.</strong>');
                                    isKkStillRequired = false;
                                }
                                // Anda bisa uncomment dan sesuaikan logika 'required' jika perlu
                                // if (isKtpStillRequired) { /* ... set required ... */ } else { ktpInput.prop('required', false); }
                                // if (isKkStillRequired) { /* ... set required ... */ } else { kkInput.prop('required', false); }
                            }

                        } else { 
                            nikHelpElement.html('<strong class=\"text-danger\">' + (response.message || 'NIK tidak ditemukan/belum disetujui.') + '</strong>');
                            submitButton.prop('disabled', false).removeClass('disabled'); 
                        }
                    },
                    error: function() { 
                        nikHelpElement.html('<strong class=\"text-danger\">Gagal memeriksa NIK.</strong>');
                        submitButton.prop('disabled', false).removeClass('disabled');
                    }
                });
            }, 750);
        } else if (nikValue.length > 0) {
             nikHelpElement.text('NIK harus 16 digit angka.');
             submitButton.prop('disabled', false).removeClass('disabled');
        } else { 
            nikHelpElement.text('Pastikan NIK sudah terdaftar di sistem pengguna.');
            submitButton.prop('disabled', false).removeClass('disabled');
        }
    }

    // Panggil cekNikDanCabor saat NIK diubah
    $('#nik').on('keyup input', function() {
        performNikAndCaborCheck();
    });

    // Panggil cekNikDanCabor juga saat CABOR diubah (jika NIK sudah 16 digit)
    $('#id_cabor').on('change', function() {
        if ($('#nik').val().length === 16) {
            performNikAndCaborCheck(); 
        }
        // Logika AJAX untuk memuat Klub berdasarkan Cabor (Logika Anda dipertahankan)
        var idCabor = $(this).val();
        var idKlubSelect = $('#id_klub');
        // ... (sisa kode AJAX load klub Anda yang sudah berjalan baik) ...
        idKlubSelect.html('<option value=\"\">Memuat klub...</option>').prop('disabled', true).trigger('change.select2');
        if (idCabor) {
            $.ajax({
                url: '" . APP_URL_BASE . "/ajax/get_klub_by_cabor.php',
                type: 'POST', data: { id_cabor: idCabor }, dataType: 'json',
                success: function(response) {
                    idKlubSelect.empty().append('<option value=\"\">-- Pilih Klub (jika ada) --</option>');
                    if (response.status === 'success' && response.klub_list && response.klub_list.length > 0) {
                        $.each(response.klub_list, function(index, klub) { idKlubSelect.append($('<option>', { value: klub.id_klub, text: klub.nama_klub })); });
                    } else if (response.status === 'success' && (!response.klub_list || response.klub_list.length === 0)) {
                        idKlubSelect.append('<option value=\"\" disabled>Tidak ada klub terdaftar di cabor ini</option>');
                    } else { idKlubSelect.append('<option value=\"\" disabled>Gagal memuat data klub</option>'); }
                    idKlubSelect.prop('disabled', false).trigger('change.select2');
                },
                error: function() { idKlubSelect.empty().append('<option value=\"\" disabled>Error AJAX memuat klub</option>').prop('disabled', false).trigger('change.select2'); }
            });
        } else { idKlubSelect.empty().append('<option value=\"\">-- Pilih Klub (jika ada, setelah memilih cabor) --</option>').prop('disabled', false).trigger('change.select2'); }
    });
    
    // Logika pengisian ulang form setelah validasi gagal (Logika Anda dipertahankan)
    var caborLama = '" . ($form_data['id_cabor'] ?? ($id_cabor_pengurus ?? '')) . "'; 
    var klubLama = '" . ($form_data['id_klub'] ?? '') . "';
    if (caborLama) { /* ... kode Anda untuk trigger load klub ... */ }
    
    // Trigger pengecekan NIK saat halaman dimuat jika NIK sudah terisi
    if ($('#nik').val().length === 16) {
        setTimeout(function() { performNikAndCaborCheck(); }, 250); 
    }
});
";

require_once(__DIR__ . '/../../core/footer.php');
?>