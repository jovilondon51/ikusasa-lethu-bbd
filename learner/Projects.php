<?php
require_once '../includes/functions.php';
requireLearner();

$learnerId = $_SESSION['user_id'];
$message = '';

// Define listFiles once at the top
if (!function_exists('listFiles')) {
    function listFiles($dir, $basePath, $projectId, $webBase) {
        $items = scandir($dir);
        echo '<ul style="list-style:none; padding-left:1rem; margin:0;">';
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $dir . '/' . $item;
            $relativePath = str_replace($basePath, '', $fullPath);
            $webPath = $webBase . $relativePath;
            
            if (is_dir($fullPath)) {
                echo '<li style="margin:0.25rem 0;"><i class="fas fa-folder" style="color:var(--warning);"></i> <strong>' . htmlspecialchars($item) . '</strong>';
                listFiles($fullPath, $basePath, $projectId, $webBase);
                echo '</li>';
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $isEditable = in_array($ext, ['html', 'css', 'js', 'txt', 'php']);
                $icon = $isEditable ? 'fa-file-code' : 'fa-file';
                echo '<li style="margin:0.25rem 0; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">';
                echo '<i class="fas ' . $icon . '" style="color:var(--primary);"></i> ';
                echo '<span>' . htmlspecialchars($item) . '</span>';
                
                if ($isEditable) {
                    echo '<button class="btn btn-sm btn-success" onclick="openEditor(\'' . htmlspecialchars($webPath) . '\', ' . $projectId . ', \'' . htmlspecialchars($item) . '\')">';
                    echo '<i class="fas fa-edit"></i> Edit';
                    echo '</button>';
                }
                
                echo '</li>';
            }
        }
        echo '</ul>';
    }
}

// Create project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $filePath = null;
    $extractedPath = null;
    
    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === 0) {
        $uploadDir = '../uploads/projects/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $originalName = basename($_FILES['project_file']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $fullPath = $uploadDir . $safeName;
        
        if (move_uploaded_file($_FILES['project_file']['tmp_name'], $fullPath)) {
            $filePath = 'uploads/projects/' . $safeName;
            
            if ($ext === 'zip') {
                $extractDir = $uploadDir . 'extracted_' . time() . '/';
                $zip = new ZipArchive();
                if ($zip->open($fullPath) === TRUE) {
                    $zip->extractTo($extractDir);
                    $zip->close();
                    $extractedPath = 'uploads/projects/extracted_' . time() . '/';
                }
            } elseif (in_array($ext, ['html', 'css', 'js'])) {
                $extractDir = $uploadDir . 'extracted_' . time() . '/';
                mkdir($extractDir, 0777, true);
                copy($fullPath, $extractDir . $originalName);
                $extractedPath = 'uploads/projects/extracted_' . time() . '/';
            }
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO projects (learner_id, title, description, code_content, file_path, extracted_path, language) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $learnerId, 
        trim($_POST['title']), 
        trim($_POST['description']), 
        $_POST['code_content'], 
        $filePath, 
        $extractedPath,
        $_POST['language']
    ]);
    
    if (function_exists('checkAndAwardBadges')) {
        checkAndAwardBadges($learnerId);
    }
    
    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Project created successfully!</div>';
}

// Delete project
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $file = $pdo->prepare("SELECT file_path, extracted_path FROM projects WHERE id = ? AND learner_id = ?");
    $file->execute([$_GET['delete'], $learnerId]);
    $result = $file->fetch();
    
    if ($result['file_path'] && file_exists('../' . $result['file_path'])) {
        unlink('../' . $result['file_path']);
    }
    if ($result['extracted_path'] && is_dir('../' . $result['extracted_path'])) {
        $dir = '../' . $result['extracted_path'];
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()) rmdir($file->getRealPath());
            else unlink($file->getRealPath());
        }
        rmdir($dir);
    }
    
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND learner_id = ?");
    $stmt->execute([$_GET['delete'], $learnerId]);
    header('Location: projects.php');
    exit;
}

