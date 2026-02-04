<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Ürünler Sayfası
 */

require_once 'includes/header.php';

$db = getDB();
$message = '';
$messageType = '';

// Kategorileri al
try {
    $stmt = $db->query("SELECT * FROM kategoriler ORDER BY kategori_adi");
    $kategoriler = $stmt->fetchAll();
} catch (PDOException $e) {
    $kategoriler = [];
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            // Ürün ekleme
            $stmt = $db->prepare("
                INSERT INTO urunler (urun_kodu, urun_adi, kategori_id, alis_fiyati, satis_fiyati, mevcut_stok, kritik_stok, aciklama)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitize($_POST['urun_kodu']),
                sanitize($_POST['urun_adi']),
                $_POST['kategori_id'] ?: null,
                floatval($_POST['alis_fiyati']),
                floatval($_POST['satis_fiyati']),
                intval($_POST['mevcut_stok']),
                intval($_POST['kritik_stok']),
                sanitize($_POST['aciklama'])
            ]);
            $message = 'Ürün başarıyla eklendi.';
            $messageType = 'success';
            
        } elseif ($action === 'edit') {
            // Ürün düzenleme
            $stmt = $db->prepare("
                UPDATE urunler SET 
                    urun_kodu = ?, urun_adi = ?, kategori_id = ?, alis_fiyati = ?, 
                    satis_fiyati = ?, mevcut_stok = ?, kritik_stok = ?, aciklama = ?
                WHERE id = ?
            ");
            $stmt->execute([
                sanitize($_POST['urun_kodu']),
                sanitize($_POST['urun_adi']),
                $_POST['kategori_id'] ?: null,
                floatval($_POST['alis_fiyati']),
                floatval($_POST['satis_fiyati']),
                intval($_POST['mevcut_stok']),
                intval($_POST['kritik_stok']),
                sanitize($_POST['aciklama']),
                intval($_POST['id'])
            ]);
            $message = 'Ürün başarıyla güncellendi.';
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            // Ürün silme
            $stmt = $db->prepare("DELETE FROM urunler WHERE id = ?");
            $stmt->execute([intval($_POST['id'])]);
            $message = 'Ürün başarıyla silindi.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Ürünleri al
try {
    $filter = $_GET['filter'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT u.*, k.kategori_adi FROM urunler u LEFT JOIN kategoriler k ON u.kategori_id = k.id WHERE u.durum = 'aktif'";
    $params = [];
    
    if ($filter === 'low_stock') {
        $sql .= " AND u.mevcut_stok <= u.kritik_stok";
    }
    
    if ($search) {
        $sql .= " AND (u.urun_kodu LIKE ? OR u.urun_adi LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY u.olusturma_tarihi DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $urunler = $stmt->fetchAll();
} catch (PDOException $e) {
    $urunler = [];
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Ürünler</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Ürünler</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus-circle me-2"></i>Yeni Ürün
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
                    <input type="text" class="form-control" name="search" placeholder="Ürün kodu veya adı ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="filter">
                    <option value="">Tüm Ürünler</option>
                    <option value="low_stock" <?= ($filter ?? '') === 'low_stock' ? 'selected' : '' ?>>Düşük Stoklu</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filtrele</button>
            </div>
            <div class="col-md-2">
                <a href="urunler.php" class="btn btn-outline-secondary w-100">Temizle</a>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box me-2"></i>Ürün Listesi</h5>
        <span class="badge bg-primary"><?= count($urunler) ?> ürün</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="productsTable">
                <thead>
                    <tr>
                        <th>Ürün Kodu</th>
                        <th>Ürün Adı</th>
                        <th>Kategori</th>
                        <th>Alış Fiyatı</th>
                        <th>Satış Fiyatı</th>
                        <th>Mevcut Stok</th>
                        <th>Durum</th>
                        <th width="150">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($urunler as $urun): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($urun['urun_kodu']) ?></strong></td>
                            <td><?= htmlspecialchars($urun['urun_adi']) ?></td>
                            <td><?= htmlspecialchars($urun['kategori_adi'] ?? '-') ?></td>
                            <td><?= formatMoney($urun['alis_fiyati']) ?></td>
                            <td><?= formatMoney($urun['satis_fiyati']) ?></td>
                            <td>
                                <strong><?= $urun['mevcut_stok'] ?></strong>
                                <small class="text-muted">/ <?= $urun['kritik_stok'] ?></small>
                            </td>
                            <td>
                                <?php if ($urun['mevcut_stok'] <= $urun['kritik_stok']): ?>
                                    <span class="badge badge-low-stock">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Düşük Stok
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-in-stock">
                                        <i class="bi bi-check-circle me-1"></i>Stokta
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-icon" 
                                            onclick="editProduct(<?= htmlspecialchars(json_encode($urun)) ?>)"
                                            title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Bu ürünü silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $urun['id'] ?>">
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Yeni Ürün Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ürün Kodu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="urun_kodu" required placeholder="Örn: URN001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="urun_adi" required placeholder="Ürün adını girin">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" name="kategori_id">
                                <option value="">Kategori Seçin</option>
                                <?php foreach ($kategoriler as $kategori): ?>
                                    <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['kategori_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alış Fiyatı (₺) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="alis_fiyati" required placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Satış Fiyatı (₺) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="satis_fiyati" required placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mevcut Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="mevcut_stok" required value="0" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kritik Stok Seviyesi</label>
                            <input type="number" class="form-control" name="kritik_stok" value="10" min="0">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" rows="3" placeholder="Ürün hakkında açıklama..."></textarea>
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

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Ürün Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ürün Kodu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="urun_kodu" id="edit_urun_kodu" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="urun_adi" id="edit_urun_adi" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" name="kategori_id" id="edit_kategori_id">
                                <option value="">Kategori Seçin</option>
                                <?php foreach ($kategoriler as $kategori): ?>
                                    <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['kategori_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alış Fiyatı (₺) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="alis_fiyati" id="edit_alis_fiyati" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Satış Fiyatı (₺) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="satis_fiyati" id="edit_satis_fiyati" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mevcut Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="mevcut_stok" id="edit_mevcut_stok" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kritik Stok Seviyesi</label>
                            <input type="number" class="form-control" name="kritik_stok" id="edit_kritik_stok" min="0">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" id="edit_aciklama" rows="3"></textarea>
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

<script>
$(document).ready(function() {
    $('#productsTable').DataTable({
        order: [[0, 'asc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json'
        }
    });
});

function editProduct(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_urun_kodu').value = product.urun_kodu;
    document.getElementById('edit_urun_adi').value = product.urun_adi;
    document.getElementById('edit_kategori_id').value = product.kategori_id || '';
    document.getElementById('edit_alis_fiyati').value = product.alis_fiyati;
    document.getElementById('edit_satis_fiyati').value = product.satis_fiyati;
    document.getElementById('edit_mevcut_stok').value = product.mevcut_stok;
    document.getElementById('edit_kritik_stok').value = product.kritik_stok;
    document.getElementById('edit_aciklama').value = product.aciklama || '';
    
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
