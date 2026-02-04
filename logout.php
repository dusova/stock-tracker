<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Çıkış İşlemi
 */

// Session başlat (eğer başlamadıysa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tüm session değişkenlerini temizle
$_SESSION = array();
session_unset();

// Session cookie'sini sil
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session'ı yok et
session_destroy();

// Yeni bir session başlat (temiz başlangıç için)
session_start();
session_regenerate_id(true);
session_destroy();

// Cache temizleme header'ları
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Giriş sayfasına yönlendir
header('Location: login.php');
exit;
