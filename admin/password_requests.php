<?php
require_once '../includes/functions.php';
requireAdmin();

if (isset($_POST['approve']) || isset($_POST['reject'])) {
    $id = filter_var($_POST['approve'] ?? $_POST['reject'], FILTER_VALIDATE_INT);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM password_change_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$id]); $request = $stmt->fetch();
        if (!$request) throw new InvalidArgumentException('This request is no longer pending.');
        $status = isset($_POST['approve']) ? 'approved' : 'rejected';
        $pdo->prepare('UPDATE password_change_requests SET status = ?, handled_by = ?, handled_at = NOW() WHERE id = ?')->execute([$status, $_SESSION['user_id'], $id]);
        if ($status === 'approved') $pdo->prepare('UPDATE learners SET can_change_password = 1 WHERE id = ?')->execute([$request['learner_id']]);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    header('Location: password_requests.php'); exit;
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
                        <form method="POST" style="display:inline;"><?php echo csrfField(); ?><input type="hidden" name="approve" value="<?php echo $req['id']; ?>"><button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button></form>
                        <form method="POST" style="display:inline;"><?php echo csrfField(); ?><input type="hidden" name="reject" value="<?php echo $req['id']; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button></form>
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
