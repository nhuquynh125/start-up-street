-- ====================================================
-- STARTUP STREET — Mặt hàng do người chơi tự thêm (custom products)
-- Chạy SAU 02. Idempotent: chạy lại nhiều lần vẫn an toàn.
--
-- products.shop_id:
--   NULL      = món có sẵn của loại hình (dùng chung cho mọi cửa hàng cùng loại hình)
--   <shop id> = món do chủ cửa hàng tự thêm, CHỈ cửa hàng đó thấy và bán được
-- ====================================================

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'shop_id') = 0,
    'ALTER TABLE products ADD COLUMN shop_id INT NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_shop') = 0,
    'ALTER TABLE products ADD INDEX idx_products_shop (shop_id)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
