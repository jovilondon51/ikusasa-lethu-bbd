-- Clean schema for NEW databases only. Do not import over the existing Aiven database.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(150) NOT NULL,
 email VARCHAR(254) NOT NULL UNIQUE, username VARCHAR(100) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS learners (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(150) NOT NULL,
 email VARCHAR(254) NOT NULL UNIQUE, username VARCHAR(100) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, grade VARCHAR(50) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', profile_picture VARCHAR(1024) DEFAULT NULL,
 can_change_password BOOLEAN NOT NULL DEFAULT FALSE, created_by INT UNSIGNED DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS projects (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_id INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL, description TEXT NOT NULL, code_content MEDIUMTEXT NOT NULL,
 file_path VARCHAR(1024) DEFAULT NULL, extracted_path VARCHAR(1024) DEFAULT NULL,
 language VARCHAR(20) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX (learner_id, created_at), FOREIGN KEY (learner_id) REFERENCES learners(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS learning_content (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL,
 description TEXT NOT NULL, content_type ENUM('text','video','pdf','document','link') NOT NULL,
 content_text MEDIUMTEXT NOT NULL, file_path VARCHAR(1024) DEFAULT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_by INT UNSIGNED DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS content_progress (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_id INT UNSIGNED NOT NULL,
 content_id INT UNSIGNED NOT NULL, progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
 completed BOOLEAN NOT NULL DEFAULT FALSE, last_accessed TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY learner_content (learner_id, content_id),
 FOREIGN KEY (learner_id) REFERENCES learners(id) ON DELETE CASCADE,
 FOREIGN KEY (content_id) REFERENCES learning_content(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS attendance (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_id INT UNSIGNED NOT NULL,
 attendance_date DATE NOT NULL, status ENUM('present','absent','late') NOT NULL,
 marked_by INT UNSIGNED DEFAULT NULL, notes VARCHAR(1000) NOT NULL DEFAULT '',
 UNIQUE KEY learner_date (learner_id, attendance_date), INDEX (attendance_date),
 FOREIGN KEY (learner_id) REFERENCES learners(id) ON DELETE CASCADE,
 FOREIGN KEY (marked_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS badges (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE,
 description VARCHAR(500) NOT NULL, icon VARCHAR(50) NOT NULL DEFAULT 'fa-medal',
 color VARCHAR(20) NOT NULL DEFAULT '#3b82f6', criteria_type VARCHAR(50) NOT NULL,
 criteria_value INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS learner_badges (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_id INT UNSIGNED NOT NULL,
 badge_id INT UNSIGNED NOT NULL, earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY learner_badge (learner_id, badge_id),
 FOREIGN KEY (learner_id) REFERENCES learners(id) ON DELETE CASCADE,
 FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS password_change_requests (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_id INT UNSIGNED NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, handled_by INT UNSIGNED DEFAULT NULL,
 handled_at TIMESTAMP NULL DEFAULT NULL, INDEX (learner_id, status),
 FOREIGN KEY (learner_id) REFERENCES learners(id) ON DELETE CASCADE,
 FOREIGN KEY (handled_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS community_messages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sender_id INT UNSIGNED NOT NULL,
 sender_type ENUM('admin','learner') NOT NULL, message TEXT NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (status, created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 user_type ENUM('admin','learner') NOT NULL, ip_address VARCHAR(45) NOT NULL,
 login_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (user_type, login_time)
) ENGINE=InnoDB;
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('community_mode', 'admin_only');
INSERT IGNORE INTO badges (name, description, icon, color, criteria_type, criteria_value) VALUES
 ('First project', 'Create your first coding project.', 'fa-code', '#3b82f6', 'project_create', 1),
 ('First lesson', 'Complete your first active lesson.', 'fa-book', '#10b981', 'content_complete', 1),
 ('Three-session streak', 'Attend three consecutive recorded sessions.', 'fa-medal', '#f59e0b', 'attendance_streak', 3);
