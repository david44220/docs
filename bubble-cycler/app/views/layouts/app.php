<?php
/**
 * Member & admin shell: sidebar navigation, wallet pills, notices.
 *
 * @var string $content
 * @var string $title
 * @var string $page     active navigation key
 */
$me = current_user();
$adminArea = !empty($admin_area);
$nav = $adminArea ? admin_nav() : user_nav();
$page ??= '';
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= partial('head', ['title' => $title ?? '', 'scripts' => $scripts ?? []]) ?>
</head>
<body class="shell cosmic-app theme-violet<?= $adminArea ? ' shell--admin' : '' ?>">
<a class="skip-link" href="#content"><?= e(t('Skip to content')) ?></a>

<aside class="sidebar" id="sidebar" data-sidebar>
    <div class="sidebar__top">
        <?= partial('brand', ['href' => url($adminArea ? 'admin/index.php' : 'dashboard.php')]) ?>
        <?php if ($adminArea): ?><span class="sidebar__tag"><?= icon('shield') ?><?= e(t('Admin')) ?></span><?php endif; ?>
        <button class="icon-btn sidebar__close" type="button" data-sidebar-close aria-label="<?= e(t('Close menu')) ?>"><?= icon('x') ?></button>
    </div>

    <nav class="nav" aria-label="<?= e(t('Main')) ?>">
        <?php foreach ($nav as $section => $items): ?>
            <div class="nav__section"><?= e($section) ?></div>
            <?php foreach ($items as $item): ?>
                <?php [$key, $file, $label, $iconName] = $item; $badge = (int) ($item[4] ?? 0); ?>
                <a class="nav__item<?= $key === $page ? ' is-active' : '' ?>" href="<?= e(url($file)) ?>"<?= $key === $page ? ' aria-current="page"' : '' ?>>
                    <?= icon($iconName) ?>
                    <span><?= e($label) ?></span>
                    <?php if ($badge > 0): ?><b class="nav__badge"><?= e(num($badge)) ?></b><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot">
        <?php if ($adminArea): ?>
            <a class="nav__item nav__item--switch" href="<?= e(url('dashboard.php')) ?>"><?= icon('arrow-left') ?><span><?= e(t('Member area')) ?></span></a>
        <?php elseif (is_admin($me)): ?>
            <a class="nav__item nav__item--switch" href="<?= e(url('admin/index.php')) ?>"><?= icon('shield') ?><span><?= e(t('Admin panel')) ?></span></a>
        <?php endif; ?>
        <div class="sidebar__lang"><span><?= e(t('Language')) ?></span><?= partial('lang-switch', ['variant' => 'segmented']) ?></div>
        <div class="me">
            <?= user_avatar($me['username']) ?>
            <div class="me__text">
                <strong><?= e($me['username']) ?></strong>
                <small><?= e($me['role'] === 'admin' ? t('Administrator') : t('Member since {date}', ['date' => fmt_date($me['created_at'], 'M Y')])) ?></small>
            </div>
            <form method="post" action="<?= e(url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="<?= e(t('Sign out')) ?>" aria-label="<?= e(t('Sign out')) ?>"><?= icon('logout') ?></button>
            </form>
        </div>
    </div>
</aside>
<div class="sidebar-scrim" data-sidebar-close></div>

<div class="main">
    <header class="topbar">
        <button class="icon-btn topbar__menu" type="button" data-sidebar-open aria-label="<?= e(t('Open menu')) ?>" aria-controls="sidebar"><?= icon('menu') ?></button>
        <div class="topbar__title">
            <span class="eyebrow"><?= e(!empty($eyebrow) ? $eyebrow : ($adminArea ? t('Admin panel') : t('Member area'))) ?></span>
            <h1><?= e($title ?? '') ?></h1>
        </div>
        <div class="topbar__wallets">
            <a class="pill" href="<?= e(url('deposit.php')) ?>" title="<?= e(t('Purchase balance — used to buy bubbles')) ?>">
                <span class="pill__dot pill__dot--violet"></span><span class="pill__label"><?= e(t('Purchase')) ?></span><b><?= e(money($me['purchase_balance'])) ?></b>
            </a>
            <a class="pill" href="<?= e(url('withdraw.php')) ?>" title="<?= e(t('Cash balance — bubble payouts, withdrawable')) ?>">
                <span class="pill__dot pill__dot--green"></span><span class="pill__label"><?= e(t('Cash')) ?></span><b><?= e(money($me['cash_balance'])) ?></b>
            </a>
            <a class="pill pill--hide-md" href="<?= e(url('advertise.php')) ?>" title="<?= e(t('Advertising credits')) ?>">
                <span class="pill__dot pill__dot--pink"></span><span class="pill__label"><?= e(t('Ad credits')) ?></span><b><?= e(num($me['ad_credits'])) ?></b>
            </a>
        </div>
        <a class="btn btn--primary topbar__cta" href="<?= e(url('buy.php')) ?>" aria-label="<?= e(t('Buy bubbles')) ?>"><span><?= e(t('Buy bubbles')) ?></span><span class="btn__glyph" aria-hidden="true">↗</span></a>
    </header>

    <main class="content" id="content">
        <?= partial('flashes', ['flashes' => take_flashes()]) ?>
        <?= $content ?>
    </main>

    <footer class="footer">
        <p class="footer__risk"><?= icon('info') ?><span><?= e(setting_text('disclaimer')) ?></span></p>
        <p class="footer__meta">© <?= gmdate('Y') ?> <?= e(site_name()) ?> · <a href="<?= e(url('terms.php')) ?>"><?= e(t('Terms & risks')) ?></a> · <a href="<?= e(url('privacy.php')) ?>"><?= e(t('Privacy')) ?></a><?php if (setting('support_email') !== ''): ?> · <a href="mailto:<?= e(setting('support_email')) ?>"><?= e(t('Support')) ?></a><?php endif; ?></p>
    </footer>
</div>
</body>
</html>
