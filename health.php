<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/storage.php';
try {
    $pdo->query('SELECT 1 FROM admins LIMIT 1');
    if (!is_writable(storageRoot())) throw new RuntimeException('Storage is unavailable.');
    echo json_encode(['status' => 'ready']);
} catch (Throwable $error) { http_response_code(503); echo json_encode(['status' => 'unavailable']); }
