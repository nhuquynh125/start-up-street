<?php
// GET api/get-shop-products.php?shop_id=5
// Chủ cửa hàng = người đang đăng nhập (getCurrentUserId), KHÔNG nhận owner_user_id từ client.
// Trả về sản phẩm của loại hình + tồn kho + giá bán của cửa hàng (module bán hàng của TV4 dùng lại API này)
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $shopId = toPositiveInt($_GET['shop_id'] ?? null);
    if ($shopId === null) {
        throw new ApiException("Thiếu hoặc sai shop_id", 400);
    }

    $shop = requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403

    $stmt = $pdo->prepare('
        SELECT
            p.id AS product_id,
            p.name AS product_name,
            p.base_cost,
            COALESCE(i.quantity, 0) AS stock_quantity,
            COALESCE(i.selling_price, p.base_cost * ' . DEFAULT_MARKUP . ') AS selling_price,
            i.last_restocked
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.shop_id = ?
        WHERE p.business_type_id = ?
        ORDER BY p.id
    ');
    $stmt->execute([$shopId, $shop['business_type_id']]);

    // Số dư ví để UI hiển thị; nếu bảng ví chưa khớp cấu hình thì trả null thay vì làm hỏng cả API
    $balance = null;
    try {
        $balance = getWalletBalance($pdo, $ownerId);
    } catch (Exception $e) {
        error_log('get-shop-products: không đọc được ví: ' . $e->getMessage());
    }

    apiRespond(true, "Lấy danh sách sản phẩm thành công", [
        "shop"     => ["id" => (int)$shop['id'], "name" => $shop['name'], "business_type_id" => (int)$shop['business_type_id']],
        "balance"  => $balance,
        "products" => $stmt->fetchAll()
    ]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Exception $e) {
    apiRespond(false, "Lỗi truy vấn: " . $e->getMessage(), null, 500);
}
