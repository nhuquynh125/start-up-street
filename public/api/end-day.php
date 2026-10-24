<?php
// POST api/end-day.php  {shop_id, since_id?}
// CHỐT CA: kết thúc ngày chơi hiện tại, lưu báo cáo ngày vào MySQL rồi sang ngày mới.
//  - Sang tháng mới (mỗi 30 ngày chơi) thì server TỰ TRỪ tiền thuê mặt bằng và ghi sổ transactions.
//  - Nhật ký khách cũ hơn 7 ngày chơi bị xóa (sang Ngày 8 thì Ngày 1 mất). Báo cáo ngày/tuần/tháng vẫn giữ.
// Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id / số tiền / số ngày từ client: server tự quyết hết.
// Trả về { closed, new_day, rent_charged, purged_orders, state } ; state cùng định dạng với shop-tick.php.
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

    $result = shopEndDay($pdo, $shopId, $ownerId, $sinceId);
    apiRespond(true, "Đã chốt ca Ngày " . $result['closed']['day'], $result);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('end-day: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý chốt ca", null, 500);
}
