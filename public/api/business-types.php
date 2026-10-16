<?php
// Thiết lập header trả về định dạng JSON
header('Content-Type: application/json; charset=utf-8');

// Require file db.php từ thư mục gốc (lùi 2 cấp thư mục từ public/api/)
$dbPath = __DIR__ . '/../../db.php';
if (!file_exists($dbPath)) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi server: Không tìm thấy file db.php",
        "data" => null
    ]);
    exit;
}
require_once $dbPath;

// Đảm bảo có biến $pdo từ file db.php
if (!isset($pdo)) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi server: Không thể kết nối database.",
        "data" => null
    ]);
    exit;
}

try {
    // Chỉ xử lý method GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Method Not Allowed. Vui lòng dùng GET.",
            "data" => null
        ]);
        exit;
    }

    // Truy vấn lấy tất cả loại hình kinh doanh
    $stmt = $pdo->query('SELECT id, name, required_capital, description FROM business_types');
    $businessTypes = $stmt->fetchAll();

    // Trả về JSON theo đúng chuẩn hợp đồng API của nhóm
    echo json_encode([
        "success" => true,
        "message" => "Lấy danh mục kinh doanh thành công",
        "data" => [
            "business_types" => $businessTypes
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Lỗi truy vấn: " . $e->getMessage(),
        "data" => null
    ]);
}
?>