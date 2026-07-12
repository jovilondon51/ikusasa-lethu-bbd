<?php
require_once '../includes/functions.php';
requireAdmin();

$filterLearner = $_GET['learner'] ?? null;

$learners = $pdo->query("SELECT id, full_name FROM learners WHERE status = 'active' ORDER BY full_name")->fetchAll();

if ($filterLearner && is_numeric($filterLearner)) {
    $stmt = $pdo->prepare("SELECT p.*, l.full_name as learner_name, l.username, l.grade, l.profile_picture 
        FROM projects p 
        JOIN learners l ON p.learner_id = l.id 
        WHERE p.learner_id = ? 
        ORDER BY p.created_at DESC");
    $stmt->execute([$filterLearner]);
} else {
    $stmt = $pdo->query("SELECT p.*, l.full_name as learner_name, l.username, l.grade, l.profile_picture 
        FROM projects p 
        JOIN learners l ON p.learner_id = l.id 
        ORDER BY p.created_at DESC");
}
$projects = $stmt->fetchAll();

$pageTitle = "Learner Projects";
$activePage = "projects";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-code-branch"></i> Learner Projects</h2>
        <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
            <select name="learner" class="form-select" style="width:auto;">
                <option value="">All Learners</option>
                <?php foreach ($learners as $l): ?>
                <option value="<?php echo $l['id']; ?>" <?php echo $filterLearner == $l['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($l['full_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($filterLearner): ?>
            <a href="projects.php" class="btn btn-sm btn-warning"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($projects)): ?>
<div class="card">
    <p style="text-align:center; color:var(--text-muted); padding:2rem;">No projects found.</p>
</div>
<?php endif; ?>

<?php foreach ($projects as $project): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-header">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <?php if ($project['profile_picture']): ?>
                <img src="../<?php echo $project['profile_picture']; ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
            <?php else: ?>
                <div style="width:40px; height:40px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700;">
                    <?php echo strtoupper(substr($project['learner_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div>
                <h3 style="margin-bottom:0.15rem; font-size:1.1rem;"><?php echo htmlspecialchars($project['title']); ?></h3>
                <p style="margin:0; color:var(--text-muted); font-size:0.85rem;">
                    <strong><?php echo htmlspecialchars($project['learner_name']); ?></strong> 
                    (@<?php echo htmlspecialchars($project['username']); ?>) 
                    • Grade: <?php echo htmlspecialchars($project['grade']); ?>
                    • <span class="badge badge-info"><?php echo htmlspecialchars($project['language']); ?></span>
                    <?php if ($project['extracted_path']): ?>
                        <span class="badge badge-success"><i class="fas fa-folder-open"></i> Has Files</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <span style="color:var(--text-muted); font-size:0.85rem;">
            <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
        </span>
    </div>
    
    <?php if ($project['description']): ?>
        <p style="margin-bottom:1rem; color:var(--text-muted);"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
    <?php endif; ?>
    
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem;">
        <?php if ($project['file_path']): ?>
            <a href="../<?php echo $project['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary" download>
                <i class="fas fa-download"></i> Download Original
            </a>
        <?php endif; ?>
    </div>
    
    <?php if ($project['extracted_path'] && is_dir('../' . $project['extracted_path'])): ?>
        <div style="margin-bottom:1rem; padding:1rem; background:var(--bg); border-radius:8px; border:1px solid var(--border);">
            <h4 style="margin-bottom:0.75rem; font-size:0.95rem;"><i class="fas fa-folder-tree"></i> Extracted Files</h4>
            <?php
            if (!function_exists('listAdminFiles')) {
            function listAdminFiles($dir, $webBase) {
                $items = scandir($dir);
                echo '<ul style="list-style:none; padding-left:1rem; margin:0;">';
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $fullPath = $dir . '/' . $item;
                    $webPath = $webBase . '/' . $item;
                    
                    if (is_dir($fullPath)) {
                        echo '<li style="margin:0.25rem 0;"><i class="fas fa-folder" style="color:var(--warning);"></i> <strong>' . htmlspecialchars($item) . '</strong>';
                        listAdminFiles($fullPath, $webPath);
                        echo '</li>';
                    } else {
                        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                        $icon = in_array($ext, ['html', 'css', 'js']) ? 'fa-file-code' : 'fa-file';
                        echo '<li style="margin:0.25rem 0;"><i class="fas ' . $icon . '" style="color:var(--primary);"></i> ' . htmlspecialchars($item) . '</li>';
                    }
                }
                echo '</ul>';
            }
            }
            
            $baseDir = '../' . $project['extracted_path'];
            $webBase = $project['extracted_path'];
            listAdminFiles($baseDir, $webBase);
            ?>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>