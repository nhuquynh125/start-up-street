// ==============================================================
// STARTUP STREET — Member 4: Simulation & Finance Engine
// Trách nhiệm: Nhận đơn hàng, trừ kho, tính thu chi, lập báo cáo
// ==============================================================

// ── Trạng thái trò chơi ──
const GameState = {
  balance: 10000000,   // Số dư ví (kết nối với Member 1)
  day: 1,              // Ngày hoạt động hiện tại
  reputation: 100,     // Điểm uy tín
  
  // Dữ liệu tạm thời trong ngày hôm nay (trước khi chốt ca)
  today: {
    orders: 0,
    missedOrders: 0,   // Số đơn bị mất do hết hàng
    revenue: 0,
    materialCost: 0,
    soldItems: {},     // { productId: count }
    feedbacks: [],
  },

  // Lũy kế toàn bộ các ngày
  totalRevenue: 0,
  totalExpenses: 0,
  totalProfit: 0,
  totalOrders: 0,
  totalMissed: 0,

  // Lịch sử theo từng ngày (để vẽ biểu đồ Revenue vs Expenses)
  dailyHistory: [],
  
  // Lịch sử giao dịch (Sổ cái kế toán)
  transactions: [],
};

// ── Danh mục sản phẩm & kho hàng (Dữ liệu từ Member 3) ──
const Products = [
  { 
    id: 1, 
    name: 'Trà sữa trân châu', 
    emoji: '🧋', 
    costPrice: 12000, 
    sellPrice: 35000, 
    stock: 40, 
    soldTotal: 0,
    goodComments: ["Trà sữa vị vừa miệng, trân châu rất dẻo!", "Trà sữa đậm đà, rất thơm béo!"],
    badComments: ["Hôm nay trà sữa hơi ngọt quá.", "Trân châu hơi cứng một chút."]
  },
  { 
    id: 2, 
    name: 'Trà đào cam sả',    
    emoji: '🍑', 
    costPrice: 10000, 
    sellPrice: 30000, 
    stock: 35, 
    soldTotal: 0,
    goodComments: ["Trà đào thơm thanh, giải nhiệt rất đã!", "Miếng đào giòn ngọt, rất ngon."],
    badComments: ["Trà đào hơi chua so với khẩu vị của tôi."]
  },
  { 
    id: 3, 
    name: 'Matcha đá xay',      
    emoji: '🍵', 
    costPrice: 15000, 
    sellPrice: 40000, 
    stock: 25, 
    soldTotal: 0,
    goodComments: ["Matcha đá xay đậm vị Nhật, thơm lừng!", "Kem béo ngậy, matcha nguyên chất."],
    badComments: ["Matcha đá xay bị tan hơi nhiều đá."]
  },
  { 
    id: 4, 
    name: 'Cà phê sữa đá',     
    emoji: '☕', 
    costPrice: 8000,  
    sellPrice: 25000, 
    stock: 30, 
    soldTotal: 0,
    goodComments: ["Cà phê đậm vị, uống rất tỉnh táo!", "Pha chuẩn vị cà phê sữa truyền thống."],
    badComments: ["Hơi nhiều sữa đặc, át mất vị cà phê."]
  },
];

// ── Chi phí cố định mỗi ngày (Mặt bằng từ Member 2 + Lương + Điện nước) ──
const DailyFixedCosts = {
  rent: 500000,      // Tiền thuê mặt bằng (kết nối với Member 2)
  staff: 300000,     // Lương nhân viên phục vụ
  utilities: 50000,  // Điện, nước, đá viên
};

// ══════════════════════════════════════════════════════════════
// 1. NGHIỆP VỤ XỬ LÝ ĐƠN HÀNG (Nhận từ quầy Member 3)
// ══════════════════════════════════════════════════════════════

/**
 * Xử lý khi một món ăn/đồ uống được làm xong và bán cho khách
 * @returns {boolean} True nếu bán thành công, False nếu hết hàng
 */
