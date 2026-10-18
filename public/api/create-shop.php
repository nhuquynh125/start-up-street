<?php
// POST api/create-shop.php  {owner_user_id, property_id, business_type_id, name}
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $input = readJsonBody();

    $ownerUserId    = toPositiveInt($input['owner_user_id'] ?? null);
    $propertyId     = toPositiveInt($input['property_id'] ?? null);
    $businessTypeId = toPositiveInt($input['business_type_id'] ?? null);
    $name           = trim((string)($input['name'] ?? ''));

    if (!$ownerUserId || !$propertyId || !$businessTypeId || $name === '') {
        throw new ApiException("Thiếu hoặc sai thông tin bắt buộc (owner_user_id, property_id, business_type_id, name)", 400);
    }
    if (textLength($name) > 100) {
        throw new ApiException("Tên cửa hàng tối đa 100 ký tự", 400);
    }

    // Loại hình phải tồn tại
    $stmt = $pdo->prepare('SELECT id FROM business_types WHERE id = ?');
    $stmt->execute([$businessTypeId]);
    if (!$stmt->fetch()) {
        throw new ApiException("Loại hình kinh doanh không tồn tại", 404);
    }

    // Mỗi mặt bằng chỉ có 1 cửa hàng
    $stmt = $pdo->prepare('SELECT id, owner_user_id FROM shops WHERE property_id = ?');
    $stmt->execute([$propertyId]);
    if ($existing = $stmt->fetch()) {
        throw new ApiException("Mặt bằng này đã có cửa hàng", 409, ["shop_id" => (int)$existing['id']]);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO shops (owner_user_id, property_id, business_type_id, name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ownerUserId, $propertyId, $businessTypeId, $name]);
    } catch (PDOException $e) {
        // 23000 = vi phạm unique/foreign key (2 người cùng bấm thuê, hoặc user/property không tồn tại)
        if ($e->getCode() === '23000') {
            throw new ApiException("Không thể tạo cửa hàng: mặt bằng đã có chủ hoặc người chơi/mặt bằng không tồn tại", 409);
        }
        throw $e;
    }

    apiRespond(true, "Tạo cửa hàng thành công", [
        "shop_id"          => (int)$pdo->lastInsertId(),
        "name"             => $name,
        "property_id"      => $propertyId,
        "business_type_id" => $businessTypeId
    ], 201);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Exception $e) {
    apiRespond(false, "Lỗi truy vấn: " . $e->getMessage(), null, 500);
}
