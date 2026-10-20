<?php
/* ============================================================================
   Rinaldo Marasco — cœur du site (contenu, chemins, helpers)
   Le CONTENU (content.json + images ajoutées) vit hors du dépôt GitHub, dans un
   dossier partagé entre dev et prod :  <hébergement>/rinaldo-content/
   Le CODE (ce dépôt) est déployé par GitHub Actions et ne touche jamais au contenu.
   ============================================================================ */
declare(strict_types=1);

const SITE_ROOT = __DIR__ . '/..';
const SEED_FILE = SITE_ROOT . '/data/seed.json';

/* Dossier de contenu partagé. Ordre de recherche :
   1. chemin forcé dans content-path.php (fichier optionnel, non versionné)
   2. <hébergement>/rinaldo-content   (partagé entre sites/dev… et sites/…)
   3. <site>/content                  (repli local, non partagé) */
function content_dir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;
    $root = realpath(SITE_ROOT) ?: SITE_ROOT;
    $cands = [];
    if (is_file($root . '/content-path.php')) {
        $p = include $root . '/content-path.php';
        if (is_string($p) && $p !== '') $cands[] = rtrim($p, '/');
    }
    $cands[] = dirname($root, 2) . '/rinaldo-content';
    $cands[] = $root . '/content';
    foreach ($cands as $c) {
        if (@is_dir($c) && @is_writable($c)) return $dir = $c;
        if (!@is_dir($c) && @mkdir($c, 0775, true)) return $dir = $c;
    }
    return $dir = $root . '/content';
}
function content_is_shared(): bool {
    $root = realpath(SITE_ROOT) ?: SITE_ROOT;
    return strpos(content_dir(), $root . '/') !== 0 && content_dir() !== $root . '/content';
}
function media_dir(): string {
    $d = content_dir() . '/media';
    if (!@is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}
function content_file(): string { return content_dir() . '/content.json'; }

/* Protection si le contenu est dans le dossier du site (repli local) */
function protect_local_content(): void {
    $root = realpath(SITE_ROOT) ?: SITE_ROOT;
    if (strpos(content_dir(), $root) === 0) {
        $ht = content_dir() . '/.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
}

/* ------------------------------------------------------------- données -- */
function load_content(): array {
    $f = content_file();
    if (is_file($f)) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) return $d;
    }
    $d = json_decode((string)@file_get_contents(SEED_FILE), true);
    return is_array($d) ? $d : [];
}
function save_content(array $d): bool {
    protect_local_content();
    $f = content_file();
    if (is_file($f)) {                       // sauvegarde de la version précédente
        $bk = content_dir() . '/backups';
        if (!is_dir($bk)) @mkdir($bk, 0775, true);
        @copy($f, $bk . '/content-' . date('Ymd-His') . '.json');
        $all = glob($bk . '/content-*.json') ?: [];
        sort($all);
        foreach (array_slice($all, 0, max(0, count($all) - 30)) as $old) @unlink($old);
    }
    $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = $f . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $f);
}

/* ------------------------------------------------------------- helpers -- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/* Texte saisi dans l'admin (texte brut) : échappé, retours à la ligne conservés */
function rich($s): string { return nl2br(h(trim((string)$s)), false); }
/* Texte libre → paragraphes (séparés par une ligne vide) */
function paras($s): array {
    $s = str_replace(["\r\n", "\r"], "\n", (string)$s);
    return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $s) ?: []), fn($p) => $p !== ''));
}
/* URL d'une image : assets/... (dépôt) ou media/... (ajoutée via l'admin) */
function src($p): string {
    $p = (string)$p;
    if (strncmp($p, 'media/', 6) === 0) return 'media.php?f=' . rawurlencode(substr($p, 6));
    return $p;
}
function asset_v(string $rel): string {
    $f = SITE_ROOT . '/' . $rel;
    return $rel . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function series_url(array $s): string { return 'works.php?s=' . rawurlencode((string)$s['slug']); }
function find_series(array $d, string $slug): ?array {
    foreach ($d['series'] ?? [] as $s) if (($s['slug'] ?? '') === $slug) return $s;
    return null;
}
function bound_class(array $w): string {
    $ww = (int)($w['w'] ?? 1); $hh = (int)($w['h'] ?? 1);
    return ($hh > 0 && $ww / $hh >= 560 / 680) ? 'wbound' : 'hbound';
}
