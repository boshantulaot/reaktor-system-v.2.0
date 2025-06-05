<?php
// File: modules/pelatih/tambah_pelatih.php

$page_title = "Tambah Pelatih Baru (Peran Cabor)";
$current_page_is_tambah_pelatih = true; 

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/bs-custom-file-input/bs-custom-file-input.min.js',
];

require_once(__DIR__ . '/../../core/header.php'); 

// Konstanta ukuran file
if (!defined('MAX_FILE_SIZE_FOTO_INDIVIDU_MB')) { define('MAX_FILE_SIZE_FOTO_INDIVIDU_MB', 1); }
if (!defined('MAX_FILE_SIZE_FOTO_INDIVIDU_BYTES')) { define('MAX_FILE_SIZE_FOTO_INDIVIDU_BYTES', MAX_FILE_SIZE_FOTO_INDIVIDU_MB * 1024 * 1024); }
if (!defined('MAX_FILE_SIZE_KTP_KK_MB')) { define('MAX_FILE_SIZE_KTP_KK_MB', 2); } // Asumsi sama untuk KTP/KK

// Pengecekan Sesi & Peran Pengguna
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo)) { /* ... error handling ... */ exit(); }
if (!in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) { /* ... error handling ... */ exit(); }


$id_cabor_pengurus_context = null; 
if ($user_role_utama === 'pengurus_cabor') {
    $id_cabor_pengurus_context = $_SESSION['id_cabor_pengurus_utama'] ?? null;
    if (!$id_cabor_pengurus_context) { /* ... error handling ... */ exit(); }
}

$cabor_list_for_pelatih_form = [];
try {
    $query_cabor_pelatih = "SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC";
    if ($id_cabor_pengurus_context) {
        $query_cabor_pelatih = "SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor_pengurus AND status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC";
        $stmt_cabor_pelatih = $pdo->prepare($query_cabor_pelatih);
        $stmt_cabor_pelatih->execute([':id_cabor_pengurus' => $id_cabor_pengurus_context]);
    } else {
        $stmt_cabor_pelatih = $pdo->query($query_cabor_pelatih);
    }
    if($stmt_cabor_pelatih) { $cabor_list_for_pelatih_form = $stmt_cabor_pelatih->fetchAll(PDO::FETCH_ASSOC); }
} catch (PDOException $e) { /* ... error log ... */ }

$form_data = $_SESSION['form_data_tambah_pelatih'] ?? []; 
$errors = $_SESSION['errors_tambah_pelatih'] ?? [];
$form_error_fields = $_SESSION['error_fields_tambah_pelatih'] ?? [];
unset($_SESSION['form_data_tambah_pelatih'], $_SESSION['errors_tambah_pelatih'], $_SESSION['error_fields_tambah_pelatih']);
?>

