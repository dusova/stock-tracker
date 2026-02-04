<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Müşteriler API
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Oturum açmanız gerekiyor.'], 401);
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Özel eylemler
    if ($action === 'transactions' && isset($_GET['id'])) {
        // Müşterinin işlemlerini getir
        $stmt = $db->prepare("
            SELECT * FROM islemler 
            WHERE musteri_id = ? 
            ORDER BY tarih DESC 
            LIMIT 20
        ");
        $stmt->execute([intval($_GET['id'])]);
        jsonResponse($stmt->fetchAll());
    }
    
    if ($action === 'payments' && isset($_GET['id'])) {
        // Müşterinin ödemelerini getir
        $stmt = $db->prepare("
            SELECT * FROM odemeler 
            WHERE musteri_id = ? 
            ORDER BY odeme_tarihi DESC 
            LIMIT 20
        ");
        $stmt->execute([intval($_GET['id'])]);
        jsonResponse($stmt->fetchAll());
    }

    switch ($method) {
        case 'GET':
            // Müşteri listesi veya tek müşteri
            if (isset($_GET['id']) && !$action) {
                $stmt = $db->prepare("SELECT * FROM musteriler WHERE id = ?");
                $stmt->execute([intval($_GET['id'])]);
                $customer = $stmt->fetch();
                jsonResponse($customer ?: ['error' => 'Müşteri bulunamadı'], $customer ? 200 : 404);
            } else if (!$action) {
                $stmt = $db->query("SELECT * FROM musteriler WHERE durum = 'aktif' ORDER BY ad_soyad");
                jsonResponse($stmt->fetchAll());
            }
            break;

        case 'POST':
            // Yeni müşteri ekle
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $baslangicBakiye = floatval($data['baslangic_bakiye'] ?? 0);
            
            $stmt = $db->prepare("
                INSERT INTO musteriler (ad_soyad, telefon, eposta, adres, iban, baslangic_bakiye, mevcut_bakiye)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitize($data['ad_soyad']),
                sanitize($data['telefon'] ?? ''),
                sanitize($data['eposta'] ?? ''),
                sanitize($data['adres'] ?? ''),
                sanitize($data['iban'] ?? ''),
                $baslangicBakiye,
                $baslangicBakiye
            ]);
            
            jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Müşteri başarıyla eklendi.']);
            break;

        case 'PUT':
            // Müşteri güncelle
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("
                UPDATE musteriler SET 
                    ad_soyad = ?, telefon = ?, eposta = ?, adres = ?, iban = ?
                WHERE id = ?
            ");
            $stmt->execute([
                sanitize($data['ad_soyad']),
                sanitize($data['telefon'] ?? ''),
                sanitize($data['eposta'] ?? ''),
                sanitize($data['adres'] ?? ''),
                sanitize($data['iban'] ?? ''),
                intval($data['id'])
            ]);
            
            jsonResponse(['success' => true, 'message' => 'Müşteri başarıyla güncellendi.']);
            break;

        case 'DELETE':
            // Müşteri sil
            $data = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
            $id = intval($data['id'] ?? $_GET['id'] ?? 0);
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Müşteri ID gerekli.'], 400);
            }
            
            $stmt = $db->prepare("DELETE FROM musteriler WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse(['success' => true, 'message' => 'Müşteri başarıyla silindi.']);
            break;

        default:
            jsonResponse(['error' => 'Geçersiz istek metodu'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()], 500);
}
