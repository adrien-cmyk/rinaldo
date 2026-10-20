<?php
require_once __DIR__ . '/inc/layout.php';
$d = load_content();
$c = $d['contact'] ?? [];
$tel = preg_replace('/[^0-9+]/', '', (string)($c['phone'] ?? ''));
$ig  = ltrim(trim((string)($c['instagram'] ?? '')), '@');
page_head(($c['title'] ?? 'Contact') . ' — ' . ($d['site']['name'] ?? 'Rinaldo Marasco'));
site_header($d, 'contact'); ?>

<main class="textpage">
  <h1><?= h($c['title'] ?? 'Contact') ?></h1>
  <div class="contact-block">
<?php if (!empty($c['email'])): ?>
    <div class="row"><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></div>
<?php endif; if ($tel !== ''): ?>
    <div class="row"><a href="tel:<?= h($tel) ?>"><?= h($c['phone']) ?></a></div>
<?php endif; if ($ig !== ''): ?>
    <div class="row">insta: <a href="https://instagram.com/<?= h(rawurlencode($ig)) ?>" target="_blank" rel="noopener">@<?= h($ig) ?></a></div>
<?php endif; ?>
  </div>
</main>
<?php site_footer($d);
