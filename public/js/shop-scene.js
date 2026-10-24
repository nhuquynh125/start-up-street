/*
 * shop-scene.js — cảnh trong quán cho Startup Street (v3, khớp JSON của shop-tick.php)
 * Góc nhìn của CHỦ QUÁN đứng sau quầy: chỉ thấy khách. Khách đi vào từ cửa bên trái, đứng trước quầy,
 * nói món muốn mua, nhận hàng, "+tiền" nổi lên rồi ra về. Hết hàng thì khách buồn bỏ về; chê giá thì chỉ đi ngang qua cửa kính.
 * Khách được vẽ cùng phong cách avatar ở màn "Tùy chỉnh cư dân" (profile.html).
 *
 * Module này CHỈ VẼ. Tiền/khách/kho do server quyết định.
 * Phần quầy hàng (kệ hàng) do trang đặt ngay dưới #stage; khách bị quầy che nửa thân dưới.
 *
 *   const scene = ShopScene.mount(el, { emojiFor: productId => '🧋' });
 *   scene.setOpen(true);
 *   scene.push(state.orders, { silent: firstState });
 *   scene.walkBy(state.walked_by);
 */
(function (global) {
  'use strict';

  function normalizeEvent(raw, emojiFor) {
    return {
      id: raw.id,
      status: raw.status === 'served' ? 'served' : 'missed',
      customer: raw.customer || '',
      items: [{ name: String(raw.product_name || 'món'), qty: Number(raw.quantity) || 1, emoji: emojiFor(raw.product_id) }],
      revenue: Number(raw.total) || 0
    };
  }

  const SLOT_X = [24, 50, 76];   // % chiều ngang chỗ khách đứng trước quầy
  const MAX_QUEUE = 8;           // xếp hàng quá dài thì bỏ bớt khách cũ khỏi cảnh (tiền vẫn đã tính ở server)
  const pick = a => a[Math.floor(Math.random() * a.length)];
  const rand = (a, b) => a + Math.random() * (b - a);
  const sleep = ms => (document.hidden ? Promise.resolve() : new Promise(r => setTimeout(r, ms)));
  const money = (n, cur) => '+' + Math.round(n).toLocaleString('vi-VN') + cur;

  /* ───────── Vẽ một người (nửa thân trên, nhìn thẳng) cùng nét vẽ với avatar trong profile.html ───────── */
  function shade(hex, t) {          // t > 0: pha trắng, t < 0: pha đen
    const c = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16)), k = t < 0 ? 0 : 255, a = Math.abs(t);
    return '#' + c.map(v => Math.round(v + (k - v) * a).toString(16).padStart(2, '0')).join('');
  }
  function person(sad) {
    const skin = pick(['#ffd6a5', '#e8b88a', '#c68a5e', '#8d5a3b']);
    const hair = pick(['#2b1b12', '#5b3a1e', '#c0860c', '#d4c075', '#9a9a9a']);
    const oc = pick(['#5b8dd9', '#e76f51', '#4a8a3a', '#9c7ac9', '#f0b832', '#c94040', '#7ab8b0']);
    const hs = Math.floor(Math.random() * 4), es = Math.floor(Math.random() * 3);
    const hat = Math.random() < .14, glasses = Math.random() < .2, dress = Math.random() < .3;
    const OL = 'stroke="#4a2f1d" stroke-opacity=".5" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"';
    const ink = '#2a1810';
    let g = '';
    if (hs === 1) g += `<path d="M31 52 Q26 108 42 118 L78 118 Q94 108 89 52 Q88 18 60 18 Q32 18 31 52Z" fill="${shade(hair, -.25)}" ${OL}/>`;
    g += `<path d="M20 150 Q20 100 60 97 Q100 100 100 150Z" fill="${oc}" ${OL}/>`;
    g += `<rect x="52" y="82" width="16" height="18" rx="6" fill="${shade(skin, -.12)}"/>`;
    g += dress ? `<path d="M46 99 Q60 114 74 99" fill="${skin}" ${OL}/>`
               : `<path d="M50 97 L60 112 L70 97 L66 97 L60 105 L54 97Z" fill="${shade(oc, .5)}" ${OL}/>`;
    g += `<circle cx="31" cy="55" r="5.5" fill="${skin}" ${OL}/><circle cx="89" cy="55" r="5.5" fill="${skin}" ${OL}/>`;
    g += `<ellipse cx="60" cy="52" rx="29" ry="31" fill="${skin}" ${OL}/>`;
    g += `<ellipse cx="41" cy="65" rx="5.5" ry="3.2" fill="#ff8f7a" opacity=".32"/><ellipse cx="79" cy="65" rx="5.5" ry="3.2" fill="#ff8f7a" opacity=".32"/>`;
    const by = sad ? 47 : 49;
    g += sad ? `<path d="M42 ${by + 3} L53 ${by} M78 ${by + 3} L67 ${by}" stroke="${shade(hair, -.1)}" stroke-width="2.4" fill="none" stroke-linecap="round"/>`
             : `<path d="M42 ${by} Q48 46 54 ${by} M66 ${by} Q72 46 78 ${by}" stroke="${shade(hair, -.1)}" stroke-width="2.4" fill="none" stroke-linecap="round"/>`;
    for (const x of [48, 72]) {
      if (es === 0) g += `<ellipse cx="${x}" cy="57" rx="3.6" ry="4.4" fill="${ink}"/><circle cx="${x + 1.2}" cy="55.4" r="1.3" fill="#fff"/>`;
      if (es === 1) g += `<ellipse cx="${x}" cy="57" rx="4.6" ry="5.4" fill="${ink}"/><circle cx="${x + 1.5}" cy="54.9" r="1.7" fill="#fff"/><circle cx="${x - 1.4}" cy="59" r="1" fill="#fff"/>`;
      if (es === 2) g += `<path d="M${x - 5} 58 Q${x} 51 ${x + 5} 58" stroke="${ink}" stroke-width="2.6" fill="none" stroke-linecap="round"/>`;
    }
    g += `<path d="M58 63 Q60 66 62 63" stroke="${shade(skin, -.28)}" stroke-width="1.6" fill="none" stroke-linecap="round"/>`;
    g += sad ? `<path d="M53 76 Q60 70 67 76" stroke="#8a3f35" stroke-width="2.2" fill="none" stroke-linecap="round"/>`
             : `<path d="M53 70 Q60 77 67 70" stroke="#8a3f35" stroke-width="2.2" fill="none" stroke-linecap="round"/>`;
    const cap = 'M30 52 Q26 20 60 17 Q94 20 90 52 Q88 38 78 33 Q66 39 44 35 Q34 41 30 52Z';
    if (hs === 0) g += `<path d="${cap}" fill="${hair}" ${OL}/>`;
    if (hs === 1) g += `<path d="M30 52 Q26 19 60 17 Q94 19 90 52 Q80 31 60 33 Q40 31 30 52Z" fill="${hair}" ${OL}/>`;
    if (hs === 2) g += `<circle cx="60" cy="12" r="11" fill="${hair}" ${OL}/><path d="${cap}" fill="${hair}" ${OL}/>`;
    if (hs === 3) for (const [x, y, r] of [[36, 33, 11], [48, 25, 12], [62, 22, 12], [76, 25, 12], [86, 35, 11], [31, 48, 9], [89, 48, 9]]) g += `<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}" ${OL}/>`;
    if (hat) g += `<path d="M30 42 Q32 15 60 14 Q88 15 90 42 Q60 35 30 42Z" fill="${shade(oc, -.1)}" ${OL}/><path d="M58 40 Q90 35 106 45 Q90 50 58 44Z" fill="${shade(oc, -.3)}" ${OL}/>`;
    if (glasses) g += `<circle cx="48" cy="57" r="9" fill="#fff" fill-opacity=".2" stroke="#6b4a2a" stroke-width="2"/><circle cx="72" cy="57" r="9" fill="#fff" fill-opacity=".2" stroke="#6b4a2a" stroke-width="2"/><path d="M57 56 L63 56" stroke="#6b4a2a" stroke-width="2"/>`;
    return `<svg class="ss-svg" viewBox="0 0 120 150" aria-hidden="true">${g}</svg>`;
  }

  const CSS = `
.ss-scene{--wood-d:#4a2a16;--wood:#8a5a3b;--wood-l:#b9835a;--cream:#fff6dd;--ok:#4c8a26;--no:#a63a31;--pw:clamp(96px,min(15vw,26vh),180px);
  position:relative;flex-shrink:0;height:230px;overflow:hidden;border:4px solid var(--wood);border-radius:18px;
  background:linear-gradient(#f3e4c0 0 58%,#c99567 58% 100%);font-family:'Baloo 2',system-ui,sans-serif;color:var(--wood-d)}
.ss-wall{position:absolute;inset:0 0 42% 0;background:repeating-linear-gradient(90deg,#f7e8c6 0 46px,#f1dfb8 46px 48px)}
.ss-floor{position:absolute;inset:58% 0 0 0;background:repeating-linear-gradient(90deg,#0000 0 78px,#0002 78px 80px),linear-gradient(#cf9d6e,#b9835a)}
.ss-awning{position:absolute;left:0;right:0;top:0;height:7%;min-height:20px;z-index:4;
  background:repeating-linear-gradient(90deg,#d95b4f 0 28px,#fff6dd 28px 56px);border-bottom:4px solid var(--wood-d)}
.ss-window{position:absolute;left:5%;right:5%;top:12%;height:34%;border:5px solid var(--wood);border-radius:12px;overflow:hidden;
  background:linear-gradient(#bfe3f5 0 62%,#d8ccb0 62% 100%);box-shadow:inset 0 0 0 3px #fff8;z-index:1}
.ss-window::before{content:'';position:absolute;left:12%;bottom:30%;width:36px;height:60%;background:#4c9a4c;border-radius:50% 50% 8px 8px;box-shadow:0 6px 0 -2px #35692a}
.ss-window::after{content:'';position:absolute;right:10%;top:12%;width:70px;height:20px;background:#fff;border-radius:14px;box-shadow:20px -8px 0 2px #fff}
.ss-door{position:absolute;left:0;bottom:42%;width:7%;min-width:44px;height:50%;z-index:1;background:#f6dca4;border:4px solid var(--wood-d);border-left:0;border-radius:0 10px 0 0}
.ss-cust{position:absolute;z-index:3;bottom:-4%;width:var(--pw);margin-left:calc(var(--pw) / -2);left:-14%;
  transition:left var(--ss-walk,1.2s) ease-in-out}
.ss-cust .ss-svg{display:block;width:100%;height:auto;overflow:visible;filter:drop-shadow(0 4px 0 #3c230f22)}
.ss-cust.walking .ss-svg{animation:ss-bob .45s ease-in-out infinite}
@keyframes ss-bob{50%{transform:translateY(-6px)}}
.ss-bubble{position:absolute;bottom:100%;left:50%;transform:translateX(-50%) scale(.8);transform-origin:50% 100%;margin-bottom:-6px;
  width:max-content;max-width:min(220px,40vw);padding:6px 11px;background:var(--cream);border:3px solid var(--wood);border-radius:14px;
  font-size:14px;font-weight:700;line-height:1.2;text-align:center;opacity:0;transition:opacity .2s,transform .2s;pointer-events:none}
.ss-bubble::after{content:'';position:absolute;top:100%;left:50%;margin-left:-7px;border:7px solid transparent;border-top-color:var(--wood)}
.ss-bubble.miss{border-color:var(--no);color:var(--no)}.ss-bubble.miss::after{border-top-color:var(--no)}
.ss-bubble.on{opacity:1;transform:translateX(-50%) scale(1)}
.ss-item{position:absolute;left:50%;bottom:6%;font-size:38px;line-height:1;opacity:0;transform:translateX(-50%);transition:opacity .2s,bottom .5s}
.ss-item.on{opacity:1;bottom:20%}
.ss-pay{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font-weight:800;font-size:22px;color:var(--ok);
  white-space:nowrap;text-shadow:0 2px 0 #fff;opacity:0;z-index:2}
.ss-pay.on{animation:ss-float 1.1s ease-out forwards}
@keyframes ss-float{0%{opacity:0;transform:translate(-50%,6px)}20%{opacity:1}100%{opacity:0;transform:translate(-50%,-34px)}}
.ss-walker{position:absolute;bottom:-14%;left:-8%;width:54px;opacity:.9;transition:left 6s linear}
.ss-walker .ss-svg{width:100%;height:auto;display:block}
.ss-walker i{position:absolute;bottom:92%;left:50%;transform:translateX(-50%);font:700 11px/1 'Baloo 2',system-ui,sans-serif;
  font-style:normal;white-space:nowrap;background:var(--cream);border:2px solid var(--wood);border-radius:8px;padding:2px 6px}
.ss-closed{position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#4a2a16bb;
  font-weight:800;font-size:clamp(18px,3vw,26px);color:var(--cream);z-index:6}
.ss-scene.is-closed .ss-closed{display:flex}
@media (prefers-reduced-motion:reduce){.ss-cust.walking .ss-svg,.ss-pay.on{animation:none}.ss-pay.on{opacity:1}}
`;

  function injectCss() {
    if (document.getElementById('ss-css')) return;
    const s = document.createElement('style');
    s.id = 'ss-css';
    s.textContent = CSS;
    document.head.appendChild(s);
  }

  function mount(root, opts) {
    injectCss();
    const o = Object.assign({ currency: 'đ', walkMs: 1200, orderMs: 1900, serveMs: 700, payMs: 1100, leaveMs: 1000,
      emojiFor: () => '🧋', onSale: null }, opts);

    root.classList.add('ss-scene', 'is-closed');
    root.style.setProperty('--ss-walk', o.walkMs + 'ms');
    root.innerHTML = '<div class="ss-wall"></div><div class="ss-floor"></div><div class="ss-awning"></div>' +
      '<div class="ss-window"></div><div class="ss-door"></div><div class="ss-closed">Quán đang đóng cửa</div>';
    const windowEl = root.querySelector('.ss-window');

    const slots = SLOT_X.map(() => null);
    const queue = [];
    const seen = new Set();
    let wantOpen = false;

    function applyOpen() {   // đóng cửa thì chờ khách đang trong quán phục vụ xong mới hiện biển
      const idle = !queue.length && slots.every(s => s === null);
      root.classList.toggle('is-closed', !wantOpen && idle);
    }

    function speech(evt) {
      const a = evt.items[0];
      if (evt.status === 'missed') return `Hết ${a.name} rồi à? Thôi mình đi nhé…`;
      return `${evt.customer ? evt.customer + ': ' : ''}Cho mình ${a.qty} ${a.name}!`;
    }

    async function run(evt, idx) {
      const el = document.createElement('div');
      el.className = 'ss-cust walking';
      el.innerHTML = '<div class="ss-bubble"></div><div class="ss-pay"></div>' + person(evt.status === 'missed') + '<div class="ss-item"></div>';
      root.appendChild(el);
      const bubble = el.querySelector('.ss-bubble'), pay = el.querySelector('.ss-pay'), item = el.querySelector('.ss-item');

      await sleep(rand(0, 450));                        // lệch nhịp để khách không đi cùng lúc
      void el.offsetWidth;
      el.style.left = SLOT_X[idx] + '%';
      await sleep(o.walkMs);
      el.classList.remove('walking');

      bubble.textContent = speech(evt);
      bubble.classList.toggle('miss', evt.status === 'missed');
      bubble.classList.add('on');
      await sleep(o.orderMs);
      bubble.classList.remove('on');

      if (evt.status === 'served') {
        item.textContent = evt.items[0].emoji;          // hàng được đưa tới khách
        item.classList.add('on');
        await sleep(o.serveMs);
        item.classList.remove('on');
        if (evt.revenue > 0) { pay.textContent = money(evt.revenue, o.currency); pay.classList.add('on'); }
        if (typeof o.onSale === 'function') o.onSale(evt);
        await sleep(o.payMs);
      }

      el.classList.add('walking');                      // quay ra cửa
      el.style.left = '-16%';
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

      /* silent = true: chỉ ghi nhớ đã thấy, không vẽ (lần tải trang đầu tiên) */
      push(orders, opt) {
        const silent = !!(opt && opt.silent);
        (Array.isArray(orders) ? orders : [orders]).forEach(raw => {
          const evt = normalizeEvent(raw, o.emojiFor);
          if (evt.id != null) {
            if (seen.has(evt.id)) return;
            seen.add(evt.id);
          }
          if (!silent) queue.push(evt);
        });
        while (queue.length > MAX_QUEUE) queue.shift();
        pump();
        applyOpen();
      },

      /* n người chê giá: đi ngang qua cửa kính, không vào quán */
      walkBy(n) {
        if (document.hidden) return;
        for (let i = 0; i < Math.min(n | 0, 3); i++) {
          setTimeout(() => {
            const w = document.createElement('div');
            w.className = 'ss-walker';
            w.innerHTML = person(false);
            if (i === 0) { const t = document.createElement('i'); t.textContent = 'đắt quá 💸'; w.appendChild(t); }
            windowEl.appendChild(w);
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
