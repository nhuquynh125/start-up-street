// ==============================================================
// STARTUP STREET — Quầy pha chế (mini-game vận hành cửa hàng trà sữa)
// Mở bằng: shop-game.html?shop_id=<id cửa hàng>
//
// Quy tắc: trình duyệt chỉ lo phần CHƠI (đơn của khách, pha chế, đếm giờ).
// Tiền và tồn kho thật nằm ở MySQL: mỗi ly phục vụ đúng đơn gọi sell-product.php,
// server lấy giá từ inventory.selling_price, trừ kho, cộng users.balance.
// ==============================================================
(function () {
  'use strict';

  // ───────────── Cấu hình ─────────────
  const API = 'api/';
  const SHIFT_SEC = 150;      // độ dài một ca
  const PATIENCE_SEC = 40;    // thời gian khách chịu chờ
  const MAX_QUEUE = 3;        // số khách tối đa xếp hàng
  const SPAWN_MIN = 5, SPAWN_MAX = 9; // giây giữa hai khách
  const REP_WRONG = 3, REP_LATE = 4, REP_OK = 1;
  const SHOP_ID = Number(new URLSearchParams(location.search).get('shop_id')) || null;

  // ───────────── Dữ liệu công thức (khớp tên món trong migration 02) ─────────────
  const BASES = {
    trasua: { label: '🧋 Trà sữa', color: '#c8a96e' },
    luctra: { label: '🍃 Lục trà', color: '#b9d77a' },
    matcha: { label: '🍵 Matcha',  color: '#8db580' },
    caphe:  { label: '☕ Cà phê',  color: '#6b4226' },
  };
  const TOPS = {
    tranchau: { label: '⚫ Trân châu', emoji: '⚫⚫⚫' },
    dao:      { label: '🍑 Đào miếng', emoji: '🍑🍑' },
    foam:     { label: '☁️ Kem foam',  emoji: '☁️' },
    none:     { label: '∅ Không',      emoji: '' },
  };
  const RECIPES = {
    'Trà sữa trân châu': { base: 'trasua', top: 'tranchau', emoji: '🧋' },
    'Trà đào cam sả':    { base: 'luctra', top: 'dao',      emoji: '🍑' },
    'Matcha đá xay':     { base: 'matcha', top: 'foam',     emoji: '🍵' },
    'Cà phê sữa đá':     { base: 'caphe',  top: 'none',     emoji: '☕' },
  };
  const SUGARS = [0, 50, 100];
  const ICES = ['ít đá', 'đá thường'];
  const SIZES = { M: 'Ly M', L: 'Ly L' };
  const SIZE_MULT = { M: 1, L: 1.3 }; // CHỈ để hiển thị giá ước tính; giá thật do sell-product.php tính

  // ───────────── Trạng thái ─────────────
  const state = {
    products: [],      // [{dbId, name, stock, price}] chỉ món có công thức
    balance: null,     // bản sao số dư server, chỉ để hiển thị
    rep: 100,
    queue: [],         // khách đang chờ
    selectedId: null,
    cup: emptyCup(),
    running: false,
    timeLeft: 0,
    busy: false,       // đang chờ server trả lời một đơn
    nextCustomerId: 1,
    spawnIn: 0,
    stats: newStats(),
  };
  function emptyCup() { return { size: null, base: null, top: null, sugar: null, ice: null }; }
  function newStats() { return { served: 0, wrong: 0, lost: 0, revenue: 0, cogs: 0, fixedCost: 0 }; }

  const $ = id => document.getElementById(id);
  const fmt = n => Number(n).toLocaleString('vi-VN') + 'đ';

  // ───────────── Gọi API ─────────────
  async function api(path, opt) {
    let res;
    try { res = await fetch(API + path, opt); }
    catch (e) { throw new Error('Không kết nối được server'); }
    if (res.status === 401) { location.href = 'login.html'; throw new Error('Bạn chưa đăng nhập'); }
    const json = await res.json().catch(() => null);
    if (!json) throw new Error('Server không trả về JSON (HTTP ' + res.status + ')');
    if (!json.success) {
      const err = new Error(json.message || 'Lỗi API');
      err.status = res.status;
      err.data = json.data;
      throw err;
    }
    return json;
  }

  async function loadShop() {
    const d = (await api('get-shop-products.php?shop_id=' + SHOP_ID)).data;
    $('shopName').textContent = d.shop.name;
    state.balance = d.balance === null ? null : Number(d.balance);
    state.products = d.products
      .filter(p => RECIPES[p.product_name])
      .map(p => ({
        dbId: Number(p.product_id),
        name: p.product_name,
        stock: Number(p.stock_quantity),
        price: Number(p.selling_price),
        cost: Number(p.base_cost),
      }));
    $('restock').href = 'business-management.html?shop_id=' + SHOP_ID;
  }

  // ───────────── Vẽ giao diện ─────────────
  function buildOptions(containerId, items, onPick) {
    const box = $(containerId);
    box.innerHTML = '';
    items.forEach(([key, label]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'opt';
      b.dataset.key = key;
      b.setAttribute('aria-pressed', 'false');
      b.textContent = label;
      b.addEventListener('click', () => onPick(key));
      box.appendChild(b);
    });
  }

  function markPressed(containerId, key) {
    $(containerId).querySelectorAll('.opt').forEach(b =>
      b.setAttribute('aria-pressed', String(b.dataset.key === String(key))));
  }

  function renderCup() {
    const c = state.cup;
    markPressed('sizes', c.size);
    markPressed('bases', c.base);
    markPressed('tops', c.top);
    markPressed('sugars', c.sugar);
    markPressed('ices', c.ice);

    $('liquid').parentElement.style.height = c.size === 'L' ? '112px' : '96px';
    const liquid = $('liquid');
    liquid.style.height = c.base ? (c.top ? '72%' : '55%') : '0';
    liquid.style.background = c.base ? BASES[c.base].color : '#ddd';
    $('topEmoji').textContent = c.top ? TOPS[c.top].emoji : '';

    const parts = [];
    if (c.size) parts.push(SIZES[c.size]);
    if (c.base) parts.push(BASES[c.base].label);
    if (c.top && c.top !== 'none') parts.push(TOPS[c.top].label);
    if (c.sugar !== null) parts.push(c.sugar + '% đường');
    if (c.ice !== null) parts.push(ICES[c.ice]);
    $('cupText').textContent = parts.length ? parts.join(' · ') : 'Ly trống';
  }

  function renderQueue() {
    const box = $('queue');
    if (!state.queue.length) {
      box.innerHTML = '<div class="empty">' + (state.running ? 'Đang chờ khách…' : 'Chưa có khách') + '</div>';
      return;
    }
    box.innerHTML = '';
    state.queue.forEach(c => {
      const t = document.createElement('div');
      t.className = 'ticket' + (c.id === state.selectedId ? ' sel' : '');
      t.dataset.id = c.id;
      const who = document.createElement('div');
      who.className = 'who';
      who.textContent = c.avatar + ' Khách #' + c.id;
      const want = document.createElement('div');
      want.className = 'want';
      want.textContent = RECIPES[c.product.name].emoji + ' ' + c.product.name + ' (' + c.size + ') · ' + c.sugar + '% đường · ' + ICES[c.ice]
        + ' · ~' + fmt(c.product.price * SIZE_MULT[c.size]);
      const bar = document.createElement('div');
      bar.className = 'bar';
      bar.innerHTML = '<i></i>';
      t.append(who, want, bar);
      box.appendChild(t);
    });
    updatePatienceBars();
  }

  function updatePatienceBars() {
    $('queue').querySelectorAll('.ticket').forEach(t => {
      const c = state.queue.find(x => x.id === Number(t.dataset.id));
      if (!c) return;
      const pct = Math.max(0, c.patience / PATIENCE_SEC * 100);
      const bar = t.querySelector('.bar i');
      bar.style.width = pct + '%';
      bar.style.background = pct > 50 ? '#6fb23f' : pct > 25 ? '#e8a020' : '#d95b4f';
    });
  }

  function renderStocks() {
    const box = $('stocks');
    box.innerHTML = '';
    state.products.forEach(p => {
      const row = document.createElement('div');
      row.className = 'stock' + (p.stock <= 0 ? ' out' : '');
      const a = document.createElement('span');
      a.textContent = RECIPES[p.name].emoji + ' ' + p.name;
      const b = document.createElement('span');
      b.textContent = p.stock <= 0 ? 'HẾT' : p.stock;
      row.append(a, b);
      box.appendChild(row);
    });
  }

  function renderHud() {
    $('balance').textContent = state.balance === null ? '…' : fmt(state.balance);
    $('rep').textContent = state.rep;
    $('shiftRev').textContent = fmt(state.stats.revenue);
    const s = Math.max(0, Math.ceil(state.timeLeft));
    $('time').textContent = state.running ? Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') : '--';
    $('serve').disabled = !state.running || state.busy;
  }

  let toastTimer;
  function toast(msg) {
    const t = $('toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.style.display = 'none'; }, 2200);
  }

  function showNotice(msg) {
    const n = $('notice');
    n.textContent = msg;
    n.style.display = msg ? 'block' : 'none';
  }

  // ───────────── Khách hàng ─────────────
  const AVATARS = ['🧑', '👩', '👨', '🧒', '👵', '🧔'];
  const rand = (a, b) => a + Math.random() * (b - a);
  const pickOne = arr => arr[Math.floor(Math.random() * arr.length)];

  function spawnCustomer() {
    if (state.queue.length >= MAX_QUEUE) return;
    // Chỉ gọi món còn hàng, giống khách thật nhìn menu
    const available = state.products.filter(p => p.stock > 0);
    if (!available.length) return;
    state.queue.push({
      id: state.nextCustomerId++,
      avatar: pickOne(AVATARS),
      product: pickOne(available),
      sugar: pickOne(SUGARS),
      size: Math.random() < 0.3 ? 'L' : 'M',
      ice: Math.floor(Math.random() * ICES.length),
      patience: PATIENCE_SEC,
    });
    if (state.selectedId === null) state.selectedId = state.queue[0].id;
    renderQueue();
  }

  function removeCustomer(id) {
    state.queue = state.queue.filter(c => c.id !== id);
    if (state.selectedId === id) state.selectedId = state.queue.length ? state.queue[0].id : null;
    renderQueue();
  }

  function changeRep(delta) {
    state.rep = Math.max(0, Math.min(100, state.rep + delta));
  }

  // ───────────── Phục vụ ─────────────
  function cupMatches(c, cup) {
    const r = RECIPES[c.product.name];
    return cup.size === c.size && cup.base === r.base && cup.top === r.top && cup.sugar === c.sugar && cup.ice === c.ice;
  }

  async function serve() {
    if (!state.running || state.busy) return;
    const c = state.queue.find(x => x.id === state.selectedId);
    if (!c) { toast('Hãy chọn một khách để phục vụ.'); return; }
    const cup = state.cup;
    if (!cup.size || !cup.base || !cup.top || cup.sugar === null || cup.ice === null) {
      toast('Ly chưa xong: chọn đủ cỡ ly, trà, topping, độ ngọt và đá.');
      return;
    }

    // Làm sai đơn: khách bỏ đi, KHÔNG bán, không gọi server
    if (!cupMatches(c, cup)) {
      state.stats.wrong++;
      changeRep(-REP_WRONG);
      state.cup = emptyCup();
      removeCustomer(c.id);
      renderCup(); renderHud();
      toast('😠 Làm sai đơn! Khách bỏ đi.');
      return;
    }

    // Làm đúng: server trừ kho + cộng tiền theo giá trong database
    state.busy = true;
    renderHud();
    try {
      const r = (await api('sell-product.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ shop_id: SHOP_ID, product_id: c.product.dbId, quantity: 1, size: c.size }),
      })).data;
      c.product.stock = Number(r.stock_quantity);
      c.product.price = Number(r.unit_price);
      state.balance = Number(r.balance);
      state.stats.revenue += Number(r.revenue);
      state.stats.cogs += c.product.cost;
      state.stats.served++;
      changeRep(REP_OK);
      state.cup = emptyCup();
      removeCustomer(c.id);
      toast('+' + fmt(r.revenue) + ' 🎉');
    } catch (e) {
      if (e.status === 409 && e.data && 'available' in e.data) {
        c.product.stock = Number(e.data.available);
        state.stats.lost++;
        changeRep(-REP_LATE);
        removeCustomer(c.id);
        toast('📦 Hết nguyên liệu! Khách bỏ về.');
      } else {
        toast('❌ ' + e.message);
      }
    } finally {
      state.busy = false;
      renderCup(); renderStocks(); renderHud();
    }
  }

  // ───────────── Vòng lặp ca làm việc ─────────────
  const TICK_MS = 250;
  let timer = null;

  function tick() {
    if (!state.running) return;
    const dt = TICK_MS / 1000;
    state.timeLeft -= dt;

    // Khách mất kiên nhẫn
    state.queue.forEach(c => { c.patience -= dt; });
    const gone = state.queue.filter(c => c.patience <= 0);
    gone.forEach(c => {
      state.stats.lost++;
      changeRep(-REP_LATE);
      removeCustomer(c.id);
      toast('⌛ Khách chờ lâu quá và bỏ về.');
    });
    updatePatienceBars();

    state.spawnIn -= dt;
    if (state.spawnIn <= 0) {
      spawnCustomer();
      state.spawnIn = rand(SPAWN_MIN, SPAWN_MAX);
    }

    if (state.timeLeft <= 0) { endShift(); return; }
    renderHud();
  }

  async function startShift() {
    if (!state.products.length || state.busy) return;
    state.busy = true;
    $('newShift').disabled = true;
    let fee;
    try {
      // Server trừ phí nhân viên + điện nước trước khi cho mở ca
      fee = (await api('start-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ shop_id: SHOP_ID }),
      })).data;
    } catch (e) {
      $('ovBody').textContent = '❌ ' + e.message;
      return;
    } finally {
      state.busy = false;
      $('newShift').disabled = false;
    }
    state.balance = Number(fee.balance);
    showNotice('');
    state.running = true;
    state.timeLeft = SHIFT_SEC;
    state.queue = [];
    state.selectedId = null;
    state.cup = emptyCup();
    state.stats = newStats();
    state.stats.fixedCost = Number(fee.fixed_cost);
    state.spawnIn = 1;
    $('overlay').style.display = 'none';
    renderQueue(); renderCup(); renderStocks(); renderHud();
    clearInterval(timer);
    timer = setInterval(tick, TICK_MS);
  }

  function endShift() {
    state.running = false;
    clearInterval(timer);
    const s = state.stats;
    $('ovTitle').textContent = 'Hết ca!';
    $('newShift').textContent = '▶ Ca mới';
    const body = $('ovBody');
    body.innerHTML = '';
    [
      '✅ Đã phục vụ: ' + s.served + ' ly',
      '😠 Làm sai đơn: ' + s.wrong,
      '⌛ Khách bỏ về: ' + s.lost,
      '💵 Doanh thu: ' + fmt(s.revenue),
      '🧾 Giá vốn món đã bán: -' + fmt(s.cogs),
      '👥 Phí mở ca (nhân viên + điện nước): -' + fmt(s.fixedCost),
      '📈 Lợi nhuận ca: ' + fmt(s.revenue - s.cogs - s.fixedCost),
      '⭐ Uy tín: ' + state.rep,
    ].forEach(line => {
      const d = document.createElement('div');
      d.textContent = line;
      body.appendChild(d);
    });
    $('overlay').style.display = 'flex';
    renderQueue(); renderHud();
  }

  // ───────────── Khởi động ─────────────
  function bindControls() {
    buildOptions('bases', Object.entries(BASES).map(([k, v]) => [k, v.label]), k => { state.cup.base = k; renderCup(); });
    buildOptions('sizes', Object.entries(SIZES), k => { state.cup.size = k; renderCup(); });
    buildOptions('tops', Object.entries(TOPS).map(([k, v]) => [k, v.label]), k => { state.cup.top = k; renderCup(); });
    buildOptions('sugars', SUGARS.map(v => [v, v + '% đường']), k => { state.cup.sugar = Number(k); renderCup(); });
    buildOptions('ices', ICES.map((v, i) => [i, v]), k => { state.cup.ice = Number(k); renderCup(); });

    $('queue').addEventListener('click', ev => {
      const t = ev.target.closest('.ticket');
      if (!t) return;
      state.selectedId = Number(t.dataset.id);
      renderQueue();
    });
    $('serve').addEventListener('click', serve);
    $('trash').addEventListener('click', () => { state.cup = emptyCup(); renderCup(); });
    $('newShift').addEventListener('click', startShift);
  }

  async function init() {
    bindControls();
    renderCup(); renderQueue(); renderHud();

    if (!SHOP_ID) {
      showNotice('Thiếu shop_id trên URL. Mở: shop-game.html?shop_id=<id cửa hàng> (hoặc vào từ trang Kho hàng).');
      $('serve').disabled = true;
      return;
    }
    try {
      await loadShop();
    } catch (e) {
      showNotice('❌ ' + e.message);
      return;
    }
    renderStocks(); renderHud();

    if (!state.products.length) {
      showNotice('Mini-game hiện chỉ hỗ trợ cửa hàng "Trà sữa & đồ uống". Cửa hàng này chưa có món phù hợp.');
      return;
    }
    if (state.products.every(p => p.stock <= 0)) {
      showNotice('Kho đang trống. Hãy nhập hàng trước khi mở ca.');
    }
    $('ovTitle').textContent = 'Sẵn sàng mở quán?';
    $('ovBody').textContent = 'Khách gọi món theo cỡ ly, độ ngọt và đá. Mở ca tốn phí nhân viên + điện nước (trừ vào ví). Pha đúng đơn trước khi họ hết kiên nhẫn!';
    $('newShift').textContent = '▶ Mở cửa';
    $('overlay').style.display = 'flex';
  }

  document.addEventListener('DOMContentLoaded', init);
})();
