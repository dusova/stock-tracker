-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 04 Şub 2026, 09:52:21
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `stok_takip`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ayarlar`
--

CREATE TABLE `ayarlar` (
  `id` int(11) NOT NULL,
  `anahtar` varchar(100) NOT NULL,
  `deger` text DEFAULT NULL,
  `aciklama` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `ayarlar`
--

INSERT INTO `ayarlar` (`id`, `anahtar`, `deger`, `aciklama`) VALUES
(1, 'firma_adi', 'Cemil Çalışkan', 'Firma Adı'),
(2, 'firma_adres', '', 'Firma Adresi'),
(3, 'firma_telefon', '', 'Firma Telefonu'),
(4, 'firma_eposta', '', 'Firma E-posta'),
(5, 'kdv_orani', '18', 'Varsayılan KDV Oranı'),
(6, 'para_birimi', '₺', 'Para Birimi'),
(7, 'kritik_stok_varsayilan', '10', 'Varsayılan Kritik Stok Seviyesi');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `islemler`
--

CREATE TABLE `islemler` (
  `id` int(11) NOT NULL,
  `islem_no` varchar(50) NOT NULL,
  `islem_tipi` enum('alis','satis') NOT NULL,
  `tarih` datetime NOT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `miktar` int(11) NOT NULL,
  `birim_fiyat` decimal(10,2) NOT NULL,
  `kdv_orani` decimal(5,2) DEFAULT 18.00,
  `ara_toplam` decimal(10,2) NOT NULL,
  `kdv_tutari` decimal(10,2) NOT NULL,
  `genel_toplam` decimal(10,2) NOT NULL,
  `musteri_id` int(11) DEFAULT NULL,
  `odeme_sekli` enum('nakit','kredi_karti','havale','cek','vadeli') DEFAULT 'nakit',
  `not_aciklama` text DEFAULT NULL,
  `durum` enum('tamamlandi','beklemede','iptal') DEFAULT 'tamamlandi',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `islemler`
--

INSERT INTO `islemler` (`id`, `islem_no`, `islem_tipi`, `tarih`, `urun_id`, `miktar`, `birim_fiyat`, `kdv_orani`, `ara_toplam`, `kdv_tutari`, `genel_toplam`, `musteri_id`, `odeme_sekli`, `not_aciklama`, `durum`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(4, 'ISL202602044250', 'alis', '2026-02-04 09:38:00', 2, 1, 45800.00, 18.00, 45800.00, 8244.00, 54044.00, NULL, 'nakit', '', 'tamamlandi', '2026-02-04 06:39:24', '2026-02-04 06:39:24'),
(6, 'ISL202602046476', 'satis', '2026-02-04 09:40:00', 2, 1, 49300.00, 18.00, 49300.00, 8874.00, 58174.00, 2, 'vadeli', '', 'tamamlandi', '2026-02-04 06:40:48', '2026-02-04 06:40:48');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kategoriler`
--

