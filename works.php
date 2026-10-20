<?php
require_once __DIR__ . '/inc/layout.php';
$d = load_content();
$slug = (string)($_GET['s'] ?? '');
$s = find_series($d, $slug) ?? ($d['series'][0] ?? null);
if (!$s) { http_response_code(404); exit('Not found'); }
$works = array_values(array_filter($s['works'] ?? [], fn($w) => ($w['image'] ?? '') !== ''));
$hero  = ($s['layout'] ?? 'hero') === 'hero';
$cls   = [];
if ($hero) $cls[] = 'layout-hero';
if (in_array($s['shadow'] ?? '', ['medium', 'strong'], true)) $cls[] = 'shadow-' . $s['shadow'];
$heroWork = ($hero && $works) ? array_shift($works) : null;

page_head($s['title'] . ' — ' . ($d['site']['name'] ?? 'Rinaldo Marasco'), implode(' ', $cls));
site_header($d, 'works'); ?>

<section class="intro">
  <div class="intro-inner">
    <div class="intro-text">
      <h1><?= h($s['title']) ?></h1>
<?php foreach ($s['intro'] ?? [] as $p): if (trim((string)$p) === '') continue; ?>
      <p><?= rich($p) ?></p>
<?php endforeach; ?>
    </div>
<?php if ($heroWork): echo hero_figure($heroWork);
      elseif (!$hero && !empty($s['discover'])): ?>
    <div class="discover" id="discover"><img src="<?= h(src($s['discover'])) ?>" alt=""></div>
<?php endif; ?>
  </div>
<?php down_arrow(); ?>
</section>

<main id="works">
<?php foreach ($works as $w) echo work_figure($w), "\n\n"; ?>
</main>
<?php site_footer($d, true);
