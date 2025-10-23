<?php
// Hata raporlamasını kullanıcıdan gizle (üretim için)
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Oturumu başlat
session_start();

// Oturum kontrolü: Kullanıcı girişi yapılmamışsa yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Veritabanı bağlantısını dahil et
include '../backend/db_connect.php';

// Kullanıcı ID'sini oturumdan al
$user_id = $_SESSION['user_id'];

// Veritabanı işlemlerini try-catch ile güvenli hale getir
try {
    // Kullanıcı bilgilerini çek
    $stmt = $db->prepare("SELECT full_name, email, balance, role FROM user WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kullanıcı bulunamazsa oturumu sonlandır ve yönlendir
    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    // Kullanıcı bilgilerini güvenli bir şekilde atama
    $user_full_name = htmlspecialchars($user['full_name']);
    $user_email = htmlspecialchars($user['email']);
    $user_balance = htmlspecialchars(number_format($user['balance'], 2)) . ' TL';
    $user_role = htmlspecialchars($user['role']);

    // Toplam bilet sayısını çek
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE user_id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $ticket_count = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_tickets = htmlspecialchars($ticket_count['total']);

    // Aktif bilet sayısını çek
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM tickets WHERE user_id = :id AND status = 'active'");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $active_count = $stmt->fetch(PDO::FETCH_ASSOC);
    $active_tickets = htmlspecialchars($active_count['active']);

    // Dinamik bilet listesini çek
    $stmt = $db->prepare("
        SELECT t.id, t.seat_number, t.price_paid, t.status, t.created_at,
               tr.departure_city, tr.destination_city, tr.departure_time,
               c.name AS company_name
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN companies c ON tr.company_id = c.id
        WHERE t.user_id = :user_id
        ORDER BY t.created_at DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kayıt tarihini al ve formatla
    $stmt = $db->prepare("SELECT created_at FROM user WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $registration_date = $stmt->fetchColumn();
    $registration_date = date('d F Y', strtotime($registration_date)); // Türkçe tarih formatı

} catch (PDOException $e) {
    // Hata durumunda login sayfasına yönlendir
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiletYol - Hesabım</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* Kart stil ayarları */
        .ticket-card,
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Kart başlığı stil ayarları */
        .ticket-card .card-header,
        .profile-card .card-header {
            background-color: #870000;
            color: white;
        }

        /* Çıkış butonu stil ayarları */
        .btn-logout {
            background-color: #dc3545;
            color: white;
        }

        .btn-logout:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
                <?php endif; ?>
                <a class="nav-link text-white" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <!-- Ana İçerik -->
    <div class="container mt-5">
        <div class="row">
            <!-- Sol Kolon - Biletler -->
            <div class="col-md-8">
                <div class="card ticket-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Biletlerim</h5>
                    </div>
                    <div class="card-body">
                        <p>
                            Toplam Bilet: <span class="badge bg-secondary"><?php echo $total_tickets; ?></span> |
                            Aktif Bilet: <span class="badge bg-success"><?php echo $active_tickets; ?></span>
                        </p>
                        <?php if (!empty($tickets)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Bilet ID</th>
                                            <th>Sefer</th>
                                            <th>Koltuk</th>
                                            <th>Fiyat</th>
                                            <th>Durum</th>
                                            <th>Tarih</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['departure_city']) . ' → ' . htmlspecialchars($ticket['destination_city']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['seat_number']); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($ticket['price_paid'], 2)); ?> TL</td>
                                                <td>
                                                    <span class="badge <?php echo $ticket['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($ticket['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($ticket['created_at']))); ?></td>
                                                <td>
                                                    <?php if ($ticket['status'] === 'active'): ?>
                                                        <a href="../backend/generate_pdf.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                        <a href="cancel_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> İptal
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Henüz biletiniz bulunmuyor. <a href="../index.php">Sefer arayarak</a> bilet satın alabilirsiniz.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon - Hızlı İşlemler -->
            <div class="col-md-4">
                <div class="card profile-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Profil Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Ad Soyad:</strong><br>
                            <span><?php echo $user_full_name; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>E-posta:</strong><br>
                            <span><?php echo $user_email; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Rol:</strong><br>
                            <span class="badge bg-primary"><?php echo ucfirst($user_role); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Bakiye:</strong><br>
                            <span class="text-warning"><?php echo $user_balance; ?></span>
                            <a href="add_balance.php" class="btn btn-sm btn-warning ms-2">Yükle</a>
                        </div>
                        <div class="mb-3">
                            <strong>Kayıt Tarihi:</strong><br>
                            <span><?php echo $registration_date; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt"></i> Hızlı İşlemler</h5>
                    </div>
                    <div class="card-body">
                        <a href="../index.php" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-search"></i> Sefer Ara
                        </a>
                        <a href="account.php" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-ticket-alt"></i> Biletlerimi Gör
                        </a>
                        <a href="edit_profile.php" class="btn btn-info w-100 mb-2">
                            <i class="fas fa-user-edit"></i> Profili Düzenle
                        </a>
                        <a href="logout.php" class="btn btn-logout w-100">
                            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>