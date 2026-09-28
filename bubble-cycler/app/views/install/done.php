<?php
/**
 * @var bool $adminCreated
 * @var array $form
 */
?><!doctype html>
<html lang="en">
<head>
<?= partial('head', ['title' => 'Installed']) ?>
</head>
<body class="install-page cosmic-app theme-violet">
<div class="backdrop" aria-hidden="true"><img src="<?= e(asset('img/hero-08.webp')) ?>" alt=""></div>
<main class="install install--done">
    <?= bubble_html(['size' => 'lg', 'state' => 'expired']) ?>
    <p class="eyebrow"><span class="eyebrow__line"></span>Installation complete</p>
    <h1>You’re <span class="text-iris">live.</span></h1>
    <p class="muted">
        <?= $adminCreated
            ? 'Sign in as <strong>' . e($form['admin_username']) . '</strong> to open the admin panel.'
            : 'An admin account already existed in this database — sign in with it.' ?>
    </p>
    <div class="card install__next">
        <h2 class="card__title">Next steps</h2>
        <ol class="ticks ticks--numbered">
            <li>Open <strong>Admin → Payment methods</strong>, put your real wallet / bank details on a deposit method and activate it.</li>
            <li>Add at least one withdrawal method.</li>
            <li>Review <strong>Admin → Settings</strong>: bubble price, pool share, expiry target, ad timer.</li>
            <li>Delete <code>public/install.php</code> (it is already locked by <code>storage/installed.lock</code>).</li>
        </ol>
    </div>
    <a class="btn btn--primary btn--lg" href="<?= e(url('login.php')) ?>">Sign in<span class="btn__glyph" aria-hidden="true">↗</span></a>
</main>
</body>
</html>
