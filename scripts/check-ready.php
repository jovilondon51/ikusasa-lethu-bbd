<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/storage.php';
try {
    $pdo->query('SELECT 1 FROM admins LIMIT 1');
    if (!is_writable(storageRoot())) throw new RuntimeException('Storage not writable.');
} catch (Throwable $error) { fwrite(STDERR, "Database schema or upload storage is not ready.\n"); exit(1); }
