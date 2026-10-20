<?php
/* Rinaldo Marasco — gabarits partagés (en-tête, menu, pied, cartels) */
require_once __DIR__ . '/core.php';

function page_head(string $title, string $bodyClass = '', string $css = 'assets/site.css'): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= h(asset_v($css)) ?>">
</head>
<body<?= $bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : '' ?>>
<?php }

function site_header(array $d, string $current = ''): void {
    $series = $d['series'] ?? [];
    $cls = fn($k) => $current === $k ? 'current' : ''; ?>

<header>
  <a class="brand" href="index.php" aria-label="Rinaldo Marasco — home">
    <?php readfile(__DIR__ . '/logo.svg'); ?>
    <span class="brand-name">Rinaldo&nbsp;Marasco</span>
  </a>
  <nav>
    <div class="has-sub" id="worksMenu">
      <a href="#" class="<?= $cls('works') ?>">Works</a>
      <div class="submenu">
<?php foreach ($series as $s): ?>
        <a href="<?= h(series_url($s)) ?>"><?= h($s['title']) ?></a>
<?php endforeach; ?>
      </div>
    </div>
    <a href="exhibitions.php" class="<?= $cls('exhibitions') ?>">Exhibitions</a>
    <a href="about.php" class="<?= $cls('about') ?>">About</a>
    <a href="contact.php" class="<?= $cls('contact') ?>">Contact</a>
  </nav>
  <button class="burger" id="burger" aria-label="Menu"><span></span><span></span><span></span></button>
</header>
<div class="mobile-menu" id="mobileMenu">
  <span class="mm-label">Works</span>
<?php foreach ($series as $s): ?>
  <a class="mm-sub" href="<?= h(series_url($s)) ?>"><?= h($s['title']) ?></a>
<?php endforeach; ?>
  <a class="mm-top" href="exhibitions.php">Exhibitions</a>
  <a class="mm-top" href="about.php">About</a>
  <a class="mm-top" href="contact.php">Contact</a>
</div>
<?php }

function down_arrow(string $id = 'toworks', string $label = 'Scroll to works'): void { ?>
  <button class="down" id="<?= h($id) ?>" aria-label="<?= h($label) ?>">
    <svg width="46" height="24" viewBox="0 0 46 24" fill="none"><path d="M2 2 L23 21 L44 2" stroke-width="2.2" fill="none"/></svg>
  </button>
<?php }

function site_footer(array $d, bool $lightbox = false, string $js = 'assets/site.js'): void { ?>

<footer>
  <div class="f-nav">
<?php foreach ($d['series'] ?? [] as $s): ?>
    <a href="<?= h(series_url($s)) ?>"><?= h($s['title']) ?></a>
<?php endforeach; ?>
  </div>
  <div class="copy"><?= h($d['site']['copyright'] ?? '© Rinaldo Marasco') ?></div>
</footer>
<?php if ($lightbox): ?>

<div class="lb" id="lb" aria-hidden="true">
  <button class="lb-close" id="lbClose" aria-label="Close">×</button>
  <button class="lb-prev" id="lbPrev" aria-label="Previous">‹</button>
  <img id="lbImg" src="" alt="">
  <button class="lb-next" id="lbNext" aria-label="Next">›</button>
</div>
<?php endif; ?>

<script src="<?= h(asset_v($js)) ?>"></script>
</body>
</html>
<?php }

/* ---------------------------------------------------------- œuvres -- */
function cartel(array $w, string $ind = '        '): string {
    $o  = $ind . '<figcaption class="cartel">' . "\n";
    $o .= $ind . '  <div class="c-head"><span class="c-title">' . h($w['title'] ?? '') . '</span>'
        . (($w['date'] ?? '') !== '' ? ' <span class="c-date">(' . h($w['date']) . ')</span>' : '') . "</div>\n";
    if (($w['dim'] ?? '') !== '') $o .= $ind . '  <div class="c-dim">' . h($w['dim']) . "</div>\n";
    foreach ($w['tech'] ?? [] as $t) if (trim((string)$t) !== '') $o .= $ind . '  <div class="c-tech">' . h($t) . "</div>\n";
    return $o . $ind . "</figcaption>\n";
}
function ar(array $w): string { return (int)($w['w'] ?? 1) . '/' . (int)($w['h'] ?? 1); }

function work_figure(array $w): string {
    $title = (string)($w['title'] ?? '');
    $zoom  = (float)($w['zoom'] ?? 0);
    $prox  = $zoom > 0;
    $bound = bound_class($w);
    $media = 'work-media' . ($prox ? ' prox' : '') . ' ' . $bound . ((!empty($w['large']) && $bound === 'wbound') ? ' lg' : '');
    $attrs = $prox ? ' data-zoom="' . h(number_format($zoom, 1, '.', '')) . '" aria-label="' . h($title) . ' — approchez pour voir de plus près"' : '';
    $o  = '  <figure class="work" id="' . h($w['id'] ?? '') . "\">\n    <div class=\"work-inner\">\n";
    $o .= '      <div class="' . $media . '" style="--ar:' . ar($w) . ';"' . $attrs . ">\n";
    $o .= '        <img src="' . h(src($w['image'] ?? '')) . '" alt="' . h($title) . ' — Rinaldo Marasco"' . ($prox ? ' draggable="false"' : '') . ">\n";
    if ($prox) $o .= "          <div class=\"dot\"></div>\n";
    $o .= "      </div>\n      <div class=\"caption-row\">\n" . cartel($w);
    $th = array_values(array_filter($w['thumbs'] ?? [], fn($t) => trim((string)$t) !== ''));
    if ($th) {
        $o .= '        <div class="d-row" data-title="' . h($title) . "\">\n";
        foreach ($th as $i => $t) {
            $u = h(src($t));
            $o .= '          <button class="thumb" data-full="' . $u . '"><img src="' . $u . '" alt="' . h($title) . ' — detail ' . ($i + 1) . "\"></button>\n";
        }
        $o .= "        </div>\n";
    }
    return $o . "      </div>\n    </div>\n  </figure>";
}

function hero_figure(array $w): string {
    $title = (string)($w['title'] ?? '');
    $o  = "    <figure class=\"intro-work\">\n";
    $o .= '      <div class="work-media ' . bound_class($w) . '" style="--ar:' . ar($w) . ";\">\n";
    $o .= '        <img src="' . h(src($w['image'] ?? '')) . '" alt="' . h($title) . " — Rinaldo Marasco\">\n";
    $o .= "      </div>\n      <div class=\"caption-row\">\n" . cartel($w) . "      </div>\n    </figure>\n";
    return $o;
}