function processIncomingOrder(productId) {
  const product = Products.find(p => p.id === productId);
  if (!product) return false;

  // KIỂM TRA TỒN KHO CHẶT CHẼ
  if (product.stock <= 0) {
    GameState.today.missedOrders++;
    GameState.reputation = Math.max(0, GameState.reputation - 1); // Trừ 1 điểm uy tín vì để hết hàng
    
    addActivityFeed(
      `❌ Khách muốn mua ${product.emoji} ${product.name} nhưng HẾT HÀNG! Khách bỏ về`,
      '0đ',
      'expense'
    );
    showToast('⚠️', `Đã hết nguyên liệu làm ${product.name}! Khách bỏ về.`);
    updateTodayStats();
    updateHUD();
    return false; // KHÔNG BÁN, KHÔNG CỘNG TIỀN
  }

  // 1. Trừ tồn kho nguyên liệu
  product.stock--;
  product.soldTotal++;

  // 2. Cập nhật số liệu tạm tính trong ngày
  GameState.today.orders++;
  GameState.today.revenue += product.sellPrice;
  GameState.today.materialCost += product.costPrice;
  GameState.today.soldItems[product.id] = (GameState.today.soldItems[product.id] || 0) + 1;

  // 3. Cộng tiền doanh thu vào ví
  GameState.balance += product.sellPrice;

  // 4. Ghi nhận giao dịch vào sổ cái
  const txId = 'TX' + String(Date.now()).slice(-6);
  GameState.transactions.push({
    id: txId,
    day: GameState.day,
    type: 'sale',
    desc: `Bán ${product.emoji} ${product.name}`,
    amount: product.sellPrice,
    balance: GameState.balance,
  });

  // 5. Sinh nhận xét khớp CHÍNH XÁC với món vừa mua
  const isGood = Math.random() < 0.85;
  const commentList = isGood ? product.goodComments : product.badComments;
  const commentText = commentList[Math.floor(Math.random() * commentList.length)];
  const rating = isGood ? 5 : 3;

  GameState.today.feedbacks.push({
    customer: `Khách #${GameState.today.orders}`,
    item: product.name,
    comment: commentText,
    rating: rating,
  });

  // 6. Cập nhật giao diện
  addActivityFeed(`Bán thành công ${product.emoji} ${product.name}`, `+${formatMoney(product.sellPrice)}`, 'income');
  updateTodayStats();
  updateInventoryTable();
  updateHUD();
  updateTransactionTable();

  showToast(product.emoji, `+${formatMoney(product.sellPrice)} (Đơn #${GameState.today.orders})`);
  return true;
}

/**
 * Mô phỏng giờ cao điểm có nhiều khách gọi món liên tiếp
 */
function simulateRushHour(count) {
  // Kiểm tra xem quầy còn món nào trong kho không
  const totalStockLeft = Products.reduce((sum, p) => sum + p.stock, 0);
  if (totalStockLeft <= 0) {
    showToast('❌', 'Toàn bộ kho đã hết sạch nguyên liệu! Hãy nhập hàng trước khi mở giờ cao điểm.');
    addActivityFeed('⚠️ Khách ùa vào nhưng quán HẾT SẠCH HÀNG, tất cả khách bỏ về!', '0đ', 'expense');
    return;
  }

  let done = 0;
  const interval = setInterval(() => {
    if (done >= count) {
      clearInterval(interval);
      return;
    }

    // Chọn ngẫu nhiên món mà khách MUỐN GỌI (ngay cả khi hết hàng)
    const desiredProduct = Products[Math.floor(Math.random() * Products.length)];
    processIncomingOrder(desiredProduct.id);

    done++;
  }, 220);
}

/**
 * Nhập thêm nguyên liệu cho kho hàng
 */
function restockAllProducts() {
  let totalRestockCost = 0;
  Products.forEach(p => {
    const need = 40 - p.stock;
    if (need > 0) {
      totalRestockCost += need * p.costPrice;
      p.stock = 40;
    }
  });

  if (totalRestockCost === 0) {
    showToast('📦', 'Tồn kho nguyên liệu đã đầy!');
    return;
  }

  if (GameState.balance < totalRestockCost) {
    showToast('❌', `Không đủ tiền trong ví! Cần ${formatMoney(totalRestockCost)}`);
    return;
  }

  // Trừ tiền mua nguyên liệu
  GameState.balance -= totalRestockCost;
  GameState.today.materialCost += totalRestockCost;

  GameState.transactions.push({
    id: 'TX' + String(Date.now()).slice(-6),
    day: GameState.day,
    type: 'cost',
    desc: 'Nhập bổ sung nguyên vật liệu quầy pha chế',
    amount: -totalRestockCost,
    balance: GameState.balance,
  });

  addActivityFeed('📥 Nhập nguyên liệu kho', `-${formatMoney(totalRestockCost)}`, 'expense');
  updateInventoryTable();
  updateHUD();
  updateTransactionTable();
  showToast('✅', `Đã nhập hàng: -${formatMoney(totalRestockCost)}`);
}

