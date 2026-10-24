/*
 * shop-scene.js — cảnh trong quán cho Startup Street (v3, khớp JSON của shop-tick.php)
 *
 * Góc nhìn: người chơi là CHỦ QUÁN đứng sau quầy nên KHÔNG vẽ chủ quán, chỉ vẽ khách.
 * Khách (avatar hoạt hình cùng kiểu với trang hồ sơ) đẩy cửa bước vào, đi tới quầy ở dưới cùng màn hình,
 * nói món muốn mua -> nhận hàng -> "+tiền" nổi lên -> quay ra cửa. Hết hàng thì khách nói "hết rồi à?" và bỏ về;
 * người chê giá chỉ đi ngang qua cửa sổ.
 *
 * Module này CHỈ VẼ. Tiền/khách/kho do server quyết định, ở đây không tính gì.
 *
 *   const scene = ShopScene.mount(el, { emojiFor: productId => '🧋' });
 *   scene.setGoods(['🧋','🍵']);                        // (tuỳ chọn) hàng bày trên kệ sau lưng
 *   scene.setOpen(true);
 *   scene.push(state.orders, { silent: firstState });   // mảng orders của shop-tick.php
 *   scene.walkBy(state.walked_by);
 *
 * Phần tử mount nên có chiều cao do trang quyết định (vd. flex:1). Mép dưới của cảnh chính là mép trên của quầy:
 * trang đặt quầy ngay bên dưới, chân khách bị quầy che đi cho đúng chiều sâu.
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

  /* ───────────── Avatar hoạt hình (cùng bộ vẽ với profile.html) ───────────── */
  const SKIN = ['#ffd6a5', '#e8b88a', '#c68a5e', '#8d5a3b', '#5a3018'];
  const HAIR = ['#2b1b12', '#5b3a1e', '#c0860c', '#d4c075', '#f0f0f0', '#c87878'];
  const OUTFIT = ['#5b8dd9', '#e76f51', '#4a8a3a', '#9c7ac9', '#f0b832', '#c94040', '#ffffff', '#2a1800'];
  const AGES = ['teen', 'young', 'young', 'adult', 'adult', 'adult', 'middle', 'middle', 'senior', 'elder'];

  const hex6 = c => { c = String(c); return c.length === 4 ? '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3] : c; };
  function mix(a, b, t) {
    const q = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
    const A = q(a), B = q(b);
    return '#' + A.map((v, i) => Math.round(v + (B[i] - v) * t).toString(16).padStart(2, '0')).join('');
  }
  const dark = (c, t) => mix(c, '#000000', t === undefined ? 0.18 : t);
  const light = (c, t) => mix(c, '#ffffff', t === undefined ? 0.25 : t);

  /* Vẽ thân avatar trong khung 180x240 (chân ở y~226). p = hồ sơ avatar. */
  function avatarInner(p) {
    const skin = hex6(SKIN[p.skin] || SKIN[2]);
    const age = p.age || 'adult';
    const grey = { teen: 0, young: 0, adult: 0, middle: 0.2, senior: 0.5, elder: 0.8 }[age] || 0;
    const hair = mix(hex6(HAIR[p.hairColor] || HAIR[0]), '#d8d8d8', grey);
    const hairD = dark(hair, 0.25), hairL = light(hair, 0.3);
    const oc = hex6(p.outfitColor || '#5b8dd9'), ocD = dark(oc, 0.2), ocL = light(oc, 0.5);
    const o = p.outfit | 0, hs = p.hairStyle | 0, es = p.eyes | 0, hat = p.hat | 0, gl = p.glasses | 0;
    const sc = { teen: 0.88, young: 0.96, adult: 1, middle: 1, senior: 0.97, elder: 0.94 }[age] || 1;
    const sk2 = dark(skin, 0.12);
    const OL = 'stroke="#4a2f1d" stroke-opacity=".5" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round"';
    const ink = '#2a1810';
    let g = '<ellipse cx="90" cy="226" rx="36" ry="6" fill="#3c230f" opacity=".18"/>';

    if (hs === 2) g += `<path d="M58 56 Q50 118 66 130 L114 130 Q130 118 122 56 Q120 22 90 22 Q60 22 58 56Z" fill="${hairD}" ${OL}/>`;

    const pants = o === 4 ? '#f7f1e6' : '#4f6f9f';
    if (o === 1 || o === 5) {
      g += `<rect x="74" y="150" width="14" height="64" rx="7" fill="${skin}" ${OL}/><rect x="92" y="150" width="14" height="64" rx="7" fill="${skin}" ${OL}/>`;
    } else {
      g += `<rect x="70" y="146" width="19" height="70" rx="8" fill="${pants}" ${OL}/><rect x="91" y="146" width="19" height="70" rx="8" fill="${pants}" ${OL}/>
        <path d="M80 160 L80 210 M100 160 L100 210" stroke="${dark(pants, 0.12)}" stroke-width="1.2" opacity=".6"/>`;
    }
    for (const x of [79, 101]) g += `<ellipse cx="${x}" cy="219" rx="13" ry="7" fill="#b5503a" ${OL}/><path d="M${x - 12} 222 Q${x} 227 ${x + 12} 222" stroke="#fff" stroke-width="2" fill="none" opacity=".85"/>`;

    g += `<rect x="82" y="86" width="16" height="16" rx="6" fill="${sk2}"/>`;
    g += `<circle cx="61" cy="66" r="5.5" fill="${skin}" ${OL}/><circle cx="119" cy="66" r="5.5" fill="${skin}" ${OL}/>`;

    const longSleeve = [0, 2, 4].includes(o);
    if (o === 1) {
      g += `<path d="M66 98 Q90 91 114 98 L116 128 L130 182 Q90 192 50 182 L64 128Z" fill="${oc}" ${OL}/><path d="M64 128 L116 128" stroke="${ocD}" stroke-width="3"/>
        <path d="M76 98 Q90 112 104 98" fill="${skin}" stroke="none"/>`;
    } else if (o === 4) {
      g += `<path d="M66 98 Q90 91 114 98 L118 150 L114 202 L90 198 L66 202 L62 150Z" fill="${oc}" ${OL}/><path d="M90 198 L90 150" stroke="${ocD}" stroke-width="1.5"/>
        <rect x="82" y="94" width="16" height="8" rx="3" fill="${ocL}" ${OL}/>`;
    } else {
      g += `<path d="M65 98 Q90 90 115 98 L118 152 Q90 158 62 152Z" fill="${oc}" ${OL}/>`;
      if (o === 0) g += `<path d="M82 94 L90 108 L98 94 L98 100 L90 112 L82 100Z" fill="${ocL}" ${OL}/><path d="M90 112 L90 152" stroke="${ocD}" stroke-width="1.4"/>
        <circle cx="90" cy="122" r="1.5" fill="${ocD}"/><circle cx="90" cy="134" r="1.5" fill="${ocD}"/><circle cx="90" cy="146" r="1.5" fill="${ocD}"/>`;
      if (o === 2) g += `<path d="M80 96 L100 96 L99 152 L81 152Z" fill="#f4efe6" ${OL}/><path d="M80 96 L82 152 M100 96 L98 152" stroke="${ocD}" stroke-width="2"/>`;
      if (o === 3) g += `<path d="M78 97 Q90 108 102 97" fill="${skin}" ${OL}/>`;
      if (o === 5) g += `<path d="M64 120 L116 120" stroke="#fff" stroke-width="4" opacity=".85"/><rect x="66" y="150" width="48" height="28" rx="8" fill="${ocD}" ${OL}/>`;
    }
    const armL = 'M66 103 Q52 126 57 149', armR = 'M114 103 Q128 126 123 149';
    for (const d of [armL, armR]) {
      if (longSleeve) g += `<path d="${d}" stroke="#4a2f1d" stroke-opacity=".5" stroke-width="16" fill="none" stroke-linecap="round"/><path d="${d}" stroke="${oc}" stroke-width="13" fill="none" stroke-linecap="round"/>`;
      else g += `<path d="${d}" stroke="#4a2f1d" stroke-opacity=".5" stroke-width="14" fill="none" stroke-linecap="round"/><path d="${d}" stroke="${skin}" stroke-width="11.5" fill="none" stroke-linecap="round"/>`;
    }
    if (!longSleeve) for (const d of ['M66 103 Q60 113 58 121', 'M114 103 Q120 113 122 121']) g += `<path d="${d}" stroke="${oc}" stroke-width="14" fill="none" stroke-linecap="round"/>`;
    g += `<circle cx="57" cy="152" r="7" fill="${skin}" ${OL}/><circle cx="123" cy="152" r="7" fill="${skin}" ${OL}/>`;

    g += `<ellipse cx="90" cy="62" rx="29" ry="31" fill="${skin}" ${OL}/>`;
    g += '<ellipse cx="71" cy="76" rx="5.5" ry="3.2" fill="#ff8f7a" opacity=".3"/><ellipse cx="109" cy="76" rx="5.5" ry="3.2" fill="#ff8f7a" opacity=".3"/>';
    const brow = dark(hair, 0.1);
    g += `<path d="M72 57 Q78 54 84 57 M96 57 Q102 54 108 57" stroke="${brow}" stroke-width="2.4" fill="none" stroke-linecap="round"/>`;
    for (const x of [78, 102]) {
      if (es === 0) g += `<ellipse cx="${x}" cy="66" rx="3.6" ry="4.4" fill="${ink}"/><circle cx="${x + 1.2}" cy="64.4" r="1.3" fill="#fff"/>`;
      if (es === 1) g += `<ellipse cx="${x}" cy="66" rx="4.8" ry="5.6" fill="${ink}"/><circle cx="${x + 1.6}" cy="63.8" r="1.7" fill="#fff"/><circle cx="${x - 1.5}" cy="68" r="1" fill="#fff"/>`;
      if (es === 2) g += `<path d="M${x - 5} 67 Q${x} 60 ${x + 5} 67" stroke="${ink}" stroke-width="2.6" fill="none" stroke-linecap="round"/>`;
      if (es === 3) g += `<ellipse cx="${x}" cy="66" rx="4.4" ry="2.8" fill="${ink}"/><path d="M${x - 5.5} 63.5 Q${x} 61 ${x + 5.5} 63.5" stroke="${ink}" stroke-width="1.8" fill="none" stroke-linecap="round"/>`;
    }
    g += `<path d="M88 72 Q90 75 92 72" stroke="${dark(skin, 0.28)}" stroke-width="1.6" fill="none" stroke-linecap="round"/>`;
    g += '<path d="M83 80 Q90 86 97 80" stroke="#8a3f35" stroke-width="2.2" fill="none" stroke-linecap="round"/>';
    if (grey >= 0.5) g += `<path d="M70 63 L66 61 M70 68 L66 69 M110 63 L114 61 M110 68 L114 69 M80 79 Q78 84 81 88 M100 79 Q102 84 99 88" stroke="${dark(skin, 0.25)}" stroke-width="1.1" fill="none" opacity=".7" stroke-linecap="round"/>`;

    const cap = 'M60 62 Q56 29 90 26 Q124 29 120 62 Q118 48 108 42 Q96 48 74 44 Q64 50 60 62Z';
    if (hs === 0) g += `<path d="${cap}" fill="${hair}" ${OL}/><path d="M76 37 Q90 31 104 37" stroke="${hairL}" stroke-width="2.4" fill="none" opacity=".7" stroke-linecap="round"/>`;
    if (hs === 1) g += `<path d="M59 60 Q54 90 67 88 L67 62Z" fill="${hair}" ${OL}/><path d="M121 60 Q126 90 113 88 L113 62Z" fill="${hair}" ${OL}/>
      <path d="M60 62 Q56 28 90 26 Q124 28 120 62 Q112 40 90 40 Q68 40 60 62Z" fill="${hair}" ${OL}/><path d="M74 35 Q90 30 106 35" stroke="${hairL}" stroke-width="2.4" fill="none" opacity=".7" stroke-linecap="round"/>`;
    if (hs === 2) g += `<path d="M60 62 Q56 28 90 26 Q124 28 120 62 Q110 42 90 44 Q70 42 60 62Z" fill="${hair}" ${OL}/><path d="M60 62 Q56 90 62 100 L66 66Z M120 62 Q124 90 118 100 L114 66Z" fill="${hair}" ${OL}/>
      <path d="M74 35 Q90 30 106 35" stroke="${hairL}" stroke-width="2.4" fill="none" opacity=".7" stroke-linecap="round"/>`;
    if (hs === 3) g += `<circle cx="90" cy="22" r="12" fill="${hair}" ${OL}/><path d="${cap}" fill="${hair}" ${OL}/><rect x="82" y="30" width="16" height="4" rx="2" fill="#e76f51"/>`;
    if (hs === 4) {
      g += `<ellipse cx="90" cy="46" rx="30" ry="18" fill="${hair}"/>`;
      for (const [x, y, r] of [[66, 42, 11], [78, 34, 12], [92, 30, 12], [106, 34, 12], [116, 44, 11], [61, 58, 9], [119, 58, 9]]) g += `<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}" ${OL}/>`;
    }
    if (hs === 5) g += `<path d="M60 58 L62 36 L72 44 L76 24 L86 40 L92 20 L100 40 L110 26 L112 44 L120 36 L120 58 Q112 46 90 44 Q68 46 60 58Z" fill="${hair}" ${OL}/>`;

    if (hat === 1) g += `<path d="M60 52 Q62 25 90 24 Q118 25 120 52 Q90 45 60 52Z" fill="#e8903a" ${OL}/><path d="M88 50 Q120 45 136 55 Q120 60 88 54Z" fill="#c9722a" ${OL}/><circle cx="90" cy="25" r="2.5" fill="#c9722a"/>`;
    if (hat === 2) g += `<ellipse cx="90" cy="42" rx="46" ry="9" fill="#e8c882" ${OL}/><path d="M66 42 Q66 18 90 18 Q114 18 114 42Z" fill="#f0d594" ${OL}/><rect x="66" y="33" width="48" height="6" fill="#c0504a"/>`;
    if (hat === 3) g += `<ellipse cx="90" cy="40" rx="36" ry="7" fill="#2b2b33" ${OL}/><rect x="68" y="8" width="44" height="32" rx="4" fill="#2b2b33" ${OL}/><rect x="68" y="30" width="44" height="6" fill="#b5503a"/>`;

    if (gl === 1) g += '<rect x="68" y="59" width="19" height="13" rx="5" fill="#1f1f26" opacity=".92"/><rect x="93" y="59" width="19" height="13" rx="5" fill="#1f1f26" opacity=".92"/><path d="M87 64 L93 64 M68 63 L61 61 M112 63 L119 61" stroke="#1f1f26" stroke-width="2"/>';
    if (gl === 2) g += '<circle cx="78" cy="66" r="9" fill="#fff" fill-opacity=".18" stroke="#6b4a2a" stroke-width="2"/><circle cx="102" cy="66" r="9" fill="#fff" fill-opacity=".18" stroke="#6b4a2a" stroke-width="2"/><path d="M87 65 L93 65 M69 64 L61 62 M111 64 L119 62" stroke="#6b4a2a" stroke-width="2"/>';

    return `<g transform="translate(90 228) scale(${sc}) translate(-90 -228)">${g}</g>`;
  }
  const avatarSvg = p => `<svg viewBox="0 0 180 240" preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false">${avatarInner(p)}</svg>`;

  /* Số ngẫu nhiên có hạt giống: cùng một đơn luôn ra cùng một gương mặt (vẽ lại không bị "đổi người"). */
  function rng(seed) {
    let s = (seed | 0) ^ 0x9e3779b9;
    return () => {
      s = (s + 0x6D2B79F5) | 0;
      let t = Math.imul(s ^ (s >>> 15), 1 | s);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  function randomProfile(seed) {
    const r = rng(seed);
    const pick = a => a[Math.floor(r() * a.length)];
    return {
      age: pick(AGES),
      skin: Math.floor(r() * SKIN.length),
      hairStyle: Math.floor(r() * 6),
      hairColor: r() < 0.08 ? 5 : Math.floor(r() * 4),
      eyes: Math.floor(r() * 4),
      outfit: Math.floor(r() * 6),
      outfitColor: pick(OUTFIT),
      hat: r() < 0.22 ? 1 + Math.floor(r() * 3) : 0,
      glasses: r() < 0.2 ? 1 + Math.floor(r() * 2) : 0
    };
  }

  /* ───────────── Giao diện ───────────── */
  const DOOR_X = 9;                       // % chiều ngang: cửa ra vào ở góc trái
  const DOOR_B = 44;                      // % chiều cao: chân cửa nằm trên đường nối tường - sàn
  const DOOR_S = 0.42;                    // khách ở xa (cửa) nhìn nhỏ hơn
  const SLOT_X = [30, 50, 70];            // % chiều ngang chỗ đứng trước quầy
  const SLOT_B = -3;                      // % chiều cao: âm = chân bị quầy che bớt
  const MAX_QUEUE = 8;                    // xếp hàng quá dài thì bỏ bớt khách cũ khỏi cảnh (tiền vẫn đã tính ở server)

  const CSS = `
.ss-scene{--wood-d:#4a2a16;--wood:#8a5a3b;--wood-l:#b9835a;--cream:#fff6dd;--ok:#4c8a26;--no:#a63a31;
  --ss-ch:clamp(170px,40vh,400px);
  position:relative;width:100%;height:360px;min-height:260px;overflow:hidden;isolation:isolate;
  background:#efe3c4;font-family:'Baloo 2',system-ui,sans-serif;color:var(--wood-d)}
/* tường + sàn */
.ss-wall{position:absolute;left:0;right:0;top:0;bottom:45%;
  background:linear-gradient(#f6ead0 0 62%,#cfe2cb 62% 100%);
  border-bottom:6px solid #7a5336}
.ss-wall::before{content:'';position:absolute;inset:0 0 38% 0;
  background:repeating-linear-gradient(90deg,#0000 0 54px,#c9b88e22 54px 56px)}
.ss-floor{position:absolute;left:0;right:0;top:55%;bottom:0;
  background:repeating-linear-gradient(90deg,#0000 0 118px,#6b432733 118px 121px),
             repeating-linear-gradient(0deg,#d9aa75 0 46px,#cf9d68 46px 92px)}
.ss-floor::after{content:'';position:absolute;inset:0;background:linear-gradient(#0003,#0000 40%)}
/* diềm mái hiên trên cùng */
.ss-valance{position:absolute;left:0;right:0;top:0;height:30px;z-index:3;
  background:repeating-linear-gradient(90deg,#d95b4f 0 28px,#fff6dd 28px 56px);border-bottom:4px solid var(--wood-d)}
/* đèn treo */
.ss-lamp{position:absolute;top:30px;width:3px;height:6%;background:var(--wood-d);z-index:2}
.ss-lamp::after{content:'';position:absolute;left:-17px;top:100%;width:37px;height:22px;border-radius:20px 20px 4px 4px;
  background:#ffd36b;box-shadow:0 8px 34px 10px #ffd36b66}
/* cửa ra vào */
.ss-door{position:absolute;left:3%;bottom:45%;width:12%;height:46%;z-index:1;
  background:linear-gradient(90deg,#a87245,#c08754 50%,#a87245);border:5px solid var(--wood-d);border-bottom:0;border-radius:10px 10px 0 0}
.ss-door::before{content:'';position:absolute;left:14%;right:14%;top:12%;height:34%;background:#bfe3f5;border:4px solid var(--wood-d);border-radius:6px}
.ss-door::after{content:'';position:absolute;right:10%;top:56%;width:9px;height:9px;border-radius:50%;background:#f2b441;border:2px solid var(--wood-d)}
.ss-sign{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:2;padding:0 8px;border-radius:8px;
  background:var(--cream);border:3px solid var(--wood-d);font-weight:800;font-size:12px;white-space:nowrap;color:var(--ok)}
.ss-scene.is-closed .ss-sign{color:var(--no)}
/* cửa sổ nhìn ra phố */
.ss-window{position:absolute;left:21%;top:16%;width:27%;height:30%;z-index:1;border:6px solid var(--wood-d);border-radius:8px;overflow:hidden;
  background:linear-gradient(#bfe3f5 0 55%,#cdbfa8 55% 100%);box-shadow:inset 0 0 0 3px #fff6dd88}
.ss-window::after{content:'';position:absolute;left:50%;top:0;bottom:0;width:5px;margin-left:-2px;background:var(--wood-d)}
.ss-walker{position:absolute;bottom:2%;left:-18%;height:62%;aspect-ratio:3/4;transition:left 6s linear}
.ss-walker svg{display:block;width:100%;height:100%}
.ss-walker i{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font:700 10px/1 'Baloo 2',system-ui,sans-serif;font-style:normal;
  white-space:nowrap;background:var(--cream);border:2px solid var(--wood);border-radius:8px;padding:2px 5px}
/* kệ hàng sau lưng */
.ss-shelves{position:absolute;right:3%;top:14%;width:35%;height:34%;z-index:1;display:flex;flex-direction:column;
  border:5px solid var(--wood-d);border-radius:8px;background:#b9835a;box-shadow:inset 0 0 0 3px #d4a874}
.ss-shelves div{flex:1;display:flex;align-items:flex-end;justify-content:space-around;padding:0 6px 2px;font-size:clamp(18px,3.4vh,32px);line-height:1;
  border-bottom:6px solid var(--wood-d)}
.ss-shelves div:last-child{border-bottom:0}
.ss-plant{position:absolute;z-index:1;bottom:46%;font-size:clamp(26px,6vh,56px);line-height:1}
/* khách */
.ss-cust{position:absolute;z-index:2;height:var(--ss-ch);aspect-ratio:3/4;left:-20%;bottom:0;opacity:0;
  transform-origin:50% 100%;
  transition:left var(--ss-walk,1.2s) ease-in-out,bottom var(--ss-walk,1.2s) ease-in-out,transform var(--ss-walk,1.2s) ease-in-out,opacity .4s}
.ss-body{width:100%;height:100%}
.ss-body svg{display:block;width:100%;height:100%;overflow:visible;filter:drop-shadow(0 4px 3px #0003)}
.ss-cust.walking .ss-body{animation:ss-bob .45s ease-in-out infinite}
@keyframes ss-bob{50%{transform:translateY(-3%)}}
.ss-name{position:absolute;left:50%;top:-2px;transform:translate(-50%,-100%);font-size:12px;font-weight:700;white-space:nowrap;
  background:#fff6ddcc;border:2px solid var(--wood);border-radius:999px;padding:0 9px}
.ss-bubble{position:absolute;bottom:calc(100% + 30px);left:50%;transform:translateX(-50%) scale(.8);transform-origin:50% 100%;
  min-width:110px;max-width:190px;padding:6px 10px;background:var(--cream);border:3px solid var(--wood);border-radius:14px;
  font-size:14px;font-weight:700;line-height:1.2;text-align:center;opacity:0;transition:opacity .2s,transform .2s;pointer-events:none}
.ss-bubble::after{content:'';position:absolute;left:50%;top:100%;margin-left:-7px;border:7px solid transparent;border-top-color:var(--wood)}
.ss-bubble.miss{border-color:var(--no);color:var(--no)}
.ss-bubble.miss::after{border-top-color:var(--no)}
.ss-bubble.on{opacity:1;transform:translateX(-50%) scale(1)}
.ss-want{display:block;font-size:26px;line-height:1.1}
.ss-pay{position:absolute;bottom:calc(100% + 30px);left:50%;transform:translateX(-50%);font-weight:800;font-size:24px;color:var(--ok);
  white-space:nowrap;text-shadow:0 2px 0 #fff,0 0 8px #fff;opacity:0}
.ss-pay.on{animation:ss-float 1.1s ease-out forwards}
@keyframes ss-float{0%{opacity:0;transform:translate(-50%,10px)}20%{opacity:1}100%{opacity:0;transform:translate(-50%,-40px)}}
/* đóng cửa */
.ss-closed{position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#2a160acc;
  font-weight:800;font-size:clamp(20px,3.4vw,34px);color:var(--cream);z-index:6;text-align:center;padding:0 16px}
.ss-scene.is-closed .ss-closed{display:flex}
@media (prefers-reduced-motion:reduce){.ss-cust.walking .ss-body,.ss-pay.on{animation:none}.ss-pay.on{opacity:1}.ss-cust,.ss-walker{transition-duration:.01s!important}}
@media (max-width:640px){.ss-shelves{display:none}.ss-window{width:40%}}
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
      currency: 'đ', walkMs: 1500, orderMs: 1900, serveMs: 600, payMs: 1100, leaveMs: 1300,
      emojiFor: () => '🧋', onSale: null
    }, opts);

    root.classList.add('ss-scene', 'is-closed');
    root.style.setProperty('--ss-walk', o.walkMs + 'ms');
    root.innerHTML =
      '<div class="ss-wall"></div><div class="ss-floor"></div>' +
      '<div class="ss-valance"></div>' +
      '<i class="ss-lamp" style="left:34%"></i><i class="ss-lamp" style="left:68%"></i>' +
      '<div class="ss-door"><span class="ss-sign">MỞ CỬA</span></div>' +
      '<div class="ss-window"></div>' +
      '<div class="ss-shelves"><div></div><div></div></div>' +
      '<span class="ss-plant" style="left:16.5%" aria-hidden="true">🪴</span>' +
      '<div class="ss-closed">Quán đang đóng cửa</div>';
    const sign = root.querySelector('.ss-sign');
    const windowEl = root.querySelector('.ss-window');
    const shelfRows = root.querySelectorAll('.ss-shelves div');

    const slots = SLOT_X.map(() => null);
    const queue = [];
    const seen = new Set();
    let wantOpen = false;

    // Đóng cửa thì chờ khách đang có trong cảnh phục vụ xong rồi mới hiện biển "đóng cửa"
    function applyOpen() {
      const idle = !queue.length && slots.every(s => s === null);
      root.classList.toggle('is-closed', !wantOpen && idle);
      sign.textContent = wantOpen ? 'MỞ CỬA' : 'ĐÓNG CỬA';
    }

    function speech(evt) {
      const a = evt.items[0];
      if (evt.status === 'missed') return { text: `Hết ${a.name} rồi à? Thôi mình đi nhé…`, emoji: '' };
      const more = evt.items.length > 1 ? ` và ${evt.items.length - 1} món nữa` : '';
      return { text: `Cho mình ${a.qty} ${a.name}${more}!`, emoji: a.emoji };
    }

    const place = (el, leftPct, bottomPct, scale) => {
      el.style.left = leftPct + '%';
      el.style.bottom = bottomPct + '%';
      el.style.transform = `translateX(-50%) scale(${scale})`;
    };

    async function run(evt, idx) {
      const el = document.createElement('div');
      el.className = 'ss-cust walking';
      const seed = evt.id != null ? evt.id : Math.floor(Math.random() * 1e9);
      el.innerHTML = '<div class="ss-body"></div><span class="ss-name"></span><div class="ss-bubble"></div><div class="ss-pay"></div>';
      el.querySelector('.ss-body').innerHTML = avatarSvg(randomProfile(seed));
      el.querySelector('.ss-name').textContent = evt.customer;
      place(el, DOOR_X, DOOR_B, DOOR_S);                 // xuất phát ở cửa, nhỏ vì ở xa
      root.appendChild(el);
      const bubble = el.querySelector('.ss-bubble');
      const pay = el.querySelector('.ss-pay');

      await sleep(rand(0, 450));                          // lệch nhịp để khách không bước vào cùng lúc
      void el.offsetWidth;
      el.style.opacity = '1';
      place(el, SLOT_X[idx], SLOT_B, 1);                  // đi tới quầy, to dần
      await sleep(o.walkMs);
      el.classList.remove('walking');

      const sp = speech(evt);                             // nói món muốn mua
      bubble.textContent = sp.text;
      if (sp.emoji) {
        const em = document.createElement('span');
        em.className = 'ss-want';
        em.textContent = sp.emoji;
        bubble.prepend(em);
      }
      bubble.classList.toggle('miss', evt.status === 'missed');
      bubble.classList.add('on');
      await sleep(o.orderMs);
      bubble.classList.remove('on');

      if (evt.status === 'served') {
        await sleep(o.serveMs);                           // chủ quán đưa hàng (không vẽ chủ quán)
        if (evt.revenue > 0) {                            // tiền hiện trên đầu khách
          pay.textContent = money(evt.revenue, o.currency);
          pay.classList.add('on');
        }
        if (typeof o.onSale === 'function') o.onSale(evt);
        await sleep(o.payMs);
      }

      el.classList.add('walking');                        // quay ra cửa
      place(el, DOOR_X, DOOR_B, DOOR_S);
      setTimeout(() => { el.style.opacity = '0'; }, Math.max(0, o.leaveMs - 450));
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

      /* emojis: hàng bày trên kệ sau lưng (tuỳ chọn). */
      setGoods(emojis) {
        const list = (emojis || []).filter(Boolean);
        shelfRows.forEach((row, r) => {
          row.textContent = '';
          for (let i = 0; i < 5 && list.length; i++) {
            const s = document.createElement('span');
            s.textContent = list[(i + r * 2) % list.length];
            row.appendChild(s);
          }
        });
      },

      /* orders: mảng đơn từ server. silent = true: chỉ ghi nhớ đã thấy, không vẽ (lần tải trang đầu tiên). */
      push(orders, opt) {
        const silent = !!(opt && opt.silent);
        (Array.isArray(orders) ? orders : [orders]).forEach(raw => {
          const evt = normalizeEvent(raw, o.emojiFor);
          if (evt.id != null) {                           // server gửi lại cũng không vẽ hai lần
            if (seen.has(evt.id)) return;
            seen.add(evt.id);
          }
          if (!silent) queue.push(evt);
        });
        while (queue.length > MAX_QUEUE) queue.shift();
        pump();
        applyOpen();
      },

      /* n người chê giá: đi ngang qua cửa sổ, không vào quán */
      walkBy(n) {
        if (document.hidden) return;
        for (let i = 0; i < Math.min(n | 0, 3); i++) {
          setTimeout(() => {
            const w = document.createElement('div');
            w.className = 'ss-walker';
            w.innerHTML = avatarSvg(randomProfile(Math.floor(Math.random() * 1e9)));
            if (i === 0) { const t = document.createElement('i'); t.textContent = 'đắt quá 💸'; w.appendChild(t); }
            windowEl.appendChild(w);
            void w.offsetWidth;
            w.style.left = '110%';
            setTimeout(() => w.remove(), 6200);
          }, i * 900);
        }
      }
    };
  }

  global.ShopScene = { mount, normalizeEvent, avatarSvg, randomProfile };
})(window);
