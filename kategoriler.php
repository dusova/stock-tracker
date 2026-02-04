<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Kategoriler Sayfası
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
            $stmt = $db->prepare("INSERT INTO kategoriler (kategori_adi, aciklama) VALUES (?, ?)");
            $stmt->execute([
                sanitize($_POST['kategori_adi']),
                sanitize($_POST['aciklama'])
            ]);
            $message = 'Kategori başarıyla eklendi.';
            $messageType = 'success';
            
        } elseif ($action === 'edit') {
            $stmt = $db->prepare("UPDATE kategoriler SET kategori_adi = ?, aciklama = ? WHERE id = ?");
            $stmt->execute([
                sanitize($_POST['kategori_adi']),
                sanitize($_POST['aciklama']),
                intval($_POST['id'])
            ]);
            $message = 'Kategori başarıyla güncellendi.';
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            // Önce bu kategorideki ürünlerin kategorisini null yap
            $stmt = $db->prepare("UPDATE urunler SET kategori_id = NULL WHERE kategori_id = ?");
            $stmt->execute([intval($_POST['id'])]);
            
            // Sonra kategoriyi sil
            $stmt = $db->prepare("DELETE FROM kategoriler WHERE id = ?");
            $stmt->execute([intval($_POST['id'])]);
            $message = 'Kategori başarıyla silindi.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Kategorileri al (ürün sayısıyla birlikte)
try {
    $stmt = $db->query("
        SELECT k.*, COUNT(u.id) as urun_sayisi 
        FROM kategoriler k 
        LEFT JOIN urunler u ON k.id = u.kategori_id 
        GROUP BY k.id 
        ORDER BY k.kategori_adi
    ");
    $kategoriler = $stmt->fetchAll();
} catch (PDOException $e) {
    $kategoriler = [];
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Kategoriler</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Kategoriler</li>
            </ol>
        </nav>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle me-2"></i>Yeni Kategori
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Categories -->
<div class="row">
    <?php foreach ($kategoriler as $kategori): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1"><?= htmlspecialchars($kategori['kategori_adi']) ?></h5>
                            <small class="text-muted">
                                <i class="bi bi-box me-1"></i><?= $kategori['urun_sayisi'] ?> ürün
                            </small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-link text-dark" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="editCategory(<?= htmlspecialchars(json_encode($kategori)) ?>)">
                                        <i class="bi bi-pencil me-2"></i>Düzenle
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="urunler.php?kategori=<?= $kategori['id'] ?>">
                                        <i class="bi bi-box me-2"></i>Ürünleri Gör
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" onsubmit="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $kategori['id'] ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash me-2"></i>Sil
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <p class="card-text text-muted small">
                        <?= htmlspecialchars($kategori['aciklama'] ?? 'Açıklama yok') ?>
                    </p>
                </div>
                <div class="card-footer bg-transparent">
                    <small class="text-muted">
                        <i class="bi bi-calendar me-1"></i>
                        <?= formatDate($kategori['olusturma_tarihi'], 'd.m.Y') ?>
                    </small>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (empty($kategoriler)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-tags fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">Henüz kategori eklenmemiş.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle me-2"></i>İlk Kategoriyi Ekle
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Yeni Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kategori_adi" required placeholder="Örn: Elektronik">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" rows="3" placeholder="Kategori açıklaması..."></textarea>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Kategori Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kategori_adi" id="edit_kategori_adi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" id="edit_aciklama" rows="3"></textarea>
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
function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_kategori_adi').value = category.kategori_adi;
    document.getElementById('edit_aciklama').value = category.aciklama || '';
    
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
