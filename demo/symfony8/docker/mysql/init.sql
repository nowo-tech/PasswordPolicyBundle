-- This file is automatically executed when MySQL container starts for the first time
-- It creates the database, tables and initializes it with sample data

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT NOT NULL,
    email VARCHAR(180) NOT NULL,
    password VARCHAR(255) NOT NULL,
    password_changed_at DATETIME DEFAULT NULL,
    UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Create password_history table
CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_4E9C81F6A76ED395 (user_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Add foreign key constraint
ALTER TABLE password_history 
    ADD CONSTRAINT FK_4E9C81F6A76ED395 
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

-- Insert initial demo data
-- IMPORTANT: Bcrypt generates different hashes each time (due to random salt), but password_verify() 
-- can verify any hash against the original password. The hashes below correspond to:
-- demo@example.com -> password: 'demo123'
-- admin@example.com -> password: 'admin123'  
-- expired@example.com -> password: 'expired123'
-- 
-- To verify/regenerate hashes, use: password_verify('password', '$2y$12$...hash...')
-- To generate new hashes, use: password_hash('password', PASSWORD_BCRYPT)

-- User 1: demo@example.com - password: 'demo123' - password changed 85 days ago (will expire in 5 days if expiry_days is 90)
INSERT INTO users (email, password, password_changed_at) VALUES
('demo@example.com', '$2y$12$d6CAV3zn1/grGaovSu7g5u0WaLkJCK6qyclDPs.Oii0oPM591BUvy', DATE_SUB(NOW(), INTERVAL 85 DAY))
ON DUPLICATE KEY UPDATE email=email;

-- User 2: admin@example.com - password: 'admin123' - password changed today
INSERT INTO users (email, password, password_changed_at) VALUES
('admin@example.com', '$2y$12$u7OzcjG.uQvpnv5mzpq1KOBfUW5keb20DF20.cmw/CHRVDC05TPMS', NOW())
ON DUPLICATE KEY UPDATE email=email;

-- User 3: expired@example.com - password: 'expired123' - password changed 100 days ago (expired if expiry_days is 90)
INSERT INTO users (email, password, password_changed_at) VALUES
('expired@example.com', '$2y$12$.K7fUv1hcOOR5SzFyW1dbe4ctn/4G/d5Tok6BjPjyd/9iAdcCpkL.', DATE_SUB(NOW(), INTERVAL 100 DAY))
ON DUPLICATE KEY UPDATE email=email;

-- Insert password history for demo users
-- Password history for demo@example.com
-- Previous password changed 90 days ago
INSERT IGNORE INTO password_history (user_id, password, created_at) 
SELECT id, '$2y$12$xCutWHwy2C3WlIlx1VvQ9Ozk/TbUxC3YtTT8pPjYQEGWiqfd7uiDu', DATE_SUB(NOW(), INTERVAL 90 DAY)
FROM users WHERE email = 'demo@example.com';

-- Previous password changed 95 days ago
INSERT IGNORE INTO password_history (user_id, password, created_at) 
SELECT id, '$2y$12$5YgmQoHIeQLIaIONSalI6.Y0CwsSycSR1fvBCZSNk6zc/4Y2cWNi6', DATE_SUB(NOW(), INTERVAL 95 DAY)
FROM users WHERE email = 'demo@example.com';

-- Password history for admin@example.com
-- Previous password changed 10 days ago
INSERT IGNORE INTO password_history (user_id, password, created_at) 
SELECT id, '$2y$12$xCutWHwy2C3WlIlx1VvQ9Ozk/TbUxC3YtTT8pPjYQEGWiqfd7uiDu', DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM users WHERE email = 'admin@example.com';

-- Previous password changed 20 days ago
INSERT IGNORE INTO password_history (user_id, password, created_at) 
SELECT id, '$2y$12$5YgmQoHIeQLIaIONSalI6.Y0CwsSycSR1fvBCZSNk6zc/4Y2cWNi6', DATE_SUB(NOW(), INTERVAL 20 DAY)
FROM users WHERE email = 'admin@example.com';

-- Password history for expired@example.com
-- Previous password changed 105 days ago
INSERT IGNORE INTO password_history (user_id, password, created_at) 
SELECT id, '$2y$12$xCutWHwy2C3WlIlx1VvQ9Ozk/TbUxC3YtTT8pPjYQEGWiqfd7uiDu', DATE_SUB(NOW(), INTERVAL 105 DAY)
FROM users WHERE email = 'expired@example.com';

-- Previous password changed 110 days ago
INSERT IGNORE INTO password_history (user_id, password, created_at) 
SELECT id, '$2y$12$5YgmQoHIeQLIaIONSalI6.Y0CwsSycSR1fvBCZSNk6zc/4Y2cWNi6', DATE_SUB(NOW(), INTERVAL 110 DAY)
FROM users WHERE email = 'expired@example.com';
