<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Veritabanı Bağlantı Dosyası
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Veritabanı ayarları
define('DB_HOST', 'localhost');
define('DB_NAME', 'stok_takip');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Uygulama ayarları
define('APP_NAME', 'Cemil Çalışkan Stok Takip');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/stok-takip/');

// PDO bağlantısı
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci"
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// Yardımcı fonksiyonlar
function getDB() {
    return Database::getInstance()->getConnection();
}

function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' ₺';
}

function formatDate($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

function generateCode($prefix = 'ISL') {
    return $prefix . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
