<?php
// CHỈ ĐỂ TEST TRÊN MÁY LOCAL, cho tới khi API đăng nhập thật của TV1 ghi $_SESSION['user_id'].
// Mở http://127.0.0.1:8000/api/dev-login.php?user_id=1 một lần trong trình duyệt rồi dùng game bình thường.
// XÓA FILE NÀY khi có đăng nhập thật (nếu để lại, ai cũng có thể giả danh bất kỳ người chơi nào).
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    apiRespond(false, "dev-login chỉ dùng được trên máy local", null, 403);
}

$userId = toPositiveInt($_GET['user_id'] ?? 1);
if ($userId === null || getWalletBalance($pdo, $userId) === null) {
    apiRespond(false, "Không tìm thấy người chơi", null, 404);
}

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$_SESSION['user_id'] = $userId;
apiRespond(true, "Đã đăng nhập thử nghiệm", ["user_id" => $userId]);
