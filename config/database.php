<?php
// Render uses Aiven credentials. Local Compose supplies these same variables.
$host     = getenv('DB_HOST');
$dbname   = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: '3306';

if (!$host || !$dbname || !$username || !$password || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    error_log('Database configuration is missing or invalid.');
    http_response_code(503);
    echo 'The service is temporarily unavailable.';
    exit(1);
}

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// Aiven requires SSL. The CA certificate is bundled into the project
// at config/aiven-ca.pem, and used automatically when DB_HOST points
// at an aivencloud.com address.
if (str_ends_with(strtolower($host), '.aivencloud.com') || getenv('DB_SSL_CA')) {
    $caPath = getenv('DB_SSL_CA') ?: __DIR__ . '/aiven-ca.pem';
    if (!is_readable($caPath)) {
        error_log('Database CA certificate is missing.');
        http_response_code(503);
        echo 'The service is temporarily unavailable.';
    exit(1);
    }
    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getCode());
    http_response_code(503);
    echo 'The service is temporarily unavailable.';
    exit(1);
}
?>
