<?php
/**
 * Stand-alone error page (works even when the database is down).
 *
 * @var int $code
 * @var string $message
 */
$titles = [
    403 => [t('Access denied'), t('You do not have permission to open this page.')],
    404 => [t('Page not found'), t('This bubble floated away — the page you are looking for does not exist.')],
    413 => [t('Upload too large'), t('The data you sent is larger than the server accepts.')],
    419 => [t('Session expired'), t('For your security the form expired. Please go back and try again.')],
    500 => [t('Something went wrong'), t('An unexpected error occurred. Please try again in a moment.')],
    503 => [t('Back in a moment'), t('We are performing scheduled maintenance. Please check back shortly.')],
];
[$heading, $fallback] = $titles[$code] ?? [t('Error'), t('Something went wrong.')];
$debug ??= '';
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= partial('head', ['title' => $heading]) ?>
</head>
<body class="error-page cosmic-app theme-violet">
<div class="backdrop" aria-hidden="true"><img src="<?= e(asset('img/hero-08.webp')) ?>" alt=""></div>
<div class="corner-lang"><?= partial('lang-switch') ?></div>
<main class="error">
    <p class="eyebrow"><span class="eyebrow__line"></span><?= e(site_name()) ?> <span class="eyebrow__dot">·</span> <?= (int) $code ?></p>
    <p class="error__code" aria-hidden="true"><?= (int) $code ?></p>
    <h1><?= e($heading) ?></h1>
    <p><?= e($message !== '' ? $message : $fallback) ?></p>
    <?php if ($debug !== ''): ?><pre class="error__debug"><?= e($debug) ?></pre><?php endif; ?>
    <div class="error__actions">
        <a class="btn btn--primary btn--lg" href="<?= e(url('index.php')) ?>"><?= e(t('Home')) ?><span class="btn__glyph" aria-hidden="true">↗</span></a>
        <?php if ($code !== 503): ?><a class="btn btn--secondary btn--lg" href="<?= e(url('dashboard.php')) ?>" data-back><?= icon('arrow-left') ?> <?= e(t('Go back')) ?></a><?php endif; ?>
    </div>
</main>
</body>
</html>
