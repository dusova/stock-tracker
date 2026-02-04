<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Raporlar Sayfası
 */

require_once 'includes/header.php';

$db = getDB();

// Tarih filtreleri
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report'] ?? 'summary';

try {
    // Özet rapor verileri
    
    // Satış özeti
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as islem_sayisi,
            COALESCE(SUM(genel_toplam), 0) as toplam_tutar,
            COALESCE(SUM(kdv_tutari), 0) as toplam_kdv,
            COALESCE(SUM(ara_toplam), 0) as toplam_net
        FROM islemler 
        WHERE islem_tipi = 'satis' AND DATE(tarih) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $satisOzet = $stmt->fetch();

    // Alış özeti
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as islem_sayisi,
            COALESCE(SUM(genel_toplam), 0) as toplam_tutar,
            COALESCE(SUM(kdv_tutari), 0) as toplam_kdv,
            COALESCE(SUM(ara_toplam), 0) as toplam_net
        FROM islemler 
        WHERE islem_tipi = 'alis' AND DATE(tarih) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $alisOzet = $stmt->fetch();

    // En çok satan ürünler
    $stmt = $db->prepare("
        SELECT u.urun_adi, u.urun_kodu, SUM(i.miktar) as toplam_miktar, SUM(i.genel_toplam) as toplam_tutar
        FROM islemler i
        JOIN urunler u ON i.urun_id = u.id
        WHERE i.islem_tipi = 'satis' AND DATE(i.tarih) BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY toplam_miktar DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $enCokSatanlar = $stmt->fetchAll();

    // En çok alışveriş yapan müşteriler
    $stmt = $db->prepare("
        SELECT m.ad_soyad, COUNT(i.id) as islem_sayisi, SUM(i.genel_toplam) as toplam_tutar
        FROM islemler i
        JOIN musteriler m ON i.musteri_id = m.id
        WHERE i.islem_tipi = 'satis' AND DATE(i.tarih) BETWEEN ? AND ?
        GROUP BY m.id
        ORDER BY toplam_tutar DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $enIyiMusteriler = $stmt->fetchAll();

    // Kategori bazlı satışlar
    $stmt = $db->prepare("
        SELECT k.kategori_adi, COUNT(i.id) as islem_sayisi, SUM(i.miktar) as toplam_miktar, SUM(i.genel_toplam) as toplam_tutar
        FROM islemler i
        JOIN urunler u ON i.urun_id = u.id
        LEFT JOIN kategoriler k ON u.kategori_id = k.id
        WHERE i.islem_tipi = 'satis' AND DATE(i.tarih) BETWEEN ? AND ?
        GROUP BY k.id
        ORDER BY toplam_tutar DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $kategoriBazliSatislar = $stmt->fetchAll();

    // Günlük satış trendi
    $stmt = $db->prepare("
        SELECT DATE(tarih) as gun, SUM(genel_toplam) as toplam
        FROM islemler 
        WHERE islem_tipi = 'satis' AND DATE(tarih) BETWEEN ? AND ?
        GROUP BY DATE(tarih)
        ORDER BY gun ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $gunlukSatislar = $stmt->fetchAll();

    // Ödeme şekline göre dağılım
    $stmt = $db->prepare("
        SELECT odeme_sekli, COUNT(*) as islem_sayisi, SUM(genel_toplam) as toplam_tutar
        FROM islemler 
        WHERE islem_tipi = 'satis' AND DATE(tarih) BETWEEN ? AND ?
        GROUP BY odeme_sekli
    ");
    $stmt->execute([$startDate, $endDate]);
    $odemeDagilimi = $stmt->fetchAll();

    // Stok durumu özeti
    $stmt = $db->query("
        SELECT 
            COUNT(*) as toplam_urun,
            SUM(CASE WHEN mevcut_stok <= kritik_stok THEN 1 ELSE 0 END) as dusuk_stok,
            SUM(CASE WHEN mevcut_stok = 0 THEN 1 ELSE 0 END) as stokta_yok,
            SUM(mevcut_stok * satis_fiyati) as toplam_deger
        FROM urunler WHERE durum = 'aktif'
    ");
    $stokOzet = $stmt->fetch();

    // Kar marjı hesaplama
    $karMarji = $satisOzet['toplam_net'] - $alisOzet['toplam_net'];

} catch (PDOException $e) {
    $satisOzet = ['islem_sayisi' => 0, 'toplam_tutar' => 0, 'toplam_kdv' => 0, 'toplam_net' => 0];
    $alisOzet = ['islem_sayisi' => 0, 'toplam_tutar' => 0, 'toplam_kdv' => 0, 'toplam_net' => 0];
    $enCokSatanlar = [];
    $enIyiMusteriler = [];
    $kategoriBazliSatislar = [];
    $gunlukSatislar = [];
    $odemeDagilimi = [];
    $stokOzet = ['toplam_urun' => 0, 'dusuk_stok' => 0, 'stokta_yok' => 0, 'toplam_deger' => 0];
    $karMarji = 0;
}
?>

<!-- Page Title -->
<div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Raporlar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Raporlar</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger" onclick="generatePDF()">
            <i class="bi bi-file-pdf me-2"></i>PDF İndir
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Raporu Güncelle
                </button>
            </div>
            <div class="col-md-3">
                <div class="btn-group w-100">
                    <a href="?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Bu Ay</a>
                    <a href="?start_date=<?= date('Y-01-01') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Bu Yıl</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon success">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="value"><?= formatMoney($satisOzet['toplam_tutar']) ?></div>
            <div class="label">Toplam Satış (<?= $satisOzet['islem_sayisi'] ?> işlem)</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon primary">
                <i class="bi bi-graph-down-arrow"></i>
            </div>
            <div class="value"><?= formatMoney($alisOzet['toplam_tutar']) ?></div>
            <div class="label">Toplam Alış (<?= $alisOzet['islem_sayisi'] ?> işlem)</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon <?= $karMarji >= 0 ? 'success' : 'danger' ?>">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="value"><?= formatMoney($karMarji) ?></div>
            <div class="label">Tahmini Kar Marjı</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="icon warning">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="value"><?= formatMoney($satisOzet['toplam_kdv']) ?></div>
            <div class="label">Toplam KDV (Satış)</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Günlük Satış Trendi</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Ödeme Dağılımı</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>En Çok Satan Ürünler</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Miktar</th>
                                <th>Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enCokSatanlar)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Veri bulunamadı</td></tr>
                            <?php else: ?>
                                <?php foreach ($enCokSatanlar as $i => $urun): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?= $i + 1 ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($urun['urun_adi']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($urun['urun_kodu']) ?></small>
                                        </td>
                                        <td><?= number_format($urun['toplam_miktar']) ?> adet</td>
                                        <td><strong><?= formatMoney($urun['toplam_tutar']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-star me-2"></i>En İyi Müşteriler</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Müşteri</th>
                                <th>İşlem</th>
                                <th>Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enIyiMusteriler)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Veri bulunamadı</td></tr>
                            <?php else: ?>
                                <?php foreach ($enIyiMusteriler as $i => $musteri): ?>
                                    <tr>
                                        <td><span class="badge bg-success"><?= $i + 1 ?></span></td>
                                        <td><strong><?= htmlspecialchars($musteri['ad_soyad']) ?></strong></td>
                                        <td><?= $musteri['islem_sayisi'] ?> işlem</td>
                                        <td><strong><?= formatMoney($musteri['toplam_tutar']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Sales & Stock Summary -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tags me-2"></i>Kategori Bazlı Satışlar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>İşlem</th>
                                <th>Miktar</th>
                                <th>Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kategoriBazliSatislar)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Veri bulunamadı</td></tr>
                            <?php else: ?>
                                <?php foreach ($kategoriBazliSatislar as $kategori): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($kategori['kategori_adi'] ?? 'Kategorisiz') ?></strong></td>
                                        <td><?= $kategori['islem_sayisi'] ?></td>
                                        <td><?= number_format($kategori['toplam_miktar']) ?> adet</td>
                                        <td><strong><?= formatMoney($kategori['toplam_tutar']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Stok Durumu Özeti</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-4">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-primary"><?= number_format($stokOzet['toplam_urun']) ?></div>
                            <div class="text-muted">Toplam Ürün</div>
                        </div>
                    </div>
                    <div class="col-6 mb-4">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-success"><?= formatMoney($stokOzet['toplam_deger']) ?></div>
                            <div class="text-muted">Stok Değeri</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-warning"><?= number_format($stokOzet['dusuk_stok']) ?></div>
                            <div class="text-muted">Düşük Stok</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-danger"><?= number_format($stokOzet['stokta_yok']) ?></div>
                            <div class="text-muted">Stokta Yok</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden PDF Template -->
<div id="pdfContent" style="display: none;">
    <div style="font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
        <!-- Header -->
        <div style="text-align: center; border-bottom: 3px solid #4361ee; padding-bottom: 20px; margin-bottom: 30px;">
            <h1 style="margin: 0; color: #1a1a2e; font-size: 24px;">Cemil Çalışkan</h1>
            <h2 style="margin: 5px 0; color: #4361ee; font-size: 18px;">Stok Takip Sistemi - Satış Raporu</h2>
            <p style="margin: 10px 0 0; color: #666; font-size: 12px;">
                Tarih Aralığı: <strong><?= date('d.m.Y', strtotime($startDate)) ?></strong> - <strong><?= date('d.m.Y', strtotime($endDate)) ?></strong>
                &nbsp;|&nbsp; Rapor Tarihi: <strong><?= date('d.m.Y H:i') ?></strong>
            </p>
        </div>
        
        <!-- Summary Stats -->
        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 30px;">
            <div style="flex: 1; min-width: 150px; background: linear-gradient(135deg, #06d6a0 0%, #05b88a 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold;"><?= formatMoney($satisOzet['toplam_tutar']) ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Toplam Satış</div>
                <div style="font-size: 11px; opacity: 0.8;">(<?= $satisOzet['islem_sayisi'] ?> işlem)</div>
            </div>
            <div style="flex: 1; min-width: 150px; background: linear-gradient(135deg, #4361ee 0%, #3651d4 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold;"><?= formatMoney($alisOzet['toplam_tutar']) ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Toplam Alış</div>
                <div style="font-size: 11px; opacity: 0.8;">(<?= $alisOzet['islem_sayisi'] ?> işlem)</div>
            </div>
            <div style="flex: 1; min-width: 150px; background: linear-gradient(135deg, <?= $karMarji >= 0 ? '#06d6a0' : '#ef476f' ?> 0%, <?= $karMarji >= 0 ? '#05b88a' : '#d63d5e' ?> 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold;"><?= formatMoney($karMarji) ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Kar Marjı</div>
            </div>
            <div style="flex: 1; min-width: 150px; background: linear-gradient(135deg, #ffd166 0%, #e6bc5a 100%); color: #1a1a2e; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold;"><?= formatMoney($satisOzet['toplam_kdv']) ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Toplam KDV</div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
            <!-- En Çok Satanlar -->
            <div style="flex: 1; min-width: 300px;">
                <h3 style="color: #1a1a2e; font-size: 14px; border-bottom: 2px solid #4361ee; padding-bottom: 8px; margin-bottom: 15px;">
                    🏆 En Çok Satan Ürünler
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">#</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Ürün</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Miktar</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enCokSatanlar)): ?>
                            <tr><td colspan="4" style="padding: 15px; text-align: center; color: #999;">Veri bulunamadı</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($enCokSatanlar, 0, 5) as $i => $urun): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= $i + 1 ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($urun['urun_adi']) ?></td>
                                    <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;"><?= number_format($urun['toplam_miktar']) ?></td>
                                    <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee; font-weight: bold;"><?= formatMoney($urun['toplam_tutar']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- En İyi Müşteriler -->
            <div style="flex: 1; min-width: 300px;">
                <h3 style="color: #1a1a2e; font-size: 14px; border-bottom: 2px solid #06d6a0; padding-bottom: 8px; margin-bottom: 15px;">
                    ⭐ En İyi Müşteriler
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">#</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Müşteri</th>
                            <th style="padding: 8px; text-align: center; border-bottom: 1px solid #ddd;">İşlem</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enIyiMusteriler)): ?>
                            <tr><td colspan="4" style="padding: 15px; text-align: center; color: #999;">Veri bulunamadı</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($enIyiMusteriler, 0, 5) as $i => $musteri): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= $i + 1 ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($musteri['ad_soyad']) ?></td>
                                    <td style="padding: 8px; text-align: center; border-bottom: 1px solid #eee;"><?= $musteri['islem_sayisi'] ?></td>
                                    <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee; font-weight: bold;"><?= formatMoney($musteri['toplam_tutar']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Second Row Tables -->
        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
            <!-- Kategori Bazlı -->
            <div style="flex: 1; min-width: 300px;">
                <h3 style="color: #1a1a2e; font-size: 14px; border-bottom: 2px solid #ffd166; padding-bottom: 8px; margin-bottom: 15px;">
                    📦 Kategori Bazlı Satışlar
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Kategori</th>
                            <th style="padding: 8px; text-align: center; border-bottom: 1px solid #ddd;">İşlem</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Miktar</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kategoriBazliSatislar)): ?>
                            <tr><td colspan="4" style="padding: 15px; text-align: center; color: #999;">Veri bulunamadı</td></tr>
                        <?php else: ?>
                            <?php foreach ($kategoriBazliSatislar as $kategori): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($kategori['kategori_adi'] ?? 'Kategorisiz') ?></td>
                                    <td style="padding: 8px; text-align: center; border-bottom: 1px solid #eee;"><?= $kategori['islem_sayisi'] ?></td>
                                    <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;"><?= number_format($kategori['toplam_miktar']) ?></td>
                                    <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee; font-weight: bold;"><?= formatMoney($kategori['toplam_tutar']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Ödeme Dağılımı -->
            <div style="flex: 1; min-width: 300px;">
                <h3 style="color: #1a1a2e; font-size: 14px; border-bottom: 2px solid #ef476f; padding-bottom: 8px; margin-bottom: 15px;">
                    💳 Ödeme Şekli Dağılımı
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Ödeme Şekli</th>
                            <th style="padding: 8px; text-align: center; border-bottom: 1px solid #ddd;">İşlem</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $odemeLabels = [
                            'nakit' => 'Nakit',
                            'kredi_karti' => 'Kredi Kartı',
                            'havale' => 'Havale/EFT',
                            'cek' => 'Çek',
                            'vadeli' => 'Vadeli'
                        ];
                        if (empty($odemeDagilimi)): ?>
                            <tr><td colspan="3" style="padding: 15px; text-align: center; color: #999;">Veri bulunamadı</td></tr>
                        <?php else: ?>
                            <?php foreach ($odemeDagilimi as $odeme): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= $odemeLabels[$odeme['odeme_sekli']] ?? $odeme['odeme_sekli'] ?></td>
                                    <td style="padding: 8px; text-align: center; border-bottom: 1px solid #eee;"><?= $odeme['islem_sayisi'] ?></td>
                                    <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee; font-weight: bold;"><?= formatMoney($odeme['toplam_tutar']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Günlük Satış Tablosu -->
        <div style="margin-bottom: 30px;">
            <h3 style="color: #1a1a2e; font-size: 14px; border-bottom: 2px solid #118ab2; padding-bottom: 8px; margin-bottom: 15px;">
                📊 Günlük Satış Detayı
            </h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Tarih</th>
                        <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Satış Tutarı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gunlukSatislar)): ?>
                        <tr><td colspan="2" style="padding: 15px; text-align: center; color: #999;">Veri bulunamadı</td></tr>
                    <?php else: ?>
                        <?php foreach ($gunlukSatislar as $gun): ?>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= date('d.m.Y', strtotime($gun['gun'])) ?></td>
                                <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee; font-weight: bold;"><?= formatMoney($gun['toplam']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Stok Özeti -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
            <h3 style="color: #1a1a2e; font-size: 14px; margin: 0 0 15px 0;">📦 Stok Durumu Özeti</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 100px; text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #4361ee;"><?= number_format($stokOzet['toplam_urun']) ?></div>
                    <div style="font-size: 11px; color: #666;">Toplam Ürün</div>
                </div>
                <div style="flex: 1; min-width: 100px; text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #06d6a0;"><?= formatMoney($stokOzet['toplam_deger']) ?></div>
                    <div style="font-size: 11px; color: #666;">Stok Değeri</div>
                </div>
                <div style="flex: 1; min-width: 100px; text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #ffd166;"><?= number_format($stokOzet['dusuk_stok']) ?></div>
                    <div style="font-size: 11px; color: #666;">Düşük Stok</div>
                </div>
                <div style="flex: 1; min-width: 100px; text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #ef476f;"><?= number_format($stokOzet['stokta_yok']) ?></div>
                    <div style="font-size: 11px; color: #666;">Stokta Yok</div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 10px;">
            Bu rapor Cemil Çalışkan Stok Takip Sistemi tarafından otomatik olarak oluşturulmuştur.
        </div>
    </div>
