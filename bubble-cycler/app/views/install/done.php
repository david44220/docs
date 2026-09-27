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
<body class="install-page">
<?= partial('ambient') ?>
<main class="install install--done">
    <?= bubble_html(['size' => 'lg', 'state' => 'filling', 'fill' => 100, 'label' => '✓']) ?>
    <h1>You're live!</h1>
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
    <a class="btn btn--primary btn--lg" href="<?= e(url('login.php')) ?>"><?= icon('arrow-right') ?> Sign in</a>
</main>
</body>
</html>
