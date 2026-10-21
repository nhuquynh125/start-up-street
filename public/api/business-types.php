<?php
// GET api/business-types.php — danh mục loại hình kinh doanh (card chọn mô hình)
// Yêu cầu đăng nhập như mọi API khác: người gọi được xác định qua session (getCurrentUserId), 401 nếu chưa đăng nhập.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    getCurrentUserId(); // 401 nếu chưa đăng nhập (danh mục không phụ thuộc người chơi nên không cần giữ giá trị)

    // SELECT * để tự lấy thêm target_customers / estimated_cost nếu đã chạy migration 02
    $stmt = $pdo->query('SELECT * FROM business_types ORDER BY id');
    apiRespond(true, "Lấy danh mục kinh doanh thành công", ["business_types" => $stmt->fetchAll()]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('business-types: ' . $e->getMessage());
    apiRespond(false, "Lỗi truy vấn danh mục kinh doanh", null, 500);
}