<section class="content">
    <div class="container-fluid">
        <?php /* ... Pesan Feedback ... */ ?>

        <div class="card card-outline card-primary"> 
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chalkboard-teacher mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                <div class="card-tools">
                    <a href="daftar_pelatih.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Pelatih</a>
                </div>
            </div>
            <form action="proses_tambah_pelatih.php" method="post" enctype="multipart/form-data" id="formTambahPelatih">
                <div class="card-body">
                    <h5 class="mt-1 mb-3 text-primary"><i class="fas fa-user-check mr-1"></i> Identifikasi Pelatih</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nik_form_input_pelatih">NIK Calon Pelatih <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(in_array('nik', $form_error_fields)) echo 'is-invalid'; ?>" id="nik_form_input_pelatih" name="nik" value="<?php echo htmlspecialchars($form_data['nik'] ?? ''); ?>" placeholder="Masukkan NIK Pengguna (16 digit)" maxlength="16" required>
                                <small id="nik_pelatih_help" class="form-text text-muted">Pastikan NIK sudah terdaftar & akunnya aktif.</small>
                                <div id="infoNamaPenggunaPelatih" class="mt-2" style="display:none;">
                                    <span class="badge badge-info p-2">Nama Pengguna: <strong id="namaPenggunaDariNikPelatih"></strong></span>
                                </div>
                                <?php /* ... error NIK ... */ ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_cabor_form_pelatih">Cabang Olahraga <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('id_cabor', $form_error_fields)) echo 'is-invalid'; ?>" id="id_cabor_form_pelatih" name="id_cabor" style="width: 100%;" required <?php if ($id_cabor_pengurus_context && count($cabor_list_for_pelatih_form) === 1) echo 'disabled'; ?>>
                                    <option value="">-- Pilih Cabang Olahraga --</option>
                                    <?php foreach ($cabor_list_for_pelatih_form as $cabor_item_form_pelatih): ?>
                                        <option value="<?php echo $cabor_item_form_pelatih['id_cabor']; ?>" <?php echo (($form_data['id_cabor'] ?? ($id_cabor_pengurus_context ?? '')) == $cabor_item_form_pelatih['id_cabor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cabor_item_form_pelatih['nama_cabor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($id_cabor_pengurus_context && count($cabor_list_for_pelatih_form) === 1): ?>
                                    <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($id_cabor_pengurus_context); ?>">
                                <?php endif; ?>
                                <div id="cabor_pelatih_feedback" class="mt-1"></div>
                                <?php /* ... error Cabor ... */ ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_klub_form_pelatih">Klub Afiliasi (Opsional)</label>
                                <select class="form-control select2bs4 <?php if(in_array('id_klub_afiliasi', $form_error_fields)) echo 'is-invalid'; ?>" id="id_klub_form_pelatih" name="id_klub_afiliasi" style="width: 100%;">
                                    <option value="">-- Pilih Klub (jika ada, setelah memilih cabor) --</option>
                                </select>
                                <?php /* ... error Klub ... */ ?>
                            </div>
                        </div>
                        {/* Kontak Alternatif DIHAPUS karena tidak ada di tabel pelatih Anda yang baru */}
                    </div>
                    
                    <hr>
                    <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-file-upload mr-1"></i> Upload Dokumen & Foto Pelatih</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pas_foto_pelatih_form">Pas Foto Pelatih (Spesifik Cabor)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(in_array('pas_foto_pelatih', $form_error_fields)) echo 'is-invalid'; ?>" id="pas_foto_pelatih_form" name="pas_foto_pelatih" accept="image/jpeg,image/png,image/gif">
                                    <label class="custom-file-label" for="pas_foto_pelatih_form">Pilih file (Max: <?php echo MAX_FILE_SIZE_FOTO_INDIVIDU_MB; ?>MB)...</label>
                                </div>
                                <small class="form-text text-muted">Format: JPG, PNG, GIF.</small>
                                <?php /* ... error pas_foto_pelatih ... */ ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label id="label_ktp_path_pelatih" for="ktp_path_pelatih_form">Scan KTP</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(in_array('ktp_path', $form_error_fields)) echo 'is-invalid'; ?>" id="ktp_path_pelatih_form" name="ktp_path" accept="image/jpeg,image/png,application/pdf">
                                    <label class="custom-file-label" for="ktp_path_pelatih_form">Pilih file (Max: <?php echo MAX_FILE_SIZE_KTP_KK_MB; ?>MB)...</label>
                                </div>
                                <small id="help_ktp_path_pelatih" class="form-text text-muted">Format: JPG, PNG, PDF.</small>
                                <?php /* ... error ktp_path ... */ ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label id="label_kk_path_pelatih" for="kk_path_pelatih_form">Scan KK</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(in_array('kk_path', $form_error_fields)) echo 'is-invalid'; ?>" id="kk_path_pelatih_form" name="kk_path" accept="image/jpeg,image/png,application/pdf">
                                    <label class="custom-file-label" for="kk_path_pelatih_form">Pilih file (Max: <?php echo MAX_FILE_SIZE_KTP_KK_MB; ?>MB)...</label>
                                </div>
                                <small id="help_kk_path_pelatih" class="form-text text-muted">Format: JPG, PNG, PDF.</small>
                                <?php /* ... error kk_path ... */ ?>
                            </div>
                        </div>
                    </div>
                     <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" name="submit_tambah_pelatih" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Daftarkan Peran Pelatih
                    </button>
                    <a href="daftar_pelatih.php" class="btn btn-secondary">
                        <i class="fas fa-times mr-1"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
// JavaScript SAMA PERSIS dengan tambah_atlet.php, hanya disesuaikan ID elemen dan konteks AJAX
$inline_script = "
$(function () {
    // Inisialisasi Select2
    $('.select2bs4').select2({ theme: 'bootstrap4', placeholder: $(this).data('placeholder') || '-- Pilih --', allowClear: true });
    // Inisialisasi bsCustomFileInput
    if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
        bsCustomFileInput.init();
    }

    var nikPelatihTimeoutForm; 
    
    function performNikPelatihCheck() { 
        clearTimeout(nikPelatihTimeoutForm);
        var nikValPelatih = $('#nik_form_input_pelatih').val().replace(/[^0-9]/g, '');
        $('#nik_form_input_pelatih').val(nikValPelatih);
        
        var caborValPelatih = $('#id_cabor_form_pelatih').val(); 
        
        var nikHelpPelatihEl = $('#nik_pelatih_help');
        var infoNamaPelatihEl = $('#infoNamaPenggunaPelatih');
        var namaPenggunaPelatihEl = $('#namaPenggunaDariNikPelatih');
        var submitBtnPelatih = $('button[name=\"submit_tambah_pelatih\"]');

        var ktpInputPelatih = $('#ktp_path_pelatih_form');
        var kkInputPelatih = $('#kk_path_pelatih_form');
        var ktpLabelPelatih = $('#label_ktp_path_pelatih');
        var kkLabelPelatih = $('#label_kk_path_pelatih');
        var ktpHelpPelatih = $('#help_ktp_path_pelatih');
        var kkHelpPelatih = $('#help_kk_path_pelatih');

        infoNamaPelatihEl.hide(); namaPenggunaPelatihEl.text('');
        submitBtnPelatih.prop('disabled', false).removeClass('disabled');
        
        ktpInputPelatih.prop('disabled', false); //.prop('required', false); // Kewajiban diatur oleh backend / kebutuhan
        kkInputPelatih.prop('disabled', false); //.prop('required', false);   
        ktpLabelPelatih.find('span.text-danger.doc-required-mark').remove();
        kkLabelPelatih.find('span.text-danger.doc-required-mark').remove();
        ktpHelpPelatih.text('Format: JPG, PNG, PDF. Ukuran Max: " . (defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2) . "MB.').removeClass('text-info text-warning text-danger');
        kkHelpPelatih.text('Format: JPG, PNG, PDF. Ukuran Max: " . (defined('MAX_FILE_SIZE_KTP_KK_MB') ? MAX_FILE_SIZE_KTP_KK_MB : 2) . "MB.').removeClass('text-info text-warning text-danger');

        if (nikValPelatih.length === 16) {
            nikHelpPelatihEl.html('<em class=\"text-muted\">Memeriksa NIK & Cabor... <i class=\"fas fa-spinner fa-spin\"></i></em>');
            nikPelatihTimeoutForm = setTimeout(function() {
                $.ajax({
                    url: '" . APP_URL_BASE . "/ajax/cek_pengguna_by_nik.php',
                    type: 'POST',
                    data: { nik: nikValPelatih, id_cabor: caborValPelatih, context: 'cek_pelatih_baru_per_cabor' }, 
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.data_pengguna) {
                            namaPenggunaPelatihEl.text(response.data_pengguna.nama_lengkap);
                            infoNamaPelatihEl.show();
                            var mainMsgPelatih = '<strong class=\"text-success\"><i class=\"fas fa-check-circle\"></i> NIK Pengguna: ' + response.data_pengguna.nama_lengkap + '.</strong> ';
                            var caborMsgPelatih = ''; var docMsgPelatih = '';
                            if (caborValPelatih && response.data_pengguna.is_role_in_selected_cabor) {
                                caborMsgPelatih = '<br><strong class=\"text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Sudah terdaftar sebagai pelatih di Cabor ini.</strong>';
                                submitBtnPelatih.prop('disabled', true).addClass('disabled');
                            } else if (response.data_pengguna.is_role_in_any_cabor) {
                                caborMsgPelatih = '<br><span class=\"text-info\">Sudah menjadi pelatih di cabor lain (KTP/KK mungkin sudah ada).</span>';
                            }
                            if (!(caborValPelatih && response.data_pengguna.is_role_in_selected_cabor)) {
                                if(response.data_pengguna.has_ktp) { ktpInputPelatih.prop('disabled', true); ktpHelpPelatih.html('<strong class=\"text-info\"><i class=\"fas fa-check-circle\"></i> KTP sudah terarsip.</strong>'); }
                                if(response.data_pengguna.has_kk) { kkInputPelatih.prop('disabled', true); kkHelpPelatih.html('<strong class=\"text-info\"><i class=\"fas fa-check-circle\"></i> KK sudah terarsip.</strong>'); }
                            }
                            nikHelpPelatihEl.html(mainMsgPelatih + caborMsgPelatih);
                        } else { 
                            nikHelpPelatihEl.html('<strong class=\"text-danger\">' + (response.message || 'NIK tidak ditemukan/belum disetujui.') + '</strong>');
                            submitBtnPelatih.prop('disabled', true).addClass('disabled'); 
                        }
                    },
                    error: function() { nikHelpPelatihEl.html('<strong class=\"text-danger\">Gagal memeriksa NIK.</strong>'); submitBtnPelatih.prop('disabled', true).addClass('disabled');}
                });
            }, 750);
        } else if (nikValPelatih.length > 0) {
             nikHelpPelatihEl.text('NIK harus 16 digit angka.');
             submitBtnPelatih.prop('disabled', true).addClass('disabled');
        } else { 
            nikHelpPelatihEl.text('Pastikan NIK sudah terdaftar di sistem pengguna.');
             submitBtnPelatih.prop('disabled', true).addClass('disabled');
        }
    }

    $('#nik_form_input_pelatih').on('keyup input', function() { performNikPelatihCheck(); });
    $('#id_cabor_form_pelatih').on('change', function() {
        if ($('#nik_form_input_pelatih').val().length === 16) { performNikPelatihCheck(); }
        var idCabor = $(this).val();
        var idKlubSelect = $('#id_klub_form_pelatih');
        idKlubSelect.html('<option value=\"\">Memuat klub...</option>').prop('disabled', true).trigger('change.select2');
        if (idCabor) {
            $.ajax({
                url: '" . APP_URL_BASE . "/ajax/get_klub_by_cabor.php',
                type: 'POST', data: { id_cabor: idCabor }, dataType: 'json',
                success: function(response) {
                    idKlubSelect.empty().append('<option value=\"\">-- Pilih Klub (opsional) --</option>');
                    if (response.status === 'success' && response.klub_list && response.klub_list.length > 0) {
                        $.each(response.klub_list, function(index, klub) { idKlubSelect.append($('<option>', { value: klub.id_klub, text: klub.nama_klub })); });
                    } else { idKlubSelect.append('<option value=\"\" disabled>Tidak ada klub di cabor ini</option>'); }
                    idKlubSelect.prop('disabled', false).trigger('change.select2');
                    var cL = '" . ($form_data['id_cabor'] ?? ($id_cabor_pengurus_context ?? '')) . "';
                    var kL = '" . ($form_data['id_klub_afiliasi'] ?? '') . "';
                    if (idCabor === cL && kL) { if ($('#id_klub_form_pelatih option[value=\"' + kL + '\"]').length > 0) { $('#id_klub_form_pelatih').val(kL).trigger('change.select2');}}
                },
                error: function() { idKlubSelect.empty().append('<option value=\"\" disabled>Error AJAX memuat klub</option>').prop('disabled', false).trigger('change.select2'); }
            });
        } else { idKlubSelect.empty().append('<option value=\"\">-- Pilih Klub (opsional) --</option>').prop('disabled', false).trigger('change.select2'); }
    });
    
    var caborLamaInit = '" . ($form_data['id_cabor'] ?? ($id_cabor_pengurus_context ?? '')) . "'; 
    if (caborLamaInit) { $('#id_cabor_form_pelatih').val(caborLamaInit).trigger('change');  }
    if ($('#nik_form_input_pelatih').val().length === 16) { setTimeout(function() { performNikPelatihCheck(); }, 350); }
});
";

require_once(__DIR__ . '/../../core/footer.php');
?>