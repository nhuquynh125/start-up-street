<?php
// POST api/restock-bulk.php  {shop_id, items: [{product_id, quantity, selling_price?}, ...]}
// Nhập NHIỀU món trong MỘT transaction: hoặc nhập hết, hoặc không đổi gì (all-or-nothing).
// Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id từ client. Giá vốn luôn lấy từ bảng products.
//
// Khác purchase-inventory.php (1 món / 1 request):
//  - Trừ ví 1 lần cho cả đơn nhập; không còn cảnh nhập được 2 món rồi hết tiền ở món thứ 3.
//  - Server tự chặn vượt sức chứa kho (MAX_STOCK_PER_PRODUCT trong lib/shop_helpers.php), không tin client.
//
// Thứ tự khóa: ví -> cửa hàng -> kho (giống purchase-inventory.php).
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

const MAX_BULK_ITEMS = 50;

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input    = readJsonBody();
    $shopId   = toPositiveInt($input['shop_id'] ?? null);
    $rawItems = $input['items'] ?? null;
    if (!$shopId || !is_array($rawItems) || !$rawItems || count($rawItems) > MAX_BULK_ITEMS) {
        throw new ApiException("Thiếu hoặc sai thông tin nhập hàng (shop_id, items: 1 đến " . MAX_BULK_ITEMS . " món)", 400);
    }

    // Làm sạch từng dòng: product_id => ['qty' => int, 'price' => ?float]
    $items = [];
    foreach ($rawItems as $row) {
        if (!is_array($row)) {
            throw new ApiException("Mỗi phần tử của items phải là object {product_id, quantity}", 400);
        }
        $pid = toPositiveInt($row['product_id'] ?? null);
        $qty = toPositiveInt($row['quantity'] ?? null);
        if (!$pid || !$qty || $qty > MAX_RESTOCK_QTY) {
            throw new ApiException("Thông tin nhập hàng không hợp lệ (quantity từ 1 đến " . MAX_RESTOCK_QTY . ")", 400);
        }
        if (isset($items[$pid])) {
            throw new ApiException("Sản phẩm #$pid bị lặp trong đơn nhập", 400);
        }
        $price = null;
        if (array_key_exists('selling_price', $row) && $row['selling_price'] !== null && $row['selling_price'] !== '') {
            $price = toPositiveNumber($row['selling_price']);
            if ($price === null) {
                throw new ApiException("Giá bán phải là số lớn hơn 0", 400);
            }
        }
        $items[$pid] = ['qty' => $qty, 'price' => $price];
    }
    $ids = array_keys($items);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $pdo->beginTransaction();

    $balance = getWalletBalance($pdo, $ownerId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }
    $shop = requireOwnedShop($pdo, $shopId, $ownerId, true); // 404 / 403

    // Mọi sản phẩm phải thuộc đúng loại hình của cửa hàng
    $stmt = $pdo->prepare("SELECT id, name, base_cost FROM products WHERE business_type_id = ? AND id IN ($in)");
    $stmt->execute(array_merge([(int)$shop['business_type_id']], $ids));
    $products = [];
    foreach ($stmt->fetchAll() as $p) {
        $products[(int)$p['id']] = $p;
    }
    foreach ($ids as $pid) {
        if (!isset($products[$pid])) {
            throw new ApiException("Sản phẩm #$pid không thuộc loại hình kinh doanh của cửa hàng này", 404);
        }
    }

    // Khóa các dòng kho liên quan
    $stmt = $pdo->prepare("SELECT id, product_id, quantity, selling_price FROM inventory WHERE shop_id = ? AND product_id IN ($in) FOR UPDATE");
    $stmt->execute(array_merge([$shopId], $ids));
    $inv = [];
    foreach ($stmt->fetchAll() as $r) {
        $inv[(int)$r['product_id']] = $r;
    }

    // Tính tiền + kiểm tra sức chứa TRƯỚC khi sửa bất cứ thứ gì
    $total = 0.0;
    $lines = [];
    foreach ($items as $pid => $it) {
        $current = isset($inv[$pid]) ? (int)$inv[$pid]['quantity'] : 0;
        $newQty  = $current + $it['qty'];
        if ($newQty > MAX_STOCK_PER_PRODUCT) {
            throw new ApiException(
                "Kho chỉ chứa tối đa " . MAX_STOCK_PER_PRODUCT . " mỗi món: \"" . $products[$pid]['name']
                . "\" đang có $current, chỉ nhập thêm được " . max(0, MAX_STOCK_PER_PRODUCT - $current),
                409,
                ["product_id" => $pid, "current" => $current, "max_can_add" => max(0, MAX_STOCK_PER_PRODUCT - $current)]
            );
        }
        $cost = round((float)$products[$pid]['base_cost'] * $it['qty'], 2);
        $total += $cost;
        $lines[$pid] = ['cost' => $cost, 'new_qty' => $newQty, 'has_row' => isset($inv[$pid])];
    }
    $total = round($total, 2);

    // Trừ ví 1 lần (deductWallet kiểm tra lại balance >= total ngay trong câu UPDATE)
    if ($balance < $total || !deductWallet($pdo, $ownerId, $total)) {
        throw new ApiException("Không đủ tiền để nhập hàng", 400, ["required" => $total, "balance" => $balance]);
    }
    $newBalance = round($balance - $total, 2);

    // Cộng kho + ghi sổ từng món
    $result  = [];
    $running = $balance;
    foreach ($items as $pid => $it) {
        $line = $lines[$pid];
        if ($line['has_row']) {
            $price = $it['price'] !== null ? $it['price'] : (float)$inv[$pid]['selling_price'];
            $pdo->prepare('UPDATE inventory SET quantity = ?, selling_price = ?, last_restocked = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$line['new_qty'], $price, $inv[$pid]['id']]);
        } else {
            $price = $it['price'] !== null ? $it['price'] : (float)$products[$pid]['base_cost'] * DEFAULT_MARKUP;
            $pdo->prepare('INSERT INTO inventory (shop_id, product_id, quantity, selling_price, last_restocked) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)')
                ->execute([$shopId, $pid, $line['new_qty'], $price]);
        }

        $running = round($running - $line['cost'], 2);
        try { // sổ cái là tùy chọn: lỗi ở đây không làm hỏng việc nhập hàng
            $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                           VALUES (?, ?, \'expense\', \'material\', ?, ?, ?)')
                ->execute([$ownerId, $shopId, $line['cost'], $running, "Nhập {$it['qty']} x " . $products[$pid]['name']]);
        } catch (Exception $e) {
            error_log('restock-bulk: không ghi được bảng transactions: ' . $e->getMessage());
        }

        $result[] = [
            "product_id"     => $pid,
            "quantity_added" => $it['qty'],
            "stock_quantity" => $line['new_qty'],
            "selling_price"  => $price,
            "cost"           => $line['cost'],
        ];
    }

    $pdo->commit();

    apiRespond(true, "Nhập hàng thành công", [
        "shop_id"    => $shopId,
        "items"      => $result,
        "total_cost" => $total,
        "balance"    => $newBalance,
    ]);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('restock-bulk: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý nhập hàng", null, 500);
}
