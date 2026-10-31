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
    if (window.StartupWallet && window.StartupWallet.setStock) {
      window.StartupWallet.setStock(s.stock);
    }
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

  // ── Hộp thoại chốt ca (thay cho confirm() của trình duyệt) ──
  // Dùng lại biến CSS (--paper, --wood...), class .dl-btn và keyframes slideIn có sẵn trong startup-street-ui.html
  function ensureDayModalCss() {
    if (document.getElementById('day-modal-css')) return;
    const s = document.createElement('style');
    s.id = 'day-modal-css';
    s.textContent = `
.dm-ov{position:fixed;inset:0;z-index:300;display:grid;place-items:center;padding:14px;background:#2a160a99}
.dm-box{width:min(420px,100%);background:var(--paper);border:5px solid var(--wood);border-radius:20px;
  box-shadow:0 6px 0 var(--wood-dark),0 16px 30px #0005;padding:18px;animation:slideIn .2s ease-out}
.dm-title{font-size:20px;font-weight:800;color:var(--wood-dark);margin-bottom:10px}
.dm-rows{background:var(--cream);border:3px solid var(--paper-dark);border-radius:12px;padding:6px 12px}
.dm-row{display:flex;justify-content:space-between;gap:10px;padding:4px 0;font-weight:600;font-size:14px}
.dm-row+.dm-row{border-top:2px dashed var(--paper-dark)}
.dm-profit{margin:12px 0 4px;text-align:center;border-radius:14px;padding:10px;border:4px solid var(--ok-dark);background:#e6f4d7}
.dm-profit.neg{border-color:var(--no-dark);background:#fde0dc}
.dm-profit small{display:block;font-weight:700;opacity:.75;font-size:13px}
.dm-profit b{font-size:30px;line-height:1.2;color:var(--ok-dark)}
.dm-profit.neg b{color:var(--no-dark)}
.dm-note{margin-top:8px;font-size:13px;font-weight:700;color:#7a4d00;background:#fff0c9;border-radius:10px;padding:6px 10px}
.dm-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:14px}`;
    document.head.appendChild(s);
  }

  /**
   * Hiện hộp thoại, trả về Promise<boolean> (true = bấm nút xác nhận).
   * o = { title, rows: [[nhãn, giá trị], ...], profit, profitLabel, note?, confirmText, cancelText (null = ẩn nút hủy) }
   */
  function showDayModal(o) {
    ensureDayModalCss();
    return new Promise(resolve => {
      const ov = document.createElement('div');
      ov.className = 'dm-ov';
      const box = document.createElement('div');
      box.className = 'dm-box';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');

      const title = document.createElement('div');
      title.className = 'dm-title';
      title.textContent = o.title;
      box.appendChild(title);

      const rows = document.createElement('div');
      rows.className = 'dm-rows';
      o.rows.forEach(([label, value]) => {
        const r = document.createElement('div');
        r.className = 'dm-row';
        const a = document.createElement('span'); a.textContent = label;
        const b = document.createElement('strong'); b.textContent = value;
        r.append(a, b);
        rows.appendChild(r);
      });
      box.appendChild(rows);

      const p = document.createElement('div');
      p.className = 'dm-profit' + (o.profit < 0 ? ' neg' : '');
      const pl = document.createElement('small'); pl.textContent = o.profitLabel;
      const pv = document.createElement('b');
      pv.textContent = (o.profit > 0 ? '+' : '') + formatMoney(o.profit);
      p.append(pl, pv);
      box.appendChild(p);

      if (o.note) {
        const n = document.createElement('div');
        n.className = 'dm-note';
        n.textContent = o.note;
        box.appendChild(n);
      }

      const act = document.createElement('div');
      act.className = 'dm-actions';
      const onKey = e => { if (e.key === 'Escape' && o.cancelText) done(false); };
      const done = v => { document.removeEventListener('keydown', onKey); ov.remove(); resolve(v); };
      if (o.cancelText) {
        const no = document.createElement('button');
        no.type = 'button'; no.className = 'dl-btn no'; no.textContent = o.cancelText;
        no.onclick = () => done(false);
        act.appendChild(no);
      }
      const yes = document.createElement('button');
      yes.type = 'button'; yes.className = 'dl-btn ok'; yes.textContent = o.confirmText;
      yes.onclick = () => done(true);
      act.appendChild(yes);
      box.appendChild(act);

      document.addEventListener('keydown', onKey);
      ov.addEventListener('click', e => { if (e.target === ov && o.cancelText) done(false); });

      ov.appendChild(box);
      document.body.appendChild(ov);
      yes.focus();
    });
  }

  // ── CHỐT CA: server lưu báo cáo ngày, sang ngày mới, tự trừ tiền thuê khi sang tháng mới ──
  // Ghi đè endDayAndSettle của game.js (bản gốc chỉ tính cục bộ nên tải lại trang là về Ngày 1).
  let endingDay = false;   // chặn bấm liên tiếp làm mở nhiều hộp thoại
  window.endDayAndSettle = async function () {
    if (gone || busy || endingDay) return;
    endingDay = true;
    try {
      // Bước 1: xem trước số liệu hôm nay (lấy từ lần tick gần nhất của server)
      const t = GameState.today;
      const willPayRent = nextRentDay === currentDay + 1;
      const ok = await showDayModal({
        title: '🌙 Chốt ca Ngày ' + currentDay,
        rows: [
          ['🛒 Đơn bán thành công', t.orders + ' đơn'],
          ['❌ Đơn lỡ (hết hàng)', t.missedOrders + ' đơn'],
          ['💵 Doanh thu', formatMoney(t.revenue)],
          ['🥤 Vốn mặt hàng', formatMoney(t.materialCost)],
        ],
        profit: t.revenue - t.materialCost,
        profitLabel: 'Lợi nhuận hôm nay',
        note: willPayRent ? '🏠 Sang Ngày ' + nextRentDay + ' hệ thống sẽ tự trừ tiền thuê mặt bằng tháng mới.' : '',
        confirmText: 'Chốt ca',
        cancelText: 'Để sau',
      });
      if (!ok || gone) return;

      // Bước 2: gọi server chốt ca
      let r = null;
      busy = true;
      try {
        r = await call('end-day.php', { shop_id: SHOP_ID, since_id: lastOrderId });
        handle(r.state);
        const c = r.closed;
        logRow(`🌙 Chốt ca Ngày ${c.day}: ${c.served} đơn, doanh thu ${formatMoney(c.revenue)}, vốn mặt hàng ${formatMoney(c.cost)}, lợi nhuận ${formatMoney(c.profit)}`, '', '', c.day);
        if (r.rent_charged > 0) {
          logRow(`🏠 Tiền thuê mặt bằng tháng mới (Ngày ${r.new_day})`, '-' + formatMoney(r.rent_charged), 'expense', r.new_day);
        }
        logRow(`☀️ Bắt đầu Ngày ${r.new_day}`, '', '', r.new_day);
        if (r.purged_orders > 0) pruneLog(r.new_day);
        if (window.StartupReports) window.StartupReports.reload();
      } catch (e) {
        showToast('❌', e.message);
      } finally {
        busy = false;
      }

      // Bước 3: hiện kết quả chính thức từ server
      if (r) {
        const c = r.closed;
        await showDayModal({
          title: '✅ Đã chốt Ngày ' + c.day,
          rows: [
            ['🛒 Đơn bán thành công', c.served + ' đơn'],
            ['❌ Đơn lỡ', c.missed + ' đơn'],
            ['💵 Doanh thu', formatMoney(c.revenue)],
            ['🥤 Vốn mặt hàng', formatMoney(c.cost)],
          ],
          profit: c.profit,
          profitLabel: 'Lợi nhuận Ngày ' + c.day,
          note: r.rent_charged > 0 ? '🏠 Đã trừ tiền thuê mặt bằng tháng mới: -' + formatMoney(r.rent_charged) : '',
          confirmText: 'Bắt đầu Ngày ' + r.new_day,
          cancelText: null,
        });
      }
    } finally {
      endingDay = false;
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
