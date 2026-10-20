/* Rinaldo Marasco — accueil (rapprochement) */
(function(){
  const frame = document.getElementById('prox');
  const img   = document.getElementById('artwork');
  const dot   = document.getElementById('dot');
  const down  = document.getElementById('down');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ----- Mode Proximité ----------------------------------------
     Principes (cf. brief) :
     - le cadre ne bouge pas, l'image grandit derrière ;
     - 1,7 s de survol continu avant que le rapprochement commence ;
     - l'approche démarre focalisée sur le CENTRE de l'œuvre ;
     - dès que le zoom a débuté, on peut naviguer (déplacer la souris)
       dans l'image, avec inertie ; le focus au centre n'agit qu'au tout début ;
     - retour lent et recentrage à la sortie.                       */

  const ZOOM      = parseFloat(frame.dataset.zoom || '2') || 2;  // 200 % par défaut (réglable dans l'admin)
  const LATENCY   = 1700;   // 1,7 s de survol continu avant le rapprochement
  const EASE_ZOOM = 0.0032; // approche très lente, contemplative
  const EASE_OUT  = 0.035;  // vitesse de retour
  const EASE_PAN  = 0.055;  // inertie du regard
  const NAV_FROM  = 1.03;   // navigation dès que le zoom a débuté (recentrage au tout début seulement)

  let inside=false, timer=null, navigable=false;
  let scale=1, targetScale=1;
  let px=.5, py=.5, tx=.5, ty=.5;   // focale (0..1) — départ au centre
  let rafActive=false;

  function loop(){
    const ease = (targetScale>scale) ? EASE_ZOOM : EASE_OUT;
    scale += (targetScale-scale)*ease;

    // navigation possible dès que le zoom a débuté ; recentrage seulement au tout début
    navigable = (targetScale===ZOOM && scale >= NAV_FROM);
    if(!navigable){ tx=.5; ty=.5; }   // tout début de l'approche : focalisation au centre

    px += (tx-px)*EASE_PAN;
    py += (ty-py)*EASE_PAN;

    const w=frame.clientWidth, h=frame.clientHeight;
    // le point (px,py) de l'image reste sous le point (px,py) du cadre
    const x = px*w - px*w*scale;
    const y = py*h - py*h*scale;
    img.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;

    if (Math.abs(targetScale-scale)>.0005 || Math.abs(tx-px)>.0005 || Math.abs(ty-py)>.0005){
      requestAnimationFrame(loop);
    } else { rafActive=false; }
  }
  function kick(){ if(!rafActive){ rafActive=true; requestAnimationFrame(loop);} }

  function pointFromEvent(e){
    const r = frame.getBoundingClientRect();
    const cx = (e.touches? e.touches[0].clientX : e.clientX);
    const cy = (e.touches? e.touches[0].clientY : e.clientY);
    return [ Math.min(1,Math.max(0,(cx-r.left)/r.width)),
             Math.min(1,Math.max(0,(cy-r.top)/r.height)),
             cx-r.left, cy-r.top ];
  }

  /* --- souris --- */
  frame.addEventListener('mouseenter', e=>{
    inside=true; frame.classList.add('is-inside');
    tx=.5; ty=.5;                               // le rapprochement vise le centre
    const [ , , rx, ry]=pointFromEvent(e);
    dot.style.left=rx+'px'; dot.style.top=ry+'px';
    clearTimeout(timer);
    timer=setTimeout(()=>{ if(inside){ targetScale=ZOOM; kick(); } }, LATENCY);
  });
  frame.addEventListener('mousemove', e=>{
    const [x,y,rx,ry]=pointFromEvent(e);
    dot.style.left=rx+'px'; dot.style.top=ry+'px';
    if(navigable){ tx=x; ty=y; kick(); }        // navigation possible une fois à 200%
  });
  frame.addEventListener('mouseleave', ()=>{
    inside=false; frame.classList.remove('is-inside');
    clearTimeout(timer);
    targetScale=1; tx=.5; ty=.5; kick();        // retour lent + recentrage
  });

  /* --- tactile : 1er toucher = approche (centre), 2e = retour --- */
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

  /* ----- flèche différée : ~4,5 s après l'arrivée ----- */
  setTimeout(()=>down.classList.add('visible'), reduced?800:4500);
  down.addEventListener('click', ()=>{
    const t=document.getElementById('entries');
    if(t) t.scrollIntoView({behavior: reduced?'auto':'smooth'});
  });

  /* ----- révélation des entrées au scroll ----- */
  const entries=document.getElementById('entries');
  if(entries){
  new IntersectionObserver(es=>{
    es.forEach(x=>{ if(x.isIntersecting) entries.classList.add('reveal'); });
  },{threshold:.35}).observe(entries); }
})();
