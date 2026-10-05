<?php
require_once __DIR__ . '/includes/security.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['confirm'] ?? '') === '1') {
    require_once __DIR__ . '/includes/functions.php';
    $user = currentUser();
    if (!$user) {
        header('Location: /index.php', true, 303);
        exit;
    }
    $pageTitle = 'Confirm logout';
    $activePage = '';
    $dashboard = isAdmin() ? '/admin/dashboard.php' : '/learner/dashboard.php';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width:600px; margin:2rem auto;">
        <h2 style="margin-bottom:1rem;">Confirm logout</h2>
        <p style="margin-bottom:0.5rem;">Your sign-in changed or the previous form expired.</p>
        <p style="margin-bottom:1.5rem;">You are currently signed in as <strong><?php echo h($user['full_name']); ?></strong>. Would you like to log out of this browser?</p>
        <form method="POST" action="/logout.php">
            <?php echo csrfField(); ?>
            <button type="submit" class="btn btn-danger">Log out</button>
            <a class="btn btn-secondary" href="<?php echo h($dashboard); ?>">Stay signed in</a>
        </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') failRequest(405, 'Please use the Logout button.');
destroyLogin();
header('Location: /index.php');
exit;
