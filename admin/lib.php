<?php
/* ============================================================================
   Admin Rinaldo Marasco — bibliothèque (auth, enregistrement, images)
   Le mot de passe n'est jamais versionné : son empreinte (password_hash) est
   stockée dans le dossier de contenu partagé (auth.json), créée au premier
   accès. Pour réinitialiser : supprimer rinaldo-content/auth.json par FTP.
   ============================================================================ */
declare(strict_types=1);
require_once __DIR__ . '/../inc/core.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('rm_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

/* --------------------------------------------------------------- AUTH -- */
function auth_file(): string { return content_dir() . '/auth.json'; }
function auth_data(): array {
    $a = is_file(auth_file()) ? json_decode((string)file_get_contents(auth_file()), true) : null;
    return is_array($a) ? $a : [];
}
function auth_save(array $a): void {
    protect_local_content();
    file_put_contents(auth_file(), json_encode($a, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod(auth_file(), 0600);
}
function has_password(): bool { return !empty(auth_data()['hash']); }
/* Comptes : 'hash' = accès principal (client) ; 'users' = accès supplémentaires
   [nom => empreinte] (ex. le webmaster). Session : rm_who = '' (principal) ou nom. */
function set_password(string $pw, string $who = ''): void {
    $a = auth_data();
    if ($who === '') $a['hash'] = password_hash($pw, PASSWORD_DEFAULT);
    else $a['users'][$who] = password_hash($pw, PASSWORD_DEFAULT);
    $a['fails'] = 0; $a['until'] = 0;
    auth_save($a);
}
/* 5 essais ratés → blocage 10 minutes */
function locked_for(): int { return max(0, (int)(auth_data()['until'] ?? 0) - time()); }
function try_login(string $pw): bool {
    $a = auth_data();
    if (locked_for() > 0) return false;
    $who = null;
    if (!empty($a['hash']) && password_verify($pw, $a['hash'])) $who = '';
    else foreach ((array)($a['users'] ?? []) as $n => $hh) if (password_verify($pw, (string)$hh)) { $who = (string)$n; break; }
    if ($who !== null) {
        $a['fails'] = 0; $a['until'] = 0; auth_save($a);
        session_regenerate_id(true);
        $_SESSION['rm_admin'] = true;
        $_SESSION['rm_who'] = $who;
        return true;
    }
    $a['fails'] = (int)($a['fails'] ?? 0) + 1;
    if ($a['fails'] >= 5) { $a['until'] = time() + 600; $a['fails'] = 0; }
    auth_save($a);
    usleep(600000);
    return false;
}
function current_who(): string { return (string)($_SESSION['rm_who'] ?? ''); }
function account_hash(string $who): string { $a = auth_data(); return (string)($who === '' ? ($a['hash'] ?? '') : ($a['users'][$who] ?? '')); }
function is_logged_in(): bool {
    if (empty($_SESSION['rm_admin'])) return false;
    if (account_hash(current_who()) === '') { logout(); return false; }   // accès supprimé entre-temps
    return true;
}
function logout(): void { $_SESSION = []; session_destroy(); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_ok(?string $t): bool {
    return $t !== null && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function json_out(array $a, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* --------------------------------------------------------- NETTOYAGE -- */
function txt($v, int $max = 300): string {
    $v = is_scalar($v) ? (string)$v : '';
    $v = str_replace(["\r\n", "\r"], "\n", strip_tags($v));
    $v = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}
function line($v, int $max = 300): string { return str_replace("\n", ' ', txt($v, $max)); }
function lines($v, int $maxLines = 40, int $max = 4000): array {
    $arr = is_array($v) ? $v : explode("\n", txt($v, $max));
    $out = [];
    foreach ($arr as $l) { $l = line($l, 600); if ($l !== '') $out[] = $l; }
    return array_slice($out, 0, $maxLines);
}
function slugify(string $s): string {
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'item';
}
function unique_slug(string $base, array &$used): string {
    $s = $base; $i = 2;
    while (isset($used[$s])) $s = $base . '-' . $i++;
    $used[$s] = true;
    return $s;
}
/* Chemin d'image accepté : fichier du dépôt (assets/…) ou média ajouté (media/…) */
function media_path($p): string {
    $p = is_string($p) ? trim($p) : '';
    if ($p === '' || str_contains($p, '..') || !preg_match('#^(assets|media)/[A-Za-z0-9._/-]+$#', $p)) return '';
    if (str_starts_with($p, 'media/')) return is_file(media_dir() . '/' . basename($p)) ? 'media/' . basename($p) : '';
    return is_file(SITE_ROOT . '/' . $p) ? $p : '';
}
function media_list($v, int $max = 60): array {
    $out = [];
    foreach ((array)$v as $p) if (($q = media_path($p)) !== '') $out[] = $q;
    return array_slice($out, 0, $max);
}
/* Paragraphes : tableau (éditeur) ou texte libre séparé par des lignes vides */
function paras_in($v, int $max = 20000): array {
    if (is_array($v)) $v = implode("\n\n", array_map(fn($x) => is_scalar($x) ? (string)$x : '', $v));
    return paras(txt($v, $max));
}
function opt($v, array $allowed, string $def): string { return in_array($v, $allowed, true) ? $v : $def; }

/* Reconstruit un contenu propre et complet à partir de ce qu'envoie l'éditeur */
function normalize(array $p): array {
    $d = [];
    $site = $p['site'] ?? [];
    $d['site'] = [
        'name'      => line($site['name'] ?? 'Rinaldo Marasco', 120) ?: 'Rinaldo Marasco',
        'copyright' => line($site['copyright'] ?? '© Rinaldo Marasco', 160),
    ];
    $hm = $p['home'] ?? [];
    $d['home'] = [
        'image' => media_path($hm['image'] ?? '') ?: 'assets/seed.webp',
        'hd'    => media_path($hm['hd'] ?? ''),
        'title' => line($hm['title'] ?? '', 160),
        'w'     => max(1, (int)($hm['w'] ?? 1)),
        'h'     => max(1, (int)($hm['h'] ?? 1)),
        'zoom'  => max(1.0, min(10.0, (float)($hm['zoom'] ?? 2))),
        'speed' => opt($hm['speed'] ?? '', ['slow', 'normal', 'fast'], 'slow'),
    ];

    $d['series'] = [];
    $slugs = [];
    foreach (array_slice((array)($p['series'] ?? []), 0, 40) as $s) {
        if (!is_array($s)) continue;
        $title = line($s['title'] ?? '', 120);
        if ($title === '') continue;
        $slug = slugify((string)($s['slug'] ?? '') !== '' ? (string)$s['slug'] : $title);
        $ser = [
            'id'       => preg_match('/^[a-z0-9-]{1,60}$/', (string)($s['id'] ?? '')) ? $s['id'] : 's-' . bin2hex(random_bytes(3)),
            'slug'     => unique_slug($slug, $slugs),
            'title'    => $title,
            'intro'    => paras_in($s['intro'] ?? []),
            'layout'   => opt($s['layout'] ?? '', ['hero', 'loupe'], 'hero'),
            'shadow'   => opt($s['shadow'] ?? '', ['normal', 'medium', 'strong'], 'normal'),
            'discover' => media_path($s['discover'] ?? ''),
            'works'    => [],
        ];
        $ids = [];
        foreach (array_slice((array)($s['works'] ?? []), 0, 400) as $w) {
            if (!is_array($w)) continue;
            $img = media_path($w['image'] ?? '');
            $wt  = line($w['title'] ?? '', 160);
            if ($img === '' && $wt === '') continue;
            $id = preg_match('/^[a-z0-9-]{1,80}$/', (string)($w['id'] ?? '')) ? (string)$w['id'] : slugify($wt);
            $ser['works'][] = [
                'id'     => unique_slug($id, $ids),
                'title'  => $wt,
                'date'   => line($w['date'] ?? '', 40),
                'dim'    => line($w['dim'] ?? '', 120),
                'tech'   => lines($w['tech'] ?? [], 8),
                'image'  => $img,
                'hd'     => $img !== '' ? media_path($w['hd'] ?? '') : '',
                'w'      => max(1, (int)($w['w'] ?? 1)),
                'h'      => max(1, (int)($w['h'] ?? 1)),
                'zoom'   => max(0.0, min(10.0, (float)($w['zoom'] ?? 0))),
                'speed'  => opt($w['speed'] ?? '', ['slow', 'normal', 'fast'], 'normal'),
                'large'  => !empty($w['large']),
                'thumbs' => media_list($w['thumbs'] ?? [], 24),
            ];
        }
        $d['series'][] = $ser;
    }

    $e = $p['exhibitions'] ?? [];
    $d['exhibitions'] = [
        'title' => line($e['title'] ?? 'Exhibitions', 120) ?: 'Exhibitions',
        'lede'  => txt($e['lede'] ?? '', 2000),
        'items' => [],
    ];
    $ids = [];
    foreach (array_slice((array)($e['items'] ?? []), 0, 300) as $x) {
        if (!is_array($x)) continue;
        $t = line($x['title'] ?? '', 200);
        if ($t === '' && line($x['venue'] ?? '') === '') continue;
        $id = preg_match('/^[a-z0-9-]{1,80}$/', (string)($x['id'] ?? '')) ? (string)$x['id'] : slugify($t . '-' . ($x['year'] ?? ''));
        $d['exhibitions']['items'][] = [
            'id'      => unique_slug($id, $ids),
            'group'   => opt($x['group'] ?? '', ['solo', 'group'], 'solo'),
            'year'    => line($x['year'] ?? '', 20),
            'title'   => $t,
            'venue'   => line($x['venue'] ?? '', 240),
            'text'    => txt($x['text'] ?? '', 12000),
            'images'  => media_list($x['images'] ?? [], 40),
            'caption' => line($x['caption'] ?? '', 240),
        ];
    }

    $a = $p['about'] ?? [];
    $d['about'] = [
        'title'      => line($a['title'] ?? 'About', 120) ?: 'About',
        'paragraphs' => paras_in($a['paragraphs'] ?? []),
        'portrait'   => media_path($a['portrait'] ?? ''),
        'video'      => media_path($a['video'] ?? ''),
        'poster'     => media_path($a['poster'] ?? ''),
    ];
    $c = $p['contact'] ?? [];
    $d['contact'] = [
        'title'     => line($c['title'] ?? 'Contact', 120) ?: 'Contact',
        'email'     => filter_var(line($c['email'] ?? '', 200), FILTER_VALIDATE_EMAIL) ?: '',
        'phone'     => line($c['phone'] ?? '', 60),
        'instagram' => ltrim(line($c['instagram'] ?? '', 80), '@'),
    ];
    return $d;
}

/* ------------------------------------------------------------ UPLOAD -- */
const MAX_SIDE = 2400;      // côté le plus long des images enregistrées
const IMG_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const VID_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mp4'];

function bytes_ini(string $k): int {
    $v = trim((string)ini_get($k)); if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (int)$v; $u = strtolower(substr($v, -1));
    return $u === 'g' ? $n << 30 : ($u === 'm' ? $n << 20 : ($u === 'k' ? $n << 10 : $n));
}
function upload_limit(): int { return min(bytes_ini('upload_max_filesize'), bytes_ini('post_max_size')); }
function unique_name(string $dir, string $slug, string $ext): string {
    $n = $slug . '.' . $ext; $i = 2;
    while (is_file($dir . '/' . $n)) $n = $slug . '-' . $i++ . '.' . $ext;
    return $n;
}

/* Enregistre une image envoyée : version d'affichage (max 2400 px, WebP) et, si
   $keepHd et que l'original est plus grand, le FICHIER ORIGINAL intact (non recompressé)
   chargé par la loupe au premier survol. Renvoie [chemin, largeur, hauteur, hd]. */
function store_image(array $f, bool $keepHd = false): array {
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException(upload_err((int)($f['error'] ?? 1)));
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(IMG_TYPES[$mime])) throw new RuntimeException('Format non pris en charge (JPEG, PNG, WebP ou GIF).');
    $info = @getimagesize($f['tmp_name']);
    if (!$info) throw new RuntimeException('Image illisible.');
    [$w, $h] = $info;
    $slug = slugify(pathinfo((string)$f['name'], PATHINFO_FILENAME));
    $dir  = media_dir();

    $orient = 1;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data($f['tmp_name']);
        $orient = (int)($ex['Orientation'] ?? 1);
    }
    $need = ($w * $h + min(MAX_SIDE, max($w, $h)) ** 2) * 5 + 32 * 1024 * 1024;
    @ini_set('memory_limit', '768M');
    $canGd = function_exists('imagecreatetruecolor') && $mime !== 'image/gif'
          && $need < bytes_ini('memory_limit');

    if ($canGd) {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
            'image/png'  => @imagecreatefrompng($f['tmp_name']),
            'image/webp' => @imagecreatefromwebp($f['tmp_name']),
            default      => false,
        };
        if ($src) {
            if (in_array($orient, [3, 6, 8], true)) {
                $r = imagerotate($src, [3 => 180, 6 => -90, 8 => 90][$orient], 0);
                if ($r) { imagedestroy($src); $src = $r; }
            }
            $w = imagesx($src); $h = imagesy($src);
            $k = min(1, MAX_SIDE / max($w, $h));
            $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $webp = function_exists('imagewebp');
            $name = unique_name($dir, $slug, $webp ? 'webp' : 'jpg');
            $ok = $webp ? imagewebp($dst, $dir . '/' . $name, 90) : imagejpeg($dst, $dir . '/' . $name, 92);
            imagedestroy($dst);
            if ($ok) {
                $hd = '';
                if ($keepHd && $k < 1) {
                    $hn = unique_name($dir, $slug . '-hd', IMG_TYPES[$mime]);
                    if (move_uploaded_file($f['tmp_name'], $dir . '/' . $hn)) $hd = 'media/' . $hn;
                }
                return ['media/' . $name, $nw, $nh, $hd];
            }
        }
    }
    /* Repli : fichier d'origine conservé */
    if ($orient >= 5 && $orient <= 8) [$w, $h] = [$h, $w];
    $name = unique_name($dir, $slug, IMG_TYPES[$mime]);
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Écriture impossible sur le serveur.');
    return ['media/' . $name, $w, $h, ''];
}
function store_video(array $f): array {
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException(upload_err((int)($f['error'] ?? 1)));
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(VID_TYPES[$mime])) throw new RuntimeException('Format vidéo non pris en charge (MP4).');
    $name = unique_name(media_dir(), slugify(pathinfo((string)$f['name'], PATHINFO_FILENAME)), VID_TYPES[$mime]);
    if (!move_uploaded_file($f['tmp_name'], media_dir() . '/' . $name)) throw new RuntimeException('Écriture impossible sur le serveur.');
    return ['media/' . $name, 0, 0, ''];
}
function upload_err(int $c): string {
    return match ($c) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd (max ' . round(upload_limit() / 1048576) . ' Mo).',
        UPLOAD_ERR_PARTIAL => 'Envoi interrompu, réessayez.',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier reçu.',
        default => 'Erreur d\'envoi (' . $c . ').',
    };
}
/* URL utilisable depuis /admin pour prévisualiser une image */
function admin_src(string $p): string { return $p === '' ? '' : '../' . src($p); }
