<?php
// GET api/get-occupied-properties.php
// Cho bản đồ biết mặt bằng nào đã có người thuê HOẶC thuộc NPC mà KHÔNG lộ ai là chủ:
// trả property_id + is_mine.
// is_mine = true chỉ khi chính người chơi đang đăng nhập thuê mặt bằng đó.
// Mặt bằng NPC (status='npc') được tính là 'owned' (is_mine=false) từ phía bản đồ.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('GET');

try {
    $userId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    // Mặt bằng đã có người chơi thuê
    $stmt = $pdo->prepare('SELECT property_id, (user_id = ?) AS is_mine FROM rentals ORDER BY property_id');
    $stmt->execute([$userId]);

    $properties = [];
    $rented = [];
    foreach ($stmt->fetchAll() as $row) {
        $pid = (int)$row['property_id'];
        $rented[$pid] = true;
        $properties[] = [
            "property_id" => $pid,
            "is_mine"     => (int)$row['is_mine'] === 1,
        ];
    }

    // Mặt bằng NPC (không cho thuê, không phải của ai)
    try {
        $stmt2 = $pdo->prepare("SELECT id AS property_id FROM properties WHERE status = 'npc'");
        $stmt2->execute();
        foreach ($stmt2->fetchAll() as $row) {
            $pid = (int)$row['property_id'];
            if (isset($rented[$pid])) continue; // đã xử lý ở trên (không thể xảy ra nhưng phòng thủ)
            $properties[] = [
                "property_id" => $pid,
                "is_mine"     => false,
            ];
        }
    } catch (Exception $e) {
        // Bảng properties chưa tồn tại (migration 03 chưa chạy) → bỏ qua, chỉ trả rentals
        error_log('get-occupied-properties: bảng properties không có / query lỗi: ' . $e->getMessage());
    }

    apiRespond(true, "Lấy danh sách mặt bằng đã thuê thành công", ["properties" => $properties]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('get-occupied-properties: ' . $e->getMessage());
    apiRespond(false, "Lỗi truy vấn mặt bằng", null, 500);
}