CREATE TABLE `kategoriler` (
  `id` int(11) NOT NULL,
  `kategori_adi` varchar(100) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `kategoriler`
--

INSERT INTO `kategoriler` (`id`, `kategori_adi`, `aciklama`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(1, 'Elektronik', 'Elektronik ürünler', '2026-02-04 06:12:30', '2026-02-04 06:12:30'),
(2, 'Gıda', 'Gıda ürünleri', '2026-02-04 06:12:30', '2026-02-04 06:12:30'),
(3, 'Giyim', 'Giyim ürünleri', '2026-02-04 06:12:30', '2026-02-04 06:12:30'),
(4, 'Ev & Yaşam', 'Ev ve yaşam ürünleri', '2026-02-04 06:12:30', '2026-02-04 06:12:30'),
(5, 'Kozmetik', 'Kozmetik ürünleri', '2026-02-04 06:12:30', '2026-02-04 06:12:30'),
(6, 'Kırtasiye', 'Kırtasiye ürünleri', '2026-02-04 06:12:30', '2026-02-04 06:12:30'),
(7, 'Diğer', 'Diğer ürünler', '2026-02-04 06:12:30', '2026-02-04 06:12:30');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanicilar`
--

CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL,
  `kullanici_adi` varchar(50) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `ad_soyad` varchar(100) NOT NULL,
  `eposta` varchar(255) DEFAULT NULL,
  `rol` enum('admin','kullanici') DEFAULT 'kullanici',
  `son_giris` datetime DEFAULT NULL,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `kullanicilar`
--

INSERT INTO `kullanicilar` (`id`, `kullanici_adi`, `sifre`, `ad_soyad`, `eposta`, `rol`, `son_giris`, `durum`, `olusturma_tarihi`) VALUES
(1, 'admin', '$2y$10$WT8AiFrDpEzQxY3QeffP1eoNr5wgIPeheefCW5BzEzYFcN1RobT1G', 'Cemil Çalışkan', 'admin@example.com', 'admin', NULL, 'aktif', '2026-02-04 06:12:31'),
(2, 'cemilcaliskan', '$2y$10$qqaaRVg4CdqkTUP7HTxasOdOLzzEviGhlbCPJU8NHZTVTFifdJPX2', 'Cemil Çalışkan', 'cemil@example.com', 'admin', '2026-02-04 10:04:31', 'aktif', '2026-02-04 06:20:09');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteriler`
--

CREATE TABLE `musteriler` (
  `id` int(11) NOT NULL,
  `ad_soyad` varchar(255) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `eposta` varchar(255) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `baslangic_bakiye` decimal(10,2) DEFAULT 0.00,
  `mevcut_bakiye` decimal(10,2) DEFAULT 0.00,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `musteriler`
--

INSERT INTO `musteriler` (`id`, `ad_soyad`, `telefon`, `eposta`, `adres`, `iban`, `baslangic_bakiye`, `mevcut_bakiye`, `durum`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(2, 'Cemil Çalışkan', '05382368273', 'info@cemilcaliskan.com', 'Silivri', 'TR72381293026321749124', 0.00, 58174.00, 'aktif', '2026-02-04 06:37:54', '2026-02-04 06:40:48');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `odemeler`
--

CREATE TABLE `odemeler` (
  `id` int(11) NOT NULL,
  `musteri_id` int(11) NOT NULL,
  `tutar` decimal(10,2) NOT NULL,
  `odeme_tarihi` datetime NOT NULL,
  `odeme_sekli` enum('nakit','kredi_karti','havale','cek','diger') DEFAULT 'nakit',
  `aciklama` text DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stok_hareketleri`
--

CREATE TABLE `stok_hareketleri` (
  `id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `hareket_tipi` enum('giris','cikis','duzeltme') NOT NULL,
  `miktar` int(11) NOT NULL,
  `onceki_stok` int(11) NOT NULL,
  `sonraki_stok` int(11) NOT NULL,
  `islem_id` int(11) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `tarih` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `stok_hareketleri`
--

INSERT INTO `stok_hareketleri` (`id`, `urun_id`, `hareket_tipi`, `miktar`, `onceki_stok`, `sonraki_stok`, `islem_id`, `aciklama`, `tarih`) VALUES
(4, 2, 'giris', 1, 2, 3, 4, 'Alış işlemi', '2026-02-04 06:39:24'),
(5, 2, 'cikis', 1, 3, 2, NULL, 'Satış işlemi', '2026-02-04 06:39:54'),
(6, 2, 'cikis', 1, 2, 1, 6, 'Satış işlemi', '2026-02-04 06:40:48');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `urunler`
--

CREATE TABLE `urunler` (
  `id` int(11) NOT NULL,
  `urun_kodu` varchar(50) NOT NULL,
  `urun_adi` varchar(255) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `alis_fiyati` decimal(10,2) DEFAULT 0.00,
  `satis_fiyati` decimal(10,2) DEFAULT 0.00,
  `mevcut_stok` int(11) DEFAULT 0,
  `kritik_stok` int(11) DEFAULT 10,
  `aciklama` text DEFAULT NULL,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `urunler`
--

INSERT INTO `urunler` (`id`, `urun_kodu`, `urun_adi`, `kategori_id`, `alis_fiyati`, `satis_fiyati`, `mevcut_stok`, `kritik_stok`, `aciklama`, `durum`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(2, 'IP14', 'iPhone 14 128GB', 1, 45800.00, 49300.00, 2, 10, '', 'aktif', '2026-02-04 06:38:34', '2026-02-04 06:42:14');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `ayarlar`
--
ALTER TABLE `ayarlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anahtar` (`anahtar`);

--
-- Tablo için indeksler `islemler`
--
ALTER TABLE `islemler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `islem_no` (`islem_no`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `idx_islemler_tarih` (`tarih`),
  ADD KEY `idx_islemler_tip` (`islem_tipi`),
  ADD KEY `idx_islemler_musteri` (`musteri_id`);

--
-- Tablo için indeksler `kategoriler`
--
ALTER TABLE `kategoriler`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kullanici_adi` (`kullanici_adi`);

--
-- Tablo için indeksler `musteriler`
--
ALTER TABLE `musteriler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_musteriler_telefon` (`telefon`);

--
-- Tablo için indeksler `odemeler`
--
ALTER TABLE `odemeler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `musteri_id` (`musteri_id`);

--
-- Tablo için indeksler `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `islem_id` (`islem_id`);

--
-- Tablo için indeksler `urunler`
--
ALTER TABLE `urunler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `urun_kodu` (`urun_kodu`),
  ADD KEY `idx_urunler_kod` (`urun_kodu`),
  ADD KEY `idx_urunler_kategori` (`kategori_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `ayarlar`
--
ALTER TABLE `ayarlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `islemler`
--
ALTER TABLE `islemler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `kategoriler`
--
ALTER TABLE `kategoriler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `kullanicilar`
--
ALTER TABLE `kullanicilar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `musteriler`
--
ALTER TABLE `musteriler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `odemeler`
--
ALTER TABLE `odemeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `urunler`
--
ALTER TABLE `urunler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `islemler`
--
ALTER TABLE `islemler`
  ADD CONSTRAINT `islemler_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `islemler_ibfk_2` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `odemeler`
--
ALTER TABLE `odemeler`
  ADD CONSTRAINT `odemeler_ibfk_1` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  ADD CONSTRAINT `stok_hareketleri_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stok_hareketleri_ibfk_2` FOREIGN KEY (`islem_id`) REFERENCES `islemler` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `urunler`
--
ALTER TABLE `urunler`
  ADD CONSTRAINT `urunler_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
