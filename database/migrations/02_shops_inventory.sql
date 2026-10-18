-- ====================================================
-- STARTUP STREET — Thành viên 3: Cửa hàng & Kho hàng
-- Chạy được nhiều lần (idempotent): bảng đã có thì giữ nguyên, dữ liệu mẫu chỉ thêm nếu chưa có.
-- ====================================================

-- 1. Loại hình kinh doanh
CREATE TABLE IF NOT EXISTS `business_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `required_capital` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `description` VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Sản phẩm theo loại hình
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_type_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `base_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    INDEX `idx_products_type` (`business_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Cửa hàng (owner_user_id -> users của TV1, property_id -> mặt bằng của TV2)
CREATE TABLE IF NOT EXISTS `shops` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_user_id` INT NOT NULL,
    `property_id` INT NOT NULL,
    `business_type_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_shop_property` (`property_id`),
    INDEX `idx_shops_owner` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tồn kho từng cửa hàng
CREATE TABLE IF NOT EXISTS `inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shop_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `selling_price` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `last_restocked` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `uniq_shop_product` (`shop_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bổ sung cột cho card mô hình (khách hàng mục tiêu, chi phí dự kiến) — chỉ thêm nếu chưa có
SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'business_types' AND COLUMN_NAME = 'target_customers') = 0,
    'ALTER TABLE business_types ADD COLUMN target_customers VARCHAR(255) NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'business_types' AND COLUMN_NAME = 'estimated_cost') = 0,
    'ALTER TABLE business_types ADD COLUMN estimated_cost DECIMAL(15, 2) NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- 6. Chống 2 cửa hàng trên cùng 1 mặt bằng (nếu bảng shops cũ đang có dữ liệu trùng property_id thì xóa dòng trùng trước khi chạy)
SET @q = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shops' AND INDEX_NAME = 'uniq_shop_property') = 0,
    'ALTER TABLE shops ADD UNIQUE KEY uniq_shop_property (property_id)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND INDEX_NAME = 'uniq_shop_product') = 0,
    'ALTER TABLE inventory ADD UNIQUE KEY uniq_shop_product (shop_id, product_id)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- 7. Dữ liệu mẫu: 6 loại hình (khớp danh sách trong map.php)
INSERT INTO business_types (name, required_capital, description)
SELECT v.name, v.cap, v.descr FROM (
    SELECT 'Trà sữa & đồ uống' AS name, 12000000 AS cap, 'Pha chế đồ uống' AS descr
    UNION ALL SELECT 'Quán ăn nhỏ', 15000000, 'Món ăn nhanh và đồ ăn vặt'
    UNION ALL SELECT 'Tiệm bánh', 10000000, 'Bánh ngọt và bánh mì'
    UNION ALL SELECT 'Shop quần áo', 18000000, 'Thời trang và phụ kiện'
    UNION ALL SELECT 'Tiệm hoa', 8000000, 'Hoa tươi và quà tặng'
    UNION ALL SELECT 'Tạp hóa nhỏ', 14000000, 'Hàng hóa thiết yếu'
) v WHERE NOT EXISTS (SELECT 1 FROM business_types b WHERE b.name = v.name);

UPDATE business_types SET target_customers = 'Học sinh, sinh viên, dân văn phòng', estimated_cost = 3000000 WHERE name = 'Trà sữa & đồ uống' AND target_customers IS NULL;
UPDATE business_types SET target_customers = 'Công nhân, người đi làm, khách vãng lai', estimated_cost = 4000000 WHERE name = 'Quán ăn nhỏ' AND target_customers IS NULL;
UPDATE business_types SET target_customers = 'Gia đình, người đi làm buổi sáng', estimated_cost = 2500000 WHERE name = 'Tiệm bánh' AND target_customers IS NULL;
UPDATE business_types SET target_customers = 'Giới trẻ, khách du lịch', estimated_cost = 6000000 WHERE name = 'Shop quần áo' AND target_customers IS NULL;
UPDATE business_types SET target_customers = 'Cặp đôi, khách mua quà tặng', estimated_cost = 2000000 WHERE name = 'Tiệm hoa' AND target_customers IS NULL;
UPDATE business_types SET target_customers = 'Cư dân quanh khu phố', estimated_cost = 3500000 WHERE name = 'Tạp hóa nhỏ' AND target_customers IS NULL;

-- 8. Dữ liệu mẫu: sản phẩm (chỉ thêm nếu chưa có sản phẩm cùng tên trong loại hình đó)
INSERT INTO products (business_type_id, name, base_cost)
SELECT bt.id, v.pname, v.cost FROM business_types bt JOIN (
    SELECT 'Trà sữa & đồ uống' AS bt_name, 'Trà sữa trân châu' AS pname, 12000 AS cost
    UNION ALL SELECT 'Trà sữa & đồ uống', 'Trà đào cam sả', 10000
    UNION ALL SELECT 'Trà sữa & đồ uống', 'Matcha đá xay', 15000
    UNION ALL SELECT 'Trà sữa & đồ uống', 'Cà phê sữa đá', 8000
    UNION ALL SELECT 'Quán ăn nhỏ', 'Bún bò', 20000
    UNION ALL SELECT 'Quán ăn nhỏ', 'Cơm gà', 18000
    UNION ALL SELECT 'Quán ăn nhỏ', 'Bánh tráng trộn', 8000
    UNION ALL SELECT 'Quán ăn nhỏ', 'Nước ngọt', 5000
    UNION ALL SELECT 'Tiệm bánh', 'Bánh mì', 6000
    UNION ALL SELECT 'Tiệm bánh', 'Bánh kem mini', 25000
    UNION ALL SELECT 'Tiệm bánh', 'Croissant', 12000
    UNION ALL SELECT 'Tiệm bánh', 'Cookie', 7000
    UNION ALL SELECT 'Shop quần áo', 'Áo thun', 60000
    UNION ALL SELECT 'Shop quần áo', 'Quần jean', 120000
    UNION ALL SELECT 'Shop quần áo', 'Váy', 100000
    UNION ALL SELECT 'Shop quần áo', 'Nón', 30000
    UNION ALL SELECT 'Tiệm hoa', 'Hoa hồng (bó)', 40000
    UNION ALL SELECT 'Tiệm hoa', 'Hoa hướng dương', 25000
    UNION ALL SELECT 'Tiệm hoa', 'Chậu cây mini', 30000
    UNION ALL SELECT 'Tiệm hoa', 'Hộp quà', 20000
    UNION ALL SELECT 'Tạp hóa nhỏ', 'Gạo (túi 1kg)', 18000
    UNION ALL SELECT 'Tạp hóa nhỏ', 'Dầu ăn (chai)', 35000
    UNION ALL SELECT 'Tạp hóa nhỏ', 'Mì gói (thùng)', 90000
    UNION ALL SELECT 'Tạp hóa nhỏ', 'Nước suối (thùng)', 80000
) v ON v.bt_name = bt.name
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.business_type_id = bt.id AND p.name = v.pname);
