<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * İşlemler Sayfası
 */

require_once 'includes/header.php';

$db = getDB();
$message = '';
$messageType = '';

// Ürünleri al
try {
    $stmt = $db->query("SELECT id, urun_kodu, urun_adi, alis_fiyati, satis_fiyati, mevcut_stok FROM urunler WHERE durum = 'aktif' ORDER BY urun_adi");
    $urunler = $stmt->fetchAll();
} catch (PDOException $e) {
    $urunler = [];
}

// Müşterileri al
try {
    $stmt = $db->query("SELECT id, ad_soyad FROM musteriler WHERE durum = 'aktif' ORDER BY ad_soyad");
    $musteriler = $stmt->fetchAll();
} catch (PDOException $e) {
    $musteriler = [];
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            // İşlem ekleme
            $islemNo = generateCode('ISL');
            $islemTipi = $_POST['islem_tipi'];
            $tarih = $_POST['tarih'] . ' ' . $_POST['saat'];
            $urunId = intval($_POST['urun_id']);
            $miktar = intval($_POST['miktar']);
            $birimFiyat = floatval($_POST['birim_fiyat']);
            $kdvOrani = floatval($_POST['kdv_orani']);
            $araToplam = $miktar * $birimFiyat;
            $kdvTutari = ($araToplam * $kdvOrani) / 100;
            $genelToplam = $araToplam + $kdvTutari;
            $musteriId = $_POST['musteri_id'] ? intval($_POST['musteri_id']) : null;
            $odemeSekli = $_POST['odeme_sekli'];
            $not = sanitize($_POST['not_aciklama']);
            
            // Stok kontrolü (satış için)
            if ($islemTipi === 'satis') {
                $stmt = $db->prepare("SELECT mevcut_stok FROM urunler WHERE id = ?");
                $stmt->execute([$urunId]);
                $urun = $stmt->fetch();
                if ($urun['mevcut_stok'] < $miktar) {
                    throw new Exception('Yetersiz stok! Mevcut stok: ' . $urun['mevcut_stok']);
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
            
            // Stok hareketi kaydet
            $stmt = $db->prepare("SELECT mevcut_stok FROM urunler WHERE id = ?");
            $stmt->execute([$urunId]);
            $yeniStok = $stmt->fetch()['mevcut_stok'];
            $oncekiStok = $islemTipi === 'satis' ? $yeniStok + $miktar : $yeniStok - $miktar;
            
            $stmt = $db->prepare("
                INSERT INTO stok_hareketleri (urun_id, hareket_tipi, miktar, onceki_stok, sonraki_stok, islem_id, aciklama)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $urunId,
                $islemTipi === 'satis' ? 'cikis' : 'giris',
                $miktar,
                $oncekiStok,
                $yeniStok,
                $islemId,
                $islemTipi === 'satis' ? 'Satış işlemi' : 'Alış işlemi'
            ]);
            
            $message = 'İşlem başarıyla kaydedildi. İşlem No: ' . $islemNo;
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            // İşlem silme (sadece son işlemi silebilir)
            $islemId = intval($_POST['id']);
            
            // İşlem bilgilerini al
            $stmt = $db->prepare("SELECT * FROM islemler WHERE id = ?");
            $stmt->execute([$islemId]);
            $islem = $stmt->fetch();
            
            if ($islem) {
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
                $stmt->execute([$islemId]);
                
                $message = 'İşlem başarıyla silindi.';
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Filtreleri al
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$islemTipi = $_GET['islem_tipi'] ?? '';
$search = $_GET['search'] ?? '';

// İşlemleri al
try {
    $sql = "
        SELECT i.*, u.urun_kodu, u.urun_adi, m.ad_soyad as musteri_adi 
        FROM islemler i 
        LEFT JOIN urunler u ON i.urun_id = u.id 
        LEFT JOIN musteriler m ON i.musteri_id = m.id 
        WHERE DATE(i.tarih) BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($islemTipi) {
        $sql .= " AND i.islem_tipi = ?";
        $params[] = $islemTipi;
    }
    
    if ($search) {
        $sql .= " AND (i.islem_no LIKE ? OR u.urun_adi LIKE ? OR m.ad_soyad LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY i.tarih DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $islemler = $stmt->fetchAll();
    
    // Toplam hesapla
    $toplamSatis = 0;
    $toplamAlis = 0;
    foreach ($islemler as $islem) {
        if ($islem['islem_tipi'] === 'satis') {
            $toplamSatis += $islem['genel_toplam'];
        } else {
            $toplamAlis += $islem['genel_toplam'];
        }
    }
} catch (PDOException $e) {
    $islemler = [];
    $toplamSatis = 0;
    $toplamAlis = 0;
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>İşlemler</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">İşlemler</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTransactionModal" onclick="setTransactionType('satis')">
            <i class="bi bi-cart-plus me-2"></i>Yeni Satış
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal" onclick="setTransactionType('alis')">
            <i class="bi bi-bag-plus me-2"></i>Yeni Alış
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="icon success">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="value"><?= formatMoney($toplamSatis) ?></div>
            <div class="label">Toplam Satış</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="icon primary">
                <i class="bi bi-graph-down-arrow"></i>
            </div>
            <div class="value"><?= formatMoney($toplamAlis) ?></div>
            <div class="label">Toplam Alış</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="icon <?= ($toplamSatis - $toplamAlis) >= 0 ? 'success' : 'danger' ?>">
                <i class="bi bi-calculator"></i>
            </div>
            <div class="value"><?= formatMoney($toplamSatis - $toplamAlis) ?></div>
            <div class="label">Net Kar/Zarar</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">İşlem Tipi</label>
                <select class="form-select" name="islem_tipi">
                    <option value="">Tümü</option>
                    <option value="satis" <?= $islemTipi === 'satis' ? 'selected' : '' ?>>Satış</option>
                    <option value="alis" <?= $islemTipi === 'alis' ? 'selected' : '' ?>>Alış</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Arama</label>
                <input type="text" class="form-control" name="search" placeholder="İşlem no, ürün, müşteri..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill">
                    <i class="bi bi-search me-1"></i>Filtrele
                </button>
                <a href="islemler.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>İşlem Listesi</h5>
        <span class="badge bg-primary"><?= count($islemler) ?> işlem</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="transactionsTable">
                <thead>
                    <tr>
                        <th>İşlem No</th>
                        <th>Tarih</th>
                        <th>Tip</th>
                        <th>Ürün</th>
                        <th>Müşteri</th>
                        <th>Miktar</th>
                        <th>Birim Fiyat</th>
                        <th>KDV</th>
                        <th>Toplam</th>
                        <th>Ödeme</th>
                        <th width="100">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($islemler as $islem): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($islem['islem_no']) ?></strong></td>
                            <td><?= formatDate($islem['tarih']) ?></td>
                            <td>
                                <?php if ($islem['islem_tipi'] === 'satis'): ?>
                                    <span class="badge bg-success">Satış</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Alış</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($islem['urun_adi'] ?? '-') ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($islem['urun_kodu'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($islem['musteri_adi'] ?? '-') ?></td>
                            <td><?= $islem['miktar'] ?> adet</td>
                            <td><?= formatMoney($islem['birim_fiyat']) ?></td>
                            <td>
                                <small>
                                    %<?= number_format($islem['kdv_orani'], 0) ?>
                                    <br><?= formatMoney($islem['kdv_tutari']) ?>
                                </small>
                            </td>
                            <td>
                                <strong class="<?= $islem['islem_tipi'] === 'satis' ? 'text-success' : 'text-primary' ?>">
                                    <?= formatMoney($islem['genel_toplam']) ?>
                                </strong>
                            </td>
                            <td>
                                <?php
                                $odemeBadges = [
                                    'nakit' => 'bg-success',
                                    'kredi_karti' => 'bg-info',
                                    'havale' => 'bg-warning',
                                    'cek' => 'bg-secondary',
                                    'vadeli' => 'bg-danger'
                                ];
                                $odemeLabels = [
                                    'nakit' => 'Nakit',
                                    'kredi_karti' => 'K.Kartı',
                                    'havale' => 'Havale',
                                    'cek' => 'Çek',
                                    'vadeli' => 'Vadeli'
                                ];
                                ?>
                                <span class="badge <?= $odemeBadges[$islem['odeme_sekli']] ?? 'bg-secondary' ?>">
                                    <?= $odemeLabels[$islem['odeme_sekli']] ?? $islem['odeme_sekli'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-icon" 
                                            onclick="showTransactionDetail(<?= htmlspecialchars(json_encode($islem)) ?>)"
                                            title="Detay">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Bu işlemi silmek istediğinize emin misiniz? Stok değerleri geri alınacaktır.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $islem['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="transactionForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="bi bi-plus-circle me-2"></i>Yeni İşlem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İşlem Tipi <span class="text-danger">*</span></label>
                            <select class="form-select" name="islem_tipi" id="islem_tipi" required onchange="onTransactionTypeChange()">
                                <option value="satis">Satış</option>
                                <option value="alis">Alış</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tarih <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tarih" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Saat <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="saat" required value="<?= date('H:i') ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Ürün <span class="text-danger">*</span></label>
                            <select class="form-select" name="urun_id" id="urun_id" required onchange="onProductSelect(this)">
                                <option value="">Ürün Seçin</option>
                                <?php foreach ($urunler as $urun): ?>
                                    <option value="<?= $urun['id'] ?>" 
                                            data-alis-fiyati="<?= $urun['alis_fiyati'] ?>"
                                            data-satis-fiyati="<?= $urun['satis_fiyati'] ?>"
                                            data-stok="<?= $urun['mevcut_stok'] ?>">
                                        <?= htmlspecialchars($urun['urun_kodu'] . ' - ' . $urun['urun_adi']) ?> 
                                        (Stok: <?= $urun['mevcut_stok'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Miktar <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="miktar" id="miktar" required min="1" value="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birim Fiyat (₺) - KDV Hariç <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="birim_fiyat" id="birim_fiyat" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">KDV Oranı</label>
                            <select class="form-select" name="kdv_orani" id="kdv_orani">
                                <option value="0">%0</option>
                                <option value="1">%1</option>
                                <option value="8">%8</option>
                                <option value="10">%10</option>
                                <option value="18" selected>%18</option>
                                <option value="20">%20</option>
                            </select>
                        </div>
                        
                        <!-- Hesaplama Alanı -->
                        <div class="col-12 mb-3">
                            <div class="card bg-light">
                                <div class="card-body py-3">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <small class="text-muted">Ara Toplam (KDV Hariç)</small>
                                            <div class="fs-5 fw-bold" id="ara_toplam">₺0,00</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">KDV Tutarı (<span id="kdv_label">%18</span>)</small>
                                            <div class="fs-5 fw-bold" id="kdv_tutari">₺0,00</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Genel Toplam (KDV Dahil)</small>
                                            <div class="fs-4 fw-bold text-success" id="genel_toplam_display">₺0,00</div>
                                            <input type="hidden" name="genel_toplam" id="genel_toplam" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Müşteri</label>
                            <select class="form-select" name="musteri_id" id="musteri_id">
                                <option value="">Müşteri Seçin (Opsiyonel)</option>
                                <?php foreach ($musteriler as $musteri): ?>
                                    <option value="<?= $musteri['id'] ?>"><?= htmlspecialchars($musteri['ad_soyad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ödeme Şekli <span class="text-danger">*</span></label>
                            <select class="form-select" name="odeme_sekli" id="odeme_sekli" required>
                                <option value="nakit">Nakit</option>
                                <option value="kredi_karti">Kredi Kartı</option>
                                <option value="havale">Havale/EFT</option>
                                <option value="cek">Çek</option>
                                <option value="vadeli">Vadeli (Borç)</option>
                                <option value="vadeli">Vadeli</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Not</label>
                            <textarea class="form-control" name="not_aciklama" rows="2" placeholder="İşlem ile ilgili not..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transaction Detail Modal -->
<div class="modal fade" id="transactionDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>İşlem Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-borderless">
                    <tr>
                        <td width="140"><strong>İşlem No:</strong></td>
                        <td id="detail_islem_no"></td>
                    </tr>
                    <tr>
                        <td><strong>Tarih:</strong></td>
                        <td id="detail_tarih"></td>
                    </tr>
                    <tr>
                        <td><strong>İşlem Tipi:</strong></td>
                        <td id="detail_tip"></td>
                    </tr>
                    <tr>
                        <td><strong>Ürün:</strong></td>
                        <td id="detail_urun"></td>
                    </tr>
                    <tr>
                        <td><strong>Müşteri:</strong></td>
                        <td id="detail_musteri"></td>
                    </tr>
                    <tr>
                        <td><strong>Miktar:</strong></td>
                        <td id="detail_miktar"></td>
                    </tr>
                    <tr>
                        <td><strong>Birim Fiyat:</strong></td>
                        <td id="detail_birim_fiyat"></td>
                    </tr>
                    <tr>
                        <td><strong>Ara Toplam:</strong></td>
                        <td id="detail_ara_toplam"></td>
                    </tr>
                    <tr>
                        <td><strong>KDV (%):</strong></td>
                        <td id="detail_kdv"></td>
                    </tr>
                    <tr>
                        <td><strong>KDV Tutarı:</strong></td>
                        <td id="detail_kdv_tutari"></td>
                    </tr>
                    <tr class="table-success">
                        <td><strong>Genel Toplam:</strong></td>
                        <td id="detail_genel_toplam" class="fw-bold fs-5"></td>
                    </tr>
                    <tr>
                        <td><strong>Ödeme Şekli:</strong></td>
                        <td id="detail_odeme"></td>
                    </tr>
                    <tr>
                        <td><strong>Not:</strong></td>
                        <td id="detail_not"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" onclick="printTransaction()">
                    <i class="bi bi-printer me-2"></i>Yazdır
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#transactionsTable').DataTable({
        order: [[1, 'desc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json'
        }
    });
    
    // Select2
    $('#urun_id, #musteri_id').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#addTransactionModal'),
        language: 'tr'
    });
    
    // Hesaplama işlemleri
    $('#miktar, #birim_fiyat').on('input', updateCalculations);
    $('#kdv_orani').on('change', function() {
        $('#kdv_label').text('%' + $(this).val());
        updateCalculations();
    });
});

function updateCalculations() {
    const miktar = parseFloat($('#miktar').val()) || 0;
    const birimFiyat = parseFloat($('#birim_fiyat').val()) || 0;
    const kdvOrani = parseFloat($('#kdv_orani').val()) || 0;
    
    const araToplam = miktar * birimFiyat;
    const kdvTutari = (araToplam * kdvOrani) / 100;
    const genelToplam = araToplam + kdvTutari;
    
    $('#ara_toplam').text(formatMoney(araToplam));
    $('#kdv_tutari').text(formatMoney(kdvTutari));
    $('#genel_toplam_display').text(formatMoney(genelToplam));
    $('#genel_toplam').val(genelToplam.toFixed(2));
}

function setTransactionType(type) {
    $('#islem_tipi').val(type);
    if (type === 'satis') {
        $('#modalTitle').html('<i class="bi bi-cart-plus me-2"></i>Yeni Satış');
    } else {
        $('#modalTitle').html('<i class="bi bi-bag-plus me-2"></i>Yeni Alış');
    }
    
    // Seçili ürün varsa fiyatı güncelle
    if ($('#urun_id').val()) {
        onProductSelect($('#urun_id')[0]);
    }
}

function onTransactionTypeChange() {
    const type = $('#islem_tipi').val();
    setTransactionType(type);
}

function onProductSelect(selectElement) {
    const selectedOption = $(selectElement).find(':selected');
    const satisFiyati = selectedOption.data('satis-fiyati');
    const alisFiyati = selectedOption.data('alis-fiyati');
    const stok = selectedOption.data('stok');
    const islemTipi = $('#islem_tipi').val();
    
    if (islemTipi === 'satis') {
        $('#birim_fiyat').val(satisFiyati || 0);
        $('#miktar').attr('max', stok);
    } else {
        $('#birim_fiyat').val(alisFiyati || 0);
        $('#miktar').removeAttr('max');
    }
    
    updateCalculations();
}

function showTransactionDetail(transaction) {
    document.getElementById('detail_islem_no').textContent = transaction.islem_no;
    document.getElementById('detail_tarih').textContent = new Date(transaction.tarih).toLocaleString('tr-TR');
    document.getElementById('detail_tip').innerHTML = transaction.islem_tipi === 'satis' 
        ? '<span class="badge bg-success">Satış</span>' 
        : '<span class="badge bg-primary">Alış</span>';
    document.getElementById('detail_urun').textContent = transaction.urun_adi || '-';
    document.getElementById('detail_musteri').textContent = transaction.musteri_adi || '-';
    document.getElementById('detail_miktar').textContent = transaction.miktar + ' adet';
    document.getElementById('detail_birim_fiyat').textContent = formatMoney(transaction.birim_fiyat);
    document.getElementById('detail_ara_toplam').textContent = formatMoney(transaction.ara_toplam);
    document.getElementById('detail_kdv').textContent = '%' + transaction.kdv_orani;
    document.getElementById('detail_kdv_tutari').textContent = formatMoney(transaction.kdv_tutari);
    document.getElementById('detail_genel_toplam').textContent = formatMoney(transaction.genel_toplam);
    
    const odemeLabels = {
        'nakit': 'Nakit',
        'kredi_karti': 'Kredi Kartı',
        'havale': 'Havale/EFT',
        'cek': 'Çek',
        'vadeli': 'Vadeli'
    };
    document.getElementById('detail_odeme').textContent = odemeLabels[transaction.odeme_sekli] || transaction.odeme_sekli;
    document.getElementById('detail_not').textContent = transaction.not_aciklama || '-';
    
    new bootstrap.Modal(document.getElementById('transactionDetailModal')).show();
}

function printTransaction() {
    window.print();
}

function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2
    }).format(amount);
}
</script>

<?php require_once 'includes/footer.php'; ?>
