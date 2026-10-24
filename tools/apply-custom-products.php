<?php
// Thêm tính năng "Thêm mặt hàng mới" cho cửa hàng.
// Chạy 1 lần từ thư mục gốc dự án, SAU KHI đã chép 3 file mới vào đúng chỗ:
//     database/migrations/05_custom_products.sql
//     public/api/add-product.php
//     public/api/purchase-inventory.php   (bản sửa: ghi đè file cũ)
//   rồi:   php tools/apply-custom-products.php
//   rồi:   php tools/run_migration.php     (chạy migration 05 để có cột products.shop_id)
//
// Script vá: get-shop-products.php, restock-bulk.php, lib/shop_simulation.php, close-shop.php,
//            business-management.html, tools/run_migration.php
// Giống các script apply-* trước: chỉ sửa đúng các dòng cần thiết, tạo file .bak,
// và nếu BẤT KỲ chỗ nào không khớp thì KHÔNG ghi gì cả.

$root = $argv[1] ?? dirname(__DIR__);

foreach (['public/api/add-product.php', 'database/migrations/05_custom_products.sql'] as $need) {
    if (!is_file("$root/$need")) {
        fwrite(STDERR, "Thiếu $need. Hãy chép file đó vào dự án trước rồi chạy lại.\n");
        exit(1);
    }
}

$files = [];

// ── public/api/get-shop-products.php ──
$e = [];
$e[] = ['i.last_restocked', "i.last_restocked,\n            (p.shop_id IS NOT NULL) AS is_custom"];
$e[] = ['WHERE p.business_type_id = ?', 'WHERE p.business_type_id = ? AND (p.shop_id IS NULL OR p.shop_id = ?)'];
$e[] = ['$stmt->execute([$shopId, $shop[\'business_type_id\']]);', '$stmt->execute([$shopId, $shop[\'business_type_id\'], $shopId]);'];
$files['public/api/get-shop-products.php'] = ['marker' => 'is_custom', 'edits' => $e];

// ── public/api/restock-bulk.php ──
$e = [];
$e[] = [
    'FROM products WHERE business_type_id = ? AND id IN ($in)',
    'FROM products WHERE business_type_id = ? AND id IN ($in) AND (shop_id IS NULL OR shop_id = ?)',
];
$e[] = [
    '$stmt->execute(array_merge([(int)$shop[\'business_type_id\']], $ids));',
    '$stmt->execute(array_merge([(int)$shop[\'business_type_id\']], $ids, [$shopId]));',
];
$files['public/api/restock-bulk.php'] = ['marker' => 'shop_id IS NULL OR shop_id', 'edits' => $e];

// ── lib/shop_simulation.php (menu khách gọi món phải gồm cả món tự thêm) ──
$e = [];
$e[] = [
    'FROM products WHERE business_type_id = ?\');',
    'FROM products WHERE business_type_id = ? AND (shop_id IS NULL OR shop_id = ?)\');',
];
$e[] = [
    '$stmt->execute([(int)$shop[\'business_type_id\']]);',
    '$stmt->execute([(int)$shop[\'business_type_id\'], $shopId]);',
];
$files['lib/shop_simulation.php'] = ['marker' => 'shop_id IS NULL OR shop_id', 'edits' => $e];

// ── public/api/close-shop.php (thanh lý cả món tự thêm, rồi xóa chúng) ──
$e = [];
$e[] = [
    'FROM products WHERE business_type_id = ?\');',
    'FROM products WHERE business_type_id = ? AND (shop_id IS NULL OR shop_id = ?)\');',
];
$e[] = [
    '$stmt->execute([(int)$shop[\'business_type_id\']]);',
    '$stmt->execute([(int)$shop[\'business_type_id\'], $shopId]);',
];
$e[] = [
    '$pdo->prepare(\'DELETE FROM inventory WHERE shop_id = ?\')->execute([$shopId]);',
    '$pdo->prepare(\'DELETE FROM inventory WHERE shop_id = ?\')->execute([$shopId]);' . "\n"
    . '    $pdo->prepare(\'DELETE FROM products WHERE shop_id = ?\')->execute([$shopId]); // món do chủ quán tự thêm',
];
$files['public/api/close-shop.php'] = ['marker' => 'shop_id IS NULL OR shop_id', 'edits' => $e];

