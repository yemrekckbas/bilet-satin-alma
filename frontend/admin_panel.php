<?php
// admin_panel.php
// Güvenlik notu: production ortamında display_errors kapalı olmalı (php.ini veya ini_set).
// session ve auth kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../backend/auth_check.php';
checkRole('admin'); // auth_check.php içinde session kontrolü yapıldığını varsayıyorum
include '../backend/db_connect.php';

$message = '';
$message_type = 'success'; // success | danger

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper: güvenli çıktı
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF doğrulaması
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $message = "Geçersiz CSRF token. Lütfen sayfayı yenileyip tekrar deneyin.";
        $message_type = 'danger';
    } elseif (isset($_POST['action'])) {
        try {
            $action = $_POST['action'];

            if ($action === 'add_company') {
                $company_name = trim($_POST['company_name'] ?? '');
                if ($company_name !== '') {
                    $stmt = $db->prepare("INSERT INTO companies (name) VALUES (:name)");
                    $stmt->bindParam(':name', $company_name);
                    $stmt->execute();
                    $message = "Şirket başarıyla eklendi.";
                    $message_type = 'success';
                } else {
                    $message = "Firma adı boş olamaz.";
                    $message_type = 'danger';
                }

            } elseif ($action === 'update_company') {
                $company_id = intval($_POST['company_id'] ?? 0);
                $company_name = trim($_POST['company_name'] ?? '');
                if ($company_id > 0 && $company_name !== '') {
                    $stmt = $db->prepare("UPDATE companies SET name = :name WHERE id = :id");
                    $stmt->bindParam(':name', $company_name);
                    $stmt->bindParam(':id', $company_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $message = "Şirket başarıyla güncellendi.";
                    $message_type = 'success';
                } else {
                    $message = "Geçersiz şirket bilgisi.";
                    $message_type = 'danger';
                }

            } elseif ($action === 'delete_company') {
                $company_id = intval($_POST['company_id'] ?? 0);
                if ($company_id > 0) {
                    $stmt = $db->prepare("DELETE FROM companies WHERE id = :id");
                    $stmt->bindParam(':id', $company_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $message = "Şirket başarıyla silindi.";
                    $message_type = 'success';
                } else {
                    $message = "Geçersiz şirket seçimi.";
                    $message_type = 'danger';
                }

            } elseif ($action === 'add_company_admin') {
                $full_name = trim($_POST['full_name'] ?? '');
                $email_raw = $_POST['email'] ?? '';
                $email = filter_var($email_raw, FILTER_SANITIZE_EMAIL);
                $password_raw = trim($_POST['password'] ?? '');
                $company_id = intval($_POST['company_id'] ?? 0) ?: null;

                if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password_raw === '') {
                    $message = "Lütfen geçerli ad, e-posta ve parola girin.";
                    $message_type = 'danger';
                } else {
                    // email benzersizliği kontrolü
                    $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE email = :email");
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    if ($stmt->fetchColumn() == 0) {
                        $hashed = password_hash($password_raw, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO user (full_name, email, password, role, company_id) VALUES (:full_name, :email, :password, 'company_admin', :company_id)");
                        $stmt->bindParam(':full_name', $full_name);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':password', $hashed);
                        if ($company_id === null) {
                            $stmt->bindValue(':company_id', null, PDO::PARAM_NULL);
                        } else {
                            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
                        }
                        $stmt->execute();
                        $message = "Firma admini başarıyla eklendi.";
                        $message_type = 'success';
                    } else {
                        $message = "Bu e-posta zaten kullanılıyor.";
                        $message_type = 'danger';
                    }
                }

            } elseif ($action === 'update_company_admin') {
                $admin_id = intval($_POST['admin_id'] ?? 0);
                $full_name = trim($_POST['full_name'] ?? '');
                $email_raw = $_POST['email'] ?? '';
                $email = filter_var($email_raw, FILTER_SANITIZE_EMAIL);
                $company_id = intval($_POST['company_id'] ?? 0) ?: null;
                $password_raw = trim($_POST['password'] ?? '');

                if ($admin_id <= 0 || $full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = "Eksik veya hatalı admin bilgisi.";
                    $message_type = 'danger';
                } else {
                    $update_query = "UPDATE user SET full_name = :full_name, email = :email, company_id = :company_id";
                    $params = [':full_name' => $full_name, ':email' => $email, ':company_id' => $company_id, ':id' => $admin_id];

                    if ($password_raw !== '') {
                        $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);
                        $update_query .= ", password = :password";
                        $params[':password'] = $hashed_password;
                    }
                    $update_query .= " WHERE id = :id AND role = 'company_admin'";

                    $stmt = $db->prepare($update_query);
                    $stmt->execute($params);
                    $message = "Firma admini başarıyla güncellendi.";
                    $message_type = 'success';
                }

            } elseif ($action === 'delete_company_admin') {
                $admin_id = intval($_POST['admin_id'] ?? 0);
                if ($admin_id > 0) {
                    $stmt = $db->prepare("DELETE FROM user WHERE id = :id AND role = 'company_admin'");
                    $stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $message = "Firma admini başarıyla silindi.";
                    $message_type = 'success';
                } else {
                    $message = "Geçersiz admin seçimi.";
                    $message_type = 'danger';
                }

            } elseif ($action === 'add_global_coupon') {
                $code = trim($_POST['coupon_code'] ?? '');
                $discount_rate_input = floatval($_POST['discount_rate'] ?? 0);
                $discount_rate = min(1, max(0, $discount_rate_input / 100));
                $usage_limit = max(1, intval($_POST['usage_limit'] ?? 1));
                $expiry_date_raw = $_POST['expiry_date'] ?? '';
                $expiry_date = $expiry_date_raw ?: date('Y-m-d', strtotime('+1 month'));

                if ($code === '' || $discount_rate < 0 || $usage_limit <= 0) {
                    $message = "Geçersiz kupon bilgisi.";
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare("INSERT INTO coupons (code, discount_rate, usage_limit, expiry_date, company_id) VALUES (:code, :discount_rate, :usage_limit, :expiry_date, NULL)");
                    $stmt->bindParam(':code', $code);
                    $stmt->bindParam(':discount_rate', $discount_rate);
                    $stmt->bindParam(':usage_limit', $usage_limit, PDO::PARAM_INT);
                    $stmt->bindParam(':expiry_date', $expiry_date);
                    $stmt->execute();
                    $message = "Genel kupon başarıyla eklendi.";
                    $message_type = 'success';
                }

            } elseif ($action === 'update_global_coupon') {
                $coupon_id = intval($_POST['coupon_id'] ?? 0);
                $code = trim($_POST['coupon_code'] ?? '');
                $discount_rate_input = floatval($_POST['discount_rate'] ?? 0);
                $discount_rate = min(1, max(0, $discount_rate_input / 100));
                $usage_limit = max(1, intval($_POST['usage_limit'] ?? 1));
                $expiry_date_raw = $_POST['expiry_date'] ?? '';
                $expiry_date = $expiry_date_raw ?: date('Y-m-d', strtotime('+1 month'));

                if ($coupon_id > 0 && $code !== '' && $discount_rate >= 0 && $usage_limit > 0) {
                    $stmt = $db->prepare("UPDATE coupons SET code = :code, discount_rate = :discount_rate, usage_limit = :usage_limit, expiry_date = :expiry_date WHERE id = :id AND company_id IS NULL");
                    $stmt->bindParam(':code', $code);
                    $stmt->bindParam(':discount_rate', $discount_rate);
                    $stmt->bindParam(':usage_limit', $usage_limit, PDO::PARAM_INT);
                    $stmt->bindParam(':expiry_date', $expiry_date);
                    $stmt->bindParam(':id', $coupon_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $message = "Genel kupon başarıyla güncellendi.";
                    $message_type = 'success';
                } else {
                    $message = "Geçersiz kupon bilgisi.";
                    $message_type = 'danger';
                }

            } elseif ($action === 'delete_global_coupon') {
                $coupon_id = intval($_POST['coupon_id'] ?? 0);
                if ($coupon_id > 0) {
                    $stmt = $db->prepare("DELETE FROM coupons WHERE id = :id AND company_id IS NULL");
                    $stmt->bindParam(':id', $coupon_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $message = "Genel kupon başarıyla silindi.";
                    $message_type = 'success';
                } else {
                    $message = "Geçersiz kupon seçimi.";
                    $message_type = 'danger';
                }

            } else {
                $message = "Bilinmeyen işlem.";
                $message_type = 'danger';
            }

        } catch (Exception $e) {
            // Hata mesajını kullanıcıya ham olarak göstermeyin; loglayın
            error_log("Admin panel hatası: " . $e->getMessage());
            $message = "İşlem sırasında bir hata oluştu. Sistem yöneticisine bildirin.";
            $message_type = 'danger';
        }
    }
}

// Veri çekme (her zaman sayfa sonunda)
try {
    $stmt = $db->prepare("SELECT * FROM companies ORDER BY name ASC");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT u.id, u.full_name, u.email, u.company_id, c.name AS company_name FROM user u LEFT JOIN companies c ON u.company_id = c.id WHERE u.role = 'company_admin' ORDER BY u.full_name ASC");
    $stmt->execute();
    $company_admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM coupons WHERE company_id IS NULL ORDER BY id DESC");
    $stmt->execute();
    $global_coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Veri çekme hatası: " . $e->getMessage());
    $companies = $company_admins = $global_coupons = [];
    $message = "Veri çekilirken hata oluştu.";
    $message_type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>BiletYol - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <?php if ($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
    <?php endif; ?>

    <!-- Firma Yönetimi -->
    <div class="card mb-4">
        <div class="card-header">Firma Yönetimi</div>
        <div class="card-body">
            <form method="POST" class="mb-3" autocomplete="off">
                <input type="hidden" name="action" value="add_company">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label class="form-label">Firma Adı</label>
                    <input type="text" class="form-control" name="company_name" required>
                </div>
                <button type="submit" class="btn btn-primary">Ekle</button>
            </form>

            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Ad</th>
                    <th>İşlem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td><?php echo e($company['name'] ?? ''); ?></td>
                        <td>
                            <form method="POST" style="display:inline;" class="me-2" autocomplete="off">
                                <input type="hidden" name="action" value="update_company">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">
                                <input type="text" name="company_name" value="<?php echo e($company['name'] ?? ''); ?>" class="form-control d-inline-block" style="width: 200px;">
                                <button type="submit" class="btn btn-warning btn-sm">Güncelle</button>
                            </form>
                            <form method="POST" style="display:inline;" autocomplete="off">
                                <input type="hidden" name="action" value="delete_company">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinizden emin misiniz?');">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($companies)): ?>
                    <tr><td colspan="2">Kayıtlı firma yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Firma Admin Yönetimi -->
    <div class="card mb-4">
        <div class="card-header">Firma Admin Yönetimi</div>
        <div class="card-body">
            <form method="POST" class="mb-3" autocomplete="off">
                <input type="hidden" name="action" value="add_company_admin">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label class="form-label">Ad Soyad</label>
                    <input type="text" class="form-control" name="full_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-posta</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Parola</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şirket</label>
                    <select class="form-control" name="company_id">
                        <option value="">Seçiniz</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo e($company['id']); ?>"><?php echo e($company['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Ekle</button>
            </form>

            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Şirket</th>
                    <th>İşlem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($company_admins as $admin): ?>
                    <tr>
                        <td><?php echo e($admin['full_name'] ?? ''); ?></td>
                        <td><?php echo e($admin['email'] ?? ''); ?></td>
                        <td><?php echo e($admin['company_name'] ?? 'Yok'); ?></td>
                        <td>
                            <form method="POST" style="display:inline;" class="me-2" autocomplete="off">
                                <input type="hidden" name="action" value="update_company_admin">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="admin_id" value="<?php echo e($admin['id'] ?? ''); ?>">

                                <input type="text" name="full_name" value="<?php echo e($admin['full_name'] ?? ''); ?>" class="form-control d-inline-block" style="width: 150px;">
                                <input type="email" name="email" value="<?php echo e($admin['email'] ?? ''); ?>" class="form-control d-inline-block" style="width: 150px;">
                                <select name="company_id" class="form-control d-inline-block" style="width: 150px;">
                                    <option value="">Yok</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo e($company['id']); ?>" <?php echo ((isset($admin['company_id']) ? $admin['company_id'] : '') == $company['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($company['name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="password" name="password" placeholder="Yeni Parola (Boş bırak)" class="form-control d-inline-block" style="width: 120px;">
                                <button type="submit" class="btn btn-warning btn-sm">Güncelle</button>
                            </form>

                            <form method="POST" style="display:inline;" autocomplete="off">
                                <input type="hidden" name="action" value="delete_company_admin">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="admin_id" value="<?php echo e($admin['id'] ?? ''); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinizden emin misiniz?');">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($company_admins)): ?>
                    <tr><td colspan="4">Kayıtlı firma admini yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Genel Kupon Yönetimi -->
    <div class="card">
        <div class="card-header">Genel Kupon Yönetimi</div>
        <div class="card-body">
            <form method="POST" class="mb-3" autocomplete="off">
                <input type="hidden" name="action" value="add_global_coupon">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label class="form-label">Kupon Kodu</label>
                    <input type="text" class="form-control" name="coupon_code" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">İndirim Oranı (%)</label>
                    <input type="number" class="form-control" name="discount_rate" min="0" max="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kullanım Limiti</label>
                    <input type="number" class="form-control" name="usage_limit" min="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Son Kullanma Tarihi</label>
                    <input type="date" class="form-control" name="expiry_date" value="<?php echo e(date('Y-m-d', strtotime('+1 month'))); ?>" required>
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
                <?php foreach ($global_coupons as $coupon): ?>
                    <tr>
                        <td><?php echo e($coupon['code'] ?? ''); ?></td>
                        <td><?php echo e((isset($coupon['discount_rate']) ? ($coupon['discount_rate'] * 100) : 0)); ?></td>
                        <td><?php echo e($coupon['usage_limit'] ?? ''); ?></td>
                        <td><?php echo e($coupon['expiry_date'] ?? ''); ?></td>
                        <td>
                            <form method="POST" style="display:inline;" class="me-2" autocomplete="off">
                                <input type="hidden" name="action" value="update_global_coupon">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="coupon_id" value="<?php echo e($coupon['id'] ?? ''); ?>">

                                <input type="text" name="coupon_code" value="<?php echo e($coupon['code'] ?? ''); ?>" class="form-control d-inline-block" style="width: 100px;">
                                <input type="number" name="discount_rate" value="<?php echo e((isset($coupon['discount_rate']) ? ($coupon['discount_rate'] * 100) : 0)); ?>" class="form-control d-inline-block" style="width: 80px;">
                                <input type="number" name="usage_limit" value="<?php echo e($coupon['usage_limit'] ?? 1); ?>" class="form-control d-inline-block" style="width: 80px;">
                                <input type="date" name="expiry_date" value="<?php echo e($coupon['expiry_date'] ?? date('Y-m-d', strtotime('+1 month'))); ?>" class="form-control d-inline-block" style="width: 120px;">
                                <button type="submit" class="btn btn-warning btn-sm">Güncelle</button>
                            </form>
                            <form method="POST" style="display:inline;" autocomplete="off">
                                <input type="hidden" name="action" value="delete_global_coupon">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="coupon_id" value="<?php echo e($coupon['id'] ?? ''); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinizden emin misiniz?');">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($global_coupons)): ?>
                    <tr><td colspan="5">Kayıtlı kupon yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
