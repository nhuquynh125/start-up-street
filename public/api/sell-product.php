<?php
// POST api/sell-product.php  {shop_id, product_id, quantity?, size?}   size: 'M' (mặc định) | 'L'
// Chủ cửa hàng = người đang đăng nhập (getCurrentUserId), KHÔNG nhận owner_user_id từ client.
// Bán hàng: trừ kho + cộng users.balance + ghi sổ transactions trong 1 transaction.
// GIÁ BÁN LUÔN DO SERVER TÍNH: inventory.selling_price x hệ số cỡ ly (SIZE_MULTIPLIERS).
// Request chỉ gửi được "size", không có và không được phép có trường giá/tiền.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

const SIZE_MULTIPLIERS = ['M' => 1.0, 'L' => 1.3]; // chỉnh cân bằng game ở đây (frontend chỉ hiển thị giá ước tính)

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input = readJsonBody();

    $shopId    = toPositiveInt($input['shop_id'] ?? null);
    $productId = toPositiveInt($input['product_id'] ?? null);
    $quantity  = toPositiveInt($input['quantity'] ?? 1);
    $size      = $input['size'] ?? 'M';

    if (!$shopId || !$productId || !$quantity) {
        throw new ApiException("Thông tin bán hàng không hợp lệ (shop_id, product_id, quantity)", 400);
    }
    if (!is_string($size) || !isset(SIZE_MULTIPLIERS[$size])) {
        throw new ApiException("Cỡ ly không hợp lệ (M hoặc L)", 400);
    }

    apiRespond(false, "Bán thủ công đã tắt: khách đến mua tự động khi quán mở cửa.", null, 410); // 404 / 403

    $pdo->beginTransaction();

    // Giá gốc lấy từ database; chưa có dòng kho / chưa có giá => truyền null để helper trả 409
    $stmt = $pdo->prepare('SELECT selling_price FROM inventory WHERE shop_id = ? AND product_id = ?');
    $stmt->execute([$shopId, $productId]);
    $basePrice = $stmt->fetchColumn();
    $unitPrice = ($basePrice !== false && (float)$basePrice > 0)
        ? round((float)$basePrice * SIZE_MULTIPLIERS[$size], 2)
        : null;

    // Helper thấy transaction đang mở nên KHÔNG tự commit: ta commit sau khi ghi sổ
    $result = deductShopInventoryAndAddBalance($pdo, $shopId, $productId, $quantity, $unitPrice);

    // Sổ giao dịch cho module Tài chính (tùy chọn: lỗi ở đây không làm hỏng việc bán)
    try {
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $name = (string)$stmt->fetchColumn();
        $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                       VALUES (?, ?, \'income\', \'sale\', ?, ?, ?)')
            ->execute([$ownerId, $shopId, $result['revenue'], $result['balance'], "Bán $quantity x $name (cỡ $size)"]);
    } catch (Exception $e) {
        error_log('sell-product: không ghi được bảng transactions: ' . $e->getMessage());
    }

    $pdo->commit();

    $result['size'] = $size;
    apiRespond(true, "Bán hàng thành công", $result);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('sell-product: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý bán hàng", null, 500);
}
