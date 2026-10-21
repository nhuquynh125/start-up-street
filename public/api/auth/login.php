<?php
// POST api/auth/login.php  { username_or_email, password }
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';
require_once dirname(__DIR__, 3) . '/lib/auth_session.php';

requireMethod('POST');
header('Cache-Control: no-store');

const LOGIN_MAX_IDENTIFIER_LENGTH = 120;  // khớp users.email VARCHAR(120)
const LOGIN_MAX_PASSWORD_BYTES    = 1024; // chặn body khổng lồ; bcrypt chỉ dùng 72 byte đầu

// Hash bcrypt của một chuỗi vô nghĩa: khi KHÔNG có user vẫn chạy password_verify() với hash này,
// để thời gian phản hồi "sai tên" và "sai mật khẩu" như nhau (không dò được tài khoản nào tồn tại).
const LOGIN_DUMMY_HASH = '$2y$10$m.GCkFJ1xAvdIWeOUBOHPuEi94oleNOXcSFyJVcASOTw1h299qj9m';

try {
    $input = readJsonBody();
    $login = $input['username_or_email'] ?? null;
    $pass  = $input['password'] ?? null;

    // Phải là chuỗi: mảng/số/bool từ client sẽ làm trim()/password_verify() ném TypeError (500)
    if (!is_string($login) || !is_string($pass)) {
        throw new ApiException("Vui lòng nhập tên đăng nhập và mật khẩu.", 400);
    }
    $login = trim($login);
    if ($login === '' || $pass === '') {
        throw new ApiException("Vui lòng nhập tên đăng nhập và mật khẩu.", 400);
    }
    if (textLength($login) > LOGIN_MAX_IDENTIFIER_LENGTH || strlen($pass) > LOGIN_MAX_PASSWORD_BYTES) {
        throw new ApiException("Tên đăng nhập hoặc mật khẩu không đúng.", 401);
    }

    // Tìm user qua username hoặc email
    $stmt = $pdo->prepare('SELECT id, username, password_hash, balance FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    $passwordOk = password_verify($pass, $user['password_hash'] ?? LOGIN_DUMMY_HASH);
    if (!$user || !$passwordOk) {
        throw new ApiException("Tên đăng nhập hoặc mật khẩu không đúng.", 401);
    }
    $userId = (int)$user['id'];

    // Việc phụ (last_login, nâng cấp hash): lỗi ở đây không được làm hỏng đăng nhập
    try {
        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$userId]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $userId]);
        }
    } catch (Throwable $e) {
        error_log('login: không cập nhật được last_login/hash: ' . $e->getMessage());
    }

    // Đổi session id TRƯỚC khi gắn user_id (chống session fixation) và bỏ dữ liệu của phiên cũ
    startAuthSession();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $userId];

    apiRespond(true, "Đăng nhập thành công!", [
        "user_id"  => $userId,
        "username" => $user['username'],
        "balance"  => (float)$user['balance'],
    ]);

} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (Throwable $e) {
    error_log('login: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi đăng nhập.", null, 500);
}
