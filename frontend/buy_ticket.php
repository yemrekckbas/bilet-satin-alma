<?php
session_start();
include '../backend/auth_check.php';
checkRole('user');
include '../backend/db_connect.php';

$trip_id = $_GET['trip_id'] ?? '';
if (!$trip_id) {
    header("Location: ../index.php");
    exit;
}

$message = ''; // Hata veya başarı mesajı
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seat_number = $_POST['seat_number'] ?? '';
    $coupon_code = $_POST['coupon_code'] ?? '';
    $use_coupon = isset($_POST['use_coupon']) ? true : false;

    if (!$seat_number) {
        $message = 'Lütfen bir koltuk seçin.';
    } else {
        $stmt = $db->prepare("SELECT t.price, t.capacity, t.company_id, (SELECT COUNT(*) FROM seats s WHERE s.trip_id = t.id AND s.is_taken = 1) as taken FROM trips t WHERE t.id = :trip_id");
        $stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
        $stmt->execute();
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($trip) {
            $discount = 0;
            if ($use_coupon && $coupon_code) {
                $stmt = $db->prepare("SELECT discount_rate FROM coupons WHERE code = :code AND expiry_date > CURRENT_TIMESTAMP AND usage_limit > (SELECT COUNT(*) FROM user_coupons WHERE coupon_id = id) AND (company_id IS NULL OR company_id = :company_id)");
                $stmt->bindParam(':code', $coupon_code);
                $stmt->bindParam(':company_id', $trip['company_id'], PDO::PARAM_INT);
                $stmt->execute();
                $discount = $stmt->fetchColumn() ?: 0;
                if ($discount == 0) {
                    $message = 'Geçersiz veya süresi dolmuş kupon.';
                }
            }

            $price = $trip['price'] * (1 - $discount);
            $user_id = $_SESSION['user_id'];
            $stmt = $db->prepare("SELECT balance FROM user WHERE id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $balance = $stmt->fetchColumn();

            if ($balance >= $price && $trip['taken'] < $trip['capacity']) {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("UPDATE seats SET is_taken = 1 WHERE trip_id = :trip_id AND seat_number = :seat_number AND is_taken = 0");
                    $stmt->execute([':trip_id' => $trip_id, ':seat_number' => $seat_number]);
                    if ($stmt->rowCount() > 0) {
                        $stmt = $db->prepare("INSERT INTO tickets (user_id, trip_id, seat_number, price_paid) VALUES (:user_id, :trip_id, :seat_number, :price_paid)");
                        $stmt->execute([':user_id' => $user_id, ':trip_id' => $trip_id, ':seat_number' => $seat_number, ':price_paid' => $price]);
                        $stmt = $db->prepare("UPDATE user SET balance = balance - :price WHERE id = :id");
                        $stmt->execute([':price' => $price, ':id' => $user_id]);
                        if ($use_coupon && $coupon_code && $discount > 0) {
                            $stmt = $db->prepare("INSERT INTO user_coupons (user_id, coupon_id) VALUES (:user_id, (SELECT id FROM coupons WHERE code = :code))");
                            $stmt->execute([':user_id' => $user_id, ':code' => $coupon_code]);
                        }
                        $db->commit();
                        header("Location: account.php?success=1");
                        exit;
                    } else {
                        $db->rollBack();
                        $message = 'Seçilen koltuk zaten alınmış.';
                    }
                } catch (PDOException $e) {
                    $db->rollBack();
                    $message = 'İşlem sırasında bir hata oluştu: ' . $e->getMessage();
                }
            } elseif ($balance < $price) {
                $message = 'Yetersiz bakiye. Lütfen bakiye yükleyin.';
            } elseif ($trip['taken'] >= $trip['capacity']) {
                $message = 'Sefer dolu, bilet alınamaz.';
            }
        } else {
            $message = 'Sefer bulunamadı.';
        }
    }
}

$stmt = $db->prepare("SELECT * FROM trips WHERE id = :trip_id");
$stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$stmt->execute();
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header("Location: ../index.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM seats WHERE trip_id = :trip_id");
$stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$stmt->execute();
$seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($seats)) {
    $message = 'Bu sefer için koltuk bilgisi bulunamadı. Lütfen admin panelinden koltukları kontrol edin.';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiletYol - Bilet Satın Al</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .seat { width: 40px; height: 40px; margin: 5px; }
        .taken { background-color: #dc3545; cursor: not-allowed; }
        .available { background-color: #28a745; cursor: pointer; }
        .coupon-section { display: none; }
        .coupon-section.active { display: block; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg" style="background-color: #870000;">
        <div class="container">
            <a class="navbar-brand text-white" href="../index.php">BiletYol</a>
            <div class="navbar-nav">
                <?php if (isset($_SESSION['role'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a class="nav-link text-white" href="admin_panel.php">Admin Panel</a>
                    <?php elseif ($_SESSION['role'] === 'company_admin'): ?>
                        <a class="nav-link text-white" href="company_admin_panel.php">Firma Panel</a>
                    <?php endif; ?>
                    <a class="nav-link text-white" href="account.php">Hesabım</a>
                <?php endif; ?>
                <a class="nav-link text-white" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2>Bilet Satın Al</h2>
        <p><?php echo htmlspecialchars($trip['departure_city'] ?? '') . ' → ' . htmlspecialchars($trip['destination_city'] ?? '') . ' - ' . htmlspecialchars($trip['departure_time'] ?? ''); ?></p>
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'başarıyla') !== false ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Koltuk Seçimi</label>
                <div class="d-flex flex-wrap">
                    <?php if (!empty($seats)): ?>
                        <?php foreach ($seats as $seat): ?>
                            <button type="button" class="btn seat <?php echo $seat['is_taken'] ? 'taken' : 'available'; ?>" <?php echo $seat['is_taken'] ? 'disabled' : ''; ?> onclick="document.getElementById('seat_number').value=<?php echo $seat['seat_number']; ?>; this.classList.add('btn-primary');">
                                <?php echo $seat['seat_number']; ?>
                            </button>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-danger">Koltuklar yüklenemedi. Lütfen admin panelinden sefer kontrol edin.</p>
                    <?php endif; ?>
                </div>
                <input type="hidden" id="seat_number" name="seat_number" required>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="use_coupon" name="use_coupon" onchange="toggleCoupon(this.checked)">
                    <label class="form-check-label" for="use_coupon">Kupon Kodu Kullan</label>
                </div>
                <div class="coupon-section" id="coupon_section">
                    <label class="form-label">Kupon Kodu</label>
                    <input type="text" class="form-control" name="coupon_code" id="coupon_code" disabled>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Satın Al</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.available').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.available').forEach(b => b.classList.remove('btn-primary'));
                this.classList.add('btn-primary');
            });
        });

        function toggleCoupon(checked) {
            const couponSection = document.getElementById('coupon_section');
            const couponInput = document.getElementById('coupon_code');
            if (checked) {
                couponSection.classList.add('active');
                couponInput.removeAttribute('disabled');
            } else {
                couponSection.classList.remove('active');
                couponInput.setAttribute('disabled', true);
                couponInput.value = ''; // Checkbox kaldırıldığında kupon kodunu sıfırla
            }
        }
    </script>
</body>
</html>