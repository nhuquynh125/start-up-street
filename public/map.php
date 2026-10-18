<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Startup Street</title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;800&display=swap" rel="stylesheet">
<style>
:root{--free:#7fc97f;--owned:#c9c9c9;--mine:#f2b84b;--sel:#2b6cb0;
 --wood-d:#4a2a16;--wood:#8a5a3b;--wood-l:#b9835a;--paper:#f7e8c8;--paper-d:#e6cb98;--cream:#fff6dd;--sky:#bfe3f5;--leaf:#4f8f3a;
 --ok:#6fb23f;--ok-d:#4c8a26;--no:#d95b4f;--no-d:#a63a31;--ink:#4a2a16;--font:"Baloo 2","Nunito","Segoe UI",system-ui,sans-serif}
*{box-sizing:border-box}
[hidden]{display:none!important}
html,body{height:100%;margin:0;overflow:hidden;font-family:var(--font);color:var(--ink);background:#cfe0c3}
button,select,input{font-family:inherit}button{cursor:pointer}
:focus-visible{outline:3px solid #ffb703;outline-offset:2px}

/* HUD + bản đồ */
.hud{position:fixed;inset:0 0 auto 0;height:52px;display:flex;justify-content:space-between;align-items:center;padding:0 14px;gap:10px;z-index:5;background:var(--paper);border-bottom:4px solid var(--wood);font-weight:800}
.keys{font-weight:500;font-size:14px;opacity:.8}
.coin{display:inline-block;width:16px;height:16px;border-radius:50%;background:#7a4fb5;border:3px solid #4b2d78;margin-right:6px;vertical-align:-2px}
#view{position:fixed;inset:52px 0 0 0;overflow:hidden}
#world{position:absolute;left:0;top:0;will-change:transform}
.grass{fill:#cfe0c3}
.tree-m{fill:#4c9a4c;stroke:#2f6b2f;stroke-width:2}
.shadow{fill:rgba(0,0,0,.25)}

/* nhà: mái + mặt tiền */
.lot{cursor:pointer}
.lot.available{--r1:#93d68e;--r2:#6fb86b;--wall:#f3e6c9}
.lot.owned{--r1:#d3d3d3;--r2:#b0b0b0;--wall:#e4dccb}
.lot.mine{--r1:#f7cf6e;--r2:#e3a93a;--wall:#f6e3b0}
.r1{fill:var(--r1)}.r2{fill:var(--r2)}.wall{fill:var(--wall)}
.ridge{stroke:rgba(0,0,0,.28);stroke-width:3}
.eave{fill:rgba(0,0,0,.2)}
.out{fill:none;stroke:#3b2a1c;stroke-width:2.5}
.door{fill:#6b4a2b;stroke:#3b2a1c;stroke-width:1.5}
.win{fill:#bfe3f5;stroke:#3b2a1c;stroke-width:1.5}
.board{fill:#fff6dd;stroke:#4a2a16;stroke-width:2}
.sh{fill:rgba(0,0,0,.18);pointer-events:none}
.lot:hover .r1,.lot:hover .r2{filter:brightness(1.1)}
.lot.near .out{stroke:var(--sel);stroke-width:5}
.lot text{text-anchor:middle;font-weight:800;pointer-events:none}
.code{font-size:24px;fill:#fff;stroke:rgba(0,0,0,.35);stroke-width:4px;paint-order:stroke}
.sgn{font-size:11px;fill:#4a2a16}
.pbody{fill:#2b6cb0;stroke:#fff;stroke-width:2}.phead{fill:#ffd6a5;stroke:#173f66;stroke-width:1.5}
#hint{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:var(--wood-d);color:var(--cream);padding:6px 18px;border-radius:14px;border:3px solid var(--wood-l);font-weight:800;z-index:5;display:none}

/* nút gỗ */
.btn{border:3px solid var(--wood-d);border-radius:12px;padding:6px 18px;font-weight:800;font-size:17px;color:#fff;background:var(--wood);box-shadow:0 4px 0 var(--wood-d);transition:transform .08s,box-shadow .08s}
.btn:active{transform:translateY(3px);box-shadow:0 1px 0 var(--wood-d)}
.btn.ok{background:var(--ok);border-color:var(--ok-d);box-shadow:0 4px 0 var(--ok-d)}
.btn.no{background:var(--no);border-color:var(--no-d);box-shadow:0 4px 0 var(--no-d)}
.btn.big{width:100%;font-size:20px;padding:9px;background:linear-gradient(#f2b441,#e19a1f);border-color:#8a5a12;box-shadow:0 5px 0 #8a5a12;color:var(--wood-d)}
.btn.big:disabled{background:#c9c0ad;border-color:#8c8470;box-shadow:0 5px 0 #8c8470;color:#6d664f;cursor:not-allowed}
.x{position:absolute;top:8px;right:10px;width:30px;height:30px;border-radius:50%;border:3px solid var(--no-d);background:var(--no);color:#fff;font-weight:800;line-height:1;padding:0}

/* panel mặt tiền */
#panel{position:fixed;right:16px;top:64px;width:360px;max-width:calc(100vw - 24px);max-height:calc(100vh - 76px);overflow:auto;z-index:6;display:none;
 background:var(--paper);border:5px solid var(--wood);border-radius:20px;padding:14px;box-shadow:0 6px 0 var(--wood-d),0 18px 30px #0004;animation:pop .2s ease-out}
@keyframes pop{from{transform:scale(.94);opacity:0}}
.tag{display:inline-block;background:var(--wood);color:var(--cream);border-radius:10px;padding:0 12px;font-weight:800;font-size:14px;margin-bottom:8px}
.title{display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:8px}
.title h2{margin:0;font-size:21px}
.badge{background:#d9efc4;border:2px solid var(--ok-d);color:#2f5f12;border-radius:999px;padding:0 10px;font-weight:800;font-size:14px;white-space:nowrap}
.sub{margin:0 0 8px;font-size:14px;opacity:.8}
dl{display:block;margin:0 0 4px;background:var(--cream);border:3px solid var(--paper-d);border-radius:12px;padding:6px 12px}
.r{display:flex;justify-content:space-between;padding:2px 0;font-weight:600}.r+.r{border-top:2px dashed var(--paper-d)}
dt,dd{margin:0}dd{font-weight:800}
label.l{display:block;margin:10px 0 4px;font-weight:800}
select,input[type=text]{width:100%;font-weight:600;font-size:15px;padding:7px 10px;border:3px solid var(--wood);border-radius:12px;background:var(--cream);color:var(--ink)}
.note{font-size:13px;margin:4px 0 10px;opacity:.75}
#msg{margin-top:8px;font-size:14px;font-weight:600;color:var(--no-d);min-height:18px}

/* cảnh mặt tiền */
.scene{position:relative;height:210px;border:4px solid var(--wood);border-radius:14px;overflow:hidden;
 background:linear-gradient(var(--sky) 0 52%,transparent 52%),repeating-linear-gradient(90deg,#0000 0 38px,#0001 38px 40px),repeating-linear-gradient(0deg,#d8ccb0 0 18px,#cfc2a3 18px 20px)}
.scene::before{content:"";position:absolute;left:0;right:0;top:34%;height:20%;background:linear-gradient(90deg,#d9cdbb 0 18%,#c7b8a4 18% 34%,#e2d6c0 34% 60%,#cdbfa8 60% 80%,#dccfb9 80%)}
.cloud{position:absolute;top:14px;width:60px;height:18px;border-radius:12px;background:#fff;box-shadow:16px -8px 0 2px #fff}
.tree{position:absolute;bottom:34px;width:46px;height:96px}
.tree::before{content:"";position:absolute;left:50%;bottom:0;width:10px;height:44px;margin-left:-5px;background:var(--wood);border-radius:3px}
.tree::after{content:"";position:absolute;left:0;top:0;width:46px;height:60px;border-radius:50%;background:var(--leaf);border:3px solid #35692a}
.tree.l{left:6px}.tree.r{right:6px}
.shop{position:absolute;left:50%;bottom:30px;width:210px;height:128px;transform:translateX(-50%);background:var(--body);border:4px solid var(--wood-d);border-radius:8px 8px 0 0}
.shop .sign{position:absolute;left:12px;right:12px;top:-14px;height:34px;background:var(--cream);border:3px solid var(--wood-d);border-radius:8px;display:grid;place-items:center;font-weight:800;font-size:13px;white-space:nowrap;overflow:hidden;padding:0 4px}
.shop .awn{position:absolute;left:-6px;right:-6px;top:24px;height:20px;border:3px solid var(--wood-d);border-radius:4px 4px 10px 10px;background:repeating-linear-gradient(90deg,var(--a1) 0 22px,var(--a2) 22px 44px)}
.shop .dr{position:absolute;left:12px;bottom:0;width:44px;height:62px;background:#f6dca4;border:3px solid var(--wood-d);border-bottom:0;border-radius:6px 6px 0 0}
.shop .wn{position:absolute;right:12px;bottom:8px;width:118px;height:62px;background:#f3e3bd;border:3px solid var(--wood-d);border-radius:6px;display:grid;grid-template-columns:repeat(4,1fr);place-items:center;font-size:19px;padding:2px;align-content:space-around}
.shop.empty .sign{color:#9a8660}.shop.empty .wn{background:var(--sky)}.shop.empty .dr{background:#aee0f2}
.deco{position:absolute;left:50%;bottom:6px;transform:translateX(-50%);font-size:22px;letter-spacing:150px;white-space:nowrap}
.scene.lights .sign{box-shadow:0 0 18px #ffe69a}

/* modal tùy chỉnh */
#customizer{display:none;position:fixed;inset:0;z-index:10;background:#2a160a88;padding:34px 12px 12px;overflow:auto}
.card{position:relative;width:min(900px,100%);margin:20px auto;background:var(--paper);border:6px solid var(--wood);border-radius:22px;padding:34px 16px 16px;box-shadow:0 8px 0 var(--wood-d);animation:pop .2s ease-out}
.banner{position:absolute;left:50%;top:-24px;transform:translateX(-50%);max-width:88%;background:var(--cream);border:5px solid var(--wood);border-radius:16px;padding:0 26px;font-weight:800;font-size:clamp(17px,3vw,28px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;box-shadow:0 4px 0 var(--wood-d);margin:0}
.cols{display:grid;grid-template-columns:1.05fr 1fr;gap:14px}
.right{background:var(--cream);border:4px solid var(--wood-l);border-radius:14px;padding:10px}
.tabs{display:flex;gap:6px;margin-bottom:10px}
.tab{flex:1;border:3px solid var(--wood);border-radius:12px 12px 0 0;background:var(--paper-d);font-weight:800;font-size:15px;padding:3px;color:var(--ink)}
.tab.active{background:var(--paper);border-bottom-color:var(--paper)}
.pane label{display:block;font-weight:700;font-size:15px;margin-top:8px}
.pane h4{margin:10px 0 4px;font-size:16px}
input[type=range]{width:100%;accent-color:var(--wood)}
.opts{display:flex;gap:6px;flex-wrap:wrap}
.opt{min-width:44px;height:44px;border:3px solid var(--wood-l);border-radius:10px;background:var(--paper);font-size:20px;padding:0 8px;font-weight:700;color:var(--ink)}
.opt[aria-pressed=true]{background:#ffe08a;border-color:#e19a1f}
.sw{width:34px;height:34px;min-width:0;border-radius:8px;padding:0}
.sw[aria-pressed=true]{box-shadow:0 0 0 3px #ffe08a}
.goods{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
.goods .opt{height:52px;font-size:26px}
.room{position:relative;height:300px;border:4px solid var(--wood);border-radius:12px;overflow:hidden;background:#f1dcb4}
.room .wall{position:absolute;inset:0 0 38% 0;background:repeating-linear-gradient(90deg,#f5e3bd 0 36px,#efd9ae 36px 38px)}
.room .floor{position:absolute;inset:62% 0 0 0;background:repeating-linear-gradient(90deg,#0000 0 52px,#0002 52px 54px),var(--floor)}
.room .win2{position:absolute;left:14px;top:14px;width:70px;height:84px;background:var(--sky);border:5px solid var(--wood);border-radius:4px}
.room .lamp{position:absolute;right:46%;top:0;width:3px;height:48px;background:var(--wood-d)}
.room .lamp::after{content:"";position:absolute;left:-14px;top:44px;width:30px;height:20px;border-radius:14px 14px 4px 4px;background:#ffd36b;box-shadow:0 0 24px #ffd36b}
.room .shelf{position:absolute;bottom:26%;width:96px;height:128px;background:var(--wood-l);border:4px solid var(--wood-d);border-radius:6px;display:grid;grid-template-rows:repeat(3,1fr);font-size:16px;text-align:center;left:calc(40% + var(--shelf) * .5%)}
.room .shelf span{border-bottom:3px solid var(--wood-d);display:grid;place-items:center}
.room .counter{position:absolute;left:10%;bottom:12%;width:44%;height:calc(34px + var(--ch) * .7px);background:#f6efe2;border:4px solid #b9a98c;border-radius:6px;transition:height .15s;display:flex;justify-content:center;font-size:22px}
.room .plant{position:absolute;left:4%;bottom:14%;font-size:30px}
.foot{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:12px}
#customizer-message{font-weight:700;color:var(--ok-d);min-height:22px}
@media(max-width:760px){.cols{grid-template-columns:1fr}.keys{display:none}#panel{right:8px;left:8px;width:auto;max-width:none}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="hud"><span>Startup Street</span><span class="keys">WASD / phím mũi tên: di chuyển · E: xem mặt bằng</span><span><i class="coin"></i>Vốn: <span id="money"></span></span></div>
<div id="view"><div id="world"></div></div>
<div id="hint"></div>

<aside id="panel" aria-label="Góc nhìn mặt tiền">
<button class="x" id="close" type="button" aria-label="Đóng">×</button>
<span class="tag">Góc nhìn mặt tiền</span>
<div class="scene" id="sceneP"></div>
<div class="title"><h2 id="pTitle"></h2><span id="statusPill" class="badge">Còn trống</span></div>
<p class="sub">Tọa lạc tại trung tâm Phố Startup</p>
<dl>
<div class="r"><dt>Giá thuê / mua</dt><dd id="pPrice"></dd></div>
<div class="r"><dt>Diện tích</dt><dd id="pArea"></dd></div>
<div class="r"><dt>Lượng khách qua lại</dt><dd id="pTraffic"></dd></div>
<div class="r"><dt>Tình trạng</dt><dd id="pStatus"></dd></div>
</dl>
<label class="l" for="business-type-select">Chọn mô hình kinh doanh</label>
<select id="business-type-select"><option value="">Đang tải mô hình...</option></select>
<p id="business-type-note" class="note">Chọn mô hình để xem trước mặt tiền phù hợp.</p>
<button class="btn big" id="buy" type="button">Thuê mặt bằng ngay</button>
<a class="btn" id="manage" hidden style="display:block;text-align:center;text-decoration:none;margin-top:10px">Quản lý kho hàng</a>
<div id="msg" role="status"></div>
</aside>

<section id="customizer" role="dialog" aria-modal="true" aria-labelledby="customizer-title">
<div class="card">
<h2 class="banner" id="customizer-title">Tùy chỉnh cửa hàng</h2>
<button class="x" id="customizer-close" type="button" aria-label="Đóng">×</button>
<div class="cols">
 <div>
  <div class="scene" id="sceneC"></div>
  <div class="room" id="room" hidden>
   <div class="wall"></div><div class="floor"></div><div class="win2"></div><div class="lamp"></div>
   <div class="shelf" id="shelf"><span></span><span></span><span></span></div>
   <div class="counter" id="counter">🧾</div><div class="plant" id="plantEl" hidden>🪴</div>
  </div>
 </div>
 <div class="right">
  <div class="tabs" role="tablist">
   <button class="tab active" type="button" data-tab="front">Mặt tiền</button>
   <button class="tab" type="button" data-tab="inside">Nội thất</button>
   <button class="tab" type="button" data-tab="goods">Hàng hóa</button>
   <button class="tab" type="button" data-tab="decor">Trang trí</button>
  </div>
  <div class="pane" data-pane="front">
   <label for="shop-name-custom">Tên trên biển hiệu</label>
   <input type="text" id="shop-name-custom" maxlength="32" placeholder="Ví dụ: Moon Tea">
   <h4>Màu mặt tiền</h4><div class="opts" id="facade-swatches"></div>
   <label for="awning-color">Màu mái hiên</label><select id="awning-color"></select>
  </div>
  <div class="pane" data-pane="inside" hidden>
   <label for="ch">Chiều cao quầy</label><input id="ch" type="range" min="0" max="100">
   <label for="sh">Vị trí kệ</label><input id="sh" type="range" min="0" max="100">
   <h4>Màu sắc sàn</h4><div class="opts" id="floor-swatches"></div>
  </div>
  <div class="pane" data-pane="goods" hidden>
   <h4>Chọn món đặt lên kệ</h4><div class="goods" id="goods-grid"></div>
   <p class="note">Mỗi tầng kệ chứa tối đa 3 món.</p><button class="btn" id="clear-shelf" type="button">Dọn kệ</button>
  </div>
  <div class="pane" data-pane="decor" hidden>
   <h4>Trang trí mặt tiền</h4><div class="opts" id="decor-opts"></div>
   <h4>Trong tiệm</h4><div class="opts"><button class="opt" id="plant-toggle" type="button" aria-pressed="false" aria-label="Chậu cây">🪴</button></div>
  </div>
 </div>
</div>
<div class="foot"><div id="customizer-message" role="status"></div><div style="display:flex;gap:12px"><button class="btn ok" id="save-design" type="button">Lưu thiết kế</button><button class="btn no" id="cancel-design" type="button">Đóng</button></div></div>
</div>
</section>

<script>
const W=2440,H=1240,R=12,SPEED=260;
const rows=[{y:170,h:170,face:'down',letter:'A'},{y:460,h:150,face:'up',letter:'B'},{y:630,h:150,face:'down',letter:'C'},{y:900,h:170,face:'up',letter:'D'}];
const xs=[];for(let i=0;i<6;i++)xs.push(40+i*190);for(let i=0;i<6;i++)xs.push(1280+i*190);
const labels={available:'Còn trống',owned:'Đã có chủ',mine:'Của bạn'};
let businessTypes=[
{id:1,name:'Trà sữa & đồ uống',description:'Pha chế đồ uống',required_capital:12000000},
{id:2,name:'Quán ăn nhỏ',description:'Món ăn nhanh và đồ ăn vặt',required_capital:15000000},
{id:3,name:'Tiệm bánh',description:'Bánh ngọt và bánh mì',required_capital:10000000},
{id:4,name:'Shop quần áo',description:'Thời trang và phụ kiện',required_capital:18000000},
{id:5,name:'Tiệm hoa',description:'Hoa tươi và quà tặng',required_capital:8000000},
{id:6,name:'Tạp hóa nhỏ',description:'Hàng hóa thiết yếu',required_capital:14000000}];
let selectedBusinessType=null;
const USER_ID=1; /* TODO: thay bằng user đang đăng nhập (TV1) */
const API='api/';
const apiJson=async(path,opt)=>{
 let r;try{r=await fetch(API+path,opt)}catch(e){throw new Error('Không kết nối được server')}
 const j=await r.json().catch(()=>null);
 if(!j)throw new Error('Server không trả về JSON (HTTP '+r.status+')');
 if(!j.success)throw new Error(j.message||'Lỗi API');
 return j};

/* kiểu mặt tiền theo id loại hình (id lạ dùng kiểu mặc định = tiệm bánh) */
const STYLES={
1:{body:'#6f8f5a',a1:'#4f6f3d',a2:'#cfe0bd',goods:['🧋','🍵','🥤','🍹','🫖','🍯','🧊','🍓']},
2:{body:'#c9794a',a1:'#d95b4f',a2:'#fff3d6',goods:['🍜','🍲','🍢','🍙','🥟','🍤','🍗','🥤']},
3:{body:'#a8714c',a1:'#d6a46a',a2:'#fff3d6',goods:['🍰','🥐','🍞','🧁','🥧','🍪','🎂','🥖']},
4:{body:'#5f7d9c',a1:'#9bb4cf',a2:'#fff3d6',goods:['👗','👒','👕','🧣','👖','🧢','👜','👟']},
5:{body:'#c98aa5',a1:'#e9a7bd',a2:'#fff3d6',goods:['💐','🌷','🌹','🌻','🪴','🌸','🎁','🌼']},
6:{body:'#d9b57a',a1:'#e8894a',a2:'#6fb0d6',goods:['🥕','🍎','🥫','🧺','🥬','🧅','🍋','🥚']}};
const styleOf=id=>STYLES[id]||STYLES[3];
const DECOR={plants:['Cây xanh','🌿🪴'],lights:['Đèn','✨💡'],flowers:['Chậu hoa','🌷🌸'],none:['Tối giản','']};
const FACADES=['#a8714c','#e9a7bd','#74b9a5','#f0c75e','#7899d4'];
const FLOORS=['#c99567','#a8714c','#8a5a3b','#e0c9a0','#6f5a48'];
const AWNINGS=[['#f4c95d','Vàng kem'],['#e76f51','Cam san hô'],['#8ecae6','Xanh da trời'],['#d8a7ca','Hồng pastel'],['#ffffff','Trắng']];

let seed=20260117;
function rnd(){seed=(seed+0x6D2B79F5)|0;let t=Math.imul(seed^(seed>>>15),1|seed);t=(t+Math.imul(t^(t>>>7),61|t))^t;return((t^(t>>>14))>>>0)/4294967296}
const lots=[];
rows.forEach(r=>xs.forEach((x,c)=>{
 const traffic=20+Math.floor(rnd()*76),area=30+Math.floor(rnd()*41);
 const price=Math.round((traffic*220000+area*180000)/100000)*100000;
 const status=rnd()<0.25?'owned':'available';
 lots.push({id:lots.length+1,code:r.letter+(c+1),x,y:r.y,w:170,h:r.h,face:r.face,traffic,area,price,status});
}));
const loadDesigns=()=>JSON.parse(localStorage.getItem('startupStreetDesigns')||'{}');
const saveDesigns=d=>localStorage.setItem('startupStreetDesigns',JSON.stringify(d));
const d0=loadDesigns();
lots.forEach(l=>{if(d0[l.code]&&d0[l.code].rented)l.status='mine'});

const fmt=n=>n.toLocaleString('vi-VN')+'đ';
let balance=null; /* chỉ là bản sao để hiển thị; số dư thật = users.balance trong MySQL */
localStorage.removeItem('startupStreetBalance'); /* xóa số dư cũ còn sót ở trình duyệt */
const doorPoint=l=>({x:l.x+l.w/2,y:l.face==='down'?l.y+l.h+20:l.y-20});

/* ---- vẽ nhà: mái ngói nhìn từ trên xuống + dải mặt tiền (biển, mái hiên, cửa) phía đường ---- */
const FH=54,AWN=['#e76f51','#f4c95d','#6fb0d6','#d8a7ca','#7fc97f'];
function lotSvg(l,i){
 const up=l.face==='up',rh=l.h-FH,ry=up?l.y+FH:l.y,fy=up?l.y:l.y+rh,cx=l.x+l.w/2,mid=ry+rh/2;
 const fo=(off,h)=>up?fy+FH-off-h:fy+off; /* y theo khoảng cách tới mép mái hiên */
 let stripes='';
 for(let s=0;s<10;s++)stripes+=`<rect x="${l.x+s*17}" y="${fo(22,10)}" width="17" height="10" fill="${s%2?'#fff6dd':AWN[i%5]}"/>`;
 const extra=(i*5)%3===0?`<rect x="${l.x+l.w-48}" y="${mid-24}" width="26" height="18" rx="3" fill="#bfe3f5" stroke="#3b2a1c" stroke-width="2"/>`
  :(i*3)%4===1?`<rect x="${l.x+22}" y="${mid-30}" width="16" height="22" fill="#6b4a3a" stroke="#3b2a1c" stroke-width="2"/>`:'';
 return `<g class="lot ${l.status}" data-i="${i}">
<rect class="sh" x="${l.x+8}" y="${l.y+10}" width="${l.w}" height="${l.h}" rx="6"/>
<rect class="r2" x="${l.x}" y="${ry}" width="${l.w}" height="${rh}"/>
<rect class="r1" x="${l.x}" y="${ry}" width="${l.w}" height="${rh/2}"/>
<rect fill="url(#shingle)" x="${l.x}" y="${ry}" width="${l.w}" height="${rh}"/>
<line class="ridge" x1="${l.x}" y1="${mid}" x2="${l.x+l.w}" y2="${mid}"/>
${extra}
<rect class="wall" x="${l.x}" y="${fy}" width="${l.w}" height="${FH}"/>
<rect class="win" x="${l.x+14}" y="${fo(26,22)}" width="30" height="22" rx="3"/>
<rect class="win" x="${l.x+l.w-44}" y="${fo(26,22)}" width="30" height="22" rx="3"/>
<rect class="door" x="${cx-13}" y="${fo(30,24)}" width="26" height="24" rx="3"/>
<rect class="board" x="${cx-56}" y="${fo(4,16)}" width="112" height="16" rx="4"/>
<text class="sgn" x="${cx}" y="${fo(4,16)+12}"></text>
${stripes}
<rect class="eave" x="${l.x}" y="${up?fy+FH-5:fy}" width="${l.w}" height="5"/>
<rect class="out" x="${l.x}" y="${l.y}" width="${l.w}" height="${l.h}" rx="4"/>
<text class="code" x="${cx}" y="${mid+8}">${l.code}</text></g>`;
}

/* ---- cảnh quan: đường lát đá, đèn đường, ghế, cây ---- */
let trees='';
for(let i=0;i<70;i++){const top=i%2===0,tx=20+rnd()*(W-40),ty=top?20+rnd()*120:1100+rnd()*120,r=10+Math.floor(rnd()*6);
 trees+=`<ellipse cx="${(tx+4).toFixed(0)}" cy="${(ty+r*.6).toFixed(0)}" rx="${(r*1.1).toFixed(0)}" ry="${(r*.6).toFixed(0)}" fill="rgba(0,0,0,.15)"/><circle class="tree-m" cx="${tx.toFixed(0)}" cy="${ty.toFixed(0)}" r="${r}"/><circle cx="${(tx-3).toFixed(0)}" cy="${(ty-3).toFixed(0)}" r="${(r*.55).toFixed(0)}" fill="#66b366"/>`}
let lamps='';
for(let x=100;x<W;x+=380){if(x>1100&&x<1340)continue;
 [354,446,794,886].forEach(y=>{lamps+=`<circle cx="${x}" cy="${y}" r="24" fill="url(#glow)"/><circle cx="${x}" cy="${y}" r="5" fill="#2b2f33" stroke="#111" stroke-width="1.5"/><circle cx="${x}" cy="${y}" r="2.5" fill="#ffe9a8"/>`})}
const benches=[[300,352],[1500,436],[2200,352],[420,792],[1900,876]].map(([x,y])=>`<rect x="${x}" y="${y}" width="46" height="12" rx="3" fill="#8a5a3b" stroke="#4a2a16" stroke-width="2"/>`).join('');
const bands=[[0,350,W,100],[0,790,W,100],[1170,0,100,H]];

/* ---- người đi bộ ---- */
const LANES=[{axis:'h',c:378,min:20,max:W-20},{axis:'h',c:422,min:20,max:W-20},{axis:'h',c:818,min:20,max:W-20},{axis:'h',c:862,min:20,max:W-20},{axis:'v',c:1198,min:20,max:H-20},{axis:'v',c:1242,min:20,max:H-20}];
const NPC_BODY=['#d95b4f','#7a4fb5','#e9a03b','#3b9c8f','#c0568a','#5a7bd6','#6fb23f'],NPC_SKIN=['#ffd6a5','#e8b88a','#c68a5e','#8d5a3b'],NPC_HAIR=['#2b1b12','#5b3a1e','#111','#7a4a21'];
const pick=a=>a[Math.floor(Math.random()*a.length)];
const npcs=Array.from({length:18},(_,k)=>{const ln=LANES[k%LANES.length];
 return{ln,pos:ln.min+Math.random()*(ln.max-ln.min),dir:Math.random()<.5?-1:1,sp:26+Math.random()*34,t:Math.random()*6.28,off:(Math.random()-.5)*10,body:pick(NPC_BODY),skin:pick(NPC_SKIN),hair:pick(NPC_HAIR)}});
const npcSvg=n=>`<g class="npc"><ellipse class="shadow" cx="0" cy="12" rx="10" ry="4.5"/><ellipse class="ft" cx="-4" cy="9" rx="3" ry="2.5" fill="#3a2a20"/><ellipse class="ft" cx="4" cy="9" rx="3" ry="2.5" fill="#3a2a20"/><circle cx="0" cy="1" r="9" fill="${n.body}" stroke="rgba(0,0,0,.35)" stroke-width="1.5"/><circle cx="0" cy="-10" r="6.5" fill="${n.skin}" stroke="rgba(0,0,0,.3)" stroke-width="1.2"/><path d="M-6.5 -10A6.5 6.5 0 0 1 6.5 -10Z" fill="${n.hair}"/></g>`;

const world=document.getElementById('world');
world.innerHTML=`<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
<defs>
<pattern id="cob" width="48" height="32" patternUnits="userSpaceOnUse"><rect width="48" height="32" fill="#d9cdb4"/>
<g fill="#e8decb" stroke="#c4b69a" stroke-width="1.5"><rect x="2" y="2" width="20" height="13" rx="5"/><rect x="26" y="2" width="20" height="13" rx="5"/><rect x="14" y="18" width="20" height="12" rx="5"/><rect x="-10" y="18" width="20" height="12" rx="5"/><rect x="38" y="18" width="20" height="12" rx="5"/></g></pattern>
<pattern id="shingle" width="16" height="10" patternUnits="userSpaceOnUse"><path d="M0 .5H16M0 5.5H16M8 .5V5.5M0 5.5V10M16 5.5V10" stroke="rgba(0,0,0,.14)" fill="none"/></pattern>
<radialGradient id="glow"><stop offset="0" stop-color="#ffe9a8" stop-opacity=".55"/><stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/></radialGradient>
</defs>
<rect class="grass" width="${W}" height="${H}"/>
${bands.map(b=>`<rect x="${b[0]}" y="${b[1]}" width="${b[2]}" height="${b[3]}" fill="#a89c80" stroke="#a89c80" stroke-width="8"/>`).join('')}
${bands.map(b=>`<rect x="${b[0]}" y="${b[1]}" width="${b[2]}" height="${b[3]}" fill="url(#cob)"/>`).join('')}
${trees}${benches}${lots.map(lotSvg).join('')}${lamps}
${npcs.map(npcSvg).join('')}
<g id="player"><ellipse class="shadow" cx="0" cy="13" rx="14" ry="6"/><circle cx="0" cy="2" r="18" fill="rgba(255,255,255,.35)"/><circle class="pbody" cx="0" cy="2" r="12"/><circle class="phead" cx="0" cy="-12" r="8"/></g></svg>`;

const $=id=>document.getElementById(id);
const els=world.querySelectorAll('.lot'),playerEl=$('player'),hint=$('hint'),panel=$('panel'),view=$('view'),moneyEl=$('money');
world.querySelectorAll('.npc').forEach((el,k)=>{npcs[k].el=el;npcs[k].feet=el.querySelectorAll('.ft')});
const player={x:1220,y:400},cam={x:0,y:0},keys=new Set();
let near=-1,open=-1;
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const blocked=(px,py)=>lots.some(l=>px+R>l.x&&px-R<l.x+l.w&&py+R>l.y&&py-R<l.y+l.h);

/* biển hiệu trên nhà: tên cửa hàng nếu đã thuê */
function refreshSign(i,ds){
 const l=lots[i],d=(ds||loadDesigns())[l.code];
 const t=l.status==='mine'?(d&&d.shopName)||'CỦA BẠN':l.status==='owned'?'ĐÃ CÓ CHỦ':'CHO THUÊ';
 els[i].querySelector('.sgn').textContent=t.toLocaleUpperCase('vi-VN').slice(0,14);
}
lots.forEach((l,i)=>refreshSign(i,d0));

/* ---- vẽ mặt tiền (dùng cho panel và modal) ---- */
function renderShop(el,o){
 el.classList.toggle('lights',o.decor==='lights');
 el.innerHTML=`<i class="cloud" style="left:20px"></i><i class="cloud" style="left:210px;top:30px;transform:scale(.8)"></i><i class="tree l"></i><i class="tree r"></i>
 <div class="shop${o.empty?' empty':''}" style="--body:${o.body};--a1:${o.a1};--a2:${o.a2}"><div class="sign"></div><div class="awn"></div><div class="dr"></div><div class="wn">${o.empty?'':o.goods.map(g=>`<span>${g}</span>`).join('')}</div></div>
 <span class="deco">${o.empty?'':(DECOR[o.decor]||DECOR.none)[1]}</span>`;
 el.querySelector('.sign').textContent=o.sign;
}
function updateFacadePreview(){
 const t=selectedBusinessType;
 if(!t){renderShop($('sceneP'),{empty:true,sign:'Cửa hàng của bạn',body:'#c9b99a',a1:'#d8cdb5',a2:'#f3e9d2',goods:[]});return}
 const s=styleOf(t.id);
 renderShop($('sceneP'),{sign:t.name.toLocaleUpperCase('vi-VN').slice(0,24),body:s.body,a1:s.a1,a2:s.a2,goods:s.goods,decor:'plants'});
}

function fillTypeSelect(keep){
 const sel=$('business-type-select');
 sel.innerHTML='<option value="">– Chọn mô hình kinh doanh –</option>'+businessTypes.map(t=>`<option value="${t.id}">${t.name}</option>`).join('');
 if(keep)sel.value=keep;
}
function showPanel(i){
 open=i;const l=lots[i];
 $('pTitle').textContent=l.code+' – Mặt bằng '+l.code;
 $('pPrice').textContent=fmt(l.price);$('pArea').textContent=l.area+' m²';$('pTraffic').textContent=l.traffic+'/100';
 $('pStatus').textContent=labels[l.status];$('statusPill').textContent=labels[l.status];
 const saved=loadDesigns();
 fillTypeSelect(saved[l.code]?String(saved[l.code].businessTypeId||''):'');
 selectedBusinessType=businessTypes.find(t=>String(t.id)===$('business-type-select').value)||null;
 $('business-type-note').textContent=selectedBusinessType?selectedBusinessType.description:'Chọn mô hình để xem trước mặt tiền phù hợp.';
 updateFacadePreview();
 const buy=$('buy');
 buy.disabled=l.status==='owned';
 buy.textContent=l.status==='available'?'Thuê mặt bằng ngay':l.status==='mine'?'Tùy chỉnh cửa hàng':labels[l.status];
 const mg=$('manage'),sid=(saved[l.code]||{}).shopId;mg.hidden=!(l.status==='mine'&&sid);
 if(sid)mg.href='business-management.html?user_id='+USER_ID+'&shop_id='+sid;
 $('msg').textContent='';
 panel.style.display='block';
}
function closePanel(){open=-1;panel.style.display='none'}
function updateMoney(){moneyEl.textContent=balance===null?'…':fmt(balance)}
async function refreshBalance(){
 try{balance=Number((await apiJson('get-balance.php?user_id='+USER_ID)).data.balance);updateMoney()}
 catch(e){/* giữ nguyên số đang hiển thị, không tự đoán số dư */}}
$('close').addEventListener('click',closePanel);

$('business-type-select').addEventListener('change',ev=>{
 selectedBusinessType=businessTypes.find(t=>String(t.id)===ev.target.value)||null;
 $('business-type-note').textContent=selectedBusinessType?selectedBusinessType.description+' · Vốn tham khảo: '+fmt(Number(selectedBusinessType.required_capital||0)):'Chọn mô hình để xem trước mặt tiền phù hợp.';
 updateFacadePreview();
});

$('buy').addEventListener('click',async()=>{
 if(open<0)return;const l=lots[open];
 if(l.status==='mine'){const code=l.code;closePanel();openCustomizer(code,'');return}
 if(l.status!=='available')return;
 if(balance!==null&&balance<l.price){$('msg').textContent='Bạn không đủ vốn để thuê mặt bằng này.';return}
 const typeId=$('business-type-select').value;
 if(!typeId){$('msg').textContent='Hãy chọn mô hình kinh doanh trước khi thuê.';return}
 selectedBusinessType=businessTypes.find(t=>String(t.id)===String(typeId));
 const idx=open,buyBtn=$('buy');buyBtn.disabled=true;$('msg').textContent='Đang tạo cửa hàng...';
 let shop,rent;
 try{
  rent=(await apiJson('rent-property.php',{method:'POST',headers:{'Content-Type':'application/json'},
   body:JSON.stringify({user_id:USER_ID,property_id:l.id})})).data;
  balance=Number(rent.balance);updateMoney();
 }catch(err){$('msg').textContent='Không thể thuê mặt bằng: '+err.message;buyBtn.disabled=false;refreshBalance();return}
 try{
  shop=(await apiJson('create-shop.php',{method:'POST',headers:{'Content-Type':'application/json'},
   body:JSON.stringify({owner_user_id:USER_ID,property_id:l.id,business_type_id:selectedBusinessType.id,name:selectedBusinessType.name})})).data;
 }catch(err){$('msg').textContent='Đã thuê mặt bằng nhưng chưa tạo được cửa hàng: '+err.message+'. Bấm lại để thử tiếp (không bị trừ tiền thuê lần 2).';buyBtn.disabled=false;return}
 buyBtn.disabled=false;
 /* tiền thuê đã được server trừ trong rent-property.php */
 l.status='mine';els[idx].setAttribute('class','lot mine'+(idx===near?' near':''));updateMoney();
 const s=styleOf(selectedBusinessType.id),designs=loadDesigns();
 designs[l.code]={lotCode:l.code,propertyPrice:l.price,businessTypeId:selectedBusinessType.id,businessTypeName:selectedBusinessType.name,shopName:selectedBusinessType.name,facadeColor:s.body,awningColor:'#f4c95d',decor:'plants',rented:true,shopId:shop.shop_id};
 saveDesigns(designs);refreshSign(idx,designs);
 const code=l.code;closePanel();
 openCustomizer(code,'Đã thuê mặt bằng '+code+'. Bắt đầu tùy chỉnh cửa hàng!');
});

/* ---- modal tùy chỉnh ---- */
let cd=null,curCode=null;
function setTab(t){
 document.querySelectorAll('.tab').forEach(b=>b.classList.toggle('active',b.dataset.tab===t));
 document.querySelectorAll('.pane').forEach(p=>p.hidden=p.dataset.pane!==t);
 $('sceneC').hidden=t!=='front'&&t!=='decor';
 $('room').hidden=!$('sceneC').hidden;
}
function drawC(){
 const s=styleOf(cd.businessTypeId);
 renderShop($('sceneC'),{sign:(cd.shopName||'Cửa hàng của bạn').toLocaleUpperCase('vi-VN'),body:cd.facadeColor,a1:cd.awningColor,a2:'#fff6dd',goods:s.goods,decor:cd.decor});
 const r=$('room').style;r.setProperty('--ch',cd.counterHeight);r.setProperty('--shelf',cd.shelfPos);r.setProperty('--floor',cd.floorColor);
 document.querySelectorAll('#shelf span').forEach((e,i)=>e.textContent=cd.shelfGoods[i].join(''));
 $('plantEl').hidden=!cd.plant;$('plant-toggle').setAttribute('aria-pressed',!!cd.plant);
 document.querySelectorAll('#facade-swatches .sw').forEach(b=>b.setAttribute('aria-pressed',b.dataset.c===cd.facadeColor));
 document.querySelectorAll('#floor-swatches .sw').forEach(b=>b.setAttribute('aria-pressed',b.dataset.c===cd.floorColor));
 document.querySelectorAll('#decor-opts .opt').forEach(b=>b.setAttribute('aria-pressed',b.dataset.k===cd.decor));
}
function openCustomizer(code,msg){
 const saved=loadDesigns()[code];if(!saved)return;
 curCode=code;
 cd=Object.assign({counterHeight:50,shelfPos:50,floorColor:FLOORS[0],plant:false,shelfGoods:[[],[],[]]},saved);
 const s=styleOf(cd.businessTypeId);
 $('customizer-title').textContent='Tùy chỉnh '+(cd.shopName||'cửa hàng')+' · '+code;
 $('shop-name-custom').value=cd.shopName||'';
 $('awning-color').value=cd.awningColor;$('ch').value=cd.counterHeight;$('sh').value=cd.shelfPos;
 $('goods-grid').innerHTML=s.goods.map(g=>`<button type="button" class="opt" aria-label="Đặt ${g} lên kệ">${g}</button>`).join('');
 document.querySelectorAll('#goods-grid .opt').forEach(b=>b.onclick=()=>{
  const r=cd.shelfGoods.findIndex(a=>a.length<3);if(r>=0){cd.shelfGoods[r].push(b.textContent);drawC()}});
 $('customizer-message').textContent=msg||'';
 $('customizer').style.display='block';
 setTab('front');drawC();
}
$('facade-swatches').innerHTML=FACADES.map(c=>`<button type="button" class="opt sw" data-c="${c}" style="background:${c}" aria-label="Màu mặt tiền ${c}"></button>`).join('');
$('floor-swatches').innerHTML=FLOORS.map(c=>`<button type="button" class="opt sw" data-c="${c}" style="background:${c}" aria-label="Màu sàn ${c}"></button>`).join('');
$('awning-color').innerHTML=AWNINGS.map(a=>`<option value="${a[0]}">${a[1]}</option>`).join('');
$('decor-opts').innerHTML=Object.entries(DECOR).map(([k,v])=>`<button type="button" class="opt" data-k="${k}" aria-pressed="false">${v[1]||'∅'} ${v[0]}</button>`).join('');
document.querySelectorAll('.tab').forEach(b=>b.addEventListener('click',()=>setTab(b.dataset.tab)));
document.querySelectorAll('#facade-swatches .sw').forEach(b=>b.addEventListener('click',()=>{cd.facadeColor=b.dataset.c;drawC()}));
document.querySelectorAll('#floor-swatches .sw').forEach(b=>b.addEventListener('click',()=>{cd.floorColor=b.dataset.c;drawC()}));
document.querySelectorAll('#decor-opts .opt').forEach(b=>b.addEventListener('click',()=>{cd.decor=b.dataset.k;drawC()}));
$('shop-name-custom').addEventListener('input',e=>{cd.shopName=e.target.value;drawC()});
$('awning-color').addEventListener('change',e=>{cd.awningColor=e.target.value;drawC()});
$('ch').addEventListener('input',e=>{cd.counterHeight=+e.target.value;drawC()});
$('sh').addEventListener('input',e=>{cd.shelfPos=+e.target.value;drawC()});
$('plant-toggle').addEventListener('click',()=>{cd.plant=!cd.plant;drawC()});
$('clear-shelf').addEventListener('click',()=>{cd.shelfGoods=[[],[],[]];drawC()});
$('save-design').addEventListener('click',()=>{
 const designs=loadDesigns();if(!curCode||!designs[curCode])return;
 cd.shopName=cd.shopName.trim()||'Cửa hàng của bạn';
 designs[curCode]=Object.assign(designs[curCode],cd,{designSaved:true});saveDesigns(designs);
 const li=lots.findIndex(x=>x.code===curCode);if(li>=0)refreshSign(li,designs);
 $('customizer-title').textContent='Tùy chỉnh '+cd.shopName+' · '+curCode;
 $('customizer-message').textContent='Đã lưu thiết kế trên trình duyệt.';
});
const closeC=()=>$('customizer').style.display='none';
$('customizer-close').addEventListener('click',closeC);$('cancel-design').addEventListener('click',closeC);

/* ---- điều khiển & vòng lặp game ---- */
world.addEventListener('click',ev=>{const g=ev.target.closest('.lot');if(g)showPanel(Number(g.dataset.i))});
window.addEventListener('keydown',ev=>{
 if(/^(INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName))return; /* gõ tên cửa hàng không làm nhân vật di chuyển */
 const k=ev.key.toLowerCase();
 if(['arrowup','arrowdown','arrowleft','arrowright',' '].includes(k))ev.preventDefault();
 if(k==='e'&&near>=0&&$('customizer').style.display!=='block')showPanel(near);
 if(k==='escape'){closePanel();closeC()}
 keys.add(k);
});
window.addEventListener('keyup',ev=>keys.delete(ev.key.toLowerCase()));
window.addEventListener('blur',()=>keys.clear());

function step(dt){
 let dx=0,dy=0;
 if(keys.has('a')||keys.has('arrowleft'))dx-=1;if(keys.has('d')||keys.has('arrowright'))dx+=1;
 if(keys.has('w')||keys.has('arrowup'))dy-=1;if(keys.has('s')||keys.has('arrowdown'))dy+=1;
 if(dx&&dy){dx*=Math.SQRT1_2;dy*=Math.SQRT1_2}
 const nx=clamp(player.x+dx*SPEED*dt,R,W-R);if(!blocked(nx,player.y))player.x=nx;
 const ny=clamp(player.y+dy*SPEED*dt,R,H-R);if(!blocked(player.x,ny))player.y=ny;
}
/* người đi bộ: đi dọc làn đường, chạm đầu mút thì quay lại */
function stepNpcs(dt){
 npcs.forEach(n=>{
  n.pos+=n.dir*n.sp*dt;
  if(n.pos<n.ln.min){n.pos=n.ln.min;n.dir=1}else if(n.pos>n.ln.max){n.pos=n.ln.max;n.dir=-1}
  n.t+=dt*n.sp*.22;
  const h=n.ln.axis==='h',x=h?n.pos:n.ln.c+n.off,y=(h?n.ln.c+n.off:n.pos)-Math.abs(Math.sin(n.t))*1.5,s=Math.sin(n.t)*3;
  n.el.setAttribute('transform',`translate(${x.toFixed(1)} ${y.toFixed(1)})`);
  n.feet[0].setAttribute('cy',9+s);n.feet[1].setAttribute('cy',9-s);
 });
}
function updateNear(){
 let best=-1,bestD=70;
 lots.forEach((l,i)=>{const p=doorPoint(l),d=Math.hypot(player.x-p.x,player.y-p.y);if(d<bestD){best=i;bestD=d}});
 if(best!==near){if(near>=0)els[near].classList.remove('near');if(best>=0)els[best].classList.add('near');near=best}
 if(near>=0){hint.textContent='Nhấn E để xem mặt bằng '+lots[near].code;hint.style.display='block'}else hint.style.display='none';
}
function follow(dt,snap){
 const vw=view.clientWidth,vh=view.clientHeight;
 const tx=W<=vw?(W-vw)/2:clamp(player.x-vw/2,0,W-vw),ty=H<=vh?(H-vh)/2:clamp(player.y-vh/2,0,H-vh);
 const f=snap?1:Math.min(1,dt*6);cam.x+=(tx-cam.x)*f;cam.y+=(ty-cam.y)*f;
 world.style.transform=`translate(${-cam.x}px, ${-cam.y}px)`;
}
let last=performance.now();
function frame(now){
 const dt=Math.min(0.05,(now-last)/1000);last=now;
 step(dt);stepNpcs(dt);playerEl.setAttribute('transform',`translate(${player.x.toFixed(1)} ${player.y.toFixed(1)})`);
 updateNear();follow(dt,false);requestAnimationFrame(frame);
}

fetch('api/business-types.php').then(r=>r.json()).then(data=>{
 if(data&&data.success&&data.data&&Array.isArray(data.data.business_types)&&data.data.business_types.length){
  businessTypes=data.data.business_types;
  if(open>=0)fillTypeSelect($('business-type-select').value);
 }
}).catch(()=>{});

updateMoney();refreshBalance();window.addEventListener('focus',refreshBalance);updateFacadePreview();
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
}).catch(()=>{});
playerEl.setAttribute('transform',`translate(${player.x} ${player.y})`);
follow(0,true);requestAnimationFrame(frame);
</script>
</body>
</html>
