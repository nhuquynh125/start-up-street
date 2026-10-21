<?php
// lib/auth_session.php — khởi tạo / huỷ session đăng nhập, dùng chung cho login, register, logout.
// KHÔNG phụ thuộc DB (không require db.php) nên logout vẫn chạy được khi MySQL đang lỗi.
//
// Cấu hình session ở đây phải tương thích với getCurrentUserId() trong lib/shop_helpers.php
// (cùng cookie PHPSESSID, httponly, SameSite=Lax) — getCurrentUserId() chỉ ĐỌC session nên không cần đổi.

/** true nếu request đến qua HTTPS. Cố ý KHÔNG tin header X-Forwarded-Proto (client giả được). */
function isHttpsRequest(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    return ($https !== '' && $https !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Tùy chọn cookie/session dùng chung. Cookie chỉ gắn Secure khi đang chạy HTTPS (localhost http vẫn dùng được). */
function authSessionOptions(): array
{
    return [
        'use_strict_mode'  => true,  // từ chối session id do client tự đặt (chống session fixation)
        'use_only_cookies' => true,  // không nhận session id qua URL
        'cookie_httponly'  => true,  // JS không đọc được cookie
        'cookie_samesite'  => 'Lax', // chặn gửi cookie trong POST cross-site (CSRF)
        'cookie_secure'    => isHttpsRequest(),
        'cache_limiter'    => '',    // không để PHP ghi đè header Cache-Control: no-store mà các API tự đặt
    ];
}

/** Bật session ghi được. Ném RuntimeException nếu không khởi tạo được (caller trả 500). */
function startAuthSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (!session_start(authSessionOptions())) {
        throw new RuntimeException('Không khởi tạo được session');
    }
}

/**
 * Huỷ session hiện tại và xoá cookie ở trình duyệt. Idempotent:
 * không có cookie phiên / session đã hết hạn thì không làm gì và cũng không tạo session mới.
 */
function destroyAuthSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[session_name()])) {
        return;
    }
    startAuthSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        // Dạng mảng (PHP >= 7.3) để cookie xoá mang đúng samesite/secure như cookie gốc
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
