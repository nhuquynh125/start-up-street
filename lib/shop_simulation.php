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
// Thứ tự khóa: ví -> shop_runtime -> kho (ví luôn khóa TRƯỚC, giống purchase-inventory.php) để không deadlock.

require_once __DIR__ . '/shop_helpers.php';

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

/**
 * Mô phỏng khách từ last_tick_ts đến $now. PHẢI gọi trong transaction đã khóa ví + dòng shop_runtime.
 * @return array{balance:float, reputation:float, capped:bool, delta:array}
 */
function simulateCustomers(PDO $pdo, array $shop, int $ownerId, array $rt, int $now, float $balance): array
{
    $shopId     = (int)$shop['id'];
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
                         'total' => 0.0, 'status' => 'missed', 'customer' => $customer, 'ts' => $ts];
            continue;
        }

        $qty = 1;
        if (simRandFloat() < 0.25 * $f) $qty++;         // giá mềm -> khách mua thêm
        if (simRandFloat() < 0.08)      $qty++;
        $qty   = min($qty, $item['stock']);
        $total = round($qty * $item['price'], 2);

        $menu[$pid]['stock'] -= $qty;
        $sold[$pid] = ($sold[$pid] ?? 0) + $qty;
        $reputation = min(100.0, $reputation + 0.2);
        $delta['served']++;
        $delta['revenue'] += $total;
        $orders[] = ['pid' => $pid, 'name' => $item['name'], 'qty' => $qty, 'price' => $item['price'],
                     'total' => $total, 'status' => 'served', 'customer' => $customer, 'ts' => $ts];
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

    // 3) Ghi từng đơn vào shop_orders (nguồn cho bảng hoạt động của quán)
    $ins = $pdo->prepare('INSERT INTO shop_orders (shop_id, product_id, product_name, quantity, unit_price, total, status, customer_name, arrived_ts)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($orders as $o) {
        $ins->execute([$shopId, $o['pid'], $o['name'], $o['qty'], $o['price'], $o['total'], $o['status'], $o['customer'], $o['ts']]);
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

        // 2) Khóa trạng thái vận hành của quán
        $pdo->prepare('INSERT IGNORE INTO shop_runtime (shop_id) VALUES (?)')->execute([$shopId]);
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
        if ($e instanceof PDOException && $e->getCode() === '42S02') {
            error_log('shop_simulation: thiếu bảng: ' . $e->getMessage());
            throw new ApiException("Chưa có bảng vận hành quán. Hãy chạy database/migrations/04_shop_runtime.sql", 500);
        }
        throw $e;
    }

    return shopRuntimeSnapshot($pdo, $shopId, $now, $sinceOrderId, $balance, $delta['customers'], $delta['walkby'], $capped);
}

/** Đọc (không ghi) trạng thái quán để trả cho client. */
function shopRuntimeSnapshot(PDO $pdo, int $shopId, int $now, int $sinceOrderId, float $balance, int $newCustomers, int $walkedBy, bool $capped): array
{
    $stmt = $pdo->prepare('SELECT * FROM shop_runtime WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $rt = $stmt->fetch();

    $cols = 'id, product_id, product_name, quantity, unit_price, total, status, customer_name, arrived_ts';
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
        ];
    }, $stmt->fetchAll());

    $stmt = $pdo->prepare('SELECT product_id, quantity FROM inventory WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $stock = [];
    foreach ($stmt->fetchAll() as $row) {
        $stock[(int)$row['product_id']] = (int)$row['quantity'];
    }

    $today = ['served' => 0, 'missed' => 0, 'revenue' => 0.0];
    $stmt = $pdo->prepare('SELECT status, COUNT(*) AS c, COALESCE(SUM(total), 0) AS t FROM shop_orders WHERE shop_id = ? AND arrived_ts >= ? GROUP BY status');
    $stmt->execute([$shopId, simDayStartTs($now)]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['status'] === 'served') {
            $today['served']  = (int)$row['c'];
            $today['revenue'] = (float)$row['t'];
        } else {
            $today['missed'] = (int)$row['c'];
        }
    }

    return [
        'shop_id'        => $shopId,
        'is_open'        => (int)$rt['is_open'] === 1,
        'opened_ts'      => (int)$rt['opened_ts'],
        'server_ts'      => $now,
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
