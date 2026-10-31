<?php
// POST api/auth/update-account.php
// { display_name?, username?, email?, current_password?, new_password? }
// - Chỉ cập nhật các trường được gửi lên.
// - Đổi email hoặc mật khẩu: bắt buộc current_password.
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';
require_once dirname(__DIR__, 3) . '/lib/auth_session.php';

requireMethod('POST');
header('Cache-Control: no-store');

const ACCOUNT_MAX_EMAIL_LENGTH    = 120; // khớp users.email VARCHAR(120)
const ACCOUNT_MIN_PASSWORD_LEN    = 6;
const ACCOUNT_MAX_PASSWORD_BYTES  = 72;  // bcrypt chỉ dùng 72 byte đầu
const ACCOUNT_MAX_DISPLAY_NAME    = 50;  // khớp player_profiles.display_name VARCHAR(50)

try {
    $userId = getCurrentUserId(); // 401 nếu chưa đăng nhập
    $input  = readJsonBody();

    $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new ApiException("Không tìm thấy người dùng.", 404);
    }

    $sets = [];          // cột của bảng users cần UPDATE
    $params = [];
    $newUsername = $user['username'];
    $newEmail    = $user['email'];
    $displayName = null; // null = không đổi
    $passwordChanged = false;

    // ── Tên đăng nhập ──
    if (array_key_exists('username', $input)) {
        if (!is_string($input['username'])) throw new ApiException("Tên người dùng không hợp lệ.", 400);
        $u = trim($input['username']);
        if ($u !== $user['username']) {
            $len = textLength($u);
            if ($len < 3 || $len > 32) throw new ApiException("Tên người dùng phải từ 3 đến 32 ký tự.", 400);
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $u)) {
                throw new ApiException("Tên người dùng chỉ được chứa chữ cái, số và dấu gạch dưới.", 400);
            }
            $sets[] = 'username = ?'; $params[] = $u;
            $newUsername = $u;
        }
    }

    // ── Email ──
    $emailChanged = false;
    if (array_key_exists('email', $input)) {
        if (!is_string($input['email'])) throw new ApiException("Email không hợp lệ.", 400);
        $e = trim($input['email']);
        if (strcasecmp($e, $user['email']) !== 0) {
            if (strlen($e) > ACCOUNT_MAX_EMAIL_LENGTH || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                throw new ApiException("Email không hợp lệ.", 400);
            }
            $sets[] = 'email = ?'; $params[] = $e;
            $newEmail = $e;
            $emailChanged = true;
        }
    }

    // ── Mật khẩu mới ──
    $newPassword = $input['new_password'] ?? null;
    $hasNewPassword = $newPassword !== null && $newPassword !== '';
    if ($hasNewPassword) {
        if (!is_string($newPassword)) throw new ApiException("Mật khẩu mới không hợp lệ.", 400);
        if (strlen($newPassword) < ACCOUNT_MIN_PASSWORD_LEN) {
            throw new ApiException("Mật khẩu mới phải có ít nhất " . ACCOUNT_MIN_PASSWORD_LEN . " ký tự.", 400);
        }
        if (strlen($newPassword) > ACCOUNT_MAX_PASSWORD_BYTES) {
            throw new ApiException("Mật khẩu mới tối đa " . ACCOUNT_MAX_PASSWORD_BYTES . " ký tự.", 400);
        }
    }

    // ── Xác thực mật khẩu hiện tại khi đổi email / mật khẩu ──
    if ($emailChanged || $hasNewPassword) {
        $cur = $input['current_password'] ?? null;
        if (!is_string($cur) || $cur === '') {
            throw new ApiException("Hãy nhập mật khẩu hiện tại để đổi email hoặc mật khẩu.", 400);
        }
        if (strlen($cur) > 1024 || !password_verify($cur, $user['password_hash'])) {
            throw new ApiException("Mật khẩu hiện tại không đúng.", 403);
        }
        if ($hasNewPassword) {
            $sets[] = 'password_hash = ?'; $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordChanged = true;
        }
    }

    // ── Tên hiển thị (rỗng = bỏ tên hiển thị, dùng lại username) ──
    if (array_key_exists('display_name', $input)) {
        if (!is_string($input['display_name'])) throw new ApiException("Tên hiển thị không hợp lệ.", 400);
        $dn = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $input['display_name']) ?? ''), 0, ACCOUNT_MAX_DISPLAY_NAME);
        $displayName = $dn;
    }

    if (!$sets && $displayName === null) {
        throw new ApiException("Không có thay đổi nào để lưu.", 400);
    }

    // ── Kiểm tra trùng username / email của người khác ──
    $dupWhere = []; $dupParams = [];
    if ($newUsername !== $user['username']) { $dupWhere[] = 'username = ?'; $dupParams[] = $newUsername; }
    if ($emailChanged)                      { $dupWhere[] = 'email = ?';    $dupParams[] = $newEmail; }
    if ($dupWhere) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE (' . implode(' OR ', $dupWhere) . ') AND id <> ? LIMIT 1');
        $stmt->execute(array_merge($dupParams, [$userId]));
        if ($stmt->fetch()) {
            throw new ApiException("Tên người dùng hoặc email đã được sử dụng.", 409);
        }
    }

    $pdo->beginTransaction();

    if ($sets) {
        $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute(array_merge($params, [$userId]));
    }
    if ($displayName !== null) {
        $pdo->prepare('INSERT IGNORE INTO player_profiles (user_id) VALUES (?)')->execute([$userId]);
        $pdo->prepare('UPDATE player_profiles SET display_name = ? WHERE user_id = ?')
            ->execute([$displayName === '' ? null : $displayName, $userId]);
    }

    $pdo->commit();

    // Đổi mật khẩu: cấp session id mới (getCurrentUserId đã đóng session nên phải mở lại)
    if ($passwordChanged) {
        startAuthSession();
        session_regenerate_id(true);
        $_SESSION = ['user_id' => $userId];
    }

    // Đọc lại display_name thật từ DB để trả về
    $stmt = $pdo->prepare('SELECT display_name FROM player_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $dnNow = $stmt->fetchColumn();

    apiRespond(true, "Đã cập nhật thông tin tài khoản.", [
        "user_id"          => $userId,
        "username"         => $newUsername,
        "email"            => $newEmail,
        "display_name"     => ($dnNow !== false && $dnNow !== null && $dnNow !== '') ? $dnNow : $newUsername,
        "password_changed" => $passwordChanged,
    ]);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') { // vi phạm UNIQUE do 2 request cùng lúc
        apiRespond(false, "Tên người dùng hoặc email đã được sử dụng.", null, 409);
    }
    error_log('update-account: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi cập nhật tài khoản.", null, 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('update-account: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi cập nhật tài khoản.", null, 500);
}