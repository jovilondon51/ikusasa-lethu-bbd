<?php
require_once '../includes/functions.php';
requireAdmin();

// Attendance stats by month
$attendanceStats = $pdo->query("SELECT 
    DATE_FORMAT(attendance_date, '%Y-%m') as month,
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
    COUNT(*) as total
    FROM attendance 
    GROUP BY DATE_FORMAT(attendance_date, '%Y-%m')
    ORDER BY month DESC LIMIT 6")->fetchAll();

// Content progress
$progressStats = $pdo->query("SELECT 
    l.full_name,
    COUNT(DISTINCT cp.content_id) as completed_content,
    (SELECT COUNT(*) FROM learning_content WHERE status = 'active') as total_content
    FROM learners l
    LEFT JOIN content_progress cp ON l.id = cp.learner_id AND cp.completed = 1
    WHERE l.status = 'active'
    GROUP BY l.id
    ORDER BY completed_content DESC
    LIMIT 10")->fetchAll();

$pageTitle = "Analytics";
$activePage = "analytics";
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-bar"></i> Attendance Analytics</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Month</th><th>Present</th><th>Absent</th><th>Late</th><th>Attendance Rate</th></tr>
            </thead>
            <tbody>
                <?php foreach ($attendanceStats as $stat): 
                    $rate = $stat['total'] > 0 ? round(($stat['present'] / $stat['total']) * 100) : 0;
                ?>
                <tr>
                    <td><?php echo $stat['month']; ?></td>
                    <td><?php echo $stat['present']; ?></td>
                    <td><?php echo $stat['absent']; ?></td>
                    <td><?php echo $stat['late']; ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="progress-bar" style="flex:1;">
                                <div class="progress-fill" style="width:<?php echo $rate; ?>%; background:<?php echo $rate >= 80 ? 'var(--secondary)' : ($rate >= 50 ? 'var(--warning)' : 'var(--danger)'); ?>"></div>
                            </div>
                            <span style="min-width:40px; font-weight:600;"><?php echo $rate; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attendanceStats)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No attendance data yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-trophy"></i> Content Progress (Top Learners)</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Learner</th><th>Completed</th><th>Total</th><th>Progress</th></tr>
            </thead>
            <tbody>
                <?php foreach ($progressStats as $prog): 
                    $pct = $prog['total_content'] > 0 ? round(($prog['completed_content'] / $prog['total_content']) * 100) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($prog['full_name']); ?></td>
                    <td><?php echo $prog['completed_content']; ?></td>
                    <td><?php echo $prog['total_content']; ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="progress-bar" style="flex:1;">
                                <div class="progress-fill" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <span style="min-width:40px; font-weight:600;"><?php echo $pct; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($progressStats)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No progress data yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>