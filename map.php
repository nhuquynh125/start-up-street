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
</style>
</head>
<body>
<div class="hud"><span>Startup Street</span><span class="keys">WASD / phím mũi tên: di chuyển · E: xem mặt bằng</span><span>Vốn: <span id="money"></span></span></div>
<div id="view"><div id="world"></div></div>
<div id="hint"></div>
<aside id="panel">
<button id="close" type="button" aria-label="Đóng">×</button>
<h2 id="pTitle"></h2>
<dl>
<dt>Giá</dt><dd id="pPrice"></dd>
<dt>Diện tích</dt><dd id="pArea"></dd>
<dt>Lượng khách</dt><dd id="pTraffic"></dd>
<dt>Tình trạng</dt><dd id="pStatus"></dd>
</dl>
<button id="buy" type="button">Mua mặt bằng</button>
<div id="msg"></div>
</aside>
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

const fmt = n => n.toLocaleString('vi-VN') + 'đ';
let balance = 50000000;

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
  const buy = document.getElementById('buy');
  buy.disabled = l.status !== 'available';
  buy.textContent = l.status === 'available' ? 'Mua mặt bằng' : labels[l.status];
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
  if (open < 0) return;
  const l = lots[open];
  if (l.status !== 'available') return;
  if (balance < l.price) {
    document.getElementById('msg').textContent = 'Không đủ tiền';
    return;
  }
  balance -= l.price;
  l.status = 'mine';
  els[open].setAttribute('class', 'lot mine' + (open === near ? ' near' : ''));
  updateMoney();
  showPanel(open);
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

updateMoney();
playerEl.setAttribute('transform', `translate(${player.x} ${player.y})`);
follow(0, true);
requestAnimationFrame(frame);
</script>
</body>
</html>
