<?php
require_once __DIR__ . '/inc/layout.php';
$d = load_content();
$a = $d['about'] ?? [];
page_head(($a['title'] ?? 'About') . ' — ' . ($d['site']['name'] ?? 'Rinaldo Marasco'));
site_header($d, 'about'); ?>

<main class="textpage about">
  <h1><?= h($a['title'] ?? 'About') ?></h1>
  <div class="about-grid">
    <div class="about-bio">
<?php foreach ($a['paragraphs'] ?? [] as $p): if (trim((string)$p) === '') continue; ?>
      <p><?= rich($p) ?></p>
<?php endforeach; ?>
    </div>
<?php if (!empty($a['portrait'])): ?>
    <img class="portrait" src="<?= h(src($a['portrait'])) ?>" alt="Rinaldo Marasco">
<?php endif; ?>
  </div>
<?php if (!empty($a['video'])): ?>

  <div class="about-film">
    <video controls preload="none"<?= !empty($a['poster']) ? ' poster="' . h(src($a['poster'])) . '"' : '' ?>>
      <source src="<?= h(src($a['video'])) ?>" type="video/mp4">
    </video>
  </div>
<?php endif; ?>
</main>
<?php site_footer($d);
