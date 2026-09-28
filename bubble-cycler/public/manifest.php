<?php
/** Web app manifest (lets members add the site to their home screen). */
const STATELESS = true;
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo json_encode([
    'name'             => site_name(),
    'short_name'       => mb_substr(site_name(), 0, 12),
    'start_url'        => url('dashboard.php'),
    'scope'            => url(''),
    'display'          => 'standalone',
    'background_color' => '#07090f',
    'theme_color'      => '#07090f',
    'icons'            => [
        ['src' => asset('img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => asset('img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => asset('img/logo.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
