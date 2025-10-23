<?php
include '../backend/auth_check.php';
checkRole('user');
include '../backend/db_connect.php';

$message = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');

    if ($full_name && $email) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE email = :email AND id != :id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $message = "Bu e-posta adresi zaten kullanılıyor.";
            } else {
                $update_query = "UPDATE user SET full_name = :full_name, email = :email";
                $params = [':full_name' => $full_name, ':email' => $email, ':id' => $user_id];
                if ($password) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_query .= ", password = :password";
                    $params[':password'] = $hashed_password;
                }
                $update_query .= " WHERE id = :id";
                $stmt = $db->prepare($update_query);
                $stmt->execute($params);
                $message = "Profil güncellendi.";
            }
        } catch (PDOException $e) {
            $message = "Güncelleme hatası.";
        }
    } else {
        $message = "Lütfen tüm alanları doldurun.";
    }
}

// Mevcut bilgileri çek
$stmt = $db->prepare("SELECT full_name, email FROM user WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>BiletYol - Profil Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg" style="background-color: #870000;">
        <div class="container">
            <a class="navbar-brand text-white" href="../index.php">BiletYol</a>
            <div class="navbar-nav">
                <a class="nav-link text-white" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2>Profil Düzenle</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">E-posta</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Yeni Parola (Değiştirmek istemiyorsanız boş bırakın)</label>
                <input type="password" class="form-control" name="password">
            </div>
            <button type="submit" class="btn btn-primary">Güncelle</button>
        </form>
    </div>
</body>
</html>