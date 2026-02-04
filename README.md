# Cemil Çalışkan Stok Takip Sistemi

Modern ve kullanıcı dostu bir PHP tabanlı stok takip uygulaması.

## Özellikler

### Dashboard
- Toplam ürün sayısı
- Toplam stok değeri
- Müşteri sayısı
- Toplam borç ve alacak
- Bugünkü ve bu ayki satışlar
- Düşük stok uyarıları
- Son işlemler listesi
- Satış/Alış grafikleri
- Hızlı işlem butonları

### Ürün Yönetimi
- Ürün ekleme, düzenleme, silme
- Ürün kodu, adı, kategori
- Alış ve satış fiyatları
- Mevcut stok takibi
- Kritik stok seviyesi belirleme
- Düşük stok uyarısı
- Ürün arama ve filtreleme

### Müşteri Yönetimi
- Müşteri ekleme, düzenleme, silme
- Ad soyad, telefon, e-posta, adres, IBAN
- Başlangıç bakiyesi belirleme
- Borç/Alacak takibi
- Müşteri ödeme alma
- Müşteri detay görüntüleme
- İşlem geçmişi

### İşlem Yönetimi
- Satış ve alış işlemleri
- Otomatik stok güncelleme
- KDV hesaplama (%0, %1, %8, %10, %18, %20)
- Müşteri seçimi (opsiyonel)
- Ödeme şekilleri (Nakit, Kredi Kartı, Havale, Çek, Vadeli)
- Tarih ve saat seçimi
- İşlem notu ekleme
- Tarih aralığı filtreleme
- İşlem arama

### Raporlar
- Satış ve alış özetleri
- Kar marjı hesaplama
- KDV raporları
- En çok satan ürünler
- En iyi müşteriler
- Kategori bazlı satışlar
- Günlük satış trendi grafiği
- Ödeme dağılımı grafiği
- Stok durumu özeti

### Ek Özellikler
- Kategori yönetimi
- Firma ayarları
- Şifre değiştirme
- Veritabanı yedekleme
- Responsive tasarım
- Modern UI/UX

## Kurulum

### Gereksinimler
- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri
- Apache/Nginx web sunucusu
- PDO PHP eklentisi

### Adımlar

1. **Dosyaları Kopyalayın**
   ```
   Tüm dosyaları web sunucunuzun klasörüne (örn: htdocs/stok-takip) kopyalayın.
   ```

2. **Veritabanını Oluşturun**
   ```
   phpMyAdmin veya MySQL komut satırından database.sql dosyasını çalıştırın.
   ```

3. **Veritabanı Ayarlarını Yapın**
   ```
   config/database.php dosyasındaki ayarları düzenleyin:
   - DB_HOST: Veritabanı sunucusu (varsayılan: localhost)
   - DB_NAME: Veritabanı adı (varsayılan: stok_takip)
   - DB_USER: Kullanıcı adı (varsayılan: root)
   - DB_PASS: Şifre (varsayılan: boş)
   ```

4. **Uygulamayı Açın**
   ```
   http://localhost/stok-takip/
   ```

### Varsayılan Giriş Bilgileri
- Kullanıcı Adı: admin
- Şifre: admin123

## Dosya Yapısı

```
stok-takip/
├── api/
│   ├── customers.php
│   ├── products.php
│   └── transactions.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── config/
│   └── database.php
├── includes/
│   ├── header.php
│   └── footer.php
├── ayarlar.php
├── database.sql
├── index.php
├── islemler.php
├── kategoriler.php
├── musteriler.php
├── raporlar.php
├── urunler.php
└── README.md
```

## Teknolojiler

- **Backend:** PHP 7.4+, PDO
- **Veritabanı:** MySQL
- **Frontend:** HTML5, CSS3, JavaScript
- **Framework:** Bootstrap 5
- **Kütüphaneler:**
  - DataTables (Tablo işlemleri)
  - Select2 (Gelişmiş seçim kutuları)
  - Chart.js (Grafikler)
  - SweetAlert2 (Bildirimler)
  - Bootstrap Icons

## Lisans

Bu proje Cemil Çalışkan için özel olarak geliştirilmiştir.

## Versiyon

v1.0.0 - Şubat 2026
