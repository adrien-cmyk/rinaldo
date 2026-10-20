<?php
/* Sert les images/vidéos ajoutées via l'admin (dossier de contenu partagé) */
require_once __DIR__ . '/inc/core.php';
$f = basename((string)($_GET['f'] ?? ''));
$types = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
          'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$path = media_dir() . '/' . $f;
if ($f === '' || $f[0] === '.' || !isset($types[$ext]) || !is_file($path)) { http_response_code(404); exit; }
$size = filesize($path); $mtime = filemtime($path);
$etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';
header('Content-Type: ' . $types[$ext]);
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
/* Range (lecture vidéo) */
$start = 0; $end = $size - 1;
if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $m)) {
    if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); }
    else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); }
    if ($start > $end) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));
$fp = fopen($path, 'rb'); fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp)) { $chunk = fread($fp, min(65536, $left)); echo $chunk; $left -= strlen($chunk); }
fclose($fp);
