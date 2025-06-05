<?php
$page_title = "Import/Export Data Massal";
require_once(__DIR__ . '/../../core/header.php'); // PATH BARU

// Hanya Super Admin yang boleh mengakses
if ($user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: ../../dashboard.php"); // PATH BARU
    exit();
}

if (!isset($pdo) || $pdo === null) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Koneksi Database Gagal!</strong></div></div></section>';
    require_once(__DIR__ . '/../../core/footer.php'); // PATH BARU
    exit();
}

$pesan = '';
if (isset($_SESSION['pesan_sukses_data_massal'])) {
    $pesan = '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_data_massal']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['pesan_sukses_data_massal']);
} elseif (isset($_SESSION['pesan_error_data_massal'])) {
    $pesan = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_data_massal']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['pesan_error_data_massal']);
}

// Definisikan konfigurasi modul untuk import/export (HANYA UNTUK SUPER ADMIN)
$export_import_modules = [
    'cabor' => [ // Tambahkan modul Cabor karena ini hanya untuk Super Admin
        'name' => 'Data Cabang Olahraga',
        'export_fields' => ['id_cabor', 'nama_cabor', 'kode_cabor', 'ketua_cabor_nik', 'sekretaris_cabor_nik', 'bendahara_cabor_nik', 'alamat_sekretariat', 'kontak_cabor', 'email_cabor', 'nomor_sk_provinsi', 'tanggal_sk_provinsi', 'periode_mulai', 'periode_selesai', 'status_kepengurusan'],
        'import_fields' => ['nama_cabor', 'ketua_cabor_nik', 'sekretaris_cabor_nik', 'bendahara_cabor_nik', 'alamat_sekretariat', 'kontak_cabor', 'email_cabor', 'nomor_sk_provinsi', 'tanggal_sk_provinsi', 'periode_mulai', 'periode_selesai', 'status_kepengurusan'],
        'note_export' => 'Export akan berisi semua data cabang olahraga yang terdaftar.',
        'note_import' => 'Nama Cabor akan menjadi kunci unik. Jika Nama Cabor sudah ada, data akan diperbarui. Jika NIK pengurus tidak terdaftar, kolom akan dikosongkan. Kode Cabor akan di-generate otomatis saat data baru dimasukkan. File SK/Logo harus diunggah terpisah.'
    ],
    'klub' => [
        'name' => 'Data Klub',
        'export_fields' => ['id_klub', 'nama_klub', 'id_cabor', 'nama_cabor', 'ketua_klub', 'alamat_sekretariat', 'kontak_klub', 'email_klub', 'nomor_sk_klub', 'tanggal_sk_klub'],
        'import_fields' => ['nama_klub', 'id_cabor', 'ketua_klub', 'alamat_sekretariat', 'kontak_klub', 'email_klub', 'nomor_sk_klub', 'tanggal_sk_klub'],
        'note_export' => 'Export akan berisi semua klub yang disetujui.',
        'note_import' => 'Nama Klub dan ID Cabor akan menjadi kunci unik. ID Klub akan diabaikan pada import. Data yang ada akan diperbarui jika Nama Klub dan ID Cabor ditemukan. Status approval default \'disetujui\'. File SK/logo harus diunggah terpisah.'
    ],
    'atlet' => [
        'name' => 'Data Atlet',
        'export_fields' => ['nik', 'nama_lengkap', 'id_cabor', 'nama_cabor', 'id_klub', 'nama_klub', 'tanggal_lahir', 'jenis_kelamin', 'nomor_telepon', 'email', 'alamat'],
        'import_fields' => ['nik', 'id_cabor', 'id_klub', 'tanggal_lahir', 'jenis_kelamin', 'nomor_telepon', 'email', 'alamat'],
        'note_export' => 'Export akan berisi semua atlet yang disetujui.',
        'note_import' => 'NIK harus sudah terdaftar sebagai pengguna aktif. NIK dan ID Cabor akan menjadi kunci unik. Data yang ada akan diperbarui jika NIK dan ID Cabor ditemukan. Status pendaftaran default \'disetujui\'. File KTP/KK/Pas Foto harus diunggah terpisah.'
    ],
    'pelatih' => [
        'name' => 'Data Pelatih',
        'export_fields' => ['nik', 'nama_lengkap', 'id_cabor', 'nama_cabor', 'nomor_lisensi', 'id_klub_afiliasi', 'nama_klub_afiliasi', 'kontak_pelatih', 'email'],
        'import_fields' => ['nik', 'id_cabor', 'nomor_lisensi', 'id_klub_afiliasi', 'kontak_pelatih'],
        'note_export' => 'Export akan berisi semua pelatih yang disetujui.',
        'note_import' => 'NIK harus sudah terdaftar sebagai pengguna aktif. NIK dan ID Cabor akan menjadi kunci unik. Data yang ada akan diperbarui jika NIK dan ID Cabor ditemukan. Status approval default \'disetujui\'. File lisensi/foto harus diunggah terpisah.'
    ],
    'wasit' => [
        'name' => 'Data Wasit',
        'export_fields' => ['nik', 'nama_lengkap', 'id_cabor', 'nama_cabor', 'nomor_lisensi', 'kontak_wasit', 'email'],
        'import_fields' => ['nik', 'id_cabor', 'nomor_lisensi', 'kontak_wasit'],
        'note_export' => 'Export akan berisi semua wasit yang disetujui.',
        'note_import' => 'NIK harus sudah terdaftar sebagai pengguna aktif. NIK dan ID Cabor akan menjadi kunci unik. Data yang ada akan diperbarui jika NIK dan ID Cabor ditemukan. Status approval default \'disetujui\'. File KTP/KK/lisensi/foto harus diunggah terpisah.'
    ],
    'prestasi' => [
        'name' => 'Data Prestasi',
        'export_fields' => ['id_prestasi', 'nik', 'nama_atlet', 'id_cabor', 'nama_cabor', 'nama_kejuaraan', 'tingkat_kejuaraan', 'tahun_perolehan', 'medali_peringkat'],
        'import_fields' => ['nik', 'id_cabor', 'nama_kejuaraan', 'tingkat_kejuaraan', 'tahun_perolehan', 'medali_peringkat'],
        'note_export' => 'Export akan berisi semua prestasi yang disetujui.',
        'note_import' => 'NIK atlet harus sudah terdaftar sebagai atlet aktif di cabor yang sama. NIK Atlet, Nama Kejuaraan, Tingkat, dan Tahun akan menjadi kunci unik. Data yang ada akan diperbarui jika kunci unik ditemukan. Status approval default \'disetujui_admin\'. File bukti harus diunggah terpisah.'
    ],
];

