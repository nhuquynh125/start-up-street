<?php
// lib/shop_simulation.php — Động cơ KHÁCH TỰ ĐỘNG (Member 4: Simulation & Finance)
//
// Cách hoạt động:
//  - Quán có trạng thái mở/đóng cửa lưu ở MySQL (shop_runtime).
//  - Khi quán đang MỞ, mỗi lần có request "tick", server tính xem từ lần tick trước đến giờ có bao nhiêu khách ghé
//    (phân phối Poisson theo lượng khách qua lại của mặt bằng, giờ cao điểm, uy tín, ...), rồi xử lý từng khách:
//    chọn món -> chê giá hay không -> trừ kho -> cộng users.balance -> ghi transactions + shop_orders.
//  - Mọi thứ nằm trong 1 transaction. Trình duyệt chỉ GỌI tick và VẼ kết quả; không gửi số tiền/giá/số khách nào.
//  - Gọi tick nhiều lần / nhiều tab cùng lúc không bị nhân đôi khách: last_tick_ts được khóa (FOR UPDATE) và cập nhật
//    trong cùng transaction.
//  - Người chơi vắng mặt (đóng tab) vẫn được bù khách khi quay lại, nhưng tối đa SIM_MAX_CATCHUP_SECONDS.
//
// Ngày chơi (migration 05):
//  - Mỗi đơn mang day_number (ngày chơi lúc phát sinh) và cost (vốn mặt hàng = giá vốn x số lượng).
//  - shopEndDay() = "Chốt ca": chốt khách tới lúc này, lưu tổng kết ngày vào shop_daily_reports, sang ngày mới,
//    tự trừ tiền thuê mặt bằng khi sang tháng mới (mỗi 30 ngày chơi) và xóa nhật ký khách quá 7 ngày chơi.
//
// Thứ tự khóa: ví -> cửa hàng -> shop_runtime -> kho (ví luôn khóa TRƯỚC, giống purchase-inventory.php) để không deadlock.

require_once __DIR__ . '/shop_helpers.php';
require_once __DIR__ . '/shop_reports.php';

// ── Hằng số cân bằng game: chỉnh ở đây, không cần sửa logic ──
const SIM_TIMEZONE            = 'Asia/Ho_Chi_Minh';
const SIM_MAX_CATCHUP_SECONDS = 600;   // bù tối đa 10 phút khách khi người chơi vắng mặt
const SIM_MAX_CUSTOMERS_TICK  = 40;    // chặn 1 lần tick sinh quá nhiều khách
const SIM_MIN_TICK_SECONDS    = 1;     // chưa trôi qua 1 giây thì chưa mô phỏng
const SIM_BASE_RATE           = 0.4;   // khách/phút khi mặt bằng có traffic = 0
const SIM_TRAFFIC_RATE        = 1.6;   // khách/phút cộng thêm khi traffic = 100
const SIM_MARKET_MARKUP       = 1.8;   // giá thị trường = giá vốn x 1.8; bán cao hơn nhiều thì khách chê
const SIM_USE_HOUR_FACTOR     = true;  // giờ cao điểm (sáng, trưa, chiều tối) đông khách hơn
const SIM_FEED_DEFAULT_LIMIT  = 15;    // số khách gần nhất trả về khi client chưa có since_id
const SIM_FEED_MAX_LIMIT      = 50;

function simRandFloat(): float
{
    return mt_rand() / mt_getrandmax();
}

/** Số khách đến trong khoảng thời gian có kỳ vọng $mean (thuật toán Knuth). */
function simPoisson(float $mean): int
{
    if ($mean <= 0) {
        return 0;
    }
    $limit = exp(-$mean);
    $k = 0;
    $p = 1.0;
    do {
        $k++;
        $p *= simRandFloat();
    } while ($p > $limit && $k < 1000);
    return $k - 1;
}

function simHour(int $ts): int
{
    return (int)(new DateTime('@' . $ts))->setTimezone(new DateTimeZone(SIM_TIMEZONE))->format('G');
}

function simDayStartTs(int $ts): int
{
    return (new DateTime('@' . $ts))->setTimezone(new DateTimeZone(SIM_TIMEZONE))->setTime(0, 0, 0)->getTimestamp();
}

