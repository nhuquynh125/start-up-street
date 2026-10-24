<?php
// POST api/add-product.php  {shop_id, name, base_cost}
// Chủ quán thêm MỘT MẶT HÀNG MỚI vào cửa hàng của mình (mặt hàng chỉ thuộc cửa hàng đó: products.shop_id).
// Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id từ client.
//
// - Thêm mặt hàng KHÔNG tốn tiền và KHÔNG tạo dòng kho: tồn kho = 0 cho tới khi người chơi nhập hàng
//   (purchase-inventory.php / restock-bulk.php), lúc đó mới đặt giá bán. Nhờ vậy khách không "đòi" món chưa có hàng.
// - Giá vốn do người chơi đặt nhưng bị chặn trần (CUSTOM_PRODUCT_MAX_COST) để không tự bịa ra món lãi khổng lồ.
// Cần chạy database/migrations/05_custom_products.sql (cột products.shop_id) trước.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

const CUSTOM_PRODUCT_MAX_PER_SHOP = 20;       // mỗi cửa hàng tối đa 20 món tự thêm
const CUSTOM_PRODUCT_MIN_COST     = 1000;     // giá vốn tối thiểu
const CUSTOM_PRODUCT_MAX_COST     = 200000;   // giá vốn tối đa (món có sẵn cao nhất hiện là 120.000đ). Đổi ở đây để cân bằng game
const CUSTOM_PRODUCT_MAX_NAME_LEN = 100;      // khớp products.name VARCHAR(100)

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input  = readJsonBody();
    $shopId = toPositiveInt($input['shop_id'] ?? null);
    $name   = $input['name'] ?? null;
    $cost   = toPositiveNumber($input['base_cost'] ?? null);

    if (!$shopId || !is_string($name) || $cost === null) {
        throw new ApiException("Thiếu hoặc sai thông tin bắt buộc (shop_id, name, base_cost)", 400);
    }

    // Bỏ ký tự điều khiển rồi cắt khoảng trắng hai đầu
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '');
    if ($name === '') {
        throw new ApiException("Tên mặt hàng không được để trống", 400);
    }
    if (textLength($name) > CUSTOM_PRODUCT_MAX_NAME_LEN) {
        throw new ApiException("Tên mặt hàng tối đa " . CUSTOM_PRODUCT_MAX_NAME_LEN . " ký tự", 400);
    }
    $cost = round($cost, 2);
    if ($cost < CUSTOM_PRODUCT_MIN_COST || $cost > CUSTOM_PRODUCT_MAX_COST) {
        throw new ApiException(
            "Giá vốn phải từ " . number_format(CUSTOM_PRODUCT_MIN_COST, 0, ',', '.') . "đ đến "
            . number_format(CUSTOM_PRODUCT_MAX_COST, 0, ',', '.') . "đ",
            400
        );
    }

    $pdo->beginTransaction();

    // Khóa dòng cửa hàng: 2 request thêm món cùng lúc sẽ được xếp hàng (không vượt giới hạn / không trùng tên)
    $shop = requireOwnedShop($pdo, $shopId, $ownerId, true); // 404 / 403

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    if ((int)$stmt->fetchColumn() >= CUSTOM_PRODUCT_MAX_PER_SHOP) {
        throw new ApiException("Mỗi cửa hàng chỉ thêm được tối đa " . CUSTOM_PRODUCT_MAX_PER_SHOP . " mặt hàng riêng", 409);
    }

    // Trùng tên với món có sẵn của loại hình hoặc món đã thêm (collation utf8mb4_unicode_ci: không phân biệt hoa/thường, dấu)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE business_type_id = ? AND (shop_id IS NULL OR shop_id = ?) AND name = ?');
    $stmt->execute([(int)$shop['business_type_id'], $shopId, $name]);
    if ((int)$stmt->fetchColumn() > 0) {
        throw new ApiException("Cửa hàng đã có mặt hàng tên \"$name\"", 409);
    }

    try {
        $pdo->prepare('INSERT INTO products (business_type_id, name, base_cost, shop_id) VALUES (?, ?, ?, ?)')
            ->execute([(int)$shop['business_type_id'], $name, $cost, $shopId]);
    } catch (PDOException $e) {
        // 42S22 = thiếu cột shop_id (chưa chạy migration 05)
        if ($e->getCode() === '42S22') {
            error_log('add-product: thiếu cột products.shop_id: ' . $e->getMessage());
            throw new ApiException("Chưa có cột products.shop_id. Hãy chạy database/migrations/05_custom_products.sql", 500);
        }
        throw $e;
    }
    $productId = (int)$pdo->lastInsertId();

    $pdo->commit();

    apiRespond(true, "Đã thêm mặt hàng mới", [
        "shop_id"       => $shopId,
        "product_id"    => $productId,
        "product_name"  => $name,
        "base_cost"     => $cost,
        "selling_price" => round($cost * DEFAULT_MARKUP, 2), // giá bán gợi ý; giá thật được lưu khi nhập hàng
    ], 201);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('add-product: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý thêm mặt hàng", null, 500);
}
