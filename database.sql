-- Cemil Çalışkan Stok Takip Sistemi
-- Veritabanı Oluşturma

CREATE DATABASE IF NOT EXISTS stok_takip CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE stok_takip;

-- Kategoriler Tablosu
CREATE TABLE IF NOT EXISTS kategoriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_adi VARCHAR(100) NOT NULL,
    aciklama TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ürünler Tablosu
CREATE TABLE IF NOT EXISTS urunler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    urun_kodu VARCHAR(50) UNIQUE NOT NULL,
    urun_adi VARCHAR(255) NOT NULL,
    kategori_id INT,
    alis_fiyati DECIMAL(10, 2) DEFAULT 0.00,
    satis_fiyati DECIMAL(10, 2) DEFAULT 0.00,
    mevcut_stok INT DEFAULT 0,
    kritik_stok INT DEFAULT 10,
    aciklama TEXT,
    durum ENUM('aktif', 'pasif') DEFAULT 'aktif',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategoriler(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Müşteriler Tablosu
CREATE TABLE IF NOT EXISTS musteriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_soyad VARCHAR(255) NOT NULL,
    telefon VARCHAR(20),
    eposta VARCHAR(255),
    adres TEXT,
    iban VARCHAR(50),
    baslangic_bakiye DECIMAL(10, 2) DEFAULT 0.00,
    mevcut_bakiye DECIMAL(10, 2) DEFAULT 0.00,
    durum ENUM('aktif', 'pasif') DEFAULT 'aktif',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- İşlemler Tablosu
CREATE TABLE IF NOT EXISTS islemler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    islem_no VARCHAR(50) UNIQUE NOT NULL,
    islem_tipi ENUM('alis', 'satis') NOT NULL,
    tarih DATETIME NOT NULL,
    urun_id INT,
    miktar INT NOT NULL,
    birim_fiyat DECIMAL(10, 2) NOT NULL,
    kdv_orani DECIMAL(5, 2) DEFAULT 18.00,
    ara_toplam DECIMAL(10, 2) NOT NULL,
    kdv_tutari DECIMAL(10, 2) NOT NULL,
    genel_toplam DECIMAL(10, 2) NOT NULL,
    musteri_id INT,
    odeme_sekli ENUM('nakit', 'kredi_karti', 'havale', 'cek', 'vadeli') DEFAULT 'nakit',
    not_aciklama TEXT,
    durum ENUM('tamamlandi', 'beklemede', 'iptal') DEFAULT 'tamamlandi',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE SET NULL,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Ödemeler Tablosu
CREATE TABLE IF NOT EXISTS odemeler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    musteri_id INT NOT NULL,
    tutar DECIMAL(10, 2) NOT NULL,
    odeme_tarihi DATETIME NOT NULL,
    odeme_sekli ENUM('nakit', 'kredi_karti', 'havale', 'cek', 'diger') DEFAULT 'nakit',
    aciklama TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Stok Hareketleri Tablosu (Detaylı Takip)
CREATE TABLE IF NOT EXISTS stok_hareketleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    urun_id INT NOT NULL,
    hareket_tipi ENUM('giris', 'cikis', 'duzeltme') NOT NULL,
    miktar INT NOT NULL,
    onceki_stok INT NOT NULL,
    sonraki_stok INT NOT NULL,
    islem_id INT,
    aciklama TEXT,
    tarih TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE CASCADE,
    FOREIGN KEY (islem_id) REFERENCES islemler(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Ayarlar Tablosu
CREATE TABLE IF NOT EXISTS ayarlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anahtar VARCHAR(100) UNIQUE NOT NULL,
    deger TEXT,
    aciklama VARCHAR(255)
) ENGINE=InnoDB;

-- Kullanıcılar Tablosu
CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) UNIQUE NOT NULL,
    sifre VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(100) NOT NULL,
    eposta VARCHAR(255),
    rol ENUM('admin', 'kullanici') DEFAULT 'kullanici',
    son_giris DATETIME,
    durum ENUM('aktif', 'pasif') DEFAULT 'aktif',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Varsayılan Kategoriler
INSERT INTO kategoriler (kategori_adi, aciklama) VALUES
('Elektronik', 'Elektronik ürünler'),
('Gıda', 'Gıda ürünleri'),
('Giyim', 'Giyim ürünleri'),
('Ev & Yaşam', 'Ev ve yaşam ürünleri'),
('Kozmetik', 'Kozmetik ürünleri'),
('Kırtasiye', 'Kırtasiye ürünleri'),
('Diğer', 'Diğer ürünler');

-- Varsayılan Ayarlar
INSERT INTO ayarlar (anahtar, deger, aciklama) VALUES
('firma_adi', 'Cemil Çalışkan', 'Firma Adı'),
('firma_adres', '', 'Firma Adresi'),
('firma_telefon', '', 'Firma Telefonu'),
('firma_eposta', '', 'Firma E-posta'),
('kdv_orani', '18', 'Varsayılan KDV Oranı'),
('para_birimi', '₺', 'Para Birimi'),
('kritik_stok_varsayilan', '10', 'Varsayılan Kritik Stok Seviyesi');

-- Varsayılan Admin Kullanıcı (şifre: admin123)
INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, eposta, rol) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cemil Çalışkan', 'admin@example.com', 'admin');

-- İndeksler
CREATE INDEX idx_urunler_kod ON urunler(urun_kodu);
CREATE INDEX idx_urunler_kategori ON urunler(kategori_id);
CREATE INDEX idx_musteriler_telefon ON musteriler(telefon);
CREATE INDEX idx_islemler_tarih ON islemler(tarih);
CREATE INDEX idx_islemler_tip ON islemler(islem_tipi);
CREATE INDEX idx_islemler_musteri ON islemler(musteri_id);
