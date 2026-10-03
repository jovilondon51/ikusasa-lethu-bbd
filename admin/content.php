<?php
require_once '../includes/functions.php';
requireAdmin();

$message = '';

// Add content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = requireText('title', 200);
    $description = requireText('description', 5000, false);
    $contentType = requireText('content_type', 20);
    $contentText = requireText('content_text', 100000, false);
    if (!in_array($contentType, ['text', 'video', 'pdf', 'document', 'link'], true)) failRequest(422, 'Choose a valid content type.');
    if ($contentType === 'link' && (!filter_var($contentText, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($contentText, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) failRequest(422, 'Links must start with https:// or http://.');
    $upload = null;
    try {
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) $upload = storeUpload($_FILES['file'], 'content');
        $stmt = $pdo->prepare('INSERT INTO learning_content (title, description, content_type, content_text, file_path, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $contentType, $contentText, $upload['file_path'] ?? null, $_SESSION['user_id']]);
    } catch (Throwable $error) { if ($upload) removeStored($upload['cleanup_key']); throw $error; }
    flash('Content uploaded.'); header('Location: content.php'); exit;
}

// Delete content
if (isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    $file = $pdo->prepare('SELECT file_path FROM learning_content WHERE id = ?');
    $file->execute([$_POST['delete']]); $filePath = $file->fetchColumn();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM content_progress WHERE content_id = ?')->execute([$_POST['delete']]);
        $pdo->prepare('DELETE FROM learning_content WHERE id = ?')->execute([$_POST['delete']]);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    if ($filePath) removeStored($filePath);
    header('Location: content.php');
    exit;
}

$content = $pdo->query("SELECT c.*, a.full_name as admin_name 
    FROM learning_content c 
    LEFT JOIN admins a ON c.created_by = a.id 
    ORDER BY c.created_at DESC")->fetchAll();

$pageTitle = "Learning Content";
$activePage = "content";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-book"></i> Learning Content</h2>
        <button class="btn btn-primary" onclick="openModal('addContentModal')">
            <i class="fas fa-upload"></i> Upload Content
        </button>
    </div>
    <?php echo renderFlash(); echo $message; ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($content as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td><span class="badge badge-info"><?php echo strtoupper($item['content_type']); ?></span></td>
                    <td><?php echo htmlspecialchars($item['admin_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;"><?php echo csrfField(); ?><input type="hidden" name="delete" value="<?php echo $item['id']; ?>"><button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this content?">
                            <i class="fas fa-trash"></i>
                        </button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($content)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No content yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="addContentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upload Learning Content</h2>
            <button class="modal-close" onclick="closeModal('addContentModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Content Type</label>
                <select name="content_type" class="form-select" required>
                    <option value="text">Text/HTML</option>
                    <option value="video">Video</option>
                    <option value="pdf">PDF</option>
                    <option value="document">Document</option>
                    <option value="link">External Link</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Content Text / Embed Code / Link</label>
                <textarea name="content_text" class="form-textarea" placeholder="Paste text, embed code, or URL here"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Upload File (optional)</label>
                <input type="file" name="file" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
