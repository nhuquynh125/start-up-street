<?php
// lib/shop_billing.php — Phí cố định THEO THÁNG của cửa hàng (mặt bằng + điện nước [+ lương]).
//
// Cách hoạt động:
//  - Không có cron: phí được thu "lười" mỗi lần quán được tick (shopRuntimeTick), kể cả khi người chơi vắng mặt
//    nhiều tháng (tối đa BILLING_MAX_PERIODS_PER_CALL kỳ mỗi lần gọi, kỳ còn lại thu ở lần sau).
//  - Mỗi kỳ trừ ví 1 lần và ghi MỖI KHOẢN một dòng expense trong transactions (rent / utilities / salary).
//  - Ví không đủ tiền cho 1 kỳ -> không trừ, quán bị buộc đóng cửa (xem shopRuntimeTick), kỳ đó vẫn còn nợ.
//  - Quán chưa có mốc thu phí (last_billed_ts = 0) thì mốc bắt đầu = lúc tick đầu tiên, KHÔNG thu bù về quá khứ.
//
// PHẢI gọi trong transaction đã khóa ví + dòng shop_runtime (thứ tự khóa: ví -> shop_runtime -> kho).

require_once __DIR__ . '/shop_helpers.php';

// ── Hằng số cân bằng game: chỉnh ở đây ──
const BILLING_PERIOD_DAYS          = 30;       // 1 "tháng" = 30 ngày THỜI GIAN THẬT. Muốn thử nhanh thì đặt 1 (hoặc nhỏ hơn)
const BILLING_MAX_PERIODS_PER_CALL = 12;       // vắng mặt lâu thì mỗi lần tick thu tối đa 12 kỳ
const MONTHLY_RENT_RATE            = 0.05;     // tiền thuê tháng = 5% giá mặt bằng (properties.price)
const MONTHLY_UTILITIES            = 2000000;  // điện nước mỗi tháng
const MONTHLY_STAFF                = 0;        // lương nhân viên mỗi tháng (0 = không thu)

function billingPeriodSeconds(): int
{
    return BILLING_PERIOD_DAYS * 86400;
}

/** Các khoản phí của 1 kỳ: [[category, amount, mô tả], ...] (bỏ khoản <= 0). */
function monthlyFeeLines(PDO $pdo, array $shop): array
{
    $rent = 0.0;
    try {
        $stmt = $pdo->prepare('SELECT price FROM properties WHERE id = ?');
        $stmt->execute([(int)$shop['property_id']]);
        $price = $stmt->fetchColumn();
        if ($price !== false) {
            $rent = round((float)$price * MONTHLY_RENT_RATE, 2);
        }
    } catch (PDOException $e) {
        error_log('shop_billing: không đọc được giá mặt bằng: ' . $e->getMessage());
    }

    $lines = [];
    foreach ([
        ['rent',      $rent,                       'Tiền thuê mặt bằng'],
        ['utilities', (float)MONTHLY_UTILITIES,    'Điện nước'],
        ['salary',    (float)MONTHLY_STAFF,        'Lương nhân viên'],
    ] as $line) {
        if ($line[1] > 0) {
            $lines[] = $line;
        }
    }
    return $lines;
}

/**
 * Thu các kỳ phí đã đến hạn.
 * @return array{balance:float, last_billed_ts:int, charged:float, overdue:bool}
 *         overdue = true khi ví không đủ trả 1 kỳ (kỳ đó chưa được thu).
 */
function chargeMonthlyFees(PDO $pdo, array $shop, int $ownerId, array $rt, int $now, float $balance): array
{
    $period = billingPeriodSeconds();
    $last   = (int)($rt['last_billed_ts'] ?? 0);
    $res    = ['balance' => $balance, 'last_billed_ts' => $last, 'charged' => 0.0, 'overdue' => false];

    if ($last <= 0) {                       // lần đầu thấy quán này: bắt đầu tính kỳ từ bây giờ
        $res['last_billed_ts'] = $now;
        return $res;
    }

    $due = min(intdiv($now - $last, $period), BILLING_MAX_PERIODS_PER_CALL);
    if ($due <= 0) {
        return $res;
    }

    $lines     = monthlyFeeLines($pdo, $shop);
    $perPeriod = round(array_sum(array_column($lines, 1)), 2);
    if ($perPeriod <= 0) {                  // không có khoản phí nào -> chỉ dời mốc
        $res['last_billed_ts'] = $last + $due * $period;
        return $res;
    }

    $led = $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                          VALUES (?, ?, \'expense\', ?, ?, ?, ?)');

    for ($i = 0; $i < $due; $i++) {
        // deductWallet kiểm tra lại balance >= số tiền ngay trong câu UPDATE
        if ($balance < $perPeriod || !deductWallet($pdo, $ownerId, $perPeriod)) {
            $res['overdue'] = true;
            break;
        }
        $periodEnd = $res['last_billed_ts'] + $period;
        $label = (new DateTime('@' . $periodEnd))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('m/Y');

        foreach ($lines as [$category, $amount, $text]) {
            $balance = round($balance - $amount, 2);
            try { // sổ cái là tùy chọn: lỗi ở đây không làm hỏng việc thu phí
                $led->execute([$ownerId, (int)$shop['id'], $category, $amount, $balance, "$text tháng $label - " . $shop['name']]);
            } catch (Exception $e) {
                error_log('shop_billing: không ghi được bảng transactions: ' . $e->getMessage());
            }
        }
        $res['charged']        += $perPeriod;
        $res['last_billed_ts'] += $period;
    }

    $res['balance'] = $balance;
    return $res;
}
