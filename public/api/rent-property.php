<?php
// POST api/rent-property.php  {property_id}
// Người thuê = người đang đăng nhập (getCurrentUserId), KHÔNG nhận user_id từ client.
// Thuê mặt bằng: khóa ví -> khóa mặt bằng -> kiểm tra chưa ai thuê -> kiểm tra đủ tiền -> trừ users.balance -> ghi rentals.
// Giá thuê lấy từ bảng properties (server), KHÔNG nhận giá từ frontend.
// Gọi lại với cùng user + cùng mặt bằng là an toàn (idempotent): không trừ tiền lần 2, trả already_rented = true.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

// ── Bảng mặt bằng (migration 03). Nếu TV2 có bảng khác thì chỉ sửa 4 dòng này ──
const PROPERTIES_TABLE        = 'properties';
const PROPERTIES_ID_COL       = 'id';
const PROPERTIES_PRICE_COL    = 'price';
const PROPERTIES_STATUS_COL   = 'status'; // 'available' = còn cho thuê

try {
    $userId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input = readJsonBody();

    $propertyId = toPositiveInt($input['property_id'] ?? null);
    if (!$propertyId) {
        throw new ApiException("Thiếu hoặc sai property_id", 400);
    }

    $pdo->beginTransaction();

    // 1) Khóa ví trước (cùng thứ tự khóa với các API khác)
    $balance = getWalletBalance($pdo, $userId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }

    // 2) Khóa dòng mặt bằng để 2 người cùng bấm thuê sẽ được xếp hàng
    $stmt = $pdo->prepare(sprintf(
        'SELECT %s AS price, %s AS status FROM %s WHERE %s = ? FOR UPDATE',
        PROPERTIES_PRICE_COL, PROPERTIES_STATUS_COL, PROPERTIES_TABLE, PROPERTIES_ID_COL
    ));
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    if (!$property) {
        throw new ApiException("Không tìm thấy mặt bằng", 404);
    }

    // 3) Đã có người thuê chưa?
    $stmt = $pdo->prepare('SELECT user_id FROM rentals WHERE property_id = ? FOR UPDATE');
    $stmt->execute([$propertyId]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ((int)$existing['user_id'] === $userId) {
            $pdo->commit();
            apiRespond(true, "Bạn đã thuê mặt bằng này rồi", [
                "property_id"    => $propertyId,
                "price_paid"     => 0,
                "balance"        => $balance,
                "already_rented" => true,
            ]);
        }
        throw new ApiException("Mặt bằng này đã có người thuê", 409);
    }
    if ($property['status'] !== 'available') {
        throw new ApiException("Mặt bằng này không còn cho thuê", 409);
    }

    // 4) Đủ tiền thì trừ ví (deductWallet vẫn kiểm tra lại balance >= giá ngay trong câu UPDATE)
    $price = (float)$property['price'];
    if ($balance < $price) {
        throw new ApiException("Bạn không đủ tiền để thuê mặt bằng này", 400, ["required" => $price, "balance" => $balance]);
    }
    if (!deductWallet($pdo, $userId, $price)) {
        throw new ApiException("Bạn không đủ tiền để thuê mặt bằng này", 400, ["required" => $price, "balance" => $balance]);
    }
    $newBalance = round($balance - $price, 2);

    // 5) Ghi quyền thuê (UNIQUE property_id là chốt chặn cuối cùng chống thuê trùng)
    try {
        $pdo->prepare('INSERT INTO rentals (property_id, user_id, price_paid) VALUES (?, ?, ?)')
            ->execute([$propertyId, $userId, $price]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new ApiException("Mặt bằng này đã có người thuê", 409);
        }
        throw $e;
    }

    // 6) Ghi sổ chi phí cho module Tài chính (tùy chọn: lỗi ở đây không làm hỏng việc thuê)
    try {
        $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                       VALUES (?, NULL, \'expense\', \'rent\', ?, ?, ?)')
            ->execute([$userId, $price, $newBalance, "Thuê mặt bằng #$propertyId"]);
    } catch (Exception $e) {
        error_log('rent-property: không ghi được bảng transactions: ' . $e->getMessage());
    }

    $pdo->commit();

    apiRespond(true, "Thuê mặt bằng thành công", [
        "property_id"    => $propertyId,
        "price_paid"     => $price,
        "balance"        => $newBalance,
        "already_rented" => false,
    ], 201);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('rent-property: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý thuê mặt bằng", null, 500);
}