// Save edited file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_file') {
    $fileToEdit = $_POST['file_path'];
    $projectId = $_POST['project_id'];
    
    $project = $pdo->prepare("SELECT extracted_path FROM projects WHERE id = ? AND learner_id = ?");
    $project->execute([$projectId, $learnerId]);
    $proj = $project->fetch();
    
    if ($proj && $proj['extracted_path']) {
        $basePath = realpath('../' . $proj['extracted_path']);
        $targetFile = realpath('../' . $fileToEdit);
        
        if ($targetFile && strpos($targetFile, $basePath) === 0) {
            file_put_contents($targetFile, $_POST['file_content']);
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> File saved!</div>';
        }
    }
}

$projects = $pdo->prepare("SELECT * FROM projects WHERE learner_id = ? ORDER BY created_at DESC");
$projects->execute([$learnerId]);
$projects = $projects->fetchAll();

$pageTitle = "My Projects";
$activePage = "projects";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-code"></i> My Coding Projects</h2>
        <button class="btn btn-primary" onclick="openModal('createProjectModal')">
            <i class="fas fa-plus"></i> New Project
        </button>
    </div>
    <?php echo $message; ?>
    
    <?php foreach ($projects as $project): ?>
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-header">
            <div>
                <h3 style="margin-bottom:0.25rem;"><?php echo htmlspecialchars($project['title']); ?></h3>
                <span class="badge badge-info"><?php echo htmlspecialchars($project['language']); ?></span>
                <span style="color:var(--text-muted); font-size:0.85rem; margin-left:0.5rem;">
                    <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                </span>
                <?php if ($project['extracted_path']): ?>
                    <span class="badge badge-success"><i class="fas fa-folder-open"></i> Has Files</span>
                <?php endif; ?>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <?php if ($project['extracted_path'] && file_exists('../' . $project['extracted_path'])): 
                    $previewFile = null;
                    $files = glob('../' . $project['extracted_path'] . '*');
                    foreach ($files as $f) {
                        if (is_file($f) && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'html') {
                            $previewFile = $project['extracted_path'] . basename($f);
                            break;
                        }
                    }
                    if (!$previewFile && count($files) > 0) {
                        foreach ($files as $f) {
                            if (is_file($f)) {
                                $previewFile = $project['extracted_path'] . basename($f);
                                break;
                            }
                        }
                    }
                    if ($previewFile):
                ?>
                    <button class="btn btn-sm btn-success" onclick="openPreview('<?php echo htmlspecialchars($previewFile); ?>')">
                        <i class="fas fa-play"></i> Run
                    </button>
                <?php endif; endif; ?>
                <a href="?delete=<?php echo $project['id']; ?>" class="btn btn-sm btn-danger" data-confirm="Delete this project?">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
        
        <?php if ($project['description']): ?>
            <p style="margin-bottom:1rem; color:var(--text-muted);"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
        <?php endif; ?>
        
        <?php if ($project['file_path']): ?>
            <div style="margin-bottom:1rem; padding:1rem; background:var(--bg); border-radius:8px; border:1px solid var(--border);">
                <p style="margin-bottom:0.5rem; font-weight:500;"><i class="fas fa-paperclip"></i> Original File:</p>
                <a href="../<?php echo $project['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary" download>
                    <i class="fas fa-download"></i> Download
                </a>
                <span style="color:var(--text-muted); font-size:0.8rem; margin-left:0.5rem;">
                    <?php echo basename($project['file_path']); ?>
                </span>
            </div>
        <?php endif; ?>
        
        <?php if ($project['extracted_path'] && is_dir('../' . $project['extracted_path'])): ?>
            <div style="margin-bottom:1rem;">
                <h4 style="margin-bottom:0.75rem; font-size:0.95rem;"><i class="fas fa-folder-tree"></i> Project Files</h4>
                <?php
                $baseDir = '../' . $project['extracted_path'];
                $webBase = $project['extracted_path'];
                listFiles($baseDir, $baseDir, $project['id'], $webBase);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($project['code_content']): ?>
            <details style="margin-top:0.5rem;">
                <summary style="cursor:pointer; color:var(--primary); font-weight:500; margin-bottom:0.5rem;">
                    <i class="fas fa-code"></i> View Quick Code Snippet
                </summary>
                <pre style="background:#1e1e1e; color:#d4d4d4; padding:1rem; border-radius:8px; overflow-x:auto;"><code><?php echo htmlspecialchars($project['code_content']); ?></code></pre>
            </details>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($projects)): ?>
        <p style="text-align:center; color:var(--text-muted); padding:2rem;">No projects yet. Create your first one!</p>
    <?php endif; ?>
