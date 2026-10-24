/*
 * shop-scene.js — cảnh quầy hàng cho Startup Street (v2, khớp JSON của shop-tick.php)
 * Khách đi từ ngoài phố tới quầy -> nói món muốn mua -> nhận hàng -> "+tiền" nổi trên đầu -> rời đi.
 * Khách hết hàng thì nói "hết rồi à?" và bỏ về không có tiền; người chê giá chỉ đi ngang qua quán.
 *
 * Module này CHỈ VẼ. Tiền/khách/kho do server quyết định, ở đây không tính gì.
 *
 *   const scene = ShopScene.mount(el, { emojiFor: productId => '🧋' });
 *   scene.setOpen(true);
 *   scene.push(state.orders, { silent: firstState });  // mảng orders của shop-tick.php
 *   scene.walkBy(state.walked_by);
 */
(function (global) {
  'use strict';

  /* Một dòng shop_orders -> một khách. Khớp lib/shop_simulation.php (shopRuntimeSnapshot). */
  function normalizeEvent(raw, emojiFor) {
    return {
      id: raw.id,
      status: raw.status === 'served' ? 'served' : 'missed',
      customer: raw.customer || '',
      items: [{
        name: String(raw.product_name || 'món'),
        qty: Number(raw.quantity) || 1,
        emoji: emojiFor(raw.product_id)
      }],
      revenue: Number(raw.total) || 0
    };
  }

  const PEOPLE = ['🧑', '👩', '👨', '👧', '🧒', '👵', '👴', '🧑‍🎓', '👩‍💼', '🧔'];
  const SLOT_X = [30, 47, 64];            // % chiều ngang chỗ đứng trước quầy
  const MAX_QUEUE = 8;                    // xếp hàng quá dài thì bỏ bớt khách cũ khỏi cảnh (tiền vẫn đã tính ở server)
  const CSS = `
.ss-scene{--wood-d:#4a2a16;--wood:#8a5a3b;--wood-l:#b9835a;--cream:#fff6dd;--ok:#4c8a26;--no:#a63a31;
  position:relative;flex-shrink:0;height:230px;margin-bottom:12px;overflow:hidden;border:4px solid var(--wood);border-radius:18px;
  background:linear-gradient(#cfe6d4 0 46%,#e9d9a8 46% 100%);font-family:'Baloo 2',system-ui,sans-serif;color:var(--wood-d)}
.ss-awning{position:absolute;left:0;right:0;top:0;height:30px;
  background:repeating-linear-gradient(90deg,#d95b4f 0 28px,#fff6dd 28px 56px);border-bottom:4px solid var(--wood-d)}
.ss-counter{position:absolute;right:0;bottom:0;width:30%;height:92px;background:var(--wood);border:4px solid var(--wood-d);
  border-right:0;border-bottom:0;border-radius:14px 0 0 0}
.ss-counter::before{content:'';position:absolute;left:-4px;right:0;top:-4px;height:14px;background:var(--wood-l);
  border:4px solid var(--wood-d);border-right:0;border-radius:12px 0 0 0}
.ss-keeper{position:absolute;right:9%;bottom:78px;font-size:46px;line-height:1}
.ss-pop{position:absolute;right:20%;bottom:84px;font-size:26px;opacity:0;transition:opacity .2s,transform .5s}
.ss-pop.on{opacity:1;transform:translateX(-90px) translateY(-8px)}
.ss-cust{position:absolute;z-index:2;bottom:30px;width:64px;margin-left:-32px;text-align:center;left:-14%;
  transition:left var(--ss-walk,1.2s) ease-in-out}
.ss-face{font-size:46px;line-height:1;display:block}
.ss-name{display:block;font-size:11px;font-weight:700;white-space:nowrap;opacity:.8}
.ss-cust.walking .ss-face{animation:ss-bob .45s ease-in-out infinite}
@keyframes ss-bob{50%{transform:translateY(-6px)}}
.ss-bubble{position:absolute;bottom:100%;left:50%;transform:translateX(-50%) scale(.8);transform-origin:50% 100%;
  min-width:96px;max-width:170px;padding:5px 9px;background:var(--cream);border:3px solid var(--wood);border-radius:12px;
  font-size:13px;font-weight:700;line-height:1.2;opacity:0;transition:opacity .2s,transform .2s;pointer-events:none}
.ss-bubble.miss{border-color:var(--no);color:var(--no)}
.ss-bubble.on{opacity:1;transform:translateX(-50%) scale(1)}
.ss-pay{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font-weight:800;font-size:20px;color:var(--ok);
  white-space:nowrap;text-shadow:0 2px 0 #fff;opacity:0}
.ss-pay.on{animation:ss-float 1.1s ease-out forwards}
@keyframes ss-float{0%{opacity:0;transform:translate(-50%,6px)}20%{opacity:1}100%{opacity:0;transform:translate(-50%,-34px)}}
.ss-walker{position:absolute;z-index:1;bottom:112px;left:-8%;font-size:30px;line-height:1;opacity:.85;transition:left 6s linear}
.ss-walker i{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font:700 11px/1 'Baloo 2',system-ui,sans-serif;
  font-style:normal;white-space:nowrap;background:var(--cream);border:2px solid var(--wood);border-radius:8px;padding:2px 6px}
.ss-closed{position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#4a2a16aa;
  font-weight:800;font-size:22px;color:var(--cream);z-index:5}
.ss-scene.is-closed .ss-closed{display:flex}
@media (prefers-reduced-motion:reduce){.ss-cust.walking .ss-face,.ss-pay.on{animation:none}.ss-pay.on{opacity:1}}
`;

  const sleep = ms => (document.hidden ? Promise.resolve() : new Promise(r => setTimeout(r, ms)));
  const rand = (a, b) => a + Math.random() * (b - a);
  const money = (n, cur) => '+' + Math.round(n).toLocaleString('vi-VN') + cur;

  function injectCss() {
    if (document.getElementById('ss-css')) return;
    const s = document.createElement('style');
    s.id = 'ss-css';
    s.textContent = CSS;
    document.head.appendChild(s);
  }

  function mount(root, opts) {
    injectCss();
    const o = Object.assign({
      currency: 'đ', walkMs: 1200, orderMs: 1900, serveMs: 700, payMs: 1100, leaveMs: 1000,
      emojiFor: () => '🧋', onSale: null
    }, opts);

    root.classList.add('ss-scene', 'is-closed');
    root.style.setProperty('--ss-walk', o.walkMs + 'ms');
    root.innerHTML =
      '<div class="ss-awning"></div><div class="ss-counter"></div>' +
      '<div class="ss-keeper" aria-hidden="true">🧑‍🍳</div><div class="ss-pop" aria-hidden="true"></div>' +
      '<div class="ss-closed">Quán đang đóng cửa</div>';
    const pop = root.querySelector('.ss-pop');

    const slots = SLOT_X.map(() => null);
    const queue = [];
    const seen = new Set();
    let wantOpen = false;

    // Đóng cửa thì chờ khách đang có trong cảnh phục vụ xong rồi mới hiện biển "đóng cửa"
    function applyOpen() {
      const idle = !queue.length && slots.every(s => s === null);
      root.classList.toggle('is-closed', !wantOpen && idle);
    }

    function speech(evt) {
      const a = evt.items[0];
      if (evt.status === 'missed') return `Hết ${a.name} rồi à? Thôi mình đi nhé…`;
      const more = evt.items.length > 1 ? ` và ${evt.items.length - 1} món nữa` : '';
      return `Cho mình ${a.qty} ${a.name}${more}!`;
    }

    async function run(evt, idx) {
      const el = document.createElement('div');
      el.className = 'ss-cust walking';
      el.innerHTML = '<div class="ss-bubble"></div><div class="ss-pay"></div><span class="ss-face"></span><span class="ss-name"></span>';
      el.querySelector('.ss-face').textContent = PEOPLE[Math.floor(Math.random() * PEOPLE.length)];
      el.querySelector('.ss-name').textContent = evt.customer;
      root.appendChild(el);
      const bubble = el.querySelector('.ss-bubble');
      const pay = el.querySelector('.ss-pay');

      await sleep(rand(0, 450));                        // lệch nhịp để khách không đi cùng lúc
      void el.offsetWidth;
      el.style.left = SLOT_X[idx] + '%';
      await sleep(o.walkMs);
      el.classList.remove('walking');

      bubble.textContent = speech(evt);                 // nói món muốn mua
      bubble.classList.toggle('miss', evt.status === 'missed');
      bubble.classList.add('on');
      await sleep(o.orderMs);
      bubble.classList.remove('on');

      if (evt.status === 'served') {
        pop.textContent = evt.items[0].emoji;           // đưa hàng qua quầy
        pop.classList.add('on');
        await sleep(o.serveMs);
        pop.classList.remove('on');
        if (evt.revenue > 0) {                          // tiền hiện trên đầu khách
          pay.textContent = money(evt.revenue, o.currency);
          pay.classList.add('on');
        }
        if (typeof o.onSale === 'function') o.onSale(evt);
        await sleep(o.payMs);
      }

      el.classList.add('walking');                      // quay đi
      el.style.left = '116%';
      await sleep(o.leaveMs);
      el.remove();
      slots[idx] = null;
      pump();
      applyOpen();
    }

    function pump() {
      while (queue.length) {
        const idx = slots.indexOf(null);
        if (idx < 0) return;
        const evt = queue.shift();
        slots[idx] = evt;
        run(evt, idx);
      }
    }

    return {
      setOpen(open) { wantOpen = !!open; applyOpen(); },

      /* orders: mảng đơn từ server. silent = true: chỉ ghi nhớ đã thấy, không vẽ (lần tải trang đầu tiên). */
      push(orders, opt) {
        const silent = !!(opt && opt.silent);
        (Array.isArray(orders) ? orders : [orders]).forEach(raw => {
          const evt = normalizeEvent(raw, o.emojiFor);
          if (evt.id != null) {                         // server gửi lại cũng không vẽ hai lần
            if (seen.has(evt.id)) return;
            seen.add(evt.id);
          }
          if (!silent) queue.push(evt);
        });
        while (queue.length > MAX_QUEUE) queue.shift();
        pump();
        applyOpen();
      },

      /* n người chê giá: đi ngang qua phố phía sau, không vào quán */
      walkBy(n) {
        if (document.hidden) return;
        for (let i = 0; i < Math.min(n | 0, 3); i++) {
          setTimeout(() => {
            const w = document.createElement('div');
            w.className = 'ss-walker';
            w.textContent = PEOPLE[Math.floor(Math.random() * PEOPLE.length)];
            if (i === 0) { const t = document.createElement('i'); t.textContent = 'đắt quá 💸'; w.appendChild(t); }
            root.appendChild(w);
            void w.offsetWidth;
            w.style.left = '108%';
            setTimeout(() => w.remove(), 6200);
          }, i * 900);
        }
      }
    };
  }

  global.ShopScene = { mount, normalizeEvent };
})(window);
