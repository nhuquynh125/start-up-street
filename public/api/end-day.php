<?php
// POST api/end-day.php  {shop_id}
// CHỐT CA: chuyển TOÀN BỘ doanh thu đang nằm trong két quán (shop_runtime.till_balance) vào ví thật (users.balance)
// và ghi 1 dòng income/sale vào transactions.
// Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id / số tiền từ client: số tiền do server tính.
//
// Vì sao chuyển "doanh thu" chứ không phải "lãi":
//   tiền nhập hàng ĐÃ bị trừ khỏi ví ngay lúc nhập (purchase-inventory / restock-bulk). Nếu chỉ chuyển phần lãi
//   (doanh thu - giá vốn) thì giá vốn bị trừ hai lần. Lãi ca (revenue - cogs) chỉ để BÁO CÁO.
//
// Quy trình:
//  1. shopRuntimeTick: chốt nốt khách tới giờ + thu phí tháng nếu tới hạn (không đổi trạng thái mở/đóng cửa)
//  2. Transaction mới: khóa ví -> khóa cửa hàng -> khóa shop_runtime
//  3. Báo cáo ca = các đơn có id > last_settled_order_id (đơn served / missed, doanh thu, giá vốn hàng đã bán)
//  4. Cộng két vào ví, ghi sổ, đưa két về 0, dời mốc last_settled_order_id
//
// Gọi lại khi két = 0 là an toàn: không cộng gì, trả transferred = 0.
// Cần chạy database/migrations/06_settlement.sql trước.
require_once dirname(__DIR__, 2) . '/lib/shop_simulation.php'; // kéo theo shop_helpers.php + shop_billing.php

requireMethod('POST');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input  = readJsonBody();
    $shopId = toPositiveInt($input['shop_id'] ?? null);
    if (!$shopId) {
        throw new ApiException("Thiếu hoặc sai shop_id", 400);
    }

    // Bước 1 (tự quản lý transaction bên trong, commit xong mới chạy tiếp)
    shopRuntimeTick($pdo, $shopId, $ownerId, null, 0);

    // Bước 2: transaction chốt ca. Thứ tự khóa: ví -> cửa hàng -> shop_runtime
    $pdo->beginTransaction();

    $balance = getWalletBalance($pdo, $ownerId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }
    $shop = requireOwnedShop($pdo, $shopId, $ownerId, true); // 404 / 403

    $stmt = $pdo->prepare('SELECT till_balance, last_settled_order_id FROM shop_runtime WHERE shop_id = ? FOR UPDATE');
    $stmt->execute([$shopId]);
    $rt = $stmt->fetch();
    if (!$rt) {
        throw new ApiException("Quán chưa có dữ liệu vận hành", 404);
    }
    $till    = round((float)$rt['till_balance'], 2);
    $sinceId = (int)$rt['last_settled_order_id'];

    // Bước 3: báo cáo ca (giá vốn = số lượng bán x products.base_cost)
    $stmt = $pdo->prepare('SELECT o.status, COUNT(*) AS c,
                                  COALESCE(SUM(o.total), 0) AS revenue,
                                  COALESCE(SUM(o.quantity * p.base_cost), 0) AS cogs
                           FROM shop_orders o
                           LEFT JOIN products p ON p.id = o.product_id
                           WHERE o.shop_id = ? AND o.id > ?
                           GROUP BY o.status');
    $stmt->execute([$shopId, $sinceId]);
    $served = 0; $missed = 0; $revenue = 0.0; $cogs = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        if ($row['status'] === 'served') {
            $served  = (int)$row['c'];
            $revenue = (float)$row['revenue'];
            $cogs    = (float)$row['cogs'];
        } else {
            $missed = (int)$row['c'];
        }
    }
    $revenue = round($revenue, 2);
    $cogs    = round($cogs, 2);
    $profit  = round($revenue - $cogs, 2);

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM shop_orders WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $lastOrderId = (int)$stmt->fetchColumn();

    // Bước 4: két -> ví
    $newBalance = $balance;
    if ($till > 0) {
        if (!creditWallet($pdo, $ownerId, $till)) {
            throw new ApiException("Không thể cộng tiền vào ví", 500);
        }
        $newBalance = round($balance + $till, 2);

        try { // sổ cái là tùy chọn: lỗi ở đây không làm hỏng việc chốt ca
            $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                           VALUES (?, ?, \'income\', \'sale\', ?, ?, ?)')
                ->execute([$ownerId, $shopId, $till, $newBalance,
                           "Chốt ca: doanh thu $served đơn - " . $shop['name']]);
        } catch (Exception $e) {
            error_log('end-day: không ghi được bảng transactions: ' . $e->getMessage());
        }
    }

    $pdo->prepare('UPDATE shop_runtime SET till_balance = 0, last_settled_order_id = ? WHERE shop_id = ?')
        ->execute([$lastOrderId, $shopId]);

    $pdo->commit();

    apiRespond(true, $till > 0 ? "Chốt ca thành công" : "Chưa có doanh thu mới để chốt", [
        "shop_id"       => $shopId,
        "orders_served" => $served,
        "orders_missed" => $missed,
        "revenue"       => $revenue,      // doanh thu các đơn trong ca (để báo cáo)
        "cogs"          => $cogs,         // giá vốn hàng đã bán trong ca (để báo cáo)
        "profit"        => $profit,       // lãi ca = revenue - cogs (chỉ để báo cáo)
        "transferred"   => $till,         // số tiền THẬT vừa chuyển vào ví
        "balance"       => $newBalance,
    ]);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException && $e->getCode() === '42S22') {
        error_log('end-day: thiếu cột: ' . $e->getMessage());
        apiRespond(false, "Thiếu cột két quán. Hãy chạy database/migrations/06_settlement.sql (php tools/run_migration.php)", null, 500);
    }
    error_log('end-day: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý chốt ca", null, 500);
}
