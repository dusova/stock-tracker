<?php
/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Giriş Sayfası
 */

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// Cache önleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$error = '';

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Giriş işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ? AND durum = 'aktif'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['sifre'])) {
                // Eski session'ı temizle ve yeni ID oluştur
                session_regenerate_id(true);
                
                // Giriş başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['kullanici_adi'];
                $_SESSION['user_name'] = $user['ad_soyad'];
                $_SESSION['user_role'] = $user['rol'];
                $_SESSION['login_time'] = time();
                
                // Son giriş tarihini güncelle
                $stmt = $db->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı.';
            }
        } catch (PDOException $e) {
            $error = 'Veritabanı hatası. Lütfen tekrar deneyin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Giriş - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --dark-color: #1a1a2e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark-color) 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), #7209b7);
            padding: 40px 30px;
            text-align: center;
            color: #fff;
        }
        
        .login-header .logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group .form-control {
            padding-left: 45px;
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .input-group .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .input-group .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }
        
        .btn-login {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), #7209b7);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 30px 30px;
            color: #6c757d;
            font-size: 0.85rem;
            border-top: 1px solid #eee;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <h1>Stok Takip Sistemi</h1>
                <p>Cemil Çalışkan</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label">Kullanıcı Adı</label>
                        <div class="input-group">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" name="username" 
                                   placeholder="Kullanıcı adınızı girin" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                   required autofocus>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Şifre</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" name="password" id="password"
                                   placeholder="Şifrenizi girin" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Giriş Yap
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <i class="bi bi-shield-check me-1"></i>
                Güvenli Giriş | v<?= APP_VERSION ?>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
