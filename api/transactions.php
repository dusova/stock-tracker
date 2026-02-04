<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * İşlemler API
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
            // İşlem listesi veya tek işlem
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("
                    SELECT i.*, u.urun_adi, u.urun_kodu, m.ad_soyad as musteri_adi 
                    FROM islemler i 
                    LEFT JOIN urunler u ON i.urun_id = u.id 
                    LEFT JOIN musteriler m ON i.musteri_id = m.id 
                    WHERE i.id = ?
                ");
                $stmt->execute([intval($_GET['id'])]);
                $transaction = $stmt->fetch();
                jsonResponse($transaction ?: ['error' => 'İşlem bulunamadı'], $transaction ? 200 : 404);
            } else {
                $startDate = $_GET['start_date'] ?? date('Y-m-01');
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $type = $_GET['type'] ?? '';
                
                $sql = "
                    SELECT i.*, u.urun_adi, u.urun_kodu, m.ad_soyad as musteri_adi 
                    FROM islemler i 
                    LEFT JOIN urunler u ON i.urun_id = u.id 
                    LEFT JOIN musteriler m ON i.musteri_id = m.id 
                    WHERE DATE(i.tarih) BETWEEN ? AND ?
                ";
                $params = [$startDate, $endDate];
                
                if ($type) {
                    $sql .= " AND i.islem_tipi = ?";
                    $params[] = $type;
                }
                
                $sql .= " ORDER BY i.tarih DESC";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                jsonResponse($stmt->fetchAll());
            }
            break;

        case 'POST':
            // Yeni işlem ekle
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $islemNo = generateCode('ISL');
            $islemTipi = $data['islem_tipi'];
            $tarih = $data['tarih'] . ' ' . ($data['saat'] ?? date('H:i:s'));
            $urunId = intval($data['urun_id']);
            $miktar = intval($data['miktar']);
            $birimFiyat = floatval($data['birim_fiyat']);
            $kdvOrani = floatval($data['kdv_orani'] ?? 18);
            $araToplam = $miktar * $birimFiyat;
            $kdvTutari = ($araToplam * $kdvOrani) / 100;
            $genelToplam = $araToplam + $kdvTutari;
            $musteriId = !empty($data['musteri_id']) ? intval($data['musteri_id']) : null;
            $odemeSekli = $data['odeme_sekli'] ?? 'nakit';
            $not = sanitize($data['not_aciklama'] ?? '');
            
            // Stok kontrolü (satış için)
            if ($islemTipi === 'satis') {
                $stmt = $db->prepare("SELECT mevcut_stok FROM urunler WHERE id = ?");
                $stmt->execute([$urunId]);
                $urun = $stmt->fetch();
                if ($urun['mevcut_stok'] < $miktar) {
                    jsonResponse(['success' => false, 'message' => 'Yetersiz stok! Mevcut stok: ' . $urun['mevcut_stok']], 400);
                }
            }
            
            // İşlemi kaydet
            $stmt = $db->prepare("
                INSERT INTO islemler (islem_no, islem_tipi, tarih, urun_id, miktar, birim_fiyat, kdv_orani, ara_toplam, kdv_tutari, genel_toplam, musteri_id, odeme_sekli, not_aciklama)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $islemNo, $islemTipi, $tarih, $urunId, $miktar, $birimFiyat,
                $kdvOrani, $araToplam, $kdvTutari, $genelToplam, $musteriId, $odemeSekli, $not
            ]);
            $islemId = $db->lastInsertId();
            
            // Stok güncelle
            if ($islemTipi === 'satis') {
                $stmt = $db->prepare("UPDATE urunler SET mevcut_stok = mevcut_stok - ? WHERE id = ?");
                $stmt->execute([$miktar, $urunId]);
                
                // Müşteri bakiyesini güncelle (vadeli ise)
                if ($musteriId && $odemeSekli === 'vadeli') {
                    $stmt = $db->prepare("UPDATE musteriler SET mevcut_bakiye = mevcut_bakiye + ? WHERE id = ?");
                    $stmt->execute([$genelToplam, $musteriId]);
                }
            } else {
                $stmt = $db->prepare("UPDATE urunler SET mevcut_stok = mevcut_stok + ? WHERE id = ?");
                $stmt->execute([$miktar, $urunId]);
            }
            
            jsonResponse([
                'success' => true, 
                'id' => $islemId, 
                'islem_no' => $islemNo,
                'message' => 'İşlem başarıyla kaydedildi.'
            ]);
            break;

        case 'DELETE':
            // İşlem sil
            $data = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
            $id = intval($data['id'] ?? $_GET['id'] ?? 0);
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'İşlem ID gerekli.'], 400);
            }
            
            // İşlem bilgilerini al
            $stmt = $db->prepare("SELECT * FROM islemler WHERE id = ?");
            $stmt->execute([$id]);
            $islem = $stmt->fetch();
            
            if (!$islem) {
                jsonResponse(['success' => false, 'message' => 'İşlem bulunamadı.'], 404);
            }
            
            // Stoğu geri al
            if ($islem['islem_tipi'] === 'satis') {
                $stmt = $db->prepare("UPDATE urunler SET mevcut_stok = mevcut_stok + ? WHERE id = ?");
                $stmt->execute([$islem['miktar'], $islem['urun_id']]);
                
                // Müşteri bakiyesini geri al
                if ($islem['musteri_id'] && $islem['odeme_sekli'] === 'vadeli') {
                    $stmt = $db->prepare("UPDATE musteriler SET mevcut_bakiye = mevcut_bakiye - ? WHERE id = ?");
                    $stmt->execute([$islem['genel_toplam'], $islem['musteri_id']]);
                }
            } else {
                $stmt = $db->prepare("UPDATE urunler SET mevcut_stok = mevcut_stok - ? WHERE id = ?");
                $stmt->execute([$islem['miktar'], $islem['urun_id']]);
            }
            
            // İşlemi sil
            $stmt = $db->prepare("DELETE FROM islemler WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse(['success' => true, 'message' => 'İşlem başarıyla silindi.']);
            break;

        default:
            jsonResponse(['error' => 'Geçersiz istek metodu'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
