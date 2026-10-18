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
// NGUỒN SỰ THẬT DUY NHẤT của tiền: users.balance trong MySQL. Frontend (localStorage, GameState...) chỉ được HIỂN THỊ bản sao.
const WALLET_TABLE       = 'users';
const WALLET_ID_COL      = 'id';
const WALLET_BALANCE_COL = 'balance';

const DEFAULT_MARKUP = 1.5;   // giá bán mặc định = giá vốn x 1.5 (khớp với get-shop-products.php)
const MAX_RESTOCK_QTY = 9999; // chặn nhập quá nhiều trong 1 lần
const MAX_SALE_QTY    = 999;  // chặn bán quá nhiều trong 1 lần

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

/** Cộng tiền nguyên tử vào ví ($amount phải > 0). Trả về true nếu thành công. */
function creditWallet(PDO $pdo, $userId, $amount)
{
    $sql = sprintf(
        'UPDATE %1$s SET %2$s = %2$s + ? WHERE %3$s = ?',
        WALLET_TABLE, WALLET_BALANCE_COL, WALLET_ID_COL
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$amount, $userId]);
    return $stmt->rowCount() === 1;
}

/**
 * BÁN HÀNG NGUYÊN TỬ (dùng cho module bán hàng của TV4):
 * khóa ví chủ shop + dòng kho -> kiểm tra đủ hàng -> trừ kho -> cộng tiền vào users.balance -> commit.
 * Thiếu hàng hoặc bất kỳ lỗi nào => rollback, cả kho lẫn ví đều KHÔNG thay đổi.
 *
 * - $unitPrice: nên truyền null để lấy giá bán đã lưu trong inventory.selling_price (server tự quyết).
 *   TUYỆT ĐỐI không truyền giá do frontend gửi lên: người chơi sẽ tự nâng giá để tạo tiền.
 * - Nếu đã có transaction đang mở (caller muốn gộp thêm bước khác, vd ghi sổ cái) thì hàm dùng luôn
 *   transaction đó và KHÔNG tự commit/rollback — caller chịu trách nhiệm.
 * - Thứ tự khóa: ví -> kho (giống purchase-inventory.php: ví -> cửa hàng -> kho) để 2 request đồng thời không deadlock.
 *
 * @return array [shop_id, product_id, quantity_sold, unit_price, revenue, stock_quantity, balance]
 * @throws ApiException 400 tham số sai | 404 không có shop/người chơi | 409 không đủ hàng (data: requested, available)
 */
function deductShopInventoryAndAddBalance(PDO $pdo, $shopId, $productId, $quantity, $unitPrice = null)
{
    $shopId    = toPositiveInt($shopId);
    $productId = toPositiveInt($productId);
    $quantity  = toPositiveInt($quantity);
    if (!$shopId || !$productId || !$quantity || $quantity > MAX_SALE_QTY) {
        throw new ApiException("Thông tin bán hàng không hợp lệ (shop_id, product_id, quantity từ 1 đến " . MAX_SALE_QTY . ")", 400);
    }
    if ($unitPrice !== null) {
        $unitPrice = toPositiveNumber($unitPrice);
        if ($unitPrice === null) {
            throw new ApiException("Giá bán phải là số lớn hơn 0", 400);
        }
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 1) Tìm chủ shop (không cần khóa: owner_user_id không đổi sau khi tạo shop)
        $stmt = $pdo->prepare('SELECT owner_user_id FROM shops WHERE id = ?');
        $stmt->execute([$shopId]);
        $ownerId = $stmt->fetchColumn();
        if ($ownerId === false) {
            throw new ApiException("Không tìm thấy cửa hàng", 404);
        }
        $ownerId = (int)$ownerId;

        // 2) Khóa ví chủ shop (khóa TRƯỚC kho — xem ghi chú thứ tự khóa ở trên)
        $balance = getWalletBalance($pdo, $ownerId, true);
        if ($balance === null) {
            throw new ApiException("Không tìm thấy người chơi", 404);
        }

        // 3) Khóa dòng kho của đúng sản phẩm trong đúng cửa hàng
        $stmt = $pdo->prepare('SELECT id, quantity, selling_price FROM inventory WHERE shop_id = ? AND product_id = ? FOR UPDATE');
        $stmt->execute([$shopId, $productId]);
        $inv = $stmt->fetch();

        $available = $inv ? (int)$inv['quantity'] : 0; // chưa có dòng kho = tồn kho 0
        if (!$inv || $available < $quantity) {
            throw new ApiException("Không đủ hàng trong kho", 409, ["requested" => $quantity, "available" => $available]);
        }

        if ($unitPrice === null) {
            $unitPrice = (float)$inv['selling_price'];
        }
        if ($unitPrice <= 0) {
            throw new ApiException("Sản phẩm chưa có giá bán hợp lệ", 409);
        }
        $revenue = round($quantity * $unitPrice, 2);

        // 4) Trừ kho. Điều kiện quantity >= ? là lớp bảo vệ thứ 2 nếu có ai đó bỏ qua khóa
        $stmt = $pdo->prepare('UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?');
        $stmt->execute([$quantity, $inv['id'], $quantity]);
        if ($stmt->rowCount() !== 1) {
            throw new ApiException("Không đủ hàng trong kho", 409, ["requested" => $quantity, "available" => $available]);
        }

        // 5) Cộng doanh thu vào users.balance
        if (!creditWallet($pdo, $ownerId, $revenue)) {
            throw new ApiException("Không thể cộng tiền vào ví", 500);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return [
            "shop_id"        => $shopId,
            "product_id"     => $productId,
            "quantity_sold"  => $quantity,
            "unit_price"     => $unitPrice,
            "revenue"        => $revenue,
            "stock_quantity" => $available - $quantity,
            "balance"        => round($balance + $revenue, 2),
        ];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