/** Hệ số đông khách theo giờ trong ngày. */
function simHourFactor(int $ts): float
{
    if (!SIM_USE_HOUR_FACTOR) {
        return 1.0;
    }
    $h = simHour($ts);
    if ($h >= 7 && $h <= 8)   return 1.4;  // sáng đi làm / đi học
    if ($h >= 11 && $h <= 13) return 1.5;  // trưa
    if ($h >= 17 && $h <= 20) return 1.5;  // chiều tối
    if ($h >= 22 || $h <= 5)  return 0.5;  // khuya
    return 1.0;
}

/**
 * Khách có chịu giá không? r = giá bán / giá thị trường.
 * r = 1 -> 1.0 | r = 1.5 -> 0.4 | r >= 1.83 -> 0 (không ai mua) | r < 1 -> tới 1.3 (rẻ thì khách mua nhiều hơn).
 */
function simPriceFactor(float $price, float $baseCost): float
{
    if ($price <= 0) {
        return 0.0;
    }
    if ($baseCost <= 0) {
        return 1.0;
    }
    $r = $price / ($baseCost * SIM_MARKET_MARKUP);
    return max(0.0, min(1.3, 1.0 + 1.2 * (1.0 - $r)));
}

function simCustomerName(): string
{
    static $names = ['Lan', 'Minh', 'Hùng', 'Mai', 'Tuấn', 'Hà', 'Nam', 'Linh', 'Phúc', 'Trang',
                     'Khoa', 'Thảo', 'Bình', 'Vy', 'Đạt', 'Ngọc', 'Quân', 'Yến', 'Long', 'Chi'];
    return 'Khách ' . $names[mt_rand(0, count($names) - 1)];
}

/** Lượng khách qua lại (0-100) của mặt bằng; không đọc được thì coi như 50. */
function simPropertyTraffic(PDO $pdo, int $propertyId): int
{
    try {
        $stmt = $pdo->prepare('SELECT traffic FROM properties WHERE id = ?');
        $stmt->execute([$propertyId]);
        $v = $stmt->fetchColumn();
        return $v === false ? 50 : max(0, min(100, (int)$v));
    } catch (PDOException $e) {
        return 50;
    }
}

/** Thiếu bảng / cột của migration 04-05 -> báo rõ cần chạy migration thay vì 500 khó hiểu. */
function simRethrowSchemaError(Throwable $e): void
{
    if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
        error_log('shop_simulation: thiếu bảng/cột: ' . $e->getMessage());
        throw new ApiException("Chưa cập nhật database vận hành quán. Hãy chạy database/migrations/04_shop_runtime.sql và 05_game_days_reports.sql", 500);
    }
}

/**
 * Mô phỏng khách từ last_tick_ts đến $now. PHẢI gọi trong transaction đã khóa ví + dòng shop_runtime.
 * @return array{balance:float, reputation:float, capped:bool, delta:array}
 */
