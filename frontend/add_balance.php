<?php
include '../backend/auth_check.php';
checkRole('user');
include '../backend/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? 0;
    if ($amount > 0) {
        $stmt = $db->prepare("UPDATE user SET balance = balance + :amount WHERE id = :user_id");
        $stmt->execute([':amount' => $amount, ':user_id' => $_SESSION['user_id']]);
        header("Location: account.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>BiletYol - Bakiye Yükle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Bakiye Yükle</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Yüklemek İstediğiniz Miktar (TL)</label>
                <input type="number" class="form-control" name="amount" min="1" required>
            </div>
            <button type="submit" class="btn btn-primary">Yükle</button>
        </form>
    </div>
</body>
</html>