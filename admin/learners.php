<?php
require_once '../includes/functions.php';
requireAdmin();

$message = '';

// Add learner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $fullName = requireText('full_name', 150);
    $email = requireText('email', 254);
    $username = requireText('username', 100);
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || !validPassword($password)) failRequest(422, 'Enter a valid email and a password between 12 and 72 bytes.');
    $grade = requireText('grade', 50);
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO learners (full_name, email, username, password_hash, grade, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    try {
        $stmt->execute([$fullName, $email, $username, $hash, $grade, $_SESSION['user_id']]);
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Learner added successfully!</div>';
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1062) throw $e;
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error: Username or email already exists</div>';
    }
}

// Remove learner
if (isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    revokeAccount('learner', (int) $_POST['delete']);
    $stmt = $pdo->prepare("DELETE FROM learners WHERE id = ?");
    $stmt->execute([$_POST['delete']]);
    header('Location: learners.php');
    exit;
}

// Toggle status
if (isset($_POST['toggle']) && is_numeric($_POST['toggle'])) {
    revokeAccount('learner', (int) $_POST['toggle']);
    $stmt = $pdo->prepare("UPDATE learners SET status = IF(status='active','inactive','active') WHERE id = ?");
    $stmt->execute([$_POST['toggle']]);
    header('Location: learners.php');
    exit;
}

$learners = $pdo->query("SELECT l.*, a.full_name as admin_name 
    FROM learners l 
    LEFT JOIN admins a ON l.created_by = a.id 
    ORDER BY l.created_at DESC")->fetchAll();

$pageTitle = "Manage Learners";
$activePage = "learners";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-users"></i> Learners</h2>
        <button class="btn btn-primary" onclick="openModal('addLearnerModal')">
            <i class="fas fa-plus"></i> Add Learner
        </button>
    </div>
    <?php echo $message; ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Grade</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($learners as $learner): ?>
                <tr>
                    <td><?php echo htmlspecialchars($learner['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($learner['username']); ?></td>
                    <td><?php echo htmlspecialchars($learner['email']); ?></td>
                    <td><?php echo htmlspecialchars($learner['grade']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $learner['status'] === 'active' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($learner['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($learner['admin_name']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;"><?php echo csrfField(); ?><input type="hidden" name="toggle" value="<?php echo $learner['id']; ?>"><button type="submit" class="btn btn-sm btn-warning" title="Toggle Status">
                            <i class="fas fa-toggle-on"></i>
                        </button></form>
                        <form method="POST" style="display:inline;"><?php echo csrfField(); ?><input type="hidden" name="delete" value="<?php echo $learner['id']; ?>"><button type="submit" class="btn btn-sm btn-danger" data-confirm="Are you sure?" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($learners)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No learners yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Learner Modal -->
<div class="modal" id="addLearnerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Learner</h2>
            <button class="modal-close" onclick="closeModal('addLearnerModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input minlength="12" maxlength="72" type="password" name="password" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Grade/Level</label>
                <input type="text" name="grade" class="form-input" placeholder="e.g. Grade 10">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Learner</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
