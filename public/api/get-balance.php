<?php
// GET api/get-balance.php — số dư thật từ users.balance của NGƯỜI ĐANG ĐĂNG NHẬP (frontend chỉ dùng để HIỂN THỊ)
// Không nhận user_id: không ai xem được số dư của người khác bằng cách đổi ID.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $userId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $balance = getWalletBalance($pdo, $userId);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }
    apiRespond(true, "Lấy số dư thành công", ["user_id" => $userId, "balance" => $balance]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('get-balance: ' . $e->getMessage());
    apiRespond(false, "Lỗi truy vấn số dư", null, 500);
}