</div>

<!-- html2pdf library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Günlük Satış Trendi
    const salesData = <?= json_encode($gunlukSatislar) ?>;
    const salesCtx = document.getElementById('salesTrendChart');
    
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(d => {
                    const date = new Date(d.gun);
                    return date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: 'Satış',
                    data: salesData.map(d => parseFloat(d.toplam)),
                    borderColor: '#06d6a0',
                    backgroundColor: 'rgba(6, 214, 160, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
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
    
    // Ödeme Dağılımı
    const paymentData = <?= json_encode($odemeDagilimi) ?>;
    const paymentCtx = document.getElementById('paymentChart');
    
    const paymentLabels = {
        'nakit': 'Nakit',
        'kredi_karti': 'Kredi Kartı',
        'havale': 'Havale/EFT',
        'cek': 'Çek',
        'vadeli': 'Vadeli'
    };
    
    if (paymentCtx && paymentData.length > 0) {
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentData.map(d => paymentLabels[d.odeme_sekli] || d.odeme_sekli),
                datasets: [{
                    data: paymentData.map(d => parseFloat(d.toplam_tutar)),
                    backgroundColor: ['#06d6a0', '#4361ee', '#ffd166', '#ef476f', '#118ab2']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});

// PDF Generation Function
function generatePDF() {
    const element = document.getElementById('pdfContent');
    const fileName = 'Rapor_<?= date('Y-m-d') ?>.pdf';
    
    // Show loading
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>PDF Oluşturuluyor...';
    btn.disabled = true;
    
    // Show the hidden content temporarily
    element.style.display = 'block';
    
    const opt = {
        margin: 10,
        filename: fileName,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            letterRendering: true
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait'
        },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    html2pdf().set(opt).from(element).save().then(function() {
        // Hide the content again
        element.style.display = 'none';
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    }).catch(function(error) {
        console.error('PDF generation error:', error);
        element.style.display = 'none';
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('PDF oluşturulurken bir hata oluştu.');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
