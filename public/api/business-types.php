<?php
// GET api/business-types.php — danh mục loại hình kinh doanh (card chọn mô hình)
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    // SELECT * để tự lấy thêm target_customers / estimated_cost nếu đã chạy migration 02
    $stmt = $pdo->query('SELECT * FROM business_types ORDER BY id');
    apiRespond(true, "Lấy danh mục kinh doanh thành công", ["business_types" => $stmt->fetchAll()]);
} catch (Exception $e) {
    apiRespond(false, "Lỗi truy vấn: " . $e->getMessage(), null, 500);
}
