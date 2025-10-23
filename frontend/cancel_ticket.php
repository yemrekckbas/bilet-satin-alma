<?php
// Hata raporlamasını kullanıcıdan gizle (üretim için)
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Oturumu başlat
session_start();

// auth_check.php dosyasını dahil et (backend klasöründen)
$auth_check_path = '../backend/auth_check.php';
if (!file_exists($auth_check_path)) {
    error_log("Hata: auth_check.php dosyası bulunamadı: " . $auth_check_path, 3, __DIR__ . '/error.log');
    header("Location: login.php");
    exit;
}
include $auth_check_path;

// Rol kontrolü (yalnızca user rolü için)
checkRole('user');

include '../backend/db_connect.php';

// Ticket ID'yi al ve doğrula
$ticket_id = $_GET['ticket_id'] ?? '';
if (!$ticket_id) {
    header("Location: account.php?error=invalid_ticket");
    exit;
}

// POST isteği ile iptal işlemini gerçekleştir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Bilet bilgilerini çek (JOIN ile)
        $stmt = $db->prepare("SELECT t.trip_id, t.price_paid, tr.departure_time, tr.departure_city, tr.destination_city FROM tickets t JOIN trips tr ON t.trip_id = tr.id WHERE t.id = :ticket_id AND t.user_id = :user_id");
        $stmt->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket) {
            $departure_time = new DateTime($ticket['departure_time']);
            $now = new DateTime();
            $interval = $now->diff($departure_time);
            $hours = $interval->h + ($interval->days * 24);

            if ($hours > 1) {
                $db->beginTransaction();
                try {
                    // Koltuğu serbest bırak
                    $stmt = $db->prepare("UPDATE seats SET is_taken = 0 WHERE trip_id = :trip_id AND seat_number = (SELECT seat_number FROM tickets WHERE id = :ticket_id)");
                    $stmt->execute([':trip_id' => $ticket['trip_id'], ':ticket_id' => $ticket_id]);

                    // Bakiyeyi güncelle
                    $stmt = $db->prepare("UPDATE user SET balance = balance + :price WHERE id = :id");
                    $stmt->execute([':price' => $ticket['price_paid'], ':id' => $_SESSION['user_id']]);

                    // Bilet durumunu güncelle
                    $stmt = $db->prepare("UPDATE tickets SET status = 'canceled' WHERE id = :ticket_id");
                    $stmt->execute([':ticket_id' => $ticket_id]);

                    $db->commit();
                    header("Location: account.php?success=iptal_basarili");
                    exit;
                } catch (PDOException $e) {
                    $db->rollBack();
                    error_log("Iptal hatasi: " . $e->getMessage(), 3, __DIR__ . '/error.log');
                }
            } else {
                error_log("Iptal reddedildi: Kalkisa 1 saatten az sure kaldi, ticket_id=$ticket_id", 3, __DIR__ . '/error.log');
            }
        } else {
            error_log("Bilet bulunamadi: ticket_id=$ticket_id, user_id=" . $_SESSION['user_id'], 3, __DIR__ . '/error.log');
        }
    } catch (PDOException $e) {
        error_log("Veritabani hatasi: " . $e->getMessage(), 3, __DIR__ . '/error.log');
    }
}

// Bilet bilgilerini tekrar çek (sayfa yüklemesi için)
$stmt = $db->prepare("SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time FROM tickets t JOIN trips tr ON t.trip_id = tr.id WHERE t.id = :ticket_id AND t.user_id = :user_id");
$stmt->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);
$hours = $ticket && isset($ticket['departure_time']) ? (new DateTime())->diff(new DateTime($ticket['departure_time']))->h + ((new DateTime())->diff(new DateTime($ticket['departure_time']))->days * 24) : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiletYol - Bilet İptal</title>
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
        <h2>Bilet İptal Et</h2>
        <?php if ($ticket): ?>
            <p>
                <?php echo htmlspecialchars($ticket['departure_city']) . ' → ' . htmlspecialchars($ticket['destination_city']); ?> -
                <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($ticket['departure_time']))); ?>
            </p>
            <form method="POST">
                <button type="submit" class="btn btn-danger" <?php echo $hours <= 1 ? 'disabled' : ''; ?>>İptal Et</button>
            </form>
            <?php if ($hours <= 1): ?>
                <div class="alert alert-warning mt-3">Kalkışa 1 saatten az süre kaldı, iptal edilemez!</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-danger mt-3">Bilet bulunamadı veya size ait değil!</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>