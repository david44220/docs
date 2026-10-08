<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

render('public/terms', ['title' => t('Terms & risks'), 'hero' => [
    'eyebrow' => t('Legal · {site}', ['site' => site_name()]),
    'lines'   => [t('Terms'), t('& risks.')],
    'lead'    => t('Please read this page before depositing or buying bubbles.'),
]], 'public');
