<?php
header('Content-Type: application/json; charset=utf-8');

$dbPath = __DIR__ . '/../../db.php';
if (!file_exists($dbPath)) {
    echo json_encode(["success" => false, "message" => "Lỗi server: Không tìm thấy file db.php", "data" => null]);
    exit;
}
require_once $dbPath;

// 1. Kiểm tra method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Vui lòng dùng POST.", "data" => null]);
    exit;
}

// 2. Đọc dữ liệu JSON từ Frontend gửi lên
$input = json_decode(file_get_contents('php://input'), true);

$ownerUserId = $input['owner_user_id'] ?? null;
$propertyId = $input['property_id'] ?? null;
$businessTypeId = $input['business_type_id'] ?? null;
$name = $input['name'] ?? trim($input['name']);

// 3. Kiểm tra dữ liệu đầu vào
if (!$ownerUserId || !$propertyId || !$businessTypeId || empty($name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thiếu thông tin bắt buộc (owner_user_id, property_id, business_type_id, name)", "data" => null]);
    exit;
}

try {
    // 4. Lưu vào database
    $stmt = $pdo->prepare('INSERT INTO shops (owner_user_id, property_id, business_type_id, name) VALUES (?, ?, ?, ?)');
    $stmt->execute([$ownerUserId, $propertyId, $businessTypeId, $name]);

    $newShopId = $pdo->lastInsertId();

    // 5. Trả về JSON chuẩn của nhóm
    echo json_encode([
        "success" => true,
        "message" => "Tạo cửa hàng thành công",
        "data" => [
            "shop_id" => $newShopId,
            "name" => $name,
            "business_type_id" => $businessTypeId
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi truy vấn: " . $e->getMessage(), "data" => null]);
}
?>