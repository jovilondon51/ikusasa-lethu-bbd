<?php
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    if (!is_string($username)) failRequest(422, 'Invalid login details.');
    $username = trim($username);
    $password = $_POST['password'] ?? '';
    if (!is_string($username) || !is_string($password) || strlen($username) > 100 || strlen($password) > 1024) failRequest(422, 'Invalid login details.');
    if (loginIsLimited('learner', $username)) {
        header('Retry-After: 900');
        failRequest(429, 'Too many login attempts. Please try again in 15 minutes.');
    }
    
    // Only check learners
    $stmt = $pdo->prepare("SELECT * FROM learners WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $learner = $stmt->fetch();
    
    if ($learner && password_verify($password, $learner['password_hash'])) {
        loginSession($learner, 'learner');
        clearAccountLoginFailures('learner', $username);
        logLogin($learner['id'], 'learner');
        header('Location: learner/dashboard.php');
        exit;
    }
    
    recordLoginFailure('learner', $username);
    $error = "Invalid username or password";
}

$pageTitle = "Learner Login - Ikusasa Lethu";
$activePage = '';
include 'includes/header.php';
?>

<div class="auth-main">
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <h1><i class="fas fa-user-graduate"></i> Ikusasa Lethu</h1>
                <p>Learner Portal</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
            <?php echo csrfField(); ?>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%; justify-content:center;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div style="text-align:center; margin-top:1.5rem;">
                <a href="index.php" style="color:var(--text-muted); text-decoration:none; font-size:0.9rem;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
