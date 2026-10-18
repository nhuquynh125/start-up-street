<?php
// POST api/purchase-inventory.php  {owner_user_id, shop_id, product_id, quantity, selling_price?}
// Nhập hàng: kiểm tra quyền -> kiểm tra đủ tiền -> trừ ví -> cộng kho. Tất cả trong 1 transaction:
// không đủ tiền thì kho KHÔNG bị thay đổi.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $input = readJsonBody();

    $ownerId   = toPositiveInt($input['owner_user_id'] ?? null);
    $shopId    = toPositiveInt($input['shop_id'] ?? null);
    $productId = toPositiveInt($input['product_id'] ?? null);
    $quantity  = toPositiveInt($input['quantity'] ?? null);
    $hasPrice  = array_key_exists('selling_price', $input) && $input['selling_price'] !== null && $input['selling_price'] !== '';
    $sellingPrice = $hasPrice ? toPositiveNumber($input['selling_price']) : null;

    if (!$ownerId || !$shopId || !$productId || !$quantity || $quantity > MAX_RESTOCK_QTY) {
        throw new ApiException("Thông tin nhập hàng không hợp lệ (owner_user_id, shop_id, product_id, quantity từ 1 đến " . MAX_RESTOCK_QTY . ")", 400);
    }
    if ($hasPrice && $sellingPrice === null) {
        throw new ApiException("Giá bán phải là số lớn hơn 0", 400);
    }

    $pdo->beginTransaction();

    // Khóa ví của người chơi để 2 lần nhập hàng cùng lúc không trừ tiền chồng nhau
    $balance = getWalletBalance($pdo, $ownerId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }

    $shop = requireOwnedShop($pdo, $shopId, $ownerId, true); // 404 / 403

    // Sản phẩm phải thuộc đúng loại hình của cửa hàng
    $stmt = $pdo->prepare('SELECT id, name, base_cost FROM products WHERE id = ? AND business_type_id = ?');
    $stmt->execute([$productId, $shop['business_type_id']]);
    $product = $stmt->fetch();
    if (!$product) {
        throw new ApiException("Sản phẩm không thuộc loại hình kinh doanh của cửa hàng này", 404);
    }

    $totalCost = (float)$product['base_cost'] * $quantity;
    if ($balance < $totalCost) {
        throw new ApiException("Không đủ tiền để nhập hàng", 400, ["required" => $totalCost, "balance" => $balance]);
    }
    if (!deductWallet($pdo, $ownerId, $totalCost)) {
        throw new ApiException("Không đủ tiền để nhập hàng", 400, ["required" => $totalCost, "balance" => $balance]);
    }
    $newBalance = $balance - $totalCost;

    // Cộng kho (tạo dòng mới nếu chưa có)
    $stmt = $pdo->prepare('SELECT id, quantity, selling_price FROM inventory WHERE shop_id = ? AND product_id = ? FOR UPDATE');
    $stmt->execute([$shopId, $productId]);
    $inv = $stmt->fetch();

    if ($inv) {
        $newQty   = (int)$inv['quantity'] + $quantity;
        $newPrice = $sellingPrice !== null ? $sellingPrice : (float)$inv['selling_price'];
        $pdo->prepare('UPDATE inventory SET quantity = ?, selling_price = ?, last_restocked = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$newQty, $newPrice, $inv['id']]);
    } else {
        $newQty   = $quantity;
        $newPrice = $sellingPrice !== null ? $sellingPrice : (float)$product['base_cost'] * DEFAULT_MARKUP;
        $pdo->prepare('INSERT INTO inventory (shop_id, product_id, quantity, selling_price) VALUES (?, ?, ?, ?)')
            ->execute([$shopId, $productId, $newQty, $newPrice]);
    }

    // Ghi sổ chi phí cho module Tài chính (TV4). Bảng này là tùy chọn: lỗi ở đây không làm hỏng việc nhập hàng.
    try {
        $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                       VALUES (?, ?, \'expense\', \'material\', ?, ?, ?)')
            ->execute([$ownerId, $shopId, $totalCost, $newBalance, "Nhập $quantity x " . $product['name']]);
    } catch (Exception $e) {
        error_log('purchase-inventory: không ghi được bảng transactions: ' . $e->getMessage());
    }

    $pdo->commit();

    apiRespond(true, "Nhập hàng thành công", [
        "shop_id"        => $shopId,
        "product_id"     => $productId,
        "quantity_added" => $quantity,
        "stock_quantity" => $newQty,
        "selling_price"  => $newPrice,
        "total_cost"     => $totalCost,
        "balance"        => $newBalance
    ]);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, "Lỗi xử lý kho: " . $e->getMessage(), null, 500);
}
