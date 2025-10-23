<?php
// Hata raporlamasını kullanıcıdan gizle
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

session_start();

// CSRF token oluştur (eğer yoksa)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../backend/db_connect.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token doğrula
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Geçersiz istek. Lütfen tekrar deneyin.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password'] ?? '');

        if ($full_name && $email && $password) {
            try {
                // E-posta zaten varsa kontrol et
                $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $message = "Bu e-posta adresi zaten kullanılıyor.";
                } else {
                    // Parolayı hashle
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    // Kullanıcıyı ekle
                    $stmt = $db->prepare("INSERT INTO user (full_name, email, password, role) VALUES (:full_name, :email, :password, 'user')");
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->execute();
                    $_SESSION['user_id'] = $db->lastInsertId();
                    // CSRF token'ı yenile (güvenlik için)
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header("Location: ../index.php");
                    exit;
                }
            } catch (PDOException $e) {
                $message = "Kayıt sırasında bir hata oluştu, lütfen tekrar deneyin.";
            }
        } else {
            $message = "Lütfen tüm alanları doldurun.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiletYol - Kayıt Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f8f9fa; }
        .auth-container { max-width: 400px; margin: 50px auto; padding: 20px; background-color: white; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .alert { margin-top: 10px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg" style="background-color: #870000;">
        <div class="container">
            <a class="navbar-brand text-white" href="../index.php">BiletYol</a>
        </div>
    </nav>

    <!-- Register Container -->
    <div class="auth-container">
        <h2 class="text-center mb-4">Kayıt Ol</h2>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="register.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="full_name" class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required>
            </div>
            <div class="mb-3">
                <label for="email_register" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="email_register" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password_register" class="form-label">Parola</label>
                <input type="password" class="form-control" id="password_register" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>