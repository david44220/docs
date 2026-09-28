<?php
/**
 * Split screen for sign in, registration and password reset: the Cosmic Loop
 * nebula on the left (the landing's hero image, eyebrow, headline and
 * captions), the form on the right.
 *
 * @var string $content
 */
$pool = pool_state();
$edition = cosmic_edition();
?><!doctype html>
<html lang="en">
<head>
<?= partial('head', ['title' => $title ?? '']) ?>
</head>
<body class="auth-page cosmic-app theme-violet">
<div class="auth">
    <section class="auth__art">
        <img class="auth__image" src="<?= e(asset('img/hero-08.webp')) ?>" alt="" fetchpriority="high">
        <div class="auth__overlay"></div>
        <div class="auth__top">
            <?= partial('brand', ['href' => url('index.php')]) ?>
            <a class="auth__home" href="<?= e(url('index.php')) ?>">Home<span aria-hidden="true">↗</span></a>
        </div>
        <div class="auth__quote">
            <p class="eyebrow"><span class="eyebrow__line"></span><?= e($edition['name']) ?> <span class="eyebrow__dot">·</span> <?= e($edition['number']) ?></p>
            <p class="auth__title"><span>A different</span><span class="auth__title-accent">orbit.</span></p>
            <p class="auth__lead">Bubbles enter their advertising cycle.</p>
            <p class="auth__terms"><?= e(money(setting_int('bubble_price'))) ?> per bubble · <?= e(money(setting_int('pool_share'))) ?> to the pool · expires at <?= e(money(setting_int('bubble_target'))) ?>, in purchase order. No return is guaranteed.</p>
        </div>
        <div class="auth__foot">
            <dl class="auth__stats">
                <div><dt>Bubbles bought</dt><dd><?= number_format((int) $pool['bubbles_sold']) ?></dd></div>
                <div><dt>Expired &amp; paid</dt><dd><?= number_format((int) $pool['bubbles_expired']) ?></dd></div>
                <div><dt>In the pool</dt><dd><?= e(money($pool['balance'])) ?></dd></div>
            </dl>
            <div class="auth__caption" aria-hidden="true"><span><?= e($edition['code']) ?></span><span><?= e($edition['palette']) ?></span></div>
        </div>
    </section>
    <main class="auth__panel">
        <?= partial('flashes', ['flashes' => take_flashes()]) ?>
        <?= $content ?>
        <p class="auth__risk"><?= e(setting('disclaimer')) ?></p>
    </main>
</div>
</body>
</html>
