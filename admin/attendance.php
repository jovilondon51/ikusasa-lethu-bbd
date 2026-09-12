<?php
require_once '../includes/functions.php';
requireAdmin();

$today = date('Y-m-d');
$message = '';

// Mark attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    foreach ($_POST['attendance'] as $learnerId => $status) {
        $stmt = $pdo->prepare("INSERT INTO attendance (learner_id, attendance_date, status, marked_by, notes) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), notes = VALUES(notes)");
        $stmt->execute([$learnerId, $_POST['date'], $status, $_SESSION['user_id'], $_POST['notes'][$learnerId] ?? '']);
    }
    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Attendance saved!</div>';
}

$date = $_GET['date'] ?? $today;
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
            <input type="date" name="date" value="<?php echo $date; ?>" class="form-input" style="width:auto;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-calendar"></i> Select Date</button>
        </div>
    </form>

    <form method="POST" action="">
        <input type="hidden" name="date" value="<?php echo $date; ?>">
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
