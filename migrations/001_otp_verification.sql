-- Email verification migration. Safe on an existing database: keeps users, bookings and services.
-- Run once in phpMyAdmin OR open migrate.php (localhost). migrate.php can be re-run safely.
ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL;
ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0;
-- Customers who registered before verification existed stay able to log in.
UPDATE users SET is_verified=1 WHERE is_verified=0 AND email_verified_at IS NULL;
CREATE TABLE IF NOT EXISTS otp_challenges(
 id CHAR(32) NOT NULL PRIMARY KEY,
 purpose VARCHAR(20) NOT NULL DEFAULT 'register',
 channel ENUM('email','phone') NOT NULL,
 destination VARCHAR(190) NOT NULL,
 otp_hash CHAR(64) NOT NULL,
 bind_key CHAR(64) NOT NULL,
 status ENUM('pending','verified','consumed','expired','locked') NOT NULL DEFAULT 'pending',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 resend_available_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 verified_at DATETIME NULL,
 KEY idx_bind (bind_key,channel,status),
 KEY idx_dest (destination,channel),
 KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS rate_limits(
 k CHAR(40) NOT NULL PRIMARY KEY,
 hits INT NOT NULL DEFAULT 0,
 window_start DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
