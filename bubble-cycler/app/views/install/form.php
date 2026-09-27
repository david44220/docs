<?php
/**
 * @var array $requirements
 * @var bool $ready
 * @var ?string $error
 * @var array $form
 */
?><!doctype html>
<html lang="en">
<head>
<?= partial('head', ['title' => 'Install']) ?>
</head>
<body class="install-page">
<?= partial('ambient') ?>
<main class="install">
    <header class="install__head">
        <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="48" height="48">
        <h1>Install your bubble cycler</h1>
        <p class="muted">Three minutes: connect MySQL, name your site, create the admin account.</p>
    </header>

    <section class="card">
        <h2 class="card__title">Server check</h2>
        <ul class="checklist">
            <?php foreach ($requirements as $label => $passed): ?>
                <li class="<?= $passed ? 'is-ok' : 'is-bad' ?>"><?= icon($passed ? 'check' : 'x') ?> <?= e($label) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$ready): ?>
            <div class="alert alert--danger"><?= icon('alert') ?><div>Fix the items above (install the PHP extensions, make <code>app/</code> and <code>storage/</code> writable) and reload this page.</div></div>
        <?php endif; ?>
    </section>

    <form method="post" class="card card--glow form" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <h2 class="card__title">1 · Database</h2>
        <p class="card__sub">Create an empty MySQL / MariaDB database (utf8mb4) and a user with full rights on it.</p>
        <div class="field-row field-row--3">
            <label class="field"><span class="field__label">Host</span><input class="input" name="db_host" value="<?= e($form['db_host']) ?>" required></label>
            <label class="field"><span class="field__label">Port</span><input class="input" name="db_port" value="<?= e($form['db_port']) ?>" inputmode="numeric" required></label>
            <label class="field"><span class="field__label">Database name</span><input class="input" name="db_name" value="<?= e($form['db_name']) ?>" required></label>
        </div>
        <div class="field-row">
            <label class="field"><span class="field__label">User</span><input class="input" name="db_user" value="<?= e($form['db_user']) ?>" required></label>
            <label class="field"><span class="field__label">Password</span><input class="input" type="password" name="db_pass" autocomplete="new-password"></label>
        </div>

        <h2 class="card__title">2 · Site</h2>
        <div class="field-row">
            <label class="field"><span class="field__label">Site name</span><input class="input" name="site_name" value="<?= e($form['site_name']) ?>" maxlength="40" required></label>
            <label class="field"><span class="field__label">Public URL <small class="muted">optional</small></span><input class="input" name="base_url" value="<?= e($form['base_url']) ?>" placeholder="https://example.com"></label>
        </div>

        <h2 class="card__title">3 · Admin account</h2>
        <div class="field-row field-row--3">
            <label class="field"><span class="field__label">Username</span><input class="input" name="admin_username" value="<?= e($form['admin_username']) ?>" pattern="[A-Za-z0-9_]{3,20}" required></label>
            <label class="field"><span class="field__label">Email</span><input class="input" type="email" name="admin_email" value="<?= e($form['admin_email']) ?>" required></label>
            <label class="field"><span class="field__label">Password</span><input class="input" type="password" name="admin_password" minlength="8" autocomplete="new-password" required></label>
        </div>

        <button class="btn btn--primary btn--lg btn--block" type="submit"<?= $ready ? '' : ' disabled' ?>><?= icon('zap') ?> Install</button>
    </form>
</main>
</body>
</html>