// Karena hanya Super Admin, tidak perlu saring modul berdasarkan peran lagi di sini.
// Semua modul di atas tersedia untuk Super Admin.
$available_modules = $export_import_modules;

// Ambil nilai module_type dari session jika ada (untuk repopulate setelah error import)
$val_module_type = $_SESSION['form_data_massal']['module_type_import'] ?? '';
unset($_SESSION['form_data_massal']); // Hapus setelah digunakan
?>

<section class="content">
    <div class="container-fluid">
        <?php echo $pesan; ?>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pilih Jenis Data untuk Import/Export</h3>
            </div>
            <div class="card-body">
                <form id="module_selection_form">
                    <div class="form-group">
                        <label for="select_module">Pilih Modul Data:</label>
                        <select class="form-control" id="select_module" name="module_type">
                            <option value="">-- Pilih Modul Data --</option>
                            <?php foreach ($available_modules as $key => $module): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($val_module_type == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <div id="module_details" class="mt-4" style="display: none;">
                    <hr>
                    <h4><span id="selected_module_name"></span></h4>

                    <div class="card card-outline card-success mt-3">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-file-export mr-1"></i> Export Data</h5>
                        </div>
                        <div class="card-body">
                            <p id="export_note"></p>
                            <a id="export_template_link" href="#" class="btn btn-sm btn-info mr-2" download><i
                                    class="fas fa-file-alt mr-1"></i> Unduh Template Kosong</a>
                            <a id="export_data_link" href="#" class="btn btn-sm btn-success"><i
                                    class="fas fa-download mr-1"></i> Export Data Saat Ini</a>
                        </div>
                    </div>

                    <div class="card card-outline card-primary mt-3">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-file-import mr-1"></i> Import Data</h5>
                        </div>
                        <div class="card-body">
                            <p id="import_note"></p>
                            <form id="import_form" action="proses_data_massal.php" method="post"
                                enctype="multipart/form-data"
                                onsubmit="return confirm('Apakah Anda yakin ingin mengimport data? Pastikan format file sesuai template. Data yang sudah ada mungkin akan diperbarui.');">
                                <input type="hidden" name="action" value="import_data">
                                <input type="hidden" name="module_type_import" id="module_type_import">
                                <div class="form-group">
                                    <label for="import_file">Pilih File Spreadsheet (.xlsx, .csv - Max 10MB):</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="import_file"
                                                name="import_file" accept=".xlsx,.csv" required>
                                            <label class="custom-file-label" for="import_file">Pilih file
                                                spreadsheet</label>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Hanya file Excel (.xlsx) atau CSV yang
                                        diizinkan.</small>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Mulai Import</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once(__DIR__ . '/../../core/footer.php'); ?>

<script>
    $(function () {
        var modulesConfig = <?php echo json_encode($available_modules); ?>;
        var currentUserRole = '<?php echo $user_role_utama; ?>';
        var currentUserCaborId = '<?php echo $id_cabor_pengurus_utama; ?>'; // Tidak relevan untuk Super Admin, tapi biarkan saja

        $('#select_module').on('change', function () {
            var selectedModuleKey = $(this).val();
            var $moduleDetails = $('#module_details');

            if (selectedModuleKey && modulesConfig[selectedModuleKey]) {
                var module = modulesConfig[selectedModuleKey];
                $('#selected_module_name').text(module.name);
                $('#export_note').html(module.note_export);
                $('#import_note').html(module.note_import); // Tidak perlu filter cabor untuk SA

                // Set links for Export
                $('#export_template_link').attr('href', 'proses_data_massal.php?action=export_template&module_type=' + selectedModuleKey);
                $('#export_data_link').attr('href', 'proses_data_massal.php?action=export_data&module_type=' + selectedModuleKey);

                // Set hidden input for Import
                $('#module_type_import').val(selectedModuleKey);

                $moduleDetails.slideDown();
            } else {
                $moduleDetails.slideUp();
            }
        });

        // Trigger change saat load jika ada nilai default dari session sebelumnya (misalnya setelah error import)
        var initialModule = '<?php echo htmlspecialchars($val_module_type ?? ''); ?>';
        if (initialModule) {
            $('#select_module').val(initialModule).trigger('change');
        }
    });
</script>