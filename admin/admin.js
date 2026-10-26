/* ============================================================================
   Admin Rinaldo Marasco — éditeur
   Le DOM porte les données : chaque bloc garde une référence vers son objet
   (el._d). L'ordre (glisser-déposer) est relu dans le DOM à l'enregistrement.
   Les fichiers sont envoyés immédiatement (action=upload) ; l'enregistrement
   n'envoie que du JSON (action=save).
   ============================================================================ */
(function () {
  "use strict";
  const I = window.INIT;
  let D = JSON.parse(JSON.stringify(I.data));
  let REV = I.rev;
  let dirty = false;
  let tab = sessionStorageGet("rm-tab") || "works";
  const openKeys = new Set();

  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const esc = s => String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const el = html => { const t = document.createElement("template"); t.innerHTML = html.trim(); return t.content.firstElementChild; };
  const url = p => !p ? "" : (p.startsWith("media/") ? "../media.php?f=" + encodeURIComponent(p.slice(6)) : "../" + p);
  const rid = p => p + "-" + Math.random().toString(36).slice(2, 8);
  function sessionStorageGet(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function sessionStorageSet(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }

  /* ------------------------------------------------------------ outils -- */
  function toast(msg, err) {
    const t = $("#toast"); t.textContent = msg; t.className = "toast" + (err ? " err" : ""); t.hidden = false;
    clearTimeout(t._h); t._h = setTimeout(() => (t.hidden = true), err ? 6000 : 2600);
  }
  function markDirty() {
    dirty = true;
    $("#savebar").classList.add("dirty");
    $("#saveState").textContent = "Modifications non enregistrées";
    $("#saveBtn").disabled = false;
  }
  function sortable(list, opts = {}) {
    if (!window.Sortable) return;
    Sortable.create(list, Object.assign({ handle: ".handle", animation: 160, onEnd: markDirty }, opts));
  }
  /* champ texte lié à obj[key] ; lines=true → tableau (une ligne = une entrée) */
  function field(obj, key, label, o = {}) {
    let v = obj[key];
    if (o.lines) v = (v || []).join("\n");
    if (o.paras) v = (v || []).join("\n\n");
    const tag = o.area ? `<textarea class="in" rows="${o.rows || 3}"` : `<input class="in" type="${o.type || "text"}"`;
    const f = el(`<div class="f">${label ? `<label>${esc(label)}</label>` : ""}${tag} placeholder="${esc(o.ph || "")}"${o.area ? `>${esc(v)}</textarea>` : ` value="${esc(v)}">`}</div>`);
    const inp = $(".in", f);
    inp.addEventListener("input", () => {
      let x = inp.value;
      if (o.lines) x = x.split("\n").map(s => s.trim()).filter(Boolean);
      if (o.paras) x = x.split(/\n\s*\n/).map(s => s.trim()).filter(Boolean);
      obj[key] = x;
      if (o.on) o.on(x);
      markDirty();
    });
    return f;
  }
  function select(obj, key, label, options, on) {
    const f = el(`<div class="f">${label ? `<label>${esc(label)}</label>` : ""}<select class="in">${options.map(([v, t]) => `<option value="${esc(v)}">${esc(t)}</option>`).join("")}</select></div>`);
    const s = $("select", f); s.value = String(obj[key] ?? options[0][0]);
    if (s.value === "" && options.length) s.value = options[0][0];
    s.addEventListener("change", () => { obj[key] = typeof options[0][0] === "number" ? parseFloat(s.value) : s.value; if (on) on(obj[key]); markDirty(); });
    return f;
  }

  /* ----------------------------------------------------------- envoi -- */
  function upload(file, kind = "image", onProgress, hd) {
    return new Promise((res, rej) => {
      if (file.size > I.limit) return rej(new Error(`« ${file.name} » est trop lourd (max ${Math.round(I.limit / 1048576)} Mo).`));
      const fd = new FormData(); fd.append("file", file); fd.append("kind", kind); fd.append("csrf", I.csrf); if (hd) fd.append("hd", "1");
      const x = new XMLHttpRequest();
      x.open("POST", "?action=upload");
      x.setRequestHeader("X-CSRF", I.csrf);
      if (onProgress) x.upload.onprogress = e => e.lengthComputable && onProgress(Math.round(e.loaded / e.total * 100));
      x.onload = () => { let r; try { r = JSON.parse(x.responseText); } catch (e) { return rej(new Error("Réponse serveur invalide (" + x.status + ").")); } r.ok ? res(r) : rej(new Error(r.error || "Erreur d'envoi.")); };
      x.onerror = () => rej(new Error("Connexion interrompue."));
      x.send(fd);
    });
  }
  function chooseFiles(accept, multiple) {
    return new Promise(res => {
      const i = document.createElement("input"); i.type = "file"; i.accept = accept; i.multiple = !!multiple;
      i.onchange = () => res([...i.files]); i.click();
    });
  }

  /* Sélecteur d'une image (ou vidéo) liée à obj[key] ; dims → obj.w/obj.h */
  function picker(obj, key, o = {}) {
    const video = o.kind === "video";
    const p = el(`<div class="pick ${o.wide ? "pick__wide" : ""}">
      ${o.label ? `<span class="lbl">${esc(o.label)}</span>` : ""}
      ${video ? `<div class="pick__v"></div>` : `<div class="pick__box" title="Cliquer pour choisir une image"></div>`}
      <div class="pick__row"><button type="button" class="btn2" data-a="choose"></button>${o.optional ? `<button type="button" class="btn2 btn2--danger" data-a="rm">Retirer</button>` : ""}</div>
      ${o.dims ? `<div class="pick__dim"></div>` : ""}
    </div>`);
    function paint() {
      const v = obj[key] || "";
      $('[data-a="choose"]', p).textContent = v ? (video ? "Remplacer la vidéo" : "Remplacer") : (video ? "Choisir une vidéo" : "Choisir une image");
      const rm = $('[data-a="rm"]', p); if (rm) rm.hidden = !v;
      if (video) {
        $(".pick__v", p).innerHTML = v ? `<video controls preload="metadata" src="${esc(url(v))}"></video>` : `<p class="muted">Aucune vidéo.</p>`;
      } else {
        const b = $(".pick__box", p);
        b.style.backgroundImage = v ? `url("${url(v)}")` : "";
        b.classList.toggle("has", !!v);
        b.textContent = v ? "" : "Aucune image";
      }
      const dm = $(".pick__dim", p); if (dm) dm.textContent = v && obj.w > 1 ? `${obj.w} × ${obj.h} px` + (o.hd && obj.hd ? ` · original HD conservé pour la loupe${obj._hdw ? ` (${obj._hdw} × ${obj._hdh})` : ""}` : "") : "";
    }
    async function choose() {
      const [f] = await chooseFiles(video ? "video/mp4,video/webm,video/quicktime" : "image/jpeg,image/png,image/webp,image/gif");
      if (!f) return;
      p.classList.add("busy");
      const lab = $('[data-a="choose"]', p);
      try {
        const r = await upload(f, video ? "video" : "image", pc => (lab.textContent = `Envoi… ${pc} %`), o.hd);
        if (o.hd) { obj.hd = r.hd || ""; obj._hdw = r.hdw; obj._hdh = r.hdh; }
        obj[key] = r.path;
        if (o.dims) { obj.w = r.w; obj.h = r.h; }
        if (o.on) o.on(r);
        markDirty();
      } catch (e) { toast(e.message, true); }
      p.classList.remove("busy"); paint();
    }
    $('[data-a="choose"]', p).addEventListener("click", choose);
    const box = $(".pick__box", p); if (box) box.addEventListener("click", choose);
    const rm = $('[data-a="rm"]', p);
    if (rm) rm.addEventListener("click", () => { obj[key] = ""; if (o.hd) obj.hd = ""; markDirty(); paint(); });
    paint();
    return p;
  }

  /* Galerie d'images liée à obj[key] (tableau) — glisser pour réordonner */
  function gallery(obj, key, o = {}) {
    obj[key] = obj[key] || [];
    const g = el(`<div class="gal ${o.big ? "gal--big" : ""}"></div>`);
    const add = el(`<button type="button" class="gal__add" title="Ajouter des images">+</button>`);
    function item(path) {
      const it = el(`<div class="gal__it" title="Glisser pour réordonner"><button type="button" title="Retirer">✕</button></div>`);
      it.style.backgroundImage = `url("${url(path)}")`; it._p = path;
      $("button", it).addEventListener("click", () => { it.remove(); sync(); });
      return it;
    }
    function sync() { obj[key] = $$(".gal__it", g).map(i => i._p); markDirty(); }
    obj[key].forEach(p => g.appendChild(item(p)));
    g.appendChild(add);
    add.addEventListener("click", async () => {
      const files = await chooseFiles("image/jpeg,image/png,image/webp,image/gif", true);
      for (const f of files) {
        add.textContent = "…"; g.classList.add("busy");
        try { const r = await upload(f); g.insertBefore(item(r.path), add); sync(); }
        catch (e) { toast(e.message, true); }
      }
      add.textContent = "+"; g.classList.remove("busy");
    });
    if (window.Sortable) Sortable.create(g, { draggable: ".gal__it", animation: 160, onEnd: sync });
    return g;
  }

  /* Année la plus récente trouvée dans une date libre ("2020–2021" → 2021) */
  const yearOf = s => { const m = String(s || "").match(/\d{4}/g); return m ? Math.max(...m.map(Number)) : -1; };
  function sortByYear(list, get) {
    const items = [...list.children].filter(c => c._d);
    items.map((c, i) => [c, i]).sort((a, b) => (yearOf(get(b[0]._d)) - yearOf(get(a[0]._d))) || (a[1] - b[1]))
      .forEach(([c]) => list.appendChild(c));
    markDirty();
  }
  function details(key, summaryHtml, startOpen) {
    const d = el(`<details class="blk"><summary>${summaryHtml}</summary><div class="blk__body"></div></details>`);
    if (openKeys.has(key) || startOpen) d.open = true;
    d.addEventListener("toggle", () => d.open ? openKeys.add(key) : openKeys.delete(key));
    return d;
  }

  /* ============================================================ WORKS == */
  const SPEEDS = [["slow", "Approche lente"], ["normal", "Approche normale"], ["fast", "Approche rapide"]];
  const ZOOMS = [[0, "Sans loupe"], [2, "Loupe ×2"], [3, "Loupe ×3"], [4, "Loupe ×4"], [6, "Loupe ×6"], [8, "Loupe ×8"]];

  function workRow(w, ser) {
    w.tech = w.tech || []; w.thumbs = w.thumbs || [];
    const r = el(`<div class="work">
      <span class="handle" title="Glisser pour déplacer">⠿</span>
      <div class="work__img"></div>
      <div class="work__fields">
        <div style="margin-bottom:6px"><span class="badge first-badge">Image d'en-tête de la page</span></div>
        <div class="g3 a"></div>
        <div class="g2 b"></div>
        <div class="work__opts"></div>
        <div class="f" style="margin-top:10px"><span class="lbl">Détails (vignettes sous le cartel)</span><div class="thumbs"></div></div>
      </div>
      <button type="button" class="del" title="Supprimer l'œuvre">✕</button>
    </div>`);
    r._d = w;
    $(".work__img", r).appendChild(picker(w, "image", { dims: true, hd: true }));
    $(".a", r).append(field(w, "title", "Titre"), field(w, "date", "Année", { ph: "2024" }), field(w, "dim", "Dimensions", { ph: "100 × 100 cm" }));
    $(".b", r).append(field(w, "tech", "Technique (une ligne par ligne du cartel)", { area: true, rows: 2, lines: true }));
    const opts = $(".work__opts", r);
    w.speed = w.speed || "normal";
    const sp = select(w, "speed", "", SPEEDS); sp.style.margin = "0";
    const z = select(w, "zoom", "", ZOOMS, v => { sp.hidden = !v; }); z.style.margin = "0";
    sp.hidden = !w.zoom; opts.append(z, sp);
    const lg = el(`<label class="check"><input type="checkbox"> Grand format</label>`);
    $("input", lg).checked = !!w.large; $("input", lg).addEventListener("change", e => { w.large = e.target.checked; markDirty(); });
    opts.appendChild(lg);
    const mv = el(`<label class="check">Déplacer vers <select class="in"><option value="">—</option></select></label>`);
    const ms = $("select", mv);
    (D.series || []).forEach(s => { if (s !== ser) ms.appendChild(el(`<option value="${esc(s.id)}">${esc(s.title || "(sans titre)")}</option>`)); });
    ms.addEventListener("change", () => {
      const target = $(`.ser[data-id="${CSS.escape(ms.value)}"] .works`);
      if (target) { target.prepend(r); markDirty(); toast("Œuvre déplacée en tête de « " + ms.selectedOptions[0].text + " »"); $$(".ser").forEach(x => x._head && x._head()); }
      ms.value = "";
    });
    opts.appendChild(mv);
    $(".thumbs", r).appendChild(gallery(w, "thumbs"));
    $(".del", r).addEventListener("click", () => {
      if (!confirm(`Supprimer l'œuvre « ${w.title || "sans titre"} » ?`)) return;
      const ser = r.closest(".ser"); r.remove(); markDirty(); if (ser && ser._head) ser._head();
    });
    return r;
  }

  function seriesBlock(s) {
    s.works = s.works || []; s.intro = s.intro || [];
    const key = "s:" + s.id;
    const d = details(key, `<span class="handle" title="Glisser pour réordonner">⠿</span>
      <span class="blk__title"></span><span class="blk__meta"></span><span class="badge"></span>`);
    d.classList.add("ser"); d.dataset.id = s.id; d._d = s;
    const head = () => {
      $(".blk__title", d).textContent = s.title || "Nouvelle série";
      $(".blk__meta", d).textContent = `${$$(".work", d).length} œuvre(s)`;
      $(".badge", d).textContent = s.layout === "loupe" ? "Révélation" : "En-tête";
    };
    const body = $(".blk__body", d);
    body.append(
      field(s, "title", "Titre de la série (menu Works)", { on: head }),
      field(s, "intro", "Texte d'introduction — paragraphes séparés par une ligne vide", { area: true, rows: 6, paras: true })
    );
    const g = el(`<div class="g2"></div>`);
    const disc = el(`<div class="f"></div>`);
    const works = el(`<div class="works"></div>`);
    const lay = select(s, "layout", "Mise en page de l'introduction", [["hero", "Première œuvre à droite du texte"], ["loupe", "Image « révélation » (lampe torche), comme Territories"]], v => {
      disc.hidden = v !== "loupe"; works.classList.toggle("hero-list", v === "hero"); head();
    });
    g.append(lay, select(s, "shadow", "Ombre des œuvres", [["normal", "Normale"], ["medium", "Moyenne"], ["strong", "Forte"]]));
    body.appendChild(g);
    disc.appendChild(picker(s, "discover", { label: "Image révélée sous la lampe torche", wide: true, optional: true }));
    disc.hidden = s.layout !== "loupe";
    body.appendChild(disc);

    const tb = el(`<div class="toolbar">
      <strong>Œuvres</strong><span class="sp"></span>
      <button type="button" class="btn2" data-a="sort">Trier par année (récentes d'abord)</button>
      <button type="button" class="btn2 btn2--add" data-a="add">+ Ajouter une œuvre</button>
    </div>`);
    body.appendChild(tb);
    body.appendChild(el(`<p class="hint">Glissez <b>⠿</b> pour changer l'ordre. ${"Avec la mise en page « Première œuvre », la première de la liste s'affiche à droite du texte."}</p>`));
    works.classList.toggle("hero-list", s.layout !== "loupe");
    s.works.forEach(w => works.appendChild(workRow(w, s)));
    body.appendChild(works);
    sortable(works, { group: "works", onEnd: () => { markDirty(); $$(".ser").forEach(x => x._head && x._head()); } });
    $('[data-a="add"]', tb).addEventListener("click", () => {
      const w = { id: "", title: "", date: String(new Date().getFullYear()), dim: "", tech: [], image: "", w: 1, h: 1, zoom: s.layout === "loupe" ? 4 : 0, large: false, thumbs: [] };
      const r = workRow(w, s); works.prepend(r); head(); markDirty();
      $("input", $(".a", r)).focus();
    });
    $('[data-a="sort"]', tb).addEventListener("click", () => sortByYear(works, w => w.date));

    const foot = el(`<div class="toolbar"><a class="btn2" target="_blank" rel="noopener">Voir la page ↗</a><span class="sp"></span><button type="button" class="btn2 btn2--danger">Supprimer la série</button></div>`);
    $("a", foot).href = "../works.php?s=" + encodeURIComponent(s.slug || "");
    if (!s.slug) $("a", foot).hidden = true;
    $("button", foot).addEventListener("click", () => {
      const n = $$(".work", d).length;
      if (!confirm(`Supprimer la série « ${s.title} »${n ? ` et ses ${n} œuvre(s)` : ""} ?`)) return;
      d.remove(); markDirty();
    });
    body.appendChild(foot);
    d._head = head; head();
    return d;
  }

  function panelWorks() {
    const p = el(`<section class="panel" data-panel="works">
      <div class="card"><h2>Séries</h2>
        <p class="hint">Chaque série est une page du menu <b>Works</b>. Glissez <b>⠿</b> pour changer l'ordre du menu, cliquez sur une série pour éditer son texte et ses œuvres.</p>
        <div class="list" id="seriesList"></div>
        <div class="toolbar"><button type="button" class="btn2 btn2--add" id="addSeries">+ Ajouter une série</button></div>
      </div>
    </section>`);
    const list = $("#seriesList", p);
    (D.series || []).forEach(s => list.appendChild(seriesBlock(s)));
    sortable(list, { handle: "summary .handle" });
    $("#addSeries", p).addEventListener("click", () => {
      const s = { id: rid("s"), slug: "", title: "Nouvelle série", intro: [], layout: "hero", shadow: "normal", discover: "", works: [] };
      D.series.push(s);
      openKeys.add("s:" + s.id);
      const b = seriesBlock(s); list.appendChild(b); markDirty();
      b.scrollIntoView({ behavior: "smooth", block: "start" });
      setTimeout(() => $(".blk__body input", b).select(), 300);
    });
    return p;
  }

  /* ====================================================== EXHIBITIONS == */
  function exhBlock(x) {
    x.images = x.images || [];
    const d = details("x:" + (x.id || (x._k = x._k || rid("x"))), `<span class="handle" title="Glisser pour réordonner">⠿</span>
      <span class="blk__meta y"></span><span class="blk__title"></span><span class="badge"></span>`);
    d._d = x;
    const head = () => {
      $(".y", d).textContent = x.year || "—";
      $(".blk__title", d).textContent = [x.title, x.venue].filter(Boolean).join(" — ") || "Nouvelle exposition";
      $(".badge", d).textContent = x.group === "group" ? "Collective" : "Personnelle";
    };
    const b = $(".blk__body", d);
    const g1 = el(`<div class="g3"></div>`);
    g1.append(field(x, "title", "Titre", { on: head }), field(x, "year", "Année", { on: head, ph: "2025" }),
      select(x, "group", "Type", [["solo", "Exposition personnelle"], ["group", "Exposition collective"]], head));
    b.append(g1, field(x, "venue", "Lieu", { on: head, ph: "Galerie, Ville, Pays" }),
      field(x, "text", "Texte (facultatif) — paragraphes séparés par une ligne vide", { area: true, rows: 4 }));
    const gi = el(`<div class="f"><span class="lbl">Images — la première est l'image principale, les suivantes s'ouvrent en grand</span></div>`);
    gi.appendChild(gallery(x, "images", { big: true }));
    b.append(gi, field(x, "caption", "Légende de l'image principale"));
    const foot = el(`<div class="toolbar"><span class="sp"></span><button type="button" class="btn2 btn2--danger">Supprimer l'exposition</button></div>`);
    $("button", foot).addEventListener("click", () => { if (confirm("Supprimer cette exposition ?")) { d.remove(); markDirty(); } });
    b.appendChild(foot);
    head();
    return d;
  }
  function panelExh() {
    const e = D.exhibitions = D.exhibitions || { items: [] };
    e.items = e.items || [];
    const p = el(`<section class="panel" data-panel="exhibitions">
      <div class="card"><h2>Page Exhibitions</h2><div class="hd"></div></div>
      <div class="card"><h2>Expositions</h2>
        <p class="hint">Sur le site, elles sont regroupées en « Solo » puis « Group », dans l'ordre de cette liste. La première est ouverte par défaut.</p>
        <div class="toolbar"><span class="sp"></span>
          <button type="button" class="btn2" data-a="sort">Trier par année (récentes d'abord)</button>
          <button type="button" class="btn2 btn2--add" data-a="add">+ Ajouter une exposition</button></div>
        <div class="list" id="exhList"></div>
      </div>
    </section>`);
    $(".hd", p).append(field(e, "title", "Titre"), field(e, "lede", "Introduction", { area: true, rows: 2 }));
    const list = $("#exhList", p);
    e.items.forEach(x => list.appendChild(exhBlock(x)));
    sortable(list, { handle: "summary .handle" });
    $('[data-a="sort"]', p).addEventListener("click", () => sortByYear(list, x => x.year));
    $('[data-a="add"]', p).addEventListener("click", () => {
      const x = { id: "", group: "solo", year: String(new Date().getFullYear()), title: "", venue: "", text: "", images: [], caption: "" };
      x._k = rid("x"); openKeys.add("x:" + x._k);
      const b = exhBlock(x); list.prepend(b); markDirty();
      $(".blk__body input", b).focus();
    });
    return p;
  }

  /* ============================================================ ABOUT == */
  function panelAbout() {
    const a = D.about = D.about || {};
    const p = el(`<section class="panel" data-panel="about">
      <div class="card"><h2>About</h2><div class="t"></div></div>
      <div class="card"><h2>Portrait</h2><div class="pt"></div></div>
      <div class="card"><h2>Film</h2><p class="hint">Vidéo MP4 (H.264). Taille maximale acceptée par le serveur : <b>${Math.round(I.limit / 1048576)} Mo</b>.</p><div class="g2 v"></div></div>
    </section>`);
    $(".t", p).append(field(a, "title", "Titre"), field(a, "paragraphs", "Biographie — paragraphes séparés par une ligne vide", { area: true, rows: 14, paras: true }));
    $(".pt", p).appendChild(picker(a, "portrait", { wide: true, optional: true }));
    $(".v", p).append(picker(a, "video", { kind: "video", optional: true, label: "Vidéo" }), picker(a, "poster", { wide: true, optional: true, label: "Image affichée avant lecture" }));
    return p;
  }

  /* ========================================================== CONTACT == */
  function panelContact() {
    const c = D.contact = D.contact || {};
    const p = el(`<section class="panel" data-panel="contact"><div class="card"><h2>Contact</h2><div class="t"></div></div></section>`);
    const g = el(`<div class="g2"></div>`);
    g.append(field(c, "email", "E-mail", { type: "email" }), field(c, "phone", "Téléphone", { ph: "+41 79 000 00 00" }));
    $(".t", p).append(field(c, "title", "Titre"), g, field(c, "instagram", "Instagram (nom du compte, sans @)"));
    return p;
  }

  /* ============================================================= HOME == */
  function panelHome() {
    const h = D.home = D.home || {};
    const p = el(`<section class="panel" data-panel="home"><div class="card"><h2>Page d'accueil</h2>
      <p class="hint">L'œuvre plein écran de l'accueil. Au survol prolongé, le visiteur s'en approche (loupe).</p><div class="g2 t"></div></div></section>`);
    const right = el(`<div></div>`);
    right.append(field(h, "title", "Titre de l'œuvre"), select(h, "zoom", "Rapprochement", [[1.5, "×1,5"], [2, "×2"], [3, "×3"], [4, "×4"]]),
      (h.speed = h.speed || "slow", select(h, "speed", "Vitesse", SPEEDS)));
    $(".t", p).append(picker(h, "image", { wide: true, dims: true, hd: true }), right);
    return p;
  }

  /* ========================================================= SETTINGS == */
  function panelSettings() {
    const s = D.site = D.site || {};
    const p = el(`<section class="panel" data-panel="settings">
      <div class="card"><h2>Site</h2><div class="g2 t"></div></div>
      <div class="card"><h2>Stockage du contenu</h2><div class="info st"></div></div>
      <div class="card"><h2>Mot de passe</h2>
        <div class="g2"><div class="f"><label>Mot de passe actuel</label><input class="in" type="password" id="pwOld" autocomplete="current-password"></div>
        <div class="f"><label>Nouveau mot de passe (10 caractères min.)</label><input class="in" type="password" id="pwNew" autocomplete="new-password"></div></div>
        <button type="button" class="btn2" id="pwBtn">Changer le mot de passe</button>
      </div>
    </section>`);
    $(".t", p).append(field(s, "name", "Nom (titre des onglets)"), field(s, "copyright", "Pied de page"));
    $(".st", p).innerHTML = I.shared
      ? `<div class="ok">✓ Contenu partagé entre le site de test et le site final.</div><div class="muted">Dossier : <code>${esc(I.dir)}</code></div>`
      : `<div class="warn">Contenu propre à ce site (dossier partagé inaccessible).</div><div class="muted">Dossier : <code>${esc(I.dir)}</code></div>`;
    $("#pwBtn", p).addEventListener("click", async () => {
      const r = await api("password", { old: $("#pwOld").value, new: $("#pwNew").value });
      if (r.ok) { toast("Mot de passe changé."); $("#pwOld").value = $("#pwNew").value = ""; } else toast(r.error, true);
    });
    return p;
  }

  /* =================================================== enregistrement == */
  async function api(action, body) {
    try {
      const r = await fetch("?action=" + action, { method: "POST", headers: { "Content-Type": "application/json", "X-CSRF": I.csrf }, body: JSON.stringify(body), credentials: "same-origin" });
      const j = await r.json().catch(() => ({ ok: false, error: "Réponse serveur invalide (" + r.status + ")." }));
      j.status = r.status; return j;
    } catch (e) { return { ok: false, error: "Connexion impossible : vérifiez internet puis réessayez." }; }
  }
  function collect() {
    const out = JSON.parse(JSON.stringify(D));
    out.series = $$("#seriesList > .ser").map(sEl => Object.assign({}, sEl._d, { works: $$(".works > .work", sEl).map(w => w._d) }));
    out.exhibitions.items = $$("#exhList > .blk").map(x => x._d);
    return JSON.parse(JSON.stringify(out, (k, v) => (k === "_k" || k === "_hdw" || k === "_hdh" ? undefined : v)));
  }
  async function save(force) {
    const btn = $("#saveBtn"); btn.disabled = true; $("#saveState").textContent = "Enregistrement…";
    const r = await api("save", { rev: REV, data: collect(), force: !!force });
    if (r.ok) {
      D = r.data; REV = r.rev; dirty = false;
      const y = scrollY; render(); scrollTo(0, y);
      $("#savebar").classList.remove("dirty"); $("#saveState").textContent = "Enregistré — en ligne ✓"; btn.disabled = true;
      toast("Enregistré. Le site est à jour.");
      return;
    }
    btn.disabled = false; $("#saveState").textContent = "Modifications non enregistrées";
    if (r.conflict) {
      if (confirm(r.error + "\n\nÉcraser avec votre version ? (Annuler pour garder l'autre version : rechargez alors la page.)")) return save(true);
      return;
    }
    if (r.status === 401) { toast(r.error + " Vos modifications sont conservées dans cette page : reconnectez-vous dans un autre onglet puis enregistrez.", true); return; }
    toast(r.error || "Erreur.", true);
  }

  /* ============================================================ rendu == */
  function render() {
    const app = $("#app"); app.innerHTML = "";
    app.append(panelWorks(), panelExh(), panelAbout(), panelContact(), panelHome(), panelSettings());
    showTab(tab);
  }
  function showTab(t) {
    tab = t; sessionStorageSet("rm-tab", t);
    $$("#tabs button").forEach(b => b.classList.toggle("on", b.dataset.tab === t));
    $$(".panel").forEach(p => (p.hidden = p.dataset.panel !== t));
  }
  $$("#tabs button").forEach(b => b.addEventListener("click", () => { showTab(b.dataset.tab); scrollTo(0, 0); }));
  $("#saveBtn").addEventListener("click", () => save());
  document.addEventListener("keydown", e => { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "s") { e.preventDefault(); if (dirty) save(); } });
  window.addEventListener("beforeunload", e => { if (dirty) { e.preventDefault(); e.returnValue = ""; } });
  render();
})();
