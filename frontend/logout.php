<?php
// Hata raporlamasını kullanıcıdan gizle
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

session_start();

// Oturumu sonlandır
session_unset();
session_destroy();

// Yeni bir oturum başlat ve CSRF token oluştur
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Giriş sayfasına yönlendir
header("Location: ../index.php");
exit;
?>