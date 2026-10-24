<?php
// Chốt ca chuyển doanh thu vào ví thật + phí mặt bằng/điện nước thu theo THÁNG.
// Chạy 1 lần từ thư mục gốc dự án, SAU KHI đã chép 3 file mới vào đúng chỗ:
//     lib/shop_billing.php
//     public/api/end-day.php
//     database/migrations/06_settlement.sql
//   rồi:   php tools/apply-settlement.php
//   rồi:   php tools/run_migration.php     (chạy migration 06: thêm cột két quán / mốc chốt ca / mốc thu phí)
//
// Script vá: lib/shop_simulation.php, public/api/close-shop.php, public/shop-counter.html,
//            public/js/wallet-api.js, public/js/game.js, public/js/shop-live.js,
//            public/startup-street-ui.html, tools/run_migration.php
// Giống các script apply-* trước: chỉ sửa đúng các dòng cần thiết, tạo file .bak,
// và nếu BẤT KỲ chỗ nào không khớp thì KHÔNG ghi gì cả.

$root = $argv[1] ?? dirname(__DIR__);

foreach (['lib/shop_billing.php', 'public/api/end-day.php', 'database/migrations/06_settlement.sql'] as $need) {
    if (!is_file("$root/$need")) {
        fwrite(STDERR, "Thiếu $need. Hãy chép file đó vào dự án trước rồi chạy lại.\n");
        exit(1);
    }
}

$files = [];

// ══════════════════════════ lib/shop_simulation.php ══════════════════════════
$e = [];

$e[] = [
    "require_once __DIR__ . '/shop_helpers.php';",
    "require_once __DIR__ . '/shop_helpers.php';\nrequire_once __DIR__ . '/shop_billing.php';",
];

// Doanh thu không cộng vào ví nữa mà vào két quán (cộng ở UPDATE shop_runtime bên dưới)
$f = <<<'EOT'
    // 2) Cộng doanh thu vào ví (1 lần cho cả đợt khách)
    $revenue = round($delta['revenue'], 2);
    if ($revenue > 0 && !creditWallet($pdo, $ownerId, $revenue)) {
        throw new ApiException("Không thể cộng tiền vào ví", 500);
    }
EOT;
$r = <<<'EOT'
    // 2) Doanh thu vào KÉT QUÁN (shop_runtime.till_balance, cộng ở shopRuntimeTick). Chỉ chuyển vào ví khi chốt ca (api/end-day.php).
    $revenue = round($delta['revenue'], 2);
EOT;
$e[] = [$f, $r];

// Sổ cái: bán hàng ghi 1 dòng lúc chốt ca thay vì từng khách (balance_after từng khách sẽ sai vì ví không đổi lúc bán)
$f = <<<'EOT'
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
EOT;
$r = <<<'EOT'
    // 4) Sổ cái: doanh thu bán hàng được ghi 1 dòng lúc chốt ca (api/end-day.php), không ghi từng khách ở đây
    //    vì ví chưa đổi lúc bán (tiền nằm trong két quán).
EOT;
$e[] = [$f, $r];

$e[] = ["'balance'    => round(\$balance + \$revenue, 2),", "'balance'    => \$balance, // ví KHÔNG đổi lúc bán: doanh thu nằm trong két quán"];

$e[] = ['$capped = false;', "\$capped = false;\n    \$feesCharged = 0.0;\n    \$feesOverdue = false;"];

$f = <<<'EOT'
        // 4) Mở / đóng cửa
EOT;
$r = <<<'EOT'
        // 3b) Phí THÁNG (mặt bằng + điện nước): server tự trừ ví và ghi transactions (lib/shop_billing.php).
        //     Ví không đủ -> quán bị buộc đóng cửa cho tới khi đủ tiền (chốt ca để lấy doanh thu từ két).
        $bill        = chargeMonthlyFees($pdo, $shop, $ownerId, $rt, $now, $balance);
        $balance     = $bill['balance'];
        $lastBilled  = $bill['last_billed_ts'];
        $feesCharged = $bill['charged'];
        $feesOverdue = $bill['overdue'];
        if ($feesOverdue) {
            $isOpen = false;
        }
        if ($feesOverdue && $setOpen === true) {
            throw new ApiException("Ví không đủ tiền đóng phí tháng (mặt bằng + điện nước). Hãy chốt ca để chuyển doanh thu vào ví rồi mở lại.", 409);
        }

        // 4) Mở / đóng cửa
