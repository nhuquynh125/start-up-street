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

  // ── Nạp sản phẩm / tồn kho / giá từ MySQL (khớp theo tên món) ──
  async function loadShop() {
    if (!SHOP_ID) {
      showToast('⚠️', 'Thiếu shop_id trên URL nên đang ở chế độ demo. Mở: startup-street-ui.html?shop_id=<id cửa hàng>');
      paintBalance();
      return;
    }
    try {
      const d = (await api('get-shop-products.php?shop_id=' + SHOP_ID)).data;
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

  // ── NHẬP HÀNG: dùng purchase-inventory.php (server trừ ví + cộng kho trong 1 transaction) ──
  window.restockAllProducts = async function () {
    if (!SHOP_ID) { showToast('⚠️', 'Thiếu shop_id trên URL.'); return; }
    const todo = Products.filter(p => p.dbId && MAX_STOCK - p.stock > 0);
    if (todo.length === 0) { showToast('📦', 'Tồn kho nguyên liệu đã đầy!'); return; }

    let totalCost = 0;
    for (const p of todo) {
      try {
        const r = (await api('purchase-inventory.php', post({
          shop_id: SHOP_ID, product_id: p.dbId, quantity: MAX_STOCK - p.stock
        }))).data;
        p.stock = r.stock_quantity;
        totalCost += r.total_cost;
        walletBalance = r.balance;
        GameState.balance = r.balance;
      } catch (e) {
        showToast('❌', e.message); // vd. không đủ tiền → dừng, các món đã nhập xong vẫn được giữ
        break;
      }
    }

    if (totalCost > 0) {
      GameState.today.materialCost += totalCost;
      GameState.transactions.push({
        id: 'TX' + String(Date.now()).slice(-6),
        day: GameState.day,
        type: 'cost',
        desc: 'Nhập bổ sung nguyên vật liệu quầy pha chế',
        amount: -totalCost,
        balance: walletBalance,
      });
      addActivityFeed('📥 Nhập nguyên liệu kho', '-' + formatMoney(totalCost), 'expense');
      showToast('✅', 'Đã nhập hàng: -' + formatMoney(totalCost));
    }
    updateInventoryTable();
    updateTransactionTable();
    paintBalance();
  };

  // ── CHỐT CA: phí cố định (mặt bằng/lương/điện nước) CHƯA có endpoint server ──
  // game.js vẫn tự trừ GameState.balance cục bộ. Bọc lại để màn hình luôn quay về số dư thật của server,
  // tránh hiển thị một số dư "ảo" đã bị trừ phí. TODO: thay bằng api/end-day.php khi nhóm chốt cách tính phí theo ngày.
  const originalEndDay = window.endDayAndSettle;
  window.endDayAndSettle = function () {
    originalEndDay();
    if (SHOP_ID) syncBalance(); // demo: không có số dư server để đồng bộ
  };
  window.StartupWallet = { setBalance(v) { walletBalance = Number(v); GameState.balance = walletBalance; paintBalance(); } };

})();
