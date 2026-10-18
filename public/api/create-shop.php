<?php
// POST api/create-shop.php  {property_id, business_type_id, name}
// Chủ cửa hàng = người đang đăng nhập (getCurrentUserId), KHÔNG nhận owner_user_id từ client.
// Thứ tự kiểm tra: đăng nhập (401) -> dữ liệu vào (400) -> người chơi tồn tại (404) -> đã thuê mặt bằng (403)
//                  -> loại hình tồn tại (404) -> đủ vốn (400) -> mặt bằng chưa có shop (409) -> INSERT
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';

requireMethod('POST');

// ── Bảng thuê mặt bằng của Thành viên 2. Nếu tên bảng/cột khác thì chỉ sửa 3 dòng này ──
const RENTALS_TABLE        = 'rentals';
const RENTALS_PROPERTY_COL = 'property_id';
const RENTALS_USER_COL     = 'user_id';

/** true nếu người chơi đang thuê mặt bằng này. */
function isPropertyRentedBy(PDO $pdo, $propertyId, $userId)
{
    $sql = sprintf(
        'SELECT 1 FROM %s WHERE %s = ? AND %s = ? LIMIT 1',
        RENTALS_TABLE, RENTALS_PROPERTY_COL, RENTALS_USER_COL
    );
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$propertyId, $userId]);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        // 42S02 = bảng không tồn tại (module thuê mặt bằng của TV2 chưa được migrate)
        if ($e->getCode() === '42S02') {
            error_log('create-shop: thiếu bảng ' . RENTALS_TABLE . ': ' . $e->getMessage());
            throw new ApiException("Hệ thống thuê mặt bằng chưa sẵn sàng (thiếu bảng " . RENTALS_TABLE . ")", 500);
        }
        throw $e;
    }
}

try {
    $ownerUserId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $input = readJsonBody();

    $propertyId     = toPositiveInt($input['property_id'] ?? null);
    $businessTypeId = toPositiveInt($input['business_type_id'] ?? null);
    $name           = trim((string)($input['name'] ?? ''));

    if (!$propertyId || !$businessTypeId || $name === '') {
        throw new ApiException("Thiếu hoặc sai thông tin bắt buộc (property_id, business_type_id, name)", 400);
    }
    if (textLength($name) > 100) {
        throw new ApiException("Tên cửa hàng tối đa 100 ký tự", 400);
    }

    $pdo->beginTransaction();

    // 1) Người chơi phải tồn tại. Khóa dòng ví để số dư không đổi giữa lúc kiểm tra và lúc tạo shop.
    $balance = getWalletBalance($pdo, $ownerUserId, true);
    if ($balance === null) {
        throw new ApiException("Không tìm thấy người chơi", 404);
    }

    // 2) Mặt bằng phải do chính người chơi này thuê.
    //    Cố ý trả chung 403 cho cả "không tồn tại" lẫn "của người khác" để không lộ thông tin mặt bằng.
    if (!isPropertyRentedBy($pdo, $propertyId, $ownerUserId)) {
        throw new ApiException("Bạn chưa thuê mặt bằng này nên không thể mở cửa hàng", 403);
    }

    // 3) Loại hình phải tồn tại, và số dư phải đủ vốn yêu cầu
    $stmt = $pdo->prepare('SELECT id, name, required_capital FROM business_types WHERE id = ?');
    $stmt->execute([$businessTypeId]);
    $type = $stmt->fetch();
    if (!$type) {
        throw new ApiException("Loại hình kinh doanh không tồn tại", 404);
    }
    $requiredCapital = (float)$type['required_capital'];
    if ($balance < $requiredCapital) {
        throw new ApiException(
            "Không đủ vốn để mở loại hình \"" . $type['name'] . "\": cần "
            . number_format($requiredCapital, 0, ',', '.') . "đ, hiện có "
            . number_format($balance, 0, ',', '.') . "đ",
            400
        );
    }

    // 4) Mỗi mặt bằng chỉ có 1 cửa hàng
    $stmt = $pdo->prepare('SELECT id FROM shops WHERE property_id = ? FOR UPDATE');
    $stmt->execute([$propertyId]);
    if ($existing = $stmt->fetch()) {
        throw new ApiException("Mặt bằng này đã có cửa hàng", 409, ["shop_id" => (int)$existing['id']]);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO shops (owner_user_id, property_id, business_type_id, name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ownerUserId, $propertyId, $businessTypeId, $name]);
    } catch (PDOException $e) {
        // 23000 = vi phạm unique/foreign key (2 người cùng bấm tạo, hoặc dữ liệu tham chiếu không tồn tại)
        if ($e->getCode() === '23000') {
            throw new ApiException("Không thể tạo cửa hàng: mặt bằng đã có cửa hàng hoặc dữ liệu tham chiếu không hợp lệ", 409);
        }
        throw $e;
    }

    $shopId = (int)$pdo->lastInsertId();
    $pdo->commit();

    apiRespond(true, "Tạo cửa hàng thành công", [
        "shop_id"          => $shopId,
        "name"             => $name,
        "property_id"      => $propertyId,
        "business_type_id" => $businessTypeId
    ], 201);
} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // data = null cho mọi lỗi, trừ 409 "đã có cửa hàng" cần trả shop_id để frontend mở đúng cửa hàng đó
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create-shop: ' . $e->getMessage());
    apiRespond(false, "Lỗi xử lý tạo cửa hàng", null, 500);
}
