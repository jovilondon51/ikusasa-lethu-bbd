<?php
// Uses environment variables when set (Render), falls back to
// your local Docker Compose values otherwise.
$host     = getenv('DB_HOST') ?: 'db';
$dbname   = getenv('DB_NAME') ?: 'ikusasa_bbd';
$username = getenv('DB_USER') ?: 'bbd_user';
$password = getenv('DB_PASS') ?: 'bbd_pass123';
$port     = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// Aiven requires SSL. The CA certificate is bundled into the project
// at config/aiven-ca.pem, and used automatically when DB_HOST points
// at an aivencloud.com address.
if (strpos($host, 'aivencloud.com') !== false) {
    $caPath = __DIR__ . '/aiven-ca.pem';
    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
