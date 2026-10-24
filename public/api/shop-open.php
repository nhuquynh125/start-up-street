<?php
// POST api/shop-open.php  {shop_id, open: true|false}
// Mở / đóng cửa quán. Chủ quán = người đang đăng nhập (getCurrentUserId), KHÔNG nhận owner_user_id từ client.
// - Mở cửa: cần có hàng trong kho (409 nếu kho trống). Khách chỉ bắt đầu đến TỪ LÚC MỞ CỬA.
// - Đóng cửa: server chốt nốt khách đến tới thời điểm đóng rồi mới đóng.
// Trả về cùng định dạng với shop-tick.php.
require_once dirname(__DIR__, 2) . '/lib/shop_simulation.php';

requireMethod('POST');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input  = readJsonBody();
    $shopId = toPositiveInt($input['shop_id'] ?? null);
    $open   = $input['open'] ?? null;
    if (!$shopId || !is_bool($open)) {
        throw new ApiException("Thiếu hoặc sai thông tin (shop_id, open = true/false)", 400);
    }

    $state = shopRuntimeTick($pdo, $shopId, $ownerId, $open, 0);
    apiRespond(true, $open ? "Quán đã mở cửa" : "Quán đã đóng cửa", $state);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('shop-open: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý mở/đóng cửa quán", null, 500);
}
