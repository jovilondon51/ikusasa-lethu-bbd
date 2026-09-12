<?php
require_once '../includes/functions.php';
requireAdmin();

$month = $_GET['month'] ?? date('Y-m');
$learnerId = $_GET['learner'] ?? null;

// Get all active learners
$learners = $pdo->query("SELECT * FROM learners WHERE status = 'active' ORDER BY full_name")->fetchAll();

// Get attendance for selected month
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

if ($learnerId) {
    $attendance = $pdo->prepare("SELECT * FROM attendance WHERE learner_id = ? AND attendance_date BETWEEN ? AND ?");
    $attendance->execute([$learnerId, $startDate, $endDate]);
} else {
    $attendance = $pdo->prepare("SELECT a.*, l.full_name as learner_name, l.profile_picture 
        FROM attendance a 
        JOIN learners l ON a.learner_id = l.id 
        WHERE a.attendance_date BETWEEN ? AND ? 
        ORDER BY a.attendance_date DESC");
    $attendance->execute([$startDate, $endDate]);
}
$attendance = $attendance->fetchAll();

// Build calendar
$daysInMonth = date('t', strtotime($startDate));
$firstDay = date('N', strtotime($startDate)); // 1=Monday, 7=Sunday
$attendanceMap = [];
foreach ($attendance as $row) {
    $day = date('j', strtotime($row['attendance_date']));
    if (!isset($attendanceMap[$day])) $attendanceMap[$day] = [];
    $attendanceMap[$day][] = $row;
}

// Prev/Next month
$prevMonth = date('Y-m', strtotime($month . ' -1 month'));
$nextMonth = date('Y-m', strtotime($month . ' +1 month'));

$pageTitle = "Attendance Calendar";
$activePage = "attendance";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Attendance Calendar</h2>
        <div style="display:flex; gap:0.5rem;">
            <a href="attendance.php" class="btn btn-sm btn-primary"><i class="fas fa-list"></i> List View</a>
        </div>
    </div>
    
    <div style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center;">
        <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
            <input type="month" name="month" value="<?php echo $month; ?>" class="form-input" style="width:auto;">
            <select name="learner" class="form-select" style="width:auto;">
                <option value="">All Learners</option>
                <?php foreach ($learners as $l): ?>
                <option value="<?php echo $l['id']; ?>" <?php echo $learnerId == $l['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($l['full_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> View</button>
        </form>
        <div style="margin-left:auto; display:flex; gap:1rem; font-size:0.85rem;">
            <span><span style="display:inline-block; width:12px; height:12px; background:var(--secondary); border-radius:50%; margin-right:0.25rem;"></span> Present</span>
            <span><span style="display:inline-block; width:12px; height:12px; background:var(--danger); border-radius:50%; margin-right:0.25rem;"></span> Absent</span>
            <span><span style="display:inline-block; width:12px; height:12px; background:var(--warning); border-radius:50%; margin-right:0.25rem;"></span> Late</span>
        </div>
    </div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <a href="?month=<?php echo $prevMonth; ?>&learner=<?php echo $learnerId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-chevron-left"></i> Prev</a>
        <h3 style="font-weight:600;"><?php echo date('F Y', strtotime($startDate)); ?></h3>
        <a href="?month=<?php echo $nextMonth; ?>&learner=<?php echo $learnerId; ?>" class="btn btn-sm btn-primary">Next <i class="fas fa-chevron-right"></i></a>
    </div>
    
    <div class="calendar-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:0.5rem; text-align:center; font-weight:600; margin-bottom:0.5rem; color:var(--text-muted);">
        <div class="calendar-weekday">Mon</div><div class="calendar-weekday">Tue</div><div class="calendar-weekday">Wed</div><div class="calendar-weekday">Thu</div><div class="calendar-weekday">Fri</div><div class="calendar-weekday">Sat</div><div class="calendar-weekday">Sun</div>
    </div>
    
    <div class="calendar-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:0.5rem;">
        <?php 
        // Empty cells before first day
        for ($i = 1; $i < $firstDay; $i++) {
            echo '<div style="min-height:80px;"></div>';
        }
        
        for ($day = 1; $day <= $daysInMonth; $day++): 
            $hasData = isset($attendanceMap[$day]);
            $dayAttendance = $attendanceMap[$day] ?? [];
        ?>
        <div class="calendar-cell" style="min-height:80px; border:1px solid var(--border); border-radius:8px; padding:0.5rem; background:var(--card-bg);">
            <div style="font-weight:600; margin-bottom:0.25rem; <?php echo date('j') == $day && date('Y-m') == $month ? 'color:var(--primary);' : ''; ?>">
                <?php echo $day; ?>
            </div>
            <?php if ($hasData): ?>
                <?php if ($learnerId): ?>
                    <?php 
                    $status = $dayAttendance[0]['status'] ?? 'absent';
                    $color = $status === 'present' ? 'var(--secondary)' : ($status === 'late' ? 'var(--warning)' : 'var(--danger)');
                    ?>
                    <span class="badge" style="background:<?php echo $color; ?>20; color:<?php echo $color; ?>; font-size:0.7rem;">
                        <?php echo ucfirst($status); ?>
                    </span>
                <?php else: ?>
                    <div style="display:flex; flex-wrap:wrap; gap:0.15rem; justify-content:center;">
                        <?php foreach (array_slice($dayAttendance, 0, 3) as $record): 
                            $color = $record['status'] === 'present' ? 'var(--secondary)' : ($record['status'] === 'late' ? 'var(--warning)' : 'var(--danger)');
                        ?>
                        <span title="<?php echo htmlspecialchars($record['learner_name']); ?> - <?php echo $record['status']; ?>" 
                              style="width:8px; height:8px; border-radius:50%; background:<?php echo $color; ?>; display:inline-block;"></span>
                        <?php endforeach; ?>
                        <?php if (count($dayAttendance) > 3): ?>
                            <span style="font-size:0.6rem; color:var(--text-muted);">+<?php echo count($dayAttendance) - 3; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
