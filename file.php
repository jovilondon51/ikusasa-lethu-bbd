<?php
require_once __DIR__ . '/includes/functions.php';
$user = currentUser();
if (!$user) failRequest(401, 'Please log in again.');
$key = $_GET['path'] ?? '';
if (!is_string($key)) failRequest(400, 'Invalid file path.');
try { $key = safeStorageKey($key); } catch (InvalidArgumentException $error) { failRequest(404, 'File not found.'); }
$allowed = false;
if (str_starts_with($key, 'uploads/projects/')) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE file_path = ? OR
        (extracted_path IS NOT NULL AND LEFT(?, CHAR_LENGTH(CONCAT(TRIM(TRAILING '/' FROM extracted_path), '/'))) = CONCAT(TRIM(TRAILING '/' FROM extracted_path), '/'))");
    $stmt->execute([$key, $key]);
    foreach ($stmt->fetchAll() as $project) {
        if (isAdmin() || (int) $project['learner_id'] === (int) $user['id']) { $allowed = true; break; }
    }
} elseif (str_starts_with($key, 'uploads/content/')) {
    $stmt = $pdo->prepare("SELECT id FROM learning_content WHERE file_path = ?" . (isAdmin() ? '' : " AND status = 'active'"));
    $stmt->execute([$key]); $allowed = (bool) $stmt->fetch();
} elseif (str_starts_with($key, 'uploads/profiles/')) {
    $stmt = $pdo->prepare('SELECT id FROM learners WHERE profile_picture = ?' . (isAdmin() ? '' : ' AND id = ?'));
    $stmt->execute(isAdmin() ? [$key] : [$key, $user['id']]); $allowed = (bool) $stmt->fetch();
}
if (!$allowed) failRequest(404, 'File not found.');
try { $path = storagePath($key); } catch (InvalidArgumentException $error) { failRequest(404, 'File not found.'); }
if (!is_file($path)) failRequest(404, 'File not found.');
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (wantsJson()) {
    if (!in_array($extension, EDITABLE_EXTENSIONS, true) || filesize($path) > MAX_PROJECT_FILE_BYTES) failRequest(422, 'This file cannot be opened in the editor.');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'content' => file_get_contents($path)], JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
$inlineImage = in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'], true) && !isset($_GET['download']);
if ($inlineImage) validateImage($path);
$mime = $inlineImage ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : 'application/octet-stream';
header('Content-Type: ' . $mime);
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Content-Disposition: ' . ($inlineImage ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($path)) . '"');
header('Content-Length: ' . filesize($path));
session_write_close();
readfile($path);
