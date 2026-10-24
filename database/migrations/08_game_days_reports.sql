-- ====================================================
-- STARTUP STREET — Ngày chơi, báo cáo doanh thu, tiền thuê mặt bằng theo tháng
-- Chạy SAU 00..04. Idempotent: chạy lại nhiều lần vẫn an toàn.
--
-- Ngày chơi (day_number) chỉ tăng khi người chơi bấm "Chốt ca" (api/end-day.php).
-- shop_orders (nhật ký khách) chỉ giữ 7 ngày chơi gần nhất; shop_daily_reports giữ tổng kết từng ngày mãi mãi
-- (báo cáo tuần / tháng / tài chính tính từ bảng này nên không mất khi nhật ký bị xóa).
-- ====================================================

-- 1. shop_runtime: ngày chơi hiện tại, mốc bắt đầu ngày, và ngày mà tiền thuê đã trả tới (tháng đầu trả lúc thuê = tới ngày 30)
SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'day_number') = 0,
    'ALTER TABLE shop_runtime ADD COLUMN day_number INT NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'day_started_ts') = 0,
    'ALTER TABLE shop_runtime ADD COLUMN day_started_ts BIGINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'rent_paid_until_day') = 0,
    'ALTER TABLE shop_runtime ADD COLUMN rent_paid_until_day INT NOT NULL DEFAULT 30', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. shop_orders: giá vốn của đơn (vốn mặt hàng) và đơn thuộc ngày chơi nào
SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'cost') = 0,
    'ALTER TABLE shop_orders ADD COLUMN cost DECIMAL(15, 2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'day_number') = 0,
    'ALTER TABLE shop_orders ADD COLUMN day_number INT NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_orders' AND INDEX_NAME = 'idx_orders_shop_day') = 0,
    'ALTER TABLE shop_orders ADD INDEX idx_orders_shop_day (shop_id, day_number)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Tổng kết từng ngày chơi. Dòng của ngày đang diễn ra có closed_ts = NULL.
CREATE TABLE IF NOT EXISTS `shop_daily_reports` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `shop_id`     INT            NOT NULL,
    `day_number`  INT            NOT NULL,
    `started_ts`  BIGINT         NOT NULL DEFAULT 0,
    `closed_ts`   BIGINT         NULL DEFAULT NULL,
    `served`      INT            NOT NULL DEFAULT 0,
    `missed`      INT            NOT NULL DEFAULT 0,
    `revenue`     DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `cost`        DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `rent_cost`   DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_report_shop_day` (`shop_id`, `day_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dữ liệu cũ: ngày 1 của quán bắt đầu từ lúc tạo quán; đơn đã bán trước đây tính giá vốn theo products.base_cost
UPDATE shop_runtime r JOIN shops s ON s.id = r.shop_id SET r.day_started_ts = UNIX_TIMESTAMP(s.created_at) WHERE r.day_started_ts = 0;

UPDATE shop_orders o JOIN products p ON p.id = o.product_id SET o.cost = o.quantity * p.base_cost WHERE o.status = 'served' AND o.cost = 0 AND o.product_id IS NOT NULL;
