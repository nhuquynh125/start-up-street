<?php
// Nối map.php với API cửa hàng (Thành viên 3). Chạy 1 lần từ thư mục gốc dự án:
//     php tools/apply-map-shop-integration.php
// Script chỉ sửa đúng các dòng cần thiết, KHÔNG ghi đè cả file (map.php còn là việc của TV2).
// Nếu có chỗ không khớp (file đã bị sửa) thì báo rõ chỗ nào và KHÔNG ghi gì. Có tạo sẵn map.php.bak.

$file = $argv[1] ?? dirname(__DIR__) . '/public/map.php';
if (!is_file($file)) { fwrite(STDERR, "Không tìm thấy $file\n"); exit(1); }

$raw  = file_get_contents($file);
$crlf = strpos($raw, "\r\n") !== false;           // Windows dùng CRLF -> chuẩn hóa để so khớp
$s    = str_replace("\r\n", "\n", $raw);

if (strpos($s, 'apiJson') !== false) { echo "map.php đã được vá trước đó, không làm gì thêm.\n"; exit(0); }

$edits = [];

// 1) Hằng số + hàm gọi API
$edits['hằng số API'] = [
"let selectedBusinessType=null;",
"let selectedBusinessType=null;
const USER_ID=1; /* TODO: thay bằng user đang đăng nhập (TV1) */
const API='api/';
const apiJson=async(path,opt)=>{
 let r;try{r=await fetch(API+path,opt)}catch(e){throw new Error('Không kết nối được server')}
 const j=await r.json().catch(()=>null);
 if(!j)throw new Error('Server không trả về JSON (HTTP '+r.status+')');
 if(!j.success)throw new Error(j.message||'Lỗi API');
 return j};"];

// 2) Mỗi mặt bằng có id = thứ tự (1..48) để làm property_id trong database
$edits['id mặt bằng'] = [
"lots.push({code:r.letter+(c+1),x,y:r.y,w:170,h:r.h,face:r.face,traffic,area,price,status});",
"lots.push({id:lots.length+1,code:r.letter+(c+1),x,y:r.y,w:170,h:r.h,face:r.face,traffic,area,price,status});"];

// 3) Nút "Thuê mặt bằng ngay" gọi API create-shop trước, thành công mới trừ tiền
$edits['nút thuê (1)'] = [
"\$('buy').addEventListener('click',()=>{",
"\$('buy').addEventListener('click',async()=>{"];
$edits['nút thuê (2)'] = [
" selectedBusinessType=businessTypes.find(t=>String(t.id)===String(typeId));",
" selectedBusinessType=businessTypes.find(t=>String(t.id)===String(typeId));
 const idx=open,buyBtn=\$('buy');buyBtn.disabled=true;\$('msg').textContent='Đang tạo cửa hàng...';
 let shop;
 try{
  shop=(await apiJson('create-shop.php',{method:'POST',headers:{'Content-Type':'application/json'},
   body:JSON.stringify({owner_user_id:USER_ID,property_id:l.id,business_type_id:selectedBusinessType.id,name:selectedBusinessType.name})})).data;
 }catch(err){\$('msg').textContent='Không thể tạo cửa hàng: '+err.message;buyBtn.disabled=false;return}
 buyBtn.disabled=false;"];
$edits['nút thuê (3)'] = [
" l.status='mine';els[open].setAttribute('class','lot mine'+(open===near?' near':''));updateMoney();",
" l.status='mine';els[idx].setAttribute('class','lot mine'+(idx===near?' near':''));updateMoney();"];
$edits['nút thuê (4)'] = [
"decor:'plants',rented:true};",
"decor:'plants',rented:true,shopId:shop.shop_id};"];
$edits['nút thuê (5)'] = [
" saveDesigns(designs);refreshSign(open,designs);",
" saveDesigns(designs);refreshSign(idx,designs);"];

// 4) Nút "Quản lý kho hàng" trong panel (chỉ hiện với mặt bằng của mình đã có shopId)
$edits['html nút quản lý'] = [
"<button class=\"btn big\" id=\"buy\" type=\"button\">Thuê mặt bằng ngay</button>",
"<button class=\"btn big\" id=\"buy\" type=\"button\">Thuê mặt bằng ngay</button>
<a class=\"btn\" id=\"manage\" hidden style=\"display:block;text-align:center;text-decoration:none;margin-top:10px\">Quản lý kho hàng</a>"];
$edits['hiện nút quản lý'] = [
"buy.textContent=l.status==='available'?'Thuê mặt bằng ngay':l.status==='mine'?'Tùy chỉnh cửa hàng':labels[l.status];",
"buy.textContent=l.status==='available'?'Thuê mặt bằng ngay':l.status==='mine'?'Tùy chỉnh cửa hàng':labels[l.status];
 const mg=\$('manage'),sid=(saved[l.code]||{}).shopId;mg.hidden=!(l.status==='mine'&&sid);
 if(sid)mg.href='business-management.html?user_id='+USER_ID+'&shop_id='+sid;"];

// 5) Đường dẫn tương đối (chạy được cả khi mở bằng PhpStorm preview)
$edits['url business-types'] = [
"fetch('/api/business-types.php')",
"fetch('api/business-types.php')"];

// 6) Khi mở bản đồ: đồng bộ mặt bằng đã có cửa hàng từ MySQL (của mình -> màu vàng, của người khác -> đã có chủ)
$edits['đồng bộ từ MySQL'] = [
"updateMoney();updateFacadePreview();",
"updateMoney();updateFacadePreview();
apiJson('get-shops.php').then(res=>{
 const designs=loadDesigns();let changed=false;
 res.data.shops.forEach(s=>{
  const i=Number(s.property_id)-1,l=lots[i];if(!l)return;
  if(Number(s.owner_user_id)===USER_ID){
   l.status='mine';
   const d=designs[l.code]||(designs[l.code]={lotCode:l.code,propertyPrice:l.price,facadeColor:styleOf(Number(s.business_type_id)).body,awningColor:'#f4c95d',decor:'plants'});
   Object.assign(d,{rented:true,shopId:Number(s.shop_id),businessTypeId:Number(s.business_type_id),businessTypeName:s.business_type_name});
   if(!d.shopName)d.shopName=s.name;
   changed=true;
  }else l.status='owned';
  els[i].setAttribute('class','lot '+l.status+(i===near?' near':''));
  refreshSign(i,designs);
 });
 if(changed)saveDesigns(designs);
}).catch(()=>{});"];

// ── Kiểm tra: mỗi điểm neo phải xuất hiện đúng 1 lần ──
$errors = [];
foreach ($edits as $name => [$find, $_]) {
    $n = substr_count($s, $find);
    if ($n !== 1) $errors[] = "  - [$name] tìm thấy $n chỗ khớp (cần đúng 1)";
}
if ($errors) {
    fwrite(STDERR, "KHÔNG vá được vì map.php khác với bản đã kiểm tra:\n" . implode("\n", $errors) . "\nKhông có gì bị thay đổi. Gửi lại file map.php hiện tại để được vá thủ công.\n");
    exit(1);
}

foreach ($edits as [$find, $replace]) {
    $pos = strpos($s, $find);
    $s = substr_replace($s, $replace, $pos, strlen($find));
}

copy($file, $file . '.bak');
file_put_contents($file, $crlf ? str_replace("\n", "\r\n", $s) : $s);
echo "Đã vá " . count($edits) . " chỗ trong $file (bản gốc lưu ở $file.bak)\n";
