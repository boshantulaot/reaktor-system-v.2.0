<?php
$page_title = "Pengaturan Database";
require_once(__DIR__ . '/../../core/header.php'); // PATH BARU

// Hanya Super Admin yang boleh mengakses
if ($user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: ../../dashboard.php"); // PATH BARU
    exit();
}

if (!isset($pdo) || $pdo === null) {
    // Jika koneksi DB dari header.php gagal
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Koneksi Database Gagal!</strong></div></div></section>';
    require_once(__DIR__ . '/../../core/footer.php'); // PATH BARU
    exit();
}

$pesan = '';
if (isset($_SESSION['pesan_sukses_db_mgmt'])) {
    $pesan = '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_db_mgmt']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['pesan_sukses_db_mgmt']);
} elseif (isset($_SESSION['pesan_error_db_mgmt'])) {
    $pesan = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_db_mgmt']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['pesan_error_db_mgmt']);
}

// Ambil kredensial database yang sedang aktif dari konstanta global
// Konstanta DB_HOST, DB_NAME, DB_USER sudah didefinisikan di database_credentials.php yang di-include header.php
$db_host_display = DB_HOST;
$db_name_display = DB_NAME;
$db_user_display = DB_USER;


// Dapatkan daftar file backup database
$backup_dir = 'assets/uploads/database_backups/'; // Path relatif dari htdocs root
$absolute_backup_dir = __DIR__ . '/../../' . $backup_dir; // Path absolut di server

$backup_files = [];
if (is_dir($absolute_backup_dir)) {
    $files = scandir($absolute_backup_dir);
    foreach ($files as $file) {
        if (preg_match('/\.sql$/', $file)) { // Hanya file .sql
            $filepath_abs = $absolute_backup_dir . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => round(filesize($filepath_abs) / 1024 / 1024, 2), // Size in MB
                'date' => filemtime($filepath_abs)
            ];
        }
    }
    // Urutkan berdasarkan tanggal terbaru
    usort($backup_files, function($a, $b) {
        return $b['date'] <=> $a['date'];
    });
} else {
    // Jika folder backup belum ada, buat.
    @mkdir($absolute_backup_dir, 0755, true);
    if (!is_dir($absolute_backup_dir)) {
        $pesan .= '<div class="alert alert-danger">Direktori backup database (' . htmlspecialchars($backup_dir) . ') tidak ditemukan atau tidak dapat dibuat. Pastikan izin folder benar.</div>';
    }
}

// Dapatkan daftar file backup berkas (uploads)
$uploads_backup_dir = 'assets/uploads/file_backups/'; // Folder baru untuk backup file uploads
$absolute_uploads_backup_dir = __DIR__ . '/../../' . $uploads_backup_dir;

$uploads_backup_files = [];
if (is_dir($absolute_uploads_backup_dir)) {
    $files = scandir($absolute_uploads_backup_dir);
    foreach ($files as $file) {
        if (preg_match('/\.zip$/', $file)) { // Hanya file .zip
            $filepath_abs = $absolute_uploads_backup_dir . $file;
            $uploads_backup_files[] = [
                'name' => $file,
                'size' => round(filesize($filepath_abs) / 1024 / 1024, 2), // Size in MB
                'date' => filemtime($filepath_abs)
            ];
        }
    }
    usort($uploads_backup_files, function($a, $b) {
        return $b['date'] <=> $a['date'];
    });
} else {
    @mkdir($absolute_uploads_backup_dir, 0755, true);
    if (!is_dir($absolute_uploads_backup_dir)) {
        $pesan .= '<div class="alert alert-danger">Direktori backup berkas (' . htmlspecialchars($uploads_backup_dir) . ') tidak ditemukan atau tidak dapat dibuat. Pastikan izin folder benar.</div>';
    }
}
?>

