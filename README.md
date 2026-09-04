# Rinaldo Marasco — Website prototype

Static prototype. No build step: open `index.html`, or serve the folder on GitHub Pages.

## Pages
- `index.html` — Home. Single work with **Mode Proximité** (hover: after 1.7 s the
  image slowly draws closer from the centre up to 200 %; once the zoom has started
  you can move around inside it; slow return on exit). Arrow scrolls to the menu.
- `territories.html` — The *Territories* section: intro text + the 12 works.
  - Each work carries **Mode Proximité**, except the four **NoZoom** pieces
    (Contigo, Soplada, Soplada 2, Volando).
  - Captions follow the catalogue (dimensions converted mm → cm; "Ink" shown as
    "Marker"). Collection shown only where known (Big Bang).
  - Under each work: **detail thumbnails** that open in a **lightbox**.
  - Intro right-hand white area: **discover.webp** revealed only under the cursor
    (a "flashlight" hover effect).

## ⚠️ discover.webp
`assets/discover.webp` is a **placeholder**. Replace it with the real image —
same path, same name — and the flashlight effect will use it as-is.

## Structure
```
index.html
territories.html
assets/
  <work>.webp            display / zoom master (e.g. seed, bigbang, …)
  <work>-d1/-d2/-d3.webp detail crops (lightbox)
  discover.webp           placeholder — replace
```

## Typography
Helvetica Neue. Body text is 16px (desktop and mobile). Letter-spacing is used
**only** in the menu; titles and captions have none. Captions align to the "R"
of the RINALDO MARASCO wordmark.


## Detail thumbnails
The detail thumbnails under each work are crops I generated from the high-res
files (`assets/<work>-d1/-d2/-d3.webp`). I can't read your local folder
(`.../Territories/Thumbnails`) from here — to use your own detail shots, drop them
into `assets/` with the same names, or send them and I'll wire them in.


## Territories — layout notes (print-precise)
- Everything aligns to fixed columns: captions align to each work's image left edge,
  detail thumbnails to its right edge; portraits share one height so they line up.
- Single line-height (1.45) everywhere.
- Captions are always 4 lines: **TITLE (DATE)** (bold) / dimensions / technique split
  over two lines (at "&" if present, otherwise after the last comma).
- Seed is sized to match Big Bang (both squares → same width).
- Zoom (Mode Proximité): 400% on zoomable works, **800% on Big Bang**; Amor is static.
- Menu bar is solid white (no fade); the intro "flashlight" circle is smaller with a crisp edge.
- Detail thumbnails are the files you provided for Seed, Big Bang, The Guardians, Peace, Axis;
  the other works still use generated crops until you send theirs. Thumbnails render at half size.

## Sculptures (later)
The "1 main image + related thumbnails" pattern is exactly what Territories uses, so the
Sculptures page will follow it — send the sculpture photos and I'll build it.


## Update — Maestro + new masters
- Added **Maestro** (2026, 100 × 160 cm) after Axis, with its three detail thumbnails; zoomable like the other large works.
- Replaced the master photos for **The Guardians** and **Peace** with the new framed files (color-managed to sRGB).

## GitHub Pages
Push to a repo, then Settings → Pages → deploy from `main` (root).
`.nojekyll` is included so assets are served as-is.
