<?php
// GET api/get-reports.php?shop_id=5
// Báo cáo doanh thu của 1 cửa hàng: theo ngày (tối đa 60 ngày gần nhất), theo tuần (7 ngày chơi), theo tháng (30 ngày chơi)
// + tổng cộng toàn bộ thời gian (cho mục Tài chính). Ngày đang diễn ra được tính trực tiếp từ shop_orders nên luôn mới nhất.
// Chủ quán = người đang đăng nhập, KHÔNG nhận owner_user_id từ client.
require_once dirname(__DIR__, 2) . '/lib/shop_reports.php';

requireMethod('GET');

try {
    $ownerId = getCurrentUserId(); // 401 nếu chưa đăng nhập

    $shopId = toPositiveInt($_GET['shop_id'] ?? null);
    if ($shopId === null) {
        throw new ApiException("Thiếu hoặc sai shop_id", 400);
    }
    $shop = requireOwnedShop($pdo, $shopId, $ownerId); // 404 / 403

    $rt = null;
    try {
        $stmt = $pdo->prepare('SELECT * FROM shop_runtime WHERE shop_id = ?');
        $stmt->execute([$shopId]);
        $row = $stmt->fetch();
        $rt = $row === false ? null : $row;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
            throw new ApiException("Chưa có bảng báo cáo. Hãy chạy database/migrations/04_shop_runtime.sql và 05_game_days_reports.sql", 500);
        }
        throw $e;
    }

    try {
        $rows = shopReportRows($pdo, $shop, $rt);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
            throw new ApiException("Chưa có bảng báo cáo. Hãy chạy database/migrations/05_game_days_reports.sql", 500);
        }
        throw $e;
    }

    $totals = ['served' => 0, 'missed' => 0, 'revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0, 'rent' => 0.0, 'net' => 0.0];
    foreach ($rows as $r) {
        foreach ($totals as $k => $_) {
            $totals[$k] += $r[$k];
        }
    }
    foreach (['revenue', 'cost', 'profit', 'rent', 'net'] as $k) {
        $totals[$k] = round($totals[$k], 2);
    }

    $balance = null;
    try {
        $balance = getWalletBalance($pdo, $ownerId);
    } catch (Exception $e) {
        error_log('get-reports: không đọc được ví: ' . $e->getMessage());
    }

    apiRespond(true, "Lấy báo cáo thành công", [
        "shop_id"     => $shopId,
        "current_day" => $rt ? (int)$rt['day_number'] : 1,
        "week_days"   => REPORT_WEEK_DAYS,
        "month_days"  => RENT_CYCLE_DAYS,
        "balance"     => $balance,
        "days"        => array_slice($rows, -REPORT_MAX_DAY_ROWS),
        "weeks"       => shopGroupReports($rows, REPORT_WEEK_DAYS),
        "months"      => shopGroupReports($rows, RENT_CYCLE_DAYS),
        "totals"      => $totals,
    ]);
} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), $e->data, $e->getCode());
} catch (Throwable $e) {
    error_log('get-reports: ' . $e->getMessage());
    apiRespond(false, "Lỗi truy vấn báo cáo", null, 500);
}
