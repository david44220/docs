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
    // Full public URL, e.g. https://example.com — required for emails (reset links).
    'base_url'    => '',
    // 32 random bytes, base64: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    // Encrypts two-factor secrets and the SMTP password. Never change it once set.
    'app_key'     => '',
    // Show error details. Never enable on a live site.
    'debug'       => false,
    // Redirect every http:// request to https:// (enable once HTTPS works).
    'force_https' => false,
    // Send Strict-Transport-Security on HTTPS responses.
    'hsts'        => true,
    // Send the Content-Security-Policy header (disable only if you add third-party scripts).
    'csp'         => true,
    // Sign members out after this many seconds of inactivity.
    'session_idle' => 7200,
    // Behind Cloudflare or a reverse proxy: e.g. 'HTTP_CF_CONNECTING_IP' / true.
    'ip_header'   => '',
    'trust_proxy' => false,
];
