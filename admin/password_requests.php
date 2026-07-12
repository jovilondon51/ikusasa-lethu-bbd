<?php
require_once '../includes/functions.php';
requireAdmin();

// Handle request
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $pdo->prepare("UPDATE password_change_requests SET status = 'approved', handled_by = ?, handled_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id'], $_GET['approve']]);
    // Also allow the learner to change password
    $req = $pdo->prepare("SELECT learner_id FROM password_change_requests WHERE id = ?");
    $req->execute([$_GET['approve']]);
    $learnerId = $req->fetchColumn();
    $pdo->prepare("UPDATE learners SET can_change_password = 1 WHERE id = ?")->execute([$learnerId]);
    header('Location: password_requests.php');
    exit;
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $pdo->prepare("UPDATE password_change_requests SET status = 'rejected', handled_by = ?, handled_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id'], $_GET['reject']]);
    header('Location: password_requests.php');
    exit;
}

$requests = $pdo->query("SELECT pcr.*, l.full_name as learner_name, l.username, a.full_name as handler_name
    FROM password_change_requests pcr
    JOIN learners l ON pcr.learner_id = l.id
    LEFT JOIN admins a ON pcr.handled_by = a.id
    ORDER BY pcr.requested_at DESC")->fetchAll();

$pageTitle = "Password Requests";
$activePage = "password_requests";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-key"></i> Password Change Requests</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Learner</th><th>Username</th><th>Requested</th><th>Status</th><th>Handled By</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?php echo htmlspecialchars($req['learner_name']); ?></td>
                    <td><?php echo htmlspecialchars($req['username']); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($req['requested_at'])); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $req['status'] === 'approved' ? 'success' : ($req['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                            <?php echo ucfirst($req['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($req['handler_name'] ?? '-'); ?></td>
                    <td>
                        <?php if ($req['status'] === 'pending'): ?>
                        <a href="?approve=<?php echo $req['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i></a>
                        <a href="?reject=<?php echo $req['id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($requests)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No requests</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>