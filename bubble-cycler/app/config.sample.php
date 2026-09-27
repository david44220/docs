<?php
/**
 * Copy this file to app/config.php (the web installer does it for you).
 */
return [
    'db' => [
        'host' => '127.0.0.1',   // or a socket path such as /var/run/mysqld/mysqld.sock
        'port' => 3306,
        'name' => 'bubblecycle',
        'user' => 'bubblecycle',
        'pass' => 'change-me',
    ],
    // Full public URL, e.g. https://example.com — leave empty to auto-detect.
    'base_url'    => '',
    // Show error details. Never enable on a live site.
    'debug'       => false,
    // Send the Content-Security-Policy header (disable only if you add third-party scripts).
    'csp'         => true,
    // Behind Cloudflare or a reverse proxy: e.g. 'HTTP_CF_CONNECTING_IP' / true.
    'ip_header'   => '',
    'trust_proxy' => false,
];
