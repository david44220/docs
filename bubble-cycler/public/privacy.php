<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

render('public/privacy', ['title' => t('Privacy policy'), 'hero' => [
    'eyebrow' => t('Legal · {site}', ['site' => site_name()]),
    'lines'   => [t('Privacy'), t('policy.')],
    'lead'    => t('What {site} stores about you, why, and how to reach us.', ['site' => site_name()]),
]], 'public');
