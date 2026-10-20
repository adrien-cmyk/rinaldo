/* Rinaldo Marasco — scripts communs */
/* Mode Proximité — 1,7 s → zoom centré (par œuvre via data-zoom), navigation dès le début */
function initProx(frame){
  const img=frame.querySelector('img'), dot=frame.querySelector('.dot');
  const ZOOM=parseFloat(frame.dataset.zoom||'4'), LAT=800, EZ=0.0026, EO=0.035, EP=0.055, NAV=1.03;
  let inside=false,timer=null,nav=false,scale=1,tScale=1,px=.5,py=.5,tx=.5,ty=.5,raf=false;
  function loop(){
    const e=(tScale>scale)?EZ:EO; scale+=(tScale-scale)*e;
    nav=(tScale===ZOOM && scale>=NAV); if(!nav){tx=.5;ty=.5;}
    px+=(tx-px)*EP; py+=(ty-py)*EP;
    const w=frame.clientWidth,h=frame.clientHeight;
    img.style.transform='translate('+(px*w-px*w*scale)+'px,'+(py*h-py*h*scale)+'px) scale('+scale+')';
    if(Math.abs(tScale-scale)>.0005||Math.abs(tx-px)>.0005||Math.abs(ty-py)>.0005) requestAnimationFrame(loop); else raf=false;
  }
  function kick(){ if(!raf){raf=true;requestAnimationFrame(loop);} }
  function pt(ev){ const r=frame.getBoundingClientRect(); const cx=ev.touches?ev.touches[0].clientX:ev.clientX, cy=ev.touches?ev.touches[0].clientY:ev.clientY;
    return [Math.min(1,Math.max(0,(cx-r.left)/r.width)),Math.min(1,Math.max(0,(cy-r.top)/r.height)),cx-r.left,cy-r.top]; }
  frame.addEventListener('mouseenter',ev=>{ inside=true; frame.classList.add('is-inside'); tx=.5;ty=.5;
    const p=pt(ev); if(dot){dot.style.left=p[2]+'px';dot.style.top=p[3]+'px';}
    clearTimeout(timer); timer=setTimeout(()=>{ if(inside){tScale=ZOOM;kick();} },LAT); });
  frame.addEventListener('mousemove',ev=>{ const p=pt(ev); if(dot){dot.style.left=p[2]+'px';dot.style.top=p[3]+'px';} if(nav){tx=p[0];ty=p[1];kick();} });
  frame.addEventListener('mouseleave',()=>{ inside=false; frame.classList.remove('is-inside'); clearTimeout(timer); tScale=1;tx=.5;ty=.5;kick(); });
  /* --- tactile : pincement à deux doigts (zoom manuel) --- */
  let pScale=1, pox=0, poy=0, startDist=0, startScale=1, imgAx=0, imgAy=0, panX=0, panY=0;
  function _dist(t){ return Math.hypot(t[0].clientX-t[1].clientX, t[0].clientY-t[1].clientY); }
  function _cen(t){ const r=frame.getBoundingClientRect(); return [ (t[0].clientX+t[1].clientX)/2 - r.left, (t[0].clientY+t[1].clientY)/2 - r.top ]; }
  function _applyP(){ const w=frame.clientWidth, h=frame.clientHeight;
    pox=Math.min(0, Math.max(w*(1-pScale), pox)); poy=Math.min(0, Math.max(h*(1-pScale), poy));
    img.style.transition=''; img.style.transform='translate('+pox+'px,'+poy+'px) scale('+pScale+')'; }
  frame.addEventListener('touchstart', ev=>{
    const t=ev.touches;
    if(t.length===2){ startDist=_dist(t); startScale=pScale; const c=_cen(t); imgAx=(c[0]-pox)/pScale; imgAy=(c[1]-poy)/pScale; if(ev.cancelable) ev.preventDefault(); }
    else if(t.length===1){ const r=frame.getBoundingClientRect(); panX=t[0].clientX-r.left; panY=t[0].clientY-r.top; }
  }, {passive:false});
  frame.addEventListener('touchmove', ev=>{
    const t=ev.touches;
    if(t.length===2){ const nd=_dist(t); let ns=startDist? startScale*(nd/startDist):startScale; ns=Math.max(1, Math.min(ZOOM, ns));
      const c=_cen(t); pScale=ns; pox=c[0]-imgAx*ns; poy=c[1]-imgAy*ns;
      frame.style.touchAction = pScale>1.01 ? 'none' : 'pan-y'; _applyP(); if(ev.cancelable) ev.preventDefault(); }
    else if(t.length===1 && pScale>1.01){ const r=frame.getBoundingClientRect(); const x=t[0].clientX-r.left, y=t[0].clientY-r.top;
      pox+=x-panX; poy+=y-panY; panX=x; panY=y; _applyP(); if(ev.cancelable) ev.preventDefault(); }
  }, {passive:false});
  frame.addEventListener('touchend', ev=>{
    if(ev.touches.length===1){ const r=frame.getBoundingClientRect(); panX=ev.touches[0].clientX-r.left; panY=ev.touches[0].clientY-r.top; }
    if(ev.touches.length===0 && pScale<=1.02){ pScale=1; pox=0; poy=0; img.style.transition='transform .3s ease'; img.style.transform='translate(0,0) scale(1)'; frame.style.touchAction='pan-y'; }
  }, {passive:false});
}
document.querySelectorAll('.prox').forEach(initProx);

