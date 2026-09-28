<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

render('public/privacy', ['title' => 'Privacy policy', 'hero' => [
    'eyebrow' => 'Legal · ' . site_name(),
    'lines'   => ['Privacy', 'policy.'],
    'lead'    => 'What ' . site_name() . ' stores about you, why, and how to reach us.',
]], 'public');
