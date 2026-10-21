<?php
// POST api/auth/register.php  { username, email, password }
// Đăng ký tài khoản mới, cấp vốn ban đầu (giá trị mặc định của users.balance trong schema), tạo profile mặc định.
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';
require_once dirname(__DIR__, 3) . '/lib/auth_session.php';

requireMethod('POST');
header('Cache-Control: no-store');

const REGISTER_MAX_EMAIL_LENGTH  = 120; // khớp users.email VARCHAR(120)
const REGISTER_MIN_PASSWORD_LEN  = 6;   // khớp kiểm tra phía frontend (login.html)
const REGISTER_MAX_PASSWORD_BYTES = 72; // bcrypt chỉ dùng 72 byte đầu: từ chối thay vì âm thầm cắt

try {
    $input    = readJsonBody();
    $username = $input['username'] ?? null;
    $email    = $input['email']    ?? null;
    $password = $input['password'] ?? null;

    // Phải là chuỗi: mảng/số/bool từ client sẽ làm trim()/strlen() ném TypeError (500)
    if (!is_string($username) || !is_string($email) || !is_string($password)) {
        throw new ApiException("Vui lòng nhập đủ tên người dùng, email và mật khẩu.", 400);
    }
    $username = trim($username);
    $email    = trim($email);

    // Validation
    $usernameLen = textLength($username);
    if ($usernameLen < 3 || $usernameLen > 32) {
        throw new ApiException("Tên người dùng phải từ 3 đến 32 ký tự.", 400);
    }
    // Cờ /D: không cho "$" khớp trước một dấu xuống dòng cuối chuỗi
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $username)) {
        throw new ApiException("Tên người dùng chỉ được chứa chữ cái, số và dấu gạch dưới.", 400);
    }
    if (strlen($email) > REGISTER_MAX_EMAIL_LENGTH || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiException("Email không hợp lệ.", 400);
    }
    if (strlen($password) < REGISTER_MIN_PASSWORD_LEN) {
        throw new ApiException("Mật khẩu phải có ít nhất " . REGISTER_MIN_PASSWORD_LEN . " ký tự.", 400);
    }
    if (strlen($password) > REGISTER_MAX_PASSWORD_BYTES) {
        throw new ApiException("Mật khẩu tối đa " . REGISTER_MAX_PASSWORD_BYTES . " ký tự.", 400);
    }

    // Kiểm tra trùng username / email (để báo lỗi sớm; chốt chặn thật là UNIQUE KEY, xem catch PDOException bên dưới)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        throw new ApiException("Tên người dùng hoặc email đã được sử dụng.", 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    // Tạo user (balance lấy giá trị mặc định trong schema)
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $hash]);
    $userId = (int)$pdo->lastInsertId();

    // Tạo profile mặc định
    $pdo->prepare('INSERT INTO player_profiles (user_id) VALUES (?)')->execute([$userId]);

    // Đọc số dư thật từ DB để trả về (không hardcode, tránh lệch khi schema đổi vốn ban đầu)
    $balance = getWalletBalance($pdo, $userId);

    $pdo->commit();

    // Bắt đầu session ngay sau đăng ký
    startAuthSession();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $userId];

    apiRespond(true, "Đăng ký thành công! Chào mừng " . $username . " đến Startup Street.", [
        "user_id"  => $userId,
        "username" => $username,
        "balance"  => $balance,
    ], 201);

} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // 23000 = vi phạm UNIQUE: 2 người đăng ký cùng username/email cùng lúc (lọt qua bước kiểm tra trùng ở trên)
    if ($e->getCode() === '23000') {
        apiRespond(false, "Tên người dùng hoặc email đã được sử dụng.", null, 409);
    }
    error_log('register: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi tạo tài khoản.", null, 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('register: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi tạo tài khoản.", null, 500);
}
