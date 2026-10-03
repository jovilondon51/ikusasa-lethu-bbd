<?php
require_once '../includes/functions.php';
requireAdmin();

$today = date('Y-m-d');
$message = '';

// Mark attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $selectedDate = $_POST['date'] ?? '';
    if (!is_string($selectedDate) || !validDate($selectedDate) || $selectedDate > date('Y-m-d')) failRequest(422, 'Choose a valid attendance date that is not in the future.');
    $submitted = $_POST['attendance'] ?? [];
    $notes = $_POST['notes'] ?? [];
    if (!is_array($submitted) || !is_array($notes)) failRequest(422, 'Invalid attendance form.');
    $activeIds = array_map('intval', $pdo->query("SELECT id FROM learners WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN));
    $pdo->beginTransaction();
    try {
        foreach ($submitted as $learnerId => $status) {
            $note = $notes[$learnerId] ?? '';
            if (!ctype_digit((string) $learnerId) || !in_array((int) $learnerId, $activeIds, true) || !in_array($status, ['present', 'absent', 'late'], true) || !is_string($note) || strlen($note) > 1000) throw new InvalidArgumentException('Invalid attendance entry.');
            $stmt = $pdo->prepare("INSERT INTO attendance (learner_id, attendance_date, status, marked_by, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), notes = VALUES(notes)");
            $stmt->execute([$learnerId, $selectedDate, $status, $_SESSION['user_id'], $note]);
        }
        foreach ($activeIds as $learnerId) checkAndAwardBadges($learnerId);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Attendance saved!</div>';
}

$date = $_GET['date'] ?? $today;
if (!is_string($date) || !validDate($date)) failRequest(422, 'Choose a valid date.');
$learners = $pdo->query("SELECT * FROM learners WHERE status = 'active' ORDER BY full_name")->fetchAll();

// Get existing attendance for date
$attendanceMap = [];
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE attendance_date = ?");
$stmt->execute([$date]);
foreach ($stmt->fetchAll() as $row) {
    $attendanceMap[$row['learner_id']] = $row;
}

$pageTitle = "Attendance";
$activePage = "attendance";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-clipboard-check"></i> Mark Attendance</h2>
        <a href="attendance_calendar.php" class="btn btn-sm btn-primary"><i class="fas fa-calendar-alt"></i> Calendar View</a>
    </div>
    
    <form method="GET" style="margin-bottom:1.5rem;">
        <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <input type="date" name="date" value="<?php echo h($date); ?>" class="form-input" style="width:auto;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-calendar"></i> Select Date</button>
        </div>
    </form>

    <form method="POST" action="">
            <?php echo csrfField(); ?>
        <input type="hidden" name="date" value="<?php echo h($date); ?>">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Learner</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($learners as $learner): 
                        $current = $attendanceMap[$learner['id']] ?? null;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($learner['full_name']); ?></td>
                        <td>
                            <select name="attendance[<?php echo $learner['id']; ?>]" class="form-select" style="width:auto;">
                                <option value="present" <?php echo ($current && $current['status'] === 'present') ? 'selected' : ''; ?>>Present</option>
                                <option value="absent" <?php echo ($current && $current['status'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                <option value="late" <?php echo ($current && $current['status'] === 'late') ? 'selected' : ''; ?>>Late</option>
                            </select>
                        </td>
                        <td><input type="text" name="notes[<?php echo $learner['id']; ?>]" class="form-input" value="<?php echo htmlspecialchars($current['notes'] ?? ''); ?>" placeholder="Optional notes"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="mark_attendance" class="btn btn-success" style="margin-top:1rem;">
            <i class="fas fa-save"></i> Save Attendance
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

