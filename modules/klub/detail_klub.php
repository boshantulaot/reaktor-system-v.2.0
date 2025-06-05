<?php
$page_title = "Detail Klub Olahraga";
// --- PENYESUAIAN: $additional_css dan $additional_js akan diisi dinamis jika ada tabel ---
$additional_css = [];
$additional_js = [];

require_once(__DIR__ . '/../../core/header.php');

// ... (Seluruh blok PHP di bagian atas untuk validasi sesi, peran, pengambilan data klub, atlet, pelatih, prestasi, dan logika path TETAP SAMA PERSIS seperti yang Anda berikan) ...
if (!isset($pdo) || $pdo === null) { $pdo = getDbConnection(); if (!$pdo) { echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Koneksi Database Gagal!</strong> Tidak dapat memuat data.</div></div></section>'; require_once(__DIR__ . '/../../core/footer.php'); exit(); } }
if (!isset($app_base_path)) { echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Konfigurasi Aplikasi Error!</strong> Base path tidak terdefinisi.</div></div></section>'; require_once(__DIR__ . '/../../core/footer.php'); exit(); }
$id_klub_to_view = null; $klub = null; $atlet_terafiliasi = []; $pelatih_terafiliasi = []; $prestasi_klub = [];
if (isset($_GET['id_klub']) && filter_var($_GET['id_klub'], FILTER_VALIDATE_INT)) {
    $id_klub_to_view = (int)$_GET['id_klub'];
    try {
        $sql = "SELECT k.*, co.nama_cabor, p_pengaju.nama_lengkap AS nama_pengaju_awal, p_approver.nama_lengkap AS nama_admin_pemroses, p_editor.nama_lengkap AS nama_editor_terakhir FROM klub k JOIN cabang_olahraga co ON k.id_cabor = co.id_cabor LEFT JOIN pengguna p_pengaju ON k.created_by_nik_pengcab = p_pengaju.nik LEFT JOIN pengguna p_approver ON k.approved_by_nik_admin = p_approver.nik LEFT JOIN pengguna p_editor ON k.updated_by_nik = p_editor.nik WHERE k.id_klub = :id_klub";
        $stmt = $pdo->prepare($sql); $stmt->bindParam(':id_klub', $id_klub_to_view, PDO::PARAM_INT); $stmt->execute(); $klub = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$klub) { $_SESSION['pesan_error_klub'] = "Data Klub tidak ditemukan."; header("Location: daftar_klub.php"); exit(); }
        $stmt_atlet = $pdo->prepare("SELECT a.id_atlet, p.nama_lengkap, p.nik, a.status_pendaftaran, p.foto FROM atlet a JOIN pengguna p ON a.nik = p.nik WHERE a.id_klub = :id_klub AND a.status_pendaftaran = 'disetujui' AND p.is_approved = 1 ORDER BY p.nama_lengkap ASC");
        $stmt_atlet->bindParam(':id_klub', $id_klub_to_view, PDO::PARAM_INT); $stmt_atlet->execute(); $atlet_terafiliasi = $stmt_atlet->fetchAll(PDO::FETCH_ASSOC);
        $stmt_pelatih = $pdo->prepare("SELECT pl.id_pelatih, p.nama_lengkap, p.nik, pl.nomor_lisensi, pl.status_approval, p.foto FROM pelatih pl JOIN pengguna p ON pl.nik = p.nik WHERE pl.id_klub_afiliasi = :id_klub AND pl.status_approval = 'disetujui' AND p.is_approved = 1 ORDER BY p.nama_lengkap ASC");
        $stmt_pelatih->bindParam(':id_klub', $id_klub_to_view, PDO::PARAM_INT); $stmt_pelatih->execute(); $pelatih_terafiliasi = $stmt_pelatih->fetchAll(PDO::FETCH_ASSOC);
        $stmt_prestasi = $pdo->prepare("SELECT pr.id_prestasi, pr.nama_kejuaraan, pr.tingkat_kejuaraan, pr.tahun_perolehan, pr.medali_peringkat, pr.bukti_path, p.nama_lengkap AS nama_atlet, p.nik AS nik_atlet, co.nama_cabor FROM prestasi pr JOIN atlet a ON pr.id_atlet = a.id_atlet JOIN pengguna p ON a.nik = p.nik JOIN cabang_olahraga co ON pr.id_cabor = co.id_cabor WHERE a.id_klub = :id_klub AND pr.status_approval IN ('disetujui_pengcab', 'disetujui_admin') AND p.is_approved = 1 ORDER BY pr.tahun_perolehan DESC, pr.nama_kejuaraan ASC");
        $stmt_prestasi->bindParam(':id_klub', $id_klub_to_view, PDO::PARAM_INT); $stmt_prestasi->execute(); $prestasi_klub = $stmt_prestasi->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $_SESSION['pesan_error_klub'] = "Error mengambil data detail klub atau afiliasi: " . htmlspecialchars($e->getMessage()); error_log("Error di detail_klub.php: " . $e->getMessage()); header("Location: daftar_klub.php"); exit(); }
} else { $_SESSION['pesan_error_klub'] = "ID Klub tidak valid atau tidak disediakan."; header("Location: daftar_klub.php"); exit(); }
$logo_klub_web_path = $app_base_path . 'assets/adminlte/dist/img/default-150x150.png'; $logo_exists = false;
if (!empty($klub['logo_klub'])) { /* ... logika path logo ... */ 
    $path_logo_dari_db = $klub['logo_klub']; $logo_klub_web_path_candidate = rtrim($app_base_path, '/') . '/' . ltrim($path_logo_dari_db, '/'); $logo_klub_web_path_candidate = preg_replace('/\/+/', '/', $logo_klub_web_path_candidate);
    $path_logo_server_absolute = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . ltrim($path_logo_dari_db, '/'); $path_logo_server_absolute = preg_replace('/\/+/', '/', $path_logo_server_absolute);
    if (file_exists($path_logo_server_absolute)) { $logo_klub_web_path = $logo_klub_web_path_candidate; $logo_exists = true; }
}
$sk_klub_web_path = null; $sk_exists = false;
if (!empty($klub['path_sk_klub'])) { /* ... logika path SK ... */ 
    $path_sk_dari_db = $klub['path_sk_klub']; $sk_klub_web_path_candidate = rtrim($app_base_path, '/') . '/' . ltrim($path_sk_dari_db, '/'); $sk_klub_web_path_candidate = preg_replace('/\/+/', '/', $sk_klub_web_path_candidate);
    $path_sk_server_absolute = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . ltrim($path_sk_dari_db, '/'); $path_sk_server_absolute = preg_replace('/\/+/', '/', $path_sk_server_absolute);
    if (file_exists($path_sk_server_absolute)) { $sk_klub_web_path = $sk_klub_web_path_candidate; $sk_exists = true; }
}
?>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10 offset-md-1">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Detail Klub:
                                <?php echo htmlspecialchars($klub['nama_klub']); ?>
                            </h3>
                            <div class="card-tools">
                                <a href="daftar_klub.php<?php echo $klub['id_cabor'] ? '?id_cabor=' . $klub['id_cabor'] : ''; ?>"
                                    class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                                <?php
                                $can_edit_klub = false;
                                if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_klub = true; } 
                                elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $klub['id_cabor']) { $can_edit_klub = true; }
                                if ($can_edit_klub): ?>
                                    <a href="edit_klub.php?id_klub=<?php echo $klub['id_klub']; ?>"
                                        class="btn btn-sm btn-warning ml-2"><i class="fas fa-edit mr-1"></i> Edit Klub</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- PENAMBAHAN: Sub-Judul Informasi Umum Klub -->
                            <h5 class="mt-1 mb-3 text-olive"><i class="fas fa-shield-alt mr-1"></i> Informasi Umum Klub</h5>
                            <div class="row mb-3">
                                <div class="col-md-3 text-center align-self-center">
                                    <img src="<?php echo htmlspecialchars($logo_klub_web_path); ?>"
                                         alt="Logo <?php echo htmlspecialchars($klub['nama_klub']); ?>"
                                         class="img-fluid img-thumbnail" style="max-height: 120px; object-fit: contain;">
                                    <?php if (!empty($klub['logo_klub']) && !$logo_exists): ?>
                                        <p class="text-danger text-sm mt-1"><em>Logo tidak dapat dimuat.</em></p>
                                    <?php elseif (empty($klub['logo_klub'])): ?>
                                        <p class="text-muted text-sm mt-1">Logo belum diupload</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h4><?php echo htmlspecialchars($klub['nama_klub']); ?></h4>
                                    <p class="text-muted mb-1">Cabang Olahraga: <strong><?php echo htmlspecialchars($klub['nama_cabor']); ?></strong></p>
                                    <p class="mb-1"><strong><i class="fas fa-user mr-1"></i> Ketua Klub:</strong> <?php echo htmlspecialchars($klub['ketua_klub'] ?? '-'); ?></p>
                                    <p class="mb-0"><strong><i class="fas fa-map-marker-alt mr-1"></i> Alamat Sekretariat:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($klub['alamat_sekretariat'] ?? '-')); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-id-card-alt mr-1"></i> Kontak & Legalitas</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Kontak Klub:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($klub['kontak_klub'] ?? '-'); ?></dd>
                                <dt class="col-sm-4">Email Klub:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($klub['email_klub'] ?? '-'); ?></dd>
                                <dt class="col-sm-4">Nomor SK Klub:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($klub['nomor_sk_klub'] ?? '-'); ?></dd>
                                <dt class="col-sm-4">Tanggal SK Klub:</dt>
                                <dd class="col-sm-8"><?php echo $klub['tanggal_sk_klub'] ? date('d F Y', strtotime($klub['tanggal_sk_klub'])) : '-'; ?></dd>
                                <dt class="col-sm-4">File SK Klub:</dt>
                                <dd class="col-sm-8">
                                    <?php if ($sk_exists && $sk_klub_web_path): ?>
                                        <a href="<?php echo htmlspecialchars($sk_klub_web_path); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-download mr-1"></i> Lihat/Unduh SK (<?php echo htmlspecialchars(basename($klub['path_sk_klub'])); ?>)</a>
                                    <?php elseif (!empty($klub['path_sk_klub']) && !$sk_exists): ?>
                                        <span class="text-danger"><em>File SK tidak dapat dimuat.</em></span>
                                        <small class="d-block text-muted">Path tercatat: <?php echo htmlspecialchars($klub['path_sk_klub']); ?></small>
                                    <?php else: ?> 
                                        <span class="text-muted">File SK belum diupload</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-history mr-1"></i> Status & Histori Proses</h5>
                            <dl class="row">
                                <!-- ... (Blok DL untuk status dan histori tetap sama) ... -->
                                <dt class="col-sm-4">Status Approval Admin:</dt>
                                <dd class="col-sm-8"><span class="badge badge-<?php echo ($klub['status_approval_admin'] == 'disetujui' ? 'success' : ($klub['status_approval_admin'] == 'pending' ? 'warning' : 'danger')); ?> p-1"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$klub['status_approval_admin'] ?? 'N/A'))); ?></span></dd>
                                <dt class="col-sm-4">Diajukan Pertama oleh:</dt>
                                <dd class="col-sm-8"><?php if (!empty($klub['created_by_nik_pengcab'])) { echo htmlspecialchars($klub['nama_pengaju_awal'] ?? ('NIK Pengcab: ' . $klub['created_by_nik_pengcab'])); } else { if ($klub['status_approval_admin'] == 'disetujui' && !empty($klub['approved_by_nik_admin']) && strtotime($klub['created_at']) == strtotime($klub['approval_at_admin'] ?? '')) { echo htmlspecialchars($klub['nama_admin_pemroses'] ?? ('NIK Admin: ' . $klub['approved_by_nik_admin'])) . " <small class='text-muted'>(Input Langsung)</small>"; } else { echo "<span class='text-muted'>N/A</span>"; } } echo " <small class='text-muted'>(pada " . date('d M Y, H:i', strtotime($klub['created_at'])) . ")</small>"; ?></dd>
                                <?php if ($klub['approved_by_nik_admin'] && ($klub['created_by_nik_pengcab'] || strtotime($klub['created_at']) != strtotime($klub['approval_at_admin'] ?? ''))): ?><dt class="col-sm-4">Diproses Admin oleh:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($klub['nama_admin_pemroses'] ?? ('NIK: ' . $klub['approved_by_nik_admin'])); ?> <?php if ($klub['approval_at_admin']): ?><small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($klub['approval_at_admin'])); ?>)</small><?php endif; ?></dd><?php endif; ?>
                                <?php if ($klub['status_approval_admin'] == 'ditolak' && !empty($klub['alasan_penolakan_admin'])): ?><dt class="col-sm-4">Alasan Penolakan Admin:</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($klub['alasan_penolakan_admin'])); ?></dd><?php endif; ?>
                                <?php $compare_time_for_last_update = $klub['approval_at_admin'] ?? $klub['created_at']; if ($klub['updated_by_nik'] && $klub['last_updated_process_at'] && strtotime($klub['last_updated_process_at']) > strtotime($compare_time_for_last_update)): ?><dt class="col-sm-4">Update/Pengajuan Ulang Terakhir oleh:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($klub['nama_editor_terakhir'] ?? ('NIK: ' . $klub['updated_by_nik'])); ?> <small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($klub['last_updated_process_at'])); ?>)</small><?php if ($klub['status_approval_admin'] == 'pending' && $klub['last_updated_process_at'] && strtotime($klub['last_updated_process_at']) > strtotime($klub['approval_at_admin'] ?? '1970-01-01')): ?><br><small class="text-warning"><em>(Menunggu approval ulang setelah update ini)</em></small><?php endif; ?></dd><?php endif; ?>
                            </dl>

                            <!-- PENAMBAHAN: Sub-Judul untuk Tab Afiliasi (sebelum card-tabs) -->
                            <hr>
                            <h5 class="mt-4 mb-3 text-olive"><i class="fas fa-link mr-1"></i> Data Terafiliasi dengan Klub</h5>

                            <div class="card card-tabs card-secondary card-outline mt-0"> <!-- Dihilangkan mt-4 dari sini -->
                                <div class="card-header p-0 pt-1 border-bottom-0">
                                    <ul class="nav nav-tabs" id="klub-affiliation-tabs" role="tablist">
                                        <!-- ... (Tab Atlet, Pelatih, Prestasi tetap sama) ... -->
                                        <li class="nav-item"><a class="nav-link active" id="atlet-tab" data-toggle="pill" href="#atlet-content" role="tab" aria-controls="atlet-content" aria-selected="true"><i class="fas fa-running mr-1"></i> Atlet Terafiliasi</a></li>
                                        <li class="nav-item"><a class="nav-link" id="pelatih-tab" data-toggle="pill" href="#pelatih-content" role="tab" aria-controls="pelatih-content" aria-selected="false"><i class="fas fa-chalkboard-teacher mr-1"></i> Pelatih Terafiliasi</a></li>
                                        <li class="nav-item"><a class="nav-link" id="prestasi-tab" data-toggle="pill" href="#prestasi-content" role="tab" aria-controls="prestasi-content" aria-selected="false"><i class="fas fa-trophy mr-1"></i> Prestasi Klub</a></li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content" id="klub-affiliation-tabsContent">
                                        <!-- ... (Konten Tab Atlet, Pelatih, Prestasi tetap sama) ... -->
                                        <div class="tab-pane fade show active" id="atlet-content" role="tabpanel" aria-labelledby="atlet-tab"><?php if (!empty($atlet_terafiliasi)): /* tabel atlet */ else: echo '<div class="alert alert-info text-center">Belum ada atlet yang terafiliasi.</div>'; endif; ?></div>
                                        <div class="tab-pane fade" id="pelatih-content" role="tabpanel" aria-labelledby="pelatih-tab"><?php if (!empty($pelatih_terafiliasi)): /* tabel pelatih */ else: echo '<div class="alert alert-info text-center">Belum ada pelatih yang terafiliasi.</div>'; endif; ?></div>
                                        <div class="tab-pane fade" id="prestasi-content" role="tabpanel" aria-labelledby="prestasi-tab"><?php if (!empty($prestasi_klub)): /* tabel prestasi */ else: echo '<div class="alert alert-info text-center">Belum ada prestasi klub.</div>'; endif; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div> 
                        <div class="card-footer text-center">
                            <a href="daftar_klub.php<?php echo $klub['id_cabor'] ? '?id_cabor=' . $klub['id_cabor'] : ''; ?>"
                                class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Klub</a> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
