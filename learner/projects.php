<?php
require_once '../includes/functions.php';
requireLearner();
$learnerId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = requireText('title', 200);
        $description = requireText('description', 5000, false);
        $language = requireText('language', 20);
        if (!in_array($language, ['HTML', 'CSS', 'JavaScript'], true)) failRequest(422, 'Choose a supported language.');
        $code = $_POST['code_content'] ?? '';
        if (!is_string($code) || strlen($code) > MAX_PROJECT_FILE_BYTES || !preg_match('//u', $code) || str_contains($code, "\0")) failRequest(422, 'Code must be UTF-8 text up to 10 MB.');
        $upload = null;
        try {
            if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] !== UPLOAD_ERR_NO_FILE) $upload = storeUpload($_FILES['project_file'], 'projects');
            $stmt = $pdo->prepare('INSERT INTO projects (learner_id, title, description, code_content, file_path, extracted_path, language) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$learnerId, $title, $description, $code, $upload['file_path'] ?? null, $upload['extracted_path'] ?? null, $language]);
        } catch (Throwable $error) {
            if ($upload) removeStored($upload['cleanup_key']);
            throw $error;
        }
        checkAndAwardBadges($learnerId);
        flash('Project created successfully.');
        header('Location: projects.php'); exit;
    }
    if (in_array($action, ['delete', 'save_file'], true)) {
        $id = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND learner_id = ?');
        $stmt->execute([$id, $learnerId]);
        $project = $stmt->fetch();
        if (!$project) failRequest(404, 'Project not found.');
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM projects WHERE id = ? AND learner_id = ?')->execute([$id, $learnerId]);
            foreach (['file_path', 'extracted_path'] as $column) {
                if (!$project[$column]) continue;
                // Legacy uploads sometimes shared a timestamp folder. Do not delete another project's files.
                $other = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE $column = ?");
                $other->execute([$project[$column]]);
                if (!$other->fetchColumn()) removeStored($project[$column]);
            }
            flash('Project deleted.'); header('Location: projects.php'); exit;
        }
        $key = $_POST['file_path'] ?? '';
        $content = $_POST['file_content'] ?? '';
        if (!is_string($key) || !is_string($content) || strlen($content) > MAX_PROJECT_FILE_BYTES || str_contains($content, "\0") || !preg_match('//u', $content)) failRequest(422, 'Please save UTF-8 text up to 10 MB.');
        if (!in_array(strtolower(pathinfo($key, PATHINFO_EXTENSION)), EDITABLE_EXTENSIONS, true)) failRequest(422, 'This file type cannot be edited.');
        $target = ownedProjectFile($project, $key);
        $temporary = tempnam(dirname($target), '.save-');
        if (!$temporary) failRequest(500, 'Unable to save this file.');
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content) || !rename($temporary, $target)) throw new RuntimeException('Unable to save file.');
            $pdo->prepare('UPDATE projects SET updated_at = NOW() WHERE id = ? AND learner_id = ?')->execute([$id, $learnerId]);
        } finally { if (is_file($temporary)) unlink($temporary); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => 'File saved successfully.']); exit;
    }
    failRequest(400, 'Unknown project action.');
}
$projects = $pdo->prepare('SELECT * FROM projects WHERE learner_id = ? ORDER BY created_at DESC');
$projects->execute([$learnerId]);
$projects = $projects->fetchAll();
$pageTitle = 'My Projects'; $activePage = 'projects';
include '../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-code"></i> My Coding Projects</h2>
        <button class="btn btn-primary" onclick="openModal('createProjectModal')"><i class="fas fa-plus"></i> New Project</button>
    </div>
    <?php echo renderFlash(); ?>
    <?php foreach ($projects as $project): ?>
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-header">
            <div>
                <h3><?php echo h($project['title']); ?></h3>
                <span class="badge badge-info"><?php echo h($project['language']); ?></span>
                <small>Updated <?php echo h(date('M d, Y H:i', strtotime($project['updated_at']))); ?></small>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <?php $entry = projectEntry($project); if ($entry): ?>
                <button class="btn btn-sm btn-success preview-button" data-project-id="<?php echo (int) $project['id']; ?>" data-file-path="<?php echo h($entry); ?>"><i class="fas fa-play"></i> Run</button>
                <?php endif; ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                    <button class="btn btn-sm btn-danger" data-confirm="Delete this project?"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
        <p><?php echo nl2br(h($project['description'])); ?></p>
        <?php if ($project['file_path']): ?>
        <p style="margin:1rem 0;"><a class="btn btn-sm btn-primary" href="<?php echo h(fileUrl($project['file_path'], true)); ?>"><i class="fas fa-download"></i> Download Original</a></p>
        <?php endif; ?>
        <?php $files = projectFiles($project); foreach ($files as $key): ?>
        <div style="display:flex; gap:0.5rem; align-items:center; margin:0.5rem 0; flex-wrap:wrap;">
            <span><?php echo h(substr($key, strlen($project['extracted_path']))); ?></span>
            <?php if (in_array(strtolower(pathinfo($key, PATHINFO_EXTENSION)), EDITABLE_EXTENSIONS, true)): ?>
            <button class="btn btn-sm btn-success edit-button" data-project-id="<?php echo (int) $project['id']; ?>" data-file-path="<?php echo h($key); ?>">Edit</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($project['extracted_path'] && !$files): ?><p class="alert alert-warning">Project files are unavailable. Please ask an administrator to restore the uploaded files.</p><?php endif; ?>
        <?php if ($project['code_content']): ?><details><summary>View Quick Code Snippet</summary><pre style="overflow:auto;"><code><?php echo h($project['code_content']); ?></code></pre></details><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$projects): ?><p>No projects yet. Create your first one!</p><?php endif; ?>
