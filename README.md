# Rinaldo Marasco — Website prototype

Static prototype of the artist's site. No build step: open `index.html` in a
browser, or serve the folder over GitHub Pages.

## Pages
- `index.html` — Home. Single work confronting the visitor, with **Mode Proximité**
  (hover the artwork: after a short latency the image slowly draws closer inside a
  fixed frame, with organic inertia and a slow return). The arrow opens Territories.
- `territories.html` — The *Territories* section: five works whose simple global
  forms hold dense internal ecosystems.

## Structure
```
index.html
territories.html
assets/
  seed.webp        high-resolution Seed (home, for the close-up)
  seed-1500.webp   gallery version
  bigbang.webp  peace.webp  guardians.webp  axis.webp
```

## Mode Proximité — current parameters (home)
Editable at the top of the `<script>` in `index.html`:
- latency before the approach begins: **1.7 s**
- maximum zoom: **314 %**
- approach easing: **0.0032** (very slow, contemplative)

## Notes for the client
- Work captions are placeholders (*Year · Technique · Dimensions*) except **Big Bang**.
  Final year / technique / dimensions to be provided per work.
- "View work" links are inactive — individual work pages are not built yet.
- Typography: Helvetica Neue. Letter-spacing is used **only** in the menu; titles have none.

## GitHub Pages
Push to a repo, then Settings → Pages → deploy from the `main` branch (root).
The `.nojekyll` file is included so assets are served as-is.
