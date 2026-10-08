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
<html lang="<?= e(lang()) ?>">
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
            <div class="auth__actions"><?= partial('lang-switch') ?><a class="auth__home" href="<?= e(url('index.php')) ?>"><?= e(t('Home')) ?><span aria-hidden="true">↗</span></a></div>
        </div>
        <div class="auth__quote">
            <p class="eyebrow"><span class="eyebrow__line"></span><?= e($edition['name']) ?> <span class="eyebrow__dot">·</span> <?= e($edition['number']) ?></p>
            <p class="auth__title"><span><?= e(t('A different')) ?></span><span class="auth__title-accent"><?= e(t('orbit.')) ?></span></p>
            <p class="auth__lead"><?= e(t('Bubbles enter their advertising cycle.')) ?></p>
            <p class="auth__terms"><?= e(t('{price} per bubble · {share} to the pool · expires at {target}, in purchase order. No return is guaranteed.', ['price' => money(setting_int('bubble_price')), 'share' => money(setting_int('pool_share')), 'target' => money(setting_int('bubble_target'))])) ?></p>
        </div>
        <div class="auth__foot">
            <dl class="auth__stats">
                <div><dt><?= e(t('Bubbles bought')) ?></dt><dd><?= e(num($pool['bubbles_sold'])) ?></dd></div>
                <div><dt><?= e(t('Expired & paid')) ?></dt><dd><?= e(num($pool['bubbles_expired'])) ?></dd></div>
                <div><dt><?= e(t('In the pool')) ?></dt><dd><?= e(money($pool['balance'])) ?></dd></div>
            </dl>
            <div class="auth__caption" aria-hidden="true"><span><?= e($edition['code']) ?></span><span><?= e($edition['palette']) ?></span></div>
        </div>
    </section>
    <main class="auth__panel">
        <?= partial('flashes', ['flashes' => take_flashes()]) ?>
        <?= $content ?>
        <p class="auth__risk"><?= e(setting_text('disclaimer')) ?></p>
    </main>
</div>
</body>
</html>
