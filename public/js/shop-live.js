// ==============================================================
// STARTUP STREET — Quán chạy "thật": khách tự động đến khi quán mở cửa
// Nạp SAU game.js, wallet-api.js và shop-scene.js:
//   <script src="js/game.js"></script><script src="js/wallet-api.js"></script><script src="js/shop-scene.js"></script>
//   <script src="js/shop-live.js"></script><script src="js/reports.js"></script>
//
// Quy tắc: server (api/shop-open.php, shop-tick.php, end-day.php) quyết định có khách hay không, mua gì, bao nhiêu tiền,
// đang là Ngày mấy. File này chỉ GỌI API rồi VẼ kết quả. Không có shop_id trên URL -> không làm gì (giữ chế độ demo cũ).
//
// Nhật ký hoạt động: lúc mở trang nạp lại nhật ký đã lưu ở server (7 ngày chơi gần nhất, api/get-activity-log.php).
// Chốt ca: gọi api/end-day.php, server lưu báo cáo ngày, sang ngày mới, tự trừ tiền thuê khi sang tháng mới.
// ==============================================================
(function () {
  const SHOP_ID = Number(new URLSearchParams(location.search).get('shop_id')) || null;
  if (!SHOP_ID) return;

  const API = 'api/';
  const POLL_MS = 4000;     // hỏi server mỗi 4 giây khi quán đang mở
  const STAGGER_MS = 450;   // khách hiện lần lượt trong nhật ký cho có cảm giác "đang bước vào quán"
  const LOG_MAX = 600;      // số dòng tối đa trong khung nhật ký
  const KEEP_DAYS = 7;      // khớp ORDER_RETENTION_DAYS ở server

  let isOpen = false;
  let lastOrderId = 0;
  let pollTimer = null;
  let busy = false;
  let firstState = true;
  let lastError = '';
  let warnedEmpty = false;
  let gone = false;         // cửa hàng đã bị đóng vĩnh viễn / không còn tồn tại -> dừng mọi request
  let currentDay = 1;       // ngày chơi hiện tại (do server quyết định)
  let nextRentDay = null;   // ngày sẽ bị trừ tiền thuê mặt bằng tháng kế tiếp
  const feedQueue = [];
  let draining = false;

  // Cảnh quầy: cần <div id="shop-scene"> trong trang và js/shop-scene.js; thiếu một trong hai thì bỏ qua, nhật ký vẫn chạy.
  const sceneEl = document.getElementById('shop-scene');
  const scene = (window.ShopScene && sceneEl)
    ? ShopScene.mount(sceneEl, {
        emojiFor: id => { const p = Products.find(x => x.dbId === id); return p ? p.emoji : '🛍️'; }
      })
    : null;

  // ── Gọi API: có body -> POST JSON, không có -> GET ──
  async function call(path, body) {
    let res;
    try {
      res = await fetch(API + path, body === undefined ? undefined : {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
    } catch (e) { throw new Error('Không kết nối được server'); }
    if (res.status === 401) { location.href = 'login.html'; throw new Error('Bạn chưa đăng nhập'); }
    if (res.status === 404) {
      // Server không còn thấy cửa hàng (vd. vừa đóng vĩnh viễn ở tab khác): dừng hỏi và về bản đồ
      gone = true;
      stopPolling();
      setTimeout(() => { location.href = 'map.php'; }, 1500);
      throw new Error('Cửa hàng không còn tồn tại, đang về bản đồ…');
    }
    const json = await res.json().catch(() => null);
    if (!json) throw new Error('Server không trả về JSON (HTTP ' + res.status + ')');
    if (!json.success) {
      const err = new Error(json.message || 'Lỗi API');
      err.status = res.status;
      err.data = json.data;
      throw err;
    }
    return json.data;
  }
  const post = call;

  // ── Nhật ký hoạt động ──
  const hhmm = ts => new Date(ts * 1000).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

  /** Thêm 1 dòng lên ĐẦU nhật ký. day = ngày chơi của dòng (để xóa dòng quá 7 ngày khỏi màn hình), ts = giờ khách tới. */
  function logRow(text, amount, type, day, ts) {
    const feed = document.getElementById('activity-feed');
    if (!feed) return;
    if (feed.querySelector('.empty-state')) feed.innerHTML = '';

    const item = document.createElement('div');
    item.className = 'activity-item';
    item.dataset.day = String(day || currentDay);

    const when = document.createElement('span');
    when.className = 'act-time';
    when.style.minWidth = '92px';
    when.textContent = 'Ngày ' + (day || currentDay) + (ts ? ' · ' + hhmm(ts) : '');
    const what = document.createElement('span');
    what.className = 'act-text';
    what.textContent = text;
    item.append(when, what);
    if (amount) {
      const amt = document.createElement('span');
      amt.className = 'act-amount ' + (type || '');
      amt.textContent = amount;
      item.appendChild(amt);
    }
    feed.insertBefore(item, feed.firstChild);
    while (feed.children.length > LOG_MAX) feed.removeChild(feed.lastChild);
  }

  /** Bỏ khỏi màn hình các dòng thuộc ngày đã bị server xóa (giữ KEEP_DAYS ngày gần nhất). */
  function pruneLog(day) {
    const feed = document.getElementById('activity-feed');
    if (!feed) return;
    const minDay = day - KEEP_DAYS + 1;
    [...feed.children].forEach(el => {
      const d = Number(el.dataset.day);
      if (d && d < minDay) el.remove();
    });
  }

  function renderOrder(o) {
    const p = Products.find(x => x.dbId === o.product_id) || Products.find(x => x.name === o.product_name);
    const emoji = p ? p.emoji : '🛍️';
    if (o.status === 'served') {
      logRow(`${emoji} ${o.customer} mua ${o.quantity}× ${o.product_name}`, '+' + formatMoney(o.total), 'income', o.day_number, o.arrived_ts);
    } else {
      logRow(`😞 ${o.customer} muốn mua ${emoji} ${o.product_name} nhưng HẾT HÀNG, khách bỏ về`, '0đ', 'expense', o.day_number, o.arrived_ts);
    }
  }
  function drain() {
    if (draining) return;
    draining = true;
    (function next() {
      const o = feedQueue.shift();
      if (!o) { draining = false; return; }
      renderOrder(o);
      setTimeout(next, STAGGER_MS);
    })();
  }
  function showOrders(orders, instant) {
    if (instant) { orders.forEach(renderOrder); return; }
    feedQueue.push(...orders);
    drain();
  }

  /** Nạp nhật ký đã lưu ở server (7 ngày chơi gần nhất) trước khi bắt đầu tick. */
  async function loadSavedLog() {
    try {
      const d = await call('get-activity-log.php?shop_id=' + SHOP_ID);
      d.orders.forEach(renderOrder); // server trả cũ -> mới nên đơn mới nhất nằm trên cùng
      if (d.orders.length) lastOrderId = Math.max(lastOrderId, ...d.orders.map(o => o.id));
      currentDay = d.current_day;
    } catch (e) {
      if (!gone) showToast('❌', 'Không tải được nhật ký đã lưu: ' + e.message);
    }
  }

  // ── Áp trạng thái server lên giao diện ──
  function applyState(s) {
    isOpen = s.is_open;
    updateToggle();

    if (window.StartupWallet) window.StartupWallet.setBalance(s.balance);

    Object.entries(s.stock || {}).forEach(([pid, qty]) => {
      const p = Products.find(x => x.dbId === Number(pid));
      if (p) p.stock = qty;
    });
    updateInventoryTable();

    // Ngày chơi + số liệu "Hôm nay" lấy từ server (game.js dùng GameState.today để vẽ)
    currentDay = s.day;
    nextRentDay = s.next_rent_day;
    GameState.day = s.day;
    GameState.today.orders = s.today.served;
    GameState.today.missedOrders = s.today.missed;
    GameState.today.revenue = s.today.revenue;
    GameState.today.materialCost = s.today.cost;      // vốn mặt hàng = giá vốn của hàng đã bán
    GameState.reputation = Math.round(s.reputation);
    const dayLabel = document.getElementById('today-day-label');
    if (dayLabel) dayLabel.textContent = s.day;
    updateTodayStats();
    if (typeof window.updateHUD === 'function') window.updateHUD();

    const st = document.getElementById('live-status');
    if (st) {
      st.textContent = (s.is_open ? '🟢 Đang mở cửa' : '🔴 Đang đóng cửa')
        + ` · Ngày ${s.day} · Uy tín ${s.reputation}★ · Hôm nay ${s.today.served} khách mua, ${s.today.missed} bỏ về`
        + ` · Trừ tiền thuê kỳ tới: Ngày ${s.next_rent_day}`;
    }

    // Cảnh báo 1 lần khi hết sạch hàng lúc đang mở
    const totalStock = Object.values(s.stock || {}).reduce((a, b) => a + b, 0);
    if (s.is_open && totalStock === 0) {
      if (!warnedEmpty) { warnedEmpty = true; showToast('⚠️', 'Quán đã hết hàng, khách đang bỏ về! Hãy nhập hàng.'); }
    } else {
      warnedEmpty = false;
    }
  }

  function handle(s) {
    const fresh = s.orders.filter(o => o.id > lastOrderId);
    if (fresh.length) lastOrderId = Math.max(lastOrderId, ...fresh.map(o => o.id));

    if (scene) {
      scene.setOpen(s.is_open);
      scene.push(fresh, { silent: firstState });   // lần tải đầu: không dựng lại khách cũ
      if (!firstState) scene.walkBy(s.walked_by);
    }

    if (firstState) {
      showOrders(fresh, true);
      if (s.is_open && s.new_customers > 0) {
        showToast('🛎️', `Trong lúc bạn vắng mặt, quán đã đón ${s.new_customers} lượt khách` + (s.catchup_capped ? ' (tối đa 10 phút gần nhất)' : ''));
      }
      firstState = false;
    } else {
      showOrders(fresh, false);
    }
    if (s.walked_by > 0) {
      logRow(`🚶 ${s.walked_by} người ghé qua nhưng chê giá cao nên không vào quán`, '', '', s.day);
    }

    applyState(s);
    if (s.is_open) startPolling(); else stopPolling();
  }

  // ── Vòng lặp: hỏi server xem có khách mới không ──
  async function tick() {
    if (busy || gone) return;
    busy = true;
    try {
      handle(await post('shop-tick.php', { shop_id: SHOP_ID, since_id: lastOrderId }));
      lastError = '';
    } catch (e) {
      if (e.message !== lastError) { lastError = e.message; showToast('❌', e.message); }
    } finally {
      busy = false;
    }
  }
  function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(() => { if (!document.hidden) tick(); }, POLL_MS);
  }
  function stopPolling() {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  // Quay lại tab sau một lúc: lấy ngay khách đã đến trong thời gian vắng
  document.addEventListener('visibilitychange', () => { if (!document.hidden && isOpen) tick(); });

  // ── Nút mở / đóng cửa ──
  function updateToggle() {
    const btn = document.getElementById('btn-toggle-open');
    if (!btn) return;
    btn.className = isOpen ? 'end-day-btn' : 'rush-btn';
    btn.style.marginTop = '0';
    btn.style.marginBottom = '8px';
    btn.textContent = isOpen ? '🔒 Đóng cửa quán' : '🔓 Mở cửa quán';
  }
  async function toggleOpen() {
    const btn = document.getElementById('btn-toggle-open');
    btn.disabled = true;
    try {
      const s = await post('shop-open.php', { shop_id: SHOP_ID, open: !isOpen });
      handle(s);
      showToast(s.is_open ? '🔓' : '🔒', s.is_open ? 'Quán đã mở cửa! Khách sẽ bắt đầu ghé.' : 'Quán đã đóng cửa.');
    } catch (e) {
      showToast('❌', e.message);
    } finally {
      btn.disabled = false;
    }
  }

  // ── CHỐT CA: server lưu báo cáo ngày, sang ngày mới, tự trừ tiền thuê khi sang tháng mới ──
  // Ghi đè endDayAndSettle của game.js (bản gốc chỉ tính cục bộ nên tải lại trang là về Ngày 1).
  window.endDayAndSettle = async function () {
    if (gone || busy) return;
    const rentWarn = nextRentDay === currentDay + 1
      ? '\n• Sang Ngày ' + nextRentDay + ' hệ thống sẽ TỰ TRỪ tiền thuê mặt bằng tháng mới.'
      : '';
    if (!confirm('Chốt ca Ngày ' + currentDay + ' và sang Ngày ' + (currentDay + 1) + '?\n'
      + '• Báo cáo ngày được lưu lại.' + rentWarn)) return;

    busy = true;
    try {
      const r = await call('end-day.php', { shop_id: SHOP_ID, since_id: lastOrderId });
      handle(r.state);
      const c = r.closed;
      showToast(c.profit >= 0 ? '💰' : '📉', `Chốt Ngày ${c.day}: lợi nhuận ${formatMoney(c.profit)}`);
      logRow(`🌙 Chốt ca Ngày ${c.day}: ${c.served} đơn, doanh thu ${formatMoney(c.revenue)}, vốn mặt hàng ${formatMoney(c.cost)}, lợi nhuận ${formatMoney(c.profit)}`, '', '', c.day);
      if (r.rent_charged > 0) {
        logRow(`🏠 Tiền thuê mặt bằng tháng mới (Ngày ${r.new_day})`, '-' + formatMoney(r.rent_charged), 'expense', r.new_day);
        showToast('🏠', 'Đã trừ tiền thuê mặt bằng: -' + formatMoney(r.rent_charged));
      }
      logRow(`☀️ Bắt đầu Ngày ${r.new_day}`, '', '', r.new_day);
      if (r.purged_orders > 0) pruneLog(r.new_day);
      if (window.StartupReports) window.StartupReports.reload();
    } catch (e) {
      showToast('❌', e.message);
    } finally {
      busy = false;
    }
  };

  function injectUi() {
    // Chế độ thật: bỏ gọi món thủ công + giờ cao điểm giả lập (khách do server sinh ra)
    const orderBox = document.getElementById('order-buttons');
    const orderSection = orderBox && orderBox.closest('.sidebar-section');
    if (orderSection) orderSection.hidden = true;
    const rush = document.querySelector('.rush-btn');
    if (rush) rush.hidden = true;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'btn-toggle-open';
    btn.className = 'rush-btn';
    btn.textContent = '🔓 Mở cửa quán';
    btn.addEventListener('click', toggleOpen);

    const status = document.createElement('div');
    status.id = 'live-status';
    status.style.cssText = 'font-size:12px;font-weight:700;margin:0 0 8px;line-height:1.4';
    status.textContent = 'Đang tải trạng thái quán…';

    const restock = document.querySelector('.restock-btn');
    if (restock) {
      restock.parentNode.insertBefore(btn, restock);
      restock.parentNode.insertBefore(status, restock);
    }

    const hint = document.querySelector('#activity-feed .empty-state p');
    if (hint) hint.textContent = 'Mở cửa quán để khách bắt đầu ghé mua!';
  }

  document.addEventListener('DOMContentLoaded', async () => {
    injectUi();
    await loadSavedLog(); // nhật ký đã lưu hiện ra trước, rồi mới lấy trạng thái quán
    tick();               // quán đang mở sẽ bù khách trong lúc vắng mặt (chỉ các đơn MỚI hơn nhật ký đã nạp)
  });
})();
