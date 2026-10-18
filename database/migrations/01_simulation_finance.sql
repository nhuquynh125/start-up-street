-- ====================================================
-- STARTUP STREET — Member 4: Simulation & Finance
-- Database Schema for Transactions, Cycles, and Sales
-- ====================================================

-- 1. Bảng lịch sử giao dịch (Transactions)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `shop_id` INT DEFAULT NULL,
    `cycle_number` INT DEFAULT 1,
    `type` ENUM('income', 'expense') NOT NULL,
    `category` ENUM('sale', 'material', 'rent', 'salary', 'utilities', 'marketing', 'other') NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `balance_after` DECIMAL(15, 2) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_shop` (`user_id`, `shop_id`),
    INDEX `idx_cycle` (`cycle_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng báo cáo chu kỳ kinh doanh (Cycle Reports)
CREATE TABLE IF NOT EXISTS `cycle_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shop_id` INT NOT NULL,
    `cycle_number` INT NOT NULL,
    `customer_count` INT NOT NULL DEFAULT 0,
    `order_count` INT NOT NULL DEFAULT 0,
    `total_revenue` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `material_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `fixed_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `net_profit` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_shop_cycle` (`shop_id`, `cycle_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng chi tiết sản phẩm bán theo chu kỳ (Cycle Sales Detail)
CREATE TABLE IF NOT EXISTS `cycle_sales_detail` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cycle_report_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(100) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `profit` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (`cycle_report_id`) REFERENCES `cycle_reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
