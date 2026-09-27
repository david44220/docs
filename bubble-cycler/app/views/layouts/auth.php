<?php
/**
 * Split-screen layout for sign in / registration.
 *
 * @var string $content
 */
$pool = pool_state();
?><!doctype html>
<html lang="en">
<head>
<?= partial('head', ['title' => $title ?? '']) ?>
</head>
<body class="auth-page">
<?= partial('ambient') ?>
<div class="auth">
    <section class="auth__art" aria-hidden="true">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="34" height="34">
            <span class="brand__name"><?= e(site_name()) ?></span>
        </a>
        <div class="auth__cluster">
            <?= bubble_html(['size' => 'xl', 'state' => 'filling', 'fill' => 62, 'delay' => '-1']) ?>
            <?= bubble_html(['size' => 'md', 'state' => 'idle', 'delay' => '-3']) ?>
            <?= bubble_html(['size' => 'sm', 'state' => 'idle', 'delay' => '-5']) ?>
            <?= bubble_html(['size' => 'xs', 'state' => 'idle', 'delay' => '-2']) ?>
        </div>
        <div class="auth__quote">
            <h2>Every bubble rises.<br><span class="text-iris">The pool decides when it expires.</span></h2>
            <p><?= e(money(setting_int('bubble_price'))) ?> per bubble · <?= e(money(setting_int('pool_share'))) ?> to the pool · expires at <?= e(money(setting_int('bubble_target'))) ?></p>
        </div>
        <dl class="auth__stats">
            <div><dt>Bubbles bought</dt><dd><?= number_format((int) $pool['bubbles_sold']) ?></dd></div>
            <div><dt>Expired &amp; paid</dt><dd><?= number_format((int) $pool['bubbles_expired']) ?></dd></div>
            <div><dt>In the pool</dt><dd><?= e(money($pool['balance'])) ?></dd></div>
        </dl>
    </section>
    <section class="auth__panel">
        <a class="brand auth__brand-mobile" href="<?= e(url('index.php')) ?>">
            <img class="brand__mark" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="30" height="30">
            <span class="brand__name"><?= e(site_name()) ?></span>
        </a>
        <?= partial('flashes', ['flashes' => take_flashes()]) ?>
        <?= $content ?>
        <p class="auth__risk"><?= e(setting('disclaimer')) ?></p>
    </section>
</div>
</body>
</html>
