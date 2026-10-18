<?php
// Bỏ số dư lưu ở localStorage trong map.php: số dư thật nằm ở MySQL (users.balance).
// Chạy 1 lần từ thư mục gốc dự án (SAU tools/apply-map-shop-integration.php):
//     php tools/apply-map-balance-integration.php
// Giống script trước: chỉ sửa đúng các dòng cần thiết, có map.php.bak, và nếu có chỗ không khớp thì KHÔNG ghi gì.
//
// Sau khi vá:
//  - Không còn biến tiền nào tự cộng/trừ ở trình duyệt. `balance` chỉ là bản sao hiển thị, lấy từ api/get-balance.php.
//  - "Thuê mặt bằng ngay" gọi api/rent-property.php (server trừ users.balance, ghi rentals) rồi mới gọi create-shop.php.
//  - Nếu thuê xong mà tạo shop lỗi, người chơi bấm lại: rent-property.php nhận ra đã thuê và KHÔNG trừ tiền lần 2.

$file = $argv[1] ?? dirname(__DIR__) . '/public/map.php';
if (!is_file($file)) { fwrite(STDERR, "Không tìm thấy $file\n"); exit(1); }

$raw  = file_get_contents($file);
$crlf = strpos($raw, "\r\n") !== false;
$s    = str_replace("\r\n", "\n", $raw);

if (strpos($s, 'rent-property.php') !== false) { echo "map.php đã được vá trước đó, không làm gì thêm.\n"; exit(0); }

$edits = [];

// 1) Bỏ số dư mặc định 50.000.000 lưu trong localStorage
$f = <<<'EOT'
let balance=Number(localStorage.getItem('startupStreetBalance')||50000000);
EOT;
$r = <<<'EOT'
let balance=null; /* chỉ là bản sao để hiển thị; số dư thật = users.balance trong MySQL */
localStorage.removeItem('startupStreetBalance'); /* xóa số dư cũ còn sót ở trình duyệt */
EOT;
$edits['bỏ localStorage balance'] = [$f, $r];

// 2) updateMoney không bịa số khi chưa có dữ liệu + hàm lấy số dư từ server
$f = <<<'EOT'
function updateMoney(){moneyEl.textContent=fmt(balance)}
EOT;
$r = <<<'EOT'
function updateMoney(){moneyEl.textContent=balance===null?'…':fmt(balance)}
async function refreshBalance(){
 try{balance=Number((await apiJson('get-balance.php?user_id='+USER_ID)).data.balance);updateMoney()}
 catch(e){/* giữ nguyên số đang hiển thị, không tự đoán số dư */}}
EOT;
$edits['updateMoney + refreshBalance'] = [$f, $r];

// 3) Kiểm tra vốn ở trình duyệt chỉ còn là gợi ý UX (server mới là nơi quyết định)
$f = <<<'EOT'
if(balance<l.price){$('msg').textContent='Bạn không đủ vốn để thuê mặt bằng này.';return}
EOT;
$r = <<<'EOT'
if(balance!==null&&balance<l.price){$('msg').textContent='Bạn không đủ vốn để thuê mặt bằng này.';return}
EOT;
$edits['gợi ý đủ vốn'] = [$f, $r];

// 4) Thuê mặt bằng ở server trước, thành công mới tạo cửa hàng
$f = <<<'EOT'
let shop;
EOT;
$r = <<<'EOT'
let shop,rent;
 try{
  rent=(await apiJson('rent-property.php',{method:'POST',headers:{'Content-Type':'application/json'},
   body:JSON.stringify({user_id:USER_ID,property_id:l.id})})).data;
  balance=Number(rent.balance);updateMoney();
 }catch(err){$('msg').textContent='Không thể thuê mặt bằng: '+err.message;buyBtn.disabled=false;refreshBalance();return}
EOT;
$edits['gọi rent-property'] = [$f, $r];

// 5) Thông báo rõ khi đã trả tiền thuê nhưng chưa tạo được cửa hàng
$f = <<<'EOT'
$('msg').textContent='Không thể tạo cửa hàng: '+err.message;buyBtn.disabled=false;return}
EOT;
$r = <<<'EOT'
$('msg').textContent='Đã thuê mặt bằng nhưng chưa tạo được cửa hàng: '+err.message+'. Bấm lại để thử tiếp (không bị trừ tiền thuê lần 2).';buyBtn.disabled=false;return}
EOT;
$edits['báo lỗi tạo shop'] = [$f, $r];

// 6) Bỏ dòng tự trừ tiền ở trình duyệt
$f = <<<'EOT'
balance-=l.price;localStorage.setItem('startupStreetBalance',String(balance));
EOT;
$r = <<<'EOT'
/* tiền thuê đã được server trừ trong rent-property.php */
EOT;
$edits['bỏ tự trừ tiền'] = [$f, $r];

// 7) Lấy số dư thật khi mở bản đồ và mỗi khi quay lại tab (vd. sau khi nhập hàng ở trang quản lý kho)
$f = <<<'EOT'
updateMoney();updateFacadePreview();
EOT;
$r = <<<'EOT'
updateMoney();refreshBalance();window.addEventListener('focus',refreshBalance);updateFacadePreview();
EOT;
$edits['đồng bộ số dư khi mở'] = [$f, $r];

// ── Kiểm tra: mỗi điểm neo phải xuất hiện đúng 1 lần ──
$errors = [];
foreach ($edits as $name => [$find, $_]) {
    $n = substr_count($s, $find);
    if ($n !== 1) $errors[] = "  - [$name] tìm thấy $n chỗ khớp (cần đúng 1)";
}
if ($errors) {
    fwrite(STDERR, "KHÔNG vá được vì map.php khác với bản đã kiểm tra:\n" . implode("\n", $errors) . "\nKhông có gì bị thay đổi. Hãy chắc chắn đã chạy tools/apply-map-shop-integration.php trước.\n");
    exit(1);
}

foreach ($edits as [$find, $replace]) {
    $pos = strpos($s, $find);
    $s = substr_replace($s, $replace, $pos, strlen($find));
}

copy($file, $file . '.bak');
file_put_contents($file, $crlf ? str_replace("\n", "\r\n", $s) : $s);
echo "Đã vá " . count($edits) . " chỗ trong $file (bản gốc lưu ở $file.bak)\n";
