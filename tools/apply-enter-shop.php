<?php
// Thêm nút "Vào cửa hàng" (dưới "Quản lý kho hàng") trong public/map.php
// và bỏ cảnh quầy khỏi public/startup-street-ui.html (trang Vận hành quán).
// Chạy 1 lần từ thư mục gốc dự án:   php tools/apply-enter-shop.php
// Có tạo file .bak; nếu BẤT KỲ chỗ nào không khớp thì KHÔNG ghi gì cả.
// Cần có sẵn public/shop-counter.html (trang mới).

$root = $argv[1] ?? dirname(__DIR__);

if (!is_file($root . '/public/shop-counter.html')) {
    fwrite(STDERR, "Thiếu public/shop-counter.html. Hãy lưu file đó vào thư mục public/ trước rồi chạy lại.\n");
    exit(1);
}

$files = [
    'public/map.php' => [
        'marker' => 'id="enter"',
        'edits' => [
            [
                '<a class="btn" id="manage" hidden style="display:block;text-align:center;text-decoration:none;margin-top:10px">Quản lý kho hàng</a>',
                '<a class="btn" id="manage" hidden style="display:block;text-align:center;text-decoration:none;margin-top:10px">Quản lý kho hàng</a>' . "\n"
                . '<a class="btn" id="enter" hidden style="display:block;text-align:center;text-decoration:none;margin-top:10px">🏪 Vào cửa hàng</a>',
            ],
            [
                "if(sid)mg.href='business-management.html?shop_id='+sid;",
                "if(sid)mg.href='business-management.html?shop_id='+sid;\n"
                . " const en=\$('enter');en.hidden=!(l.status==='mine'&&sid);if(sid)en.href='shop-counter.html?shop_id='+sid;",
            ],
        ],
    ],
    'public/startup-street-ui.html' => [
        'marker' => null, // idempotent: nếu dòng đã bị xóa thì bỏ qua
        'edits' => [
            [
                '        <div id="shop-scene" role="img" aria-label="Cảnh quầy hàng: khách đến mua và rời đi"></div>' . "\n",
                '',
            ],
        ],
    ],
];

$plan = []; $errors = []; $skipped = [];
foreach ($files as $rel => $spec) {
    $path = "$root/$rel";
    if (!is_file($path)) { $errors[] = "  - Không tìm thấy $rel"; continue; }
    $raw  = file_get_contents($path);
    $crlf = strpos($raw, "\r\n") !== false;
    $s    = str_replace("\r\n", "\n", $raw);

    if ($spec['marker'] && strpos($s, $spec['marker']) !== false) { $skipped[] = $rel; continue; }

    foreach ($spec['edits'] as $i => [$find, $replace]) {
        $n = substr_count($s, $find);
        if ($n === 0 && $spec['marker'] === null) { $skipped[] = "$rel (đã bỏ cảnh quầy)"; continue; }
        if ($n !== 1) { $errors[] = "  - $rel [chỉnh sửa #" . ($i + 1) . "] tìm thấy $n chỗ khớp (cần đúng 1)"; continue; }
        $s = substr_replace($s, $replace, strpos($s, $find), strlen($find));
    }
    $plan[$rel] = [$path, $crlf ? str_replace("\n", "\r\n", $s) : $s];
}

if ($errors) {
    fwrite(STDERR, "KHÔNG vá được vì file hiện tại khác với bản đã kiểm tra:\n" . implode("\n", $errors) . "\nKhông có file nào bị thay đổi.\n");
    exit(1);
}
foreach ($plan as $rel => [$path, $out]) {
    copy($path, $path . '.bak');
    file_put_contents($path, $out);
    echo "Đã vá $rel (bản gốc: $rel.bak)\n";
}
foreach ($skipped as $rel) echo "Bỏ qua $rel\n";
