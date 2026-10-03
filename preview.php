<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/preview.php';
$user = currentUser();
if (!$user) failRequest(401, 'Please log in again.');
$id = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT);
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
$stmt->execute([$id]); $project = $stmt->fetch();
if (!$project || (!isAdmin() && (int) $project['learner_id'] !== (int) $user['id'])) failRequest(404, 'Project not found.');
$key = $_GET['path'] ?? projectEntry($project);
if (!is_string($key) || !$key) failRequest(404, 'No HTML file was found.');
$html = buildProjectPreview($project, $key);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'html' => $html], JSON_INVALID_UTF8_SUBSTITUTE);
