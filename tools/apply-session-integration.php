<?php
// Bỏ USER_ID cố định / user_id / owner_user_id gửi từ trình duyệt: server tự biết người chơi qua $_SESSION['user_id'].
// Chạy 1 lần từ thư mục gốc dự án (SAU tools/apply-map-shop-integration.php và tools/apply-map-balance-integration.php):
//     php tools/apply-session-integration.php
// Sửa 3 file: public/map.php, public/business-management.html, public/js/wallet-api.js
// Giống các script trước: chỉ sửa đúng các dòng cần thiết, tạo file .bak, và nếu BẤT KỲ chỗ nào không khớp
// thì KHÔNG ghi gì cả (không để lại tình trạng vá dở 1-2 file).
//
// Sau khi vá:
//  - Frontend không còn biết/gửi user id. Server lấy từ session (getCurrentUserId() trong lib/shop_helpers.php).
//  - map.php: get-shops.php chỉ trả cửa hàng của mình -> lot của người khác được tô "Đã có chủ" nhờ api/get-occupied-properties.php.
//  - HTTP 401 hiện thông báo "Bạn chưa đăng nhập".

$root = $argv[1] ?? dirname(__DIR__);

/** Thêm 1 chỉnh sửa: $count = số lần $find phải xuất hiện đúng (mặc định 1). $regex = true thì $find là biểu thức chính quy. */
function add(array &$list, $name, $find, $replace, $count = 1, $regex = false)
{
    $list[] = compact('name', 'find', 'replace', 'count', 'regex');
}

$files = [];

// ══════════════════════════ public/map.php ══════════════════════════
$map = [];

$f = <<<'EOT'
const USER_ID=1; /* TODO: thay bằng user đang đăng nhập (TV1) */
EOT;
$r = <<<'EOT'
/* Người chơi do server xác định qua session; trình duyệt không biết và không gửi user id. */
EOT;
add($map, 'bỏ USER_ID', $f, $r);

$f = <<<'EOT'
if(!j.success)throw new Error(j.message||'Lỗi API');
EOT;
$r = <<<'EOT'
if(!j.success){const e=new Error(r.status===401?'Bạn chưa đăng nhập':(j.message||'Lỗi API'));e.status=r.status;throw e}
EOT;
add($map, 'báo 401', $f, $r);

$f = <<<'EOT'
if(sid)mg.href='business-management.html?user_id='+USER_ID+'&shop_id='+sid;
EOT;
$r = <<<'EOT'
if(sid)mg.href='business-management.html?shop_id='+sid;
EOT;
add($map, 'link quản lý kho', $f, $r);

$f = <<<'EOT'
apiJson('get-balance.php?user_id='+USER_ID)
EOT;
$r = <<<'EOT'
apiJson('get-balance.php')
EOT;
add($map, 'get-balance', $f, $r);

$f = <<<'EOT'
JSON.stringify({user_id:USER_ID,property_id:l.id})
EOT;
$r = <<<'EOT'
JSON.stringify({property_id:l.id})
EOT;
add($map, 'rent-property', $f, $r);

