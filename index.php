<?php
require_once __DIR__ . '/inc/layout.php';
$d = load_content();
$home = $d['home'] ?? [];
$first = $d['series'][0] ?? null;
$t = (string)($home['title'] ?? '');
page_head(($d['site']['name'] ?? 'Rinaldo Marasco'), '', 'assets/home.css');
site_header($d); ?>
<section class="hero">
  <div class="proximity" id="prox" data-zoom="<?= h(number_format((float)($home['zoom'] ?? 2), 1, '.', '')) ?>" aria-label="<?= h($t) ?> — mode proximité : survolez l'œuvre pour vous en approcher">
    <img id="artwork" src="<?= h(src($home['image'] ?? '')) ?>" alt="<?= h($t) ?> — Rinaldo Marasco" draggable="false">
    <div class="dot" id="dot"></div>
  </div>
  <button class="down" id="down" aria-label="Continuer">
    <svg width="46" height="24" viewBox="0 0 46 24" fill="none">
      <path d="M2 2 L23 21 L44 2" stroke-width="2.2" fill="none"/>
    </svg>
  </button>
</section>
<section class="entries" id="entries">
  <a href="<?= $first ? h(series_url($first)) : '#' ?>">Works</a>
  <a href="about.php">About</a>
  <a href="contact.php">Contact</a>
</section>
<script src="<?= h(asset_v('assets/home.js')) ?>"></script>
<script src="<?= h(asset_v('assets/site.js')) ?>"></script>
</body>
</html>
