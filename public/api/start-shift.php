<?php
// POST api/start-shift.php  {shop_id}
// Mở ca: server trừ chi phí vận hành cố định (nhân viên + điện nước) khỏi ví và ghi sổ transactions.
// Tiền thuê mặt bằng KHÔNG tính ở đây vì đã trả một lần lúc thuê (rent-property.php) => không bị trừ lặp.
// Số tiền do HẰNG SỐ phía server quyết định; request không có và không được phép có trường tiền.
// Trả chi phí ở đầu ca (không phải cuối ca) nên người chơi không thể né phí bằng cách bỏ qua bước kết ca.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

const SHIFT_STAFF_COST     = 35000;
const SHIFT_UTILITIES_COST = 15000;

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input  = readJsonBody();
    $shopId = toPositiveInt($input['shop_id'] ?? null);
    if (!$shopId) {
        throw new ApiException("Thiếu hoặc sai shop_id", 400);
    }

    $pdo->beginTransaction();

    // Thứ tự khóa: ví -> cửa hàng (giống purchase-inventory.php) để không deadlock
    $balance = getWalletBalance($pdo, $ownerId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }
    requireOwnedShop($pdo, $shopId, $ownerId, true); // 404 / 403

    $total = SHIFT_STAFF_COST + SHIFT_UTILITIES_COST;
    if ($balance < $total || !deductWallet($pdo, $ownerId, $total)) {
        throw new ApiException("Không đủ tiền trả chi phí mở ca", 400, ["required" => $total, "balance" => $balance]);
    }
    $afterStaff = round($balance - SHIFT_STAFF_COST, 2);
    $newBalance = round($balance - $total, 2);

    // Ghi sổ (tùy chọn: lỗi ở đây không làm hỏng việc mở ca)
    try {
        $ins = $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                              VALUES (?, ?, \'expense\', ?, ?, ?, ?)');
        $ins->execute([$ownerId, $shopId, 'salary',    SHIFT_STAFF_COST,     $afterStaff, 'Lương nhân viên ca làm']);
        $ins->execute([$ownerId, $shopId, 'utilities', SHIFT_UTILITIES_COST, $newBalance, 'Điện nước ca làm']);
    } catch (Exception $e) {
        error_log('start-shift: không ghi được bảng transactions: ' . $e->getMessage());
    }

    $pdo->commit();

    apiRespond(true, "Mở ca thành công", [
        "shop_id"        => $shopId,
        "staff_cost"     => SHIFT_STAFF_COST,
        "utilities_cost" => SHIFT_UTILITIES_COST,
        "fixed_cost"     => $total,
        "balance"        => $newBalance,
    ], 201);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('start-shift: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý mở ca", null, 500);
}
