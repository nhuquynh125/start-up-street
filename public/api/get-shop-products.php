<?php
header('Content-Type: application/json; charset=utf-8');

$dbPath = dirname(__DIR__, 2) . '/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(["success" => false, "message" => "Lỗi server: Không tìm thấy file db.php", "data" => null]);
    exit;
}
require_once $dbPath;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Vui lòng dùng GET.", "data" => null]);
    exit;
}

$shopId = $_GET['shop_id'] ?? null;

if (!$shopId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thiếu shop_id", "data" => null]);
    exit;
}

try {
    // Lấy thông tin cửa hàng để biết business_type_id
    $stmtShop = $pdo->prepare('SELECT id, business_type_id, name FROM shops WHERE id = ?');
    $stmtShop->execute([$shopId]);
    $shop = $stmtShop->fetch();

    if (!$shop) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Không tìm thấy cửa hàng", "data" => null]);
        exit;
    }

    // Lấy danh sách sản phẩm thuộc loại hình kinh doanh này + số lượng tồn kho trong bảng inventory (nếu có)
    $stmt = $pdo->prepare('
        SELECT 
            p.id AS product_id,
            p.name AS product_name,
            p.base_cost,
            COALESCE(i.quantity, 0) AS stock_quantity,
            COALESCE(i.selling_price, p.base_cost * 1.5) AS selling_price
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.shop_id = ?
        WHERE p.business_type_id = ?
    ');
    $stmt->execute([$shopId, $shop['business_type_id']]);
    $products = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "message" => "Lấy danh sách sản phẩm thành công",
        "data" => [
            "shop" => $shop,
            "products" => $products
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi truy vấn: " . $e->getMessage(), "data" => null]);
}
?>