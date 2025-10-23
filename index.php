<?php
session_start();
include __DIR__ . '/backend/db_connect.php';

// Arama verilerini al
$departure_city = isset($_GET['departure_city']) ? $_GET['departure_city'] : '';
$destination_city = isset($_GET['destination_city']) ? $_GET['destination_city'] : '';
$departure_time = isset($_GET['departure_time']) ? $_GET['departure_time'] : '';

$trips = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $departure_city && $destination_city && $departure_time) {
    $stmt = $db->prepare("SELECT t.*, c.name AS company_name, 
                          (SELECT COUNT(*) FROM seats s WHERE s.trip_id = t.id AND s.is_taken = 1) AS taken_seats 
                          FROM trips t 
                          JOIN companies c ON t.company_id = c.id 
                          WHERE t.departure_city = :departure_city 
                          AND t.destination_city = :destination_city 
                          AND DATE(t.departure_time) = :departure_time 
                          AND t.departure_time > CURRENT_TIMESTAMP"); 
    $stmt->bindParam(':departure_city', $departure_city);
    $stmt->bindParam(':destination_city', $destination_city);
    $stmt->bindParam(':departure_time', $departure_time);
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Satın Al - Ana Sayfa</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f8f9fa; }
        .navbar { background-color: #870000; }
        .navbar-brand { color: white; font-weight: bold; }
        .navbar-nav .nav-link { color: white !important; }
        .search-section { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        .result-item { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        footer { background-color: #870000; color: white; padding: 20px 0; margin-top: 20px; }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">BiletYol</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="frontend/account.php">Hesabım</a></li>
                        <li class="nav-item"><a class="nav-link" href="frontend/logout.php">Çıkış Yap</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="frontend/login.php">Giriş Yap</a></li>
                        <li class="nav-item"><a class="nav-link" href="frontend/register.php">Kayıt Ol</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    
    <div class="container mt-5">
        <h1 class="text-center mb-4">Sefer Arama</h1>
        <div class="search-section">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="departure_city" class="form-label">Kalkış Şehri</label>
                        <input type="text" class="form-control" id="departure_city" name="departure_city" placeholder="Örn: Ankara" value="<?php echo htmlspecialchars($departure_city); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="destination_city" class="form-label">Varış Şehri</label>
                        <input type="text" class="form-control" id="destination_city" name="destination_city" placeholder="Örn: İstanbul" value="<?php echo htmlspecialchars($destination_city); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="departure_time" class="form-label">Kalkış Tarihi</label>
                        <input type="date" class="form-control" id="departure_time" name="departure_time" value="<?php echo htmlspecialchars($departure_time); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Ara</button>
                </div>
            </form>

            <div class="mt-4">
                <?php if (!empty($trips) && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
                    <h3>Sonuçlar</h3>
                    <div class="list-group">
                        <?php foreach ($trips as $trip): ?>
                            <div class="list-group-item result-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($trip['departure_city']); ?> → <?php echo htmlspecialchars($trip['destination_city']); ?></h5>
                                    <small><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($trip['departure_time']))); ?></small>
                                </div>
                                <p class="mb-1">Firma: <?php echo htmlspecialchars($trip['company_name']); ?></p>
                                <p>Fiyat: <?php echo htmlspecialchars($trip['price']); ?> TL | Dolu Koltuk: <?php echo htmlspecialchars($trip['taken_seats']); ?>/<?php echo htmlspecialchars($trip['capacity']); ?></p>

                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="frontend/buy_ticket.php?trip_id=<?php echo $trip['id']; ?>" class="btn btn-success btn-sm">Bilet Al</a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" onclick="alert('Lütfen giriş yapın.'); window.location.href='frontend/login.php';">Bilet Al</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($trips) && $departure_city && $destination_city && $departure_time): ?>
                    <div class="alert alert-warning">Hiç sefer bulunamadı.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
    <footer class="text-center">
        <p>&copy; 2025 BiletYol. Tüm hakları saklıdır.</p>
        <p><a href="#" class="text-white">İletişim</a> | <a href="#" class="text-white">Hakkımızda</a> | <a href="#" class="text-white">Yardım</a></p>
    </footer>

   
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>