/* loupe : révélation au survol */
(function(){
  const disc=document.getElementById('discover'); if(!disc) return;
  const img=disc.querySelector('img');
  disc.addEventListener('mousemove',e=>{ const r=disc.getBoundingClientRect(); img.style.setProperty('--mx',(e.clientX-r.left)+'px'); img.style.setProperty('--my',(e.clientY-r.top)+'px'); });
  disc.addEventListener('mouseenter',()=>disc.classList.add('lit'));
  disc.addEventListener('mouseleave',()=>disc.classList.remove('lit'));
})();

(function(){ const t=document.getElementById('toworks'), w=document.getElementById('works'); if(t&&w) t.addEventListener('click',()=>w.scrollIntoView({behavior:'smooth'})); })();

/* lightbox */
(function(){
  const lb=document.getElementById('lb'), lbImg=document.getElementById('lbImg'); if(!lb) return;
  let group=[], idx=0;
  const show=()=>lbImg.src=group[idx];
  const open=(l,i)=>{group=l;idx=i;show();lb.classList.add('open');lb.setAttribute('aria-hidden','false');};
  const close=()=>{lb.classList.remove('open');lb.setAttribute('aria-hidden','true');lbImg.src='';};
  const step=d=>{idx=(idx+d+group.length)%group.length;show();};
  document.querySelectorAll('.d-row').forEach(row=>{
    const list=[...row.querySelectorAll('.thumb')].map(b=>b.dataset.full);
    row.querySelectorAll('.thumb').forEach((b,i)=>b.addEventListener('click',()=>open(list,i)));
  });
  document.getElementById('lbClose').addEventListener('click',close);
  document.getElementById('lbPrev').addEventListener('click',()=>step(-1));
  document.getElementById('lbNext').addEventListener('click',()=>step(1));
  lb.addEventListener('click',e=>{ if(e.target===lb) close(); });
  document.addEventListener('keydown',e=>{ if(!lb.classList.contains('open'))return; if(e.key==='Escape')close(); else if(e.key==='ArrowLeft')step(-1); else if(e.key==='ArrowRight')step(1); });
})();
(function(){var w=document.getElementById("worksMenu");if(!w)return;var t=w.querySelector("a");t.addEventListener("click",function(e){e.preventDefault();w.classList.toggle("open");});document.addEventListener("click",function(e){if(!w.contains(e.target))w.classList.remove("open");});})();
(function(){var bg=document.getElementById("burger");if(bg)bg.addEventListener("click",function(){document.body.classList.toggle("menu-open");});var mm=document.getElementById("mobileMenu");if(mm)mm.querySelectorAll("a").forEach(function(a){a.addEventListener("click",function(){document.body.classList.remove("menu-open");});});})();
