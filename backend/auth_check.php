<?php
// Oturum başlat — yalnızca aktif değilse
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Rol tabanlı erişim kontrolü
 * @param string $required_role Erişim için gerekli rol (örneğin: 'admin')
 */
function checkRole(string $required_role): void {
    // Oturum bilgileri eksikse giriş sayfasına yönlendir
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header("Location: login.php");
        exit;
    }

    // Kullanıcının rolü yetmiyorsa ana sayfaya yönlendir
    if ($_SESSION['role'] !== $required_role) {
        header("Location: ../index.php");
        exit;
    }
}

// Rol belirleme (eğer yoksa)
if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db_connect.php'; // Güvenli yol kullanımı

    try {
        $stmt = $db->prepare("SELECT role FROM user WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', (int)$_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $role = $stmt->fetchColumn();

        if ($role) {
            // XSS koruması ile oturuma kaydet
            $_SESSION['role'] = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        } else {
            // Kullanıcı bulunamadıysa güvenli çıkış
            session_regenerate_id(true);
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit;
        }
    } catch (PDOException $e) {
        // Hata logla ama kullanıcıya gösterme (güvenlik)
        error_log("DB Error in role check: " . $e->getMessage());
        header("Location: error.php"); // özel hata sayfası
        exit;
    }
}
?>
