SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS sales_admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','sales') NOT NULL DEFAULT 'sales',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 client_name VARCHAR(190) NOT NULL,
 company_name VARCHAR(190) DEFAULT NULL,
 contact_email VARCHAR(190) DEFAULT NULL,
 account_owner_id INT UNSIGNED DEFAULT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_clients_account_owner FOREIGN KEY (account_owner_id) REFERENCES sales_admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback_forms (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 client_id INT UNSIGNED NOT NULL,
 token VARCHAR(64) NOT NULL UNIQUE,
 meeting_date DATE DEFAULT NULL,
 status ENUM('pending','submitted','expired') NOT NULL DEFAULT 'pending',
 sent_by_id INT UNSIGNED DEFAULT NULL,
 sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 submitted_at DATETIME DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_forms_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_forms_sent_by FOREIGN KEY (sent_by_id) REFERENCES sales_admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback_submissions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 form_id INT UNSIGNED NOT NULL UNIQUE,
 client_id INT UNSIGNED NOT NULL,
 relationship_score TINYINT UNSIGNED NOT NULL,
 meeting_notes TEXT DEFAULT NULL,
 action_items TEXT DEFAULT NULL,
 client_comments TEXT DEFAULT NULL,
 submitted_ip VARCHAR(45) DEFAULT NULL,
 respondent_client_name VARCHAR(190) DEFAULT NULL,
 staff_name VARCHAR(190) DEFAULT NULL,
 poc_name VARCHAR(190) DEFAULT NULL,
 business_needs_score TINYINT UNSIGNED DEFAULT NULL,
 communication_support_score TINYINT UNSIGNED DEFAULT NULL,
 concerns_comfort_score TINYINT UNSIGNED DEFAULT NULL,
 partnership_confidence_score TINYINT UNSIGNED DEFAULT NULL,
 improvement_feedback TEXT DEFAULT NULL,
 relationship_description TEXT DEFAULT NULL,
 additional_responses JSON DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_submissions_form FOREIGN KEY (form_id) REFERENCES feedback_forms(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_submissions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT chk_relationship_score CHECK (relationship_score BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW client_health_overview AS
SELECT c.id AS client_id,c.client_name,c.company_name,COUNT(fs.id) AS total_submissions,
 ROUND(AVG(fs.relationship_score),2) AS avg_score,
 (SELECT fs2.relationship_score FROM feedback_submissions fs2 WHERE fs2.client_id=c.id ORDER BY fs2.created_at DESC LIMIT 1) AS latest_score,
 (SELECT fs3.created_at FROM feedback_submissions fs3 WHERE fs3.client_id=c.id ORDER BY fs3.created_at DESC LIMIT 1) AS latest_submission_at
FROM clients c LEFT JOIN feedback_submissions fs ON fs.client_id=c.id GROUP BY c.id,c.client_name,c.company_name;

SET FOREIGN_KEY_CHECKS=1;
