<?php
/** Shared <head> contents. Expects $title. */
$pageTitle = isset($title) && $title !== site_name() ? $title . ' · ' . site_name() : site_name();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description ?? site_name() . ' — buy bubbles, watch the pool fill them in queue order, and advertise with the credits every bubble includes.') ?>">
<meta name="theme-color" content="#07080f">
<meta name="color-scheme" content="dark">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(asset('fonts/sora-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('fonts/inter-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
