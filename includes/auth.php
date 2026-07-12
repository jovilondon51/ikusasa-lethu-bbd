<?php
require_once __DIR__ . '/functions.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        $currentUser = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $currentUser->execute([$_SESSION['user_id']]);
        $currentUser = $currentUser->fetch();
    } else {
        $currentUser = $pdo->prepare("SELECT * FROM learners WHERE id = ?");
        $currentUser->execute([$_SESSION['user_id']]);
        $currentUser = $currentUser->fetch();
    }
} else {
    $currentUser = null;
}
?>