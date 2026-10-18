<?php
// POST api/sell-product.php  {shop_id, product_id, quantity?}
// Chủ cửa hàng = người đang đăng nhập (getCurrentUserId), KHÔNG nhận owner_user_id từ client.
// Bán hàng: trừ kho + cộng users.balance trong 1 transaction (xem deductShopInventoryAndAddBalance).
// GIÁ BÁN LUÔN LẤY TỪ DATABASE (inventory.selling_price) — request không có và không được phép có trường giá/tiền.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input = readJsonBody();

    $shopId    = toPositiveInt($input['shop_id'] ?? null);
    $productId = toPositiveInt($input['product_id'] ?? null);
    $quantity  = toPositiveInt($input['quantity'] ?? 1);

    if (!$shopId || !$productId || !$quantity) {
        throw new ApiException("Thông tin bán hàng không hợp lệ (shop_id, product_id, quantity)", 400);
    }

    requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403

    $result = deductShopInventoryAndAddBalance($pdo, $shopId, $productId, $quantity); // tự commit / rollback
    apiRespond(true, "Bán hàng thành công", $result);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('sell-product: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý bán hàng", null, 500);
}
