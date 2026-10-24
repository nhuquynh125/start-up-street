<?php
// POST api/close-shop.php  {shop_id, confirm_name}
// ĐÓNG CỬA VĨNH VIỄN (không hoàn tác). Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id từ client.
//
// Quy trình:
//  0. Kiểm tra đăng nhập (401) -> dữ liệu vào (400) -> quán tồn tại / đúng chủ (404/403) -> gõ đúng tên quán (400)
//  1. Đóng cửa quán nếu đang mở + chốt nốt khách đến lúc đóng (dùng lại shopRuntimeTick, có transaction riêng)
//  2. Mở transaction mới: khóa ví -> khóa cửa hàng -> khóa shop_runtime -> khóa kho
//  3. THANH LÝ kho: hàng còn lại bán lại cho "nhà cung cấp" với CLOSE_REFUND_RATE x giá vốn, cộng vào ví
//  4. Xóa shop_orders, shop_runtime, inventory, shops; GỠ rentals để mặt bằng quay lại thị trường
//     (tiền thuê đã trả KHÔNG được hoàn)
//  5. Ghi sổ transactions (bảng sổ cái giữ nguyên lịch sử thu chi cũ của quán)
//
// Thứ tự khóa: ví -> cửa hàng -> shop_runtime -> kho. Giống các API khác (ví luôn khóa TRƯỚC) để không deadlock.
require_once dirname(__DIR__, 2) . '/lib/shop_simulation.php'; // kéo theo shop_helpers.php

requireMethod('POST');

const CLOSE_REFUND_RATE = 0.5; // thanh lý kho = 50% giá vốn. Đổi ở đây để cân bằng game

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input   = readJsonBody();
    $shopId  = toPositiveInt($input['shop_id'] ?? null);
    $confirm = $input['confirm_name'] ?? null;
    if (!$shopId || !is_string($confirm)) {
        throw new ApiException("Thiếu hoặc sai thông tin bắt buộc (shop_id, confirm_name)", 400);
    }

    // Kiểm tra sớm (chưa khóa gì): đúng chủ + gõ đúng tên. Sai tên thì dừng ngay, chưa đụng tới dữ liệu.
    $shop = requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403
    if (trim($confirm) !== $shop['name']) {
        throw new ApiException("Tên xác nhận không khớp. Hãy gõ đúng tên cửa hàng \"" . $shop['name'] . "\"", 400);
    }

    // Bước 1: đóng cửa + chốt khách (tự quản lý transaction bên trong, commit xong mới chạy tiếp)
    shopRuntimeTick($pdo, $shopId, $ownerId, false, 0);

    // Bước 2: transaction đóng cửa
    $pdo->beginTransaction();

    $balance = getWalletBalance($pdo, $ownerId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }
    // Kiểm tra lại dưới khóa: nếu 2 tab cùng bấm đóng, tab thứ hai sẽ nhận 404 ở đây
    $shop = requireOwnedShop($pdo, $shopId, $ownerId, true);

    // Giữa bước 1 và bước 2 có thể có tab khác mở lại quán -> chặn
    $stmt = $pdo->prepare('SELECT is_open FROM shop_runtime WHERE shop_id = ? FOR UPDATE');
    $stmt->execute([$shopId]);
    $isOpen = $stmt->fetchColumn();
    if ($isOpen !== false && (int)$isOpen === 1) {
        throw new ApiException("Quán vừa được mở lại ở nơi khác, hãy thử đóng lại", 409);
    }

    // Bước 3: thanh lý kho
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM inventory WHERE shop_id = ? FOR UPDATE');
    $stmt->execute([$shopId]);
    $stock = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, base_cost FROM products WHERE business_type_id = ?');
    $stmt->execute([(int)$shop['business_type_id']]);
    $costs = [];
    foreach ($stmt->fetchAll() as $p) {
        $costs[(int)$p['id']] = (float)$p['base_cost'];
    }

    $stockValue = 0.0;
    $units      = 0;
    foreach ($stock as $row) {
        $qty = (int)$row['quantity'];
        $stockValue += $qty * ($costs[(int)$row['product_id']] ?? 0.0);
        $units      += $qty;
    }
    $refund     = round($stockValue * CLOSE_REFUND_RATE, 2);
    $newBalance = $balance;
    if ($refund > 0) {
        if (!creditWallet($pdo, $ownerId, $refund)) {
            throw new ApiException("Không thể cộng tiền thanh lý vào ví", 500);
        }
        $newBalance = round($balance + $refund, 2);
    }

    // Bước 5 (ghi trước khi xóa cho dễ đọc): sổ cái. Tùy chọn: lỗi ở đây không làm hỏng việc đóng cửa
    if ($refund > 0) {
        try {
            $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                           VALUES (?, ?, \'income\', \'other\', ?, ?, ?)')
                ->execute([$ownerId, $shopId, $refund, $newBalance,
                           "Thanh lý $units món khi đóng cửa \"" . $shop['name'] . "\""]);
        } catch (Exception $e) {
            error_log('close-shop: không ghi được bảng transactions: ' . $e->getMessage());
        }
    }

    // Bước 4: xóa dữ liệu của quán. Bảng vận hành (migration 04) có thể chưa tồn tại -> bỏ qua nếu 42S02.
    foreach (['shop_orders', 'shop_runtime'] as $table) {
        try {
            $pdo->prepare("DELETE FROM $table WHERE shop_id = ?")->execute([$shopId]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
    }
    $pdo->prepare('DELETE FROM inventory WHERE shop_id = ?')->execute([$shopId]);

    $stmt = $pdo->prepare('DELETE FROM shops WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$shopId, $ownerId]);
    if ($stmt->rowCount() !== 1) {
        throw new ApiException("Không thể xóa cửa hàng, vui lòng thử lại", 409);
    }

    // Trả mặt bằng: gỡ quyền thuê để bản đồ (get-occupied-properties.php) hiện lại "Còn trống"
    $pdo->prepare('DELETE FROM rentals WHERE property_id = ? AND user_id = ?')
        ->execute([(int)$shop['property_id'], $ownerId]);

    $pdo->commit();

    apiRespond(true, "Đã đóng cửa vĩnh viễn cửa hàng", [
        "shop_id"           => $shopId,
        "released_property" => (int)$shop['property_id'],
        "units_liquidated"  => $units,
        "refund"            => $refund,
        "balance"           => $newBalance,
    ]);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('close-shop: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý đóng cửa hàng", null, 500);
}