// ══════════════════════════════════════════════════════════════
// 2. KẾT CA & CHỐT SỔ TÀI CHÍNH CUỐI NGÀY (End Day Settlement)
// ══════════════════════════════════════════════════════════════

function endDayAndSettle() {
  const todayRevenue = GameState.today.revenue;
  const todayMaterial = GameState.today.materialCost;
  const todayFixed = DailyFixedCosts.rent + DailyFixedCosts.staff + DailyFixedCosts.utilities;
  const todayTotalExpense = todayMaterial + todayFixed;
  const todayNetProfit = todayRevenue - todayTotalExpense;

  // 1. Trừ chi phí cố định khỏi số dư ví
  GameState.balance -= todayFixed;

  // 2. Ghi nhận giao dịch chi phí cố định vào sổ cái
  GameState.transactions.push({
    id: 'TX' + String(Date.now()).slice(-6),
    day: GameState.day,
    type: 'rent',
    desc: `Chi phí cố định Ngày ${GameState.day} (Mặt bằng 500k, Lương 300k, Điện nước 50k)`,
    amount: -todayFixed,
    balance: GameState.balance,
  });

  // 3. Tính điểm hài lòng (Satisfaction %)
  const feedbacks = GameState.today.feedbacks;
  const avgRating = feedbacks.length > 0 
    ? (feedbacks.reduce((sum, f) => sum + f.rating, 0) / feedbacks.length).toFixed(1)
    : 5.0;
  
  // Trừ thêm điểm nếu để mất nhiều khách do hết hàng
  const penalty = GameState.today.missedOrders * 5;
  const satisfaction = Math.max(0, Math.min(100, Math.round((avgRating / 5) * 100) - penalty));

  // 4. Lưu lịch sử ngày
  const daySummary = {
    day: GameState.day,
    orders: GameState.today.orders,
    missed: GameState.today.missedOrders,
    revenue: todayRevenue,
    materialCost: todayMaterial,
    fixedCost: todayFixed,
    totalExpenses: todayTotalExpense,
    profit: todayNetProfit,
    satisfaction: satisfaction,
    rating: avgRating,
    feedbacks: [...feedbacks],
  };
  GameState.dailyHistory.push(daySummary);

  // 5. Cập nhật lũy kế
  GameState.totalRevenue += todayRevenue;
  GameState.totalExpenses += todayTotalExpense;
  GameState.totalProfit += todayNetProfit;
  GameState.totalOrders += GameState.today.orders;
  GameState.totalMissed += GameState.today.missedOrders;

  // 6. Hiển thị thông báo tổng kết
  showToast(
    todayNetProfit >= 0 ? '💰' : '📉',
    `Chốt Ngày ${GameState.day}: Lợi nhuận ${formatMoney(todayNetProfit)}`
  );

  // 7. Cập nhật báo cáo & biểu đồ
  renderDailyReport(daySummary);
  renderCustomerFeedbacks(daySummary.feedbacks);
  updateFinanceDashboard();
  updateTrendChart();
  updateExpenseDonut();

  // 8. Tăng ngày mới & reset số liệu tạm hôm nay
  GameState.day++;
  GameState.today = {
    orders: 0,
    missedOrders: 0,
    revenue: 0,
    materialCost: 0,
    soldItems: {},
    feedbacks: [],
  };

  // Cập nhật giao diện ngày mới
  document.getElementById('today-day-label').textContent = GameState.day;
  updateTodayStats();
  updateHUD();
  updateTransactionTable();

  addActivityFeed(`🌙 Hoàn tất chốt ca Ngày ${GameState.day - 1}. Bắt đầu Ngày ${GameState.day}!`);
}

