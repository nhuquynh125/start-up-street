<?php
// POST api/auth/logout.php
// Đăng xuất: huỷ session phía server + xoá cookie phiên phía trình duyệt.
// Idempotent: chưa đăng nhập / session đã hết hạn vẫn trả success.
//
// Cố ý KHÔNG require lib/shop_helpers.php (kéo theo db.php -> kết nối MySQL):
// đăng xuất không cần DB, và người chơi vẫn phải đăng xuất được khi DB đang lỗi.
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/auth_session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Trả JSON cùng định dạng với apiRespond() ở các API khác rồi dừng. */
function logoutRespond(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(["success" => $success, "message" => $message, "data" => null], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    logoutRespond(false, "Method Not Allowed. Vui lòng dùng POST.", 405);
}

try {
    destroyAuthSession();
    logoutRespond(true, "Đã đăng xuất.");
} catch (Throwable $e) {
    error_log('logout: ' . $e->getMessage());
    logoutRespond(false, "Lỗi server khi đăng xuất.", 500);
}
