<?php
require_once 'includes/functions.php';
if (isAdmin()) { header('Location: admin/dashboard.php'); exit; }
if (isLearner()) { header('Location: learner/dashboard.php'); exit; }

$pageTitle = "Welcome - Ikusasa Lethu";
$activePage = '';
include 'includes/header.php';
?>

<div class="auth-main">
    <div class="login-container">
        <div class="login-box" style="text-align:center;">
            <div class="login-logo">
                <h1><i class="fas fa-graduation-cap"></i> Ikusasa Lethu</h1>
                <p>BBD Coding Program - Learner Tracking System</p>
            </div>
            
            <div style="display:grid; gap:1rem; margin-top:2rem;">
                <a href="admin/login.php" class="btn btn-primary" style="justify-content:center; padding:1.25rem; font-size:1.1rem;">
                    <i class="fas fa-shield-alt"></i> Admin Portal
                </a>
                <a href="login.php" class="btn btn-success" style="justify-content:center; padding:1.25rem; font-size:1.1rem;">
                    <i class="fas fa-user-graduate"></i> Learner Portal
                </a>
            </div>
            
            <p style="margin-top:2rem; color:var(--text-muted); font-size:0.85rem;">
                Choose your portal to continue
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>