// ══════════════════════════════════════════════════════════════
// 3. HIỂN THỊ GIAO DIỆN & BÁO CÁO (Render UI)
// ══════════════════════════════════════════════════════════════

function updateTodayStats() {
  document.getElementById('today-order-count').textContent = GameState.today.orders;
  document.getElementById('today-revenue').textContent = formatMoney(GameState.today.revenue);
  document.getElementById('today-material-cost').textContent = formatMoney(GameState.today.materialCost);
}

function updateHUD() {
  document.getElementById('hud-balance').textContent = formatMoney(GameState.balance);
  document.getElementById('hud-day').textContent = GameState.day;
  document.getElementById('hud-reputation').textContent = GameState.reputation + '★';
}

function updateInventoryTable() {
  // 1. Cập nhật bảng kho
  const tbody = document.getElementById('inventory-table-body');
  tbody.innerHTML = Products.map(p => {
    const maxStock = 40;
    const pct = Math.max(0, (p.stock / maxStock) * 100);
    const cls = pct > 50 ? 'stock-high' : pct > 20 ? 'stock-medium' : 'stock-low';
    const isOutOfStock = p.stock <= 0;

    return `<tr>
      <td>
        <span class="product-emoji">${p.emoji}</span>
        <strong>${p.name}</strong>
        ${isOutOfStock ? '<span style="color:var(--accent-red); font-size:0.75rem; font-weight:800; margin-left:6px;">[HẾT HÀNG]</span>' : ''}
      </td>
      <td>${formatMoney(p.costPrice)}</td>
      <td>${formatMoney(p.sellPrice)}</td>
      <td>
        <span style="font-weight:700; color:${isOutOfStock ? 'var(--accent-red)' : 'inherit'};">${p.stock}</span> / ${maxStock}
        <div class="stock-bar"><div class="stock-bar-fill ${cls}" style="width:${pct}%"></div></div>
      </td>
      <td><strong>${p.soldTotal}</strong></td>
    </tr>`;
  }).join('');

  // 2. Cập nhật trạng thái các nút bấm gọi món (hiện rõ số lượng còn lại)
  Products.forEach(p => {
    const btn = document.getElementById(`btn-order-${p.id}`);
    if (btn) {
      if (p.stock <= 0) {
        btn.innerHTML = `${p.emoji} ${p.name} <span style="color:var(--accent-red); font-weight:800;">(HẾT HÀNG)</span>`;
        btn.style.opacity = '0.55';
        btn.style.borderColor = 'var(--accent-red)';
      } else {
        btn.innerHTML = `${p.emoji} ${p.name} (${formatMoneyShort(p.sellPrice)}) <span style="font-size:0.75rem; color:var(--line-light);">[Còn ${p.stock}]</span>`;
        btn.style.opacity = '1';
        btn.style.borderColor = 'var(--wood-medium)';
      }
    }
  });
}

function addActivityFeed(text, amount = '', type = '') {
  const feed = document.getElementById('activity-feed');
  if (feed.querySelector('.empty-state')) feed.innerHTML = '';

  const item = document.createElement('div');
  item.className = 'activity-item';
  item.innerHTML = `
    <span class="act-time">Ngày ${GameState.day}</span>
    <span class="act-text">${text}</span>
    ${amount ? `<span class="act-amount ${type}">${amount}</span>` : ''}
  `;
  feed.insertBefore(item, feed.firstChild);

  while (feed.children.length > 50) feed.removeChild(feed.lastChild);
}

function updateFinanceDashboard() {
  document.getElementById('dash-total-revenue').textContent = formatMoney(GameState.totalRevenue);
  document.getElementById('dash-total-expenses').textContent = formatMoney(GameState.totalExpenses);
  document.getElementById('dash-net-profit').textContent = formatMoney(GameState.totalProfit);
  document.getElementById('dash-current-balance').textContent = formatMoney(GameState.balance);

  document.getElementById('dash-revenue-margin').textContent = `${GameState.totalOrders} đơn đã bán (${GameState.totalMissed} đơn lỡ)`;
  
  const margin = GameState.totalRevenue > 0
    ? ((GameState.totalProfit / GameState.totalRevenue) * 100).toFixed(1)
    : 0;
  const marginEl = document.getElementById('dash-margin-rate');
  marginEl.textContent = `Margin: ${margin}%`;
  marginEl.className = margin >= 0 ? 'stat-change up' : 'stat-change down';
}