</div>

<!-- Create Project Modal -->
<div class="modal" id="createProjectModal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h2>Create New Project</h2>
            <button class="modal-close" onclick="closeModal('createProjectModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label class="form-label">Project Title *</label>
                <input type="text" name="title" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" placeholder="What is this project about?"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Language</label>
                <select name="language" class="form-select">
                    <option value="HTML">HTML</option>
                    <option value="CSS">CSS</option>
                    <option value="JavaScript">JavaScript</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Upload File or Zip Folder</label>
                <input type="file" name="project_file" class="form-input" accept=".zip,.html,.css,.js,.txt">
                <small style="color:var(--text-muted);">
                    Upload a <strong>.zip folder</strong> (auto-extracted) or individual <strong>.html, .css, .js</strong> files<br>
                    Single files will be placed in their own folder for editing
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Quick Code Snippet (optional)</label>
                <textarea name="code_content" class="code-editor" placeholder="Paste a quick code snippet here..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Project</button>
        </form>
    </div>
</div>

<!-- Code Editor Modal -->
<div class="modal" id="editorModal">
    <div class="modal-content" style="max-width:900px; height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <h2 id="editorTitle"><i class="fas fa-code"></i> Edit File</h2>
            <button class="modal-close" onclick="closeModal('editorModal')">&times;</button>
        </div>
        <div style="flex:1; display:flex; flex-direction:column; gap:1rem;">
            <div id="editor" style="flex:1; border:1px solid var(--border); border-radius:8px;"></div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button class="btn btn-success" onclick="saveFile()"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Live Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content" style="max-width:95vw; width:95vw; height:90vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <h2><i class="fas fa-play"></i> Live Preview</h2>
            <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div style="flex:1; border:1px solid var(--border); border-radius:8px; overflow:hidden;">
            <iframe id="previewFrame" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

<!-- Monaco Editor CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
<script>
let editor = null;
let currentFilePath = '';
let currentProjectId = '';

require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});

function openEditor(filePath, projectId, fileName) {
    currentFilePath = filePath;
    currentProjectId = projectId;
    document.getElementById('editorTitle').innerHTML = '<i class="fas fa-code"></i> ' + fileName;
    openModal('editorModal');
    
    const ext = fileName.split('.').pop().toLowerCase();
    const langMap = { 'html': 'html', 'css': 'css', 'js': 'javascript', 'php': 'php', 'txt': 'plaintext' };
    const language = langMap[ext] || 'plaintext';
    
    fetch('../' + filePath)
        .then(response => response.text())
        .then(content => {
            require(['vs/editor/editor.main'], function() {
                if (editor) editor.dispose();
                
                editor = monaco.editor.create(document.getElementById('editor'), {
                    value: content,
                    language: language,
                    theme: document.body.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs',
                    automaticLayout: true,
                    minimap: { enabled: false },
                    fontSize: 14,
                    scrollBeyondLastLine: false,
                    roundedSelection: false,
                    padding: { top: 16 }
                });
            });
        });
}

function saveFile() {
    if (!editor) return;
    
    const content = editor.getValue();
    
    fetch('projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save_file&project_id=' + encodeURIComponent(currentProjectId) 
              + '&file_path=' + encodeURIComponent(currentFilePath) 
              + '&file_content=' + encodeURIComponent(content)
    })
    .then(response => response.text())
    .then(() => {
        alert('File saved successfully!');
        closeModal('editorModal');
        location.reload();
    })
    .catch(err => {
        alert('Error saving file: ' + err);
    });
}

function openPreview(filePath) {
    openModal('previewModal');
    document.getElementById('previewFrame').src = '../' + filePath;
}

document.getElementById('themeToggle')?.addEventListener('click', function() {
    if (editor) {
        const theme = document.body.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
        monaco.editor.setTheme(theme);
    }
});
</script>

<?php include '../includes/footer.php'; ?>