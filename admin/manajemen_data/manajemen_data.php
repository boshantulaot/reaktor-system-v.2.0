<?php
$page_title = "Manajemen Data Sistem";
// Path ke header.php: dari admin/manajemen_data/ naik DUA level ke reaktorsystem/ lalu ke core/
require_once(__DIR__ . '/../../core/header.php'); // Path sudah diperbaiki

// --- PENTING: Proteksi Akses Halaman ---
if (!isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman Manajemen Data.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php"); // $app_base_path dari init_core.php
    exit();
}

$feedback_message = '';
if (isset($_SESSION['backup_feedback'])) {
    $feedback_type = $_SESSION['backup_feedback_type'] ?? 'info';
    $feedback_message .= '<div class="alert alert-' . htmlspecialchars($feedback_type) . ' alert-dismissible fade show" role="alert">';
    $feedback_message .= htmlspecialchars($_SESSION['backup_feedback']);
    $feedback_message .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['backup_feedback']);
    unset($_SESSION['backup_feedback_type']);
}
if (isset($_SESSION['empty_db_feedback'])) { // Feedback khusus untuk kosongkan DB
    $feedback_type = $_SESSION['empty_db_feedback_type'] ?? 'info';
    $feedback_message .= '<div class="alert alert-' . htmlspecialchars($feedback_type) . ' alert-dismissible fade show" role="alert">';
    $feedback_message .= htmlspecialchars($_SESSION['empty_db_feedback']);
    $feedback_message .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['empty_db_feedback']);
    unset($_SESSION['empty_db_feedback_type']);
}
// Tambahkan feedback untuk backup/restore aset
if (isset($_SESSION['asset_feedback'])) { 
    $feedback_type = $_SESSION['asset_feedback_type'] ?? 'info';
    $feedback_message .= '<div class="alert alert-' . htmlspecialchars($feedback_type) . ' alert-dismissible fade show" role="alert">';
    $feedback_message .= htmlspecialchars($_SESSION['asset_feedback']);
    $feedback_message .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['asset_feedback']);
    unset($_SESSION['asset_feedback_type']);
}
// Tambahkan feedback untuk export/import
if (isset($_SESSION['export_import_feedback'])) { 
    $feedback_type = $_SESSION['export_import_feedback_type'] ?? 'info';
    $feedback_message .= '<div class="alert alert-' . htmlspecialchars($feedback_type) . ' alert-dismissible fade show" role="alert">';
    $feedback_message .= htmlspecialchars($_SESSION['export_import_feedback']);
    $feedback_message .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['export_import_feedback']);
    unset($_SESSION['export_import_feedback_type']);
}

