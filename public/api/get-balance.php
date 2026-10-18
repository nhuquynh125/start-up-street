<?php
// GET api/get-balance.php?user_id=1 — số dư thật từ users.balance (frontend chỉ dùng để HIỂN THỊ)
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $userId = toPositiveInt($_GET['user_id'] ?? null);
    if ($userId === null) {
        throw new ApiException("user_id không hợp lệ", 400);
    }
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
