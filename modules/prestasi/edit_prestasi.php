<?php
// File: reaktorsystem/modules/prestasi/edit_prestasi.php

$page_title = "Edit Data Prestasi Atlet";

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php'); // header.php sudah include init_core.php

// ---- Definisi konstanta ukuran file (mengikuti standar) ----
if (!defined('MAX_FILE_SIZE_BUKTI_PRESTASI_MB')) { define('MAX_FILE_SIZE_BUKTI_PRESTASI_MB', 2); }
if (!defined('MAX_FILE_SIZE_BUKTI_PRESTASI_BYTES')) { define('MAX_FILE_SIZE_BUKTI_PRESTASI_BYTES', MAX_FILE_SIZE_BUKTI_PRESTASI_MB * 1024 * 1024); }

// Pengecekan Sesi & Peran Pengguna
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo)) { 
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah.";
    header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php");
    exit();
}

$id_prestasi_to_edit = null;
$prestasi_data = null;

if (isset($_GET['id_prestasi']) && filter_var($_GET['id_prestasi'], FILTER_VALIDATE_INT) && (int)$_GET['id_prestasi'] > 0) {
    $id_prestasi_to_edit = (int)$_GET['id_prestasi'];
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, p.nama_lengkap AS nama_atlet_prestasi, co.nama_cabor,
                   pengcab_app.nama_lengkap AS nama_approver_pengcab_prestasi, 
                   admin_app.nama_lengkap AS nama_approver_admin_prestasi,
                   editor.nama_lengkap AS nama_editor_terakhir_prestasi
            FROM prestasi pr
            JOIN pengguna p ON pr.nik = p.nik
            JOIN cabang_olahraga co ON pr.id_cabor = co.id_cabor
            LEFT JOIN pengguna pengcab_app ON pr.approved_by_nik_pengcab = pengcab_app.nik
            LEFT JOIN pengguna admin_app ON pr.approved_by_nik_admin = admin_app.nik
            LEFT JOIN pengguna editor ON pr.updated_by_nik = editor.nik
            WHERE pr.id_prestasi = :id_prestasi
        ");
        $stmt->bindParam(':id_prestasi', $id_prestasi_to_edit, PDO::PARAM_INT);
        $stmt->execute();
        $prestasi_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prestasi_data) {
            $_SESSION['pesan_error_global'] = "Data Prestasi tidak ditemukan.";
            header("Location: daftar_prestasi.php");
            exit();
        }

        // Validasi izin edit (mirip detail_atlet.php)
        $can_edit_this_prestasi_page = false;
        if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_this_prestasi_page = true; } 
        elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $prestasi_data['id_cabor']) { $can_edit_this_prestasi_page = true; } 
        elseif ($user_role_utama == 'atlet' && $user_nik == $prestasi_data['nik']) {
            if (in_array($prestasi_data['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) { // Atlet bisa edit jika ditolak admin juga
                $can_edit_this_prestasi_page = true;
            }
        }
        if (!$can_edit_this_prestasi_page) {
            $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengedit prestasi ini.";
            header("Location: daftar_prestasi.php?id_cabor=" . $prestasi_data['id_cabor'] . "&nik_atlet=" . $prestasi_data['nik']);
            exit();
        }

    } catch (PDOException $e) { 
        error_log("Error Edit Prestasi - Ambil Data: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data prestasi."; 
        header("Location: daftar_prestasi.php"); 
        exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "ID Prestasi tidak valid atau tidak disediakan."; 
    header("Location: daftar_prestasi.php"); 
    exit(); 
}

// --- PENYESUAIAN: Logika pengambilan data cabor & atlet untuk form (mirip tambah_prestasi) ---
$cabor_options_prestasi_edit_form = [];
$atlet_options_prestasi_edit_form = []; // Akan diisi oleh AJAX atau jika cabor sudah ditentukan

if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_cabor_edit = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
        $cabor_options_prestasi_edit_form = $stmt_cabor_edit->fetchAll(PDO::FETCH_ASSOC);
        
        // Load atlet untuk cabor yang sudah ada pada prestasi ini
        if ($prestasi_data['id_cabor']) {
            $stmt_atlet_current_cabor = $pdo->prepare("SELECT p.nik, p.nama_lengkap FROM atlet a JOIN pengguna p ON a.nik = p.nik WHERE a.id_cabor = :id_cabor AND a.status_pendaftaran = 'disetujui' AND p.is_approved = 1 ORDER BY p.nama_lengkap ASC");
            $stmt_atlet_current_cabor->bindParam(':id_cabor', $prestasi_data['id_cabor'], PDO::PARAM_INT);
            $stmt_atlet_current_cabor->execute();
            $atlet_options_prestasi_edit_form = $stmt_atlet_current_cabor->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { error_log("Error edit_prestasi.php - fetch cabor/atlet (admin): " . $e->getMessage()); }
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama) && $id_cabor_pengurus_utama == $prestasi_data['id_cabor']) {
    // Pengurus cabor hanya bisa edit atlet dari cabornya sendiri, tidak bisa ganti cabor/atlet
    // $atlet_options_prestasi_edit_form tidak perlu diisi karena dropdown NIK tidak akan ditampilkan untuk pengurus cabor saat edit.
}

// --- PENYESUAIAN: Pengambilan data form dari session (konsisten) ---
$form_data_prestasi_edit_sess = $_SESSION['form_data_prestasi_edit'] ?? [];
$form_errors_prestasi_edit = $_SESSION['errors_prestasi_edit'] ?? [];
$form_error_fields_prestasi_edit = $_SESSION['error_fields_prestasi_edit'] ?? [];

unset($_SESSION['form_data_prestasi_edit']);
unset($_SESSION['errors_prestasi_edit']);
unset($_SESSION['error_fields_prestasi_edit']);

// Inisialisasi nilai form dengan data dari DB, lalu timpa dengan data dari session jika ada (setelah validasi gagal)
$val_nik_atlet_form = $form_data_prestasi_edit_sess['nik'] ?? $prestasi_data['nik'];
$val_id_cabor_form = $form_data_prestasi_edit_sess['id_cabor'] ?? $prestasi_data['id_cabor'];
$val_nama_kejuaraan = $form_data_prestasi_edit_sess['nama_kejuaraan'] ?? $prestasi_data['nama_kejuaraan'];
$val_tingkat_kejuaraan = $form_data_prestasi_edit_sess['tingkat_kejuaraan'] ?? $prestasi_data['tingkat_kejuaraan'];
$val_tahun_perolehan = $form_data_prestasi_edit_sess['tahun_perolehan'] ?? $prestasi_data['tahun_perolehan'];
$val_medali_peringkat = $form_data_prestasi_edit_sess['medali_peringkat'] ?? $prestasi_data['medali_peringkat'];
$val_status_approval_current = $prestasi_data['status_approval']; // Status saat ini dari DB
// Untuk field status dan alasan, ambil dari DB sebagai default, bisa ditimpa oleh form_data jika admin melakukan perubahan
$val_status_approval_form = $form_data_prestasi_edit_sess['status_approval'] ?? $prestasi_data['status_approval'];
$val_alasan_penolakan_pengcab = $form_data_prestasi_edit_sess['alasan_penolakan_pengcab'] ?? $prestasi_data['alasan_penolakan_pengcab'];
$val_alasan_penolakan_admin = $form_data_prestasi_edit_sess['alasan_penolakan_admin'] ?? $prestasi_data['alasan_penolakan_admin'];

$doc_root_edit = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$base_path_url_edit = rtrim($app_base_path, '/');
$base_path_fs_edit = $doc_root_edit . $base_path_url_edit;
?>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit mr-1"></i> <?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($prestasi_data['nama_atlet_prestasi']); ?></h3>
                    </div>
                    <form id="formEditPrestasi" action="proses_edit_prestasi.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_prestasi" value="<?php echo htmlspecialchars($id_prestasi_to_edit); ?>">
                        <input type="hidden" name="current_bukti_path" value="<?php echo htmlspecialchars($prestasi_data['bukti_path'] ?? ''); ?>">
                        
                        <div class="card-body">
                            <?php if (!empty($form_errors_prestasi_edit)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Mengupdate Data!</h5>
                                    <ul> <?php foreach ($form_errors_prestasi_edit as $error_item_edit): ?> <li><?php echo htmlspecialchars($error_item_edit); ?></li> <?php endforeach; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-warning"><i class="fas fa-user-check mr-1"></i> Informasi Atlet & Cabor</h5>
                            <div class="callout callout-info">
                                <p class="mb-1"><strong>Atlet:</strong> <?php echo htmlspecialchars($prestasi_data['nama_atlet_prestasi']); ?> (NIK: <?php echo htmlspecialchars($prestasi_data['nik']); ?>)</p>
                                <p class="mb-0"><strong>Cabang Olahraga Saat Ini:</strong> <?php echo htmlspecialchars($prestasi_data['nama_cabor']); ?></p>
                            </div>

                            <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                <div class="form-group">
                                    <label for="id_cabor_prestasi_edit_admin">Ubah Cabang Olahraga Atlet (Admin)</label>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_prestasi_edit['id_cabor'])) echo 'is-invalid'; ?>" id="id_cabor_prestasi_edit_admin" name="id_cabor" style="width: 100%;" data-placeholder="-- Pilih Cabor Baru --">
                                        <option value=""></option>
                                        <?php foreach ($cabor_options_prestasi_edit_form as $cabor_opt_edit): ?>
                                            <option value="<?php echo $cabor_opt_edit['id_cabor']; ?>" <?php echo ($val_id_cabor_form == $cabor_opt_edit['id_cabor']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cabor_opt_edit['nama_cabor']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="nik_atlet_prestasi_edit_admin">Ubah NIK Atlet (Admin)</label>
                                    <select class="form-control select2bs4 <?php if(isset($form_error_fields_prestasi_edit['nik'])) echo 'is-invalid'; ?>" id="nik_atlet_prestasi_edit_admin" name="nik" style="width: 100%;" data-placeholder="-- Pilih Atlet Baru --" <?php if(empty($atlet_options_prestasi_edit_form) && empty($val_id_cabor_form)) echo 'disabled'; ?>>
                                        <option value=""></option>
                                        <?php foreach ($atlet_options_prestasi_edit_form as $atlet_opt_edit): ?>
                                            <option value="<?php echo $atlet_opt_edit['nik']; ?>" <?php echo ($val_nik_atlet_form == $atlet_opt_edit['nik']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($atlet_opt_edit['nama_lengkap'] . ' (NIK: ' . $atlet_opt_edit['nik'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-info" id="nik_atlet_edit_admin_info">Pilih Cabor untuk memuat atlet baru, atau biarkan untuk atlet saat ini.</small>
                                </div>
                            <?php else: // Untuk Pengurus Cabor & Atlet, NIK dan Cabor tidak bisa diubah dari form ini ?>
                                <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($prestasi_data['id_cabor']); ?>">
                                <input type="hidden" name="nik" value="<?php echo htmlspecialchars($prestasi_data['nik']); ?>">
                            <?php endif; ?>

                            <hr>
                            <h5 class="mt-3 mb-3 text-warning"><i class="fas fa-medal mr-1"></i> Edit Detail Prestasi</h5>
                            <div class="form-group"> <label for="nama_kejuaraan">Nama Kejuaraan <span class="text-danger">*</span></label> <input type="text" class="form-control <?php if(isset($form_error_fields_prestasi_edit['nama_kejuaraan'])) echo 'is-invalid'; ?>" id="nama_kejuaraan" name="nama_kejuaraan" value="<?php echo htmlspecialchars($val_nama_kejuaraan); ?>" required> </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"> <label for="tingkat_kejuaraan">Tingkat Kejuaraan <span class="text-danger">*</span></label> <select class="form-control <?php if(isset($form_error_fields_prestasi_edit['tingkat_kejuaraan'])) echo 'is-invalid'; ?>" id="tingkat_kejuaraan" name="tingkat_kejuaraan" required> <option value="">-- Pilih --</option> <option value="Kabupaten" <?php if($val_tingkat_kejuaraan == 'Kabupaten') echo 'selected'; ?>>Kabupaten/Kota</option> <option value="Provinsi" <?php if($val_tingkat_kejuaraan == 'Provinsi') echo 'selected'; ?>>Provinsi</option> <option value="Nasional" <?php if($val_tingkat_kejuaraan == 'Nasional') echo 'selected'; ?>>Nasional</option> <option value="Internasional" <?php if($val_tingkat_kejuaraan == 'Internasional') echo 'selected'; ?>>Internasional</option> </select> </div></div>
                                <div class="col-md-6"><div class="form-group"> <label for="tahun_perolehan">Tahun Perolehan <span class="text-danger">*</span></label> <input type="number" class="form-control <?php if(isset($form_error_fields_prestasi_edit['tahun_perolehan'])) echo 'is-invalid'; ?>" id="tahun_perolehan" name="tahun_perolehan" value="<?php echo htmlspecialchars($val_tahun_perolehan); ?>" min="1900" max="<?php echo date('Y') + 5; ?>" required> </div></div>
                            </div>
                            <div class="form-group"> <label for="medali_peringkat">Medali / Peringkat <span class="text-danger">*</span></label> <input type="text" class="form-control <?php if(isset($form_error_fields_prestasi_edit['medali_peringkat'])) echo 'is-invalid'; ?>" id="medali_peringkat" name="medali_peringkat" value="<?php echo htmlspecialchars($val_medali_peringkat); ?>" required> </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-warning"><i class="fas fa-file-alt mr-1"></i> Edit Bukti Prestasi</h5>
                            <div class="form-group">
                                <label for="bukti_path">Upload Bukti Baru (Kosongkan jika tidak ingin mengubah)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(isset($form_error_fields_prestasi_edit['bukti_path'])) echo 'is-invalid'; ?>" id="bukti_path" name="bukti_path" accept=".pdf,.jpg,.jpeg,.png">
                                    <label class="custom-file-label" for="bukti_path">Pilih file bukti baru...</label>
                                </div>
                                <?php if (!empty($prestasi_data['bukti_path'])): 
                                    $full_path_bukti_current = $base_path_fs_edit . '/' . ltrim($prestasi_data['bukti_path'], '/');
                                    if (file_exists(preg_replace('/\/+/', '/', $full_path_bukti_current))): ?>
                                    <small class="form-text text-muted mt-1">Bukti Saat Ini: <a href="<?php echo htmlspecialchars($base_path_url_edit . '/' . ltrim($prestasi_data['bukti_path'], '/')); ?>" target="_blank"><?php echo htmlspecialchars(basename($prestasi_data['bukti_path'])); ?></a></small>
                                <?php else: ?>
                                    <small class="form-text text-danger mt-1">File bukti saat ini (<?php echo htmlspecialchars(basename($prestasi_data['bukti_path'])); ?>) tidak ditemukan.</small>
                                <?php endif; elseif(empty($prestasi_data['bukti_path'])): ?>
                                     <small class="form-text text-muted mt-1">Belum ada bukti prestasi yang diupload sebelumnya.</small>
                                <?php endif; ?>
                            </div>
                            
                            <?php // Hanya Admin/SA yang bisa edit status approval secara langsung di form ini ?>
                            <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                            <hr>
                            <h5 class="mt-3 mb-3 text-warning"><i class="fas fa-tasks mr-1"></i> Manajemen Status Approval</h5>
                            <div class="form-group">
                                <label for="status_approval">Ubah Status Approval</label>
                                <select class="form-control <?php if(isset($form_error_fields_prestasi_edit['status_approval'])) echo 'is-invalid'; ?>" id="status_approval" name="status_approval">
                                    <option value="pending" <?php if($val_status_approval_form == 'pending') echo 'selected';?>>Pending (Menunggu Verifikasi Pengcab)</option>
                                    <option value="disetujui_pengcab" <?php if($val_status_approval_form == 'disetujui_pengcab') echo 'selected';?>>Disetujui Pengcab (Menunggu Approval Admin)</option>
                                    <option value="disetujui_admin" <?php if($val_status_approval_form == 'disetujui_admin') echo 'selected';?>>Disetujui Admin (Final)</option>
                                    <option value="ditolak_pengcab" <?php if($val_status_approval_form == 'ditolak_pengcab') echo 'selected';?>>Ditolak oleh Pengcab</option>
                                    <option value="ditolak_admin" <?php if($val_status_approval_form == 'ditolak_admin') echo 'selected';?>>Ditolak oleh Admin KONI</option>
                                    <option value="revisi" <?php if($val_status_approval_form == 'revisi') echo 'selected';?>>Perlu Revisi Data</option>
                                </select>
                            </div>
                            <div class="form-group alasan-penolakan-group <?php if(!in_array($val_status_approval_form, ['ditolak_pengcab', 'revisi'])) echo 'd-none'; ?>" id="group_alasan_pengcab">
                                <label for="alasan_penolakan_pengcab">Alasan Penolakan/Revisi dari Pengcab</label>
                                <textarea class="form-control" name="alasan_penolakan_pengcab" rows="2"><?php echo htmlspecialchars($val_alasan_penolakan_pengcab); ?></textarea>
                            </div>
                             <div class="form-group alasan-penolakan-group <?php if(!in_array($val_status_approval_form, ['ditolak_admin', 'revisi'])) echo 'd-none'; ?>" id="group_alasan_admin">
                                <label for="alasan_penolakan_admin">Alasan Penolakan/Revisi dari Admin KONI</label>
                                <textarea class="form-control" name="alasan_penolakan_admin" rows="2"><?php echo htmlspecialchars($val_alasan_penolakan_admin); ?></textarea>
                            </div>
                            <?php else: // Untuk Pengcab & Atlet, status tidak diubah di sini, tapi melalui tombol aksi di halaman daftar ?>
                                <input type="hidden" name="status_approval" value="<?php echo htmlspecialchars($prestasi_data['status_approval']); ?>">
                                <input type="hidden" name="alasan_penolakan_pengcab" value="<?php echo htmlspecialchars($prestasi_data['alasan_penolakan_pengcab'] ?? ''); ?>">
                                <input type="hidden" name="alasan_penolakan_admin" value="<?php echo htmlspecialchars($prestasi_data['alasan_penolakan_admin'] ?? ''); ?>">
                            <?php endif; ?>

                            <p class="text-muted text-sm mt-4"><span class="text-danger">*</span> Wajib diisi.</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_prestasi" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Update Data Prestasi</button>
                            <a href="daftar_prestasi.php?id_cabor=<?php echo $prestasi_data['id_cabor']; ?>&nik_atlet=<?php echo $prestasi_data['nik']; ?>" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// JavaScript untuk Select2, AJAX NIK (jika diperlukan), dan validasi frontend
$script_admin_edit_prestasi = '';
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $script_admin_edit_prestasi = "
    console.log('Edit Prestasi (Admin/SA): Menyiapkan event listener untuk #id_cabor_prestasi_edit_admin.');
    $('#id_cabor_prestasi_edit_admin').on('change', function () {
        var selectedCaborIdEdit = $(this).val();
        var atletSelectEdit = $('#nik_atlet_prestasi_edit_admin');
        var atletInfoEdit = $('#nik_atlet_edit_admin_info');
        
        console.log('Edit Prestasi (Admin/SA): Cabor diubah, ID: ' + selectedCaborIdEdit);

        atletSelectEdit.val(null).trigger('change.select2');
        atletSelectEdit.empty().append($('<option></option>').attr('value', '').text('-- Pilih Atlet --'));
        
        if (selectedCaborIdEdit && selectedCaborIdEdit !== '') {
            atletSelectEdit.prop('disabled', true).select2({ placeholder: 'Memuat atlet...', theme: 'bootstrap4', allowClear: true });
            atletInfoEdit.text('Memuat atlet untuk cabor terpilih...');
            
            $.ajax({
                url: '" . rtrim($app_base_path, '/') . "/ajax/ajax_get_atlet_by_cabor.php',
                type: 'POST',
                data: { id_cabor: selectedCaborIdEdit, status_pendaftaran: 'disetujui' },
                dataType: 'json',
                success: function(response) {
                    console.log('Edit Prestasi (Admin/SA): AJAX Response:', response);
                    atletSelectEdit.prop('disabled', false);
                    atletSelectEdit.empty().append($('<option></option>').attr('value', '').text('-- Pilih Atlet --'));
                    if (response.status === 'success' && response.atlet_list && response.atlet_list.length > 0) {
                        $.each(response.atlet_list, function(index, atlet) {
                            atletSelectEdit.append(new Option(atlet.nama_lengkap + ' (NIK: ' + atlet.nik + ')', atlet.nik));
                        });
                        atletInfoEdit.text('Pilih atlet dari daftar.');
                    } else {
                         atletSelectEdit.append(new Option((response.message || 'Tidak ada atlet di cabor ini'), '', true, true));
                         atletInfoEdit.text(response.message || 'Tidak ada atlet disetujui di cabor ini.');
                    }
                    atletSelectEdit.select2({ placeholder: '-- Pilih Atlet --', theme: 'bootstrap4', allowClear: true });
                    // Tidak otomatis memilih atlet di form edit, biarkan user memilih jika ingin ganti atlet
                },
                error: function() { /* ... (error handling sama seperti tambah) ... */ }
            });
        } else { 
            atletSelectEdit.prop('disabled', true);
            atletSelectEdit.select2({ placeholder: '-- Pilih Cabor terlebih dahulu --', theme: 'bootstrap4', allowClear: true });
            atletInfoEdit.text('Pilih Cabor untuk memuat atlet.');
        }
    });

    // Logika untuk menampilkan/menyembunyikan field alasan penolakan berdasarkan status
    $('#status_approval').on('change', function() {
        var status = $(this).val();
        $('#group_alasan_pengcab').toggleClass('d-none', !(status === 'ditolak_pengcab' || status === 'revisi'));
        $('#group_alasan_admin').toggleClass('d-none', !(status === 'ditolak_admin' || status === 'revisi'));
    }).trigger('change'); // Trigger saat load untuk set tampilan awal
    ";
}

$inline_script = "
$(function () {
  console.log('Edit Prestasi: Dokumen siap.');
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) { bsCustomFileInput.init(); }
  if (typeof $.fn.select2 === 'function') {
    $('#id_cabor_prestasi_edit_admin.select2bs4, #nik_atlet_prestasi_edit_admin.select2bs4').each(function() {
        if ($(this).data('select2')) { $(this).select2('destroy'); }
        $(this).select2({ theme: 'bootstrap4', placeholder: $(this).data('placeholder') || '-- Pilih --', allowClear: true });
    });
    // Inisialisasi dropdown NIK untuk pengurus cabor jika ada
    $('#nik_atlet_prestasi_pengcab.select2bs4').select2({ theme: 'bootstrap4', placeholder: '-- Pilih Atlet --', allowClear: true });
  }

  " . $script_admin_edit_prestasi . "

  $('#formEditPrestasi').submit(function(e) {
    // ... (Logika validasi frontend mirip tambah_prestasi.php, disesuaikan untuk ID field edit) ...
    // Untuk file, validasi ukuran hanya jika file baru dipilih.
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>