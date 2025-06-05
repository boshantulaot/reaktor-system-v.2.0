<?php
// AKTIFKAN DISPLAY ERRORS (HANYA UNTUK DEBUGGING SEMENTARA)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Menentukan $app_base_path untuk digunakan di halaman ini jika diperlukan
// Ini adalah duplikasi logika dari header.php, idealnya $app_base_path di-set sekali saja.
// Namun, karena login.php mungkin tidak selalu include header.php, kita definisikan di sini juga untuk path aset.
$base_dir_script_login = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($base_dir_script_login === '/' || $base_dir_script_login === '\\' || $base_dir_script_login === '.') {
    $app_base_path_login = '/';
} else {
    $app_base_path_login = rtrim($base_dir_script_login, '/\\') . '/';
}
$app_base_path_login = preg_replace('/\/+/', '/', $app_base_path_login);


if (isset($_SESSION['user_login_status']) && $_SESSION['user_login_status'] === true && isset($_SESSION['user_nik'])) {
    header("Location: " . rtrim($app_base_path_login, '/') . "/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Reaktor: Sistem Manajemen Data Olahraga KONI Serdang Bedagai</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="<?php echo $app_base_path_login; ?>assets/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="<?php echo $app_base_path_login; ?>assets/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo $app_base_path_login; ?>assets/adminlte/dist/css/adminlte.min.css">
  <style>
    body.login-page { background-color: #1e3c72; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-box { width: 400px; margin-top: 3vh; margin-bottom: 80px; }
    .login-logo img { max-width: 90px; margin-bottom: 8px; }
    .login-logo a { color: #ffffff; font-size: 1.5rem; line-height: 1.2; text-decoration: none; }
    .login-logo a b { font-weight: bold; }
    .login-logo a span { font-size:0.75rem; color: #e9ecef; display: block; line-height: 1.3; }
    .card-outline.card-primary .card-header { border-bottom: 0; background-color: #007bff; color: #fff; }
    .card-outline.card-primary .card-header a { color: #fff; }
    .login-footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 0.8em; color: #bdc3c7; left: 0; padding: 8px 0; background-color: rgba(0,0,0,0.1); z-index: 10; }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#">
       <img src="<?php echo $app_base_path_login; ?>assets/uploads/logos/logo_koni.png" alt="Logo KONI Serdang Bedagai"> <br> <b>Reaktor</b><br>
       <span>Sistem Manajemen Data Olahraga<br>KONI Serdang Bedagai</span>
    </a>
  </div>
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <p class="h4 mb-0"><b>Login</b> Area</p>
    </div>
    <div class="card-body login-card-body">
      <p class="login-box-msg">Silakan login untuk memulai sesi Anda</p>

      <?php
      if (isset($_SESSION['login_error'])) {
          echo '<div class="alert alert-danger alert-dismissible fade show text-sm p-2 mb-3" role="alert">' . htmlspecialchars($_SESSION['login_error']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
          unset($_SESSION['login_error']);
      }
      if (isset($_SESSION['logout_message'])) {
          echo '<div class="alert alert-success alert-dismissible fade show text-sm p-2 mb-3" role="alert">' . htmlspecialchars($_SESSION['logout_message']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
          unset($_SESSION['logout_message']);
      }
      ?>

      <form action="proses_login.php" method="post">
        <div class="input-group mb-3">
          <input type="text" class="form-control" placeholder="NIK atau Email" name="username" required autofocus>
          <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
        </div>
        <div class="input-group mb-3">
          <input type="password" class="form-control" placeholder="Password" name="password" required>
          <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
        </div>
        <div class="row">
          <div class="col-7">
            <div class="icheck-primary">
              <input type="checkbox" id="remember" name="remember"> <label for="remember">Ingat Saya</label>
            </div>
          </div>
          <div class="col-5 text-right">
            <a href="#" class="text-sm">Lupa Password?</a>
          </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                 <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </div>
        </div>
      </form>
    </div>
  </div>
   <div class="login-footer mt-3">
        Hak Cipta © <?php echo date("Y"); ?> KONI Serdang Bedagai. All rights reserved.<br>
        <b>Reaktor</b> | Versi 1.0
    </div>
</div>

<script src="<?php echo $app_base_path_login; ?>assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_base_path_login; ?>assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_base_path_login; ?>assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>