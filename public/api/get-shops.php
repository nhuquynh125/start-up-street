<?php
// GET api/get-shops.php?owner_user_id=1      -> các cửa hàng của người chơi
// GET api/get-shops.php                       -> tất cả cửa hàng (để bản đồ biết mặt bằng nào đã có chủ)
// GET api/get-shops.php?shop_id=5&owner_user_id=1 -> 1 cửa hàng (có kiểm tra quyền)
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $ownerId = isset($_GET['owner_user_id']) ? toPositiveInt($_GET['owner_user_id']) : null;
    if (isset($_GET['owner_user_id']) && $ownerId === null) {
        throw new ApiException("owner_user_id không hợp lệ", 400);
    }

    $sql = 'SELECT s.id AS shop_id, s.name, s.owner_user_id, s.property_id, s.business_type_id,
                   bt.name AS business_type_name
            FROM shops s
            JOIN business_types bt ON bt.id = s.business_type_id';
    $params = [];

    if (isset($_GET['shop_id'])) {
        $shopId = toPositiveInt($_GET['shop_id']);
        if ($shopId === null || $ownerId === null) {
            throw new ApiException("Cần shop_id và owner_user_id hợp lệ", 400);
        }
        requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403 nếu sai
        $sql .= ' WHERE s.id = ?';
        $params[] = $shopId;
    } elseif ($ownerId !== null) {
        $sql .= ' WHERE s.owner_user_id = ?';
        $params[] = $ownerId;
    }
    $sql .= ' ORDER BY s.id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiRespond(true, "Lấy danh sách cửa hàng thành công", ["shops" => $stmt->fetchAll()]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Exception $e) {
    apiRespond(false, "Lỗi truy vấn: " . $e->getMessage(), null, 500);
}
