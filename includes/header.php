<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="logo-wrapper">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>Stok Takip</span>
                </div>
                <small style="color: white;">Cemil Çalışkan</small>
            </div>

            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $currentPage == 'index' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="urunler.php" class="nav-link <?= $currentPage == 'urunler' ? 'active' : '' ?>">
                        <i class="bi bi-box"></i>
                        <span>Ürünler</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="musteriler.php" class="nav-link <?= $currentPage == 'musteriler' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i>
                        <span>Müşteriler</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="islemler.php" class="nav-link <?= $currentPage == 'islemler' ? 'active' : '' ?>">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>İşlemler</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="raporlar.php" class="nav-link <?= $currentPage == 'raporlar' ? 'active' : '' ?>">
                        <i class="bi bi-graph-up"></i>
                        <span>Raporlar</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="kategoriler.php" class="nav-link <?= $currentPage == 'kategoriler' ? 'active' : '' ?>">
                        <i class="bi bi-tags"></i>
                        <span>Kategoriler</span>
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a href="ayarlar.php" class="nav-link <?= $currentPage == 'ayarlar' ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i>
                        <span>Ayarlar</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <small class="text-muted">v<?= APP_VERSION ?></small>
            </div>
        </nav>

        <!-- Main Content -->
        <div id="content" class="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarToggle" class="btn btn-link">
                        <i class="bi bi-list fs-4"></i>
                    </button>

                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle text-dark text-decoration-none" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <span><?= htmlspecialchars(getCurrentUserName()) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="ayarlar.php"><i class="bi bi-gear me-2"></i>Ayarlar</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <div class="container-fluid px-4">
