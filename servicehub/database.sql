CREATE DATABASE IF NOT EXISTS servicehub CHARACTER SET utf8mb4; USE servicehub;
DROP TABLE IF EXISTS settings; DROP TABLE IF EXISTS otp_challenges; DROP TABLE IF EXISTS rate_limits; DROP TABLE IF EXISTS bookings; DROP TABLE IF EXISTS services; DROP TABLE IF EXISTS admins; DROP TABLE IF EXISTS users;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(600),email VARCHAR(120) UNIQUE,phone VARCHAR(255),password VARCHAR(255),blocked TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,email_verified_at DATETIME NULL,is_verified TINYINT(1) NOT NULL DEFAULT 0);
CREATE TABLE admins(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(120) UNIQUE,password VARCHAR(255));
CREATE TABLE services(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120),description TEXT,price DECIMAL(10,2),duration INT,active TINYINT DEFAULT 1);
CREATE TABLE bookings(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,service_id INT,bdate DATE,btime VARCHAR(10),address TEXT,
 status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE);
CREATE TABLE settings(k VARCHAR(50) PRIMARY KEY,v TEXT);
-- admin login: admin@servicehub.com / password  (change it!)
INSERT INTO admins(email,password) VALUES('admin@servicehub.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
INSERT INTO services(name,description,price,duration) VALUES
('Swedish Massage','Slow, flowing strokes that ease tension and calm the mind.',2499,60),
('Deep Tissue Massage','Firm, focused pressure on knots in the back, neck and shoulders.',2999,60),
('Aromatherapy Massage','Warm essential oils chosen to match how you feel today.',2799,75),
('Hot Stone Therapy','Heated basalt stones melt away deep muscle stiffness.',3499,90),
('Signature Facial','Deep cleanse, gentle exfoliation and a hydrating mask.',1999,60),
('Body Scrub & Wrap','A salt scrub and nourishing wrap for soft, glowing skin.',3299,90);
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
