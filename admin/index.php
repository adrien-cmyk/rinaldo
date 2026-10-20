<?php
/* ============================================================================
   Admin Rinaldo Marasco — point d'entrée
   ============================================================================ */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

function rev(): string { return is_file(content_file()) ? md5_file(content_file()) : 'seed'; }
$flash = ''; $err = '';

/* ---------------------------------------------------------- Déconnexion -- */
if (isset($_GET['logout'])) { logout(); header('Location: ./'); exit; }

/* -------------------------------------------- Premier accès : mot de passe -- */
if (!has_password()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok($_POST['csrf'] ?? null)) {
        $a = (string)($_POST['pw'] ?? ''); $b = (string)($_POST['pw2'] ?? '');
        if (mb_strlen($a) < 10)  $err = 'Le mot de passe doit contenir au moins 10 caractères.';
        elseif ($a !== $b)      $err = 'Les deux mots de passe ne correspondent pas.';
        elseif (!has_password()) {
            set_password($a);
            if (!has_password()) $err = 'Impossible d\'écrire dans le dossier de contenu (' . content_dir() . ').';
            else { try_login($a); header('Location: ./'); exit; }
        }
    }
    gate('setup', $err); exit;
}

/* ------------------------------------------------------------- Connexion -- */
if (!is_logged_in()) {
    if (($_GET['action'] ?? '') !== '') json_out(['ok' => false, 'error' => 'Session expirée : reconnectez-vous.'], 401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok($_POST['csrf'] ?? null)) {
        if (try_login((string)($_POST['pw'] ?? ''))) { header('Location: ./'); exit; }
        $l = locked_for();
        $err = $l > 0 ? 'Trop d\'essais. Réessayez dans ' . ceil($l / 60) . ' min.' : 'Mot de passe incorrect.';
    }
    gate('login', $err); exit;
}

/* ------------------------------------------------------------ Actions API -- */
$action = (string)($_GET['action'] ?? '');
if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Méthode invalide.'], 405);
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > bytes_ini('post_max_size'))
        json_out(['ok' => false, 'error' => 'Fichier trop lourd (max ' . round(upload_limit() / 1048576) . ' Mo).'], 413);
    if (!csrf_ok($_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null))) json_out(['ok' => false, 'error' => 'Jeton de sécurité invalide : rechargez la page.'], 403);

    if ($action === 'save') {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in) || !is_array($in['data'] ?? null)) json_out(['ok' => false, 'error' => 'Données invalides.'], 400);
        if (($in['rev'] ?? '') !== rev() && empty($in['force']))
            json_out(['ok' => false, 'conflict' => true, 'error' => 'Le contenu a été modifié ailleurs (autre onglet ou autre personne) depuis l\'ouverture de cette page.'], 409);
        $data = normalize($in['data']);
        if (!save_content($data)) json_out(['ok' => false, 'error' => 'Écriture impossible dans ' . content_dir()], 500);
        json_out(['ok' => true, 'data' => $data, 'rev' => rev()]);
    }
    if ($action === 'upload') {
        $f = $_FILES['file'] ?? null;
        if (!is_array($f) || is_array($f['name'])) json_out(['ok' => false, 'error' => 'Aucun fichier reçu.'], 400);
        try {
            [$path, $w, $h] = (($_POST['kind'] ?? '') === 'video') ? store_video($f) : store_image($f);
            json_out(['ok' => true, 'path' => $path, 'w' => $w, 'h' => $h, 'url' => admin_src($path)]);
        } catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()], 400); }
    }
    if ($action === 'password') {
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $a = auth_data();
        if (!password_verify((string)($in['old'] ?? ''), (string)($a['hash'] ?? ''))) json_out(['ok' => false, 'error' => 'Mot de passe actuel incorrect.'], 400);
        if (mb_strlen((string)($in['new'] ?? '')) < 10) json_out(['ok' => false, 'error' => 'Le nouveau mot de passe doit contenir au moins 10 caractères.'], 400);
        set_password((string)$in['new']);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'error' => 'Action inconnue.'], 404);
}

/* -------------------------------------------------------------- Éditeur -- */
$data = load_content();
$init = [
    'data'   => $data,
    'rev'    => rev(),
    'csrf'   => csrf_token(),
    'limit'  => upload_limit(),
    'shared' => content_is_shared(),
    'dir'    => content_dir(),
];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin — Rinaldo Marasco</title>
<link rel="stylesheet" href="<?= h('admin.css?v=' . filemtime(__DIR__ . '/admin.css')) ?>">
</head>
<body>
<div class="top">
  <div class="top__brand">RINALDO MARASCO <em>— administration</em></div>
  <nav class="tabs" id="tabs">
    <button type="button" data-tab="works" class="on">Works</button>
    <button type="button" data-tab="exhibitions">Exhibitions</button>
    <button type="button" data-tab="about">About</button>
    <button type="button" data-tab="contact">Contact</button>
    <button type="button" data-tab="home">Accueil</button>
    <button type="button" data-tab="settings">Réglages</button>
  </nav>
  <div class="top__actions">
    <a class="lnk" href="../" target="_blank" rel="noopener">Voir le site ↗</a>
    <a class="lnk lnk--out" href="?logout">Déconnexion</a>
  </div>
</div>

<div class="wrap" id="app"></div>

<div class="savebar" id="savebar">
  <span class="savebar__state" id="saveState">Aucune modification</span>
  <button class="btn" type="button" id="saveBtn" disabled>Enregistrer</button>
</div>
<div class="toast" id="toast" hidden></div>

<script>window.INIT = <?= json_encode($init, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h('sortable.min.js?v=' . filemtime(__DIR__ . '/sortable.min.js')) ?>"></script>
<script src="<?= h('admin.js?v=' . filemtime(__DIR__ . '/admin.js')) ?>"></script>
</body>
</html>
<?php

/* ---------------------------------------------- Écrans connexion / setup -- */
function gate(string $mode, string $err): void { ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin — Rinaldo Marasco</title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="gate">
  <form class="gate__card" method="post" autocomplete="off">
    <div class="gate__brand">RINALDO MARASCO</div>
<?php if ($mode === 'setup'): ?>
    <h1>Créer le mot de passe</h1>
    <p class="muted">Premier accès à l'administration : choisissez le mot de passe (10 caractères minimum). Il est enregistré chiffré sur le serveur, jamais dans le code.</p>
    <label for="pw">Mot de passe</label>
    <input class="in" type="password" id="pw" name="pw" required minlength="10" autofocus autocomplete="new-password">
    <label for="pw2">Confirmer</label>
    <input class="in" type="password" id="pw2" name="pw2" required minlength="10" autocomplete="new-password">
<?php else: ?>
    <h1>Connexion</h1>
    <label for="pw">Mot de passe</label>
    <input class="in" type="password" id="pw" name="pw" required autofocus autocomplete="current-password">
<?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
<?php if ($err !== ''): ?><p class="gate__err"><?= h($err) ?></p><?php endif; ?>
    <button class="btn" type="submit"><?= $mode === 'setup' ? 'Enregistrer et entrer' : 'Entrer' ?></button>
  </form>
</body>
</html>
<?php }
