-- ====================================================
-- STARTUP STREET — Icon + giá bán dự kiến cho mặt hàng người chơi tự thêm
-- Chạy SAU 05. Idempotent: chạy lại nhiều lần vẫn an toàn.
--
-- products.emoji         : icon minh họa (NULL = tự đoán theo tên món ở frontend)
-- products.default_price : giá bán người chơi chọn lúc thêm món. Dùng làm giá bán mặc định
--                          cho tới khi món có dòng kho (inventory.selling_price mới là giá thật khi bán).
-- ====================================================

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'emoji') = 0,
    'ALTER TABLE products ADD COLUMN emoji VARCHAR(16) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'default_price') = 0,
    'ALTER TABLE products ADD COLUMN default_price DECIMAL(15, 2) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
