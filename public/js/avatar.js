// public/js/avatar.js - Renderer Avatar dùng chung cho Profile và Map
const SKIN_TONES   = ['#ffd6a5','#e8b88a','#c68a5e','#8d5a3b','#5a3018'];
const HAIR_COLORS  = ['#2b1b12','#5b3a1e','#c0860c','#d4c075','#f0f0f0','#c87878'];

function norm(c){c=String(c||'');return c.length===4?'#'+c[1]+c[1]+c[2]+c[2]+c[3]+c[3]:c;}
function mix(a,b,t){
  const q=h=>[1,3,5].map(i=>parseInt(h.slice(i,i+2),16));
  const A=q(a),B=q(b);
  return '#'+A.map((v,i)=>Math.round(v+(B[i]-v)*t).toString(16).padStart(2,'0')).join('');
}
const dark=(c,t=.18)=>mix(c,'#000000',t);
const light=(c,t=.25)=>mix(c,'#ffffff',t);

function avatarInner(p){
  p = p || {};
  const skin=norm(SKIN_TONES[p.avatar_skin_tone??2]??SKIN_TONES[2]);
  const age=p.avatar_age_group??'adult';
  const grey={teen:0,young:0,adult:0,middle:.2,senior:.5,elder:.8}[age]??0;
  const hair=mix(norm(HAIR_COLORS[p.avatar_hair_color??0]??HAIR_COLORS[0]),'#d8d8d8',grey);
  const hairD=dark(hair,.25), hairL=light(hair,.3);
  const oc=norm(p.avatar_outfit_color??'#5b8dd9'), ocD=dark(oc,.2), ocL=light(oc,.5);
  const o=p.avatar_outfit??0, hs=p.avatar_hair_style??0, es=p.avatar_eye_style??0, hat=p.avatar_hat??0, gl=p.avatar_glasses??0;
  const sc={teen:.88,young:.96,adult:1,middle:1,senior:.97,elder:.94}[age]??1;
  const sk2=dark(skin,.12);
  const OL='stroke="#4a2f1d" stroke-opacity=".5" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round"';
  const ink='#2a1810';
  let g=`<ellipse cx="90" cy="226" rx="36" ry="6" fill="#3c230f" opacity=".18"/>`;

  // tóc phía sau (kiểu dài)
  if(hs===2) g+=`<path d="M58 56 Q50 118 66 130 L114 130 Q130 118 122 56 Q120 22 90 22 Q60 22 58 56Z" fill="${hairD}" ${OL}/>`;

  // chân + giày
  const pants=o===4?'#f7f1e6':'#4f6f9f';
  if(o===1||o===5){
    g+=`<rect x="74" y="150" width="14" height="64" rx="7" fill="${skin}" ${OL}/><rect x="92" y="150" width="14" height="64" rx="7" fill="${skin}" ${OL}/>`;
  } else {
    g+=`<rect x="70" y="146" width="19" height="70" rx="8" fill="${pants}" ${OL}/><rect x="91" y="146" width="19" height="70" rx="8" fill="${pants}" ${OL}/>
        <path d="M80 160 L80 210 M100 160 L100 210" stroke="${dark(pants,.12)}" stroke-width="1.2" opacity=".6"/>`;
  }
  for(const x of [79,101]) g+=`<ellipse cx="${x}" cy="219" rx="13" ry="7" fill="#b5503a" ${OL}/><path d="M${x-12} 222 Q${x} 227 ${x+12} 222" stroke="#fff" stroke-width="2" fill="none" opacity=".85"/>`;

  // cổ + tai
  g+=`<rect x="82" y="86" width="16" height="16" rx="6" fill="${sk2}"/>`;
  g+=`<circle cx="61" cy="66" r="5.5" fill="${skin}" ${OL}/><circle cx="119" cy="66" r="5.5" fill="${skin}" ${OL}/>`;

  // thân áo
  const longSleeve=[0,2,4].includes(o);
  if(o===1){
    g+=`<path d="M66 98 Q90 91 114 98 L116 128 L130 182 Q90 192 50 182 L64 128Z" fill="${oc}" ${OL}/><path d="M64 128 L116 128" stroke="${ocD}" stroke-width="3"/>
        <path d="M76 98 Q90 112 104 98" fill="${skin}" stroke="none"/>`;
  } else if(o===4){
    g+=`<path d="M66 98 Q90 91 114 98 L118 150 L114 202 L90 198 L66 202 L62 150Z" fill="${oc}" ${OL}/><path d="M90 198 L90 150" stroke="${ocD}" stroke-width="1.5"/>
        <rect x="82" y="94" width="16" height="8" rx="3" fill="${ocL}" ${OL}/>`;
  } else {
    g+=`<path d="M65 98 Q90 90 115 98 L118 152 Q90 158 62 152Z" fill="${oc}" ${OL}/>`;
    if(o===0) g+=`<path d="M82 94 L90 108 L98 94 L98 100 L90 112 L82 100Z" fill="${ocL}" ${OL}/><path d="M90 112 L90 152" stroke="${ocD}" stroke-width="1.4"/>
        <circle cx="90" cy="122" r="1.5" fill="${ocD}"/><circle cx="90" cy="134" r="1.5" fill="${ocD}"/><circle cx="90" cy="146" r="1.5" fill="${ocD}"/>`;
    if(o===2) g+=`<path d="M80 96 L100 96 L99 152 L81 152Z" fill="#f4efe6" ${OL}/><path d="M80 96 L82 152 M100 96 L98 152" stroke="${ocD}" stroke-width="2"/>`;
    if(o===3) g+=`<path d="M78 97 Q90 108 102 97" fill="${skin}" ${OL}/>`;
    if(o===5){ g+=`<path d="M64 120 L116 120" stroke="#fff" stroke-width="4" opacity=".85"/><rect x="66" y="150" width="48" height="28" rx="8" fill="${ocD}" ${OL}/>`; }
  }
  // tay
  const armL='M66 103 Q52 126 57 149', armR='M114 103 Q128 126 123 149';
  for(const d of [armL,armR]){
    if(longSleeve) g+=`<path d="${d}" stroke="#4a2f1d" stroke-opacity=".5" stroke-width="16" fill="none" stroke-linecap="round"/><path d="${d}" stroke="${oc}" stroke-width="13" fill="none" stroke-linecap="round"/>`;
    else g+=`<path d="${d}" stroke="#4a2f1d" stroke-opacity=".5" stroke-width="14" fill="none" stroke-linecap="round"/><path d="${d}" stroke="${skin}" stroke-width="11.5" fill="none" stroke-linecap="round"/>`;
  }
  if(!longSleeve) for(const d of ['M66 103 Q60 113 58 121','M114 103 Q120 113 122 121']) g+=`<path d="${d}" stroke="${oc}" stroke-width="14" fill="none" stroke-linecap="round"/>`;
  g+=`<circle cx="57" cy="152" r="7" fill="${skin}" ${OL}/><circle cx="123" cy="152" r="7" fill="${skin}" ${OL}/>`;

  // đầu + mặt
  g+=`<ellipse cx="90" cy="62" rx="29" ry="31" fill="${skin}" ${OL}/>`;
  g+=`<ellipse cx="71" cy="76" rx="5.5" ry="3.2" fill="#ff8f7a" opacity=".3"/><ellipse cx="109" cy="76" rx="5.5" ry="3.2" fill="#ff8f7a" opacity=".3"/>`;
  const brow=dark(hair,.1);
  g+=`<path d="M72 57 Q78 54 84 57 M96 57 Q102 54 108 57" stroke="${brow}" stroke-width="2.4" fill="none" stroke-linecap="round"/>`;
  for(const x of [78,102]){
    if(es===0) g+=`<ellipse cx="${x}" cy="66" rx="3.6" ry="4.4" fill="${ink}"/><circle cx="${x+1.2}" cy="64.4" r="1.3" fill="#fff"/>`;
    if(es===1) g+=`<ellipse cx="${x}" cy="66" rx="4.8" ry="5.6" fill="${ink}"/><circle cx="${x+1.6}" cy="63.8" r="1.7" fill="#fff"/><circle cx="${x-1.5}" cy="68" r="1" fill="#fff"/>`;
    if(es===2) g+=`<path d="M${x-5} 67 Q${x} 60 ${x+5} 67" stroke="${ink}" stroke-width="2.6" fill="none" stroke-linecap="round"/>`;
    if(es===3) g+=`<ellipse cx="${x}" cy="66" rx="4.4" ry="2.8" fill="${ink}"/><path d="M${x-5.5} 63.5 Q${x} 61 ${x+5.5} 63.5" stroke="${ink}" stroke-width="1.8" fill="none" stroke-linecap="round"/>`;
  }
  g+=`<path d="M88 72 Q90 75 92 72" stroke="${dark(skin,.28)}" stroke-width="1.6" fill="none" stroke-linecap="round"/>`;
  g+=`<path d="M83 80 Q90 86 97 80" stroke="#8a3f35" stroke-width="2.2" fill="none" stroke-linecap="round"/>`;
  if(grey>=.5) g+=`<path d="M70 63 L66 61 M70 68 L66 69 M110 63 L114 61 M110 68 L114 69 M80 79 Q78 84 81 88 M100 79 Q102 84 99 88" stroke="${dark(skin,.25)}" stroke-width="1.1" fill="none" opacity=".7" stroke-linecap="round"/>`;

  // tóc phía trước
  const cap=`M60 62 Q56 29 90 26 Q124 29 120 62 Q118 48 108 42 Q96 48 74 44 Q64 50 60 62Z`;
  if(hs===0) g+=`<path d="${cap}" fill="${hair}" ${OL}/><path d="M76 37 Q90 31 104 37" stroke="${hairL}" stroke-width="2.4" fill="none" opacity=".7" stroke-linecap="round"/>`;
  if(hs===1) g+=`<path d="M59 60 Q54 90 67 88 L67 62Z" fill="${hair}" ${OL}/><path d="M121 60 Q126 90 113 88 L113 62Z" fill="${hair}" ${OL}/>
      <path d="M60 62 Q56 28 90 26 Q124 28 120 62 Q112 40 90 40 Q68 40 60 62Z" fill="${hair}" ${OL}/><path d="M74 35 Q90 30 106 35" stroke="${hairL}" stroke-width="2.4" fill="none" opacity=".7" stroke-linecap="round"/>`;
  if(hs===2) g+=`<path d="M60 62 Q56 28 90 26 Q124 28 120 62 Q110 42 90 44 Q70 42 60 62Z" fill="${hair}" ${OL}/><path d="M60 62 Q56 90 62 100 L66 66Z M120 62 Q124 90 118 100 L114 66Z" fill="${hair}" ${OL}/>
      <path d="M74 35 Q90 30 106 35" stroke="${hairL}" stroke-width="2.4" fill="none" opacity=".7" stroke-linecap="round"/>`;
  if(hs===3) g+=`<circle cx="90" cy="22" r="12" fill="${hair}" ${OL}/><path d="${cap}" fill="${hair}" ${OL}/><rect x="82" y="30" width="16" height="4" rx="2" fill="#e76f51"/>`;
  if(hs===4){ g+=`<ellipse cx="90" cy="46" rx="30" ry="18" fill="${hair}"/>`;
      for(const [x,y,r] of [[66,42,11],[78,34,12],[92,30,12],[106,34,12],[116,44,11],[61,58,9],[119,58,9]]) g+=`<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}" ${OL}/>`; }
  if(hs===5) g+=`<path d="M60 58 L62 36 L72 44 L76 24 L86 40 L92 20 L100 40 L110 26 L112 44 L120 36 L120 58 Q112 46 90 44 Q68 46 60 58Z" fill="${hair}" ${OL}/>`;

  // mũ
  if(hat===1) g+=`<path d="M60 52 Q62 25 90 24 Q118 25 120 52 Q90 45 60 52Z" fill="#e8903a" ${OL}/><path d="M88 50 Q120 45 136 55 Q120 60 88 54Z" fill="#c9722a" ${OL}/><circle cx="90" cy="25" r="2.5" fill="#c9722a"/>`;
  if(hat===2) g+=`<ellipse cx="90" cy="42" rx="46" ry="9" fill="#e8c882" ${OL}/><path d="M66 42 Q66 18 90 18 Q114 18 114 42Z" fill="#f0d594" ${OL}/><rect x="66" y="33" width="48" height="6" fill="#c0504a"/>`;
  if(hat===3) g+=`<ellipse cx="90" cy="40" rx="36" ry="7" fill="#2b2b33" ${OL}/><rect x="68" y="8" width="44" height="32" rx="4" fill="#2b2b33" ${OL}/><rect x="68" y="30" width="44" height="6" fill="#b5503a"/>`;

  // kính
  if(gl===1) g+=`<rect x="68" y="59" width="19" height="13" rx="5" fill="#1f1f26" opacity=".92"/><rect x="93" y="59" width="19" height="13" rx="5" fill="#1f1f26" opacity=".92"/><path d="M87 64 L93 64 M68 63 L61 61 M112 63 L119 61" stroke="#1f1f26" stroke-width="2"/>`;
  if(gl===2) g+=`<circle cx="78" cy="66" r="9" fill="#fff" fill-opacity=".18" stroke="#6b4a2a" stroke-width="2"/><circle cx="102" cy="66" r="9" fill="#fff" fill-opacity=".18" stroke="#6b4a2a" stroke-width="2"/><path d="M87 65 L93 65 M69 64 L61 62 M111 64 L119 62" stroke="#6b4a2a" stroke-width="2"/>`;

  return `<g transform="translate(90 228) scale(${sc}) translate(-90 -228)">${g}</g>`;
}

