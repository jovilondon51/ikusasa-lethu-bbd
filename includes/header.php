<?php 
require_once __DIR__ . '/auth.php'; 

// Auto-detect if we're in admin/ or learner/ folder
$scriptPath = $_SERVER['PHP_SELF'] ?? '';
$basePath = (strpos($scriptPath, '/admin/') !== false || strpos($scriptPath, '/learner/') !== false) ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Ikusasa Lethu BBD'; ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body data-theme="light">
    <?php if ($currentUser): ?>
    <div class="mobile-topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Open menu"><i class="fas fa-bars"></i></button>
        <span class="mobile-topbar-title"><i class="fas fa-graduation-cap"></i> Ikusasa Lethu</span>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-graduation-cap"></i> Ikusasa Lethu</h2>
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
        </div>
        <ul class="nav-links">
            <?php if (isAdmin()): ?>
                <li><a href="<?php echo $basePath; ?>admin/dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="<?php echo $basePath; ?>admin/projects.php" class="<?php echo $activePage === 'projects' ? 'active' : ''; ?>">
                    <i class="fas fa-code-branch"></i> Projects</a></li>
                <li><a href="<?php echo $basePath; ?>admin/staff.php" class="<?php echo $activePage === 'staff' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i> Staff</a></li>
                <li><a href="<?php echo $basePath; ?>admin/learners.php" class="<?php echo $activePage === 'learners' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Learners</a></li>
                <li><a href="<?php echo $basePath; ?>admin/attendance.php" class="<?php echo $activePage === 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i> Attendance</a></li>
                <li><a href="<?php echo $basePath; ?>admin/content.php" class="<?php echo $activePage === 'content' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i> Content</a></li>
                <li><a href="<?php echo $basePath; ?>admin/analytics.php" class="<?php echo $activePage === 'analytics' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Analytics</a></li>
                <li><a href="<?php echo $basePath; ?>admin/password_requests.php" class="<?php echo $activePage === 'password_requests' ? 'active' : ''; ?>">
                    <i class="fas fa-key"></i> Password Requests</a></li>
            <?php else: ?>
                <li><a href="<?php echo $basePath; ?>learner/dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="<?php echo $basePath; ?>learner/projects.php" class="<?php echo $activePage === 'projects' ? 'active' : ''; ?>">
                    <i class="fas fa-code"></i> My Projects</a></li>
                <li><a href="<?php echo $basePath; ?>learner/content.php" class="<?php echo $activePage === 'content' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i> Learning</a></li>
                <li><a href="<?php echo $basePath; ?>learner/profile.php" class="<?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Profile</a></li>
            <?php endif; ?>
            <li class="logout"><a href="<?php echo $basePath; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
    <main class="main-content">
    <?php else: ?>
    <main class="auth-main">
    <?php endif; ?>
    <main class="auth-main">
    <?php endif; ?>
