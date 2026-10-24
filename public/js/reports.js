// ==============================================================
// STARTUP STREET — Bảng "Hôm nay", Báo cáo doanh thu (ngày / tuần / tháng) và mục Tài chính
// Nạp SAU shop-live.js:  <script src="js/reports.js"></script>
//
// Số liệu báo cáo do server tính và lưu (api/get-reports.php); file này chỉ GỌI API rồi VẼ.
// Không có shop_id trên URL (chế độ demo) -> chỉ chạy đồng hồ + lợi nhuận hôm nay, không gọi API.
// ==============================================================
(function () {
  'use strict';

  const SHOP_ID = Number(new URLSearchParams(location.search).get('shop_id')) || null;
  const $ = id => document.getElementById(id);
  const money = n => (typeof formatMoney === 'function') ? formatMoney(n) : Number(n).toLocaleString('vi-VN') + 'đ';

  // ── 1. Đồng hồ thời gian thực ──
  function tickClock() {
    const el = $('today-clock');
    if (!el) return;
    const d = new Date();
    el.textContent = d.toLocaleTimeString('vi-VN', { hour12: false }) + ' · ' + d.toLocaleDateString('vi-VN');
  }
  setInterval(tickClock, 1000);
  document.addEventListener('DOMContentLoaded', tickClock);

  // ── 2. Lợi nhuận hôm nay = doanh thu - vốn mặt hàng (cập nhật mỗi lần game.js / shop-live.js vẽ lại "Hôm nay") ──
  function paintProfit() {
    const el = $('today-profit');
    if (!el || typeof GameState === 'undefined') return;
    const v = GameState.today.revenue - GameState.today.materialCost;
    el.textContent = (v > 0 ? '+' : '') + money(v);
    el.style.color = v >= 0 ? 'var(--accent-green)' : 'var(--accent-red)';
  }
  const baseUpdateToday = window.updateTodayStats;
  if (typeof baseUpdateToday === 'function') {
    window.updateTodayStats = function () { baseUpdateToday(); paintProfit(); };
  }

  // ── Từ đây trở xuống cần shop_id ──
  if (!SHOP_ID) {
    document.addEventListener('DOMContentLoaded', () => {
      const body = $('report-body');
      if (body) body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;opacity:.6">Mở trang với ?shop_id=… để xem báo cáo của một cửa hàng.</td></tr>';
    });
    return;
  }

  let data = null;
  let view = 'day';
  let loading = false;

  const NOTES = {
    day: 'Mỗi dòng là một ngày chơi (chỉ sang ngày mới khi bạn bấm Chốt ca). Hiển thị tối đa 60 ngày gần nhất. Lợi nhuận = doanh thu − vốn mặt hàng; lãi ròng = lợi nhuận − tiền thuê mặt bằng.',
    week: 'Tuần = 7 ngày chơi liên tiếp (Ngày 1–7, 8–14, …). Tính từ báo cáo ngày nên vẫn đủ số liệu dù nhật ký khách đã bị xóa sau 7 ngày.',
    month: 'Tháng = 30 ngày chơi (Ngày 1–30, 31–60, …). Tiền thuê mặt bằng được tự trừ vào ví khi bước sang tháng mới và hiện ở cột Tiền thuê MB.',
  };
  const COLS = ['Kỳ', 'Đơn bán', 'Đơn lỡ', 'Doanh thu', 'Vốn mặt hàng', 'Lợi nhuận', 'Tiền thuê MB', 'Lãi ròng'];

  const fmtTime = ts => ts ? new Date(ts * 1000).toLocaleString('vi-VN') : '';

  function labelOf(r) {
    if (view === 'day') return 'Ngày ' + r.day;
    if (view === 'week') return 'Tuần ' + r.period + ' (Ngày ' + r.from_day + '–' + r.to_day + ')';
    return 'Tháng ' + r.period + ' (Ngày ' + r.from_day + '–' + r.to_day + ')';
  }
  function subOf(r) {
    if (view === 'day') return r.open ? 'đang diễn ra' : 'chốt lúc ' + fmtTime(r.closed_ts);
    const size = view === 'week' ? data.week_days : data.month_days;
    return r.days + '/' + size + ' ngày' + (r.open ? ' · đang diễn ra' : '');
  }

  function cell(content, cls) {
    const td = document.createElement('td');
    if (cls) td.className = cls;
    if (content instanceof Node) td.appendChild(content); else td.textContent = content;
    return td;
  }
  function signed(n) {
    const s = document.createElement('span');
    s.className = n >= 0 ? 'pos' : 'neg';
    s.textContent = (n > 0 ? '+' : '') + money(n);
    return s;
  }

  function renderReport() {
    const head = $('report-head'), body = $('report-body'), note = $('report-note');
    if (!head || !body) return;
    if (note) note.textContent = NOTES[view];
    document.querySelectorAll('.rv-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));

    head.innerHTML = '';
    const hr = document.createElement('tr');
    COLS.forEach((c, i) => {
      const th = document.createElement('th');
      th.textContent = c;
      if (i) th.className = 'num';
      hr.appendChild(th);
    });
    head.appendChild(hr);

    body.innerHTML = '';
    if (!data) {
      body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;opacity:.6">Đang tải...</td></tr>';
      return;
    }
    const list = (view === 'day' ? data.days : view === 'week' ? data.weeks : data.months).slice().reverse(); // mới nhất trên cùng
    if (!list.length) {
      body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;opacity:.6">Chưa có số liệu.</td></tr>';
      return;
    }
    list.forEach(r => {
      const tr = document.createElement('tr');
      if (r.open) tr.className = 'open-row';

      const first = document.createElement('div');
      const b = document.createElement('strong');
      b.textContent = labelOf(r);
      const sub = document.createElement('div');
      sub.className = 'sub';
      sub.textContent = subOf(r);
      first.append(b, sub);

      tr.append(
        cell(first),
        cell(String(r.served), 'num'),
        cell(String(r.missed), 'num'),
        cell(money(r.revenue), 'num'),
        cell(money(r.cost), 'num'),
        cell(signed(r.profit), 'num'),
        cell(r.rent > 0 ? money(r.rent) : '—', 'num'),
        cell(signed(r.net), 'num')
      );
      body.appendChild(tr);
    });
  }

  // ── Mục Tài chính: dựa trên báo cáo doanh thu ──
  const setText = (id, text) => { const el = $(id); if (el) el.textContent = text; };

  function renderFinance() {
    if (!data) return;
    const t = data.totals;
    setText('dash-total-revenue', money(t.revenue));
    setText('dash-revenue-margin', t.served + ' đơn đã bán (' + t.missed + ' đơn lỡ)');
    setText('dash-total-cost', money(t.cost));
    setText('dash-total-rent', money(t.rent));
    setText('dash-total-expenses', money(t.cost + t.rent));
    setText('dash-net-profit', (t.net > 0 ? '+' : '') + money(t.net));
    setText('dash-current-balance', data.balance === null ? '…' : money(data.balance));

    const margin = t.revenue > 0 ? (t.net / t.revenue * 100).toFixed(1) : '0.0';
    const mEl = $('dash-margin-rate');
    if (mEl) {
      mEl.textContent = 'Biên lãi ròng: ' + margin + '%';
      mEl.className = 'stat-change ' + (t.net >= 0 ? 'up' : 'down');
    }

    // Biểu đồ doanh thu vs chi phí theo ngày (30 ngày gần nhất) và cơ cấu chi phí. Biểu đồ do game.js (initCharts) tạo.
    if (typeof trendChart !== 'undefined' && trendChart) {
      const rows = data.days.slice(-30);
      trendChart.data.labels = rows.map(r => 'Ngày ' + r.day);
      trendChart.data.datasets[0].data = rows.map(r => r.revenue);
      trendChart.data.datasets[1].data = rows.map(r => r.cost + r.rent);
      trendChart.update();
    }
    if (typeof donutChart !== 'undefined' && donutChart) {
      donutChart.data.labels = ['Vốn mặt hàng', 'Tiền thuê mặt bằng'];
      donutChart.data.datasets[0].data = [t.cost, t.rent];
      donutChart.data.datasets[0].backgroundColor = ['#D47B6C', '#B094C8'];
      donutChart.update();
    }
  }

  async function load() {
    if (loading) return;
    loading = true;
    try {
      const res = await fetch('api/get-reports.php?shop_id=' + SHOP_ID);
      if (res.status === 401) { location.href = 'login.html'; return; }
      const json = await res.json().catch(() => null);
      if (!json) throw new Error('Server không trả về JSON (HTTP ' + res.status + ')');
      if (!json.success) throw new Error(json.message || 'Lỗi API');
      data = json.data;
      renderReport();
      renderFinance();
    } catch (e) {
      const body = $('report-body');
      if (body && !data) body.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--accent-red);font-weight:700"></td></tr>';
      if (body && !data) body.querySelector('td').textContent = '❌ ' + e.message;
      else if (typeof showToast === 'function') showToast('❌', e.message);
    } finally {
      loading = false;
    }
  }
  window.StartupReports = { reload: load };

  // ── Gắn sự kiện ──
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.rv-btn').forEach(b => b.addEventListener('click', () => { view = b.dataset.view; renderReport(); }));
    renderReport();
    load();
  });

  // Mở tab Báo cáo / Tài chính thì lấy số liệu mới nhất
  const baseSwitch = window.switchTab;
  if (typeof baseSwitch === 'function') {
    window.switchTab = function (name, ev) {
      baseSwitch(name, ev);
      if (name === 'report' || name === 'finance') load();
    };
  }
  // Đang xem Báo cáo / Tài chính thì tự làm mới mỗi 15 giây (ngày đang diễn ra thay đổi theo khách)
  setInterval(() => {
    const active = document.querySelector('.tab-panel.active');
    if (!document.hidden && active && (active.id === 'tab-report' || active.id === 'tab-finance')) load();
  }, 15000);
})();
