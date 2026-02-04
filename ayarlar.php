<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Ayarlar Sayfası
 */

require_once 'includes/header.php';

$db = getDB();
$message = '';
$messageType = '';

// Ayarları çek
try {
    $stmt = $db->query("SELECT anahtar, deger FROM ayarlar");
    $rows = $stmt->fetchAll();
    $ayarlar = [];
    foreach ($rows as $row) {
        $ayarlar[$row['anahtar']] = $row['deger'];
    }
} catch (PDOException $e) {
    $ayarlar = [];
}

// Form işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save_settings') {
            $settings = [
                'firma_adi' => sanitize($_POST['firma_adi']),
                'firma_adres' => sanitize($_POST['firma_adres']),
                'firma_telefon' => sanitize($_POST['firma_telefon']),
                'firma_eposta' => sanitize($_POST['firma_eposta']),
                'kdv_orani' => intval($_POST['kdv_orani']),
                'kritik_stok_varsayilan' => intval($_POST['kritik_stok_varsayilan'])
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = 'Ayarlar başarıyla kaydedildi.';
            $messageType = 'success';
            
            // Ayarları yeniden çek
            $stmt = $db->query("SELECT anahtar, deger FROM ayarlar");
            $rows = $stmt->fetchAll();
            $ayarlar = [];
            foreach ($rows as $row) {
                $ayarlar[$row['anahtar']] = $row['deger'];
            }
            
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Yeni şifreler eşleşmiyor.');
            }
            
            if (strlen($newPassword) < 6) {
                throw new Exception('Şifre en az 6 karakter olmalıdır.');
            }
            
            // Mevcut şifreyi kontrol et
            $stmt = $db->prepare("SELECT sifre FROM kullanicilar WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($currentPassword, $user['sifre'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                $message = 'Şifre başarıyla değiştirildi.';
                $messageType = 'success';
            } else {
                throw new Exception('Mevcut şifre yanlış.');
            }
            
        } elseif ($action === 'backup') {
            // Basit veritabanı yedeği (SQL dump)
            $tables = ['kategoriler', 'urunler', 'musteriler', 'islemler', 'odemeler', 'stok_hareketleri', 'ayarlar'];
            $backup = "-- Cemil Çalışkan Stok Takip Sistemi Yedeği\n";
            $backup .= "-- Tarih: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $stmt = $db->query("SELECT * FROM $table");
                $rows = $stmt->fetchAll();
                
                if (!empty($rows)) {
                    $backup .= "-- Tablo: $table\n";
                    foreach ($rows as $row) {
                        $columns = implode(', ', array_keys($row));
                        $values = implode(', ', array_map(function($v) use ($db) {
                            return $v === null ? 'NULL' : $db->quote($v);
                        }, array_values($row)));
                        $backup .= "INSERT INTO $table ($columns) VALUES ($values);\n";
                    }
                    $backup .= "\n";
                }
            }
            
            // Dosyayı indir
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="yedek_' . date('Y-m-d_H-i-s') . '.sql"');
            echo $backup;
            exit;
        }
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Ayarlar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Ayarlar</li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Firma Bilgileri -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Firma Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="mb-3">
                        <label class="form-label">Firma Adı</label>
                        <input type="text" class="form-control" name="firma_adi" value="<?= htmlspecialchars($ayarlar['firma_adi'] ?? 'Cemil Çalışkan') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adres</label>
                        <textarea class="form-control" name="firma_adres" rows="2"><?= htmlspecialchars($ayarlar['firma_adres'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefon</label>
                        <input type="tel" class="form-control" name="firma_telefon" value="<?= htmlspecialchars($ayarlar['firma_telefon'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" name="firma_eposta" value="<?= htmlspecialchars($ayarlar['firma_eposta'] ?? '') ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Varsayılan KDV Oranı (%)</label>
                            <select class="form-select" name="kdv_orani">
                                <option value="0" <?= ($ayarlar['kdv_orani'] ?? '18') == '0' ? 'selected' : '' ?>>%0</option>
                                <option value="1" <?= ($ayarlar['kdv_orani'] ?? '18') == '1' ? 'selected' : '' ?>>%1</option>
                                <option value="8" <?= ($ayarlar['kdv_orani'] ?? '18') == '8' ? 'selected' : '' ?>>%8</option>
                                <option value="10" <?= ($ayarlar['kdv_orani'] ?? '18') == '10' ? 'selected' : '' ?>>%10</option>
                                <option value="18" <?= ($ayarlar['kdv_orani'] ?? '18') == '18' ? 'selected' : '' ?>>%18</option>
                                <option value="20" <?= ($ayarlar['kdv_orani'] ?? '18') == '20' ? 'selected' : '' ?>>%20</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Varsayılan Kritik Stok</label>
                            <input type="number" class="form-control" name="kritik_stok_varsayilan" value="<?= $ayarlar['kritik_stok_varsayilan'] ?? '10' ?>" min="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Şifre Değiştir & Yedekleme -->
    <div class="col-lg-6 mb-4">
        <!-- Şifre Değiştir -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Şifre Değiştir</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label">Mevcut Şifre</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key me-2"></i>Şifreyi Değiştir
                    </button>
                </form>
            </div>
        </div>

        <!-- Yedekleme -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cloud-download me-2"></i>Yedekleme</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Veritabanı yedeğini SQL formatında indirin.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-download me-2"></i>Yedeği İndir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Sistem Bilgileri -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Sistem Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Uygulama Sürümü:</strong>
                        <p class="text-muted mb-0"><?= APP_VERSION ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>PHP Sürümü:</strong>
                        <p class="text-muted mb-0"><?= phpversion() ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>Sunucu:</strong>
                        <p class="text-muted mb-0"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor' ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>Zaman Dilimi:</strong>
                        <p class="text-muted mb-0"><?= date_default_timezone_get() ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
