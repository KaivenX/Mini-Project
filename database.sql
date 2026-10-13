-- ============================================
-- BloodLink Database Schema
-- ============================================
 
CREATE DATABASE IF NOT EXISTS bloodlink;
USE bloodlink;
 
-- ---------------------------
-- Table: users
-- ---------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'donor', 'requester') NOT NULL DEFAULT 'donor',
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
    last_donation_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
 
-- ---------------------------
-- Table: donation_drives
-- ---------------------------
CREATE TABLE donation_drives (
    drive_id INT AUTO_INCREMENT PRIMARY KEY,
    organized_by INT NOT NULL,
    location VARCHAR(150) NOT NULL,
    drive_date DATE NOT NULL,
    capacity INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organized_by) REFERENCES users(user_id)
        ON DELETE CASCADE
);
 
-- ---------------------------
-- Table: donations
-- ---------------------------
CREATE TABLE donations (
    donation_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    drive_id INT NOT NULL,
    donation_date DATE NOT NULL,
    quantity_ml INT NOT NULL DEFAULT 450,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    FOREIGN KEY (drive_id) REFERENCES donation_drives(drive_id)
        ON DELETE CASCADE
);
 
-- ---------------------------
-- Table: blood_stock
-- ---------------------------
CREATE TABLE blood_stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL UNIQUE,
    quantity_units INT NOT NULL DEFAULT 0
);

-- Requests are submitted by requester accounts and fulfilled against stock by admins.
CREATE TABLE IF NOT EXISTS blood_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    hospital_name VARCHAR(150) NOT NULL,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    quantity_units INT NOT NULL,
    urgency ENUM('Critical', 'Normal') NOT NULL DEFAULT 'Normal',
    status ENUM('Pending', 'Approved', 'Rejected', 'Fulfilled') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_blood_request_quantity CHECK (quantity_units > 0),
    FOREIGN KEY (requester_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_blood_requests_status_urgency (status, urgency, created_at)
);

-- ============================================
-- Seed data
-- ============================================

INSERT INTO blood_stock (blood_type, quantity_units) VALUES
('A+', 10), ('A-', 5), ('B+', 8), ('B-', 4),
('AB+', 3), ('AB-', 2), ('O+', 12), ('O-', 6);
 

-- Admin login: admin@bloodlink.com / admin123
INSERT INTO users (name, email, password, `role`) VALUES
('Blood Bank Admin', 'admin@bloodlink.com', '$2y$10$RoLcHx1KDqVZAQVBHcBK1.GREd4hetNnHC0wWVEf4pr5LOAtokFh.', 'admin');
 
-- Donor login: donor@bloodlink.com / donor123
INSERT INTO users (name, email, password, `role`, blood_type, last_donation_date) VALUES
('Test Donor', 'donor@bloodlink.com', '$2y$10$deWtaRKmtABuw.5fobiwY.XkPWtwPmROhYJ0/ZzSVl5N7azDyThQ2', 'donor', 'O+', '2026-07-01');
 
INSERT INTO donation_drives (organized_by, location, drive_date, capacity) VALUES
(1, 'Community Hall, Penang', '2026-10-15', 50);

UPDATE users
SET `role` = 'requester',
password = '$2y$10$deWtaRKmtABuw.5fobiwY.XkPWtwPmROhYJ0/ZzSVl5N7azDyThQ2'
WHERE email = 'donor@bloodlink.com';

UPDATE users SET password = '$2y$10$deWtaRKmtABuw.5fobiwY.XkPWtwPmROhYJ0/ZzSVl5N7azDyThQ2' 
WHERE email = 'donor@bloodlink.com' 
AND `role` = 'donor';

UPDATE users
SET password = '$2y$10$RoLcHx1KDqVZAQVBHcBK1.GREd4hetNnHC0wWVEf4pr5LOAtokFh.'
WHERE email = 'admin@bloodlink.com'
AND `role` = 'admin';
