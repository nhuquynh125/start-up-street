<?php
// lib/shop_helpers.php — hàm dùng chung cho module Cửa hàng & Kho (Thành viên 3)
// Mỗi API chỉ cần: require_once __DIR__ . '/../../lib/shop_helpers.php';  => có sẵn $pdo + các hàm bên dưới.

header('Content-Type: application/json; charset=utf-8');

$dbPath = dirname(__DIR__) . '/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi server: Không tìm thấy file db.php", "data" => null]);
    exit;
}
require_once $dbPath; // tạo biến $pdo (db.php tự trả JSON lỗi nếu không kết nối được)

// ── Cấu hình ví người chơi (Thành viên 1). Nếu bảng/cột của TV1 khác tên thì chỉ sửa 3 dòng này ──
const WALLET_TABLE       = 'users';
const WALLET_ID_COL      = 'id';
const WALLET_BALANCE_COL = 'balance';

const DEFAULT_MARKUP = 1.5;   // giá bán mặc định = giá vốn x 1.5 (khớp với get-shop-products.php)
const MAX_RESTOCK_QTY = 9999; // chặn nhập quá nhiều trong 1 lần

class ApiException extends Exception
{
    public $data;
    public function __construct($message, $status = 400, $data = null)
    {
        parent::__construct($message, $status);
        $this->data = $data;
    }
}

function apiRespond($success, $message, $data = null, $status = 200)
{
    http_response_code($status);
    echo json_encode(["success" => $success, "message" => $message, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function requireMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        apiRespond(false, "Method Not Allowed. Vui lòng dùng $method.", null, 405);
    }
}

function readJsonBody()
{
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        throw new ApiException("Body phải là JSON hợp lệ", 400);
    }
    return $input;
}

/** Trả về int dương, hoặc null nếu không hợp lệ ("abc", 0, -3, 1.5 đều bị loại). */
function toPositiveInt($value)
{
    if (is_bool($value) || is_array($value) || $value === null) return null;
    $v = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    return $v === false ? null : $v;
}

/** Trả về số > 0, hoặc null nếu không hợp lệ. */
function toPositiveNumber($value)
{
    if (is_bool($value) || is_array($value) || $value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    $v = (float)$value;
    return ($v > 0 && is_finite($v)) ? $v : null;
}

function textLength($s)
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Lấy cửa hàng và kiểm tra đúng chủ sở hữu. 404 nếu không có, 403 nếu không phải chủ. */
function requireOwnedShop(PDO $pdo, $shopId, $ownerUserId, $lock = false)
{
    $sql = 'SELECT id, owner_user_id, property_id, business_type_id, name FROM shops WHERE id = ?' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$shopId]);
    $shop = $stmt->fetch();
    if (!$shop) {
        throw new ApiException("Không tìm thấy cửa hàng", 404);
    }
    if ((int)$shop['owner_user_id'] !== (int)$ownerUserId) {
        throw new ApiException("Bạn không có quyền quản lý cửa hàng này", 403);
    }
    return $shop;
}

function getWalletBalance(PDO $pdo, $userId, $lock = false)
{
    $sql = sprintf('SELECT %s FROM %s WHERE %s = ?%s', WALLET_BALANCE_COL, WALLET_TABLE, WALLET_ID_COL, $lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (float)$v;
}

/** Trừ tiền nguyên tử: chỉ trừ khi đủ số dư. Trả về true nếu thành công. */
function deductWallet(PDO $pdo, $userId, $amount)
{
    $sql = sprintf(
        'UPDATE %1$s SET %2$s = %2$s - ? WHERE %3$s = ? AND %2$s >= ?',
        WALLET_TABLE, WALLET_BALANCE_COL, WALLET_ID_COL
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$amount, $userId, $amount]);
    return $stmt->rowCount() === 1;
}
