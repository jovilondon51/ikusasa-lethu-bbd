<?php
require_once '../includes/functions.php';
requireAdmin();

// Toggle community mode
if (isset($_POST['toggle_mode'])) {
    $newMode = getSetting('community_mode') === 'everyone' ? 'admin_only' : 'everyone';
    setSetting('community_mode', $newMode);
    header('Location: community.php');
    exit;
}

// Approve/reject message
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $pdo->prepare("UPDATE community_messages SET status = 'approved' WHERE id = ?")->execute([$_GET['approve']]);
    header('Location: community.php');
    exit;
}
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $pdo->prepare("UPDATE community_messages SET status = 'rejected' WHERE id = ?")->execute([$_GET['reject']]);
    header('Location: community.php');
    exit;
}

// Admin post message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_message'])) {
    $stmt = $pdo->prepare("INSERT INTO community_messages (sender_id, sender_type, message, status) VALUES (?, 'admin', ?, 'approved')");
    $stmt->execute([$_SESSION['user_id'], trim($_POST['message'])]);
    header('Location: community.php');
    exit;
}

$communityMode = getSetting('community_mode');
$messages = $pdo->query("SELECT cm.*, 
    CASE WHEN cm.sender_type = 'admin' THEN a.full_name ELSE l.full_name END as sender_name,
    CASE WHEN cm.sender_type = 'admin' THEN a.username ELSE l.username END as sender_username
    FROM community_messages cm
    LEFT JOIN admins a ON cm.sender_type = 'admin' AND cm.sender_id = a.id
    LEFT JOIN learners l ON cm.sender_type = 'learner' AND cm.sender_id = l.id
    ORDER BY cm.created_at DESC")->fetchAll();

$pageTitle = "Community Moderation";
$activePage = "community";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-comments"></i> Community Moderation</h2>
        <form method="POST" style="display:inline;">
            <button type="submit" name="toggle_mode" class="btn btn-<?php echo $communityMode === 'everyone' ? 'warning' : 'primary'; ?>">
                <i class="fas fa-toggle-<?php echo $communityMode === 'everyone' ? 'on' : 'off'; ?>"></i>
                Mode: <?php echo $communityMode === 'everyone' ? 'Everyone Can Post' : 'Admin Only'; ?>
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Post Announcement</h2>
    </div>
    <form method="POST" action="">
        <div class="form-group">
            <textarea name="message" class="form-textarea" placeholder="Write an announcement..." required></textarea>
        </div>
        <button type="submit" name="admin_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Announcement</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Messages</h2>
    </div>
    <div class="message-list">
        <?php foreach ($messages as $msg): ?>
        <div class="message">
            <div class="message-header">
                <span class="message-author">
                    <i class="fas fa-<?php echo $msg['sender_type'] === 'admin' ? 'shield-alt' : 'user'; ?>"></i>
                    <?php echo htmlspecialchars($msg['sender_name']); ?> 
                    <span style="color:var(--text-muted); font-size:0.8rem;">(@<?php echo htmlspecialchars($msg['sender_username'] ?? 'unknown'); ?>)</span>
                    <span class="badge badge-<?php echo $msg['sender_type'] === 'admin' ? 'info' : 'warning'; ?>">
                        <?php echo ucfirst($msg['sender_type']); ?>
                    </span>
                    <?php if ($msg['status'] === 'pending'): ?>
                        <span class="badge badge-warning">Pending</span>
                    <?php endif; ?>
                </span>
                <span class="message-time"><?php echo date('M d, H:i', strtotime($msg['created_at'])); ?></span>
            </div>
            <div class="message-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
            <div style="margin-top:0.75rem;">
                <span class="badge badge-<?php echo $msg['status'] === 'approved' ? 'success' : ($msg['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                    <?php echo ucfirst($msg['status']); ?>
                </span>
                <?php if ($msg['status'] === 'pending'): ?>
                <a href="?approve=<?php echo $msg['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</a>
                <a href="?reject=<?php echo $msg['id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <p style="text-align:center; color:var(--text-muted);">No messages yet</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>