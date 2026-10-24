// ==============================================================
// FIX: tab "Kho hàng" trong startup-street-ui.html hiện kho của quán khác
//
// NGUYÊN NHÂN
//   Bảng kho (updateInventoryTable trong game.js) vẽ từ mảng Products — 4 món trà sữa
//   cứng trong game.js với tồn kho mẫu (40/35/25/30). wallet-api.js chỉ ghi đè số liệu
//   thật lên những món TRÙNG TÊN. Nếu quán đang mở không phải "Trà sữa & đồ uống"
//   (hoặc tên món lệch), bảng vẫn hiện menu mẫu → trông như kho của quán khác.
//   Ngoài ra bảng còn hiện số mẫu trong lúc chờ API trả về.
//
// CÁCH SỬA: chỉ sửa public/js/wallet-api.js (3 chỗ, đều nằm trong IIFE)
// ==============================================================


// ── CHỖ 1 ── ngay trước dòng "async function loadShop() {" thêm 2 dòng:
//
//   let shopRows = null; // null = đang tải | false = lỗi | mảng = dòng kho THẬT của SHOP_ID
//
//   (dán đoạn "CHỖ 2" bên dưới ngay sau dòng này luôn)


// ── CHỖ 2 ── dán nguyên khối này (cùng chỗ với CHỖ 1, trước loadShop) ──
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
      + '<td><span class="product-emoji">' + (live ? live.emoji : '📦') + '</span><strong>' + esc(sp.product_name) + '</strong>'
      + (out ? '<span style="color:var(--accent-red);font-size:0.75rem;font-weight:800;margin-left:6px;">[HẾT HÀNG]</span>' : '') + '</td>'
      + '<td>' + formatMoney(cost) + '</td>'
      + '<td>' + formatMoney(price) + '</td>'
      + '<td><span style="font-weight:700;color:' + (out ? 'var(--accent-red)' : 'inherit') + ';">' + stock + '</span> / ' + MAX_STOCK
      + '<div class="stock-bar"><div class="stock-bar-fill ' + cls + '" style="width:' + pct + '%"></div></div></td>'
      + '<td><strong>' + (live ? live.soldTotal : '—') + '</strong></td>'
      + '</tr>';
  }).join('');
};


// ── CHỖ 3 ── sửa bên trong loadShop() ──
//
//   const d = (await api('get-shop-products.php?shop_id=' + SHOP_ID)).data;
// + shopRows = d.products;                 // <-- THÊM dòng này ngay sau dòng trên
//
//   ...
//   } catch (e) {
//     showToast('❌', e.message);
// +   shopRows = false;                    // <-- THÊM 2 dòng này
// +   updateInventoryTable();
//   }
