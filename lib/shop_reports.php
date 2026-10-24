<?php
// lib/shop_reports.php — ngày chơi, tiền thuê mặt bằng theo tháng và báo cáo doanh thu (ngày / tuần / tháng)
//
// Quy ước:
//  - "Ngày" của quán là NGÀY CHƠI, không phải ngày lịch: bắt đầu ở Ngày 1 và chỉ sang ngày mới khi người chơi bấm
//    "Chốt ca" (api/end-day.php). Nhờ lưu ở MySQL nên tải lại trang / đổi máy vẫn thấy đúng ngày.
//  - Tuần = 7 ngày chơi, tháng = 30 ngày chơi (cùng chu kỳ trả tiền thuê mặt bằng).
//  - shop_orders (nhật ký khách) chỉ giữ ORDER_RETENTION_DAYS ngày chơi gần nhất; shop_daily_reports giữ tổng kết
//    từng ngày mãi mãi, nên báo cáo tuần / tháng / tài chính không mất khi nhật ký bị xóa.
//  - Vốn mặt hàng = giá vốn (products.base_cost) của hàng ĐÃ BÁN.
//    Lợi nhuận = doanh thu - vốn mặt hàng. Lãi ròng = lợi nhuận - tiền thuê mặt bằng.

require_once __DIR__ . '/shop_helpers.php';

const RENT_CYCLE_DAYS      = 30;  // 1 tháng = 30 ngày chơi; hết chu kỳ thì tự trừ tiền thuê tháng mới
const REPORT_WEEK_DAYS     = 7;   // 1 tuần = 7 ngày chơi
const ORDER_RETENTION_DAYS = 7;   // nhật ký khách giữ 7 ngày: sang Ngày 8 thì xóa Ngày 1
const REPORT_MAX_DAY_ROWS  = 60;  // số dòng "theo ngày" tối đa trả về (tuần / tháng vẫn tính từ TOÀN BỘ ngày)

/** Tiền thuê tháng đầu (đã trả lúc thuê, rentals.price_paid) được tính vào Ngày 1; các ngày khác mặc định 0. */
function shopDefaultRent(PDO $pdo, array $shop, int $day): float
{
    if ($day !== 1) {
        return 0.0;
    }
    try {
        $stmt = $pdo->prepare('SELECT price_paid FROM rentals WHERE property_id = ? AND user_id = ?');
        $stmt->execute([(int)$shop['property_id'], (int)$shop['owner_user_id']]);
        $v = $stmt->fetchColumn();
        return $v === false ? 0.0 : (float)$v;
    } catch (PDOException $e) {
        return 0.0;
    }
}

/** Giá thuê 1 tháng của mặt bằng (properties.price, nguồn giá duy nhất ở server). 0 nếu không đọc được. */
function shopMonthlyRent(PDO $pdo, int $propertyId): float
{
    try {
        $stmt = $pdo->prepare('SELECT price FROM properties WHERE id = ?');
        $stmt->execute([$propertyId]);
        $v = $stmt->fetchColumn();
        return $v === false ? 0.0 : (float)$v;
    } catch (PDOException $e) {
        error_log('shop_reports: không đọc được giá thuê mặt bằng: ' . $e->getMessage());
        return 0.0;
    }
}

/** Số đơn / doanh thu / vốn mặt hàng của MỘT ngày chơi, tính từ shop_orders. */
function shopDayOrderTotals(PDO $pdo, int $shopId, int $day): array
{
    $stmt = $pdo->prepare('SELECT status, COUNT(*) AS c, COALESCE(SUM(total), 0) AS t, COALESCE(SUM(cost), 0) AS k
                           FROM shop_orders WHERE shop_id = ? AND day_number = ? GROUP BY status');
    $stmt->execute([$shopId, $day]);
    $out = ['served' => 0, 'missed' => 0, 'revenue' => 0.0, 'cost' => 0.0];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['status'] === 'served') {
            $out['served']  = (int)$row['c'];
            $out['revenue'] = (float)$row['t'];
            $out['cost']    = (float)$row['k'];
        } else {
            $out['missed'] = (int)$row['c'];
        }
    }
    return $out;
}

