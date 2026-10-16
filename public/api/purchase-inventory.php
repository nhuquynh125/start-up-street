<?php
header('Content-Type: application/json; charset=utf-8');

$dbPath = dirname(__DIR__, 2) . '/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(["success" => false, "message" => "Lỗi server: Không tìm thấy file db.php", "data" => null]);
    exit;
}
require_once $dbPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Vui lòng dùng POST.", "data" => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$shopId = $input['shop_id'] ?? null;
$productId = $input['product_id'] ?? null;
$quantity = $input['quantity'] ?? null;
$sellingPrice = $input['selling_price'] ?? null;

if (!$shopId || !$productId || !$quantity || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thông tin nhập hàng không hợp lệ (shop_id, product_id, quantity > 0)", "data" => null]);
    exit;
}

try {
    // Kiểm tra xem sản phẩm đã có trong kho chưa
    $stmtCheck = $pdo->prepare('SELECT id, quantity FROM inventory WHERE shop_id = ? AND product_id = ?');
    $stmtCheck->execute([$shopId, $productId]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        // Nếu đã có thì cộng dồn số lượng và cập nhật giá bán mới (nếu có truyền)
        $newQty = $existing['quantity'] + $quantity;
        if ($sellingPrice) {
            $stmtUpdate = $pdo->prepare('UPDATE inventory SET quantity = ?, selling_price = ?, last_restocked = CURRENT_TIMESTAMP WHERE id = ?');
            $stmtUpdate->execute([$newQty, $sellingPrice, $existing['id']]);
        } else {
            $stmtUpdate = $pdo->prepare('UPDATE inventory SET quantity = ?, last_restocked = CURRENT_TIMESTAMP WHERE id = ?');
            $stmtUpdate->execute([$newQty, $existing['id']]);
        }
    } else {
        // Nếu chưa có thì lấy giá gốc mặc định nhân 1.5 làm giá bán nếu người chơi chưa nhập
        if (!$sellingPrice) {
            $stmtBaseCost = $pdo->prepare('SELECT base_cost FROM products WHERE id = ?');
            $stmtBaseCost->execute([$productId]);
            $baseCost = $stmtBaseCost->fetchColumn() ?: 20000;
            $sellingPrice = $baseCost * 1.5;
        }

        $stmtInsert = $pdo->prepare('INSERT INTO inventory (shop_id, product_id, quantity, selling_price) VALUES (?, ?, ?, ?)');
        $stmtInsert->execute([$shopId, $productId, $quantity, $sellingPrice]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Nhập hàng thành công",
        "data" => [
            "shop_id" => $shopId,
            "product_id" => $productId,
            "quantity_added" => $quantity
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi xử lý kho: " . $e->getMessage(), "data" => null]);
}
?>