<?php
require_once '../includes/functions.php';
requireAdmin();

$message = '';

// Add admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (full_name, email, username, password_hash) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$fullName, $email, $username, $hash]);
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Staff member added successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error: Username or email already exists</div>';
    }
}
// Reset admin password
if (isset($_GET['reset']) && is_numeric($_GET['reset'])) {
    $newPass = 'BBD_' . bin2hex(random_bytes(4)); // e.g., BBD_a3f7b2d1
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$hash, $_GET['reset']]);
    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Password reset! Temporary password: <strong>' . $newPass . '</strong> (Copy this now!)</div>';
}

// Delete admin (can't delete yourself)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_GET['delete'] != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header('Location: staff.php');
        exit;
    } else {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> You cannot delete your own account!</div>';
    }
}

$staff = $pdo->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll();

$pageTitle = "Manage Staff";
$activePage = "staff";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-shield-alt"></i> BBD Staff</h2>
        <button class="btn btn-primary" onclick="openModal('addStaffModal')">
            <i class="fas fa-plus"></i> Add Staff Member
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
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $member): ?>
                <tr>
                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                    <td><strong>@<?php echo htmlspecialchars($member['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($member['created_at'])); ?></td>
                    <td>
    <?php if ($member['id'] == $_SESSION['user_id']): ?>
        <span class="badge badge-info">You</span>
    <?php else: ?>
        <a href="?reset=<?php echo $member['id']; ?>" class="btn btn-sm btn-warning" data-confirm="Reset password for <?php echo htmlspecialchars($member['full_name']); ?>? A temporary password will be generated." title="Reset Password">
            <i class="fas fa-key"></i>
        </a>
        <a href="?delete=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" data-confirm="Delete this staff member?">
            <i class="fas fa-trash"></i>
        </a>
    <?php endif; ?>
</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($staff)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No staff members yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="addStaffModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Staff Member</h2>
            <button class="modal-close" onclick="closeModal('addStaffModal')">&times;</button>
        </div>
        <form method="POST" action="">
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
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Staff</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>