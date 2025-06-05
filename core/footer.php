<?php
// File: public_html/reaktorsystem/core/footer.php

if (!isset($app_base_path)) {
    $app_base_path = '/'; 
    if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/reaktorsystem/') !== false) {
        $app_base_path = '/reaktorsystem/';
    }
    $app_base_path = rtrim($app_base_path, '/') . '/';
    $app_base_path = preg_replace('/\/+/', '/', $app_base_path);
}

// Ambil durasi timeout dari konstanta yang sudah didefinisikan di init_core.php
// Default ke 30 menit jika konstanta tidak ada (sebagai fallback)
$session_timeout_seconds_for_js = defined('MAX_SESSION_INACTIVITY_SECONDS') ? MAX_SESSION_INACTIVITY_SECONDS : (30 * 60);
$warning_before_timeout_seconds = 2 * 60; // Peringatan 2 menit sebelum timeout
$idle_check_duration_ms = ($session_timeout_seconds_for_js - $warning_before_timeout_seconds) * 1000;
if ($idle_check_duration_ms <= 0) { // Pastikan durasi peringatan tidak lebih besar dari timeout
    $idle_check_duration_ms = ($session_timeout_seconds_for_js * 1000) / 2; // Setengah dari total timeout
    $warning_before_timeout_seconds = $session_timeout_seconds_for_js / 2;
}

?>
            <?php // Penutup div dari header.php ?>
            </div>
        </section>
    </div>

    <?php 
    if (basename($_SERVER['PHP_SELF']) != 'login.php' && isset($user_login_status) && $user_login_status === true && isset($user_role_utama) && $user_role_utama != 'guest'):
    ?>
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block"><b>Reaktor</b> Versi 1.0</div>
        <strong>Hak Cipta © <?php echo date("Y"); ?> <a href="https://koniserdangbedagai.or.id" target="_blank">KONI Serdang Bedagai</a>.</strong> Semua hak dilindungi.
    </footer>

    <aside class="control-sidebar control-sidebar-dark"><div class="p-3"><h5>Pengaturan Tampilan</h5><p>Opsi tema.</p></div></aside>
    
    <?php // PENAMBAHAN: HTML Untuk Modal Peringatan Timeout Sesi ?>
    <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" aria-labelledby="sessionTimeoutModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="sessionTimeoutModalLabel"><i class="fas fa-exclamation-triangle"></i> Peringatan Sesi</h5>
                    <?php // Tidak ada tombol close, pengguna harus berinteraksi ?>
                </div>
                <div class="modal-body">
                    <p>Sesi Anda akan segera berakhir karena tidak ada aktivitas.</p>
                    <p>Sisa waktu: <strong id="sessionTimeoutCountdown"></strong> detik.</p>
                    <p>Klik "Lanjutkan Sesi" untuk tetap login.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="keepSessionAliveButton"><i class="fas fa-sync-alt"></i> Lanjutkan Sesi</button>
                    <button type="button" class="btn btn-danger" id="logoutNowButton"><i class="fas fa-sign-out-alt"></i> Logout Sekarang</button>
                </div>
            </div>
        </div>
    </div>
    <?php // AKHIR PENAMBAHAN MODAL ?>

    <?php endif; ?>

</div><!-- ./wrapper -->

<!-- SKRIP JAVASCRIPT INTI -->
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/js/adminlte.min.js'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/bs-custom-file-input/bs-custom-file-input.min.js'); ?>"></script>

<?php if (isset($additional_js) && is_array($additional_js)): foreach ($additional_js as $js_file): ?>
    <script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($js_file, '/')); ?>"></script>
<?php endforeach; endif; ?>

