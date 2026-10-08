<?php
/**
 * Public pages (terms, privacy) in the Cosmic Loop design: the landing's
 * header and footer around a hero band on the nebula.
 *
 * @var string $content
 * @var string $title
 * @var array $hero  ['eyebrow' => string, 'lines' => string[], 'lead' => string]
 */
$me = current_user();
$edition = cosmic_edition();
$lines = $hero['lines'] ?? [$title ?? site_name()];
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= partial('head', ['title' => $title ?? site_name(), 'description' => $hero['lead'] ?? null, 'indexable' => true]) ?>
</head>
<body class="public-page cosmic-app theme-violet">
<a class="skip-link" href="#content"><?= e(t('Skip to content')) ?></a>
<header class="site-header">
    <?= partial('brand', ['href' => url('index.php')]) ?>
    <nav class="site-nav" aria-label="<?= e(t('Main')) ?>">
        <a href="<?= e(url('index.php')) ?>#experience"><?= e(t('The app')) ?></a>
        <a href="<?= e(url('index.php')) ?>#loop"><?= e(t('The cycle')) ?></a>
        <a href="<?= e(url($me !== null ? 'dashboard.php' : 'login.php')) ?>"><?= e($me !== null ? t('My account') : t('Sign in')) ?></a>
    </nav>
    <?= partial('lang-switch') ?>
    <a class="header-pill" href="<?= e(url($me !== null ? 'buy.php' : 'register.php')) ?>"><?= e($me !== null ? t('Buy bubbles') : t('Create an account')) ?><span aria-hidden="true">↗</span></a>
</header>

<main id="content">
    <section class="page-hero">
        <img class="page-hero__image" src="<?= e(asset('img/hero-08.webp')) ?>" alt="" fetchpriority="high">
        <div class="page-hero__overlay"></div>
        <div class="page-hero__content">
            <p class="eyebrow"><span class="eyebrow__line"></span><?= e($hero['eyebrow'] ?? site_name()) ?></p>
            <h1><?php foreach ($lines as $i => $line): ?><?php if ($i === count($lines) - 1 && $i > 0): ?><span><?= e($line) ?></span><?php else: ?><?= e($line) ?> <?php endif; ?><?php endforeach; ?></h1>
            <?php if (!empty($hero['lead'])): ?><p class="page-hero__lead"><?= e($hero['lead']) ?></p><?php endif; ?>
        </div>
        <div class="hero__caption" aria-hidden="true"><span><?= e($edition['code']) ?></span><span><?= e($edition['palette']) ?></span></div>
    </section>
    <?= partial('flashes', ['flashes' => take_flashes()]) ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <?= partial('brand', ['href' => url('index.php')]) ?>
    <p>© <?= gmdate('Y') ?> <?= e(site_name()) ?> · <a href="<?= e(url('terms.php')) ?>"><?= e(t('Terms & risks')) ?></a> · <a href="<?= e(url('privacy.php')) ?>"><?= e(t('Privacy')) ?></a><?php if (setting('support_email') !== ''): ?> · <a href="mailto:<?= e(setting('support_email')) ?>"><?= e(t('Support')) ?></a><?php endif; ?></p>
    <a href="<?= e(url('index.php')) ?>"><?= e(t('Back to home')) ?> ↗</a>
</footer>
</body>
</html>
