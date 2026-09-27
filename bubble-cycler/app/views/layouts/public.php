<?php
/**
 * Marketing layout (landing page, terms).
 *
 * @var string $content
 */
$me = current_user();
?><!doctype html>
<html lang="en">
<head>
<?= partial('head', ['title' => $title ?? site_name(), 'indexable' => true]) ?>
</head>
<body class="site">
<a class="skip-link" href="#content">Skip to content</a>
<?= partial('ambient') ?>

<header class="site-header" data-site-header>
    <div class="site-header__inner">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="34" height="34">
            <span class="brand__name"><?= e(site_name()) ?></span>
        </a>
        <nav class="site-nav" aria-label="Main">
            <a href="<?= e(url('index.php')) ?>#how">How it works</a>
            <a href="<?= e(url('index.php')) ?>#pool">Live pool</a>
            <a href="<?= e(url('index.php')) ?>#advertise">Advertising</a>
            <a href="<?= e(url('index.php')) ?>#faq">FAQ</a>
        </nav>
        <div class="site-header__actions">
            <?php if ($me !== null): ?>
                <a class="btn btn--primary btn--sm" href="<?= e(url('dashboard.php')) ?>">Open dashboard <?= icon('arrow-right') ?></a>
            <?php else: ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('login.php')) ?>">Sign in</a>
                <a class="btn btn--primary btn--sm" href="<?= e(url('register.php')) ?>">Get started</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="content">
    <?= partial('flashes', ['flashes' => take_flashes()]) ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__brand">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="30" height="30">
                <span class="brand__name"><?= e(site_name()) ?></span>
            </a>
            <p class="muted">A transparent bubble cycler with a built-in advertising network.</p>
        </div>
        <nav class="site-footer__links" aria-label="Footer">
            <a href="<?= e(url('register.php')) ?>">Create account</a>
            <a href="<?= e(url('login.php')) ?>">Sign in</a>
            <a href="<?= e(url('terms.php')) ?>">Terms &amp; risks</a>
            <a href="<?= e(url('privacy.php')) ?>">Privacy</a>
            <?php if (setting('support_email') !== ''): ?><a href="mailto:<?= e(setting('support_email')) ?>">Support</a><?php endif; ?>
        </nav>
    </div>
    <p class="site-footer__risk"><?= icon('info') ?><span><?= e(setting('disclaimer')) ?></span></p>
    <p class="site-footer__copy">© <?= gmdate('Y') ?> <?= e(site_name()) ?>. All rights reserved.</p>
</footer>
</body>
</html>
