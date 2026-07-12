<?php
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Only check admins
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['user_name'] = $admin['full_name'];
        logLogin($admin['id'], 'admin');
        header('Location: dashboard.php');
        exit;
    }
    
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
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
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