</div>
<div class="modal" id="createProjectModal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header"><h2>Create New Project</h2><button class="modal-close" onclick="closeModal('createProjectModal')">&times;</button></div>
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="create">
            <div class="form-group"><label class="form-label" for="projectTitle">Project Title</label><input id="projectTitle" name="title" class="form-input" maxlength="200" required></div>
            <div class="form-group"><label class="form-label" for="projectDescription">Description</label><textarea id="projectDescription" name="description" class="form-textarea" maxlength="5000"></textarea></div>
            <div class="form-group"><label class="form-label" for="projectLanguage">Language</label><select id="projectLanguage" name="language" class="form-select"><option>HTML</option><option>CSS</option><option>JavaScript</option></select></div>
            <div class="form-group"><label class="form-label" for="projectFile">File or ZIP folder</label><input id="projectFile" type="file" name="project_file" class="form-input" accept=".zip,.html,.htm,.css,.js,.txt,.json"><small>Up to 20 MB per upload. ZIPs may contain up to 200 entries and 50 MB of extracted files. PHP and other executable server files are not supported.</small></div>
            <div class="form-group"><label class="form-label" for="projectCode">Quick Code Snippet</label><textarea id="projectCode" name="code_content" class="code-editor"></textarea></div>
            <button class="btn btn-primary">Save Project</button>
        </form>
    </div>
</div>
<div class="modal" id="editorModal">
    <div class="modal-content" style="max-width:900px; height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header"><h2 id="editorTitle">Edit File</h2><button class="modal-close" onclick="closeModal('editorModal')">&times;</button></div>
        <p id="editorStatus" role="status"></p><div id="editor" style="flex:1;"></div>
        <button class="btn btn-success" id="saveFileButton">Save Changes</button>
    </div>
</div>
<div class="modal" id="previewModal">
    <div class="modal-content" style="width:95vw; max-width:95vw; height:90vh; display:flex; flex-direction:column;">
        <div class="modal-header"><h2>Live Preview</h2><button class="modal-close" onclick="closeModal('previewModal')">&times;</button></div>
        <p id="previewStatus" role="status"></p>
        <iframe id="previewFrame" title="Project preview" sandbox="allow-scripts" referrerpolicy="no-referrer" style="flex:1; width:100%; border:none;"></iframe>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
<script>
const projectCsrf = <?php echo json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/assets/js/projects.js"></script>
<?php include '../includes/footer.php'; ?>