function simulateCustomers(PDO $pdo, array $shop, int $ownerId, array $rt, int $now, float $balance): array
{
    $shopId     = (int)$shop['id'];
    $day        = (int)($rt['day_number'] ?? 1);   // ngày chơi hiện tại: gắn vào từng đơn
    $reputation = (float)$rt['reputation'];
    $elapsed    = $now - (int)$rt['last_tick_ts'];
    $capped     = $elapsed > SIM_MAX_CATCHUP_SECONDS;
    if ($capped) {
        $elapsed = SIM_MAX_CATCHUP_SECONDS;
    }
    $windowStart = $now - $elapsed;
    $delta = ['customers' => 0, 'served' => 0, 'missed' => 0, 'walkby' => 0, 'revenue' => 0.0];
    $noop  = ['balance' => $balance, 'reputation' => $reputation, 'capped' => $capped, 'delta' => $delta];

    // Thực đơn = các món đã từng nhập hàng (có dòng kho + giá bán). Khóa dòng kho để trừ hàng an toàn.
    $stmt = $pdo->prepare('SELECT id, product_id, quantity, selling_price FROM inventory WHERE shop_id = ? AND selling_price > 0 FOR UPDATE');
    $stmt->execute([$shopId]);
    $menu = [];
    foreach ($stmt->fetchAll() as $row) {
        $menu[(int)$row['product_id']] = [
            'inv_id' => (int)$row['id'],
            'stock'  => (int)$row['quantity'],
            'price'  => (float)$row['selling_price'],
        ];
    }
    if (!$menu) {
        return $noop;
    }

    $stmt = $pdo->prepare('SELECT id, name, base_cost FROM products WHERE business_type_id = ?');
    $stmt->execute([(int)$shop['business_type_id']]);
    $valid = [];
    foreach ($stmt->fetchAll() as $p) {
        $pid = (int)$p['id'];
        if (isset($menu[$pid])) {
            $valid[$pid] = $menu[$pid] + ['name' => $p['name'], 'base_cost' => (float)$p['base_cost']];
        }
    }
    $menu = $valid;
    if (!$menu) {
        return $noop;
    }

    // Số khách đến trong khoảng thời gian này
    $traffic = simPropertyTraffic($pdo, (int)$shop['property_id']);
    $perMinute = (SIM_BASE_RATE + SIM_TRAFFIC_RATE * $traffic / 100.0)
               * (0.6 + 0.8 * $reputation / 100.0)
               * simHourFactor($windowStart + intdiv($elapsed, 2));
    $count = min(simPoisson($perMinute * $elapsed / 60.0), SIM_MAX_CUSTOMERS_TICK);
    if ($count <= 0) {
        return $noop;
    }

    $times = [];
    for ($i = 0; $i < $count; $i++) {
        $times[] = $windowStart + mt_rand(0, max(1, $elapsed));
    }
    sort($times);

    $ids    = array_keys($menu);
    $orders = [];
    $sold   = [];
    foreach ($times as $ts) {
        $ts = min($ts, $now);
        $delta['customers']++;
        $pid  = $ids[mt_rand(0, count($ids) - 1)];
        $item = $menu[$pid];
        $f    = simPriceFactor($item['price'], $item['base_cost']);

        if (simRandFloat() >= min(1.0, $f)) {           // chê giá cao -> đi qua, không vào quán
            $delta['walkby']++;
            continue;
        }
        $customer = simCustomerName();

        if ($item['stock'] <= 0) {                      // món hết hàng -> khách bỏ về, mất uy tín
            $delta['missed']++;
            $reputation = max(0.0, $reputation - 1.0);
            $orders[] = ['pid' => $pid, 'name' => $item['name'], 'qty' => 0, 'price' => $item['price'],
                         'total' => 0.0, 'cost' => 0.0, 'status' => 'missed', 'customer' => $customer, 'ts' => $ts];
            continue;
        }

        $qty = 1;
        if (simRandFloat() < 0.25 * $f) $qty++;         // giá mềm -> khách mua thêm
        if (simRandFloat() < 0.08)      $qty++;
        $qty   = min($qty, $item['stock']);
        $total = round($qty * $item['price'], 2);
        $cost  = round($qty * $item['base_cost'], 2);   // vốn mặt hàng của đơn này

        $menu[$pid]['stock'] -= $qty;
        $sold[$pid] = ($sold[$pid] ?? 0) + $qty;
        $reputation = min(100.0, $reputation + 0.2);
        $delta['served']++;
        $delta['revenue'] += $total;
        $orders[] = ['pid' => $pid, 'name' => $item['name'], 'qty' => $qty, 'price' => $item['price'],
                     'total' => $total, 'cost' => $cost, 'status' => 'served', 'customer' => $customer, 'ts' => $ts];
    }

    // 1) Trừ kho (điều kiện quantity >= ? là lớp bảo vệ thứ 2 ngoài khóa dòng)
    $upd = $pdo->prepare('UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?');
    foreach ($sold as $pid => $qty) {
        $upd->execute([$qty, $menu[$pid]['inv_id'], $qty]);
        if ($upd->rowCount() !== 1) {
            throw new ApiException("Kho thay đổi bất ngờ khi xử lý khách, vui lòng thử lại", 409);
        }
    }

    // 2) Cộng doanh thu vào ví (1 lần cho cả đợt khách)
    $revenue = round($delta['revenue'], 2);
    if ($revenue > 0 && !creditWallet($pdo, $ownerId, $revenue)) {
        throw new ApiException("Không thể cộng tiền vào ví", 500);
    }

    // 3) Ghi từng đơn vào shop_orders (nguồn cho nhật ký hoạt động + tổng kết ngày)
    $ins = $pdo->prepare('INSERT INTO shop_orders (shop_id, product_id, product_name, quantity, unit_price, total, cost, day_number, status, customer_name, arrived_ts)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($orders as $o) {
        $ins->execute([$shopId, $o['pid'], $o['name'], $o['qty'], $o['price'], $o['total'], $o['cost'], $day, $o['status'], $o['customer'], $o['ts']]);
    }

    // 4) Ghi sổ cái cho module Tài chính (tùy chọn: lỗi ở đây không làm hỏng việc bán hàng)
    try {
        $led = $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                              VALUES (?, ?, \'income\', \'sale\', ?, ?, ?)');
        $running = $balance;
        foreach ($orders as $o) {
            if ($o['status'] !== 'served') continue;
            $running = round($running + $o['total'], 2);
            $led->execute([$ownerId, $shopId, $o['total'], $running, $o['customer'] . ' mua ' . $o['qty'] . ' x ' . $o['name']]);
        }
    } catch (Exception $e) {
        error_log('shop_simulation: không ghi được bảng transactions: ' . $e->getMessage());
    }

    return [
        'balance'    => round($balance + $revenue, 2),
        'reputation' => $reputation,
        'capped'     => $capped,
        'delta'      => $delta,
    ];
}

