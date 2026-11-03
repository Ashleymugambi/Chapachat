-- ChapaChat – Privacy-Aware Messaging System Database Schema & Seed Data
-- Target Database: MySQL / MariaDB (InnoDB, utf8mb4)

CREATE DATABASE IF NOT EXISTS `chapachat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chapachat`;

-- 1. Roles Table
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `message`;
DROP TABLE IF EXISTS `otp_code`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `role_id` INT NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE `users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `role_id` INT NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT NOT NULL DEFAULT 1,
  `failed_attempts` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. OTP Codes Table
CREATE TABLE `otp_code` (
  `otp_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `is_used` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`otp_id`),
  KEY `idx_otp_user` (`user_id`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Messages Table
CREATE TABLE `message` (
  `message_id` INT NOT NULL AUTO_INCREMENT,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `encrypted_text` TEXT NOT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` TINYINT NOT NULL DEFAULT 0,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_message_receiver` (`receiver_id`),
  KEY `idx_message_sender` (`sender_id`),
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_message_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Audit Log Table
CREATE TABLE `audit_log` (
  `log_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `details` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `log_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_log_time` (`log_time`),
  KEY `idx_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Roles
INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'User'),
(2, 'Administrator');

-- Seed Data: Users
-- Admin: admin@chapachat.test / Admin@123
-- User 1: john@chapachat.test / User@1234
-- User 2: jane@chapachat.test / User@1234
INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`, `failed_attempts`, `created_at`) VALUES
(1, 2, 'System Administrator', 'admin@chapachat.test', '+254700000000', '$2y$10$Kd9HXZxOhYK6IBoPKV.U4.plArVQutgYnH4tbyyKjrWb9Ics9e3LC', 1, 0, NOW()),
(2, 1, 'John Doe', 'john@chapachat.test', '+254712345678', '$2y$10$h2Xkkcu4JNYOizv7k./Zq.sITLtDGEBjsfn9GLhokV18KHJTZpNBG', 1, 0, NOW()),
(3, 1, 'Jane Smith', 'jane@chapachat.test', '+254798765432', '$2y$10$h2Xkkcu4JNYOizv7k./Zq.sITLtDGEBjsfn9GLhokV18KHJTZpNBG', 1, 0, NOW());

-- Initial System Audit Event
INSERT INTO `audit_log` (`user_id`, `action`, `details`, `ip_address`, `log_time`) VALUES
(1, 'SYSTEM_INIT', 'Database initialized with default roles and accounts', '127.0.0.1', NOW());