function renderDailyReport(dayData) {
  const container = document.getElementById('daily-report-content');
  
  container.innerHTML = `
    <div style="background:var(--bg-highlight); padding:16px; border-radius:var(--radius-md); margin-bottom:16px;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:1.1rem; font-weight:800; color:var(--wood-darker);">📅 Tổng kết Ngày ${dayData.day}</span>
        <span class="status-badge ${dayData.profit >= 0 ? 'open' : 'closed'}">
          ${dayData.profit >= 0 ? 'Có lãi' : 'Bị lỗ'}
        </span>
      </div>
    </div>

    <div style="font-size:0.92rem; line-height:2.2;">
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>💵 Tổng doanh thu (Total Revenue):</span>
        <strong style="color:var(--accent-green);">${formatMoney(dayData.revenue)}</strong>
      </div>
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>🛒 Đơn bán thành công (Served):</span>
        <strong>${dayData.orders} đơn</strong>
      </div>
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>❌ Đơn bị lỡ do hết hàng (Missed):</span>
        <strong style="color:var(--accent-red);">${dayData.missed || 0} đơn</strong>
      </div>
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>🥤 Chi phí nguyên liệu món (Ingredients):</span>
        <span style="color:var(--accent-red);">${formatMoney(dayData.materialCost)}</span>
      </div>
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>🏠 Chi phí cố định (Mặt bằng + Lương + Điện):</span>
        <span style="color:var(--accent-red);">${formatMoney(dayData.fixedCost)}</span>
      </div>
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>👍 Độ hài lòng khách hàng (Satisfaction):</span>
        <strong>${dayData.satisfaction}%</strong>
      </div>
      <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--bg-highlight);">
        <span>⭐ Đánh giá trung bình (Rating):</span>
        <strong style="color:var(--accent-orange);">${'★'.repeat(Math.round(dayData.rating))} (${dayData.rating}/5.0)</strong>
      </div>
      <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:1.1rem;">
        <span>📈 Lợi nhuận ròng (Net Profit):</span>
        <strong style="color:${dayData.profit >= 0 ? 'var(--accent-green)' : 'var(--accent-red)'}; font-size:1.2rem;">
          ${dayData.profit >= 0 ? '+' : ''}${formatMoney(dayData.profit)}
        </strong>
      </div>
    </div>
  `;
}

function renderCustomerFeedbacks(feedbacks) {
  const container = document.getElementById('feedback-list');
  if (!feedbacks || feedbacks.length === 0) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">💬</div><p>Ngày này chưa có nhận xét nào.</p></div>`;
    return;
  }

  const displayFeedbacks = feedbacks.slice(-5);
  container.innerHTML = displayFeedbacks.map(f => `
    <div style="background:var(--bg-card); border:1.5px solid var(--wood-light); border-radius:var(--radius-md); padding:12px; box-shadow:var(--shadow-sm);">
      <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
        <span style="font-weight:700; color:var(--wood-darker); font-size:0.85rem;">👤 ${f.customer} (Mua ${f.item})</span>
        <span style="color:var(--accent-orange); font-size:0.85rem;">${'★'.repeat(f.rating)}</span>
      </div>
      <div style="font-size:0.85rem; color:var(--line-medium); font-style:italic;">"${f.comment}"</div>
    </div>
  `).join('');
}

function updateTransactionTable() {
  const tbody = document.getElementById('tx-table-body');
  if (GameState.transactions.length === 0) return;

  tbody.innerHTML = GameState.transactions.slice().reverse().map(tx => `
    <tr>
      <td><strong>${tx.id}</strong> (N${tx.day})</td>
      <td>
        <span class="tx-type ${tx.type}">
          ${tx.type === 'sale' ? '💵 Bán hàng' : tx.type === 'cost' ? '🥤 Nguyên liệu' : '🏠 Cố định'}
        </span>
      </td>
      <td>${tx.desc}</td>
      <td style="font-weight:700; color:${tx.amount >= 0 ? 'var(--accent-green)' : 'var(--accent-red)'};">
        ${tx.amount >= 0 ? '+' : ''}${formatMoney(tx.amount)}
      </td>
      <td>${formatMoney(tx.balance)}</td>
    </tr>
  `).join('');
}

