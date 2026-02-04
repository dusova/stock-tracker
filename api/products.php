<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Ürünler API
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Oturum açmanız gerekiyor.'], 401);
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Ürün listesi veya tek ürün
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM urunler WHERE id = ?");
                $stmt->execute([intval($_GET['id'])]);
                $product = $stmt->fetch();
                jsonResponse($product ?: ['error' => 'Ürün bulunamadı'], $product ? 200 : 404);
            } else {
                $stmt = $db->query("SELECT u.*, k.kategori_adi FROM urunler u LEFT JOIN kategoriler k ON u.kategori_id = k.id WHERE u.durum = 'aktif' ORDER BY u.urun_adi");
                jsonResponse($stmt->fetchAll());
            }
            break;

        case 'POST':
            // Yeni ürün ekle
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $stmt = $db->prepare("
                INSERT INTO urunler (urun_kodu, urun_adi, kategori_id, alis_fiyati, satis_fiyati, mevcut_stok, kritik_stok, aciklama)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitize($data['urun_kodu']),
                sanitize($data['urun_adi']),
                $data['kategori_id'] ?: null,
                floatval($data['alis_fiyati']),
                floatval($data['satis_fiyati']),
                intval($data['mevcut_stok']),
                intval($data['kritik_stok'] ?? 10),
                sanitize($data['aciklama'] ?? '')
            ]);
            
            jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Ürün başarıyla eklendi.']);
            break;

        case 'PUT':
            // Ürün güncelle
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("
                UPDATE urunler SET 
                    urun_kodu = ?, urun_adi = ?, kategori_id = ?, alis_fiyati = ?, 
                    satis_fiyati = ?, mevcut_stok = ?, kritik_stok = ?, aciklama = ?
                WHERE id = ?
            ");
            $stmt->execute([
                sanitize($data['urun_kodu']),
                sanitize($data['urun_adi']),
                $data['kategori_id'] ?: null,
                floatval($data['alis_fiyati']),
                floatval($data['satis_fiyati']),
                intval($data['mevcut_stok']),
                intval($data['kritik_stok']),
                sanitize($data['aciklama'] ?? ''),
                intval($data['id'])
            ]);
            
            jsonResponse(['success' => true, 'message' => 'Ürün başarıyla güncellendi.']);
            break;

        case 'DELETE':
            // Ürün sil
            $data = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
            $id = intval($data['id'] ?? $_GET['id'] ?? 0);
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Ürün ID gerekli.'], 400);
            }
            
            $stmt = $db->prepare("DELETE FROM urunler WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse(['success' => true, 'message' => 'Ürün başarıyla silindi.']);
            break;

        default:
            jsonResponse(['error' => 'Geçersiz istek metodu'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()], 500);
}
