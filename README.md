# Rinaldo Marasco — Website

PHP site (PHP 8.1+) with a small CMS in `/admin`. Deployed by GitHub Actions over FTP
(dev: automatic on push to `main`; production: manual "Run workflow → production").

## CMS (`/admin`)
- First visit to `/admin/` asks to **create the password** (≥ 10 chars). Only its hash is
  stored, in `rinaldo-content/auth.json` — never in this repo. To reset it, delete that file by FTP.
- Editable: Works series (menu pages: add / delete / drag to reorder / "sort by year"),
  works (title, year, dimensions, technique lines, image, zoom, large, detail thumbnails),
  Exhibitions, About (text, portrait, film), Contact, home image, footer.
- Uploaded images are resized to 2400 px max and converted to WebP.

## Where the content lives
Content is **not in git** (a push would overwrite the client's edits). It lives in a folder
shared by the dev and production sites, next to `sites/`:
```
<hosting root>/rinaldo-content/
  content.json        all texts + order (created on first save)
  media/              images / videos uploaded in the admin (served by media.php)
  backups/            the last 30 versions of content.json
  auth.json           admin password hash
```
Until the first save, the site renders `data/seed.json`. If the shared folder isn't
reachable, the site falls back to `<site>/content/` (shown in Admin → Réglages).
A local `content-path.php` (`<?php return '/abs/path';`) can force another location — handy for
`php -S localhost:8080` testing. Both are git-ignored and excluded from deploy.

## Code map
- `index.php`, `works.php?s=<slug>`, `exhibitions.php`, `about.php`, `contact.php` — public pages
- `inc/core.php` (content + helpers), `inc/layout.php` (header, menu, footer, cartels)
- `assets/site.css|js`, `assets/home.css|js` — styles and scripts
- old `*.html` URLs redirect to the PHP pages

---

## Design notes (from the static prototype)
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
