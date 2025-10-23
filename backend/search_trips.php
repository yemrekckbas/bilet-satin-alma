<?php
header('Content-Type: application/json');
include 'db_connect.php';

$departure_city = isset($_GET['departure_city']) ? $_GET['departure_city'] : '';
$destination_city = isset($_GET['destination_city']) ? $_GET['destination_city'] : '';
$departure_time = isset($_GET['departure_time']) ? $_GET['departure_time'] : '';

if (!$departure_city || !$destination_city || !$departure_time) {
    echo json_encode(['error' => 'Tüm alanlar gereklidir.']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT t.*, c.name AS company_name, 
                          (SELECT COUNT(*) FROM seats s WHERE s.trip_id = t.id AND s.is_taken = 1) AS taken_seats 
                          FROM trips t 
                          JOIN companies c ON t.company_id = c.id 
                          WHERE t.departure_city = :departure_city 
                          AND t.destination_city = :destination_city 
                          AND DATE(t.departure_time) = :departure_time");
    $stmt->bindParam(':departure_city', $departure_city);
    $stmt->bindParam(':destination_city', $destination_city);
    $stmt->bindParam(':departure_time', $departure_time);
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($trips);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>