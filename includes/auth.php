<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Kimlik Doğrulama Kontrolü
 */

// Cache önleme - her zaman sunucudan çek
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Session kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Script'in bulunduğu dizini bul ve login.php'ye yönlendir
    $loginUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/login.php';
    // Eğer includes klasöründen çağrılıyorsa bir üst dizine git
    if (strpos($loginUrl, 'includes') !== false || strpos($loginUrl, 'api') !== false) {
        $loginUrl = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/login.php';
    }
    header('Location: ' . $loginUrl);
    exit;
}

// Kullanıcı bilgilerini al
$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? '',
    'name' => $_SESSION['user_name'] ?? 'Kullanıcı',
    'role' => $_SESSION['user_role'] ?? 'kullanici'
];

// Admin kontrolü fonksiyonu
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Kullanıcı adını döndür
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'Kullanıcı';
}
