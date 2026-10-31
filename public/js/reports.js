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
      if (body) body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;opacity:.6">Mở trang với ?shop_id=… để xem báo cáo của một cửa hàng.</td></tr>';
    });
    return;
  }

  let data = null;
  let view = 'day';
  let loading = false;

  const NOTES = {
    day: 'Chỉ hiển thị các ngày đã kết thúc (sau khi bấm Chốt ca). Hiển thị tối đa 60 ngày gần nhất. Lợi nhuận = Doanh thu − Vốn mặt hàng.',
    week: 'Tuần = 7 ngày chơi liên tiếp (Ngày 1–7, 8–14, …). Chỉ hiển thị báo cáo sau khi đã kết thúc trọn vẹn 7 ngày chơi.',
    month: 'Tháng = 30 ngày chơi (Ngày 1–30, 31–60, …). Chỉ hiển thị báo cáo sau khi đã kết thúc trọn vẹn 30 ngày chơi. Cột Tiền thuê MB thể hiện tiền thuê mặt bằng được tính theo tháng.',
  };

  const getCols = () => (view === 'month')
    ? ['Kỳ', 'Đơn bán', 'Đơn lỡ', 'Doanh thu', 'Vốn mặt hàng', 'Lợi nhuận', 'Tiền thuê MB']
    : ['Kỳ', 'Đơn bán', 'Đơn lỡ', 'Doanh thu', 'Vốn mặt hàng', 'Lợi nhuận'];

  const fmtTime = ts => ts ? new Date(ts * 1000).toLocaleString('vi-VN') : '';

  function labelOf(r) {
    if (view === 'day') return 'Ngày ' + r.day;
    if (view === 'week') return 'Tuần ' + r.period + ' (Ngày ' + r.from_day + '–' + r.to_day + ')';
    return 'Tháng ' + r.period + ' (Ngày ' + r.from_day + '–' + r.to_day + ')';
  }
  function subOf(r) {
    if (view === 'day') return 'chốt lúc ' + fmtTime(r.closed_ts);
    const size = view === 'week' ? (data?.week_days || 7) : (data?.month_days || 30);
    return 'Hoàn thành ' + size + ' ngày';
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

  function getFilteredList() {
    if (!data) return [];
    if (view === 'day') {
      // Chỉ lấy các ngày đã kết thúc (chốt ca)
      return (data.days || []).filter(r => !r.open);
    }
    if (view === 'week') {
      // Chỉ lấy các tuần đã kết thúc trọn vẹn 7 ngày
      const size = data.week_days || 7;
      return (data.weeks || []).filter(r => !r.open && r.days === size);
    }
    if (view === 'month') {
      // Chỉ lấy các tháng đã kết thúc trọn vẹn 30 ngày
      const size = data.month_days || 30;
      return (data.months || []).filter(r => !r.open && r.days === size);
    }
    return [];
  }

  function renderReport() {
    const head = $('report-head'), body = $('report-body'), note = $('report-note');
    if (!head || !body) return;
    if (note) note.textContent = NOTES[view];
    document.querySelectorAll('.rv-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));

    const cols = getCols();
    head.innerHTML = '';
    const hr = document.createElement('tr');
    cols.forEach((c, i) => {
      const th = document.createElement('th');
      th.textContent = c;
      if (i) th.className = 'num';
      hr.appendChild(th);
    });
    head.appendChild(hr);

    body.innerHTML = '';
    if (!data) {
      body.innerHTML = '<tr><td colspan="' + cols.length + '" style="text-align:center;padding:20px;opacity:.6">Đang tải...</td></tr>';
      return;
    }
    const list = getFilteredList().slice().reverse(); // mới nhất trên cùng
    if (!list.length) {
      body.innerHTML = '<tr><td colspan="' + cols.length + '" style="text-align:center;padding:20px;opacity:.6">Chưa có số liệu.</td></tr>';
      return;
    }
    list.forEach(r => {
      const tr = document.createElement('tr');

      const first = document.createElement('div');
      const b = document.createElement('strong');
      b.textContent = labelOf(r);
      const sub = document.createElement('div');
      sub.className = 'sub';
      sub.textContent = subOf(r);
      first.append(b, sub);

      const cells = [
        cell(first),
        cell(String(r.served), 'num'),
        cell(String(r.missed), 'num'),
        cell(money(r.revenue), 'num'),
        cell(money(r.cost), 'num'),
        cell(signed(r.profit), 'num'),
      ];
      if (view === 'month') {
        cells.push(cell(r.rent > 0 ? money(r.rent) : '—', 'num'));
      }
      tr.append(...cells);
      body.appendChild(tr);
    });
  }

  // ── Mục Tài chính: dựa trên báo cáo doanh thu ──
  const setText = (id, text) => { const el = $(id); if (el) el.textContent = text; };

  function renderFinance() {
    if (!data) return;
    const t = data.totals || { served: 0, missed: 0, revenue: 0, cost: 0, profit: 0, rent: 0, net: 0 };
    const totalExpenses = (t.cost || 0) + (t.rent || 0);

    setText('dash-total-revenue', money(t.revenue));
    setText('dash-revenue-margin', t.served + ' đơn đã bán (' + t.missed + ' đơn lỡ)');
    setText('dash-total-cost', money(t.cost));
    setText('dash-total-rent', t.rent > 0 ? money(t.rent) : '—');
    setText('dash-total-expenses', money(totalExpenses));
    setText('dash-net-profit', (t.profit > 0 ? '+' : '') + money(t.profit));
    setText('dash-current-balance', data.balance === null ? '…' : money(data.balance));

    const mEl = $('dash-margin-rate');
    if (mEl) mEl.remove();

    // Biểu đồ Doanh thu vs Chi phí theo ngày (30 ngày gần nhất)
    if (typeof trendChart !== 'undefined' && trendChart) {
      const rows = (data.days || []).slice(-30);
      trendChart.data.labels = rows.map(r => 'Ngày ' + r.day);
      trendChart.data.datasets[0].data = rows.map(r => r.revenue);
      trendChart.data.datasets[1].data = rows.map(r => r.cost + r.rent);
      trendChart.update();
    }

    // Biểu đồ Cơ cấu chi phí
    if (typeof donutChart !== 'undefined' && donutChart) {
      if (t.rent > 0) {
        donutChart.data.labels = ['Vốn mặt hàng', 'Tiền thuê mặt bằng'];
        donutChart.data.datasets[0].data = [t.cost || 0, t.rent || 0];
        donutChart.data.datasets[0].backgroundColor = ['#D47B6C', '#B094C8'];
      } else {
        donutChart.data.labels = ['Vốn mặt hàng'];
        donutChart.data.datasets[0].data = [t.cost > 0 ? t.cost : 0];
        donutChart.data.datasets[0].backgroundColor = ['#D47B6C'];
      }
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
      if (body && !data) body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--accent-red);font-weight:700"></td></tr>';
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
