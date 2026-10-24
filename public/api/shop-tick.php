<?php
// POST api/shop-tick.php  {shop_id, since_id?}
// Cho khách (tự động) ghé quán từ lần tick trước tới giờ nếu quán đang MỞ, rồi trả trạng thái để vẽ lên màn hình.
// Quán đang đóng thì chỉ trả trạng thái, không phát sinh khách/tiền.
// Request KHÔNG có và KHÔNG được phép có trường tiền / giá / số khách: server tự quyết hết.
// Gọi lặp lại / nhiều tab cùng lúc là an toàn: khách không bị tính hai lần (xem lib/shop_simulation.php).
require_once dirname(__DIR__, 2) . '/lib/shop_simulation.php';

requireMethod('POST');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input  = readJsonBody();
    $shopId = toPositiveInt($input['shop_id'] ?? null);
    if (!$shopId) {
        throw new ApiException("Thiếu hoặc sai shop_id", 400);
    }
    $sinceId = toPositiveInt($input['since_id'] ?? null) ?? 0;

    $state = shopRuntimeTick($pdo, $shopId, $ownerId, null, $sinceId);
    apiRespond(true, "OK", $state);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('shop-tick: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý khách vào quán", null, 500);
}