// Ambil daftar tabel dari database untuk dropdown
$database_tables = [];
if (isset($pdo) && $pdo instanceof PDO && defined('DB_NAME')) {
    try {
        $stmt_get_tables = $pdo->query("SHOW TABLES FROM `" . DB_NAME . "`");
        if ($stmt_get_tables) {
            $database_tables = $stmt_get_tables->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        error_log("MANAJEMEN_DATA_ERROR: Gagal mengambil daftar tabel. Pesan: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">
    <?php echo $feedback_message; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                 <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-database mr-1"></i> Backup & Restore Database</h3>
                </div>
                <div class="card-body">
                    <p>Gunakan fitur ini untuk membuat cadangan (backup) seluruh database sistem atau memulihkannya (restore) dari file backup SQL.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Backup Database</h4>
                            <p>Membuat salinan lengkap dari database saat ini dalam format file SQL.</p>
                            <form action="proses_backup_db.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup database sekarang? Proses ini mungkin memerlukan beberapa waktu.');">
                                <button type="submit" name="backup_db_now" class="btn btn-primary"><i class="fas fa-download mr-1"></i> Backup Database Sekarang</button>
                            </form>
                            <small class="form-text text-muted mt-2">File backup akan otomatis terunduh setelah proses selesai.</small>
                        </div>
                        <div class="col-md-6">
                            <h4>Restore Database</h4>
                            <p class="text-danger"><strong>PERHATIAN:</strong> Operasi restore akan menimpa seluruh data di database saat ini dengan data dari file backup. Pastikan Anda memiliki backup terbaru sebelum melanjutkan!</p>
                            <form action="proses_restore_db.php" method="post" enctype="multipart/form-data" onsubmit="return confirmActionRestore();">
                                <div class="form-group">
                                    <label for="sql_file_restore">Pilih File Backup SQL (.sql atau .sql.gz):</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="sql_file_restore" name="sql_file_restore" accept=".sql,.gz" required>
                                            <label class="custom-file-label" for="sql_file_restore">Pilih file...</label>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Ukuran file maksimal: <?php echo ini_get('upload_max_filesize'); ?>.</small>
                                </div>
                                <button type="submit" name="restore_db_now" class="btn btn-danger"><i class="fas fa-upload mr-1"></i> Restore Database dari File</button>
                            </form>
                        </div>
                    </div>
                    <hr class="my-4"> 
                    <div class="mt-4"> 
                        <h4>Kosongkan Database (Hapus Semua Isi Tabel)</h4>
                        <p class="text-danger"><strong>PERINGATAN SANGAT KERAS!</strong> Operasi ini akan menghapus SEMUA DATA di dalam database '<?php echo defined('DB_NAME') ? htmlspecialchars(DB_NAME) : 'NAMA_DATABASE_ANDA'; ?>' (struktur tabel tetap ada). Tindakan ini TIDAK DAPAT DIBATALKAN. Lakukan ini hanya jika Anda benar-benar yakin dan sudah memiliki backup yang valid.</p>
                        <form action="proses_kosongkan_db.php" method="post" onsubmit="return confirmActionEmptyDb();">
                            <button type="submit" name="empty_db_now" class="btn btn-danger btn-lg"><i class="fas fa-exclamation-triangle mr-1"></i> KOSONGKAN ISI DATABASE SEKARANG</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-info mt-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-archive mr-1"></i> Backup & Restore Aset (Folder Uploads)</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Backup Folder Uploads</h4>
                            <p>Membuat file arsip ZIP dari seluruh konten folder <code>assets/uploads/</code>.</p>
                            <form action="proses_backup_aset.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup folder uploads sekarang? Proses ini mungkin memerlukan waktu tergantung ukuran file.');">
                                <button type="submit" name="backup_assets_now" class="btn btn-info"><i class="fas fa-file-archive mr-1"></i> Backup Folder Uploads Sekarang</button>
                            </form>
                            <small class="form-text text-muted mt-2">File arsip ZIP akan otomatis terunduh setelah proses selesai.</small>
                        </div>
                        <div class="col-md-6">
                            <h4>Restore Folder Uploads</h4>
                             <p class="text-danger"><strong>PERHATIAN:</strong> Operasi restore akan menimpa file yang ada jika nama file sama.</p>
                            <form action="proses_restore_aset.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('PERINGATAN! Anda akan melakukan restore aset. Ini bisa menimpa file yang ada. Lanjutkan?');">
                                <div class="form-group">
                                    <label for="zip_file_asset_restore">Pilih File Backup ZIP (.zip):</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="zip_file_asset_restore" name="zip_file_asset_restore" accept=".zip" required>
                                            <label class="custom-file-label" for="zip_file_asset_restore">Pilih file...</label>
                                        </div>
                                    </div>
                                     <small class="form-text text-muted">Ukuran file maksimal: <?php echo ini_get('upload_max_filesize'); ?>.</small>
                                </div>
                                <button type="submit" name="restore_assets_now" class="btn btn-danger"><i class="fas fa-upload mr-1"></i> Restore Aset dari File ZIP</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-success mt-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table mr-1"></i> Export/Import Data per Tabel</h3>
                </div>
                <div class="card-body">
                    <p>Gunakan fitur ini untuk mengunduh data dari tabel tertentu, mengunduh template kosong untuk import, atau mengimpor data dari file CSV.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Download Template Tabel Kosong (CSV)</h5>
                            <p>Unduh file CSV dengan header kolom sebagai template untuk import data.</p>
                            <?php if (!empty($database_tables)): ?>
                                <form action="proses_download_template.php" method="post" target="_blank">
                                    <div class="form-group">
                                        <label for="table_template">Pilih Tabel:</label>
                                        <select name="table_name_template" id="table_template" class="form-control" required>
                                            <option value="">-- Pilih Tabel --</option>
                                            <?php foreach ($database_tables as $table): ?>
                                                <option value="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="download_template" class="btn btn-success"><i class="fas fa-file-csv mr-1"></i> Download Template CSV</button>
                                </form>
                            <?php else: ?>
                                <p class="text-danger">Tidak dapat memuat daftar tabel untuk membuat template. Pastikan koneksi database berhasil dan database tidak kosong.</p>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <h5>Export Data Tabel ke CSV</h5>
                            <!-- GANTI PLACEHOLDER DENGAN FORM SEBENARNYA -->
                            <?php if (!empty($database_tables)): ?>
                                <form action="proses_export_tabel.php" method="post" target="_blank">
                                    <div class="form-group">
                                        <label for="table_export">Pilih Tabel untuk Export:</label>
                                        <select name="table_name_export" id="table_export" class="form-control" required>
                                            <option value="">-- Pilih Tabel --</option>
                                            <?php foreach ($database_tables as $table): ?>
                                                <option value="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="export_table_data" class="btn btn-primary"><i class="fas fa-file-export mr-1"></i> Export Data CSV</button>
                                </form>
                            <?php else: ?>
                                <p class="text-danger">Tidak dapat memuat daftar tabel untuk export.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-12">
                             <h5>Import Data ke Tabel dari CSV</h5>
                             <p class="text-warning"><strong>PENTING:</strong> Pastikan struktur file CSV Anda (urutan kolom dan tipe data) sesuai dengan struktur tabel di database. Gunakan template yang diunduh untuk format yang benar.</p>
                             <!-- GANTI PLACEHOLDER DENGAN FORM SEBENARNYA -->
                            <?php if (!empty($database_tables)): ?>
                                <form action="proses_import_tabel.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('PERHATIAN: Mengimpor data dapat mengubah atau menambahkan data secara signifikan. Pastikan file dan tabel target sudah benar. Lanjutkan?');">
                                    <div class="form-group">
                                        <label for="table_import_target">Pilih Tabel Tujuan untuk Import:</label>
                                        <select name="table_name_import" id="table_import_target" class="form-control" required>
                                            <option value="">-- Pilih Tabel Tujuan --</option>
                                            <?php foreach ($database_tables as $table): ?>
                                                <option value="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="csv_file_import">Pilih File CSV untuk Diimpor:</label>
                                        <div class="input-group">
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="csv_file_import" name="csv_file_import" accept=".csv" required>
                                                <label class="custom-file-label" for="csv_file_import">Pilih file CSV...</label>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Pastikan baris pertama file CSV adalah header nama kolom.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="import_option">Opsi Import:</label>
                                        <select name="import_option" id="import_option" class="form-control">
                                            <option value="insert">Tambah Data Baru (Abaikan jika ada duplikasi Primary Key)</option>
                                            <option value="replace">Timpa Data (REPLACE INTO - Hapus baris lama jika PK sama, lalu insert baru)</option>
                                            <option value="update">Update Data yang Ada (UPDATE jika PK cocok, abaikan jika tidak ada)</option>
                                            <option value="truncate_insert">Kosongkan Tabel Lalu Insert (TRUNCATE kemudian INSERT)</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="import_table_data" class="btn btn-info"><i class="fas fa-file-import mr-1"></i> Import Data dari CSV</button>
                                </form>
                            <?php else: ?>
                                <p class="text-danger">Tidak dapat memuat daftar tabel untuk import.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div> 
            </div> 
        </div>
    </div>
</div>

<script>
// ... (Fungsi JavaScript confirmActionRestore() dan confirmActionEmptyDb() Anda tetap sama) ...
</script>

<?php
require_once(__DIR__ . '/../../core/footer.php');
?>