$f = <<<'EOT'
JSON.stringify({owner_user_id:USER_ID,property_id:l.id,
EOT;
$r = <<<'EOT'
JSON.stringify({property_id:l.id,
EOT;
add($map, 'create-shop', $f, $r);

// get-shops.php giờ chỉ trả cửa hàng của người đang đăng nhập => mọi shop trả về đều là "của mình"
$f = <<<'EOT'
if(Number(s.owner_user_id)===USER_ID){
EOT;
$r = <<<'EOT'
{ /* get-shops.php chỉ trả cửa hàng của người đang đăng nhập */
EOT;
add($map, 'get-shops: shop nào cũng là của mình (1)', $f, $r);

$f = <<<'EOT'
}else l.status='owned';
EOT;
$r = <<<'EOT'
}
EOT;
add($map, 'get-shops: shop nào cũng là của mình (2)', $f, $r);

// Lot của người khác: lấy từ get-occupied-properties.php (không lộ chủ sở hữu)
$f = <<<'EOT'
playerEl.setAttribute('transform',`translate(${player.x} ${player.y})`);
EOT;
$r = <<<'EOT'
apiJson('get-occupied-properties.php').then(res=>{
 res.data.properties.forEach(p=>{
  if(p.is_mine)return; /* lot của mình do get-shops.php xử lý ở trên */
  const i=Number(p.property_id)-1,l=lots[i];if(!l)return;
  l.status='owned';
  els[i].setAttribute('class','lot owned'+(i===near?' near':''));
  refreshSign(i);
 });
}).catch(()=>{});
playerEl.setAttribute('transform',`translate(${player.x} ${player.y})`);
EOT;
add($map, 'tô lot đã có chủ', $f, $r);

$files['public/map.php'] = ['marker' => 'get-occupied-properties.php', 'edits' => $map];

// ══════════════════════════ public/business-management.html ══════════════════════════
$bm = [];

$f = <<<'EOT'
// Chưa có đăng nhập (TV1) nên lấy user từ URL: business-management.html?user_id=1
EOT;
$r = <<<'EOT'
// Người chơi do server xác định qua session ($_SESSION['user_id']); trang này không gửi user id.
EOT;
add($bm, 'comment cấu hình', $f, $r);

add($bm, 'bỏ CURRENT_USER_ID', '/^[ \t]*const CURRENT_USER_ID = Number\(params\.get\(\'user_id\'\)\) \|\| 1;\r?\n/m', '', 1, true);

$f = <<<'EOT'
callApi(`get-shops.php?owner_user_id=${encodeURIComponent(CURRENT_USER_ID)}`)
EOT;
$r = <<<'EOT'
callApi('get-shops.php')
EOT;
add($bm, 'get-shops', $f, $r);

$f = <<<'EOT'
callApi(`get-shop-products.php?shop_id=${encodeURIComponent(shopId)}&owner_user_id=${encodeURIComponent(CURRENT_USER_ID)}`)
EOT;
$r = <<<'EOT'
callApi(`get-shop-products.php?shop_id=${encodeURIComponent(shopId)}`)
EOT;
add($bm, 'get-shop-products', $f, $r);

// create-shop và purchase-inventory: mỗi nơi 1 dòng "owner_user_id: CURRENT_USER_ID," trong body
add($bm, 'bỏ owner_user_id trong body (2 chỗ)', '/^[ \t]*owner_user_id: CURRENT_USER_ID,\r?\n/m', '', 2, true);

$f = <<<'EOT'
const err = new Error(json.message || `API ${path} báo lỗi (HTTP ${res.status})`);
EOT;
$r = <<<'EOT'
const err = new Error(res.status === 401 ? 'Bạn chưa đăng nhập' : (json.message || `API ${path} báo lỗi (HTTP ${res.status})`));
EOT;
add($bm, 'báo 401', $f, $r);

$files['public/business-management.html'] = ['marker' => 'Người chơi do server xác định qua session', 'edits' => $bm];

// ══════════════════════════ public/js/wallet-api.js ══════════════════════════
$w = [];

$f = <<<'EOT'
const USER_ID = Number(q.get('user_id')) || 1;
EOT;
$r = <<<'EOT'
// Người chơi do server xác định qua session ($_SESSION['user_id']); không gửi user id từ trình duyệt.
EOT;
add($w, 'bỏ USER_ID', $f, $r);

$f = <<<'EOT'
const err = new Error(json.message || 'Lỗi API');
EOT;
$r = <<<'EOT'
const err = new Error(res.status === 401 ? 'Bạn chưa đăng nhập' : (json.message || 'Lỗi API'));
EOT;
add($w, 'báo 401', $f, $r);

$f = <<<'EOT'
'get-balance.php?user_id=' + USER_ID
EOT;
$r = <<<'EOT'
'get-balance.php'
EOT;
add($w, 'get-balance', $f, $r);

$f = <<<'EOT'
'get-shop-products.php?shop_id=' + SHOP_ID + '&owner_user_id=' + USER_ID
EOT;
$r = <<<'EOT'
'get-shop-products.php?shop_id=' + SHOP_ID
EOT;
add($w, 'get-shop-products', $f, $r);

$f = <<<'EOT'
owner_user_id: USER_ID, shop_id: SHOP_ID, product_id: product.dbId
EOT;
$r = <<<'EOT'
shop_id: SHOP_ID, product_id: product.dbId
EOT;
add($w, 'sell-product', $f, $r);

$f = <<<'EOT'
owner_user_id: USER_ID, shop_id: SHOP_ID, product_id: p.dbId
EOT;
$r = <<<'EOT'
shop_id: SHOP_ID, product_id: p.dbId
EOT;
add($w, 'purchase-inventory', $f, $r);

$f = <<<'EOT'
index.html?user_id=1&shop_id=<id cửa hàng>
EOT;
$r = <<<'EOT'
index.html?shop_id=<id cửa hàng>
EOT;
add($w, 'gợi ý URL', $f, $r);

$files['public/js/wallet-api.js'] = ['marker' => 'Người chơi do server xác định qua session', 'edits' => $w];

// ══════════════════════════ Kiểm tra TẤT CẢ trước, rồi mới ghi ══════════════════════════
$plan   = [];   // path => nội dung mới
$errors = [];
$skipped = [];

foreach ($files as $rel => $spec) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { $errors[] = "  - Không tìm thấy $rel"; continue; }

    $raw  = file_get_contents($path);
    $crlf = strpos($raw, "\r\n") !== false;
    $s    = str_replace("\r\n", "\n", $raw);

    if (strpos($s, $spec['marker']) !== false) { $skipped[] = $rel; continue; }

    foreach ($spec['edits'] as $e) {
        if ($e['regex']) {
            $n = preg_match_all($e['find'], $s);
        } else {
            $n = substr_count($s, $e['find']);
        }
        if ($n !== $e['count']) {
            $errors[] = "  - $rel [{$e['name']}] tìm thấy $n chỗ khớp (cần đúng {$e['count']})";
            continue;
        }
        if ($e['regex']) {
            $s = preg_replace($e['find'], $e['replace'], $s);
        } else {
            while (($pos = strpos($s, $e['find'])) !== false) {
                $s = substr_replace($s, $e['replace'], $pos, strlen($e['find']));
                break; // count luôn = 1 với chuỗi thường
            }
        }
    }
    $plan[$rel] = [$path, $crlf ? str_replace("\n", "\r\n", $s) : $s, $s];
}

if ($errors) {
    fwrite(STDERR, "KHÔNG vá được vì file hiện tại khác với bản đã kiểm tra:\n" . implode("\n", $errors)
        . "\nKhông có file nào bị thay đổi. Hãy chắc chắn đã chạy 2 script apply-map-* trước.\n");
    exit(1);
}

foreach ($plan as $rel => [$path, $out, $clean]) {
    copy($path, $path . '.bak');
    file_put_contents($path, $out);
    $left = preg_match_all('/\b(CURRENT_)?USER_ID\b/', $clean);
    echo "Đã vá $rel (bản gốc: $rel.bak)" . ($left ? "  ⚠ còn $left chỗ nhắc USER_ID, hãy tìm bằng Ctrl+Shift+F" : '') . "\n";
}
foreach ($skipped as $rel) echo "Bỏ qua $rel (đã vá trước đó)\n";
if (!$plan && !$skipped) echo "Không có gì để làm.\n";
