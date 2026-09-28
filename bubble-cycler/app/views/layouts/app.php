<?php
/**
 * Member & admin shell: sidebar navigation, wallet pills, toasts.
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
<html lang="en">
<head>
<?= partial('head', ['title' => $title ?? '', 'scripts' => $scripts ?? []]) ?>
</head>
<body class="shell cosmic-app theme-violet<?= $adminArea ? ' shell--admin' : '' ?>">
<a class="skip-link" href="#content">Skip to content</a>

<aside class="sidebar" id="sidebar" data-sidebar>
    <div class="sidebar__top">
        <?= partial('brand', ['href' => url($adminArea ? 'admin/index.php' : 'dashboard.php')]) ?>
        <?php if ($adminArea): ?><span class="sidebar__tag"><?= icon('shield') ?>Admin</span><?php endif; ?>
        <button class="icon-btn sidebar__close" type="button" data-sidebar-close aria-label="Close menu"><?= icon('x') ?></button>
    </div>

    <nav class="nav" aria-label="Main">
        <?php foreach ($nav as $section => $items): ?>
            <div class="nav__section"><?= e($section) ?></div>
            <?php foreach ($items as $item): ?>
                <?php [$key, $file, $label, $iconName] = $item; $badge = (int) ($item[4] ?? 0); ?>
                <a class="nav__item<?= $key === $page ? ' is-active' : '' ?>" href="<?= e(url($file)) ?>"<?= $key === $page ? ' aria-current="page"' : '' ?>>
                    <?= icon($iconName) ?>
                    <span><?= e($label) ?></span>
                    <?php if ($badge > 0): ?><b class="nav__badge"><?= $badge ?></b><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot">
        <?php if ($adminArea): ?>
            <a class="nav__item nav__item--switch" href="<?= e(url('dashboard.php')) ?>"><?= icon('arrow-left') ?><span>Member area</span></a>
        <?php elseif (is_admin($me)): ?>
            <a class="nav__item nav__item--switch" href="<?= e(url('admin/index.php')) ?>"><?= icon('shield') ?><span>Admin panel</span></a>
        <?php endif; ?>
        <div class="me">
            <?= user_avatar($me['username']) ?>
            <div class="me__text">
                <strong><?= e($me['username']) ?></strong>
                <small><?= $me['role'] === 'admin' ? 'Administrator' : 'Member since ' . e(fmt_date($me['created_at'], 'M Y')) ?></small>
            </div>
            <form method="post" action="<?= e(url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="Sign out" aria-label="Sign out"><?= icon('logout') ?></button>
            </form>
        </div>
    </div>
</aside>
<div class="sidebar-scrim" data-sidebar-close></div>

<div class="main">
    <header class="topbar">
        <button class="icon-btn topbar__menu" type="button" data-sidebar-open aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
        <div class="topbar__title">
            <span class="eyebrow"><?= e(!empty($eyebrow) ? $eyebrow : ($adminArea ? 'Admin panel' : 'Member area')) ?></span>
            <h1><?= e($title ?? '') ?></h1>
        </div>
        <div class="topbar__wallets">
            <a class="pill" href="<?= e(url('deposit.php')) ?>" title="Purchase balance — used to buy bubbles">
                <span class="pill__dot pill__dot--violet"></span><span class="pill__label">Purchase</span><b><?= e(money($me['purchase_balance'])) ?></b>
            </a>
            <a class="pill" href="<?= e(url('withdraw.php')) ?>" title="Cash balance — bubble payouts, withdrawable">
                <span class="pill__dot pill__dot--green"></span><span class="pill__label">Cash</span><b><?= e(money($me['cash_balance'])) ?></b>
            </a>
            <a class="pill pill--hide-md" href="<?= e(url('advertise.php')) ?>" title="Advertising credits">
                <span class="pill__dot pill__dot--pink"></span><span class="pill__label">Ad credits</span><b><?= number_format((int) $me['ad_credits']) ?></b>
            </a>
        </div>
        <a class="btn btn--primary topbar__cta" href="<?= e(url('buy.php')) ?>" aria-label="Buy bubbles"><span>Buy bubbles</span><span class="btn__glyph" aria-hidden="true">↗</span></a>
    </header>

    <main class="content" id="content">
        <?= partial('flashes', ['flashes' => take_flashes()]) ?>
        <?= $content ?>
    </main>

    <footer class="footer">
        <p class="footer__risk"><?= icon('info') ?><span><?= e(setting('disclaimer')) ?></span></p>
        <p class="footer__meta">© <?= gmdate('Y') ?> <?= e(site_name()) ?> · <a href="<?= e(url('terms.php')) ?>">Terms &amp; risks</a> · <a href="<?= e(url('privacy.php')) ?>">Privacy</a><?= setting('support_email') !== '' ? ' · <a href="mailto:' . e(setting('support_email')) . '">Support</a>' : '' ?></p>
    </footer>
</div>
</body>
</html>
