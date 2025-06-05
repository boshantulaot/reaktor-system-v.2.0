<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../core/init_core.php'); // Memuat $pdo, APP_URL_BASE, audit_helper.php

if (!$pdo instanceof PDO) {
    $_SESSION['login_error'] = "Koneksi Database Gagal. Hubungi administrator. (Error: PDO_NOT_AVAILABLE_LOGIN).";
    error_log("PROSES_LOGIN_FATAL: Objek PDO tidak tersedia setelah memuat init_core.php.");
    header("Location: login.php");
    exit();
}

$remember_me_duration = 30 * 24 * 60 * 60; // 30 hari

function clearRememberMeTokens($db_connection, $nik_user) {
    if ($db_connection && $nik_user) {
        try {
            $stmt = $db_connection->prepare("UPDATE pengguna SET remember_selector = NULL, remember_validator_hash = NULL, remember_expires_at = NULL WHERE nik = :nik");
            $stmt->bindParam(':nik', $nik_user);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("PROSES_LOGIN_ERROR (clearRememberMeTokens): Gagal membersihkan token remember_me untuk NIK " . $nik_user . ". Pesan: " . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && isset($_POST['password']) && !empty(trim($_POST['username'])) && !empty(trim($_POST['password']))) {
        
        $username_input = trim($_POST['username']);
        $password_input = $_POST['password'];
        $login_attempt_data = ['attempted_input' => $username_input, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'];

        try {
            $stmt_user = $pdo->prepare(
                "SELECT nik, password, nama_lengkap, is_approved, foto, created_at
                 FROM pengguna
                 WHERE (nik = :nik_username OR email = :email_username)"
            );
            $stmt_user->bindParam(':nik_username', $username_input);
            $stmt_user->bindParam(':email_username', $username_input);
            $stmt_user->execute();
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($user['is_approved'] == 1) {
                    if (password_verify($password_input, $user['password'])) {
                        // Login Berhasil
                        $_SESSION['user_nik'] = $user['nik'];
                        $_SESSION['nama_pengguna'] = $user['nama_lengkap'];
                        $_SESSION['user_login_status'] = true;
                        $_SESSION['user_foto'] = $user['foto'];
                        $_SESSION['user_created_at'] = $user['created_at'];
                        $_SESSION['last_activity'] = time();

                        clearRememberMeTokens($pdo, $user['nik']);

                        if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                            try {
                                $selector = bin2hex(random_bytes(16));
                                $validator = bin2hex(random_bytes(32));
                                $validator_hash = password_hash($validator, PASSWORD_DEFAULT);
                                $expires_at = date('Y-m-d H:i:s', time() + $remember_me_duration);

                                $stmt_remember = $pdo->prepare(
                                    "UPDATE pengguna 
                                     SET remember_selector = :selector, remember_validator_hash = :validator_hash, remember_expires_at = :expires_at 
                                     WHERE nik = :nik"
                                );
                                $stmt_remember->execute([
                                    ':selector' => $selector,
                                    ':validator_hash' => $validator_hash,
                                    ':expires_at' => $expires_at,
                                    ':nik' => $user['nik']
                                ]);
                                
                                $cookie_path = rtrim(parse_url(APP_URL_BASE, PHP_URL_PATH) ?: '/', '/') . '/';
                                setcookie('remember_me_reaktor', $selector . ':' . $validator, time() + $remember_me_duration, $cookie_path, "", (APP_URL_BASE && strpos(APP_URL_BASE, 'https://') === 0), true); // Secure flag true jika HTTPS
                            } catch (Exception $e) {
                                error_log("PROSES_LOGIN_ERROR (RememberMe): Gagal membuat token Remember Me. Pesan: " . $e->getMessage());
                            }
                        }
                        
                        // --- Logika Penentuan Peran ---
                        $_SESSION['roles_data'] = [];
                        $_SESSION['user_role_utama'] = 'guest'; // Default, akan ditimpa jika peran ditemukan
                        $_SESSION['id_cabor_pengurus_utama'] = null;
                        
                        $role_priority = [
                            'super_admin'    => 100,
                            'admin_koni'     => 90,
                            'pengurus_cabor' => 50,
                            'pelatih'        => 20,
                            'wasit'          => 20,
                            'atlet'          => 10,
                            'view_only'      => 5,
                            'guest'          => 0 
                        ];
                        $current_highest_priority = $role_priority['guest'];

                        // 1. Cek Peran dari Tabel 'anggota' (Super Admin, Admin KONI, Pengurus Cabor)
                        $stmt_roles_anggota = $pdo->prepare(
                            "SELECT a.role, a.id_cabor, c.nama_cabor, a.jabatan 
                             FROM anggota a 
                             LEFT JOIN cabang_olahraga c ON a.id_cabor = c.id_cabor 
                             WHERE a.nik = :nik AND a.is_verified = 1" // Pastikan is_verified = 1
                        );
                        $stmt_roles_anggota->bindParam(':nik', $user['nik']);
                        $stmt_roles_anggota->execute();
                        $db_roles_data_anggota = $stmt_roles_anggota->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($db_roles_data_anggota)) {
                            foreach ($db_roles_data_anggota as $role_info) {
                                $role_spesifik = $role_info['role'];
                                $role_detail = [
                                    'tipe_peran' => 'anggota_organisasi',
                                    'role_spesifik' => $role_spesifik,
                                    'id_cabor' => $role_info['id_cabor'],
                                    'nama_cabor' => $role_info['nama_cabor'],
                                    'detail_jabatan' => $role_info['jabatan']
                                ];
                                $_SESSION['roles_data'][] = $role_detail;

                                if (isset($role_priority[$role_spesifik]) && $role_priority[$role_spesifik] > $current_highest_priority) {
                                    $_SESSION['user_role_utama'] = $role_spesifik;
                                    $current_highest_priority = $role_priority[$role_spesifik];
                                    if ($role_spesifik == 'pengurus_cabor' && $role_info['id_cabor']) {
                                        $_SESSION['id_cabor_pengurus_utama'] = $role_info['id_cabor'];
                                    } else {
                                        $_SESSION['id_cabor_pengurus_utama'] = null; // Reset jika bukan pengurus cabor
                                    }
                                }
                            }
                        }
                        
                        // 2. Cek Peran Tambahan (Atlet, Pelatih, Wasit) - ini tidak akan menimpa Super Admin jika prioritasnya benar
                        // Atlet
                        $stmt_atlet_role = $pdo->prepare("SELECT id_atlet, id_cabor FROM atlet WHERE nik = :nik AND status_pendaftaran = 'disetujui'");
                        $stmt_atlet_role->execute([':nik' => $user['nik']]);
                        $atlet_roles_data = $stmt_atlet_role->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($atlet_roles_data as $ar) {
                             $role_detail_atlet = ['tipe_peran' => 'atlet', 'role_spesifik' => 'atlet', 'id_entitas_peran' => $ar['id_atlet'], 'id_cabor' => $ar['id_cabor']];
                             $_SESSION['roles_data'][] = $role_detail_atlet;
                            if (isset($role_priority['atlet']) && $role_priority['atlet'] > $current_highest_priority) {
                                $_SESSION['user_role_utama'] = 'atlet';
                                $current_highest_priority = $role_priority['atlet'];
                                $_SESSION['id_cabor_pengurus_utama'] = null; 
                            }
                        }
                        
                        // Pelatih
                        $stmt_pelatih_role = $pdo->prepare("SELECT id_pelatih FROM pelatih WHERE nik = :nik AND status_approval = 'disetujui'");
                        $stmt_pelatih_role->execute([':nik' => $user['nik']]);
                        $pelatih_data = $stmt_pelatih_role->fetch(PDO::FETCH_ASSOC); // Pelatih biasanya 1 profil per NIK
                        if($pelatih_data){
                             $role_detail_pelatih = ['tipe_peran' => 'pelatih', 'role_spesifik' => 'pelatih', 'id_entitas_peran' => $pelatih_data['id_pelatih']];
                             $_SESSION['roles_data'][] = $role_detail_pelatih;
                            if (isset($role_priority['pelatih']) && $role_priority['pelatih'] > $current_highest_priority) {
                                $_SESSION['user_role_utama'] = 'pelatih';
                                 $current_highest_priority = $role_priority['pelatih'];
                                 $_SESSION['id_cabor_pengurus_utama'] = null; 
                            }
                        }
                        
                        // Wasit
                        $stmt_wasit_role = $pdo->prepare("SELECT id_wasit FROM wasit WHERE nik = :nik AND status_approval = 'disetujui'");
                        $stmt_wasit_role->execute([':nik' => $user['nik']]);
                        $wasit_data = $stmt_wasit_role->fetch(PDO::FETCH_ASSOC); // Wasit biasanya 1 profil per NIK
                        if($wasit_data){
                            $role_detail_wasit = ['tipe_peran' => 'wasit', 'role_spesifik' => 'wasit', 'id_entitas_peran' => $wasit_data['id_wasit']];
                            $_SESSION['roles_data'][] = $role_detail_wasit;
                             if (isset($role_priority['wasit']) && $role_priority['wasit'] > $current_highest_priority) {
                                $_SESSION['user_role_utama'] = 'wasit';
                                $current_highest_priority = $role_priority['wasit'];
                                $_SESSION['id_cabor_pengurus_utama'] = null; 
                            }
                        }
                        // --- Akhir Logika Penentuan Peran ---

                        // Jika setelah semua pengecekan, peran masih 'guest' dan user login, ini aneh, fallback ke 'view_only' atau log error
                        if ($_SESSION['user_role_utama'] === 'guest' && $user['nik']) {
                            error_log("PROSES_LOGIN_WARNING: Pengguna {$user['nik']} login tapi tidak ada peran valid ditemukan. Default ke 'guest' tidak ideal.");
                            // Pertimbangkan untuk memberi peran default yang lebih aman jika tidak ada peran, atau mengarahkan ke halaman error.
                            // $_SESSION['user_role_utama'] = 'view_only'; // Contoh fallback
                        }

                        if (function_exists('catatAuditLog')) {
                            catatAuditLog(
                                $pdo,
                                $user['nik'],
                                'LOGIN_SUKSES',
                                'pengguna',
                                $user['nik'],
                                null,
                                json_encode($login_attempt_data),
                                'Pengguna berhasil login. Role Utama Ditetapkan: ' . $_SESSION['user_role_utama']
                            );
                        }
                        
                        header("Location: " . APP_URL_BASE . "/dashboard.php");
                        exit();
                    } else {
                        $_SESSION['login_error'] = "Password yang Anda masukkan salah.";
                        if (function_exists('catatAuditLog')) {
                            $login_attempt_data['reason'] = 'Invalid password';
                            catatAuditLog($pdo, $user['nik'], 'LOGIN_GAGAL', 'pengguna', $user['nik'], null, json_encode($login_attempt_data), 'Upaya login gagal: Password salah.');
                        }
                    }
                } else {
                    $_SESSION['login_error'] = "Akun Anda belum disetujui atau tidak aktif.";
                     if (function_exists('catatAuditLog')) {
                        $login_attempt_data['reason'] = 'Account not approved or inactive';
                        catatAuditLog($pdo, $user['nik'] ?? $username_input, 'LOGIN_GAGAL', 'pengguna', $user['nik'] ?? $username_input, null, json_encode($login_attempt_data), 'Upaya login gagal: Akun belum disetujui/tidak aktif.');
                    }
                }
            } else {
                $_SESSION['login_error'] = "NIK/Email tidak terdaftar.";
                if (function_exists('catatAuditLog')) {
                    $login_attempt_data['reason'] = 'User not found';
                    catatAuditLog($pdo, $username_input, 'LOGIN_GAGAL', null, null, null, json_encode($login_attempt_data), 'Upaya login gagal: NIK/Email tidak terdaftar.');
                }
            }
        } catch (PDOException $e) {
            error_log("PROSES_LOGIN_FATAL_ERROR (PDOException): " . $e->getMessage() . " || SQL State: " . $e->getCode() . " || Input Username: " . $username_input);
            $_SESSION['login_error'] = "Terjadi kesalahan sistem saat mencoba login. Silakan coba lagi nanti.";
        }
    } else {
        $_SESSION['login_error'] = "NIK/Email dan Password wajib diisi.";
    }
    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>