/** Dòng shop_daily_reports của một ngày (null nếu chưa có). */
function shopDayReportRow(PDO $pdo, int $shopId, int $day): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM shop_daily_reports WHERE shop_id = ? AND day_number = ?');
    $stmt->execute([$shopId, $day]);
    $r = $stmt->fetch();
    return $r === false ? null : $r;
}

/** Chuẩn hóa một dòng báo cáo trả cho client. */
function shopReportRowOut(int $day, int $startedTs, ?int $closedTs, int $served, int $missed,
                          float $revenue, float $cost, float $rent, bool $open): array
{
    $profit = round($revenue - $cost, 2);
    return [
        'day'        => $day,
        'started_ts' => $startedTs,
        'closed_ts'  => $closedTs,
        'served'     => $served,
        'missed'     => $missed,
        'revenue'    => round($revenue, 2),
        'cost'       => round($cost, 2),      // vốn mặt hàng
        'profit'     => $profit,              // lợi nhuận = doanh thu - vốn mặt hàng
        'rent'       => round($rent, 2),      // tiền thuê mặt bằng trả trong ngày này
        'net'        => round($profit - $rent, 2),
        'open'       => $open,
    ];
}

/**
 * Mọi ngày của quán theo thứ tự tăng dần: các ngày đã chốt (từ shop_daily_reports) + ngày đang diễn ra (tính trực tiếp
 * từ shop_orders nên luôn mới nhất). $rt = dòng shop_runtime (null nếu quán chưa từng được tick = Ngày 1).
 */
function shopReportRows(PDO $pdo, array $shop, ?array $rt): array
{
    $shopId = (int)$shop['id'];
    $curDay = $rt ? (int)$rt['day_number'] : 1;
    $rows   = [];

    $stmt = $pdo->prepare('SELECT day_number, started_ts, closed_ts, served, missed, revenue, cost, rent_cost
                           FROM shop_daily_reports
                           WHERE shop_id = ? AND day_number < ? AND closed_ts IS NOT NULL
                           ORDER BY day_number');
    $stmt->execute([$shopId, $curDay]);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = shopReportRowOut((int)$r['day_number'], (int)$r['started_ts'], (int)$r['closed_ts'],
            (int)$r['served'], (int)$r['missed'], (float)$r['revenue'], (float)$r['cost'], (float)$r['rent_cost'], false);
    }

    $t        = shopDayOrderTotals($pdo, $shopId, $curDay);
    $existing = shopDayReportRow($pdo, $shopId, $curDay);
    $rent     = $existing ? (float)$existing['rent_cost'] : shopDefaultRent($pdo, $shop, $curDay);
    $started  = $rt ? (int)$rt['day_started_ts'] : 0;
    $rows[] = shopReportRowOut($curDay, $started, null, $t['served'], $t['missed'], $t['revenue'], $t['cost'], $rent, true);

    return $rows;
}

/**
 * Gom các ngày thành kỳ dài hơn: $size = 7 (tuần) hoặc 30 (tháng).
 * Kỳ k gồm các ngày từ (k-1)*size+1 đến k*size. Kỳ chứa ngày đang diễn ra có open = true.
 */
function shopGroupReports(array $days, int $size): array
{
    $out = [];
    foreach ($days as $d) {
        $k = intdiv($d['day'] - 1, $size) + 1;
        if (!isset($out[$k])) {
            $out[$k] = [
                'period' => $k, 'from_day' => ($k - 1) * $size + 1, 'to_day' => $k * $size, 'days' => 0,
                'served' => 0, 'missed' => 0, 'revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0,
                'rent' => 0.0, 'net' => 0.0, 'open' => false,
            ];
        }
        $g = &$out[$k];
        $g['days']++;
        $g['served']  += $d['served'];
        $g['missed']  += $d['missed'];
        $g['revenue'] += $d['revenue'];
        $g['cost']    += $d['cost'];
        $g['profit']  += $d['profit'];
        $g['rent']    += $d['rent'];
        $g['net']     += $d['net'];
        if ($d['open']) {
            $g['open'] = true;
        }
        unset($g);
    }
    foreach ($out as &$g) {
        foreach (['revenue', 'cost', 'profit', 'rent', 'net'] as $f) {
            $g[$f] = round($g[$f], 2);
        }
    }
    unset($g);
    return array_values($out);
}
