<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Dashboard (Ana Sayfa)
 */

require_once 'includes/header.php';

$db = getDB();

// İstatistikler
try {
    // Toplam ürün sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM urunler WHERE durum = 'aktif'");
    $totalProducts = $stmt->fetch()['total'];

    // Toplam stok değeri (satış fiyatı üzerinden)
    $stmt = $db->query("SELECT SUM(mevcut_stok * satis_fiyati) as total FROM urunler WHERE durum = 'aktif'");
    $totalStockValue = $stmt->fetch()['total'] ?? 0;

    // Toplam müşteri sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM musteriler WHERE durum = 'aktif'");
    $totalCustomers = $stmt->fetch()['total'];

    // Toplam alacak (pozitif bakiye = müşteri bize borçlu = bizim alacağımız)
    $stmt = $db->query("SELECT SUM(mevcut_bakiye) as total FROM musteriler WHERE mevcut_bakiye > 0 AND durum = 'aktif'");
    $totalReceivable = $stmt->fetch()['total'] ?? 0;

    // Toplam borç (negatif bakiye = biz müşteriye borçluyuz = bizim borcumuz)
    $stmt = $db->query("SELECT SUM(ABS(mevcut_bakiye)) as total FROM musteriler WHERE mevcut_bakiye < 0 AND durum = 'aktif'");
    $totalPayable = $stmt->fetch()['total'] ?? 0;

    // Bugünkü satışlar
    $stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(genel_toplam), 0) as total FROM islemler WHERE islem_tipi = 'satis' AND DATE(tarih) = CURDATE()");
    $todaySales = $stmt->fetch();

    // Bu ayki satışlar
    $stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(genel_toplam), 0) as total FROM islemler WHERE islem_tipi = 'satis' AND MONTH(tarih) = MONTH(CURDATE()) AND YEAR(tarih) = YEAR(CURDATE())");
    $monthSales = $stmt->fetch();

    // Düşük stoklu ürünler
    $stmt = $db->query("SELECT * FROM urunler WHERE mevcut_stok <= kritik_stok AND durum = 'aktif' ORDER BY mevcut_stok ASC LIMIT 10");
    $lowStockProducts = $stmt->fetchAll();

    // Son 10 işlem
    $stmt = $db->query("
        SELECT i.*, u.urun_adi, m.ad_soyad as musteri_adi 
        FROM islemler i 
        LEFT JOIN urunler u ON i.urun_id = u.id 
        LEFT JOIN musteriler m ON i.musteri_id = m.id 
        ORDER BY i.tarih DESC 
        LIMIT 10
    ");
    $recentTransactions = $stmt->fetchAll();

    // Son 7 günlük satış grafiği verisi
    $stmt = $db->query("
        SELECT DATE(tarih) as tarih, SUM(genel_toplam) as toplam 
        FROM islemler 
        WHERE islem_tipi = 'satis' AND tarih >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(tarih)
        ORDER BY tarih ASC
    ");
    $salesChartData = $stmt->fetchAll();

    // Son 7 günlük alış grafiği verisi
    $stmt = $db->query("
        SELECT DATE(tarih) as tarih, SUM(genel_toplam) as toplam 
        FROM islemler 
        WHERE islem_tipi = 'alis' AND tarih >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(tarih)
        ORDER BY tarih ASC
    ");
    $purchaseChartData = $stmt->fetchAll();

} catch (PDOException $e) {
    // Veritabanı henüz oluşturulmamış olabilir
    $totalProducts = 0;
    $totalStockValue = 0;
    $totalCustomers = 0;
    $totalReceivable = 0;
    $totalPayable = 0;
    $todaySales = ['count' => 0, 'total' => 0];
    $monthSales = ['count' => 0, 'total' => 0];
    $lowStockProducts = [];
    $recentTransactions = [];
    $salesChartData = [];
    $purchaseChartData = [];
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center">
    <div>
        <h1>Dashboard</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Ana Sayfa</li>
            </ol>
        </nav>
    </div>
    <div>
        <span class="text-muted"><i class="bi bi-calendar3 me-2"></i><?= date('d.m.Y H:i') ?></span>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon primary">
                <i class="bi bi-box"></i>
            </div>
            <div class="value"><?= number_format($totalProducts) ?></div>
            <div class="label">Toplam Ürün</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon success">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="value"><?= formatMoney($totalStockValue) ?></div>
            <div class="label">Toplam Stok Değeri</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon info">
                <i class="bi bi-people"></i>
            </div>
            <div class="value"><?= number_format($totalCustomers) ?></div>
            <div class="label">Toplam Müşteri</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon success">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="value"><?= formatMoney($totalReceivable) ?></div>
            <div class="label">Toplam Alacak</div>
            <small class="text-muted">Müşterilerden tahsil edilecek</small>
        </div>
    </div>
</div>

<!-- Secondary Stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon success">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="value"><?= formatMoney($todaySales['total']) ?></div>
            <div class="label">Bugünkü Satışlar (<?= $todaySales['count'] ?> işlem)</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon secondary">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="value"><?= formatMoney($monthSales['total']) ?></div>
            <div class="label">Bu Ayki Satışlar (<?= $monthSales['count'] ?> işlem)</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon warning">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="value"><?= count($lowStockProducts) ?></div>
            <div class="label">Düşük Stok Uyarısı</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon danger">
                <i class="bi bi-credit-card"></i>
            </div>
            <div class="value"><?= formatMoney($totalPayable) ?></div>
            <div class="label">Toplam Borç</div>
            <small class="text-muted">Müşterilere ödenecek</small>
        </div>
    </div>
</div>

<!-- Charts and Lists -->
<div class="row">
    <!-- Sales Chart -->
    <div class="col-xl-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Son 7 Gün Satış/Alış Grafiği</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Warning -->
    <div class="col-xl-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Düşük Stok Uyarısı</h5>
                <a href="urunler.php?filter=low_stock" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
            </div>
            <div class="card-body p-0">
                <div class="p-3" style="max-height: 350px; overflow-y: auto;">
                    <?php if (empty($lowStockProducts)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-check-circle-fill text-success fs-1"></i>
                            <p class="mt-2 mb-0">Tüm ürünlerin stoğu yeterli</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <div class="low-stock-item">
                                <div class="product-info">
                                    <div class="product-icon">
                                        <i class="bi bi-box"></i>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($product['urun_adi']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($product['urun_kodu']) ?></small>
                                    </div>
                                </div>
                                <div class="stock-info">
                                    <div class="stock-current"><?= $product['mevcut_stok'] ?> adet</div>
                                    <div class="stock-critical">Kritik: <?= $product['kritik_stok'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Son İşlemler</h5>
                <a href="islemler.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentTransactions)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2 mb-0">Henüz işlem yapılmamış</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>İşlem No</th>
                                    <th>Tip</th>
                                    <th>Ürün</th>
                                    <th>Müşteri</th>
                                    <th>Miktar</th>
                                    <th>Toplam</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($transaction['islem_no']) ?></strong></td>
                                        <td>
                                            <?php if ($transaction['islem_tipi'] == 'satis'): ?>
                                                <span class="badge bg-success">Satış</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Alış</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($transaction['urun_adi'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($transaction['musteri_adi'] ?? '-') ?></td>
                                        <td><?= $transaction['miktar'] ?> adet</td>
                                        <td>
                                            <strong class="<?= $transaction['islem_tipi'] == 'satis' ? 'text-success' : 'text-primary' ?>">
                                                <?= formatMoney($transaction['genel_toplam']) ?>
                                            </strong>
                                        </td>
                                        <td><?= formatDate($transaction['tarih']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="urunler.php?action=new" class="btn btn-outline-primary w-100 py-3">
                            <i class="bi bi-plus-circle fs-4 d-block mb-2"></i>
                            Yeni Ürün Ekle
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="musteriler.php?action=new" class="btn btn-outline-info w-100 py-3">
                            <i class="bi bi-person-plus fs-4 d-block mb-2"></i>
                            Yeni Müşteri Ekle
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="islemler.php?action=new&type=satis" class="btn btn-outline-success w-100 py-3">
                            <i class="bi bi-cart-plus fs-4 d-block mb-2"></i>
                            Yeni Satış
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="islemler.php?action=new&type=alis" class="btn btn-outline-warning w-100 py-3">
                            <i class="bi bi-bag-plus fs-4 d-block mb-2"></i>
                            Yeni Alış
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Satış/Alış Grafiği
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart');
    if (ctx) {
        const salesData = <?= json_encode($salesChartData) ?>;
        const purchaseData = <?= json_encode($purchaseChartData) ?>;
        
        // Son 7 günün tarihlerini oluştur
        const labels = [];
        const salesValues = [];
        const purchaseValues = [];
        
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            labels.push(date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' }));
            
            // Satış verisi
            const saleDay = salesData.find(d => d.tarih === dateStr);
            salesValues.push(saleDay ? parseFloat(saleDay.toplam) : 0);
            
            // Alış verisi
            const purchaseDay = purchaseData.find(d => d.tarih === dateStr);
            purchaseValues.push(purchaseDay ? parseFloat(purchaseDay.toplam) : 0);
        }
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Satış',
                        data: salesValues,
                        backgroundColor: 'rgba(6, 214, 160, 0.8)',
                        borderColor: 'rgba(6, 214, 160, 1)',
                        borderWidth: 1,
                        borderRadius: 5
                    },
                    {
                        label: 'Alış',
                        data: purchaseValues,
                        backgroundColor: 'rgba(67, 97, 238, 0.8)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 1,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₺' + value.toLocaleString('tr-TR');
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
