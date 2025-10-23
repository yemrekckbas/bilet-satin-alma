<?php
// Hata raporlamasını kullanıcıdan gizle (üretim için)
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Oturum ve rol kontrolü
session_start();
include '../backend/auth_check.php';
checkRole('company_admin');
include '../backend/db_connect.php';

// Kullanıcı ID'sini al ve şirket ID'sini belirle
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT company_id FROM user WHERE id = :id");
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$company_id = $stmt->fetchColumn();

$message = ''; // Hata veya başarı mesajı için

// POST isteği ile işlemleri gerçekleştir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_trip') {
            if (empty($_POST['departure_city']) || empty($_POST['destination_city']) || empty($_POST['departure_time']) || empty($_POST['arrival_time']) || empty($_POST['price']) || empty($_POST['seat_count'])) {
                throw new Exception("Tüm alanlar doldurulmalı!");
            }
            if (!is_numeric($_POST['price']) || $_POST['price'] < 0) {
                throw new Exception("Geçersiz fiyat değeri!");
            }
            if (!is_numeric($_POST['seat_count']) || $_POST['seat_count'] <= 0) {
                throw new Exception("Koltuk sayısı 1 veya daha fazla olmalı!");
            }
            $stmt = $db->prepare("INSERT INTO trips (company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (:company_id, :departure_city, :destination_city, :departure_time, :arrival_time, :price, :capacity)");
            $stmt->execute([
                ':company_id' => $company_id,
                ':departure_city' => $_POST['departure_city'],
                ':destination_city' => $_POST['destination_city'],
                ':departure_time' => $_POST['departure_time'],
                ':arrival_time' => $_POST['arrival_time'],
                ':price' => $_POST['price'],
                ':capacity' => $_POST['seat_count']
            ]);
            $trip_id = $db->lastInsertId();
            $seat_count = (int)$_POST['seat_count'];
            for ($i = 1; $i <= $seat_count; $i++) {
                $stmt = $db->prepare("INSERT INTO seats (trip_id, seat_number, is_taken) VALUES (:trip_id, :seat_number, 0)");
                $stmt->execute([':trip_id' => $trip_id, ':seat_number' => $i]);
            }
            $message = "Sefer başarıyla eklendi ve $seat_count koltuk oluşturuldu.";
        } elseif ($action === 'update_trip') {
            if (empty($_POST['departure_city']) || empty($_POST['destination_city']) || empty($_POST['departure_time']) || empty($_POST['arrival_time']) || empty($_POST['price']) || empty($_POST['seat_count']) || empty($_POST['trip_id'])) {
                throw new Exception("Tüm alanlar doldurulmalı!");
            }
            if (!is_numeric($_POST['price']) || $_POST['price'] < 0) {
                throw new Exception("Geçersiz fiyat değeri!");
            }
            if (!is_numeric($_POST['seat_count']) || $_POST['seat_count'] <= 0) {
                throw new Exception("Koltuk sayısı 1 veya daha fazla olmalı!");
            }
            $stmt = $db->prepare("UPDATE trips SET departure_city = :departure_city, destination_city = :destination_city, departure_time = :departure_time, arrival_time = :arrival_time, price = :price, capacity = :capacity WHERE id = :id AND company_id = :company_id");
            $stmt->execute([
                ':departure_city' => $_POST['departure_city'],
                ':destination_city' => $_POST['destination_city'],
                ':departure_time' => $_POST['departure_time'],
                ':arrival_time' => $_POST['arrival_time'],
                ':price' => $_POST['price'],
                ':capacity' => $_POST['seat_count'],
                ':id' => $_POST['trip_id'],
                ':company_id' => $company_id
            ]);
            $stmt = $db->prepare("DELETE FROM seats WHERE trip_id = :trip_id");
            $stmt->execute([':trip_id' => $_POST['trip_id']]);
            $seat_count = (int)$_POST['seat_count'];
            for ($i = 1; $i <= $seat_count; $i++) {
                $stmt = $db->prepare("INSERT INTO seats (trip_id, seat_number, is_taken) VALUES (:trip_id, :seat_number, 0)");
                $stmt->execute([':trip_id' => $_POST['trip_id'], ':seat_number' => $i]);
            }
            $message = "Sefer başarıyla güncellendi ve $seat_count koltuk oluşturuldu.";
        } elseif ($action === 'delete_trip') {
            if (empty($_POST['trip_id'])) {
                throw new Exception("Sefer ID'si eksik!");
            }
            $stmt = $db->prepare("DELETE FROM trips WHERE id = :id AND company_id = :company_id");
            $stmt->execute([':id' => $_POST['trip_id'], ':company_id' => $company_id]);
            $message = "Sefer başarıyla silindi.";
        } elseif ($action === 'add_company_coupon') {
            if (empty($_POST['coupon_code']) || empty($_POST['discount_rate']) || empty($_POST['usage_limit']) || empty($_POST['expiry_date'])) {
                throw new Exception("Tüm alanlar doldurulmalı!");
            }
            if (!is_numeric($_POST['discount_rate']) || $_POST['discount_rate'] < 0 || $_POST['discount_rate'] > 100) {
                throw new Exception("İndirim oranı 0-100 arasında olmalı!");
            }
            if (!is_numeric($_POST['usage_limit']) || $_POST['usage_limit'] <= 0) {
                throw new Exception("Kullanım limiti 1 veya daha fazla olmalı!");
            }
            $stmt = $db->prepare("INSERT INTO coupons (code, discount_rate, usage_limit, expiry_date, company_id, is_global) VALUES (:code, :discount_rate, :usage_limit, :expiry_date, :company_id, 0)");
            $stmt->execute([
                ':code' => $_POST['coupon_code'],
                ':discount_rate' => $_POST['discount_rate'] / 100,
                ':usage_limit' => $_POST['usage_limit'],
                ':expiry_date' => $_POST['expiry_date'],
                ':company_id' => $company_id
            ]);
            $message = "Firma kuponu başarıyla eklendi.";
        } elseif ($action === 'update_company_coupon') {
            if (empty($_POST['coupon_code']) || empty($_POST['discount_rate']) || empty($_POST['usage_limit']) || empty($_POST['expiry_date']) || empty($_POST['coupon_id'])) {
                throw new Exception("Tüm alanlar doldurulmalı!");
            }
            if (!is_numeric($_POST['discount_rate']) || $_POST['discount_rate'] < 0 || $_POST['discount_rate'] > 100) {
                throw new Exception("İndirim oranı 0-100 arasında olmalı!");
            }
            if (!is_numeric($_POST['usage_limit']) || $_POST['usage_limit'] <= 0) {
                throw new Exception("Kullanım limiti 1 veya daha fazla olmalı!");
            }
            $stmt = $db->prepare("UPDATE coupons SET code = :code, discount_rate = :discount_rate, usage_limit = :usage_limit, expiry_date = :expiry_date WHERE id = :id AND company_id = :company_id AND is_global = 0");
            $stmt->execute([
                ':code' => $_POST['coupon_code'],
                ':discount_rate' => $_POST['discount_rate'] / 100,
                ':usage_limit' => $_POST['usage_limit'],
                ':expiry_date' => $_POST['expiry_date'],
                ':id' => $_POST['coupon_id'],
                ':company_id' => $company_id
            ]);
            $message = "Firma kuponu başarıyla güncellendi.";
        } elseif ($action === 'delete_company_coupon') {
            if (empty($_POST['coupon_id'])) {
                throw new Exception("Kupon ID'si eksik!");
            }
            $stmt = $db->prepare("DELETE FROM coupons WHERE id = :id AND company_id = :company_id AND is_global = 0");
            $stmt->execute([':id' => $_POST['coupon_id'], ':company_id' => $company_id]);
            $message = "Firma kuponu başarıyla silindi.";
        } elseif ($action === 'cancel_ticket') {
            $ticket_id = $_POST['ticket_id'] ?? '';
            if (!$ticket_id) {
                throw new Exception("Bilet ID'si eksik!");
            }
            $stmt = $db->prepare("SELECT t.trip_id, t.price_paid, tr.departure_time FROM tickets t JOIN trips tr ON t.trip_id = tr.id WHERE t.id = :ticket_id AND tr.company_id = :company_id");
            $stmt->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ticket) {
                $departure_time = new DateTime($ticket['departure_time']);
                $now = new DateTime();
                $hours = $now->diff($departure_time)->h + ($now->diff($departure_time)->days * 24);
                if ($hours > 1) {
                    $db->beginTransaction();
                    try {
                        $stmt = $db->prepare("UPDATE seats SET is_taken = 0 WHERE trip_id = :trip_id AND seat_number = (SELECT seat_number FROM tickets WHERE id = :ticket_id)");
                        $stmt->execute([':trip_id' => $ticket['trip_id'], ':ticket_id' => $ticket_id]);
                        $stmt = $db->prepare("UPDATE user SET balance = balance + :price WHERE id = (SELECT user_id FROM tickets WHERE id = :ticket_id)");
                        $stmt->execute([':price' => $ticket['price_paid'], ':ticket_id' => $ticket_id]);
                        $stmt = $db->prepare("UPDATE tickets SET status = 'canceled' WHERE id = :ticket_id");
                        $stmt->execute([':ticket_id' => $ticket_id]);
                        $db->commit();
                        $message = "Bilet başarıyla iptal edildi.";
                    } catch (PDOException $e) {
                        $db->rollBack();
                        throw new Exception("İptal işlemi başarısız: " . $e->getMessage());
                    }
                } else {
                    throw new Exception("Kalkışa 1 saatten az süre kaldı, iptal edilemez!");
                }
            } else {
                throw new Exception("Bilet bulunamadı veya size ait değil!");
            }
        }
    } catch (Exception $e) {
        $message = "Hata: " . $e->getMessage();
    }
}

