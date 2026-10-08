<?php
/**
 * @var bool $adminCreated
 * @var array $form
 */
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= partial('head', ['title' => t('Installed')]) ?>
</head>
<body class="install-page cosmic-app theme-violet">
<div class="backdrop" aria-hidden="true"><img src="<?= e(asset('img/hero-08.webp')) ?>" alt=""></div>
<main class="install install--done">
    <?= bubble_html(['size' => 'lg', 'state' => 'expired']) ?>
    <p class="eyebrow"><span class="eyebrow__line"></span><?= e(t('Installation complete')) ?></p>
    <h1><?= e(t('You’re')) ?> <span class="text-iris"><?= e(t('live.')) ?></span></h1>
    <p class="muted">
        <?php /* trusted: t_html() escapes the text, the name is escaped here */ ?>
        <?= $adminCreated
            ? t_html('Sign in as {name} to open the admin panel.', ['name' => '<strong>' . e($form['admin_username']) . '</strong>'])
            : e(t('An admin account already existed in this database — sign in with it.')) ?>
    </p>
    <div class="card install__next">
        <h2 class="card__title"><?= e(t('Next steps')) ?></h2>
        <ol class="ticks ticks--numbered">
            <li><?= t_html('Open {menu}, put your real wallet / bank details on a deposit method and activate it.', ['menu' => '<strong>' . e(t('Admin → Payment methods')) . '</strong>']) ?></li>
            <li><?= e(t('Add at least one withdrawal method.')) ?></li>
            <li><?= t_html('Review {menu}: bubble price, pool share, expiry target, ad timer.', ['menu' => '<strong>' . e(t('Admin → Settings')) . '</strong>']) ?></li>
            <li><?= t_html('Delete {file} (it is already locked by {lock}).', ['file' => '<code>public/install.php</code>', 'lock' => '<code>storage/installed.lock</code>']) ?></li>
        </ol>
    </div>
    <a class="btn btn--primary btn--lg" href="<?= e(url('login.php')) ?>"><?= e(t('Sign in')) ?><span class="btn__glyph" aria-hidden="true">↗</span></a>
</main>
</body>
</html>
