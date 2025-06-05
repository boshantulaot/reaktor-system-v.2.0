<?php
// File: reaktorsystem/admin/roles/edit_anggota.php
$page_title = "Edit Peran Anggota";

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

$id_anggota_to_edit_form = null;
$anggota_data_to_edit = null;

if (isset($_GET['id_anggota']) && filter_var($_GET['id_anggota'], FILTER_VALIDATE_INT) && (int)$_GET['id_anggota'] > 0) {
    $id_anggota_to_edit_form = (int)$_GET['id_anggota'];
    try {
        $stmt_anggota_edit = $pdo->prepare("
            SELECT ang.*, p.nama_lengkap, p.email
            FROM anggota ang
            JOIN pengguna p ON ang.nik = p.nik
            WHERE ang.id_anggota = :id_anggota
        ");
        $stmt_anggota_edit->bindParam(':id_anggota', $id_anggota_to_edit_form, PDO::PARAM_INT);
        $stmt_anggota_edit->execute();
        $anggota_data_to_edit = $stmt_anggota_edit->fetch(PDO::FETCH_ASSOC);

        if (!$anggota_data_to_edit) {
            $_SESSION['pesan_error_global'] = "Data Peran Anggota tidak ditemukan.";
            header("Location: daftar_anggota.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Form Edit Anggota - Gagal ambil data: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data peran anggota.";
        header("Location: daftar_anggota.php");
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "ID Peran Anggota tidak valid atau tidak disediakan.";
    header("Location: daftar_anggota.php");
    exit();
}

$cabor_options_anggota_edit_form = [];
try {
    $stmt_cabor_opt_ang_edit_form = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
    $cabor_options_anggota_edit_form = $stmt_cabor_opt_ang_edit_form->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Gagal ambil cabor untuk edit anggota: " . $e->getMessage()); }

// --- PENYESUAIAN: Pengambilan data form dari session ---
$form_data_agt_edit_sess = $_SESSION['form_data_anggota_edit'] ?? [];
$form_errors_agt_edit = $_SESSION['errors_anggota_edit'] ?? [];
$form_error_fields_agt_edit = $_SESSION['error_fields_anggota_edit'] ?? [];

unset($_SESSION['form_data_anggota_edit']);
unset($_SESSION['errors_anggota_edit']);
unset($_SESSION['error_fields_anggota_edit']);

// Inisialisasi nilai form
$val_nik_form_edit = $anggota_data_to_edit['nik']; // NIK tidak diubah
$val_nama_lengkap_form_edit = $anggota_data_to_edit['nama_lengkap'];
$val_jabatan_form_edit = $form_data_agt_edit_sess['jabatan'] ?? $anggota_data_to_edit['jabatan'];
$val_role_form_edit = $form_data_agt_edit_sess['role'] ?? $anggota_data_to_edit['role'];
$val_id_cabor_form_edit = $form_data_agt_edit_sess['id_cabor'] ?? $anggota_data_to_edit['id_cabor'];
$val_tingkat_pengurus_form_edit = $form_data_agt_edit_sess['tingkat_pengurus'] ?? $anggota_data_to_edit['tingkat_pengurus'];
$val_is_verified_form_edit = isset($form_data_agt_edit_sess['is_verified']) ? (int)$form_data_agt_edit_sess['is_verified'] : (int)$anggota_data_to_edit['is_verified'];

// Logika untuk NIK Super Admin Utama (misalnya, diambil dari konstanta atau setting)
define('NIK_SUPER_ADMIN_UTAMA', '0000000000000001'); // Ganti dengan NIK SA utama Anda jika berbeda
$is_editing_main_super_admin = ($val_nik_form_edit == NIK_SUPER_ADMIN_UTAMA && $val_role_form_edit == 'super_admin');
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-warning shadow mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-edit mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                    </div>
                    <form id="formEditAnggota" action="proses_edit_anggota.php" method="POST">
                        <input type="hidden" name="id_anggota" value="<?php echo htmlspecialchars($id_anggota_to_edit_form); ?>">
                        <input type="hidden" name="nik" value="<?php echo htmlspecialchars($val_nik_form_edit); ?>">
                        
                        <div class="card-body">
                            <?php if (!empty($form_errors_agt_edit)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Mengupdate Data!</h5>
                                    <ul> <?php foreach ($form_errors_agt_edit as $err_item_agt_e): ?> <li><?php echo htmlspecialchars($err_item_agt_e); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-warning"><i class="fas fa-user-check mr-1"></i> Informasi Pengguna & Peran</h5>
                            <div class="form-group">
                                <label>Pengguna (NIK - Nama):</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($val_nik_form_edit . ' - ' . $val_nama_lengkap_form_edit); ?>" readonly>
                                <small class="form-text text-muted">NIK Pengguna tidak dapat diubah dari halaman ini.</small>
                            </div>

                            <div class="form-group">
                                <label for="role_edit_anggota">Peran Sistem <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('role', $form_error_fields_agt_edit)) echo 'is-invalid'; ?>" id="role_edit_anggota" name="role" required style="width: 100%;" data-placeholder="-- Pilih Peran Sistem --" <?php if ($is_editing_main_super_admin) echo 'disabled'; ?>>
                                    <option value=""></option>
                                    <option value="admin_koni" <?php echo ($val_role_form_edit == 'admin_koni') ? 'selected' : ''; ?>>Admin KONI</option>
                                    <option value="pengurus_cabor" <?php echo ($val_role_form_edit == 'pengurus_cabor') ? 'selected' : ''; ?>>Pengurus Cabang Olahraga</option>
                                    <option value="view_only" <?php echo ($val_role_form_edit == 'view_only') ? 'selected' : ''; ?>>View Only</option>
                                    <option value="guest" <?php echo ($val_role_form_edit == 'guest') ? 'selected' : ''; ?>>Guest</option>
                                    <?php if ($is_editing_main_super_admin): ?>
                                        <option value="super_admin" selected>Super Admin</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($is_editing_main_super_admin): ?>
                                    <input type="hidden" name="role" value="super_admin">
                                    <small class="form-text text-muted">Peran Super Admin utama tidak dapat diubah.</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group" id="form_group_cabor_select_anggota_edit" style="<?php echo ($val_role_form_edit == 'pengurus_cabor') ? '' : 'display:none;'; ?>">
                                <label for="id_cabor_edit_anggota">Cabang Olahraga Terkait <span class="text-danger" id="cabor_required_star_anggota_edit" style="<?php echo ($val_role_form_edit == 'pengurus_cabor') ? '' : 'display:none;'; ?>">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('id_cabor', $form_error_fields_agt_edit)) echo 'is-invalid'; ?>" id="id_cabor_edit_anggota" name="id_cabor" style="width: 100%;" data-placeholder="-- Pilih Cabang Olahraga --">
                                    <option value=""></option>
                                    <?php foreach ($cabor_options_anggota_edit_form as $cabor_item_opt_e): ?>
                                        <option value="<?php echo htmlspecialchars($cabor_item_opt_e['id_cabor']); ?>" <?php echo ($val_id_cabor_form_edit == $cabor_item_opt_e['id_cabor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cabor_item_opt_e['nama_cabor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Wajib diisi jika peran adalah Pengurus Cabang Olahraga.</small>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-warning"><i class="fas fa-briefcase mr-1"></i> Detail Jabatan & Tingkat</h5>
                            <div class="form-group">
                                <label for="jabatan_edit_anggota">Jabatan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php if(in_array('jabatan', $form_error_fields_agt_edit)) echo 'is-invalid'; ?>" id="jabatan_edit_anggota" name="jabatan" placeholder="Contoh: Sekretaris Umum KONI" value="<?php echo htmlspecialchars($val_jabatan_form_edit); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="tingkat_pengurus_edit_anggota">Tingkat Pengurus <span class="text-danger">*</span></label>
                                <select class="form-control select2bs4 <?php if(in_array('tingkat_pengurus', $form_error_fields_agt_edit)) echo 'is-invalid'; ?>" id="tingkat_pengurus_edit_anggota" name="tingkat_pengurus" required style="width: 100%;" data-placeholder="-- Pilih Tingkat --">
                                    <option value="Kabupaten" <?php echo ($val_tingkat_pengurus_form_edit == 'Kabupaten') ? 'selected' : ''; ?>>Kabupaten</option>
                                    <option value="Provinsi" <?php echo ($val_tingkat_pengurus_form_edit == 'Provinsi') ? 'selected' : ''; ?>>Provinsi</option>
                                    <option value="Pusat" <?php echo ($val_tingkat_pengurus_form_edit == 'Pusat') ? 'selected' : ''; ?>>Pusat</option>
                                </select>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-warning"><i class="fas fa-user-check mr-1"></i> Verifikasi</h5>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_verified_edit_anggota" name="is_verified" value="1" <?php echo ($val_is_verified_form_edit == 1) ? 'checked' : ''; ?> <?php if($is_editing_main_super_admin && $val_is_verified_form_edit == 1) echo 'disabled'; /* SA Utama tidak bisa di-unverify */ ?> >
                                    <label class="custom-control-label" for="is_verified_edit_anggota">Peran Terverifikasi?</label>
                                </div>
                                <?php if($is_editing_main_super_admin && $val_is_verified_form_edit == 1): ?>
                                    <input type="hidden" name="is_verified" value="1">
                                    <small class="form-text text-muted">Status verifikasi Super Admin utama tidak dapat diubah.</small>
                                <?php else: ?>
                                    <small class="form-text text-muted">Jika dicentang, peran ini aktif dan terverifikasi.</small>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_anggota" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Update Peran</button>
                            <a href="daftar_anggota.php" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
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
  if (typeof $.fn.select2 === 'function') {
    $('#role_edit_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Peran Sistem --', allowClear: true });
    $('#id_cabor_edit_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Cabang Olahraga --', allowClear: true });
    $('#tingkat_pengurus_edit_anggota.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Tingkat --', allowClear: true });
  }

  function toggleCaborFieldEditBasedOnRole() {
    var selectedRole = $('#role_edit_anggota').val();
    var caborGroup = $('#form_group_cabor_select_anggota_edit');
    var caborSelect = $('#id_cabor_edit_anggota');
    var caborStar = $('#cabor_required_star_anggota_edit'); // Asumsi ID ini ada di span bintang

    if (selectedRole === 'pengurus_cabor') {
        caborGroup.slideDown();
        caborSelect.prop('required', true);
        caborStar.show();
    } else {
        caborGroup.slideUp();
        caborSelect.prop('required', false);
        // Jangan reset value saat edit, biarkan nilai lama jika peran diubah dari pengcab,
        // proses_edit_anggota.php akan menangani apakah id_cabor perlu di-NULL-kan.
        // caborSelect.val(null).trigger('change.select2'); 
        caborStar.hide();
    }
  }
  toggleCaborFieldEditBasedOnRole(); // Panggil saat load
  $('#role_edit_anggota').on('change', function () {
    toggleCaborFieldEditBasedOnRole();
  });

  $('#formEditAnggota').submit(function(e) {
    // ... (Logika validasi frontend mirip form tambah, disesuaikan untuk ID field edit) ...
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>