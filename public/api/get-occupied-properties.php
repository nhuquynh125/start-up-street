<?php
// GET api/get-occupied-properties.php
// Cho bản đồ biết mặt bằng nào đã có người thuê mà KHÔNG lộ ai là chủ: chỉ trả property_id + is_mine.
// (get-shops.php giờ chỉ trả cửa hàng của chính người chơi, nên bản đồ dùng endpoint này để tô "Đã có chủ".)
// Dựa vào bảng rentals nên mặt bằng đã thuê nhưng chưa tạo cửa hàng cũng được tính là đã có người thuê.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $userId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $stmt = $pdo->prepare('SELECT property_id, (user_id = ?) AS is_mine FROM rentals ORDER BY property_id');
    $stmt->execute([$userId]);

    $properties = [];
    foreach ($stmt->fetchAll() as $row) {
        $properties[] = [
            "property_id" => (int)$row['property_id'],
            "is_mine"     => (int)$row['is_mine'] === 1,
        ];
    }
    apiRespond(true, "Lấy danh sách mặt bằng đã thuê thành công", ["properties" => $properties]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('get-occupied-properties: ' . $e->getMessage());
    apiRespond(false, "Lỗi truy vấn mặt bằng", null, 500);
}
