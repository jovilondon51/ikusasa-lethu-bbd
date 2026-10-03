<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
$name = getenv('ADMIN_NAME'); $email = getenv('ADMIN_EMAIL'); $username = getenv('ADMIN_USERNAME'); $password = getenv('ADMIN_PASSWORD');
if (!$name || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$username || strlen($username) > 100 || !$password || !validPassword($password)) {
    fwrite(STDERR, "Set ADMIN_NAME, ADMIN_EMAIL, ADMIN_USERNAME and ADMIN_PASSWORD (12–72 bytes) for this command only.\n"); exit(1);
}
try {
    $pdo->prepare('INSERT INTO admins (full_name, email, username, password_hash) VALUES (?, ?, ?, ?)')->execute([$name, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Administrator created. Remove ADMIN_PASSWORD from your environment.\n";
} catch (PDOException $error) { fwrite(STDERR, "Unable to create administrator. Check duplicate usernames/emails and database setup.\n"); exit(1); }
