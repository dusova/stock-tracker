<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Müşteriler Sayfası
 */

require_once 'includes/header.php';

$db = getDB();
$message = '';
$messageType = '';

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            // Müşteri ekleme
            $baslangicBakiye = floatval($_POST['baslangic_bakiye']);
            $stmt = $db->prepare("
                INSERT INTO musteriler (ad_soyad, telefon, eposta, adres, iban, baslangic_bakiye, mevcut_bakiye)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitize($_POST['ad_soyad']),
                sanitize($_POST['telefon']),
                sanitize($_POST['eposta']),
                sanitize($_POST['adres']),
                sanitize($_POST['iban']),
                $baslangicBakiye,
                $baslangicBakiye
            ]);
            $message = 'Müşteri başarıyla eklendi.';
            $messageType = 'success';
            
        } elseif ($action === 'edit') {
            // Müşteri düzenleme
            $stmt = $db->prepare("
                UPDATE musteriler SET 
                    ad_soyad = ?, telefon = ?, eposta = ?, adres = ?, iban = ?
                WHERE id = ?
            ");
            $stmt->execute([
                sanitize($_POST['ad_soyad']),
                sanitize($_POST['telefon']),
                sanitize($_POST['eposta']),
                sanitize($_POST['adres']),
                sanitize($_POST['iban']),
                intval($_POST['id'])
            ]);
            $message = 'Müşteri başarıyla güncellendi.';
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            // Müşteri silme
            $stmt = $db->prepare("DELETE FROM musteriler WHERE id = ?");
            $stmt->execute([intval($_POST['id'])]);
            $message = 'Müşteri başarıyla silindi.';
            $messageType = 'success';
            
        } elseif ($action === 'payment') {
            // Ödeme ekleme
            $musteriId = intval($_POST['musteri_id']);
            $tutar = floatval($_POST['tutar']);
            $odemeTarihi = $_POST['odeme_tarihi'] . ' ' . date('H:i:s');
            $odemeSekli = $_POST['odeme_sekli'];
            $aciklama = sanitize($_POST['aciklama']);
            
            // Ödemeyi kaydet
            $stmt = $db->prepare("
                INSERT INTO odemeler (musteri_id, tutar, odeme_tarihi, odeme_sekli, aciklama)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$musteriId, $tutar, $odemeTarihi, $odemeSekli, $aciklama]);
            
            // Müşteri bakiyesini güncelle (ödeme yapıldığında bakiye düşer)
            $stmt = $db->prepare("UPDATE musteriler SET mevcut_bakiye = mevcut_bakiye - ? WHERE id = ?");
            $stmt->execute([$tutar, $musteriId]);
            
            $message = 'Ödeme başarıyla kaydedildi.';
            $messageType = 'success';
            
        } elseif ($action === 'adjust_balance') {
            // Bakiye düzeltme/ayarlama
            $musteriId = intval($_POST['musteri_id']);
            $islemTipi = $_POST['islem_tipi']; // borc_ekle, borc_cikar, bakiye_sifirla
            $tutar = floatval($_POST['tutar']);
            $aciklama = sanitize($_POST['aciklama']);
            
            if ($islemTipi === 'borc_ekle') {
                // Borç ekle (bakiyeyi artır)
                $stmt = $db->prepare("UPDATE musteriler SET mevcut_bakiye = mevcut_bakiye + ? WHERE id = ?");
                $stmt->execute([$tutar, $musteriId]);
                
                // İşlemi kaydet
                $stmt = $db->prepare("
                    INSERT INTO odemeler (musteri_id, tutar, odeme_tarihi, odeme_sekli, aciklama)
                    VALUES (?, ?, NOW(), 'diger', ?)
                ");
                $stmt->execute([$musteriId, -$tutar, 'Borç Ekleme: ' . $aciklama]);
                
                $message = 'Borç başarıyla eklendi.';
            } elseif ($islemTipi === 'borc_cikar') {
                // Borç çıkar (bakiyeyi düşür)
                $stmt = $db->prepare("UPDATE musteriler SET mevcut_bakiye = mevcut_bakiye - ? WHERE id = ?");
                $stmt->execute([$tutar, $musteriId]);
                
                // İşlemi kaydet
                $stmt = $db->prepare("
                    INSERT INTO odemeler (musteri_id, tutar, odeme_tarihi, odeme_sekli, aciklama)
                    VALUES (?, ?, NOW(), 'diger', ?)
                ");
                $stmt->execute([$musteriId, $tutar, 'Borç Çıkarma: ' . $aciklama]);
                
                $message = 'Borç başarıyla çıkarıldı.';
            } elseif ($islemTipi === 'bakiye_sifirla') {
                // Bakiyeyi sıfırla
                $stmt = $db->prepare("SELECT mevcut_bakiye FROM musteriler WHERE id = ?");
                $stmt->execute([$musteriId]);
                $mevcutBakiye = $stmt->fetch()['mevcut_bakiye'];
                
                $stmt = $db->prepare("UPDATE musteriler SET mevcut_bakiye = 0 WHERE id = ?");
                $stmt->execute([$musteriId]);
                
                // İşlemi kaydet
                $stmt = $db->prepare("
                    INSERT INTO odemeler (musteri_id, tutar, odeme_tarihi, odeme_sekli, aciklama)
                    VALUES (?, ?, NOW(), 'diger', ?)
                ");
                $stmt->execute([$musteriId, $mevcutBakiye, 'Bakiye Sıfırlama: ' . $aciklama]);
                
                $message = 'Bakiye başarıyla sıfırlandı.';
            }
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Müşterileri al
try {
    $search = $_GET['search'] ?? '';
    $balanceFilter = $_GET['balance'] ?? '';
    
    $sql = "SELECT * FROM musteriler WHERE durum = 'aktif'";
    $params = [];
    
    if ($search) {
        $sql .= " AND (ad_soyad LIKE ? OR telefon LIKE ? OR eposta LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($balanceFilter === 'debt') {
        $sql .= " AND mevcut_bakiye > 0";
    } elseif ($balanceFilter === 'credit') {
        $sql .= " AND mevcut_bakiye < 0";
    }
    
    $sql .= " ORDER BY ad_soyad ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $musteriler = $stmt->fetchAll();
} catch (PDOException $e) {
    $musteriler = [];
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Müşteriler</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Müşteriler</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="bi bi-person-plus me-2"></i>Yeni Müşteri
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Ad, telefon veya e-posta ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="balance">
                    <option value="">Tüm Bakiyeler</option>
                    <option value="debt" <?= ($balanceFilter ?? '') === 'debt' ? 'selected' : '' ?>>Borçlu Müşteriler</option>
                    <option value="credit" <?= ($balanceFilter ?? '') === 'credit' ? 'selected' : '' ?>>Alacaklı Müşteriler</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filtrele</button>
            </div>
            <div class="col-md-2">
                <a href="musteriler.php" class="btn btn-outline-secondary w-100">Temizle</a>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Müşteri Listesi</h5>
        <span class="badge bg-primary"><?= count($musteriler) ?> müşteri</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="customersTable">
                <thead>
                    <tr>
                        <th>Ad Soyad</th>
                        <th>Telefon</th>
                        <th>E-posta</th>
                        <th>Adres</th>
                        <th>Bakiye</th>
                        <th width="200">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($musteriler as $musteri): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($musteri['ad_soyad']) ?></strong></td>
                            <td>
                                <?php if ($musteri['telefon']): ?>
                                    <a href="tel:<?= $musteri['telefon'] ?>" class="text-decoration-none">
                                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($musteri['telefon']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($musteri['eposta']): ?>
                                    <a href="mailto:<?= $musteri['eposta'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($musteri['eposta']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(mb_substr($musteri['adres'] ?? '-', 0, 30)) ?>...</td>
                            <td>
                                <?php if ($musteri['mevcut_bakiye'] > 0): ?>
                                    <span class="balance-negative">
                                        <i class="bi bi-arrow-up-circle me-1"></i><?= formatMoney($musteri['mevcut_bakiye']) ?>
                                        <small class="d-block text-muted">Borç</small>
                                    </span>
                                <?php elseif ($musteri['mevcut_bakiye'] < 0): ?>
                                    <span class="balance-positive">
                                        <i class="bi bi-arrow-down-circle me-1"></i><?= formatMoney(abs($musteri['mevcut_bakiye'])) ?>
                                        <small class="d-block text-muted">Alacak</small>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">₺0,00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-icon" 
                                            onclick="showCustomerDetail(<?= htmlspecialchars(json_encode($musteri)) ?>)"
                                            title="Detay">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-icon" 
                                            onclick="showBalanceModal(<?= $musteri['id'] ?>, '<?= htmlspecialchars($musteri['ad_soyad']) ?>', <?= $musteri['mevcut_bakiye'] ?>)"
                                            title="Bakiye İşlemleri">
                                        <i class="bi bi-wallet2"></i>
                                    </button>
                                    <?php if ($musteri['mevcut_bakiye'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success btn-icon" 
                                                onclick="showPaymentModal(<?= $musteri['id'] ?>, '<?= htmlspecialchars($musteri['ad_soyad']) ?>', <?= $musteri['mevcut_bakiye'] ?>)"
                                                title="Ödeme Al">
                                            <i class="bi bi-cash"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-icon" 
                                            onclick="editCustomer(<?= htmlspecialchars(json_encode($musteri)) ?>)"
                                            title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Bu müşteriyi silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $musteri['id'] ?>">
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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Yeni Müşteri Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ad_soyad" required placeholder="Müşteri adı soyadı">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="telefon" placeholder="05XX XXX XX XX">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" name="eposta" placeholder="ornek@email.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IBAN</label>
                            <input type="text" class="form-control" name="iban" placeholder="TR00 0000 0000 0000 0000 0000 00">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Adres</label>
                            <textarea class="form-control" name="adres" rows="2" placeholder="Müşteri adresi"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Başlangıç Bakiyesi (₺)</label>
                            <input type="number" step="0.01" class="form-control" name="baslangic_bakiye" value="0" placeholder="Borç: +, Alacak: -">
                            <small class="text-muted">Pozitif değer = Borç, Negatif değer = Alacak</small>
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

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Müşteri Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ad_soyad" id="edit_ad_soyad" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="telefon" id="edit_telefon">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" name="eposta" id="edit_eposta">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IBAN</label>
                            <input type="text" class="form-control" name="iban" id="edit_iban">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Adres</label>
                            <textarea class="form-control" name="adres" id="edit_adres" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Customer Detail Modal -->
<div class="modal fade" id="customerDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person me-2"></i>Müşteri Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Kişisel Bilgiler</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td width="120"><strong>Ad Soyad:</strong></td>
                                    <td id="detail_ad_soyad"></td>
                                </tr>
                                <tr>
                                    <td><strong>Telefon:</strong></td>
                                    <td id="detail_telefon"></td>
                                </tr>
                                <tr>
                                    <td><strong>E-posta:</strong></td>
                                    <td id="detail_eposta"></td>
                                </tr>
                                <tr>
                                    <td><strong>Adres:</strong></td>
                                    <td id="detail_adres"></td>
                                </tr>
                                <tr>
                                    <td><strong>IBAN:</strong></td>
                                    <td id="detail_iban"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Finansal Bilgiler</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td width="150"><strong>Başlangıç Bakiye:</strong></td>
                                    <td id="detail_baslangic_bakiye"></td>
                                </tr>
                                <tr>
                                    <td><strong>Mevcut Bakiye:</strong></td>
                                    <td id="detail_mevcut_bakiye"></td>
                                </tr>
                                <tr>
                                    <td><strong>Kayıt Tarihi:</strong></td>
                                    <td id="detail_kayit_tarihi"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <hr>
                <h6 class="text-muted mb-3">Son İşlemler</h6>
                <div id="detail_transactions" class="table-responsive">
                    <!-- İşlemler AJAX ile yüklenecek -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="payment">
                <input type="hidden" name="musteri_id" id="payment_musteri_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Ödeme Al</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Müşteri:</strong> <span id="payment_musteri_adi"></span><br>
                        <strong>Mevcut Borç:</strong> <span id="payment_mevcut_borc"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ödeme Tutarı (₺) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="tutar" id="payment_tutar" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ödeme Tarihi <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="odeme_tarihi" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ödeme Şekli</label>
                        <select class="form-select" name="odeme_sekli">
                            <option value="nakit">Nakit</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="havale">Havale/EFT</option>
                            <option value="cek">Çek</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" rows="2" placeholder="Ödeme ile ilgili not"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-2"></i>Ödemeyi Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="adjust_balance">
                <input type="hidden" name="musteri_id" id="balance_musteri_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Bakiye İşlemleri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Müşteri:</strong> <span id="balance_musteri_adi"></span><br>
                        <strong>Mevcut Bakiye:</strong> <span id="balance_mevcut"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İşlem Tipi <span class="text-danger">*</span></label>
                        <select class="form-select" name="islem_tipi" id="balance_islem_tipi" required onchange="onBalanceTypeChange()">
                            <option value="borc_ekle">Borç Ekle (+)</option>
                            <option value="borc_cikar">Borç Çıkar (-)</option>
                            <option value="bakiye_sifirla">Bakiyeyi Sıfırla</option>
                        </select>
                    </div>
                    <div class="mb-3" id="balance_tutar_group">
                        <label class="form-label">Tutar (₺) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="tutar" id="balance_tutar" min="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" rows="2" placeholder="İşlem ile ilgili not"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle me-2"></i>İşlemi Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#customersTable').DataTable({
        order: [[0, 'asc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json'
        }
    });
});

function editCustomer(customer) {
    document.getElementById('edit_id').value = customer.id;
    document.getElementById('edit_ad_soyad').value = customer.ad_soyad;
    document.getElementById('edit_telefon').value = customer.telefon || '';
    document.getElementById('edit_eposta').value = customer.eposta || '';
    document.getElementById('edit_adres').value = customer.adres || '';
    document.getElementById('edit_iban').value = customer.iban || '';
    
    new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
}

function showCustomerDetail(customer) {
    document.getElementById('detail_ad_soyad').textContent = customer.ad_soyad;
    document.getElementById('detail_telefon').textContent = customer.telefon || '-';
    document.getElementById('detail_eposta').textContent = customer.eposta || '-';
    document.getElementById('detail_adres').textContent = customer.adres || '-';
    document.getElementById('detail_iban').textContent = customer.iban || '-';
    document.getElementById('detail_baslangic_bakiye').textContent = formatMoney(customer.baslangic_bakiye);
    
    const mevcutBakiye = parseFloat(customer.mevcut_bakiye);
    const bakiyeElement = document.getElementById('detail_mevcut_bakiye');
    if (mevcutBakiye > 0) {
        bakiyeElement.innerHTML = '<span class="text-danger">' + formatMoney(mevcutBakiye) + ' (Borç)</span>';
    } else if (mevcutBakiye < 0) {
        bakiyeElement.innerHTML = '<span class="text-success">' + formatMoney(Math.abs(mevcutBakiye)) + ' (Alacak)</span>';
    } else {
        bakiyeElement.textContent = '₺0,00';
    }
    
    document.getElementById('detail_kayit_tarihi').textContent = customer.olusturma_tarihi ? new Date(customer.olusturma_tarihi).toLocaleDateString('tr-TR') : '-';
    
    // İşlemleri yükle
    fetch('api/customers.php?action=transactions&id=' + customer.id)
        .then(response => response.json())
        .then(data => {
            let html = '<table class="table table-sm"><thead><tr><th>Tarih</th><th>Tip</th><th>Tutar</th></tr></thead><tbody>';
            if (data.length > 0) {
                data.forEach(item => {
                    html += '<tr>';
                    html += '<td>' + new Date(item.tarih).toLocaleDateString('tr-TR') + '</td>';
                    html += '<td>' + (item.islem_tipi === 'satis' ? '<span class="badge bg-success">Satış</span>' : '<span class="badge bg-primary">Alış</span>') + '</td>';
                    html += '<td>' + formatMoney(item.genel_toplam) + '</td>';
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="3" class="text-center text-muted">Henüz işlem yok</td></tr>';
            }
            html += '</tbody></table>';
            document.getElementById('detail_transactions').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('detail_transactions').innerHTML = '<p class="text-muted">İşlemler yüklenemedi.</p>';
        });
    
    new bootstrap.Modal(document.getElementById('customerDetailModal')).show();
}

function showPaymentModal(musteriId, musteriAdi, mevcutBorc) {
    document.getElementById('payment_musteri_id').value = musteriId;
    document.getElementById('payment_musteri_adi').textContent = musteriAdi;
    document.getElementById('payment_mevcut_borc').textContent = formatMoney(mevcutBorc);
    document.getElementById('payment_tutar').value = mevcutBorc;
    document.getElementById('payment_tutar').max = mevcutBorc;
    
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2
    }).format(amount);
}

function showBalanceModal(musteriId, musteriAdi, mevcutBakiye) {
    document.getElementById('balance_musteri_id').value = musteriId;
    document.getElementById('balance_musteri_adi').textContent = musteriAdi;
    
    const bakiye = parseFloat(mevcutBakiye);
    if (bakiye > 0) {
        document.getElementById('balance_mevcut').innerHTML = '<span class="text-danger">' + formatMoney(bakiye) + ' (Borç)</span>';
    } else if (bakiye < 0) {
        document.getElementById('balance_mevcut').innerHTML = '<span class="text-success">' + formatMoney(Math.abs(bakiye)) + ' (Alacak)</span>';
    } else {
        document.getElementById('balance_mevcut').textContent = '₺0,00';
    }
    
    document.getElementById('balance_islem_tipi').value = 'borc_ekle';
    document.getElementById('balance_tutar').value = '';
    document.getElementById('balance_tutar_group').style.display = 'block';
    document.getElementById('balance_tutar').required = true;
    
    new bootstrap.Modal(document.getElementById('balanceModal')).show();
}

function onBalanceTypeChange() {
    const islemTipi = document.getElementById('balance_islem_tipi').value;
    const tutarGroup = document.getElementById('balance_tutar_group');
    const tutarInput = document.getElementById('balance_tutar');
    
    if (islemTipi === 'bakiye_sifirla') {
        tutarGroup.style.display = 'none';
        tutarInput.required = false;
        tutarInput.value = '0';
    } else {
        tutarGroup.style.display = 'block';
        tutarInput.required = true;
        tutarInput.value = '';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
