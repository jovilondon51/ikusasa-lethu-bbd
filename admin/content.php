<?php
require_once '../includes/functions.php';
requireAdmin();

$message = '';

// Add content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $contentType = $_POST['content_type'];
    $contentText = trim($_POST['content_text'] ?? '');
    
    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $uploadDir = '../uploads/content/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName);
        $filePath = 'uploads/content/' . $fileName;
    }
    
    $stmt = $pdo->prepare("INSERT INTO learning_content (title, description, content_type, content_text, file_path, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $contentType, $contentText, $filePath, $_SESSION['user_id']]);
    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Content uploaded!</div>';
}

// Delete content
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM learning_content WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
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
    <?php echo $message; ?>
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
                        <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" data-confirm="Delete this content?">
                            <i class="fas fa-trash"></i>
                        </a>
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