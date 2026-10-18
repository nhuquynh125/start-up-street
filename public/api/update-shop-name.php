<?php
// POST api/update-shop-name.php  {shop_id, name}
// Đồng bộ tên cửa hàng người chơi đặt trong map.php (localStorage) lên shops.name trong MySQL.
// Chủ cửa hàng = người đang đăng nhập (getCurrentUserId), KHÔNG nhận owner_user_id từ client.
// Thứ tự kiểm tra: đăng nhập (401) -> dữ liệu vào (400) -> cửa hàng tồn tại (404) / đúng chủ (403) -> UPDATE
// Gọi lại với cùng tên là an toàn (idempotent): vẫn trả success.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

const MAX_SHOP_NAME_LENGTH = 100; // khớp với create-shop.php và cột shops.name VARCHAR(100)

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input = readJsonBody();

    $shopId = toPositiveInt($input['shop_id'] ?? null);
    $name   = $input['name'] ?? null;
    if (!$shopId || !is_string($name)) {
        throw new ApiException("Thiếu hoặc sai thông tin bắt buộc (shop_id, name)", 400);
    }

    // Bỏ ký tự điều khiển (xuống dòng, tab...) rồi cắt khoảng trắng hai đầu
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '');
    if ($name === '') {
        throw new ApiException("Tên cửa hàng không được để trống", 400);
    }
    if (textLength($name) > MAX_SHOP_NAME_LENGTH) {
        throw new ApiException("Tên cửa hàng tối đa " . MAX_SHOP_NAME_LENGTH . " ký tự", 400);
    }

    requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403

    // owner_user_id trong WHERE là lớp bảo vệ thứ 2 ngoài requireOwnedShop
    $stmt = $pdo->prepare('UPDATE shops SET name = ? WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$name, $shopId, $ownerId]);
    // rowCount() = 0 khi tên không đổi: không phải lỗi

    apiRespond(true, "Đổi tên cửa hàng thành công", [
        "shop_id" => $shopId,
        "name"    => $name,
    ]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('update-shop-name: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý đổi tên cửa hàng", null, 500);
}
