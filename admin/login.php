<?php
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    if (!is_string($username)) failRequest(422, 'Invalid login details.');
    $username = trim($username);
    $password = $_POST['password'] ?? '';
    if (!is_string($username) || !is_string($password) || strlen($username) > 100 || strlen($password) > 1024) failRequest(422, 'Invalid login details.');
    if (loginIsLimited('admin', $username)) {
        header('Retry-After: 900');
        failRequest(429, 'Too many login attempts. Please try again in 15 minutes.');
    }
    
    // Only check admins
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        loginSession($admin, 'admin');
        clearAccountLoginFailures('admin', $username);
        logLogin($admin['id'], 'admin');
        header('Location: dashboard.php');
        exit;
    }
    
    recordLoginFailure('admin', $username);
    $error = "Invalid username or password";
}

$pageTitle = "Admin Login - Ikusasa Lethu";
$activePage = '';
include '../includes/header.php';
?>

<div class="auth-main">
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <h1><i class="fas fa-shield-alt"></i> Ikusasa Lethu</h1>
                <p>Admin Portal - BBD Staff Only</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
            <?php echo csrfField(); ?>
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input id="username" type="text" name="username" class="form-input" autocomplete="username" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input id="password" type="password" name="password" class="form-input" autocomplete="current-password" required>
                    <button type="button" class="btn btn-sm btn-secondary" data-password-toggle="password" aria-controls="password" aria-pressed="false" style="margin-top:0.5rem;">Show password</button>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div style="text-align:center; margin-top:1.5rem;">
                <a href="../index.php" style="color:var(--text-muted); text-decoration:none; font-size:0.9rem;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