EOT;
$e[] = [$f, $r];

// UPDATE shop_runtime: thêm két + mốc thu phí
$e[] = [
    'revenue_total = revenue_total + ?',
    'revenue_total = revenue_total + ?, till_balance = till_balance + ?, last_billed_ts = ?',
];
$e[] = [
    "round(\$delta['revenue'], 2), \$shopId]);",
    "round(\$delta['revenue'], 2), round(\$delta['revenue'], 2), \$lastBilled, \$shopId]);",
];

$f = <<<'EOT'
if ($e instanceof PDOException && $e->getCode() === '42S02') {
EOT;
$r = <<<'EOT'
if ($e instanceof PDOException && $e->getCode() === '42S22') {
            error_log('shop_simulation: thiếu cột: ' . $e->getMessage());
            throw new ApiException("Thiếu cột két quán / mốc thu phí. Hãy chạy database/migrations/06_settlement.sql (php tools/run_migration.php)", 500);
        }
        if ($e instanceof PDOException && $e->getCode() === '42S02') {
EOT;
$e[] = [$f, $r];

// Snapshot trả két quán cho client
$e[] = [
    "'new_customers'  => \$newCustomers,",
    "'till_balance'   => round((float)(\$rt['till_balance'] ?? 0), 2), // doanh thu chưa chuyển vào ví (chốt ca để nhận)\n        'new_customers'  => \$newCustomers,",
];

$f = <<<'EOT'
return shopRuntimeSnapshot($pdo, $shopId, $now, $sinceOrderId, $balance, $delta['customers'], $delta['walkby'], $capped);
EOT;
$r = <<<'EOT'
$snapshot = shopRuntimeSnapshot($pdo, $shopId, $now, $sinceOrderId, $balance, $delta['customers'], $delta['walkby'], $capped);
    $snapshot['fees_charged'] = round($feesCharged, 2);   // phí tháng vừa thu trong lần tick này
    $snapshot['fees_overdue'] = $feesOverdue;             // true = ví không đủ đóng phí tháng, quán bị buộc đóng cửa
    return $snapshot;
EOT;
$e[] = [$f, $r];

$files['lib/shop_simulation.php'] = ['marker' => 'shop_billing.php', 'edits' => $e];

// ══════════════════════════ public/api/close-shop.php ══════════════════════════
// Đóng quán vĩnh viễn: két chưa chốt phải được trả vào ví trước khi xóa shop_runtime
$e = [];
$e[] = [
    "\$stmt = \$pdo->prepare('SELECT is_open FROM shop_runtime WHERE shop_id = ? FOR UPDATE');",
    "\$stmt = \$pdo->prepare('SELECT is_open, till_balance FROM shop_runtime WHERE shop_id = ? FOR UPDATE');",
];
$f = <<<'EOT'
$isOpen = $stmt->fetchColumn();
EOT;
$r = <<<'EOT'
$rtRow  = $stmt->fetch();
    $isOpen = $rtRow ? $rtRow['is_open'] : false;
    $till   = $rtRow ? round((float)$rtRow['till_balance'], 2) : 0.0; // doanh thu chưa chốt ca
EOT;
$e[] = [$f, $r];

$f = <<<'EOT'
    // Bước 3: thanh lý kho
EOT;
$r = <<<'EOT'
    // Két quán chưa chốt ca: trả hết vào ví trước (rồi mới thanh lý kho), không để mất tiền khi xóa quán
    if ($till > 0) {
        if (!creditWallet($pdo, $ownerId, $till)) {
            throw new ApiException("Không thể cộng doanh thu trong két vào ví", 500);
        }
        $balance = round($balance + $till, 2);
        try {
            $pdo->prepare('INSERT INTO transactions (user_id, shop_id, type, category, amount, balance_after, description)
                           VALUES (?, ?, \'income\', \'sale\', ?, ?, ?)')
                ->execute([$ownerId, $shopId, $till, $balance, "Chốt két khi đóng cửa \"" . $shop['name'] . "\""]);
        } catch (Exception $e) {
            error_log('close-shop: không ghi được bảng transactions: ' . $e->getMessage());
        }
    }

    // Bước 3: thanh lý kho
EOT;
$e[] = [$f, $r];

$e[] = ['"refund"            => $refund,', "\"refund\"            => \$refund,\n        \"till_paid_out\"     => \$till,"];
$files['public/api/close-shop.php'] = ['marker' => 'till_balance', 'edits' => $e];

// ══════════════════════════ public/shop-counter.html ══════════════════════════
$e = [];
$e[] = [
    '<span class="pill">💰 <b id="balance">…</b></span>',
    '<span class="pill" title="Doanh thu chưa chuyển vào ví. Bấm Chốt ca để nhận.">🧾 Két: <b id="till">…</b></span>' . "\n"
    . '    <span class="pill">💰 <b id="balance">…</b></span>',
];
$e[] = [
    '<button class="btn ok" id="toggle" type="button" disabled>🔓 Mở cửa quán</button>',
    '<button class="btn ok" id="toggle" type="button" disabled>🔓 Mở cửa quán</button>' . "\n"
    . '    <button class="btn" id="settle" type="button" disabled>💵 Chốt ca</button>',
];
$f = <<<'EOT'
$('balance').textContent = vnd(s.balance);
EOT;
$r = <<<'EOT'
$('balance').textContent = vnd(s.balance);
    $('till').textContent = vnd(s.till_balance || 0);
    $('settle').disabled = false;
    if (s.fees_overdue) notice('⚠️ Ví không đủ tiền đóng phí tháng (mặt bằng + điện nước) nên quán bị đóng cửa. Bấm "Chốt ca" để chuyển doanh thu vào ví rồi mở lại.');
    else if (s.fees_charged > 0) toast('🧾 Đã trừ phí tháng ' + vnd(s.fees_charged) + ' (mặt bằng + điện nước)');
EOT;
$e[] = [$f, $r];
$f = <<<'EOT'
async function init() {
EOT;
$r = <<<'EOT'
$('settle').addEventListener('click', async () => {
    const b = $('settle'); b.disabled = true;
    try {
      const r = await api('end-day.php', { shop_id: SHOP_ID });
      $('balance').textContent = vnd(r.balance);
      $('till').textContent = vnd(0);
      toast(r.transferred > 0
        ? '💵 Chốt ca: +' + vnd(r.transferred) + ' vào ví · Lãi ca ' + vnd(r.profit)
        : 'Chưa có doanh thu mới để chốt.');
    } catch (e) { toast('❌ ' + e.message); }
    b.disabled = false;
  });

  async function init() {
EOT;
$e[] = [$f, $r];
$files['public/shop-counter.html'] = ['marker' => 'id="settle"', 'edits' => $e];

// ══════════════════════════ public/js/wallet-api.js (nút Chốt ca ở startup-street-ui.html) ══════════════════════════
$f = <<<'EOT'
  const originalEndDay = window.endDayAndSettle;
  window.endDayAndSettle = function () {
    originalEndDay();
    if (SHOP_ID) syncBalance(); // demo: không có số dư server để đồng bộ
  };
EOT;
$r = <<<'EOT'
  // Chế độ thật: server chuyển doanh thu trong két vào ví (api/end-day.php) rồi trả số liệu ca để vẽ báo cáo.
  // Phí mặt bằng/điện nước nay thu theo THÁNG ở server nên báo cáo ngày không còn phí cố định.
  const originalEndDay = window.endDayAndSettle;
  let settling = false;
  window.endDayAndSettle = async function () {
    if (!SHOP_ID) { originalEndDay(); return; } // demo: giữ cách cũ
    if (settling) return;
    settling = true;
    try {
      const r = (await api('end-day.php', post({ shop_id: SHOP_ID }))).data;
      DailyFixedCosts.rent = DailyFixedCosts.staff = DailyFixedCosts.utilities = 0;
      GameState.today.orders       = r.orders_served;
      GameState.today.missedOrders = r.orders_missed;
      GameState.today.revenue      = r.revenue;
      GameState.today.materialCost = r.cogs; // giá vốn hàng đã bán (tiền nhập hàng đã trừ ví lúc nhập)
      originalEndDay();                      // vẽ báo cáo / biểu đồ bằng số của server
      walletBalance = r.balance;
      GameState.balance = r.balance;
      paintBalance();
      if (r.transferred > 0) showToast('💵', 'Đã chuyển ' + formatMoney(r.transferred) + ' vào ví');
    } catch (e) {
      showToast('❌', e.message);
    } finally {
      settling = false;
    }
  };
EOT;
$files['public/js/wallet-api.js'] = ['marker' => "api('end-day.php'", 'edits' => [[$f, $r]]];

// ══════════════════════════ public/js/game.js ══════════════════════════
// Không ghi dòng "Chi phí cố định" 0đ vào sổ cái cục bộ khi phí cố định theo ngày đã bị tắt
$f = <<<'EOT'
  // 2. Ghi nhận giao dịch chi phí cố định vào sổ cái
  GameState.transactions.push({
EOT;
$r = <<<'EOT'
  // 2. Ghi nhận giao dịch chi phí cố định vào sổ cái (bỏ qua khi = 0: chế độ thật thu phí theo THÁNG ở server)
  if (todayFixed > 0) GameState.transactions.push({
EOT;
$files['public/js/game.js'] = ['marker' => 'if (todayFixed > 0)', 'edits' => [[$f, $r]]];

// ══════════════════════════ public/js/shop-live.js ══════════════════════════
$f = <<<'EOT'
${s.today.missed} bỏ về`;
EOT;
$r = <<<'EOT'
${s.today.missed} bỏ về`
        + ` · Két ${formatMoney(s.till_balance || 0)} (bấm Chốt ca để chuyển vào ví)`;
      if (s.fees_overdue) showToast('⚠️', 'Ví không đủ tiền đóng phí tháng nên quán bị đóng cửa. Hãy chốt ca để lấy doanh thu.');
EOT;
$files['public/js/shop-live.js'] = ['marker' => 'till_balance', 'edits' => [[$f, $r]]];

// ══════════════════════════ public/startup-street-ui.html ══════════════════════════
$files['public/startup-street-ui.html'] = [
    'marker' => 'trong két quán',
    'edits'  => [[
        'Chi phí cố định (mặt bằng, lương, điện nước) mới chỉ nằm trong báo cáo, chưa trừ vào ví.',
        'Nút Chốt ca chuyển doanh thu trong két quán vào ví thật. Tiền mặt bằng và điện nước do server trừ theo tháng (có ghi sổ giao dịch), nên báo cáo ngày không còn phí cố định.',
    ]],
];

// ══════════════════════════ tools/run_migration.php ══════════════════════════
$files['tools/run_migration.php'] = [
    'marker' => '06_settlement',
    'edits'  => [["'05_custom_products.sql',", "'05_custom_products.sql',\n    '06_settlement.sql',"]],
];

// ── Kiểm tra TẤT CẢ trước, rồi mới ghi ──
$plan = []; $errors = []; $skipped = [];
foreach ($files as $rel => $spec) {
    $path = "$root/$rel";
    if (!is_file($path)) { $errors[] = "  - Không tìm thấy $rel"; continue; }
    $raw  = file_get_contents($path);
    $crlf = strpos($raw, "\r\n") !== false;
    $s    = str_replace("\r\n", "\n", $raw);

    if (strpos($s, $spec['marker']) !== false) { $skipped[] = $rel; continue; }

    foreach ($spec['edits'] as $i => [$find, $replace]) {
        $n = substr_count($s, $find);
        if ($n !== 1) { $errors[] = "  - $rel [chỉnh sửa #" . ($i + 1) . "] tìm thấy $n chỗ khớp (cần đúng 1)"; continue; }
        $s = substr_replace($s, $replace, strpos($s, $find), strlen($find));
    }
    $plan[$rel] = [$path, $crlf ? str_replace("\n", "\r\n", $s) : $s];
}

if ($errors) {
    fwrite(STDERR, "KHÔNG vá được vì file hiện tại khác với bản đã kiểm tra:\n" . implode("\n", $errors)
        . "\nKhông có file nào bị thay đổi. Gửi lại file bị báo lỗi để được vá thủ công.\n");
    exit(1);
}
foreach ($plan as $rel => [$path, $out]) {
    copy($path, $path . '.bak');
    file_put_contents($path, $out);
    echo "Đã vá $rel (bản gốc: $rel.bak)\n";
}
foreach ($skipped as $rel) echo "Bỏ qua $rel (đã vá trước đó)\n";
if (!$plan && !$skipped) echo "Không có gì để làm.\n";
