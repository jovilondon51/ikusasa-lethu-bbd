<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : '';
}

function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

function logLogin($userId, $userType) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, user_type, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $userType, $ip]);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isLearner() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'learner';
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

function requireLearner() {
    if (!isLearner()) {
        header('Location: /login.php');
        exit;
    }
}

function getLearnerName($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT full_name FROM learners WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ? $result['full_name'] : 'Unknown';
}

function getAdminName($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ? $result['full_name'] : 'Unknown';
}

function getUnreadLoginCount() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_type = 'learner' AND DATE(login_time) = ?");
    $stmt->execute([$today]);
    return $stmt->fetchColumn();
}

function checkAndAwardBadges($learnerId) {
    global $pdo;
    
    // Get current stats
    $contentCompleted = $pdo->prepare("SELECT COUNT(*) FROM content_progress WHERE learner_id = ? AND completed = 1");
    $contentCompleted->execute([$learnerId]);
    $contentCount = $contentCompleted->fetchColumn();
    
    $projectCount = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE learner_id = ?");
    $projectCount->execute([$learnerId]);
    $projects = $projectCount->fetchColumn();
    
    $attendanceCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE learner_id = ? AND status = 'present'");
    $attendanceCount->execute([$learnerId]);
    $attendance = $attendanceCount->fetchColumn();
    
    // Get all badges
    $badges = $pdo->query("SELECT * FROM badges")->fetchAll();
    
    foreach ($badges as $badge) {
        $earned = false;
        
        switch ($badge['criteria_type']) {
            case 'content_complete':
                if ($contentCount >= $badge['criteria_value']) $earned = true;
                break;
            case 'project_create':
                if ($projects >= $badge['criteria_value']) $earned = true;
                break;
            case 'attendance_streak':
                if ($attendance >= $badge['criteria_value']) $earned = true;
                break;
        }
        
        if ($earned) {
            try {
                $stmt = $pdo->prepare("INSERT INTO learner_badges (learner_id, badge_id) VALUES (?, ?)");
                $stmt->execute([$learnerId, $badge['id']]);
            } catch (PDOException $e) {
                // Already has badge, ignore
            }
        }
    }
}
?>