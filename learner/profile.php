<?php
require_once '../includes/functions.php';
requireLearner();

$learnerId = $_SESSION['user_id'];
$message = '';

$learner = currentUser();
// Profile photos are checked as actual images and stored outside the web root.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload = storeUpload($_FILES['profile_picture'], 'profiles');
    try { $pdo->prepare('UPDATE learners SET profile_picture = ? WHERE id = ?')->execute([$upload['file_path'], $learnerId]); }
    catch (Throwable $error) { removeStored($upload['cleanup_key']); throw $error; }
    if ($learner['profile_picture']) removeStored($learner['profile_picture']);
    flash('Profile picture updated.'); header('Location: profile.php'); exit;
}
if (isset($_POST['request_password_change'])) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM learners WHERE id = ? FOR UPDATE')->execute([$learnerId]);
        $pending = $pdo->prepare("SELECT id FROM password_change_requests WHERE learner_id = ? AND status = 'pending'");
        $pending->execute([$learnerId]);
        if (!$pending->fetch()) $pdo->prepare('INSERT INTO password_change_requests (learner_id) VALUES (?)')->execute([$learnerId]);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    flash('Your password-change request is pending.'); header('Location: profile.php'); exit;
}
if (isset($_POST['change_password'])) {
    $password = $_POST['new_password'] ?? '';
    if (!is_string($password) || !validPassword($password) || $password !== ($_POST['confirm_password'] ?? null)) failRequest(422, 'Use matching passwords between 12 and 72 bytes.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM learners WHERE id = ? FOR UPDATE'); $stmt->execute([$learnerId]); $learner = $stmt->fetch();
        if (!$learner['can_change_password']) throw new InvalidArgumentException('Please request approval before changing your password.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE learners SET password_hash = ?, can_change_password = 0 WHERE id = ?')->execute([$hash, $learnerId]);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    // Keep this session; other sessions are revoked by their credential-version check.
    $learner['password_hash'] = $hash;
    loginSession($learner, 'learner');
    flash('Password updated.'); header('Location: profile.php'); exit;
}

// Get learner's badges
$badges = $pdo->prepare("SELECT b.*, lb.earned_at FROM badges b 
    JOIN learner_badges lb ON b.id = lb.badge_id 
    WHERE lb.learner_id = ? ORDER BY lb.earned_at DESC");
$badges->execute([$learnerId]);
$badges = $badges->fetchAll();

$pageTitle = "My Profile";
$activePage = "profile";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-user"></i> My Profile</h2>
    </div>
    <?php echo renderFlash(); echo $message; ?>
    
    <div style="display:flex; align-items:center; gap:1.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div style="position:relative;">
            <?php if ($learner['profile_picture']): ?>
                <img src="<?php echo h(fileUrl($learner['profile_picture'])); ?>" alt="Profile" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid var(--primary);">
            <?php else: ?>
                <div style="width:100px; height:100px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; color:#fff; font-size:2.5rem; font-weight:700;">
                    <?php echo strtoupper(substr($learner['full_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="" enctype="multipart/form-data" style="margin-top:0.5rem;">
            <?php echo csrfField(); ?>
                <label class="btn btn-sm btn-primary" style="cursor:pointer;">
                    <i class="fas fa-camera"></i> Change Photo
                    <input type="file" name="profile_picture" accept="image/*" style="display:none;" onchange="this.form.submit()">
                </label>
            </form>
        </div>
        <div>
            <h3 style="margin-bottom:0.25rem;"><?php echo htmlspecialchars($learner['full_name']); ?></h3>
            <p style="color:var(--text-muted); margin-bottom:0.25rem;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($learner['email']); ?></p>
            <p style="color:var(--text-muted); margin-bottom:0.25rem;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($learner['username']); ?></p>
            <p style="color:var(--text-muted);"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($learner['grade']); ?></p>
        </div>
    </div>
    
    <?php if (!empty($badges)): ?>
    <div style="border-top:1px solid var(--border); padding-top:1.5rem; margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-medal"></i> My Badges</h3>
        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <?php foreach ($badges as $badge): ?>
            <div style="text-align:center; padding:1rem; background:var(--bg); border-radius:12px; border:1px solid var(--border); min-width:120px;">
                <i class="fas <?php echo $badge['icon']; ?>" style="font-size:2rem; color:<?php echo $badge['color']; ?>; margin-bottom:0.5rem;"></i>
                <p style="font-weight:600; font-size:0.9rem;"><?php echo htmlspecialchars($badge['name']); ?></p>
                <p style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($badge['description']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div style="border-top:1px solid var(--border); padding-top:1.5rem;">
        <h3 style="margin-bottom:1rem;">Password</h3>
        <?php if ($learner['can_change_password']): ?>
            <form method="POST" action="">
            <?php echo csrfField(); ?>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-input" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-success"><i class="fas fa-key"></i> Update Password</button>
            </form>
        <?php else: ?>
            <form method="POST" action="">
            <?php echo csrfField(); ?>
                <p style="color:var(--text-muted); margin-bottom:1rem;">You need admin approval to change your password.</p>
                <button type="submit" name="request_password_change" class="btn btn-warning">
                    <i class="fas fa-paper-plane"></i> Request Password Change
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
