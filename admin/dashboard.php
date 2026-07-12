<?php
require_once '../includes/functions.php';
requireAdmin();

// Get stats
$totalLearners = $pdo->query("SELECT COUNT(*) FROM learners WHERE status = 'active'")->fetchColumn();
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$totalContent = $pdo->query("SELECT COUNT(*) FROM learning_content WHERE status = 'active'")->fetchColumn();
$todayLogins = $pdo->query("SELECT COUNT(*) FROM login_logs WHERE user_type = 'learner' AND DATE(login_time) = CURDATE()")->fetchColumn();

// Recent logins
$recentLogins = $pdo->query("SELECT ll.*, l.full_name as learner_name 
    FROM login_logs ll 
    LEFT JOIN learners l ON ll.user_id = l.id 
    WHERE ll.user_type = 'learner' 
    ORDER BY ll.login_time DESC LIMIT 10")->fetchAll();

// Recent projects
$recentProjects = $pdo->query("SELECT p.*, l.full_name as learner_name 
    FROM projects p 
    JOIN learners l ON p.learner_id = l.id 
    ORDER BY p.created_at DESC LIMIT 5")->fetchAll();

$pageTitle = "Admin Dashboard";
$activePage = "dashboard";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h1 class="card-title">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p class="card-subtitle">Here's what's happening today</p>
        </div>
        <span class="badge badge-info"><i class="fas fa-shield-alt"></i> Admin</span>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalLearners; ?></h3>
            <p>Active Learners</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-code"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalProjects; ?></h3>
            <p>Total Projects</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-book"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalContent; ?></h3>
            <p>Learning Content</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-sign-in-alt"></i></div>
        <div class="stat-info">
            <h3><?php echo $todayLogins; ?></h3>
            <p>Today's Logins</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-bell"></i> Login Alerts (Recent)</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Learner</th><th>Login Time</th><th>IP Address</th></tr>
            </thead>
           <tbody>
    <?php foreach ($recentLogins as $log): 
        // Get learner profile pic
        $pic = $pdo->prepare("SELECT profile_picture FROM learners WHERE id = ?");
        $pic->execute([$log['user_id']]);
        $profilePic = $pic->fetchColumn();
    ?>
    <tr>
        <td>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <?php if ($profilePic): ?>
                    <img src="../<?php echo $profilePic; ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <div style="width:32px; height:32px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.75rem; font-weight:700;">
                        <?php echo strtoupper(substr($log['learner_name'] ?? 'U', 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <?php echo htmlspecialchars($log['learner_name'] ?? 'Unknown'); ?>
            </div>
        </td>
        <td><?php echo date('M d, Y H:i', strtotime($log['login_time'])); ?></td>
        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($recentLogins)): ?>
    <tr><td colspan="3" style="text-align:center; color:var(--text-muted);">No logins yet</td></tr>
    <?php endif; ?>
</tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-code-branch"></i> Recent Projects</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Project</th><th>Learner</th><th>Language</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentProjects as $proj): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($proj['title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($proj['learner_name']); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($proj['language']); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($proj['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentProjects)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No projects yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>