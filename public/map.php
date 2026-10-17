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
.grass{fill:#cfe0c3}.walk{fill:#d8d2c3}.road{fill:#5b5f66}
.dash{stroke:#fff;stroke-width:3;stroke-dasharray:20 14}
.tree-m{fill:#4c9a4c;stroke:#2f6b2f;stroke-width:2}
.body{stroke:#333;stroke-width:2}
.available .body{fill:var(--free)}.owned .body{fill:var(--owned)}.mine .body{fill:var(--mine)}
.roof{fill:rgba(0,0,0,.2)}.door{fill:#6b4a2b}.win{fill:#bfe3f5;stroke:#333;stroke-width:1.5}
.lot{cursor:pointer}.lot:hover .body{filter:brightness(1.12)}
.lot.near .body{stroke:var(--sel);stroke-width:5}
.lot text{font-size:24px;font-weight:700;text-anchor:middle;fill:#222;pointer-events:none}
.shadow{fill:rgba(0,0,0,.25)}.pbody{fill:#2b6cb0;stroke:#173f66;stroke-width:2}.phead{fill:#ffd6a5;stroke:#173f66;stroke-width:2}
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
 lots.push({code:r.letter+(c+1),x,y:r.y,w:170,h:r.h,face:r.face,traffic,area,price,status});
}));
const loadDesigns=()=>JSON.parse(localStorage.getItem('startupStreetDesigns')||'{}');
const saveDesigns=d=>localStorage.setItem('startupStreetDesigns',JSON.stringify(d));
const d0=loadDesigns();
lots.forEach(l=>{if(d0[l.code]&&d0[l.code].rented)l.status='mine'});

const fmt=n=>n.toLocaleString('vi-VN')+'đ';
let balance=Number(localStorage.getItem('startupStreetBalance')||50000000);
const doorPoint=l=>({x:l.x+l.w/2,y:l.face==='down'?l.y+l.h+20:l.y-20});

function lotSvg(l,i){
 const roofY=l.face==='down'?l.y:l.y+l.h-26,doorY=l.face==='down'?l.y+l.h-34:l.y,winY=l.y+l.h/2-12;
 return `<g class="lot ${l.status}" data-i="${i}">
<rect class="body" x="${l.x}" y="${l.y}" width="${l.w}" height="${l.h}" rx="8"/>
<rect class="roof" x="${l.x}" y="${roofY}" width="${l.w}" height="26" rx="4"/>
<rect class="door" x="${l.x+l.w/2-14}" y="${doorY}" width="28" height="34" rx="3"/>
<rect class="win" x="${l.x+22}" y="${winY}" width="30" height="24" rx="3"/>
<rect class="win" x="${l.x+l.w-52}" y="${winY}" width="30" height="24" rx="3"/>
<text x="${l.x+l.w/2}" y="${winY+8}">${l.code}</text></g>`;
}
let trees='';
for(let i=0;i<70;i++){const top=i%2===0,tx=20+rnd()*(W-40),ty=top?20+rnd()*120:1100+rnd()*120;
 trees+=`<circle class="tree-m" cx="${tx.toFixed(0)}" cy="${ty.toFixed(0)}" r="${10+Math.floor(rnd()*6)}"/>`}

const world=document.getElementById('world');
world.innerHTML=`<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
<rect class="grass" width="${W}" height="${H}"/>
<rect class="walk" x="0" y="350" width="${W}" height="100"/><rect class="walk" x="0" y="790" width="${W}" height="100"/><rect class="walk" x="1170" y="0" width="100" height="${H}"/>
<rect class="road" x="0" y="360" width="${W}" height="80"/><rect class="road" x="0" y="800" width="${W}" height="80"/><rect class="road" x="1180" y="0" width="80" height="${H}"/>
<line class="dash" x1="0" y1="400" x2="${W}" y2="400"/><line class="dash" x1="0" y1="840" x2="${W}" y2="840"/><line class="dash" x1="1220" y1="0" x2="1220" y2="${H}"/>
${trees}${lots.map(lotSvg).join('')}
<g id="player"><ellipse class="shadow" cx="0" cy="12" rx="13" ry="6"/><circle class="pbody" cx="0" cy="2" r="12"/><circle class="phead" cx="0" cy="-12" r="8"/></g></svg>`;

const $=id=>document.getElementById(id);
const els=world.querySelectorAll('.lot'),playerEl=$('player'),hint=$('hint'),panel=$('panel'),view=$('view'),moneyEl=$('money');
const player={x:1220,y:400},cam={x:0,y:0},keys=new Set();
let near=-1,open=-1;
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const blocked=(px,py)=>lots.some(l=>px+R>l.x&&px-R<l.x+l.w&&py+R>l.y&&py-R<l.y+l.h);

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
 $('msg').textContent='';
 panel.style.display='block';
}
function closePanel(){open=-1;panel.style.display='none'}
function updateMoney(){moneyEl.textContent=fmt(balance)}
$('close').addEventListener('click',closePanel);

$('business-type-select').addEventListener('change',ev=>{
 selectedBusinessType=businessTypes.find(t=>String(t.id)===ev.target.value)||null;
 $('business-type-note').textContent=selectedBusinessType?selectedBusinessType.description+' · Vốn tham khảo: '+fmt(Number(selectedBusinessType.required_capital||0)):'Chọn mô hình để xem trước mặt tiền phù hợp.';
 updateFacadePreview();
});

$('buy').addEventListener('click',()=>{
 if(open<0)return;const l=lots[open];
 if(l.status==='mine'){const code=l.code;closePanel();openCustomizer(code,'');return}
 if(l.status!=='available')return;
 if(balance<l.price){$('msg').textContent='Bạn không đủ vốn để thuê mặt bằng này.';return}
 const typeId=$('business-type-select').value;
 if(!typeId){$('msg').textContent='Hãy chọn mô hình kinh doanh trước khi thuê.';return}
 selectedBusinessType=businessTypes.find(t=>String(t.id)===String(typeId));
 balance-=l.price;localStorage.setItem('startupStreetBalance',String(balance));
 l.status='mine';els[open].setAttribute('class','lot mine'+(open===near?' near':''));updateMoney();
 const s=styleOf(selectedBusinessType.id),designs=loadDesigns();
 designs[l.code]={lotCode:l.code,propertyPrice:l.price,businessTypeId:selectedBusinessType.id,businessTypeName:selectedBusinessType.name,shopName:selectedBusinessType.name,facadeColor:s.body,awningColor:'#f4c95d',decor:'plants',rented:true};
 saveDesigns(designs);
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
 step(dt);playerEl.setAttribute('transform',`translate(${player.x.toFixed(1)} ${player.y.toFixed(1)})`);
 updateNear();follow(dt,false);requestAnimationFrame(frame);
}

fetch('/api/business-types.php').then(r=>r.json()).then(data=>{
 if(data&&data.success&&data.data&&Array.isArray(data.data.business_types)&&data.data.business_types.length){
  businessTypes=data.data.business_types;
  if(open>=0)fillTypeSelect($('business-type-select').value);
 }
}).catch(()=>{});

updateMoney();updateFacadePreview();
playerEl.setAttribute('transform',`translate(${player.x} ${player.y})`);
follow(0,true);requestAnimationFrame(frame);
</script>
</body>
</html>