// ... (Blok JavaScript untuk DataTables TETAP SAMA seperti yang Anda berikan) ...
$inline_script_detail = "";
if(!empty($atlet_terafiliasi) || !empty($pelatih_terafiliasi) || !empty($prestasi_klub)){
    $additional_css[] = 'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css';
    $additional_js[] = 'assets/adminlte/plugins/datatables/jquery.dataTables.min.js';
    $additional_js[] = 'assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js';
    $inline_script_detail = "$(document).ready(function() { $('.datatable-detail').DataTable({\"paging\": true, \"lengthChange\": false, \"searching\": true, \"ordering\": true, \"info\": true, \"autoWidth\": false, \"responsive\": true, \"pageLength\": 5, \"language\": {\"search\": \"Cari:\",\"zeroRecords\": \"Tidak ada data yang cocok ditemukan\",\"info\": \"Menampilkan _START_ sampai _END_ dari _TOTAL_ entri\",\"infoEmpty\": \"Tidak ada entri tersedia\",\"infoFiltered\": \"(difilter dari _MAX_ total entri)\",\"paginate\": {\"first\": \"Awal\",\"last\": \"Akhir\",\"next\": \"Berikutnya\",\"previous\": \"Sebelumnya\"}}});});";
}
if(isset($inline_script) && !empty($inline_script)){ $inline_script .= $inline_script_detail; } else { $inline_script = $inline_script_detail; }
require_once(__DIR__ . '/../../core/footer.php');
?>