function avatarSVG(p,vb='0 0 180 240',w=180,h=240){
  return `<svg viewBox="${vb}" width="${w}" height="${h}" style="display:block">${avatarInner(p)}</svg>`;
}

function randomAvatarProfile(seed){
  const r = seed !== undefined ? (function(){
    let s = (seed | 0) ^ 0x9e3779b9;
    return function(){
      s = (s + 0x6d2b79f5) | 0;
      let t = Math.imul(s ^ (s >>> 15), 1 | s);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  })() : Math.random;

  const pick = arr => arr[Math.floor(r() * arr.length)];
  const randInt = max => Math.floor(r() * max);
  const OUTFIT_COLORS = ['#5b8dd9','#e76f51','#4a8a3a','#9c7ac9','#f0b832','#c94040','#ffffff','#2a1800','#20b2aa','#e87a90','#34495e','#d35400'];
  const AGES = ['teen', 'young', 'adult', 'middle', 'senior'];

  return {
    avatar_age_group: pick(AGES),
    avatar_skin_tone: randInt(SKIN_TONES.length),
    avatar_hair_style: randInt(6),
    avatar_hair_color: randInt(HAIR_COLORS.length),
    avatar_eye_style: randInt(4),
    avatar_outfit: randInt(6),
    avatar_outfit_color: pick(OUTFIT_COLORS),
    avatar_hat: r() < 0.35 ? (1 + randInt(3)) : 0,
    avatar_glasses: r() < 0.3 ? (1 + randInt(2)) : 0
  };
}

window.AvatarRenderer = { SKIN_TONES, HAIR_COLORS, norm, mix, dark, light, avatarInner, avatarSVG, randomAvatarProfile };
