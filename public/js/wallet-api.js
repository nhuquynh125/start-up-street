// ==============================================================
// STARTUP STREET — Cầu nối game.js (Member 4) với MySQL
// Nạp SAU game.js:  <script src="js/game.js"></script><script src="js/wallet-api.js"></script>
// Mở bằng:  startup-street-ui.html?shop_id=5  (session PHP xác thực người dùng, SHOP_ID lấy từ URL)
//
// Quy tắc: tiền + tồn kho thật nằm ở MySQL. Ở đây chỉ GỌI API rồi vẽ lại kết quả server trả về.
// Phần còn lại của game.js (feed, toast, feedback, báo cáo) giữ nguyên — ta bọc/ghi đè đúng các hàm đụng tới tiền.
//
// Hai chế độ:
//  - Có shop_id trên URL  -> chế độ THẬT: mọi đơn bán / nhập hàng đi qua API, server quyết định tiền và kho.
//  - Không có shop_id     -> chế độ DEMO: đơn chạy hoàn toàn cục bộ bằng game.js (không gọi API, không đụng MySQL,
//                            không đổi số dư thật). Chỉ để thử giao diện / mô phỏng.
// ==============================================================
(function () {
  const q = new URLSearchParams(location.search);
  const SHOP_ID = Number(q.get('shop_id')) || null;
  const API = 'api/';
  const MAX_STOCK = 40; // khớp mức "đầy kho" trong game.js

  let walletBalance = null; // bản sao số dư server, chỉ để hiển thị
  let demoWarned = false;

  // ── Gọi API: luôn trả { success, message, data } hoặc ném lỗi có status/data ──
  async function api(path, opt) {
    let res;
    try { res = await fetch(API + path, opt); }
    catch (e) { throw new Error('Không kết nối được server'); }
    const json = await res.json().catch(() => null);
    if (!json) throw new Error('Server không trả về JSON (HTTP ' + res.status + ')');
    if (!json.success) {
      const err = new Error(res.status === 401 ? 'Bạn chưa đăng nhập' : (json.message || 'Lỗi API'));
      err.status = res.status;
      err.data = json.data;
      throw err;
    }
    return json;
  }
  const post = body => ({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });

  // ── Số dư ──
  function paintBalance() {
    const text = walletBalance === null ? '…' : formatMoney(walletBalance);
    document.getElementById('hud-balance').textContent = text;
    const dash = document.getElementById('dash-current-balance');
    if (dash) dash.textContent = text;
  }
  async function syncBalance() {
    try {
      walletBalance = Number((await api('get-balance.php')).data.balance);
      GameState.balance = walletBalance; // bản sao cho các chỗ game.js còn đọc; KHÔNG phải nguồn sự thật
    } catch (e) { /* giữ số cũ */ }
    paintBalance();
  }

  // HUD đọc số dư server thay vì GameState.balance
  window.updateHUD = function () {
    paintBalance();
    document.getElementById('hud-day').textContent = GameState.day;
    document.getElementById('hud-reputation').textContent = GameState.reputation + '★';
  };

  // Nhắc 1 lần (lúc có đơn đầu tiên) rằng đang ở chế độ demo, tránh người chơi tưởng tiền được lưu
  function warnDemoOnce() {
    if (demoWarned) return;
    demoWarned = true;
    showToast('🧪', 'Chế độ demo: chưa có shop_id nên đơn chỉ chạy cục bộ, KHÔNG ghi vào MySQL.');
  }

  // ── Tab "Kho hàng": chỉ hiện kho THẬT của cửa hàng đang mở (SHOP_ID), không dùng menu mẫu cứng trong game.js ──
  // (game.js vẽ từ mảng Products; nếu quán không phải trà sữa / tên món lệch thì bảng sẽ hiện kho của "quán khác")
  let shopRows = null; // null = đang tải | false = lỗi | mảng = dòng kho từ get-shop-products.php

  const baseUpdateInventory = window.updateInventoryTable;
  window.updateInventoryTable = function () {
    baseUpdateInventory();          // game.js vẫn cập nhật các nút "Gọi món thủ công"
    if (!SHOP_ID) return;           // chế độ demo: giữ bảng mẫu như cũ

    const tbody = document.getElementById('inventory-table-body');
    if (!tbody) return;
    const msg = t => { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;opacity:.6">' + t + '</td></tr>'; };

    if (shopRows === null) return msg('Đang tải...');
    if (shopRows === false) return msg('Không tải được kho của cửa hàng này.');
    if (!shopRows.length) return msg('Cửa hàng này chưa có sản phẩm nào.');

    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    tbody.innerHTML = shopRows.map(sp => {
      // Món có trong menu game.js thì lấy số đang chạy trong phiên (bán / nhập sẽ cập nhật ở đó)
      const live = Products.find(p => p.dbId === Number(sp.product_id));
      const stock = live ? live.stock : Number(sp.stock_quantity);
      const cost  = live ? live.costPrice : Number(sp.base_cost);
      const price = live ? live.sellPrice : Number(sp.selling_price);
      const pct = Math.max(0, Math.min(100, stock / MAX_STOCK * 100));
      const cls = pct > 50 ? 'stock-high' : pct > 20 ? 'stock-medium' : 'stock-low';
      const out = stock <= 0;
      return '<tr>'
        + '<td><span class="product-emoji">' + (sp.emoji || (live ? live.emoji : '📦')) + '</span><strong>' + esc(sp.product_name) + '</strong>'
        + (out ? '<span style="color:var(--accent-red);font-size:0.75rem;font-weight:800;margin-left:6px;">[HẾT HÀNG]</span>' : '') + '</td>'
        + '<td>' + formatMoney(cost) + '</td>'
        + '<td>' + formatMoney(price) + '</td>'
        + '<td><span style="font-weight:700;color:' + (out ? 'var(--accent-red)' : 'inherit') + ';">' + stock + '</span> / ' + MAX_STOCK
        + '<div class="stock-bar"><div class="stock-bar-fill ' + cls + '" style="width:' + pct + '%"></div></div></td>'
        + '<td><strong>' + (live ? live.soldTotal : '—') + '</strong></td>'
        + '</tr>';
    }).join('');
  };

  // ── Nạp sản phẩm / tồn kho / giá từ MySQL (khớp theo tên món) ──
  async function loadShop() {
    if (!SHOP_ID) {
      showToast('⚠️', 'Thiếu shop_id trên URL nên đang ở chế độ demo. Mở: startup-street-ui.html?shop_id=<id cửa hàng>');
      paintBalance();
      return;
    }
    try {
      const d = (await api('get-shop-products.php?shop_id=' + SHOP_ID)).data;
      shopRows = d.products;
      d.products.forEach(sp => {
        const p = Products.find(x => x.name === sp.product_name);
        if (!p) return;
        p.dbId      = Number(sp.product_id);
        p.costPrice = Number(sp.base_cost);
        p.sellPrice = Number(sp.selling_price);
        p.stock     = Number(sp.stock_quantity);
      });
      updateInventoryTable();
    } catch (e) {
      showToast('❌', e.message);
      shopRows = false;
      updateInventoryTable();
    }
    await syncBalance();
  }
  document.addEventListener('DOMContentLoaded', loadShop);

  // ── BÁN HÀNG: server trừ kho + cộng users.balance (sell-product.php → deductShopInventoryAndAddBalance) ──
  // Bọc hàm gốc: gọi server TRƯỚC, thành công mới cho game.js ghi feed/feedback/ledger như cũ.
  const originalProcess = window.processIncomingOrder;

  // Chế độ thật (đã có SHOP_ID). Chế độ demo không đi qua hàm này.
  async function doSale(productId) {
    const product = Products.find(p => p.id === productId);
    if (!product) return false;
    if (!product.dbId) {
      showToast('⚠️', 'Món này chưa liên kết với kho trong MySQL.');
      return false;
    }
    try {
      const r = (await api('sell-product.php', post({
        shop_id: SHOP_ID, product_id: product.dbId, quantity: 1
      }))).data;

      // Ép số liệu cục bộ về đúng số server trả, để phần xử lý gốc cộng/trừ ra kết quả khớp:
      product.sellPrice = r.unit_price;
      product.stock = r.stock_quantity + 1;              // bản gốc sẽ stock-- → bằng r.stock_quantity
      walletBalance = r.balance;
      GameState.balance = r.balance - r.unit_price;      // bản gốc sẽ += sellPrice → bằng r.balance
      return originalProcess(productId);
    } catch (e) {
      if (e.status === 409 && e.data && 'available' in e.data) {
        product.stock = e.data.available;                // hết hàng thật → để bản gốc ghi nhận "đơn bị lỡ"
        return originalProcess(productId);
      }
      showToast('❌', e.message);
      return false;
    }
  }

  // Xếp hàng các đơn (giờ cao điểm bắn liên tục) để kết quả server được áp dụng đúng thứ tự
  let chain = Promise.resolve();
  window.processIncomingOrder = function (productId) {
    // Không có shop_id → chế độ demo: chạy thẳng logic cục bộ của game.js, không gọi API.
    if (!SHOP_ID) {
      warnDemoOnce();
      return originalProcess(productId);
    }
    const run = chain.then(() => doSale(productId));
    // Nếu 1 đơn ném lỗi bất ngờ, hàng đợi vẫn đi tiếp thay vì kẹt vĩnh viễn ở trạng thái rejected
    chain = run.catch(() => false);
    return run;
  };

  // ── NHẬP HÀNG: bổ sung đầy kho hàng loạt giống business-management.html (restock-bulk.php) ──
  let restockPlan = [];

  async function executeRestockPlan() {
    if (!restockPlan.length || !SHOP_ID) return;
    const dyes = document.getElementById('dlg-btn-confirm');
    const dlg = document.getElementById('dlg-restock');
    if (dyes) dyes.disabled = true;

    try {
      const items = restockPlan.map(x => ({
        product_id: x.id,
        quantity: x.quantity,
        selling_price: x.price > 0 ? x.price : undefined
      }));
      const r = (await api('restock-bulk.php', post({ shop_id: SHOP_ID, items }))).data;

      // Cập nhật số dư ví
      walletBalance = r.balance;
      GameState.balance = r.balance;
      paintBalance();

      // Cập nhật tồn kho thật cho cả danh sách shopRows và Products
      r.items.forEach(it => {
        if (shopRows) {
          const row = shopRows.find(x => Number(x.product_id) === Number(it.product_id));
          if (row) row.stock_quantity = it.stock_quantity;
        }
        const p = Products.find(x => x.dbId === Number(it.product_id));
        if (p) p.stock = it.stock_quantity;
      });

      addActivityFeed('📥 Bổ sung đầy kho', '-' + formatMoney(r.total_cost), 'expense');
      showToast('✅', 'Đã nhập ' + r.items.length + ' sản phẩm · −' + formatMoney(r.total_cost));

      if (dlg) dlg.hidden = true;
      updateInventoryTable();
    } catch (e) {
      showToast('❌', e.message);
      if (e.data && e.data.balance !== undefined) {
        walletBalance = e.data.balance;
        GameState.balance = e.data.balance;
        paintBalance();
      }
    } finally {
      if (dyes) dyes.disabled = false;
    }
  }

  window.restockAllProducts = async function () {
    if (!SHOP_ID) {
      const items = Products.filter(p => MAX_STOCK - p.stock > 0);
      if (!items.length) { showToast('📦', 'Tồn kho nguyên liệu đã đầy!'); return; }
      showToast('⚠️', 'Đang ở chế độ demo. Hãy vào cửa hàng từ bản đồ để nhập kho thật.');
      return;
    }

    if (shopRows === null) {
      await loadShop();
    }
    if (!shopRows || !shopRows.length) {
      showToast('⚠️', 'Không tải được danh sách sản phẩm của cửa hàng.');
      return;
    }

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Lập danh sách bổ sung đầy kho dựa trên danh sách sản phẩm THẬT của cửa hàng
    restockPlan = shopRows.map(sp => {
      const live = Products.find(p => p.dbId === Number(sp.product_id));
      const curStock = live ? Number(live.stock) : Number(sp.stock_quantity);
      const maxS = sp.max_stock ? Number(sp.max_stock) : MAX_STOCK;
      const need = maxS - curStock;
      const baseCost = live ? Number(live.costPrice) : Number(sp.base_cost);
      const sellPrice = live ? Number(live.sellPrice) : Number(sp.selling_price);
      const emoji = sp.emoji || (live ? live.emoji : '📦');
      return {
        id: Number(sp.product_id),
        name: sp.product_name,
        emoji: emoji,
        currentStock: curStock,
        quantity: need,
        cost: need > 0 ? baseCost * need : 0,
        price: sellPrice
      };
    }).filter(x => x.quantity > 0);

    if (!restockPlan.length) {
      showToast('📦', 'Tồn kho đã đầy!');
      return;
    }

    const totalCost = restockPlan.reduce((a, x) => a + x.cost, 0);

    const dlg = document.getElementById('dlg-restock');
    const dlist = document.getElementById('dlg-list');
    const dtot = document.getElementById('dlg-total');
    const dyes = document.getElementById('dlg-btn-confirm');

    if (dlg && dlist && dtot && dyes) {
      dlist.innerHTML = restockPlan.map(x =>
        `<li><span>${x.emoji} ${esc(x.name)} × ${x.quantity}</span><span>${formatMoney(x.cost)}</span></li>`
      ).join('');

      const short = (walletBalance !== null && totalCost > walletBalance);
      dtot.textContent = formatMoney(totalCost) + (short ? ' (không đủ tiền trong ví, đơn nhập sẽ không được thực hiện)' : '');
      dyes.disabled = short;
      dlg.hidden = false;
      dyes.focus();
    } else {
      if (walletBalance !== null && totalCost > walletBalance) {
        showToast('❌', 'Không đủ tiền trong ví! Cần ' + formatMoney(totalCost));
        return;
      }
      executeRestockPlan();
    }
  };

  // ── CHỐT CA: phí cố định (mặt bằng/lương/điện nước) CHƯA có endpoint server ──
  // game.js vẫn tự trừ GameState.balance cục bộ. Bọc lại để màn hình luôn quay về số dư thật của server,
  // tránh hiển thị một số dư "ảo" đã bị trừ phí. TODO: thay bằng api/end-day.php khi nhóm chốt cách tính phí theo ngày.
  // Chế độ thật: server chuyển doanh thu trong két vào ví (api/end-day.php) rồi trả số liệu ca để vẽ báo cáo.
  // Phí mặt bằng/điện nước nay thu theo THÁNG ở server nên báo cáo ngày không còn phí cố định.
  const originalEndDay = window.endDayAndSettle;
  let settling = false;
  window.endDayAndSettle = async function () {
    if (!SHOP_ID) { originalEndDay(); return; } // demo: giữ cách cũ
    if (settling) return;
    settling = true;
    try {
      const r = (await api('end-day.php', post({ shop_id: SHOP_ID }))).data;
      DailyFixedCosts.rent = DailyFixedCosts.staff = DailyFixedCosts.utilities = 0;
      GameState.today.orders       = r.orders_served;
      GameState.today.missedOrders = r.orders_missed;
      GameState.today.revenue      = r.revenue;
      GameState.today.materialCost = r.cogs; // giá vốn hàng đã bán (tiền nhập hàng đã trừ ví lúc nhập)
      originalEndDay();                      // vẽ báo cáo / biểu đồ bằng số của server
      walletBalance = r.balance;
      GameState.balance = r.balance;
      paintBalance();
      if (r.transferred > 0) showToast('💵', 'Đã chuyển ' + formatMoney(r.transferred) + ' vào ví');
    } catch (e) {
      showToast('❌', e.message);
    } finally {
      settling = false;
    }
  };

  // Đăng ký sự kiện đóng/hủy cho modal nhập hàng
  document.addEventListener('DOMContentLoaded', () => {
    const dlg = document.getElementById('dlg-restock');
    const dno = document.getElementById('dlg-btn-cancel');
    const dyes = document.getElementById('dlg-btn-confirm');
    if (dno && dlg) {
      dno.addEventListener('click', () => { dlg.hidden = true; });
    }
    if (dyes) {
      dyes.addEventListener('click', executeRestockPlan);
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && dlg && !dlg.hidden) {
        dlg.hidden = true;
      }
    });
  });

  // Đăng ký toàn cục để các script khác (vd: shop-live.js) có thể gọi cập nhật số dư và kho hiển thị
  window.StartupWallet = {
    setBalance(v) {
      walletBalance = Number(v);
      GameState.balance = walletBalance;
      paintBalance();
    },
    setStock(stockMap) {
      if (!shopRows || !stockMap) return;
      Object.entries(stockMap).forEach(([pid, qty]) => {
        const row = shopRows.find(r => Number(r.product_id) === Number(pid));
        if (row) row.stock_quantity = Number(qty);
      });
    },
    reloadShop: loadShop
  };

})();