/**
 * Cập nhật quán đến thời điểm hiện tại (và tùy chọn mở/đóng cửa) rồi trả trạng thái để client vẽ.
 * @param bool|null $setOpen true = mở cửa, false = đóng cửa (chốt khách đến lúc đóng), null = chỉ tick
 * @param int       $sinceOrderId chỉ trả các đơn có id lớn hơn (0 = trả vài đơn gần nhất)
 */
function shopRuntimeTick(PDO $pdo, int $shopId, int $ownerId, ?bool $setOpen = null, int $sinceOrderId = 0): array
{
    $shop = requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403
    $now  = time();
    $delta = ['customers' => 0, 'served' => 0, 'missed' => 0, 'walkby' => 0, 'revenue' => 0.0];
    $capped = false;

    try {
        $pdo->beginTransaction();

        // 1) Khóa ví trước (cùng thứ tự với các API khác)
        $balance = getWalletBalance($pdo, $ownerId, true);
        if ($balance === null) {
            throw new ApiException("Không tìm thấy người chơi", 404);
        }

        // 2) Khóa trạng thái vận hành của quán (quán mới: Ngày 1 bắt đầu từ bây giờ)
        $pdo->prepare('INSERT IGNORE INTO shop_runtime (shop_id, day_started_ts) VALUES (?, ?)')->execute([$shopId, $now]);
        $stmt = $pdo->prepare('SELECT * FROM shop_runtime WHERE shop_id = ? FOR UPDATE');
        $stmt->execute([$shopId]);
        $rt = $stmt->fetch();

        $isOpen     = (int)$rt['is_open'] === 1;
        $openedTs   = (int)$rt['opened_ts'];
        $lastTick   = (int)$rt['last_tick_ts'];
        $reputation = (float)$rt['reputation'];

        // 3) Quán đang mở -> mô phỏng khách từ lần tick trước đến giờ
        if ($isOpen && $now - $lastTick >= SIM_MIN_TICK_SECONDS) {
            $r = simulateCustomers($pdo, $shop, $ownerId, $rt, $now, $balance);
            $balance    = $r['balance'];
            $reputation = $r['reputation'];
            $delta      = $r['delta'];
            $capped     = $r['capped'];
            $lastTick   = $now;
        }

        // 4) Mở / đóng cửa
        if ($setOpen === true && !$isOpen) {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE shop_id = ?');
            $stmt->execute([$shopId]);
            if ((int)$stmt->fetchColumn() <= 0) {
                throw new ApiException("Kho đang trống. Hãy nhập hàng trước khi mở cửa.", 409);
            }
            $isOpen   = true;
            $openedTs = $now;
            $lastTick = $now; // khách chỉ đến TỪ LÚC MỞ CỬA, không tính lùi về trước
        } elseif ($setOpen === false && $isOpen) {
            $isOpen = false;
        }

        $pdo->prepare('UPDATE shop_runtime SET is_open = ?, opened_ts = ?, last_tick_ts = ?, reputation = ?,
                           customers_total = customers_total + ?, served_total = served_total + ?,
                           missed_total = missed_total + ?, walkby_total = walkby_total + ?,
                           revenue_total = revenue_total + ?
                       WHERE shop_id = ?')
            ->execute([$isOpen ? 1 : 0, $openedTs, $lastTick, $reputation,
                       $delta['customers'], $delta['served'], $delta['missed'], $delta['walkby'],
                       round($delta['revenue'], 2), $shopId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        simRethrowSchemaError($e);
        throw $e;
    }

    return shopRuntimeSnapshot($pdo, $shopId, $now, $sinceOrderId, $balance, $delta['customers'], $delta['walkby'], $capped);
}

/**
 * CHỐT CA: kết thúc ngày chơi hiện tại và sang ngày mới. Tất cả trong 1 transaction (lỗi giữa chừng = không đổi gì).
 *  1. Quán đang mở thì chốt nốt khách đến lúc này vào ngày đang kết.
 *  2. Lưu tổng kết ngày (đơn bán, đơn lỡ, doanh thu, vốn mặt hàng, tiền thuê) vào shop_daily_reports.
 *  3. Sang ngày mới. Nếu bước sang tháng mới (mỗi RENT_CYCLE_DAYS ngày) thì TỰ TRỪ tiền thuê mặt bằng vào ví
 *     và ghi sổ transactions (category 'rent'). Không đủ tiền -> 409, ngày KHÔNG sang (tiền khách vừa chốt cũng không đổi).
 *  4. Xóa nhật ký khách (shop_orders) cũ hơn ORDER_RETENTION_DAYS ngày chơi: sang Ngày 8 thì Ngày 1 bị xóa.
 *     Báo cáo trong shop_daily_reports KHÔNG bị xóa.
 * @return array{closed:array,new_day:int,rent_charged:float,purged_orders:int,state:array}
 */
function shopEndDay(PDO $pdo, int $shopId, int $ownerId, int $sinceOrderId = 0): array
{
    requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403 sớm, chưa khóa gì
    $now    = time();
    $delta  = ['customers' => 0, 'served' => 0, 'missed' => 0, 'walkby' => 0, 'revenue' => 0.0];
    $capped = false;

    try {
        $pdo->beginTransaction();

        // Thứ tự khóa: ví -> cửa hàng -> shop_runtime -> kho (giống close-shop.php)
        $balance = getWalletBalance($pdo, $ownerId, true);
        if ($balance === null) {
            throw new ApiException("Không tìm thấy người chơi", 404);
        }
        $shop = requireOwnedShop($pdo, $shopId, $ownerId, true); // kiểm tra lại dưới khóa: quán có thể vừa bị đóng

        $pdo->prepare('INSERT IGNORE INTO shop_runtime (shop_id, day_started_ts) VALUES (?, ?)')->execute([$shopId, $now]);
        $stmt = $pdo->prepare('SELECT * FROM shop_runtime WHERE shop_id = ? FOR UPDATE');
        $stmt->execute([$shopId]);
        $rt = $stmt->fetch();

        $day            = (int)$rt['day_number'];
        $dayStartedTs   = (int)$rt['day_started_ts'] ?: $now;
        $isOpen         = (int)$rt['is_open'] === 1;
        $lastTick       = (int)$rt['last_tick_ts'];
        $reputation     = (float)$rt['reputation'];
        $rentPaidUntil  = (int)$rt['rent_paid_until_day'];

        // 1) Chốt nốt khách đến tới lúc kết ca
        if ($isOpen && $now - $lastTick >= SIM_MIN_TICK_SECONDS) {
            $r = simulateCustomers($pdo, $shop, $ownerId, $rt, $now, $balance);
            $balance    = $r['balance'];
            $reputation = $r['reputation'];
            $delta      = $r['delta'];
            $capped     = $r['capped'];
            $lastTick   = $now;
        }

        // 2) Tổng kết ngày vừa kết (tính từ shop_orders của đúng ngày này)
        $t        = shopDayOrderTotals($pdo, $shopId, $day);
        $existing = shopDayReportRow($pdo, $shopId, $day);
        $rentOfDay = $existing ? (float)$existing['rent_cost'] : shopDefaultRent($pdo, $shop, $day);
        $pdo->prepare('INSERT INTO shop_daily_reports (shop_id, day_number, started_ts, closed_ts, served, missed, revenue, cost, rent_cost)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE started_ts = VALUES(started_ts), closed_ts = VALUES(closed_ts),
                           served = VALUES(served), missed = VALUES(missed), revenue = VALUES(revenue),
                           cost = VALUES(cost), rent_cost = VALUES(rent_cost)')
            ->execute([$shopId, $day, $dayStartedTs, $now, $t['served'], $t['missed'], $t['revenue'], $t['cost'], $rentOfDay]);
        $closed = shopReportRowOut($day, $dayStartedTs, $now, $t['served'], $t['missed'], $t['revenue'], $t['cost'], $rentOfDay, false);

        // 3) Sang ngày mới; bước sang tháng mới thì trừ tiền thuê mặt bằng
        $newDay       = $day + 1;
        $rentCharged  = 0.0;
        $monthlyRent  = shopMonthlyRent($pdo, (int)$shop['property_id']);
        while ($newDay > $rentPaidUntil) {
            if ($monthlyRent > 0) {
                if ($balance < $monthlyRent || !deductWallet($pdo, $ownerId, $monthlyRent)) {
                    throw new ApiException(
                        "Không đủ tiền trả tiền thuê mặt bằng tháng mới (cần " . number_format($monthlyRent, 0, ',', '.')
                        . "đ, hiện có " . number_format($balance, 0, ',', '.') . "đ). Hãy bán thêm hàng rồi chốt ca lại.",
                        409,
                        ["required" => $monthlyRent, "balance" => $balance]
                    );
                }
                $balance      = round($balance - $monthlyRent, 2);
                $rentCharged += $monthlyRent;
                try { // sổ cái là tùy chọn
                    $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                                   VALUES (?, ?, \'expense\', \'rent\', ?, ?, ?)')
                        ->execute([$ownerId, $shopId, $monthlyRent, $balance,
                                   'Tiền thuê mặt bằng #' . (int)$shop['property_id'] . ' tháng ' . (intdiv($newDay - 1, RENT_CYCLE_DAYS) + 1)
                                   . ' (Ngày ' . $newDay . ')']);
                } catch (Exception $e) {
                    error_log('shop_end_day: không ghi được bảng transactions: ' . $e->getMessage());
                }
            }
            $rentPaidUntil += RENT_CYCLE_DAYS;
        }
        $rentCharged = round($rentCharged, 2);

        // Dòng báo cáo của ngày mới (closed_ts = NULL = đang diễn ra), mang theo tiền thuê vừa trừ
        $pdo->prepare('INSERT INTO shop_daily_reports (shop_id, day_number, started_ts, rent_cost) VALUES (?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE started_ts = VALUES(started_ts), closed_ts = NULL, rent_cost = VALUES(rent_cost)')
            ->execute([$shopId, $newDay, $now, $rentCharged]);

        $pdo->prepare('UPDATE shop_runtime SET day_number = ?, day_started_ts = ?, rent_paid_until_day = ?,
                           last_tick_ts = ?, reputation = ?,
                           customers_total = customers_total + ?, served_total = served_total + ?,
                           missed_total = missed_total + ?, walkby_total = walkby_total + ?,
                           revenue_total = revenue_total + ?
                       WHERE shop_id = ?')
            ->execute([$newDay, $now, $rentPaidUntil, $lastTick, $reputation,
                       $delta['customers'], $delta['served'], $delta['missed'], $delta['walkby'],
                       round($delta['revenue'], 2), $shopId]);

        // 4) Xóa nhật ký khách quá hạn: giữ ORDER_RETENTION_DAYS ngày gần nhất (sang Ngày 8 -> xóa Ngày 1)
        $purged = 0;
        $oldestDeleted = $newDay - ORDER_RETENTION_DAYS;
        if ($oldestDeleted >= 1) {
            $del = $pdo->prepare('DELETE FROM shop_orders WHERE shop_id = ? AND day_number <= ?');
            $del->execute([$shopId, $oldestDeleted]);
            $purged = $del->rowCount();
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        simRethrowSchemaError($e);
        throw $e;
    }

    return [
        'closed'        => $closed,
        'new_day'       => $newDay,
        'rent_charged'  => $rentCharged,
        'purged_orders' => $purged,
        'state'         => shopRuntimeSnapshot($pdo, $shopId, $now, $sinceOrderId, $balance, $delta['customers'], $delta['walkby'], $capped),
    ];
}

/** Đọc (không ghi) trạng thái quán để trả cho client. */
function shopRuntimeSnapshot(PDO $pdo, int $shopId, int $now, int $sinceOrderId, float $balance, int $newCustomers, int $walkedBy, bool $capped): array
{
    $stmt = $pdo->prepare('SELECT * FROM shop_runtime WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $rt = $stmt->fetch();

    $cols = 'id, product_id, product_name, quantity, unit_price, total, status, customer_name, arrived_ts, day_number';
    if ($sinceOrderId > 0) {
        $stmt = $pdo->prepare("SELECT $cols FROM shop_orders WHERE shop_id = ? AND id > ? ORDER BY id ASC LIMIT " . SIM_FEED_MAX_LIMIT);
        $stmt->execute([$shopId, $sinceOrderId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM (SELECT $cols FROM shop_orders WHERE shop_id = ? ORDER BY id DESC LIMIT " . SIM_FEED_DEFAULT_LIMIT . ") t ORDER BY id ASC");
        $stmt->execute([$shopId]);
    }
    $orders = array_map(function ($o) {
        return [
            'id'           => (int)$o['id'],
            'product_id'   => $o['product_id'] === null ? null : (int)$o['product_id'],
            'product_name' => $o['product_name'],
            'quantity'     => (int)$o['quantity'],
            'unit_price'   => (float)$o['unit_price'],
            'total'        => (float)$o['total'],
            'status'       => $o['status'],
            'customer'     => $o['customer_name'],
            'arrived_ts'   => (int)$o['arrived_ts'],
            'day_number'   => (int)$o['day_number'],
        ];
    }, $stmt->fetchAll());

    $stmt = $pdo->prepare('SELECT product_id, quantity FROM inventory WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $stock = [];
    foreach ($stmt->fetchAll() as $row) {
        $stock[(int)$row['product_id']] = (int)$row['quantity'];
    }

    // "Hôm nay" = NGÀY CHƠI hiện tại (không phải ngày lịch): đổi sang ngày mới khi người chơi chốt ca
    $day   = (int)$rt['day_number'];
    $t     = shopDayOrderTotals($pdo, $shopId, $day);
    $today = [
        'served'  => $t['served'],
        'missed'  => $t['missed'],
        'revenue' => $t['revenue'],
        'cost'    => $t['cost'],                                // vốn mặt hàng (giá vốn của hàng đã bán)
        'profit'  => round($t['revenue'] - $t['cost'], 2),      // lợi nhuận = doanh thu - vốn mặt hàng
    ];

    return [
        'shop_id'        => $shopId,
        'is_open'        => (int)$rt['is_open'] === 1,
        'opened_ts'      => (int)$rt['opened_ts'],
        'server_ts'      => $now,
        'day'            => $day,
        'next_rent_day'  => (int)$rt['rent_paid_until_day'] + 1,   // ngày sẽ bị trừ tiền thuê tháng kế tiếp
        'reputation'     => round((float)$rt['reputation'], 1),
        'balance'        => round($balance, 2),
        'new_customers'  => $newCustomers,
        'walked_by'      => $walkedBy,
        'catchup_capped' => $capped,
        'orders'         => $orders,
        'stock'          => $stock,
        'today'          => $today,
        'totals'         => [
            'customers' => (int)$rt['customers_total'],
            'served'    => (int)$rt['served_total'],
            'missed'    => (int)$rt['missed_total'],
            'walkby'    => (int)$rt['walkby_total'],
            'revenue'   => (float)$rt['revenue_total'],
        ],
    ];
}
