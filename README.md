# Rinaldo Marasco — Website prototype

Static prototype. No build step: open `index.html`, or serve the folder on GitHub Pages.

## Pages
- `index.html` — Home. Single work with **Mode Proximité** (hover: after 1.7 s the
  image slowly draws closer from the centre up to 200 %; once the zoom has started
  you can move around inside it; slow return on exit). Arrow scrolls to the menu.
- `territories.html` — The *Territories* section: intro text + the 11 works.
  - Each work carries **Mode Proximité**, except the four **NoZoom** pieces
    (Contigo, Soplada, Soplada 2, Volando).
  - Captions follow the catalogue (dimensions converted mm → cm; "Ink" shown as
    "Marker"). Collection shown only where known (Big Bang).
  - Under each work: **detail thumbnails** that open in a **lightbox**.
  - Intro right-hand white area: **discover.png** revealed only under the cursor
    (a "flashlight" hover effect).

## ⚠️ discover.png
`assets/discover.png` is a **placeholder**. Replace it with the real image —
same path, same name — and the flashlight effect will use it as-is.

## Structure
```
index.html
territories.html
assets/
  <work>.webp            display / zoom master (e.g. seed, bigbang, …)
  <work>-d1/-d2/-d3.webp detail crops (lightbox)
  discover.png           placeholder — replace
```

## Typography
Helvetica Neue. Body text is 16px (desktop and mobile). Letter-spacing is used
**only** in the menu; titles and captions have none. Captions align to the "R"
of the RINALDO MARASCO wordmark.

## GitHub Pages
Push to a repo, then Settings → Pages → deploy from `main` (root).
`.nojekyll` is included so assets are served as-is.
