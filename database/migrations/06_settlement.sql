-- ====================================================
-- STARTUP STREET — Két quán + chốt ca + phí tháng
-- Chạy SAU 04. Idempotent: chạy lại nhiều lần vẫn an toàn.
--
-- shop_runtime.till_balance          : doanh thu khách trả, CHƯA chuyển vào ví. Chốt ca (api/end-day.php) mới chuyển.
-- shop_runtime.last_settled_order_id : đơn cuối cùng đã nằm trong báo cáo chốt ca trước (báo cáo ca = đơn có id lớn hơn).
-- shop_runtime.last_billed_ts        : mốc unix time đã thu phí tháng tới đâu (0 = chưa bắt đầu, lần tick đầu sẽ đặt = bây giờ).
-- ====================================================

-- Nhớ xem cột last_settled_order_id đã có chưa, để chỉ đặt mốc ban đầu ĐÚNG MỘT LẦN (khi cột mới được thêm)
SET @fresh = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'last_settled_order_id') = 0, 1, 0);

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'till_balance') = 0,
    'ALTER TABLE shop_runtime ADD COLUMN till_balance DECIMAL(15, 2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'last_settled_order_id') = 0,
    'ALTER TABLE shop_runtime ADD COLUMN last_settled_order_id BIGINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_runtime' AND COLUMN_NAME = 'last_billed_ts') = 0,
    'ALTER TABLE shop_runtime ADD COLUMN last_billed_ts BIGINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- Các đơn cũ đã được cộng thẳng vào ví theo cách cũ: đặt mốc = đơn hiện tại để báo cáo ca đầu tiên không tính lại chúng
SET @q = IF(@fresh = 1,
    'UPDATE shop_runtime r SET r.last_settled_order_id = (SELECT COALESCE(MAX(o.id), 0) FROM shop_orders o WHERE o.shop_id = r.shop_id)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
