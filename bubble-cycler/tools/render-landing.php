<?php
// Renders app/views/public/landing.php with the mockup fixtures, for the DOM diff.
// Usage: php tools/render-landing.php fr|en > /tmp/render.html
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$lang = ($argv[1] ?? 'fr') === 'en' ? 'en' : 'fr';
$fixtures = require dirname(__DIR__) . '/fixtures/landing.php';
echo view_capture('public/landing', ['page' => $fixtures[$lang]]);
