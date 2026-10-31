<?php
// GET api/get-all-shops-finance.php
// Trả về báo cáo tài chính tổng hợp của TOÀN BỘ các cửa hàng thuộc người chơi đang đăng nhập.
require_once dirname(__DIR__, 2) . '/lib/shop_helpers.php';
require_once dirname(__DIR__, 2) . '/lib/shop_reports.php';
require_once dirname(__DIR__, 2) . '/lib/shop_simulation.php';

requireMethod('GET');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    // 1. Lấy danh sách tất cả cửa hàng của người chơi
    $stmt = $pdo->prepare('
        SELECT s.id AS shop_id, s.name, s.property_id, s.business_type_id,
               bt.name AS business_type_name,
               p.code AS property_code, p.price AS rent_price
        FROM shops s
        JOIN business_types bt ON bt.id = s.business_type_id
        LEFT JOIN properties p ON p.id = s.property_id
        WHERE s.owner_user_id = ?
        ORDER BY s.id
    ');
    $stmt->execute([$ownerId]);
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Tự động mô phỏng / cập nhật khách cho tất cả cửa hàng đang mở của người chơi
    // Giúp phát sinh đơn bán và cập nhật lịch sử giao dịch liên tục mà người chơi không cần phải vào từng cửa hàng.
    foreach ($shops as $s) {
        try {
            shopRuntimeTick($pdo, (int)$s['shop_id'], $ownerId, null, 0);
        } catch (Throwable $e) {
            error_log('get-all-shops-finance: tick shop #' . $s['shop_id'] . ' thất bại: ' . $e->getMessage());
        }
    }

    // 3. Lấy số dư ví người chơi sau khi các cửa hàng đã bán hàng và cộng tiền
    $balance = getWalletBalance($pdo, $ownerId);

    // 4. Với mỗi cửa hàng, tính tổng doanh thu, vốn hàng, tiền thuê, lợi nhuận, đơn bán
    $shopsFinance = [];
    $systemTotals = [
        'shop_count'    => count($shops),
        'revenue'       => 0.0,
        'cost'          => 0.0,
        'rent'          => 0.0,
        'profit'        => 0.0,
        'served'        => 0,
        'missed'        => 0,
        'inventory_val' => 0.0,
        'inventory_qty' => 0,
    ];

    $shopIds = array_map(fn($s) => (int)$s['shop_id'], $shops);

    // Tồn kho từng shop (nếu có shop)
    $invMap = [];
    if (!empty($shopIds)) {
        $inClause = implode(',', $shopIds);
        $invStmt = $pdo->query("
            SELECT i.shop_id,
                   SUM(i.quantity) AS total_qty,
                   SUM(i.quantity * p.base_cost) AS total_cost_val,
                   SUM(i.quantity * i.selling_price) AS total_sell_val
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            WHERE i.shop_id IN ($inClause)
            GROUP BY i.shop_id
        ");
        foreach ($invStmt->fetchAll(PDO::FETCH_ASSOC) as $ir) {
            $invMap[(int)$ir['shop_id']] = [
                'qty'      => (int)$ir['total_qty'],
                'cost_val' => (float)$ir['total_cost_val'],
                'sell_val' => (float)$ir['total_sell_val'],
            ];
        }
    }

    // Báo cáo ngày đã chốt ca của từng shop (từ shop_daily_reports)
    $repMap = [];
    if (!empty($shopIds)) {
        $inClause = implode(',', $shopIds);
        $repStmt = $pdo->query("
            SELECT shop_id,
                   COUNT(id) AS closed_days_count,
                   MAX(day_number) AS max_day,
                   SUM(served) AS served,
                   SUM(missed) AS missed,
                   SUM(revenue) AS revenue,
                   SUM(cost) AS cost,
                   SUM(rent_cost) AS rent
            FROM shop_daily_reports
            WHERE shop_id IN ($inClause) AND closed_ts IS NOT NULL
            GROUP BY shop_id
        ");
        foreach ($repStmt->fetchAll(PDO::FETCH_ASSOC) as $rr) {
            $repMap[(int)$rr['shop_id']] = [
                'closed_days' => (int)$rr['closed_days_count'],
                'max_day'     => (int)$rr['max_day'],
                'served'      => (int)$rr['served'],
                'missed'      => (int)$rr['missed'],
                'revenue'     => (float)$rr['revenue'],
                'cost'        => (float)$rr['cost'],
                'rent'        => (float)$rr['rent'],
            ];
        }
    }

    // Runtime info (is_open, reputation, day_number)
    $rtMap = [];
    if (!empty($shopIds)) {
        $inClause = implode(',', $shopIds);
        $rtStmt = $pdo->query("
            SELECT shop_id, day_number, is_open, reputation
            FROM shop_runtime
            WHERE shop_id IN ($inClause)
        ");
        foreach ($rtStmt->fetchAll(PDO::FETCH_ASSOC) as $rtr) {
            $rtMap[(int)$rtr['shop_id']] = [
                'day_number' => (int)$rtr['day_number'],
                'is_open'    => (bool)$rtr['is_open'],
                'reputation' => (int)$rtr['reputation'],
            ];
        }
    }

    foreach ($shops as $s) {
        $sid = (int)$s['shop_id'];
        $rep = $repMap[$sid] ?? [
            'closed_days' => 0, 'max_day' => 0, 'served' => 0, 'missed' => 0,
            'revenue' => 0.0, 'cost' => 0.0, 'rent' => 0.0
        ];
        $inv = $invMap[$sid] ?? ['qty' => 0, 'cost_val' => 0.0, 'sell_val' => 0.0];
        $rt  = $rtMap[$sid]  ?? ['day_number' => 1, 'is_open' => true, 'reputation' => 100];

        $profit = round($rep['revenue'] - $rep['cost'], 2);

        $shopData = [
            'shop_id'            => $sid,
            'name'               => $s['name'],
            'property_id'        => (int)$s['property_id'],
            'property_code'      => $s['property_code'] ?? ('#' . $s['property_id']),
            'business_type_id'   => (int)$s['business_type_id'],
            'business_type_name' => $s['business_type_name'],
            'rent_price'         => (float)($s['rent_price'] ?? 0),
            'day_number'         => $rt['day_number'],
            'is_open'            => $rt['is_open'],
            'reputation'         => $rt['reputation'],
            'closed_days'        => $rep['closed_days'],
            'served'             => $rep['served'],
            'missed'             => $rep['missed'],
            'revenue'            => round($rep['revenue'], 2),
            'cost'               => round($rep['cost'], 2),
            'rent'               => round($rep['rent'], 2),
            'profit'             => $profit,
            'inventory_qty'      => $inv['qty'],
            'inventory_cost'     => round($inv['cost_val'], 2),
            'inventory_sell'     => round($inv['sell_val'], 2),
        ];

        $shopsFinance[] = $shopData;

        $systemTotals['revenue']       += $rep['revenue'];
        $systemTotals['cost']          += $rep['cost'];
        $systemTotals['rent']          += $rep['rent'];
        $systemTotals['profit']        += $profit;
        $systemTotals['served']        += $rep['served'];
        $systemTotals['missed']        += $rep['missed'];
        $systemTotals['inventory_val'] += $inv['cost_val'];
        $systemTotals['inventory_qty'] += $inv['qty'];
    }

    // Làm tròn số
    foreach (['revenue', 'cost', 'rent', 'profit', 'inventory_val'] as $k) {
        $systemTotals[$k] = round($systemTotals[$k], 2);
    }

    // 4. Doanh thu theo từng ngày của toàn hệ thống (gom từ các ngày đã chốt)
    $dailyTrends = [];
    if (!empty($shopIds)) {
        $inClause = implode(',', $shopIds);
        $trendStmt = $pdo->query("
            SELECT day_number,
                   SUM(revenue) AS revenue,
                   SUM(cost) AS cost,
                   SUM(rent_cost) AS rent,
                   SUM(served) AS served
            FROM shop_daily_reports
            WHERE shop_id IN ($inClause) AND closed_ts IS NOT NULL
            GROUP BY day_number
            ORDER BY day_number
        ");
        foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $tr) {
            $dailyTrends[] = [
                'day'     => (int)$tr['day_number'],
                'revenue' => round((float)$tr['revenue'], 2),
                'cost'    => round((float)$tr['cost'], 2),
                'rent'    => round((float)$tr['rent'], 2),
                'served'  => (int)$tr['served'],
            ];
        }
    }

    // 5. Giao dịch tài chính gần nhất của người chơi
    $txStmt = $pdo->prepare('
        SELECT t.id, t.shop_id, s.name AS shop_name, t.type, t.category,
               t.amount, t.balance_after, t.description, t.created_at
        FROM transactions t
        LEFT JOIN shops s ON s.id = t.shop_id
        WHERE t.user_id = ?
        ORDER BY t.id DESC
        LIMIT 30
    ');
    $txStmt->execute([$ownerId]);
    $recentTransactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    apiRespond(true, "Lấy dữ liệu tài chính toàn hệ thống thành công", [
        'balance'             => $balance,
        'system_totals'       => $systemTotals,
        'shops'               => $shopsFinance,
        'daily_trends'        => $dailyTrends,
        'recent_transactions' => $recentTransactions,
    ]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Exception $e) {
    apiRespond(false, "Lỗi server: " . $e->getMessage(), null, 500);
}
