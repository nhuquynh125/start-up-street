<?php
// POST api/sell-product.php  {owner_user_id, shop_id, product_id, quantity?}
// Bán hàng: trừ kho + cộng users.balance trong 1 transaction (xem deductShopInventoryAndAddBalance).
// GIÁ BÁN LUÔN LẤY TỪ DATABASE (inventory.selling_price) — request không có và không được phép có trường giá/tiền.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $input = readJsonBody();

    $ownerId   = toPositiveInt($input['owner_user_id'] ?? null);
    $shopId    = toPositiveInt($input['shop_id'] ?? null);
    $productId = toPositiveInt($input['product_id'] ?? null);
    $quantity  = toPositiveInt($input['quantity'] ?? 1);

    if (!$ownerId || !$shopId || !$productId || !$quantity) {
        throw new ApiException("Thông tin bán hàng không hợp lệ (owner_user_id, shop_id, product_id, quantity)", 400);
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