// ── tools/run_migration.php ──
$files['tools/run_migration.php'] = [
    'marker' => '05_custom_products',
    'edits'  => [["'04_shop_runtime.sql',", "'04_shop_runtime.sql',\n    '05_custom_products.sql',"]],
];

// ── public/business-management.html ──
$e = [];

$f = <<<'EOT'
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
EOT;
$r = <<<'EOT'
.dl .fl{display:block;font-weight:800;margin:8px 0}
.dl input[type=text]{width:100%;margin-top:4px;font-size:16px;font-weight:600;padding:7px 10px;border:3px solid var(--wood);border-radius:12px;background:#fff;color:var(--ink)}
.dl .fl input[type=number]{width:100%;margin-top:4px;text-align:left}
.cu{font-size:11px;font-weight:800;color:var(--coin)}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
EOT;
$e[] = [$f, $r];

$f = <<<'EOT'
<button class="btn ok sm" id="bulk" type="button">📦 Bổ sung đầy kho</button>
EOT;
$r = <<<'EOT'
<button class="btn ok sm" id="bulk" type="button">📦 Bổ sung đầy kho</button>
          <button class="btn gold sm" id="addp" type="button">➕ Thêm mặt hàng</button>
EOT;
$e[] = [$f, $r];

$f = <<<'EOT'
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
EOT;
$r = <<<'EOT'
<div class="ov" id="npd" hidden>
  <div class="panel dl" role="dialog" aria-modal="true" aria-labelledby="npt">
    <span class="tag" id="npt">Thêm mặt hàng mới</span>
    <label class="fl">Tên mặt hàng<input type="text" id="npname" maxlength="100" placeholder="Ví dụ: Trà vải"></label>
    <label class="fl">Giá vốn mỗi món (đ)<input type="number" id="npcost" min="1000" max="200000" step="500" value="10000"></label>
    <p class="note">Thêm món không tốn tiền. Món mới có tồn kho 0 cho tới khi bạn nhập hàng; giá bán mặc định = giá vốn × 1.5 và chỉnh được ở thẻ sản phẩm.</p>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
      <button class="btn no" id="npno" type="button">Hủy</button>
      <button class="btn ok" id="npyes" type="button">Thêm</button>
    </div>
  </div>
</div>
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
EOT;
$e[] = [$f, $r];

$f = <<<'EOT'
/* ── đóng cửa vĩnh viễn ── */
EOT;
$r = <<<'EOT'
/* ── thêm mặt hàng mới (món riêng của cửa hàng, do server kiểm tra giới hạn) ── */
$('addp').addEventListener('click',()=>{$('npname').value='';$('npcost').value=10000;$('npd').hidden=false;$('npname').focus()});
$('npno').addEventListener('click',()=>$('npd').hidden=true);
$('npyes').addEventListener('click',async()=>{
 const name=$('npname').value.trim(),cost=parseFloat($('npcost').value);
 if(!name)return toast('Hãy đặt tên cho mặt hàng.');
 if(!(cost>0))return toast('Giá vốn phải lớn hơn 0.');
 const b=$('npyes');b.disabled=true;
 try{
  await call('add-product.php',post({shop_id:cur,name,base_cost:cost}));
  toast('✅ Đã thêm "'+name+'". Hãy nhập hàng để bắt đầu bán.',1);
  $('npd').hidden=true;await loadShop(cur);
 }catch(e){toast('❌ '+e.message)}
 b.disabled=false});

/* ── đóng cửa vĩnh viễn ── */
EOT;
$e[] = [$f, $r];

$f = <<<'EOT'
document.addEventListener('keydown',e=>{if(e.key==='Escape')$('dlg').hidden=true});
EOT;
$r = <<<'EOT'
document.addEventListener('keydown',e=>{if(e.key==='Escape'){$('dlg').hidden=true;$('npd').hidden=true}});
EOT;
$e[] = [$f, $r];

$f = <<<'EOT'
<h4>${esc(p.product_name)}</h4>
EOT;
$r = <<<'EOT'
<h4>${esc(p.product_name)}${+p.is_custom?' <span class="cu">✨ riêng</span>':''}</h4>
EOT;
$e[] = [$f, $r];

$files['public/business-management.html'] = ['marker' => 'id="addp"', 'edits' => $e];

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
