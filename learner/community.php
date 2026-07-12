<?php
require_once '../includes/functions.php';
requireLearner();

$learnerId = $_SESSION['user_id'];
$communityMode = getSetting('community_mode');

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $communityMode === 'everyone') {
    $stmt = $pdo->prepare("INSERT INTO community_messages (sender_id, sender_type, message, status) VALUES (?, 'learner', ?, 'pending')");
    $stmt->execute([$learnerId, trim($_POST['message'])]);
    header('Location: community.php');
    exit;
}

$messages = $pdo->query("SELECT cm.*, 
    CASE WHEN cm.sender_type = 'admin' THEN a.full_name ELSE l.full_name END as sender_name
    FROM community_messages cm
    LEFT JOIN admins a ON cm.sender_type = 'admin' AND cm.sender_id = a.id
    LEFT JOIN learners l ON cm.sender_type = 'learner' AND cm.sender_id = l.id
    WHERE cm.status = 'approved' OR (cm.sender_type = 'learner' AND cm.sender_id = $learnerId)
    ORDER BY cm.created_at DESC LIMIT 50")->fetchAll();

$pageTitle = "Community";
$activePage = "community";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-comments"></i> Community</h2>
        <?php if ($communityMode === 'admin_only'): ?>
            <span class="badge badge-warning"><i class="fas fa-lock"></i> Admin Only Mode</span>
        <?php else: ?>
            <span class="badge badge-success"><i class="fas fa-unlock"></i> Open Chat</span>
        <?php endif; ?>
    </div>
    
    <?php if ($communityMode === 'everyone'): ?>
    <form method="POST" action="" style="margin-bottom:1.5rem;">
        <div class="form-group">
            <textarea name="message" class="form-textarea" placeholder="Write a message..." required></textarea>
        </div>
        <button type="submit" name="send_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
        <small style="color:var(--text-muted); margin-left:1rem;">Messages require admin approval</small>
    </form>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Only admins can post announcements right now.
    </div>
    <?php endif; ?>
    
    <div class="message-list">
        <?php foreach ($messages as $msg): ?>
        <div class="message">
            <div class="message-header">
                <span class="message-author">
                    <i class="fas fa-<?php echo $msg['sender_type'] === 'admin' ? 'shield-alt' : 'user'; ?>"></i>
                    <?php echo htmlspecialchars($msg['sender_name']); ?>
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
        </div>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <p style="text-align:center; color:var(--text-muted);">No messages yet</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>