<?php
if (PHP_SAPI !== 'cli' || getenv('TEST_DATABASE') !== '1' || !str_ends_with(getenv('DB_NAME') ?: '', '_test') || !in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost'], true)) { fwrite(STDERR, "Fixture setup requires an isolated local *_test database.\n"); exit(1); }
require_once __DIR__ . '/../config/database.php';
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['admins', 'learners', 'projects', 'learning_content', 'content_progress', 'attendance', 'badges', 'learner_badges', 'password_change_requests', 'community_messages', 'settings', 'login_logs'] as $table) $pdo->exec("TRUNCATE TABLE $table");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$password = password_hash('Test-only-pass123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO admins (full_name, email, username, password_hash) VALUES (?, ?, ?, ?)');
$stmt->execute(['Test Admin', 'admin@example.invalid', 'test_admin', $password]);
$stmt->execute(['Other Admin', 'otheradmin@example.invalid', 'other_admin', $password]);
$stmt = $pdo->prepare('INSERT INTO learners (full_name, email, username, password_hash, grade, created_by) VALUES (?, ?, ?, ?, ?, 1)');
$stmt->execute(['Test Learner', 'learner@example.invalid', 'test_learner', $password, '10']);
$stmt->execute(['Other Learner', 'other@example.invalid', 'other_learner', $password, '11']);
// A missing past mark breaks a streak; future marks must never count.
$pdo->exec("INSERT INTO attendance (learner_id, attendance_date, status, marked_by) VALUES (2, DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'present', 1), (1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'present', 1), (2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'present', 1)");
$pdo->exec("INSERT INTO learning_content (title, description, content_type, content_text, status, created_by) VALUES ('Active lesson', 'Active', 'text', 'Hello', 'active', 1), ('Inactive lesson', 'Inactive', 'text', 'Old', 'inactive', 1)");
$pdo->exec('INSERT INTO content_progress (learner_id, content_id, completed, progress_percent) VALUES (1, 2, 1, 100)');
$pdo->exec("INSERT INTO settings VALUES ('community_mode', 'everyone')");
$pdo->exec("INSERT INTO badges (name, description, icon, color, criteria_type, criteria_value) VALUES ('Streak', 'Three consecutive sessions', 'fa-medal', '#00aabb', 'attendance_streak', 3)");
echo "Isolated test fixtures created.\n";