<script>
$(function () {
    console.log("Footer Global Script: Document Ready.");

    if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
        bsCustomFileInput.init();
    }
    if ($.isFunction($.fn.tooltip)) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    <?php // --- PENAMBAHAN: LOGIKA JAVASCRIPT UNTUK TIMEOUT SESI --- ?>
    <?php if ($user_login_status === true && isset($user_nik) && $user_role_utama != 'guest' && basename($_SERVER['PHP_SELF']) != 'login.php'): ?>
    
    var idleTimer = null;
    var warningTimer = null;
    var countdownTimerInterval = null;
    var maxIdleTime = <?php echo $idle_check_duration_ms; ?>; // Waktu idle sebelum peringatan (ms)
    var warningDurationBeforeLogout = <?php echo $warning_before_timeout_seconds; ?>; // Durasi peringatan (detik)
    var countdownValue;

    var heartbeatUrl = '<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/ajax/session_heartbeat.php'); ?>';
    var logoutUrl = '<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/auth/login.php?reason=js_force_timeout'); ?>'; // Atau ke logout.php dulu

    function resetIdleActivityTimer() {
        clearTimeout(idleTimer);
        clearTimeout(warningTimer);
        clearInterval(countdownTimerInterval);
        $('#sessionTimeoutModal').modal('hide');
        idleTimer = setTimeout(showSessionWarningModal, maxIdleTime);
        // console.log('Idle timer reset. Warning in ' + (maxIdleTime / 1000) + 's.');
    }

    function showSessionWarningModal() {
        // console.log('Showing session warning modal.');
        countdownValue = warningDurationBeforeLogout;
        $('#sessionTimeoutCountdown').text(countdownValue);
        $('#sessionTimeoutModal').modal('show');
        
        countdownTimerInterval = setInterval(function() {
            countdownValue--;
            $('#sessionTimeoutCountdown').text(countdownValue);
            if (countdownValue <= 0) {
                clearInterval(countdownTimerInterval);
                forceUserLogout();
            }
        }, 1000);
    }

    function keepUserSessionAlive() {
        clearInterval(countdownTimerInterval); // Hentikan countdown
        $('#sessionTimeoutModal').modal('hide');
        $.ajax({
            url: heartbeatUrl,
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response && response.status === 'success') {
                    console.log('Session keep-alive ping success.');
                    resetIdleActivityTimer();
                } else {
                    console.warn('Session keep-alive ping returned error or unexpected response:', response);
                    forceUserLogout(); // Jika heartbeat gagal, logout saja
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Session keep-alive ping AJAX error:', textStatus, errorThrown);
                forceUserLogout(); // Logout jika AJAX gagal
            }
        });
    }

    function forceUserLogout() {
        console.log('Forcing user logout due to inactivity.');
        window.location.href = logoutUrl;
    }

    // Event listeners untuk aktivitas pengguna
    $(document).on('mousemove keypress_ignored mousedown touchstart click scroll', function() {
        // Menggunakan keypress bisa terlalu sering, pertimbangkan keydown atau hanya beberapa event
        resetIdleActivityTimer();
    });
     // Khusus untuk input dan textarea agar lebih responsif saat mengetik
    $('input, textarea').on('keydown input paste', function() {
        resetIdleActivityTimer();
    });


    // Event listener untuk tombol di modal
    $('#keepSessionAliveButton').on('click', function() {
        keepUserSessionAlive();
    });
    $('#logoutNowButton').on('click', function() {
        forceUserLogout();
    });

    // Mulai timer idle saat halaman dimuat
    resetIdleActivityTimer();

    <?php endif; // Akhir if user_login_status ?>
    <?php // --- AKHIR PENAMBAHAN LOGIKA JAVASCRIPT TIMEOUT --- ?>


    // Inisialisasi DataTables Global (jika masih digunakan)
    if ($('table[id^="tabelData"]').length > 0) {
        // ... (kode DataTables global Anda bisa tetap di sini jika diperlukan) ...
    }
});
</script>





<?php if (isset($inline_script) && !empty(trim($inline_script))): ?>
<script>
    <?php echo $inline_script; ?>
</script>
<?php else: ?>
    <?php if (basename($_SERVER['PHP_SELF']) != 'login.php'): ?>
    <script>
        // console.log("Footer Script: No specific inline script provided for this page.");
    </script>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
<?php
if (ob_get_level() > 0) { ob_end_flush(); }
if (!defined('FOOTER_INCLUDED')) { define('FOOTER_INCLUDED', true); }
?>