<?php
require_once '../includes/functions.php';
requireLearner();

$learnerId = $_SESSION['user_id'];

// Get learner's projects
$projects = $pdo->prepare("SELECT * FROM projects WHERE learner_id = ? ORDER BY created_at DESC LIMIT 3");
$projects->execute([$learnerId]);
$projects = $projects->fetchAll();

// Get content
$content = $pdo->query("SELECT * FROM learning_content WHERE status = 'active' ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Get progress
$progress = $pdo->prepare("SELECT COUNT(*) FROM content_progress WHERE learner_id = ? AND completed = 1");
$progress->execute([$learnerId]);
$completed = $progress->fetchColumn();

$totalContent = $pdo->query("SELECT COUNT(*) FROM learning_content WHERE status = 'active'")->fetchColumn();

$pageTitle = "Learner Dashboard";
$activePage = "dashboard";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h1 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p class="card-subtitle">Keep coding and learning!</p>
        </div>
        <span class="badge badge-success"><i class="fas fa-user-graduate"></i> Learner</span>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-code"></i></div>
        <div class="stat-info">
            <h3><?php echo count($projects); ?></h3>
            <p>Your Projects</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?php echo $completed; ?>/<?php echo $totalContent; ?></h3>
            <p>Content Completed</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-code"></i> Recent Projects</h2>
        <a href="projects.php" class="btn btn-primary btn-sm">View All</a>
    </div>
    <?php if (empty($projects)): ?>
        <p style="color:var(--text-muted);">No projects yet. <a href="projects.php">Create your first project!</a></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Language</th><th>Last Updated</th></tr></thead>
                <tbody>
                    <?php foreach ($projects as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                        <td><span class="badge badge-info"><?php echo htmlspecialchars($p['language']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($p['updated_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-book-open"></i> New Learning Content</h2>
        <a href="content.php" class="btn btn-primary btn-sm">View All</a>
    </div>
    <?php if (empty($content)): ?>
        <p style="color:var(--text-muted);">No content available yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Type</th><th>Added</th></tr></thead>
                <tbody>
                    <?php foreach ($content as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['title']); ?></td>
                        <td><span class="badge badge-info"><?php echo strtoupper($c['content_type']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>