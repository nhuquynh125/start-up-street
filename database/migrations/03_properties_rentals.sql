-- ====================================================
-- STARTUP STREET — Mặt bằng & thuê mặt bằng (nguồn giá DUY NHẤT nằm ở server)
-- Chạy được nhiều lần (idempotent). Số liệu 48 mặt bằng được sinh từ ĐÚNG bộ random (seed 20260117)
-- mà map.php đang dùng, nên mã/giá/diện tích khớp với những gì người chơi thấy trên bản đồ.
-- Nếu đã có bảng properties/rentals riêng thì KHÔNG chạy file này:
-- chỉ cần chỉnh 3 hằng số RENTALS_* trong create-shop.php và PROPERTIES_* trong rent-property.php.
-- ====================================================

CREATE TABLE IF NOT EXISTS `properties` (
    `id` INT PRIMARY KEY,
    `code` VARCHAR(10) NOT NULL,
    `traffic` INT NOT NULL,
    `area` INT NOT NULL,
    `price` DECIMAL(15, 2) NOT NULL,
    `status` ENUM('available', 'npc') NOT NULL DEFAULT 'available',  -- npc = đã có chủ sẵn (không cho thuê)
    UNIQUE KEY `uniq_property_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- create-shop.php đọc bảng này (rentals.property_id, rentals.user_id)
CREATE TABLE IF NOT EXISTS `rentals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `price_paid` DECIMAL(15, 2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_rental_property` (`property_id`),   -- 1 mặt bằng chỉ có 1 người thuê, chặn thuê trùng ở tầng DB
    INDEX `idx_rentals_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `properties` (id, code, traffic, area, price, status) VALUES
    (1, 'A1', 88, 69, 31800000, 'available'),
    (2, 'A2', 73, 52, 25400000, 'available'),
    (3, 'A3', 21, 35, 10900000, 'npc'),
    (4, 'A4', 65, 43, 22000000, 'available'),
    (5, 'A5', 33, 69, 19700000, 'npc'),
    (6, 'A6', 62, 66, 25500000, 'available'),
    (7, 'A7', 28, 47, 14600000, 'available'),
    (8, 'A8', 87, 55, 29000000, 'available'),
    (9, 'A9', 78, 60, 28000000, 'available'),
    (10, 'A10', 28, 50, 15200000, 'available'),
    (11, 'A11', 72, 33, 21800000, 'npc'),
    (12, 'A12', 71, 36, 22100000, 'available'),
    (13, 'B1', 36, 31, 13500000, 'available'),
    (14, 'B2', 70, 53, 24900000, 'npc'),
    (15, 'B3', 24, 46, 13600000, 'available'),
    (16, 'B4', 28, 60, 17000000, 'available'),
    (17, 'B5', 87, 50, 28100000, 'npc'),
    (18, 'B6', 79, 53, 26900000, 'available'),
    (19, 'B7', 78, 55, 27100000, 'npc'),
    (20, 'B8', 85, 52, 28100000, 'available'),
    (21, 'B9', 55, 64, 23600000, 'available'),
    (22, 'B10', 73, 45, 24200000, 'npc'),
    (23, 'B11', 26, 64, 17200000, 'available'),
    (24, 'B12', 24, 36, 11800000, 'available'),
    (25, 'C1', 27, 65, 17600000, 'available'),
    (26, 'C2', 45, 54, 19600000, 'available'),
    (27, 'C3', 46, 39, 17100000, 'available'),
    (28, 'C4', 63, 31, 19400000, 'available'),
    (29, 'C5', 29, 44, 14300000, 'npc'),
    (30, 'C6', 39, 34, 14700000, 'available'),
    (31, 'C7', 66, 57, 24800000, 'available'),
    (32, 'C8', 70, 32, 21200000, 'npc'),
    (33, 'C9', 23, 69, 17500000, 'available'),
    (34, 'C10', 44, 61, 20700000, 'available'),
    (35, 'C11', 20, 65, 16100000, 'available'),
    (36, 'C12', 65, 68, 26500000, 'available'),
    (37, 'D1', 60, 39, 20200000, 'available'),
    (38, 'D2', 42, 56, 19300000, 'available'),
    (39, 'D3', 57, 40, 19700000, 'npc'),
    (40, 'D4', 41, 30, 14400000, 'available'),
    (41, 'D5', 23, 56, 15100000, 'available'),
    (42, 'D6', 27, 33, 11900000, 'available'),
    (43, 'D7', 90, 52, 29200000, 'available'),
    (44, 'D8', 31, 51, 16000000, 'available'),
    (45, 'D9', 61, 47, 21900000, 'available'),
    (46, 'D10', 84, 68, 30700000, 'available'),
    (47, 'D11', 82, 61, 29000000, 'available'),
    (48, 'D12', 27, 69, 18400000, 'available');
