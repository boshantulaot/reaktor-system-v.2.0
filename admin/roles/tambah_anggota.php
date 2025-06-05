<?php
// File: reaktorsystem/admin/roles/tambah_anggota.php
$page_title = "Tambah Peran Anggota Baru";

// --- Definisi Aset CSS & JS ---
$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Pengecekan sesi & peran pengguna (Hanya Super Admin)
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo) || $user_role_utama != 'super_admin') { 
    $_SESSION['pesan_error_global'] = "Akses ditolak atau sesi tidak valid.";
    if (!headers_sent()) { header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php"); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error: Akses ditolak.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

// Ambil daftar pengguna yang AKTIF dan BELUM memiliki entri di tabel 'anggota'
$pengguna_options_agt = [];
try {
    // ===== PERUBAHAN QUERY DI SINI =====
    $stmt_pengguna_opt_agt = $pdo->query(
        "SELECT p.nik, p.nama_lengkap, p.email
         FROM pengguna p
         WHERE p.is_approved = 1
         AND p.nik NOT IN (SELECT DISTINCT ang.nik FROM anggota ang) 
         ORDER BY p.nama_lengkap ASC"
    );
    // ===================================
    $pengguna_options_agt = $stmt_pengguna_opt_agt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Gagal ambil pengguna untuk tambah anggota: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Gagal memuat daftar pengguna yang dapat diberi peran.";
}

$cabor_options_anggota_form = [];
try {
    $stmt_cabor_opt_ang_form = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
    $cabor_options_anggota_form = $stmt_cabor_opt_ang_form->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Gagal ambil cabor untuk tambah anggota: " . $e->getMessage());}

$form_data_agt_tambah = $_SESSION['form_data_anggota_tambah'] ?? [];
$form_errors_agt_tambah = $_SESSION['errors_anggota_tambah'] ?? [];
$form_error_fields_agt_tambah = $_SESSION['error_fields_anggota_tambah'] ?? [];

unset($_SESSION['form_data_anggota_tambah']);
unset($_SESSION['errors_anggota_tambah']);
unset($_SESSION['error_fields_anggota_tambah']);

$val_nik_anggota_form = $form_data_agt_tambah['nik'] ?? '';
$val_jabatan_form = $form_data_agt_tambah['jabatan'] ?? '';
$val_role_form = $form_data_agt_tambah['role'] ?? '';
$val_id_cabor_form = $form_data_agt_tambah['id_cabor'] ?? '';
$val_tingkat_pengurus_form = $form_data_agt_tambah['tingkat_pengurus'] ?? 'Kabupaten';
$val_is_verified_form = isset($form_data_agt_tambah['is_verified']) ? (int)$form_data_agt_tambah['is_verified'] : 1;
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-primary shadow mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-tag mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formTambahAnggota" action="proses_tambah_anggota.php" method="POST">
                        <div class="card-body">
                            <?php if (!empty($form_errors_agt_tambah)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Menyimpan Data!</h5>
                                    <ul> <?php foreach ($form_errors_agt_tambah as $err_item_agt): ?> <li><?php echo htmlspecialchars($err_item_agt); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-primary"><i class="fas fa-user-check mr-1"></i> Pilih Pengguna & Peran</h5>
                            <div class="form-group">
                                <label for="nik_form_anggota">Pilih Pengguna (NIK) <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('nik', $form_error_fields_agt_tambah)) echo 'is-invalid'; ?>" id="nik_form_anggota" name="nik" required style="width: 100%;" data-placeholder="-- Cari & Pilih Pengguna --">
                                    <option value=""></option>
                                    <?php foreach ($pengguna_options_agt as $pgn_opt): ?>
                                        <option value="<?php echo htmlspecialchars($pgn_opt['nik']); ?>" <?php echo ($val_nik_anggota_form == $pgn_opt['nik']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pgn_opt['nama_lengkap'] . ' - ' . $pgn_opt['nik'] . ($pgn_opt['email'] ? ' (' . $pgn_opt['email'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($pengguna_options_agt)): ?>
                                    <small class="form-text text-warning">Semua pengguna aktif sudah memiliki peran atau tidak ada pengguna aktif yang tersedia.</small>
                                <?php else: ?>
                                    <small class="form-text text-muted">Hanya pengguna aktif yang belum memiliki peran di sistem ini yang tampil.</small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="role_form_anggota">Peran Sistem <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('role', $form_error_fields_agt_tambah)) echo 'is-invalid'; ?>" id="role_form_anggota" name="role" required style="width: 100%;" data-placeholder="-- Pilih Peran Sistem --">
                                    <option value=""></option>
                                    <option value="admin_koni" <?php echo ($val_role_form == 'admin_koni') ? 'selected' : ''; ?>>Admin KONI</option>
                                    <option value="pengurus_cabor" <?php echo ($val_role_form == 'pengurus_cabor') ? 'selected' : ''; ?>>Pengurus Cabang Olahraga</option>
                                    <option value="view_only" <?php echo ($val_role_form == 'view_only') ? 'selected' : ''; ?>>View Only</option>
                                    <option value="guest" <?php echo ($val_role_form == 'guest') ? 'selected' : ''; ?>>Guest</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="form_group_cabor_select_anggota" style="<?php echo ($val_role_form == 'pengurus_cabor') ? '' : 'display:none;'; ?>">
                                <label for="id_cabor_form_anggota">Cabang Olahraga Terkait <span class="text-danger" id="cabor_required_star_anggota_tambah" style="<?php echo ($val_role_form == 'pengurus_cabor') ? '' : 'display:none;'; ?>">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('id_cabor', $form_error_fields_agt_tambah)) echo 'is-invalid'; ?>" id="id_cabor_form_anggota" name="id_cabor" style="width: 100%;" data-placeholder="-- Pilih Cabang Olahraga --">
                                    <option value=""></option>
                                    <?php foreach ($cabor_options_anggota_form as $cabor_item_opt): ?>
                                        <option value="<?php echo htmlspecialchars($cabor_item_opt['id_cabor']); ?>" <?php echo ($val_id_cabor_form == $cabor_item_opt['id_cabor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cabor_item_opt['nama_cabor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Wajib diisi jika peran adalah Pengurus Cabang Olahraga.</small>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-briefcase mr-1"></i> Detail Jabatan & Tingkat</h5>
                            <div class="form-group">
                                <label for="jabatan_form_anggota">Jabatan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(in_array('jabatan', $form_error_fields_agt_tambah)) echo 'is-invalid'; ?>" id="jabatan_form_anggota" name="jabatan" placeholder="Contoh: Sekretaris Umum KONI, Ketua Pengcab Catur" value="<?php echo htmlspecialchars($val_jabatan_form); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="tingkat_pengurus_form_anggota">Tingkat Pengurus <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('tingkat_pengurus', $form_error_fields_agt_tambah)) echo 'is-invalid'; ?>" id="tingkat_pengurus_form_anggota" name="tingkat_pengurus" required style="width: 100%;" data-placeholder="-- Pilih Tingkat --">
                                    <option value="Kabupaten" <?php echo ($val_tingkat_pengurus_form == 'Kabupaten') ? 'selected' : ''; ?>>Kabupaten</option>
                                    <option value="Provinsi" <?php echo ($val_tingkat_pengurus_form == 'Provinsi') ? 'selected' : ''; ?>>Provinsi</option>
                                    <option value="Pusat" <?php echo ($val_tingkat_pengurus_form == 'Pusat') ? 'selected' : ''; ?>>Pusat</option>
                                </select>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-user-check mr-1"></i> Verifikasi</h5>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_verified_form_anggota" name="is_verified" value="1" <?php echo ($val_is_verified_form == 1) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="is_verified_form_anggota">Langsung Verifikasi Peran Ini?</label>
                                </div>
                                <small class="form-text text-muted">Jika dicentang, peran ini akan langsung aktif dan terverifikasi.</small>
                            </div>
                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_tambah_anggota" class="btn btn-primary"><i class="fas fa-user-tag mr-1"></i> Tambah Peran</button>
                            <a href="daftar_anggota.php" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Blok $inline_script tetap sama seperti versi sebelumnya, tidak ada perubahan logika JS
$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    bsCustomFileInput.init();
  }
  if (typeof $.fn.select2 === 'function') {
    $('#nik_form_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Cari & Pilih Pengguna --', allowClear: true });
    $('#role_form_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Peran Sistem --', allowClear: true });
    $('#id_cabor_form_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Cabang Olahraga --', allowClear: true });
    $('#tingkat_pengurus_form_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Tingkat --', allowClear: true });
  }
  function toggleCaborFieldBasedOnRole() {
    var selectedRole = $('#role_form_anggota').val();
    if (selectedRole === 'pengurus_cabor') {
        $('#form_group_cabor_select_anggota').slideDown();
        $('#id_cabor_form_anggota').prop('required', true);
        $('#cabor_required_star_anggota_tambah').show();
    } else {
        $('#form_group_cabor_select_anggota').slideUp();
        $('#id_cabor_form_anggota').prop('required', false).val(null).trigger('change.select2');
        $('#cabor_required_star_anggota_tambah').hide();
    }
  }
  toggleCaborFieldBasedOnRole();
  $('#role_form_anggota').on('change', function () { toggleCaborFieldBasedOnRole(); });
  $('#formTambahAnggota').submit(function(e) {
    let isValidAnggota = true; let focusFieldAnggota = null; $('.is-invalid').removeClass('is-invalid'); $('.select2-selection--single').removeClass('is-invalid'); 
    const requiredSelects = [ {id: 'nik_form_anggota', name: 'NIK Pengguna'}, {id: 'role_form_anggota', name: 'Peran Sistem'}, {id: 'tingkat_pengurus_form_anggota', name: 'Tingkat Pengurus'} ];
    requiredSelects.forEach(function(selectInfo) { let field = $('#' + selectInfo.id); if (field.val() === '' || field.val() === null) { if(!focusFieldAnggota) focusFieldAnggota = field; field.next('.select2-container').find('.select2-selection--single').addClass('is-invalid'); isValidAnggota = false; }});
    if ($('#jabatan_form_anggota').val().trim() === '') { if(!focusFieldAnggota) focusFieldAnggota = $('#jabatan_form_anggota'); $('#jabatan_form_anggota').addClass('is-invalid'); isValidAnggota = false; }
    if ($('#role_form_anggota').val() === 'pengurus_cabor' && ($('#id_cabor_form_anggota').val() === '' || $('#id_cabor_form_anggota').val() === null)) { if(!focusFieldAnggota) focusFieldAnggota = $('#id_cabor_form_anggota'); $('#id_cabor_form_anggota').next('.select2-container').find('.select2-selection--single').addClass('is-invalid'); isValidAnggota = false; }
    if (!isValidAnggota) { e.preventDefault(); if(focusFieldAnggota) { if (focusFieldAnggota.hasClass('select2bs4')) { $('html, body').animate({ scrollTop: focusFieldAnggota.next('.select2-container').offset().top - 70 }, 500); focusFieldAnggota.select2('open'); } else { focusFieldAnggota.focus(); if (!focusFieldAnggota.is(':visible') || focusFieldAnggota.offset().top < $(window).scrollTop() || focusFieldAnggota.offset().top + focusFieldAnggota.outerHeight() > $(window).scrollTop() + $(window).height()) { $('html, body').animate({ scrollTop: focusFieldAnggota.closest('.form-group').offset().top - 70 }, 500); } } } }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>