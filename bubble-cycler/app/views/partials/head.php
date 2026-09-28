<?php
/**
 * Shared <head> contents.
 *
 * @var string $title
 * @var ?string $description
 * @var array $scripts     extra scripts (relative to assets/), loaded before app.js
 * @var bool $indexable    public marketing pages only; everything else is noindex
 */
$pageTitle = isset($title) && $title !== '' && $title !== site_name() ? $title . ' · ' . site_name() : site_name();
$metaDescription = $description ?? site_name() . ' — buy bubbles, watch the pool fill them in queue order, and advertise with the credits every bubble includes.';
$indexable ??= false;
$origin = mail_base_url() !== '' ? (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', mail_base_url()) : '';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php if (!$indexable): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php if ($origin !== ''): ?>
<meta property="og:url" content="<?= e($origin . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)) ?>">
<meta property="og:image" content="<?= e($origin . asset('img/og.jpg')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<meta name="theme-color" content="#07090f">
<meta name="color-scheme" content="dark">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('manifest.php')) ?>">
<?php /* Same URL as the @font-face rule in fonts.css (no ?v=), or the browser downloads the font twice. */ ?>
<link rel="preload" href="<?= e(url('assets/fonts/inter-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php /* The Cosmic Loop design: Inter, the mockup's stylesheet unchanged, then the application layer. */ ?>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/cosmic.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php foreach ($scripts ?? [] as $script): ?>
<script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
