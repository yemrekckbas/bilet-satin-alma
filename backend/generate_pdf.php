<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
include 'auth_check.php';
checkRole('user');
include 'db_connect.php';

// Oturum değişkenlerini kontrol et
if (!isset($_SESSION['user_id'])) {
    error_log("Oturum user_id tanımlı değil.", 3, __DIR__ . '/error.log');
    header("Location: ../frontend/login.php");
    exit;
}

// $db nesnesini kontrol et
if (!isset($db) || !($db instanceof PDO)) {
    error_log("Veritabanı bağlantısı başarısız: \$db nesnesi oluşturulmadı.", 3, __DIR__ . '/error.log');
    die('Sistem hatası: Veritabanı bağlantısı sağlanamadı.');
}

// Composer autoload dosyasını dahil et (mPDF için)
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    error_log('vendor/autoload.php dosyası bulunamadı. Lütfen Composer ile mPDF kütüphanesini kurun.', 3, __DIR__ . '/error.log');
    die('vendor/autoload.php dosyası bulunamadı. Lütfen Composer ile mPDF kütüphanesini kurun.');
}
require_once __DIR__ . '/vendor/autoload.php';

use Mpdf\Mpdf;

$ticket_id = $_GET['ticket_id'] ?? '';
if (!$ticket_id) {
    error_log("ticket_id parametresi eksik.", 3, __DIR__ . '/error.log');
    header("Location: ../frontend/account.php");
    exit;
}

try {
    $stmt = $db->prepare("SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time, c.name AS company_name FROM tickets t JOIN trips tr ON t.trip_id = tr.id JOIN companies c ON tr.company_id = c.id WHERE t.id = :ticket_id AND t.user_id = :user_id AND t.status = 'active'");
    $stmt->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    // Hata ayıkla
    if (!$ticket) {
        error_log("Bilet bulunamadı: ticket_id=$ticket_id, user_id=" . $_SESSION['user_id'], 3, __DIR__ . '/error.log');
        header("Location: ../frontend/account.php?error=no_ticket");
        exit;
    }

    // Veriyi kontrol et
    if (empty($ticket['departure_city']) || empty($ticket['destination_city']) || empty($ticket['departure_time']) || empty($ticket['arrival_time']) || empty($ticket['seat_number']) || empty($ticket['price_paid']) || empty($ticket['company_name'])) {
        error_log("Bilet verisi eksik: " . print_r($ticket, true), 3, __DIR__ . '/error.log');
        die('Bilet verilerinde eksiklik var. Lütfen veritabanını kontrol edin.');
    }

    // mPDF nesnesini oluştur
    $mpdf = new Mpdf([
        'default_font' => 'dejavusans', // Türkçe karakterler için uygun font
        'format' => 'A4', // Sayfa boyutu
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
    ]);

    // Bilet için HTML içeriği
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            h1 { color: #333; text-align: center; font-family: dejavusans; }
            .ticket { border: 1px solid #000; padding: 20px; width: 80%; margin: 0 auto; background-color: #f9f9f9; }
            .info { font-size: 14px; margin: 10px 0; font-family: dejavusans; }
            .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>BiletYol - Bilet</h1>
        <div class="ticket">
            <div class="header">
                <h2>Bilet Bilgileri</h2>
            </div>
            <p class="info"><strong>Kalkış:</strong> ' . htmlspecialchars($ticket['departure_city']) . '</p>
            <p class="info"><strong>Varış:</strong> ' . htmlspecialchars($ticket['destination_city']) . '</p>
            <p class="info"><strong>Kalkış Zamanı:</strong> ' . htmlspecialchars($ticket['departure_time']) . '</p>
            <p class="info"><strong>Varış Zamanı:</strong> ' . htmlspecialchars($ticket['arrival_time']) . '</p>
            <p class="info"><strong>Koltuk No:</strong> ' . htmlspecialchars($ticket['seat_number']) . '</p>
            <p class="info"><strong>Fiyat:</strong> ' . htmlspecialchars($ticket['price_paid']) . ' TL</p>
            <p class="info"><strong>Firma:</strong> ' . htmlspecialchars($ticket['company_name']) . '</p>
        </div>
    </body>
    </html>';

    // HTML içeriğini PDF'ye yaz
    $mpdf->WriteHTML($html);

    // PDF'yi tarayıcıda göster
    $mpdf->Output('bilet_' . $ticket_id . '.pdf', 'I'); // 'I': tarayıcıda göster, 'F': dosyaya kaydet, 'D': indirme
    exit;
} catch (Exception $e) {
    error_log("PDF oluşturma hatası: " . $e->getMessage(), 3, __DIR__ . '/error.log');
    die('PDF oluşturma hatası: ' . $e->getMessage());
}
?>