// ══════════════════════════════════════════════════════════════
// 4. BIỂU ĐỒ REVENUE VS EXPENSES TREND (Chart.js)
// ══════════════════════════════════════════════════════════════

let trendChart = null;
let donutChart = null;

function initCharts() {
  Chart.defaults.font.family = 'Nunito';
  Chart.defaults.color = '#3C2A21';

  const ctxTrend = document.getElementById('chart-finance-trend').getContext('2d');
  trendChart = new Chart(ctxTrend, {
    type: 'line',
    data: {
      labels: ['Bắt đầu'],
      datasets: [
        {
          label: 'Revenue (Doanh thu)',
          data: [0],
          borderColor: '#4A7BB0',
          backgroundColor: 'rgba(74, 123, 176, 0.15)',
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: '#4A7BB0',
        },
        {
          label: 'Expenses (Chi phí)',
          data: [0],
          borderColor: '#D45B6C',
          backgroundColor: 'rgba(212, 91, 108, 0.15)',
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: '#D45B6C',
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: ctx => `${ctx.dataset.label}: ${formatMoney(ctx.raw)}`
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: v => formatMoneyShort(v) },
          grid: { color: 'rgba(45, 30, 24, 0.06)' },
        },
        x: { grid: { display: false } }
      }
    }
  });

  const ctxDonut = document.getElementById('chart-expense-donut').getContext('2d');
  donutChart = new Chart(ctxDonut, {
    type: 'doughnut',
    data: {
      labels: ['Nguyên liệu món', 'Tiền thuê mặt bằng', 'Lương nhân sự', 'Điện nước'],
      datasets: [{
        data: [1, 1, 1, 1],
        backgroundColor: ['#D47B6C', '#B094C8', '#7EAFC9', '#F0D58C'],
        borderColor: '#FFFBF5',
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right' }
      }
    }
  });
}

function updateTrendChart() {
  if (!trendChart) return;

  const history = GameState.dailyHistory;
  trendChart.data.labels = history.map(h => `Ngày ${h.day}`);
  trendChart.data.datasets[0].data = history.map(h => h.revenue);
  trendChart.data.datasets[1].data = history.map(h => h.totalExpenses);
  trendChart.update();
}

function updateExpenseDonut() {
  if (!donutChart) return;

  const totalMaterial = GameState.dailyHistory.reduce((sum, h) => sum + h.materialCost, 0);
  const days = GameState.dailyHistory.length;
  const totalRent = days * DailyFixedCosts.rent;
  const totalStaff = days * DailyFixedCosts.staff;
  const totalUtilities = days * DailyFixedCosts.utilities;

  donutChart.data.datasets[0].data = [totalMaterial, totalRent, totalStaff, totalUtilities];
  donutChart.update();
}

// ══════════════════════════════════════════════════════════════
// 5. TIỆN ÍCH CHUNG (Helpers)
// ══════════════════════════════════════════════════════════════

function formatMoney(amount) {
  const abs = Math.abs(amount);
  const formatted = abs.toLocaleString('vi-VN') + 'đ';
  return amount < 0 ? '-' + formatted : formatted;
}

function formatMoneyShort(amount) {
  if (Math.abs(amount) >= 1000000) return (amount / 1000000).toFixed(1) + 'M';
  if (Math.abs(amount) >= 1000) return (amount / 1000).toFixed(0) + 'K';
  return amount.toString();
}

function switchTab(tabName) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + tabName).classList.add('active');
  event.currentTarget.classList.add('active');
}

function showToast(icon, message) {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<span style="font-size:1.2rem;">${icon}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => { if (toast.parentNode) toast.remove(); }, 3000);
}

// ── Khởi động ứng dụng ──
document.addEventListener('DOMContentLoaded', () => {
  updateInventoryTable();
  updateHUD();
  initCharts();
});
