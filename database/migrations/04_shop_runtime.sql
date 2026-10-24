-- ====================================================
-- STARTUP STREET — Member 4: Vận hành quán & khách tự động
-- Chạy SAU 00, 01, 02, 03. Idempotent: chạy lại nhiều lần vẫn an toàn.
-- ====================================================

-- 1. Trạng thái vận hành của từng cửa hàng (mở/đóng cửa, mốc thời gian mô phỏng, uy tín, bộ đếm)
--    Thời gian lưu dạng unix timestamp (giây) do PHP time() sinh ra, tránh lệch múi giờ giữa PHP và MySQL.
CREATE TABLE IF NOT EXISTS `shop_runtime` (
    `shop_id`         INT PRIMARY KEY,
    `is_open`         TINYINT(1)    NOT NULL DEFAULT 0,
    `opened_ts`       BIGINT        NOT NULL DEFAULT 0,
    `last_tick_ts`    BIGINT        NOT NULL DEFAULT 0,
    `reputation`      DECIMAL(5, 2) NOT NULL DEFAULT 60.00,
    `customers_total` INT           NOT NULL DEFAULT 0,
    `served_total`    INT           NOT NULL DEFAULT 0,
    `missed_total`    INT           NOT NULL DEFAULT 0,
    `walkby_total`    INT           NOT NULL DEFAULT 0,
    `revenue_total`   DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mỗi dòng = một khách đã vào quán (mua được hoặc bỏ về vì hết hàng)
CREATE TABLE IF NOT EXISTS `shop_orders` (
    `id`            BIGINT AUTO_INCREMENT PRIMARY KEY,
    `shop_id`       INT           NOT NULL,
    `product_id`    INT           NULL,
    `product_name`  VARCHAR(100)  NOT NULL DEFAULT '',
    `quantity`      INT           NOT NULL DEFAULT 0,
    `unit_price`    DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `total`         DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `status`        ENUM('served', 'missed') NOT NULL,
    `customer_name` VARCHAR(40)   NOT NULL DEFAULT '',
    `arrived_ts`    BIGINT        NOT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_orders_shop_id` (`shop_id`, `id`),
    INDEX `idx_orders_shop_time` (`shop_id`, `arrived_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
