// ==============================================================
// STARTUP STREET — Quán chạy "thật": khách tự động đến khi quán mở cửa
// Nạp SAU game.js và wallet-api.js:
//   <script src="js/game.js"></script><script src="js/wallet-api.js"></script><script src="js/shop-live.js"></script>
//
// Quy tắc: server (api/shop-open.php, api/shop-tick.php) quyết định có khách hay không, mua gì, bao nhiêu tiền.
// File này chỉ GỌI API rồi VẼ kết quả. Không có shop_id trên URL -> không làm gì (giữ chế độ demo cũ).
// ==============================================================
(function () {
  const SHOP_ID = Number(new URLSearchParams(location.search).get('shop_id')) || null;
  if (!SHOP_ID) return;

  const API = 'api/';
  const POLL_MS = 4000;     // hỏi server mỗi 4 giây khi quán đang mở
  const STAGGER_MS = 450;   // khách hiện lần lượt trong nhật ký cho có cảm giác "đang bước vào quán"

  let isOpen = false;
  let lastOrderId = 0;
  let pollTimer = null;
  let busy = false;
  let firstState = true;
  let lastError = '';
  let warnedEmpty = false;
  const feedQueue = [];
  let draining = false;

  // ── Gọi API (POST JSON) ──
  async function post(path, body) {
    let res;
    try {
      res = await fetch(API + path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
    } catch (e) { throw new Error('Không kết nối được server'); }
    if (res.status === 401) { location.href = 'login.html'; throw new Error('Bạn chưa đăng nhập'); }
    const json = await res.json().catch(() => null);
    if (!json) throw new Error('Server không trả về JSON (HTTP ' + res.status + ')');
    if (!json.success) {
      const err = new Error(json.message || 'Lỗi API');
      err.status = res.status;
      throw err;
    }
    return json.data;
  }

  // ── Vẽ nhật ký khách ──
  function renderOrder(o) {
    const p = Products.find(x => x.dbId === o.product_id);
    const emoji = p ? p.emoji : '🛍️';
    if (o.status === 'served') {
      addActivityFeed(`${emoji} ${o.customer} mua ${o.quantity}× ${o.product_name}`, '+' + formatMoney(o.total), 'income');
    } else {
      addActivityFeed(`😞 ${o.customer} muốn mua ${emoji} ${o.product_name} nhưng HẾT HÀNG, khách bỏ về`, '0đ', 'expense');
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

    // Số liệu "Hôm nay" lấy từ server (game.js dùng GameState.today để vẽ và chốt ca)
    GameState.today.orders = s.today.served;
    GameState.today.missedOrders = s.today.missed;
    GameState.today.revenue = s.today.revenue;
    GameState.reputation = Math.round(s.reputation);
    updateTodayStats();
    if (typeof window.updateHUD === 'function') window.updateHUD();

    const st = document.getElementById('live-status');
    if (st) {
      st.textContent = (s.is_open ? '🟢 Đang mở cửa' : '🔴 Đang đóng cửa')
        + ` · Uy tín ${s.reputation}★ · Hôm nay ${s.today.served} khách mua, ${s.today.missed} bỏ về`;
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
      addActivityFeed(`🚶 ${s.walked_by} người ghé qua nhưng chê giá cao nên không vào quán`);
    }

    applyState(s);
    if (s.is_open) startPolling(); else stopPolling();
  }

  // ── Vòng lặp: hỏi server xem có khách mới không ──
  async function tick() {
    if (busy) return;
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

  document.addEventListener('DOMContentLoaded', () => {
    injectUi();
    tick(); // lấy trạng thái ban đầu (nếu quán đang mở sẽ bù khách trong lúc vắng mặt)
  });
})();
