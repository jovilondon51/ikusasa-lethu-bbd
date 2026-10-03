<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/storage.php';

function currentUser(): ?array {
    global $pdo;
    static $loaded = false;
    static $user = null;
    if ($loaded) return $user;
    $loaded = true;
    $role = $_SESSION['user_type'] ?? '';
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || !in_array($role, ['admin', 'learner'], true)) return null;
    $table = $role === 'admin' ? 'admins' : 'learners';
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found || ($role === 'learner' && $found['status'] !== 'active')
        || !hash_equals(accountRevision($role, $id), $_SESSION['account_revision'] ?? '')
        || time() - ($_SESSION['last_activity'] ?? 0) > 1800
        || !hash_equals(hash('sha256', $found['password_hash']), $_SESSION['credential_version'] ?? '')) {
        destroyLogin();
        return null;
    }
    $_SESSION['last_activity'] = time();
    $_SESSION['user_name'] = $found['full_name'];
    return $user = $found;
}

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
    return currentUser() !== null && ($_SESSION['user_type'] ?? '') === 'admin';
}

function isLearner() {
    return currentUser() !== null && ($_SESSION['user_type'] ?? '') === 'learner';
}

function requireAdmin() {
    if (!isAdmin()) {
        if (wantsJson()) failRequest(401, 'Please log in again.');
        header('Location: /admin/login.php');
        exit;
    }
}

function requireLearner() {
    if (!isLearner()) {
        if (wantsJson()) failRequest(401, 'Please log in again.');
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
    $contentCompleted = $pdo->prepare("SELECT COUNT(*) FROM content_progress cp JOIN learning_content lc ON lc.id = cp.content_id WHERE cp.learner_id = ? AND cp.completed = 1 AND lc.status = 'active'");
    $contentCompleted->execute([$learnerId]);
    $contentCount = $contentCompleted->fetchColumn();
    
    $projectCount = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE learner_id = ?");
    $projectCount->execute([$learnerId]);
    $projects = $projectCount->fetchColumn();
    
    $attendanceCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE learner_id = ? AND status = 'present'");
    $attendanceCount->execute([$learnerId]);
    $attendance = $attendanceCount->fetchColumn();

    // A missed recorded session breaks the streak. Unrecorded dates are not counted.
    $sessions = $pdo->prepare("SELECT sessions.attendance_date, COALESCE(a.status, 'absent') AS status
        FROM (SELECT DISTINCT attendance_date FROM attendance WHERE attendance_date <= CURDATE()) sessions
        LEFT JOIN attendance a ON a.attendance_date = sessions.attendance_date AND a.learner_id = ?
        ORDER BY sessions.attendance_date DESC");
    $sessions->execute([$learnerId]);
    $streak = 0;
    foreach ($sessions->fetchAll() as $session) {
        if ($session['status'] !== 'present') break;
        $streak++;
    }
    
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
                if ($streak >= $badge['criteria_value']) $earned = true;
                break;
            case 'attendance_total':
                if ($attendance >= $badge['criteria_value']) $earned = true;
                break;
        }
        
        if ($earned) {
            try {
                $stmt = $pdo->prepare("INSERT INTO learner_badges (learner_id, badge_id) VALUES (?, ?)");
                $stmt->execute([$learnerId, $badge['id']]);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062) throw $e;
            }
        }
    }
}
?>
