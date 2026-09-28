<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

render('public/terms', ['title' => 'Terms & risks', 'hero' => [
    'eyebrow' => 'Legal · ' . site_name(),
    'lines'   => ['Terms', '& risks.'],
    'lead'    => 'Please read this page before depositing or buying bubbles.',
]], 'public');