<section class="content">
    <div class="container-fluid">
        <?php echo $pesan; ?>

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Informasi Koneksi Database Saat Ini</h3>
            </div>
            <div class="card-body">
                <p>Informasi koneksi ini digunakan oleh sistem untuk berinteraksi dengan database. **Mengubah ini tanpa pemahaman yang benar dapat merusak fungsi sistem.**</p>
                <dl class="row">
                    <dt class="col-sm-3">Host:</dt>
                    <dd class="col-sm-9"><code><?php echo htmlspecialchars($db_host_display); ?></code></dd>
                    <dt class="col-sm-3">Nama Database:</dt>
                    <dd class="col-sm-9"><code><?php echo htmlspecialchars($db_name_display); ?></code></dd>
                    <dt class="col-sm-3">Username:</dt>
                    <dd class="col-sm-9"><code><?php echo htmlspecialchars($db_user_display); ?></code></dd>
                    <dt class="col-sm-3">Password:</dt>
                    <dd class="col-sm-9"><span class="text-muted">*********** (Tidak ditampilkan untuk keamanan)</span></dd>
                </dl>
                <div class="alert alert-warning text-sm mt-3">
                    **Peringatan:** Untuk mengubah detail koneksi database, Anda **harus mengedit file <code>database_credentials.php</code>** secara langsung di server Anda (lokasi: <code>/home/config/database_credentials.php</code>). Sistem ini tidak menyediakan antarmuka *in-app* untuk mengubah kredensial karena risiko tinggi.
                </div>
            </div>
        </div>

        <div class="card card-success">
            <div class="card-header"><h3 class="card-title">Manajemen Database SQL</h3></div>
            <div class="card-body">
                <h5><i class="fas fa-database mr-1"></i> Backup Database SQL</h5>
                <p>Buat salinan (*backup*) dari database Anda. Ini sangat penting untuk pemulihan data jika terjadi masalah.</p>
                <form action="proses_pengaturan_database.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup database sekarang? Proses ini mungkin memakan waktu.');">
                    <input type="hidden" name="action" value="backup_db">
                    <div class="form-group">
                        <label for="backup_filename_prefix">Prefix Nama File Backup (Opsional):</label>
                        <input type="text" class="form-control form-control-sm" id="backup_filename_prefix" name="prefix" placeholder="Misal: db_reaktor_full">
                        <small class="form-text text-muted">Nama file akan menjadi [prefix]_YYYYMMDD_HHMMSS.sql. Jika kosong, defaultnya `backup_YYYYMMDD_HHMMSS.sql`.</small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-download mr-1"></i> Mulai Backup</button>
                </form>
                <hr class="my-4">

                <h5><i class="fas fa-undo-alt mr-1"></i> Restore Database SQL dari File Baru</h5>
                <p class="text-danger">**PERHATIAN:** Proses ini akan **MENIMPA data yang ada di database Anda saat ini. Lakukan dengan sangat hati-hati!**</p>
                <form action="proses_pengaturan_database.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('APAKAH ANDA SANGAT YAKIN INGIN MELAKUKAN RESTORE DATABASE? SEMUA DATA SAAT INI AKAN HILANG DAN DIGANTIKAN DENGAN DATA DARI FILE BACKUP. PROSES INI TIDAK DAPAT DIBATALKAN!');">
                    <input type="hidden" name="action" value="restore_db">
                    <div class="form-group">
                        <label for="sql_file_to_restore">Pilih File SQL untuk Restore (.sql - Max 50MB):</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="sql_file_to_restore" name="sql_file" accept=".sql" required>
                                <label class="custom-file-label" for="sql_file_to_restore">Pilih file .sql</label>
                            </div>
                        </div>
                        <small class="form-text text-muted">Hanya terima file `.sql`.</small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-upload mr-1"></i> Unggah & Mulai Restore</button>
                </form>
            </div>
        </div>

        <div class="card card-warning">
            <div class="card-header"><h3 class="card-title">Manajemen Berkas Massal (Folder Uploads)</h3></div>
            <div class="card-body">
                <h5><i class="fas fa-file-archive mr-1"></i> Backup Semua Berkas Sistem</h5>
                <p>Unduh semua berkas yang diunggah (foto, KTP, KK, SK, bukti) dari folder <code>assets/uploads/</code> sebagai satu file ZIP. Ini adalah backup file sistem, BUKAN database.</p>
                <form action="proses_pengaturan_database.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup semua berkas sekarang? Proses ini mungkin memakan waktu dan ukuran file bisa besar.');">
                    <input type="hidden" name="action" value="backup_all_files">
                    <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-download mr-1"></i> Unduh Semua Berkas (ZIP)</button>
                </form>
                <hr class="my-4">

                <h5><i class="fas fa-folder-open mr-1"></i> Pulihkan Semua Berkas dari ZIP (Restore File System)</h5>
                <p class="text-danger">**PERINGATAN KERAS:** Proses ini akan **menghapus semua file yang ada di folder <code>assets/uploads/</code> saat ini** dan menggantinya dengan isi file ZIP yang Anda unggah. Lakukan dengan sangat hati-hati! Pastikan file ZIP Anda berisi struktur folder `uploads/` yang benar.</p>
                <form action="proses_pengaturan_database.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('APAKAH ANDA SANGAT YAKIN INGIN MELAKUKAN PEMULIHAN BERKAS SISTEM? SEMUA FILE YANG ADA DI SERVER SAAT INI AKAN HILANG DAN DIGANTIKAN DENGAN FILE DARI ZIP. PROSES INI TIDAK DAPAT DIBATALKAN!');">
                    <input type="hidden" name="action" value="restore_all_files">
                    <div class="form-group">
                        <label for="zip_file_to_restore">Pilih File ZIP Berkas (.zip - Max 500MB):</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="zip_file_to_restore" name="zip_file_to_restore" accept=".zip" required>
                                <label class="custom-file-label" for="zip_file_to_restore">Pilih file .zip</label>
                            </div>
                        </div>
                        <small class="form-text text-muted">Hanya terima file `.zip`.</small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-upload mr-1"></i> Unggah & Pulihkan Berkas</button>
                </form>
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header"><h3 class="card-title">File Backup Database SQL yang Tersedia</h3></div>
            <div class="card-body">
                <?php if (!empty($backup_files)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Nama File</th>
                                    <th>Ukuran (MB)</th>
                                    <th>Tanggal Backup</th>
                                    <th style="width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backup_files as $file): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($file['name']); ?></code></td>
                                        <td><?php echo htmlspecialchars($file['size']); ?></td>
                                        <td><?php echo date('d M Y H:i:s', $file['date']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($backup_dir . $file['name']); ?>" class="btn btn-xs btn-primary" download><i class="fas fa-download"></i> Unduh</a>
                                            <form action="proses_pengaturan_database.php" method="post" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus file backup ini?');">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> Hapus</button>
                                            </form>
                                            <form action="proses_pengaturan_database.php" method="post" style="display:inline-block;" onsubmit="return confirm('APAKAH ANDA SANGAT YAKIN INGIN MELAKUKAN RESTORE DARI FILE INI? SEMUA DATA SAAT INI AKAN HILANG DAN DIGANTIKAN DENGAN DATA DARI FILE BACKUP INI. PROSES INI TIDAK DAPAT DIBATALKAN!');">
                                                <input type="hidden" name="action" value="restore_db_from_existing">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                <button type="submit" class="btn btn-xs btn-info"><i class="fas fa-undo"></i> Restore</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">Tidak ada file backup database yang ditemukan.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header"><h3 class="card-title">File Backup Berkas (Uploads) yang Tersedia</h3></div>
            <div class="card-body">
                <?php if (!empty($uploads_backup_files)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Nama File</th>
                                    <th>Ukuran (MB)</th>
                                    <th>Tanggal Backup</th>
                                    <th style="width: 150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($uploads_backup_files as $file): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($file['name']); ?></code></td>
                                        <td><?php echo htmlspecialchars($file['size']); ?></td>
                                        <td><?php echo date('d M Y H:i:s', $file['date']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($uploads_backup_dir . $file['name']); ?>" class="btn btn-xs btn-primary" download><i class="fas fa-download"></i> Unduh</a>
                                            <form action="proses_pengaturan_database.php" method="post" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus file backup ini?');">
                                                <input type="hidden" name="action" value="delete_file_backup">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> Hapus</button>
                                            </form>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">Tidak ada file backup berkas yang ditemukan.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php
require_once(__DIR__ . '/../../core/footer.php'); // PATH BARU
?>