-- ====================================================
-- STARTUP STREET — Bảng users (tài khoản người chơi) + player_profiles (avatar & chi tiêu)
-- Chạy TRƯỚC các migration khác (01, 02, 03).
-- Idempotent: an toàn để chạy lại nhiều lần.
-- ====================================================

-- 1. Bảng tài khoản người chơi
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(32)  NOT NULL,
    `email`         VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `balance`       DECIMAL(15,2) NOT NULL DEFAULT 1000000000.00,  -- 1 tỉ vốn ban đầu
    `avatar_data`   JSON         DEFAULT NULL,  -- dữ liệu avatar 2D (skin, hair, outfit, v.v.)
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `last_login`    TIMESTAMP    NULL,
    UNIQUE KEY `uniq_username` (`username`),
    UNIQUE KEY `uniq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng hồ sơ chi tiết (avatar layer + thiết lập giao diện)
CREATE TABLE IF NOT EXISTS `player_profiles` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT NOT NULL,
    -- Avatar layers
    `avatar_age_group`   VARCHAR(20) DEFAULT 'adult',   -- teen | young | adult | middle | senior
    `avatar_skin_tone`   TINYINT     DEFAULT 2,          -- 0-4
    `avatar_hair_style`  TINYINT     DEFAULT 0,          -- 0-5
    `avatar_hair_color`  TINYINT     DEFAULT 0,          -- 0-4
    `avatar_eye_style`   TINYINT     DEFAULT 0,          -- 0-3
    `avatar_outfit`      TINYINT     DEFAULT 0,          -- 0-5
    `avatar_outfit_color` VARCHAR(10) DEFAULT '#5b8dd9',
    `avatar_hat`         TINYINT     DEFAULT 0,          -- 0=none, 1-3
    `avatar_glasses`     TINYINT     DEFAULT 0,          -- 0=none, 1-2
    -- UI theme
    `theme`         ENUM('warm','cool','forest','night') DEFAULT 'warm',
    -- Metadata
    `display_name`  VARCHAR(50)  DEFAULT NULL,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_profile_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng chi tiêu cá nhân (personal expense tracker — mục quản lý chi tiêu)
CREATE TABLE IF NOT EXISTS `personal_expenses` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `category`    ENUM('food','transport','entertainment','shopping','utilities','health','education','other') NOT NULL DEFAULT 'other',
    `amount`      DECIMAL(15,2) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `expense_date` DATE        NOT NULL,
    `created_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pe_user_date` (`user_id`, `expense_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
