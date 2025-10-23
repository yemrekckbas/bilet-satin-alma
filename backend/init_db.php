<?php
// init_db.php: Veritabanı ve tabloları oluşturur

// Veritabanı dosyası yolu
$dbPath = __DIR__ . '/db/database.db';

// Klasörü yoksa oluştur
if (!file_exists(__DIR__ . '/db')) {
    mkdir(__DIR__ . '/db', 0755, true);
}

try {
    // SQLite bağlantısı
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Foreign key desteği
    $db->exec('PRAGMA foreign_keys = ON;');

    // User tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('user', 'company_admin', 'admin')),
            balance REAL DEFAULT 0.0,
            company_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
        );
    ");

    // Companies tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS companies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Trips tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            departure_city TEXT NOT NULL,
            destination_city TEXT NOT NULL,
            departure_time DATETIME NOT NULL,
            arrival_time DATETIME NOT NULL,
            price REAL NOT NULL CHECK (price >= 0),
            capacity INTEGER NOT NULL CHECK (capacity > 0),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        );
    ");

    // Tickets tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            trip_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL CHECK (seat_number > 0),
            coupon_id INTEGER,
            price_paid REAL NOT NULL CHECK (price_paid >= 0),
            status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'canceled')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
        );
    ");

    // Seats tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS seats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL CHECK (seat_number > 0),
            is_taken BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (trip_id, seat_number),
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
        );
    ");

    // Coupons tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            discount_rate REAL NOT NULL CHECK (discount_rate >= 0 AND discount_rate <= 1),
            usage_limit INTEGER NOT NULL CHECK (usage_limit > 0),
            expiry_date DATETIME NOT NULL,
            company_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
        );
    ");

    // User_coupons tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            coupon_id INTEGER NOT NULL,
            used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
        );
    ");

    echo "Veritabanı ve tablolar başarıyla oluşturuldu!\n";
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}

// Bağlantıyı kapat
$db = null;
?>