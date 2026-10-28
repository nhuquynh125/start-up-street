-- inventory.max_stock: sức chứa kho tối đa của MỘT món trong MỘT cửa hàng (mặc định 40, người chơi chỉnh được)
SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'max_stock') = 0,
    'ALTER TABLE inventory ADD COLUMN max_stock INT NOT NULL DEFAULT 40', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;