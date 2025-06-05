<?php
// home/htdocs/index.php
// Halaman ini bisa berfungsi sebagai halaman landing atau redirect langsung ke login
// atau dashboard jika sudah login.

session_start(); // Mulai session

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_login_status']) && $_SESSION['user_login_status'] === true) {
    header("Location: dashboard.php");
    exit();
} else {
    // Jika belum login, redirect ke halaman login
    header("Location: auth/login.php");
    exit();
}
?>