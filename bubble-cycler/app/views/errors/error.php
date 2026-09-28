<?php
/**
 * Stand-alone error page (works even when the database is down).
 *
 * @var int $code
 * @var string $message
 */
$titles = [
    403 => ['Access denied', 'You do not have permission to open this page.'],
    404 => ['Page not found', 'This bubble floated away — the page you are looking for does not exist.'],
    413 => ['Upload too large', 'The data you sent is larger than the server accepts.'],
    419 => ['Session expired', 'For your security the form expired. Please go back and try again.'],
    500 => ['Something went wrong', 'An unexpected error occurred. Please try again in a moment.'],
    503 => ['Back in a moment', 'We are performing scheduled maintenance. Please check back shortly.'],
];
[$heading, $fallback] = $titles[$code] ?? ['Error', 'Something went wrong.'];
$debug ??= '';
?><!doctype html>
<html lang="en">
<head>
<?= partial('head', ['title' => $heading]) ?>
</head>
<body class="error-page cosmic-app theme-violet">
<div class="backdrop" aria-hidden="true"><img src="<?= e(asset('img/hero-08.webp')) ?>" alt=""></div>
<main class="error">
    <p class="eyebrow"><span class="eyebrow__line"></span><?= e(site_name()) ?> <span class="eyebrow__dot">·</span> <?= (int) $code ?></p>
    <p class="error__code" aria-hidden="true"><?= (int) $code ?></p>
    <h1><?= e($heading) ?></h1>
    <p><?= e($message !== '' ? $message : $fallback) ?></p>
    <?php if ($debug !== ''): ?><pre class="error__debug"><?= e($debug) ?></pre><?php endif; ?>
    <div class="error__actions">
        <a class="btn btn--primary btn--lg" href="<?= e(url('index.php')) ?>">Home<span class="btn__glyph" aria-hidden="true">↗</span></a>
        <?php if ($code !== 503): ?><a class="btn btn--secondary btn--lg" href="<?= e(url('dashboard.php')) ?>" data-back><?= icon('arrow-left') ?> Go back</a><?php endif; ?>
    </div>
</main>
</body>
</html>
