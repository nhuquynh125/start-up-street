<?php
// GET api/get-shops.php                -> các cửa hàng của NGƯỜI CHƠI ĐANG ĐĂNG NHẬP (không còn trả cửa hàng của người khác)
// GET api/get-shops.php?shop_id=5      -> 1 cửa hàng của người đó (404 / 403 nếu không có / không phải của họ)
// Không nhận owner_user_id: nếu client có gửi thì bị bỏ qua.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $sql = 'SELECT s.id AS shop_id, s.name, s.owner_user_id, s.property_id, s.business_type_id,
                   bt.name AS business_type_name
            FROM shops s
            JOIN business_types bt ON bt.id = s.business_type_id
            WHERE s.owner_user_id = ?';
    $params = [$ownerId];

    if (isset($_GET['shop_id'])) {
        $shopId = toPositiveInt($_GET['shop_id']);
        if ($shopId === null) {
            throw new ApiException("shop_id không hợp lệ", 400);
        }
        requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403 nếu sai
        $sql .= ' AND s.id = ?';
        $params[] = $shopId;
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