// Seferleri çek (kalkış zamanı geçmiş olanları hariç tut)
$stmt = $db->prepare("SELECT t.*, COUNT(s.id) as available_seats FROM trips t LEFT JOIN seats s ON t.id = s.trip_id AND s.is_taken = 0 WHERE t.company_id = :company_id AND t.departure_time > DATETIME('now') GROUP BY t.id");
$stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
$stmt->execute();
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Firma kuponlarını çek
$stmt = $db->prepare("SELECT * FROM coupons WHERE company_id = :company_id AND is_global = 0");
$stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
$stmt->execute();
$company_coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bilet alan kullanıcıların ve bilet bilgilerinin çekilmesi (geçmiş tarihli biletler hariç)
$ticket_users = [];
if (!empty($trips)) {
    $trip_ids = array_column($trips, 'id');
    $in_clause = implode(',', array_fill(0, count($trip_ids), '?'));
    $stmt = $db->prepare("SELECT u.id as user_id, u.full_name, u.email, u.balance, t.id as ticket_id, t.trip_id, t.seat_number, t.price_paid, t.status, t.created_at, tr.departure_time, tr.departure_city, tr.destination_city FROM user u JOIN tickets t ON u.id = t.user_id JOIN trips tr ON t.trip_id = tr.id WHERE t.trip_id IN ($in_clause) AND t.status = 'active' AND tr.company_id = :company_id AND tr.departure_time > DATETIME('now')");
    $stmt->execute(array_merge($trip_ids, [$company_id]));
    $ticket_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiletYol - Firma Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .user-info { margin-top: 10px; font-size: 0.9em; }
        .ticket-actions { margin-top: 5px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg" style="background-color: #870000;">
        <div class="container">
            <a class="navbar-brand text-white" href="../index.php">BiletYol</a>
            <div class="navbar-nav">
                <a class="nav-link text-white" href="../index.php">Anasayfa</a>
                <a class="nav-link text-white" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2>Firma Admin Paneli</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo strpos($message, 'Hata') === false ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Yeni Sefer Ekle -->
        <div class="card">
            <div class="card-header">Yeni Sefer Ekle</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_trip">
                    <div class="mb-3">
                        <label class="form-label">Kalkış Şehri</label>
                        <input type="text" class="form-control" name="departure_city" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Varış Şehri</label>
                        <input type="text" class="form-control" name="destination_city" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kalkış Zamanı</label>
                        <input type="datetime-local" class="form-control" name="departure_time" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Varış Zamanı</label>
                        <input type="datetime-local" class="form-control" name="arrival_time" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fiyat (TL)</label>
                        <input type="number" class="form-control" name="price" min="1" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Koltuk Sayısı </label>
                        <input type="number" class="form-control" name="seat_count" min="1" value="40" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </form>
            </div>
        </div>

        <!-- Sefer Listesi ve İşlemler -->
        <div class="card">
            <div class="card-header">Sefer Listesi</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Kalkış</th>
                            <th>Varış</th>
                            <th>Kalkış Zamanı</th>
                            <th>Varış Zamanı</th>
                            <th>Fiyat</th>
                            <th>Koltuk</th>
                            <th>Kalan Koltuk</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trip['departure_city']); ?></td>
                                <td><?php echo htmlspecialchars($trip['destination_city']); ?></td>
                                <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($trip['departure_time']))); ?></td>
                                <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($trip['arrival_time']))); ?></td>
                                <td><?php echo htmlspecialchars($trip['price']); ?> TL</td>
                                <td><?php echo htmlspecialchars($trip['capacity']); ?></td>
                                <td><?php echo htmlspecialchars($trip['available_seats']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" class="me-2">
                                        <input type="hidden" name="action" value="update_trip">
                                        <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                                        <input type="text" name="departure_city" value="<?php echo htmlspecialchars($trip['departure_city']); ?>" class="form-control d-inline-block" style="width: 100px;">
                                        <input type="text" name="destination_city" value="<?php echo htmlspecialchars($trip['destination_city']); ?>" class="form-control d-inline-block" style="width: 100px;">
                                        <input type="datetime-local" name="departure_time" value="<?php echo htmlspecialchars($trip['departure_time']); ?>" class="form-control d-inline-block" style="width: 150px;">
                                        <input type="datetime-local" name="arrival_time" value="<?php echo htmlspecialchars($trip['arrival_time']); ?>" class="form-control d-inline-block" style="width: 150px;">
                                        <input type="number" name="price" value="<?php echo htmlspecialchars($trip['price']); ?>" class="form-control d-inline-block" style="width: 80px;">
                                        <input type="number" name="seat_count" value="<?php echo htmlspecialchars($trip['capacity']); ?>" class="form-control d-inline-block" style="width: 80px;">
                                        <button type="submit" class="btn btn-warning btn-sm">Güncelle</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_trip">
                                        <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinizden emin misiniz?');">Sil</button>
                                    </form>
                                    <!-- Bilet Alan Kullanıcılar -->
                                    <?php
                                    $users_for_trip = array_filter($ticket_users, fn($u) => $u['trip_id'] == $trip['id']);
                                    if (!empty($users_for_trip)): ?>
                                        <div class="user-info">
                                            <strong>Bilet Alanlar:</strong><br>
                                            <?php foreach ($users_for_trip as $user): ?>
                                                <?php echo htmlspecialchars($user['full_name']) . ' (' . htmlspecialchars($user['email']) . ')<br>'; ?>
                                                <div class="ticket-actions">
                                                    <strong>Profil:</strong> ID: <?php echo $user['user_id']; ?>, Bakiye: <?php echo number_format($user['balance'], 2); ?> TL<br>
                                                    <strong>Bilet:</strong> Koltuk: <?php echo $user['seat_number']; ?>, Fiyat: <?php echo number_format($user['price_paid'], 2); ?> TL, Tarih: <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>, Durum: <?php echo $user['status']; ?><br>
                                                    <form method="POST" style="display:inline;" class="me-2">
                                                        <input type="hidden" name="action" value="cancel_ticket">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $user['ticket_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" <?php echo (new DateTime())->diff(new DateTime($user['departure_time']))->h + ((new DateTime())->diff(new DateTime($user['departure_time']))->days * 24) <= 1 ? 'disabled' : ''; ?>>İptal Et</button>
                                                    </form>
                                                    <a href="backend/generate_pdf.php?ticket_id=<?php echo $user['ticket_id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </a>
                                                    <a href="user_history.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-secondary btn-sm">Geçmiş Biletler</a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Firma İndirim Kuponları -->
        <div class="card">
            <div class="card-header">Firma İndirim Kuponları</div>
            <div class="card-body">
                <form method="POST" class="mb-3">
                    <input type="hidden" name="action" value="add_company_coupon">
                    <div class="mb-3">
                        <label class="form-label">Kupon Kodu</label>
                        <input type="text" class="form-control" name="coupon_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İndirim Oranı (%)</label>
                        <input type="number" class="form-control" name="discount_rate" min="1" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kullanım Limiti</label>
                        <input type="number" class="form-control" name="usage_limit" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Son Kullanma Tarihi</label>
                        <input type="date" class="form-control" name="expiry_date" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </form>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Kod</th>
                            <th>Oran (%)</th>
                            <th>Limit</th>
                            <th>Son Tarih</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($company_coupons as $coupon): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($coupon['code']); ?></td>
                                <td><?php echo htmlspecialchars($coupon['discount_rate'] * 100); ?></td>
                                <td><?php echo htmlspecialchars($coupon['usage_limit']); ?></td>
                                <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" class="me-2">
                                        <input type="hidden" name="action" value="update_company_coupon">
                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                        <input type="text" name="coupon_code" value="<?php echo htmlspecialchars($coupon['code']); ?>" class="form-control d-inline-block" style="width: 100px;">
                                        <input type="number" name="discount_rate" value="<?php echo htmlspecialchars($coupon['discount_rate'] * 100); ?>" class="form-control d-inline-block" style="width: 80px;">
                                        <input type="number" name="usage_limit" value="<?php echo htmlspecialchars($coupon['usage_limit']); ?>" class="form-control d-inline-block" style="width: 80px;">
                                        <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($coupon['expiry_date']); ?>" class="form-control d-inline-block" style="width: 120px;">
                                        <button type="submit" class="btn btn-warning btn-sm">Güncelle</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_company_coupon">
                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinizden emin misiniz?');">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>