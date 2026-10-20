<?php
require_once __DIR__ . '/inc/layout.php';
$d = load_content();
$e = $d['exhibitions'] ?? [];
$groups = ['solo' => 'Selected Solo Exhibitions', 'group' => 'Selected Group Exhibitions'];
page_head(($e['title'] ?? 'Exhibitions') . ' — ' . ($d['site']['name'] ?? 'Rinaldo Marasco'));
site_header($d, 'exhibitions');
$first = true; $hasGallery = false; ?>

<main class="textpage">
  <h1><?= h($e['title'] ?? 'Exhibitions') ?></h1>
<?php if (trim((string)($e['lede'] ?? '')) !== ''): ?>
  <p class="lede"><?= rich($e['lede']) ?></p>
<?php endif;
foreach ($groups as $g => $label):
    $items = array_values(array_filter($e['items'] ?? [], fn($x) => ($x['group'] ?? 'solo') === $g));
    if (!$items) continue; ?>
  <div class="exh-group">
    <h2><?= h($label) ?></h2>
<?php foreach ($items as $x):
        $imgs = array_values(array_filter($x['images'] ?? [], fn($i) => trim((string)$i) !== ''));
        $line = trim((string)($x['title'] ?? '')) . (trim((string)($x['venue'] ?? '')) !== '' ? ' — ' . trim((string)$x['venue']) : '');
        $hasBody = $imgs || trim(strip_tags((string)($x['text'] ?? ''))) !== ''; ?>
    <details class="exh-item"<?= $first ? ' open' : '' ?>>
      <summary class="exh-row"><span class="y"><?= h($x['year'] ?? '') ?></span><span class="d"><?= h($line) ?></span><span class="tg" aria-hidden="true"></span></summary>
<?php if ($hasBody): ?>
      <div class="exh-body">
<?php if (trim(strip_tags((string)($x['text'] ?? ''))) !== ''): ?>
        <div class="exh-text">
<?php foreach (paras($x['text']) as $p): ?>
          <p><?= rich($p) ?></p>
<?php endforeach; ?>
        </div>
<?php endif;
      if ($imgs): $main = array_shift($imgs); ?>
        <figure class="exh-main">
          <img src="<?= h(src($main)) ?>" alt="<?= h(mb_strtoupper((string)($x['title'] ?? ''))) ?> — Rinaldo Marasco, exhibition view<?= ($x['venue'] ?? '') !== '' ? ' at ' . h(explode(',', (string)$x['venue'])[0]) : '' ?>">
<?php if (trim((string)($x['caption'] ?? '')) !== ''): ?>
          <figcaption><?= h($x['caption']) ?></figcaption>
<?php endif; ?>
        </figure>
<?php if ($imgs): $hasGallery = true; ?>
        <div class="d-row exh-gallery" data-title="<?= h($x['title'] ?? '') ?>">
<?php foreach ($imgs as $i => $im): $u = h(src($im)); ?>
          <button class="thumb" data-full="<?= $u ?>"><img src="<?= $u ?>" alt="<?= h($x['title'] ?? '') ?> — view <?= $i + 2 ?>"></button>
<?php endforeach; ?>
        </div>
<?php endif; endif; ?>
      </div>
<?php endif; ?>
    </details>
<?php $first = false; endforeach; ?>
  </div>
<?php endforeach; ?>
</main>
<?php site_footer($d, $hasGallery);
