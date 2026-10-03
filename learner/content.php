<?php
require_once '../includes/functions.php';
requireLearner();

$learnerId = $_SESSION['user_id'];

$contentId = $_GET['view'] ?? null;
if ($contentId !== null) {
    if (!filter_var($contentId, FILTER_VALIDATE_INT)) failRequest(404, 'Content not found.');
    $exists = $pdo->prepare("SELECT id FROM learning_content WHERE id = ? AND status = 'active'");
    $exists->execute([$contentId]);
    if (!$exists->fetch()) failRequest(404, 'Content not found.');
    $pdo->prepare('INSERT INTO content_progress (learner_id, content_id, progress_percent, last_accessed) VALUES (?, ?, 0, NOW()) ON DUPLICATE KEY UPDATE last_accessed = NOW()')->execute([$learnerId, $contentId]);
    if (isset($_POST['mark_complete'])) {
        $pdo->prepare('UPDATE content_progress SET progress_percent = 100, completed = 1 WHERE learner_id = ? AND content_id = ?')->execute([$learnerId, $contentId]);
        checkAndAwardBadges($learnerId); header('Location: content.php'); exit;
    }
}
$stmt = $pdo->prepare("SELECT lc.*, cp.completed AS is_completed, cp.progress_percent AS progress FROM learning_content lc LEFT JOIN content_progress cp ON cp.content_id = lc.id AND cp.learner_id = ? WHERE lc.status = 'active' ORDER BY lc.created_at DESC");
$stmt->execute([$learnerId]); $contentItems = $stmt->fetchAll();

$viewing = isset($_GET['view']) ? array_filter($contentItems, fn($c) => $c['id'] == $_GET['view']) : null;
$viewing = $viewing ? array_values($viewing)[0] : null;

$pageTitle = "Learning Content";
$activePage = "content";
include '../includes/header.php';
?>

<?php if ($viewing): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo htmlspecialchars($viewing['title']); ?></h2>
            <p class="card-subtitle"><?php echo htmlspecialchars($viewing['description']); ?></p>
        </div>
        <a href="content.php" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
    
    <div style="margin:1.5rem 0;">
        <?php if ($viewing['content_type'] === 'text'): ?>
            <div style="line-height:1.8;"><?php echo nl2br(htmlspecialchars($viewing['content_text'])); ?></div>
        <?php elseif ($viewing['content_type'] === 'link'): ?>
            <a href="<?php echo h(filter_var($viewing['content_text'], FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($viewing['content_text'], PHP_URL_SCHEME) ?? ''), ['https', 'http'], true) ? $viewing['content_text'] : '#'); ?>" rel="noopener noreferrer" target="_blank" class="btn btn-primary">
                <i class="fas fa-external-link-alt"></i> Open Link
            </a>
        <?php elseif ($viewing['file_path']): ?>
            <a href="<?php echo h(fileUrl($viewing['file_path'], true)); ?>" target="_blank" class="btn btn-primary">
                <i class="fas fa-download"></i> Download / View File
            </a>
        <?php endif; ?>
    </div>
    
    <?php if ($viewing['content_text'] && $viewing['content_type'] !== 'text' && $viewing['content_type'] !== 'link'): ?>
        <div style="margin-top:1rem; padding:1rem; background:var(--bg); border-radius:8px;">
            <?php echo nl2br(htmlspecialchars($viewing['content_text'])); ?>
        </div>
    <?php endif; ?>
    
    <div style="margin-top:2rem; padding-top:1rem; border-top:1px solid var(--border);">
        <?php if ($viewing['is_completed']): ?>
            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Completed</span>
        <?php else: ?>
            <form method="POST" action="?view=<?php echo (int) $viewing['id']; ?>" style="display:inline;">
                <?php echo csrfField(); ?>
                <button type="submit" name="mark_complete" class="btn btn-success">
                    <i class="fas fa-check"></i> Mark as Complete
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-book-open"></i> Learning Content</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Progress</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($contentItems as $item): 
                    $pct = $item['progress'] ?? 0;
                    $completed = $item['is_completed'] ?? 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td><span class="badge badge-info"><?php echo strtoupper($item['content_type']); ?></span></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div class="progress-bar" style="width:100px;">
                                <div class="progress-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $completed ? 'var(--secondary)' : 'var(--primary)'; ?>"></div>
                            </div>
                            <span class="badge badge-<?php echo $completed ? 'success' : 'warning'; ?>">
                                <?php echo $completed ? 'Done' : ($pct . '%'); ?>
                            </span>
                        </div>
                    </td>
                    <td><a href="?view=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($contentItems)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No content available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
