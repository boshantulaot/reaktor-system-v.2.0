<?php
// File: modules/pelatih/edit_pelatih.php (REVISI TOTAL)

$page_title = "Edit Profil Pelatih";

$additional_css = [
    // Mungkin tidak perlu Select2 lagi di sini
    // 'assets/adminlte/plugins/select2/css/select2.min.css',
    // 'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    // 'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Konstanta ukuran file untuk foto profil khusus pelatih
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB')) { define('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB', 1); }
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_BYTES')) { define('MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_BYTES', MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB * 1024 * 1024); }


// 1. Pengecekan Sesi & ID Pelatih
if (!isset($user_nik, $user_role_utama, $app_base_path, $pdo)) {
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah.";
    header("Location: " . ($app_base_path ?? '.') . "/auth/login.php");
    exit();
}

if (!isset($_GET['id_pelatih']) || !filter_var($_GET['id_pelatih'], FILTER_VALIDATE_INT) || (int)$_GET['id_pelatih'] <= 0) {
    $_SESSION['pesan_error_global'] = "ID Profil Pelatih tidak valid atau tidak disertakan.";
    header("Location: daftar_pelatih.php");
    exit();
}
$id_pelatih_to_edit = (int)$_GET['id_pelatih'];

// 2. Pengambilan Data Profil Pelatih dari DB
$pelatih_profil = null;
try {
    // Query hanya mengambil data dari tabel `pelatih` dan `pengguna`
    $stmt_get_pp = $pdo->prepare("SELECT plt.*, p.nama_lengkap, p.email, p.foto AS foto_pengguna_utama
                                 FROM pelatih plt
                                 JOIN pengguna p ON plt.nik = p.nik
                                 WHERE plt.id_pelatih = :id_pelatih");
    $stmt_get_pp->bindParam(':id_pelatih', $id_pelatih_to_edit, PDO::PARAM_INT);
    $stmt_get_pp->execute();
    $pelatih_profil = $stmt_get_pp->fetch(PDO::FETCH_ASSOC);

    if (!$pelatih_profil) {
        $_SESSION['pesan_error_global'] = "Profil Pelatih dengan ID " . htmlspecialchars($id_pelatih_to_edit) . " tidak ditemukan.";
        header("Location: daftar_pelatih.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['pesan_error_global'] = "Gagal memuat data profil pelatih untuk diedit.";
    error_log("EDIT_PROFIL_PELATIH_FETCH_ERROR: " . $e->getMessage());
    header("Location: daftar_pelatih.php");
    exit();
}

// 3. Hak Akses Edit (Hanya Admin KONI/Super Admin yang bisa edit profil & statusnya)
if (!in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengedit profil pelatih ini.";
    header("Location: daftar_pelatih.php");
    exit();
}

// Mengambil data form dari sesi jika ada error validasi sebelumnya, atau dari DB jika load pertama
$form_data_edit_pp = $_SESSION['form_data_profil_pelatih_edit_' . $id_pelatih_to_edit] ?? $pelatih_profil;
$form_errors_edit_pp = $_SESSION['errors_profil_pelatih_edit_' . $id_pelatih_to_edit] ?? [];
$form_error_fields_edit_pp = $_SESSION['error_fields_profil_pelatih_edit_' . $id_pelatih_to_edit] ?? [];

unset($_SESSION['form_data_profil_pelatih_edit_' . $id_pelatih_to_edit]);
unset($_SESSION['errors_profil_pelatih_edit_' . $id_pelatih_to_edit]);
unset($_SESSION['error_fields_profil_pelatih_edit_' . $id_pelatih_to_edit]);

$base_url_img_edit = rtrim($app_base_path, '/');
?>

<section class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['pesan_sukses_global'])): /* ... pesan sukses ... */ endif; ?>
        <?php if (isset($_SESSION['pesan_error_global'])): /* ... pesan error global ... */ endif; ?>
        
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card card-purple">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Profil Pelatih: 
                            <strong><?php echo htmlspecialchars($pelatih_profil['nama_lengkap']); ?></strong>
                            <small>(NIK: <?php echo htmlspecialchars($pelatih_profil['nik']); ?>)</small>
                        </h3>
                    </div>
                    <form id="formEditProfilPelatih" action="proses_edit_pelatih.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_pelatih" value="<?php echo htmlspecialchars($id_pelatih_to_edit); ?>">
                        <input type="hidden" name="current_foto_pelatih_profil" value="<?php echo htmlspecialchars($pelatih_profil['foto_pelatih_profil'] ?? ''); ?>">

                        <div class="card-body">
                            <?php if (!empty($form_errors_edit_pp)): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Gagal Update Profil!</h5>
                                    <ul> <?php foreach ($form_errors_edit_pp as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?> </ul>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-1 mb-3 text-purple"><i class="fas fa-user-tag mr-1"></i> Informasi Pengguna (Read-only)</h5>
                            <div class="form-group">
                                <label>NIK</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($pelatih_profil['nik']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($pelatih_profil['nama_lengkap']); ?>" readonly>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-address-book mr-1"></i> Data Profil Pelatih (Bisa Diedit)</h5>

                            <div class="form-group">
                                <label for="kontak_pelatih_alternatif_edit">Kontak Pelatih Tambahan (Opsional)</label>
                                <input type="text" class="form-control <?php if(isset($form_error_fields_edit_pp['kontak_pelatih_alternatif'])) echo 'is-invalid'; ?>" 
                                       id="kontak_pelatih_alternatif_edit" name="kontak_pelatih_alternatif" 
                                       placeholder="Nomor telepon jika berbeda dari data pengguna" 
                                       value="<?php echo htmlspecialchars($form_data_edit_pp['kontak_pelatih_alternatif'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="foto_pelatih_profil_edit">Ganti Foto Profil Khusus Pelatih (JPG, PNG - Maks <?php echo MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB; ?>MB)</label>
                                <?php 
                                    $current_foto_pp_path = $pelatih_profil['foto_pelatih_profil'] ?? '';
                                    if (!empty($current_foto_pp_path)) {
                                        $foto_pp_url = $base_url_img_edit . '/' . ltrim($current_foto_pp_path, '/');
                                        if (file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $foto_pp_url)) {
                                            echo '<div class="mb-2"><small>Foto Profil Pelatih Saat Ini:</small><br><img src="'.htmlspecialchars($foto_pp_url).'" alt="Foto Profil Pelatih" style="max-height: 80px; border:1px solid #ddd; padding:2px; border-radius: .2rem;"></div>';
                                        } else { echo '<p class="text-sm text-danger"><small>File foto profil pelatih ('.htmlspecialchars(basename($current_foto_pp_path)).') tidak ditemukan.</small></p>';}
                                    } elseif (!empty($pelatih_profil['foto_pengguna_utama'])) {
                                        $foto_pu_url = $base_url_img_edit . '/' . ltrim($pelatih_profil['foto_pengguna_utama'], '/');
                                         if (file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $foto_pu_url)) {
                                            echo '<div class="mb-2"><small>Menggunakan Foto Pengguna Utama:</small><br><img src="'.htmlspecialchars($foto_pu_url).'" alt="Foto Pengguna Utama" style="max-height: 80px; border:1px solid #ddd; padding:2px; border-radius: .2rem;"></div>';
                                        }
                                    } else {
                                        echo '<p class="text-sm text-muted"><small>Belum ada foto profil khusus pelatih atau foto pengguna utama.</small></p>';
                                    }
                                ?>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input <?php if(isset($form_error_fields_edit_pp['foto_pelatih_profil'])) echo 'is-invalid'; ?>" 
                                           id="foto_pelatih_profil_edit" name="foto_pelatih_profil" accept=".jpg,.jpeg,.png">
                                    <label class="custom-file-label" for="foto_pelatih_profil_edit">Pilih file baru jika ingin ganti...</label>
                                </div>
                                <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah foto.</small>
                            </div>

                            <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-user-check mr-1"></i> Status Approval Profil</h5>
                            <div class="form-group">
                                <label for="status_approval_profil_edit">Status Approval</label>
                                <select name="status_approval" id="status_approval_profil_edit" class="form-control <?php if(isset($form_error_fields_edit_pp['status_approval'])) echo 'is-invalid'; ?>">
                                    <option value="pending" <?php echo (($form_data_edit_pp['status_approval'] ?? '') == 'pending' ? 'selected' : ''); ?>>Pending</option>
                                    <option value="disetujui" <?php echo (($form_data_edit_pp['status_approval'] ?? '') == 'disetujui' ? 'selected' : ''); ?>>Disetujui</option>
                                    <option value="ditolak" <?php echo (($form_data_edit_pp['status_approval'] ?? '') == 'ditolak' ? 'selected' : ''); ?>>Ditolak</option>
                                    <option value="revisi" <?php echo (($form_data_edit_pp['status_approval'] ?? '') == 'revisi' ? 'selected' : ''); ?>>Revisi</option>
                                </select>
                            </div>
                             <div class="form-group" id="alasan_penolakan_profil_edit_group" style="display: <?php echo (in_array(($form_data_edit_pp['status_approval'] ?? ''), ['ditolak', 'revisi']) ? 'block' : 'none'); ?>;">
                                <label for="alasan_penolakan_profil_edit">Alasan Penolakan/Revisi (jika status diubah)</label>
                                <textarea name="alasan_penolakan" id="alasan_penolakan_profil_edit" class="form-control" rows="2"><?php echo htmlspecialchars($form_data_edit_pp['alasan_penolakan'] ?? ''); ?></textarea>
                            </div>
                            <?php else: // Jika bukan admin, status approval tidak bisa diubah dari sini ?>
                                <input type="hidden" name="status_approval" value="<?php echo htmlspecialchars($pelatih_profil['status_approval']); ?>">
                                <?php if(!empty($pelatih_profil['alasan_penolakan'])): ?>
                                    <input type="hidden" name="alasan_penolakan" value="<?php echo htmlspecialchars($pelatih_profil['alasan_penolakan']); ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                            
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_profil_pelatih" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Update Profil</button>
                            <a href="daftar_pelatih.php" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
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

  // Tampilkan/sembunyikan alasan penolakan berdasarkan status approval
  $('#status_approval_profil_edit').on('change', function() {
    if ($(this).val() === 'ditolak' || $(this).val() === 'revisi') {
      $('#alasan_penolakan_profil_edit_group').slideDown();
    } else {
      $('#alasan_penolakan_profil_edit_group').slideUp();
      // Kosongkan alasan jika statusnya bukan ditolak/revisi, agar tidak tersimpan jika tidak relevan
      // $('#alasan_penolakan_profil_edit').val(''); 
    }
  }).trigger('change'); // Trigger saat load


  // Validasi Frontend Sederhana (hanya ukuran file jika ada)
  $('#formEditProfilPelatih').submit(function(e) {
    let isValid = true;
    let focusField = null;
    $('.is-invalid').removeClass('is-invalid'); 
    
    let fotoProfilFile = $('#foto_pelatih_profil_edit')[0].files[0];
    if(fotoProfilFile && fotoProfilFile.size > " . MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_BYTES . "){ 
       if(!focusField) focusField = $('#foto_pelatih_profil_edit');
       $('#foto_pelatih_profil_edit').closest('.custom-file').find('.custom-file-label').addClass('is-invalid');
       $('#foto_pelatih_profil_edit').addClass('is-invalid');
       alert('Ukuran file Foto Profil tidak boleh lebih dari " . MAX_FILE_SIZE_FOTO_PROFIL_PELATIH_MB . "MB.');
       isValid = false;
    }
    
    if (!isValid) {
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