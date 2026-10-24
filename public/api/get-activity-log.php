<?php
// GET api/get-activity-log.php?shop_id=5[&limit=300]
// Nhật ký hoạt động đã LƯU của quán: các khách đã vào quán trong 7 ngày chơi gần nhất (cũ hơn đã bị end-day.php xóa).
// Trả theo thứ tự cũ -> mới (client vẽ lần lượt, đơn mới nhất nằm trên cùng).
// Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id từ client.
require_once dirname(__DIR__, 2) . '/lib/shop_reports.php';

requireMethod('GET');

const ACTIVITY_LOG_DEFAULT_LIMIT = 300;
const ACTIVITY_LOG_MAX_LIMIT     = 1000;

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $shopId = toPositiveInt($_GET['shop_id'] ?? null);
    if ($shopId === null) {
        throw new ApiException("Thiếu hoặc sai shop_id", 400);
    }
    requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403

    $limit = toPositiveInt($_GET['limit'] ?? null) ?? ACTIVITY_LOG_DEFAULT_LIMIT;
    $limit = min($limit, ACTIVITY_LOG_MAX_LIMIT);

    try {
        $stmt = $pdo->prepare('SELECT day_number FROM shop_runtime WHERE shop_id = ?');
        $stmt->execute([$shopId]);
        $day = $stmt->fetchColumn();
        $currentDay = $day === false ? 1 : (int)$day;

        // LIMIT phải là số nguyên viết thẳng (PDO không bind LIMIT khi tắt emulate prepares); $limit đã ép int ở trên
        $stmt = $pdo->prepare('SELECT * FROM (
                                   SELECT id, product_id, product_name, quantity, total, status, customer_name, arrived_ts, day_number
                                   FROM shop_orders WHERE shop_id = ? ORDER BY id DESC LIMIT ' . (int)$limit . '
                               ) t ORDER BY id ASC');
        $stmt->execute([$shopId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
            throw new ApiException("Chưa có bảng nhật ký. Hãy chạy database/migrations/04_shop_runtime.sql và 05_game_days_reports.sql", 500);
        }
        throw $e;
    }

    $orders = array_map(function ($o) {
        return [
            'id'           => (int)$o['id'],
            'product_id'   => $o['product_id'] === null ? null : (int)$o['product_id'],
            'product_name' => $o['product_name'],
            'quantity'     => (int)$o['quantity'],
            'total'        => (float)$o['total'],
            'status'       => $o['status'],
            'customer'     => $o['customer_name'],
            'arrived_ts'   => (int)$o['arrived_ts'],
            'day_number'   => (int)$o['day_number'],
        ];
    }, $stmt->fetchAll());

    apiRespond(true, "Lấy nhật ký hoạt động thành công", [
        "shop_id"        => $shopId,
        "current_day"    => $currentDay,
        "retention_days" => ORDER_RETENTION_DAYS,
        "oldest_day"     => max(1, $currentDay - ORDER_RETENTION_DAYS + 1),
        "orders"         => $orders,
    ]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('get-activity-log: ' . $e->getMessage());
    apiRespond(false, "Lỗi truy vấn nhật ký hoạt động", null, 500);
}
