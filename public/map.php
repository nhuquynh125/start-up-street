<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Startup Street</title>
<style>
:root { --free:#7fc97f; --owned:#c9c9c9; --mine:#f2b84b; --sel:#2b6cb0; }
* { box-sizing:border-box; }
html, body { height:100%; margin:0; overflow:hidden; font-family:system-ui,sans-serif; color:#222; background:#cfe0c3; }
.hud { position:fixed; inset:0 0 auto 0; height:48px; display:flex; justify-content:space-between; align-items:center; padding:0 20px; background:#fff; border-bottom:1px solid #ddd; font-weight:600; z-index:5; }
#view { position:fixed; inset:48px 0 0 0; overflow:hidden; }
#world { position:absolute; left:0; top:0; will-change:transform; }
.grass { fill:#cfe0c3; }
.walk { fill:#d8d2c3; }
.road { fill:#5b5f66; }
.dash { stroke:#fff; stroke-width:3; stroke-dasharray:20 14; }
.tree { fill:#4c9a4c; stroke:#2f6b2f; stroke-width:2; }
.body { stroke:#333; stroke-width:2; }
.available .body { fill:var(--free); }
.owned .body { fill:var(--owned); }
.mine .body { fill:var(--mine); }
.roof { fill:rgba(0,0,0,.2); }
.door { fill:#6b4a2b; }
.win { fill:#bfe3f5; stroke:#333; stroke-width:1.5; }
.lot { cursor:pointer; }
.lot:hover .body { filter:brightness(1.12); }
.lot.near .body { stroke:var(--sel); stroke-width:5; }
.lot text { font-size:24px; font-weight:700; text-anchor:middle; fill:#222; pointer-events:none; }
.shadow { fill:rgba(0,0,0,.25); }
.pbody { fill:#2b6cb0; stroke:#173f66; stroke-width:2; }
.phead { fill:#ffd6a5; stroke:#173f66; stroke-width:2; }
#hint { position:fixed; left:50%; bottom:24px; transform:translateX(-50%); background:rgba(0,0,0,.75); color:#fff; padding:10px 16px; border-radius:20px; font-size:15px; z-index:5; display:none; }
#panel { position:fixed; right:20px; bottom:20px; width:300px; background:#fff; border-radius:12px; padding:20px; box-shadow:0 4px 16px rgba(0,0,0,.25); z-index:6; display:none; }
#panel h2 { margin:0 28px 12px 0; font-size:18px; }
#close { position:absolute; top:10px; right:12px; border:0; background:none; font-size:22px; cursor:pointer; width:auto; padding:0; color:#222; }
dl { display:grid; grid-template-columns:auto 1fr; gap:6px 12px; margin:0 0 16px; }
dt { color:#666; }
dd { margin:0; font-weight:600; }
#buy { width:100%; padding:12px; border:0; border-radius:8px; background:var(--sel); color:#fff; font-size:16px; font-weight:600; cursor:pointer; }
#buy:disabled { background:#aaa; cursor:not-allowed; }
#msg { margin-top:10px; font-size:14px; color:#b03030; min-height:18px; }
.keys { font-weight:400; color:#666; font-size:14px; }

.kicker{display:inline-block;color:#f2c85c;background:#38415a;border-radius:20px;padding:5px 10px;font-size:10px;font-weight:800;letter-spacing:.07em}
#panel{top:50%;bottom:auto;transform:translateY(-50%);width:min(430px,calc(100vw - 28px));max-height:calc(100vh - 76px);overflow:auto;padding:18px;background:#17243a;color:#f4f7ff;border:1px solid #34445f}
#pTitle{margin:10px 0 2px;font-size:20px;color:#f2c65b}#pSubtitle{margin:0 0 10px;color:#aebbd0;font-size:12px}.panel-heading{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.status-pill{background:#243a39;color:#80e1b1;padding:6px 9px;border-radius:18px;font-size:11px;font-weight:700;white-space:nowrap}
.facade-preview{margin:14px 0;border-radius:10px;overflow:hidden;border:5px solid #0e1728}.facade-preview svg{display:block;width:100%}#panel dl{grid-template-columns:1fr auto;gap:9px 12px;padding:12px;background:#202f48;border-radius:10px;font-size:13px}#panel dt{color:#aebbd0}#panel dd{color:#fff;text-align:right}.field-label{display:block;margin:14px 0 7px;font-size:10px;font-weight:800;color:#f2c65b;letter-spacing:.06em}#business-type-select,.control-section select,.control-section input{width:100%;border:1px solid #435572;background:#111d32;color:#f4f7ff;border-radius:8px;padding:11px;font-size:14px}.muted-note{font-size:11px;color:#9ba9bf;line-height:1.5;margin:8px 0 12px}#buy{background:linear-gradient(100deg,#3978ef,#6245e8)}#buy:disabled{background:#8b95a5}
#customizer{display:none;position:fixed;z-index:10;inset:64px 3vw 18px;max-width:1160px;margin:auto;background:#111d32;color:#f4f7ff;border:1px solid #34445f;border-radius:18px;padding:24px;box-shadow:0 24px 90px #111b2b99;overflow:auto}#customizer-close{position:absolute;top:12px;right:15px;color:#eaf0ff;background:transparent;border:0;font-size:28px;cursor:pointer}.customizer-top{display:flex;align-items:center;justify-content:space-between;gap:20px;padding-right:28px;margin-bottom:20px}.customizer-top h2{color:#f5c65c;margin:8px 0 0;font-size:clamp(20px,3vw,28px)}.customizer-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.8fr);gap:22px}.scene{height:360px;position:relative;overflow:hidden;border-radius:14px;background:linear-gradient(#bde5ff 0 65%,#e9dfcb 65%);border:1px solid #4c5d77}.shop-front{position:absolute;width:66%;height:66%;bottom:20%;left:17%;background:#a95c32;border:7px solid #55331f;box-shadow:0 12px 0 #0002}.awning{height:18px;background:#f4c95d;border-bottom:4px solid #55331f}.shop-sign{margin:8px auto;width:84%;padding:8px 4px;background:#fff2d2;color:#4a3020;border:3px solid #55331f;font-weight:900;font-size:clamp(10px,1.6vw,17px);text-align:center;overflow:hidden;white-space:nowrap}.shop-window{position:absolute;bottom:8px;left:7%;width:43%;height:38%;display:grid;place-items:center;background:#bde8f3;border:5px solid #55331f;color:#a95c32;font-size:20px}.shop-door{position:absolute;bottom:8px;right:8%;width:25%;height:45%;background:#70452b;border:4px solid #55331f}.plants{position:absolute;bottom:-8px;left:-32px;font-size:25px}.inside-preview{display:none;position:absolute;inset:9% 8%;background:#f4e5c9;color:#70452b;border:8px solid #77523b;text-align:center;padding:25px}.inside-preview b{font-size:13px}.inside-preview #interior-items{font-size:35px;margin-top:35px}.counter{width:70%;height:22px;background:#bd8552;border:4px solid #77523b;margin:28px auto}.show-inside .inside-preview{display:block}.show-inside .shop-front{opacity:.12}.modern .inside-preview{background:#d9e5e8;border-color:#455b67}.minimal .inside-preview{background:#f8f6ef;border-color:#aaa}.no-decor .plants{display:none}.lights .shop-sign{box-shadow:0 0 18px #ffe69a}.flowers .plants{filter:hue-rotate(75deg)}
.customizer-controls{background:#1b2a43;border:1px solid #354762;border-radius:14px;padding:18px}.step-tabs{display:flex;gap:8px;margin-bottom:18px}.step-tab{flex:1;border:1px solid #435572;border-radius:8px;padding:10px;background:#22334f;color:#c8d4e8;cursor:pointer;font-weight:700}.step-tab.active{background:#f5c65c;color:#252c3b}.control-section label{display:block;margin:13px 0 6px;font-size:12px;font-weight:700;color:#d4deee}.swatches{display:flex;gap:10px;margin:10px 0 14px}.swatch{width:34px;height:34px;border:3px solid #ffffff55;border-radius:50%;background:var(--swatch);cursor:pointer}.swatch.active{outline:3px solid #f5c65c;outline-offset:2px}.primary-btn{width:100%;border:0;border-radius:9px;padding:13px;margin-top:18px;background:linear-gradient(100deg,#3978ef,#6245e8);color:white;font-weight:800;cursor:pointer}.interior-note{color:#9eacc2;background:#142239;padding:12px;border-radius:8px;font-size:12px;line-height:1.5}#customizer-message{color:#9ae6b4;font-size:13px;margin-top:12px;min-height:18px}
@media(max-width:760px){#panel{right:10px;left:10px;top:auto;bottom:10px;transform:none;width:auto;max-height:82vh}.customizer-grid{grid-template-columns:1fr}#customizer{inset:56px 8px 8px;padding:16px}.scene{height:250px}.customizer-top{align-items:flex-start;flex-direction:column}}

</style>
</head>
<body>
<div class="hud"><span>Startup Street</span><span class="keys">WASD / phím mũi tên: di chuyển · E: xem mặt bằng</span><span>Vốn: <span id="money"></span></span></div>
<div id="view"><div id="world"></div></div>
<div id="hint"></div>
<aside id="panel">
<button id="close" type="button" aria-label="Đóng">×</button>
<div class="kicker">GÓC NHÌN MẶT TIỀN</div>
<div class="facade-preview"><svg viewBox="0 0 420 230"><rect width="420" height="230" fill="#c7e7fa"/><rect y="174" width="420" height="56" fill="#e9e2d5"/><rect x="76" y="57" width="268" height="130" rx="4" fill="#a95c32" stroke="#56311f" stroke-width="5"/><rect x="91" y="70" width="238" height="26" fill="#f8e9c9" stroke="#56311f" stroke-width="3"/><text id="facade-sign" x="210" y="88" text-anchor="middle" font-size="13" font-weight="700" fill="#56311f">CỬA HÀNG CỦA BẠN</text><rect x="100" y="108" width="78" height="67" fill="#a8dff0" stroke="#56311f" stroke-width="4"/><rect x="190" y="108" width="136" height="67" fill="#f9f3e8" stroke="#56311f" stroke-width="4"/><path d="M198 150H318M198 130H318" stroke="#d5b88d" stroke-width="4"/><rect x="129" y="146" width="20" height="29" fill="#6b442b"/></svg></div>
<div class="panel-heading"><div><h2 id="pTitle"></h2><p id="pSubtitle">Tọa lạc tại trung tâm Phố Startup</p></div><span id="statusPill" class="status-pill">Còn trống</span></div>
<dl><dt>Giá thuê / mua</dt><dd id="pPrice"></dd><dt>Diện tích</dt><dd id="pArea"></dd><dt>Lượng khách qua lại</dt><dd id="pTraffic"></dd><dt>Tình trạng</dt><dd id="pStatus"></dd></dl>
<label class="field-label" for="business-type-select">CHỌN MÔ HÌNH KINH DOANH</label><select id="business-type-select"><option value="">Đang tải mô hình...</option></select><p id="business-type-note" class="muted-note">Chọn mô hình để xem trước mặt tiền phù hợp.</p>
<button id="buy" type="button">Thuê mặt bằng ngay</button><div id="msg" role="status"></div>
</aside>
<section id="customizer"><button id="customizer-close" type="button" aria-label="Đóng">×</button><div class="customizer-top"><div><div class="kicker">BƯỚC 2 · THIẾT KẾ CỬA HÀNG</div><h2 id="customizer-title">Biến mặt bằng thành cửa hàng của bạn</h2></div><span class="status-pill">Mặt bằng đã thuê</span></div>
<div class="customizer-grid"><div class="customizer-art"><div id="customizer-scene" class="scene"><div class="shop-front" id="shop-front"><div class="awning" id="awning"></div><div id="shop-sign" class="shop-sign">CỬA HÀNG CỦA BẠN</div><div class="shop-window">☕　✿　🛍️</div><div class="shop-door"></div><div class="plants" id="plants">🌿　🪴</div></div><div id="inside-preview" class="inside-preview"><b>KHÔNG GIAN BÊN TRONG</b><div id="interior-items">🪑　☕　🪴</div><div class="counter"></div></div></div><p class="muted-note">Hình minh họa xem trước — có thể đổi màu, biển hiệu và cách bố trí.</p></div>
<div class="customizer-controls"><div class="step-tabs"><button class="step-tab active" data-step="outside" type="button">1. Mặt tiền</button><button class="step-tab" data-step="inside" type="button">2. Bên trong</button></div>
<div id="outside-controls" class="control-section"><label for="shop-name-custom">Tên trên biển hiệu</label><input id="shop-name-custom" maxlength="32" placeholder="Ví dụ: Moon Tea"><label>Màu mặt tiền</label><div class="swatches"><button type="button" class="swatch active" data-color="#a95c32" style="--swatch:#a95c32" aria-label="Nâu gỗ"></button><button type="button" class="swatch" data-color="#e9a7bd" style="--swatch:#e9a7bd" aria-label="Hồng"></button><button type="button" class="swatch" data-color="#74b9a5" style="--swatch:#74b9a5" aria-label="Xanh bạc hà"></button><button type="button" class="swatch" data-color="#f0c75e" style="--swatch:#f0c75e" aria-label="Vàng"></button><button type="button" class="swatch" data-color="#7899d4" style="--swatch:#7899d4" aria-label="Xanh dương"></button></div><label for="awning-color">Màu mái hiên</label><select id="awning-color"><option value="#f4c95d">Vàng kem</option><option value="#e76f51">Cam san hô</option><option value="#8ecae6">Xanh da trời</option><option value="#d8a7ca">Hồng pastel</option><option value="#ffffff">Trắng</option></select><label for="decor-choice">Trang trí</label><select id="decor-choice"><option value="plants">Cây xanh</option><option value="lights">Đèn trang trí</option><option value="flowers">Chậu hoa</option><option value="none">Tối giản</option></select><button id="to-inside" class="primary-btn" type="button">Tiếp theo: Bên trong →</button></div>
<div id="inside-controls" class="control-section hidden"><label for="interior-layout">Phong cách bố trí</label><select id="interior-layout"><option value="cozy">Ấm cúng</option><option value="modern">Hiện đại</option><option value="minimal">Tối giản</option></select><label for="interior-items-select">Đồ nội thất</label><select id="interior-items-select"><option value="cafe">Bàn ghế & quầy</option><option value="retail">Kệ hàng</option><option value="flowers">Kệ hoa</option><option value="food">Quầy đồ ăn</option></select><p class="interior-note">Bản xem trước nội thất; hiện chưa tính phí đồ đạc.</p><button id="save-design" class="primary-btn" type="button">Lưu thiết kế cửa hàng</button></div><div id="customizer-message" role="status"></div></div></div></section>
<script>
const W = 2440, H = 1240, R = 12, SPEED = 260;
const rows = [
  { y: 170, h: 170, face: 'down', letter: 'A' },
  { y: 460, h: 150, face: 'up', letter: 'B' },
  { y: 630, h: 150, face: 'down', letter: 'C' },
  { y: 900, h: 170, face: 'up', letter: 'D' }
];
const xs = [];
for (let i = 0; i < 6; i++) xs.push(40 + i * 190);
for (let i = 0; i < 6; i++) xs.push(1280 + i * 190);
const labels = { available: 'Còn trống', owned: 'Đã có chủ', mine: 'Của bạn' };
let businessTypes = [
{id:1,name:'Trà sữa & đồ uống',description:'Pha chế đồ uống',required_capital:12000000},
{id:2,name:'Quán ăn nhỏ',description:'Món ăn nhanh và đồ ăn vặt',required_capital:15000000},
{id:3,name:'Tiệm bánh',description:'Bánh ngọt và bánh mì',required_capital:10000000},
{id:4,name:'Shop quần áo',description:'Thời trang và phụ kiện',required_capital:18000000},
{id:5,name:'Tiệm hoa',description:'Hoa tươi và quà tặng',required_capital:8000000},
{id:6,name:'Tạp hóa nhỏ',description:'Hàng hóa thiết yếu',required_capital:14000000}];
let selectedBusinessType=null, facadeColor='#a95c32';

let seed = 20260117;
function rnd() {
  seed = (seed + 0x6D2B79F5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
}

const lots = [];
rows.forEach(r => xs.forEach((x, c) => {
  const traffic = 20 + Math.floor(rnd() * 76);
  const area = 30 + Math.floor(rnd() * 41);
  const price = Math.round((traffic * 220000 + area * 180000) / 100000) * 100000;
  const status = rnd() < 0.25 ? 'owned' : 'available';
  lots.push({ code: r.letter + (c + 1), x, y: r.y, w: 170, h: r.h, face: r.face, traffic, area, price, status });
 }));
const savedDesignsAtLoad = JSON.parse(localStorage.getItem('startupStreetDesigns') || '{}');
lots.forEach(l => { if (savedDesignsAtLoad[l.code] && savedDesignsAtLoad[l.code].rented) l.status = 'mine'; });

const fmt = n => n.toLocaleString('vi-VN') + 'đ';
let balance = Number(localStorage.getItem('startupStreetBalance') || 50000000);

function doorPoint(l) {
  return { x: l.x + l.w / 2, y: l.face === 'down' ? l.y + l.h + 20 : l.y - 20 };
}

function lotSvg(l, i) {
  const roofY = l.face === 'down' ? l.y : l.y + l.h - 26;
  const doorY = l.face === 'down' ? l.y + l.h - 34 : l.y;
  const winY = l.y + l.h / 2 - 12;
  return `<g class="lot ${l.status}" data-i="${i}">
<rect class="body" x="${l.x}" y="${l.y}" width="${l.w}" height="${l.h}" rx="8"/>
<rect class="roof" x="${l.x}" y="${roofY}" width="${l.w}" height="26" rx="4"/>
<rect class="door" x="${l.x + l.w / 2 - 14}" y="${doorY}" width="28" height="34" rx="3"/>
<rect class="win" x="${l.x + 22}" y="${winY}" width="30" height="24" rx="3"/>
<rect class="win" x="${l.x + l.w - 52}" y="${winY}" width="30" height="24" rx="3"/>
<text x="${l.x + l.w / 2}" y="${winY + 8}">${l.code}</text>
</g>`;
}

let trees = '';
for (let i = 0; i < 70; i++) {
  const top = i % 2 === 0;
  const tx = 20 + rnd() * (W - 40);
  const ty = top ? 20 + rnd() * 120 : 1100 + rnd() * 120;
  trees += `<circle class="tree" cx="${tx.toFixed(0)}" cy="${ty.toFixed(0)}" r="${10 + Math.floor(rnd() * 6)}"/>`;
}

const world = document.getElementById('world');
world.innerHTML = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
<rect class="grass" width="${W}" height="${H}"/>
<rect class="walk" x="0" y="350" width="${W}" height="100"/>
<rect class="walk" x="0" y="790" width="${W}" height="100"/>
<rect class="walk" x="1170" y="0" width="100" height="${H}"/>
<rect class="road" x="0" y="360" width="${W}" height="80"/>
<rect class="road" x="0" y="800" width="${W}" height="80"/>
<rect class="road" x="1180" y="0" width="80" height="${H}"/>
<line class="dash" x1="0" y1="400" x2="${W}" y2="400"/>
<line class="dash" x1="0" y1="840" x2="${W}" y2="840"/>
<line class="dash" x1="1220" y1="0" x2="1220" y2="${H}"/>
${trees}
${lots.map(lotSvg).join('')}
<g id="player"><ellipse class="shadow" cx="0" cy="12" rx="13" ry="6"/><circle class="pbody" cx="0" cy="2" r="12"/><circle class="phead" cx="0" cy="-12" r="8"/></g>
</svg>`;

const els = world.querySelectorAll('.lot');
const playerEl = document.getElementById('player');
const hint = document.getElementById('hint');
const panel = document.getElementById('panel');
const view = document.getElementById('view');
const moneyEl = document.getElementById('money');

const player = { x: 1220, y: 400 };
const cam = { x: 0, y: 0 };
const keys = new Set();
let near = -1;
let open = -1;

const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
const blocked = (px, py) => lots.some(l => px + R > l.x && px - R < l.x + l.w && py + R > l.y && py - R < l.y + l.h);

function showPanel(i) {
  open = i;
  const l = lots[i];
  document.getElementById('pTitle').textContent = l.code + ' — Mặt bằng ' + l.code;
  document.getElementById('pPrice').textContent = fmt(l.price);
  document.getElementById('pArea').textContent = l.area + ' m²';
  document.getElementById('pTraffic').textContent = l.traffic + '/100';
  document.getElementById('pStatus').textContent = labels[l.status];
  document.getElementById('statusPill').textContent = labels[l.status];
  const typeSelect=document.getElementById('business-type-select');
  typeSelect.innerHTML='<option value="">-- Chọn mô hình kinh doanh --</option>'+businessTypes.map(t=>`<option value="${t.id}">${t.name}</option>`).join('');
  const saved=JSON.parse(localStorage.getItem('startupStreetDesigns')||'{}');
  if(saved[l.code]) typeSelect.value=String(saved[l.code].businessTypeId||'');
  selectedBusinessType=businessTypes.find(t=>String(t.id)===typeSelect.value)||null;
  document.getElementById('business-type-note').textContent=selectedBusinessType?selectedBusinessType.description:'Chọn mô hình để xem trước mặt tiền phù hợp.';
  updateFacadePreview();

  const buy = document.getElementById('buy');
  buy.disabled = l.status !== 'available';
  buy.textContent = l.status === 'available' ? 'Thuê mặt bằng ngay' : labels[l.status];
  document.getElementById('msg').textContent = '';
  panel.style.display = 'block';
}

function closePanel() {
  open = -1;
  panel.style.display = 'none';
}

function updateMoney() {
  moneyEl.textContent = fmt(balance);
}

document.getElementById('close').addEventListener('click', closePanel);

document.getElementById('buy').addEventListener('click', () => {
  if(open<0)return; const l=lots[open]; if(l.status!=='available')return;
  if(balance<l.price){document.getElementById('msg').textContent='Bạn không đủ vốn để thuê mặt bằng này.';return;}
  const typeId=document.getElementById('business-type-select').value;
  if(!typeId){document.getElementById('msg').textContent='Hãy chọn mô hình kinh doanh trước khi thuê.';return;}
  selectedBusinessType=businessTypes.find(t=>String(t.id)===String(typeId));
  balance-=l.price;localStorage.setItem('startupStreetBalance',String(balance));l.status='mine';els[open].setAttribute('class','lot mine'+(open===near?' near':''));
  updateMoney();
  const designs=JSON.parse(localStorage.getItem('startupStreetDesigns')||'{}');
  designs[l.code]={lotCode:l.code,propertyPrice:l.price,businessTypeId:selectedBusinessType.id,businessTypeName:selectedBusinessType.name,shopName:selectedBusinessType.name,facadeColor,awningColor:'#f4c95d',decor:'plants',interiorLayout:'cozy',interiorItems:'cafe',rented:true};
  localStorage.setItem('startupStreetDesigns',JSON.stringify(designs));
  document.getElementById('customizer-title').textContent='Thiết kế '+l.code+' — '+selectedBusinessType.name;
  document.getElementById('shop-name-custom').value=selectedBusinessType.name;
  document.getElementById('customizer').style.display='block';
  document.getElementById('customizer').classList.remove('show-inside','lights','flowers','no-decor','modern','minimal');
  document.getElementById('customizer-message').textContent='Đã thuê mặt bằng '+l.code+'. Bắt đầu tùy chỉnh cửa hàng!';
  closePanel();updateFacadePreview();
});

world.addEventListener('click', ev => {
  const g = ev.target.closest('.lot');
  if (g) showPanel(Number(g.dataset.i));
});

window.addEventListener('keydown', ev => {
  const k = ev.key.toLowerCase();
  if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright', ' '].includes(k)) ev.preventDefault();
  if (k === 'e' && near >= 0) showPanel(near);
  if (k === 'escape') closePanel();
  keys.add(k);
});
window.addEventListener('keyup', ev => keys.delete(ev.key.toLowerCase()));
window.addEventListener('blur', () => keys.clear());

function step(dt) {
  let dx = 0, dy = 0;
  if (keys.has('a') || keys.has('arrowleft')) dx -= 1;
  if (keys.has('d') || keys.has('arrowright')) dx += 1;
  if (keys.has('w') || keys.has('arrowup')) dy -= 1;
  if (keys.has('s') || keys.has('arrowdown')) dy += 1;
  if (dx && dy) { dx *= Math.SQRT1_2; dy *= Math.SQRT1_2; }
  const nx = clamp(player.x + dx * SPEED * dt, R, W - R);
  if (!blocked(nx, player.y)) player.x = nx;
  const ny = clamp(player.y + dy * SPEED * dt, R, H - R);
  if (!blocked(player.x, ny)) player.y = ny;
}

function updateNear() {
  let best = -1, bestD = 70;
  lots.forEach((l, i) => {
    const p = doorPoint(l);
    const d = Math.hypot(player.x - p.x, player.y - p.y);
    if (d < bestD) { best = i; bestD = d; }
  });
  if (best !== near) {
    if (near >= 0) els[near].classList.remove('near');
    if (best >= 0) els[best].classList.add('near');
    near = best;
  }
  if (near >= 0) {
    hint.textContent = 'Nhấn E để xem mặt bằng ' + lots[near].code;
    hint.style.display = 'block';
  } else {
    hint.style.display = 'none';
  }
}

function follow(dt, snap) {
  const vw = view.clientWidth, vh = view.clientHeight;
  const tx = W <= vw ? (W - vw) / 2 : clamp(player.x - vw / 2, 0, W - vw);
  const ty = H <= vh ? (H - vh) / 2 : clamp(player.y - vh / 2, 0, H - vh);
  const f = snap ? 1 : Math.min(1, dt * 6);
  cam.x += (tx - cam.x) * f;
  cam.y += (ty - cam.y) * f;
  world.style.transform = `translate(${-cam.x}px, ${-cam.y}px)`;
}

let last = performance.now();
function frame(now) {
  const dt = Math.min(0.05, (now - last) / 1000);
  last = now;
  step(dt);
  playerEl.setAttribute('transform', `translate(${player.x.toFixed(1)} ${player.y.toFixed(1)})`);
  updateNear();
  follow(dt, false);
  requestAnimationFrame(frame);
}


function updateFacadePreview(){
 const type=selectedBusinessType?selectedBusinessType.name:'CỬA HÀNG CỦA BẠN';
 const svgSign=document.getElementById('facade-sign');if(svgSign)svgSign.textContent=type.toLocaleUpperCase('vi-VN').slice(0,24);
 const front=document.getElementById('shop-front');if(front)front.style.background=facadeColor;
}
document.getElementById('business-type-select').addEventListener('change',ev=>{
 selectedBusinessType=businessTypes.find(t=>String(t.id)===ev.target.value)||null;
 document.getElementById('business-type-note').textContent=selectedBusinessType?selectedBusinessType.description+' · Vốn tham khảo: '+fmt(Number(selectedBusinessType.required_capital||0)):'Chọn mô hình để xem trước mặt tiền phù hợp.';
 updateFacadePreview();
});
fetch('/api/business-types.php').then(r=>r.json()).then(data=>{
 if(data&&data.success&&data.data&&Array.isArray(data.data.business_types)&&data.data.business_types.length){
  businessTypes=data.data.business_types;
  if(open>=0){const sel=document.getElementById('business-type-select');const old=sel.value;sel.innerHTML='<option value="">-- Chọn mô hình kinh doanh --</option>'+businessTypes.map(t=>`<option value="${t.id}">${t.name}</option>`).join('');sel.value=old;}
 }
}).catch(()=>{});
function setCustomizerStep(step){
 const inside=step==='inside';
 document.getElementById('outside-controls').classList.toggle('hidden',inside);
 document.getElementById('inside-controls').classList.toggle('hidden',!inside);
 document.querySelectorAll('.step-tab').forEach(b=>b.classList.toggle('active',b.dataset.step===step));
 document.getElementById('customizer').classList.toggle('show-inside',inside);
}
document.querySelectorAll('.step-tab').forEach(b=>b.addEventListener('click',()=>setCustomizerStep(b.dataset.step)));
document.getElementById('to-inside').addEventListener('click',()=>setCustomizerStep('inside'));
document.querySelectorAll('.swatch').forEach(b=>b.addEventListener('click',()=>{
 document.querySelectorAll('.swatch').forEach(x=>x.classList.remove('active'));b.classList.add('active');facadeColor=b.dataset.color;updateFacadePreview();
}));
document.getElementById('shop-name-custom').addEventListener('input',ev=>document.getElementById('shop-sign').textContent=ev.target.value.toLocaleUpperCase('vi-VN')||'CỬA HÀNG CỦA BẠN');
document.getElementById('awning-color').addEventListener('change',ev=>document.getElementById('awning').style.background=ev.target.value);
document.getElementById('decor-choice').addEventListener('change',ev=>{
 const c=document.getElementById('customizer');c.classList.remove('lights','flowers','no-decor');if(ev.target.value!=='plants')c.classList.add(ev.target.value==='none'?'no-decor':ev.target.value);
});
document.getElementById('interior-layout').addEventListener('change',ev=>{
 const c=document.getElementById('customizer');c.classList.remove('modern','minimal');if(ev.target.value!=='cozy')c.classList.add(ev.target.value);
});
document.getElementById('interior-items-select').addEventListener('change',ev=>{
 const items={cafe:'🪑　☕　🪴',retail:'🛍️　👕　🛒',flowers:'💐　🌷　🪴',food:'🍜　🥤　🍳'};
 document.getElementById('interior-items').textContent=items[ev.target.value];
});
document.getElementById('save-design').addEventListener('click',()=>{
 const title=document.getElementById('customizer-title').textContent,match=title.match(/[A-D]\d+/),lotCode=match&&match[0];
 const designs=JSON.parse(localStorage.getItem('startupStreetDesigns')||'{}');
 if(lotCode&&designs[lotCode]){
  Object.assign(designs[lotCode],{shopName:document.getElementById('shop-name-custom').value.trim()||'Cửa hàng của bạn',facadeColor,awningColor:document.getElementById('awning-color').value,decor:document.getElementById('decor-choice').value,interiorLayout:document.getElementById('interior-layout').value,interiorItems:document.getElementById('interior-items-select').value,designSaved:true});
  localStorage.setItem('startupStreetDesigns',JSON.stringify(designs));
  document.getElementById('customizer-message').textContent='Đã lưu thiết kế trên trình duyệt. Bạn có thể quay lại phố để tiếp tục!';
  document.getElementById('customizer-title').textContent='Thiết kế '+lotCode+' — '+designs[lotCode].shopName;
 }
});
document.getElementById('customizer-close').addEventListener('click',()=>document.getElementById('customizer').style.display='none');

updateMoney();
playerEl.setAttribute('transform', `translate(${player.x} ${player.y})`);
follow(0, true);
requestAnimationFrame(frame);
</script>
</body>
</html>
