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
include '../backend/auth_check.php'; // Rolü session'a set etmek için

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token doğrula
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Geçersiz istek. Lütfen tekrar deneyin.";
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password'] ?? '');

        if ($email && $password) {
            try {
                $stmt = $db->prepare("SELECT id, password, role FROM user WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role']; // Değişiklik: Direkt set

                    // CSRF token'ı yenile
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // Rol bazlı yönlendirme
                    if ($_SESSION['role'] === 'admin') {
                        header("Location: admin_panel.php");
                    } elseif ($_SESSION['role'] === 'company_admin') {
                        header("Location: company_admin_panel.php");
                    } else {
                        header("Location: ../index.php");
                    }
                    exit;
                } else {
                    $message = "Kullanıcı adı veya şifre hatalı.";
                }
            } catch (PDOException $e) {
                $message = "Kullanıcı adı veya şifre hatalı.";
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
    <title>BiletYol - Giriş Yap</title>
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

    <!-- Login Container -->
    <div class="auth-container">
        <h2 class="text-center mb-4">Giriş Yap</h2>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="email_login" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="email_login" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password_login" class="form-label">Parola</label>
                <input type="password" class="form-control" id="password_login" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>