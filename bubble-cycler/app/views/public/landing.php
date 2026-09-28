<?php
/**
 * Landing page — Cosmic Loop, edition 08 (mockups/cosmic-loop).
 *
 * The markup reproduces, element for element and class for class, what the
 * mockup's app.js renders on the client; the stylesheet is the mockup's
 * styles.css byte for byte (assets/css/cosmic.css). Every text and link comes
 * from $page (app/lib/landing.php, or fixtures/landing.php for the DOM diff).
 *
 * @var array $page
 */
$hero = $page['hero'];
?><!doctype html>
<html lang="<?= e($page['lang']) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#080a0d">
  <meta name="description" content="<?= e($page['description']) ?>">
<?php foreach ($page['head'] ?? [] as $tag): ?>
  <?= $tag /* trusted: built by landing_head_tags() from escaped values */ ?>

<?php endforeach; ?>
<?php foreach ($page['styles'] as $href): ?>
  <link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
  <title><?= e($page['title']) ?></title>
</head>
<body id="top" data-edition="08" class="landing-page theme-violet layout-cosmic" style="--page-bg: #07090f; --ink: #f7f5f0; --muted: #b8bdc9;">
  <div id="app"><header class="site-header"><a class="brand" href="#top" aria-label="<?= e($page['brand']['label']) ?>"><span class="brand__mark" aria-hidden="true"><i></i><i></i><i></i></span><span><?= e($page['brand']['name']) ?><span class="brand__light"><?= e($page['brand']['light']) ?></span></span></a><nav class="site-nav" aria-label="<?= e($page['nav_label']) ?>"><?php foreach ($page['nav'] as $link): ?><a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a><?php endforeach; ?></nav><button class="language-switch" type="button" data-lang-switch aria-label="<?= e($page['switch']['label']) ?>"><?= e($page['switch']['text']) ?><span aria-hidden="true">↗</span></button></header><main><section class="hero hero--cosmic"><img class="hero__image" src="<?= e($page['image']) ?>" alt="<?= e($hero['alt']) ?>" fetchpriority="high"><div class="hero__overlay"></div><div class="hero__content"><p class="eyebrow"><span class="eyebrow__line"></span><?= e($page['edition']['name']) ?> <span class="eyebrow__dot">·</span> <?= e($page['edition']['number']) ?></p><h1><?php foreach ($hero['lines'] as $i => $line): ?><span class="hero__line<?= $i === count($hero['lines']) - 1 ? ' hero__line--accent' : '' ?>"><?= e($line) ?></span><?php endforeach; ?></h1><p class="hero__lead"><?= e($hero['lead']) ?></p><p class="hero__intro"><?= e($hero['intro']) ?></p><div class="hero__actions"><a class="button button--primary" href="<?= e($hero['primary']['href']) ?>"><?= e($hero['primary']['label']) ?><span aria-hidden="true"><?= e($hero['primary']['icon']) ?></span></a><a class="text-link" href="<?= e($hero['link']['href']) ?>"><?= e($hero['link']['label']) ?></a><a class="button button--download" href="<?= e($hero['secondary']['href']) ?>"<?php if (!empty($hero['secondary']['download'])): ?> download="<?= e($hero['secondary']['download']) ?>"<?php endif; ?>><?= e($hero['secondary']['label']) ?><span aria-hidden="true"><?= e($hero['secondary']['icon']) ?></span></a></div></div><div class="hero__caption"><span><?= e($page['edition']['code']) ?></span><span><?= e($page['edition']['palette']) ?></span></div><a class="scroll-cue" href="#experience" aria-label="<?= e($hero['scroll']) ?>"><span aria-hidden="true">↓</span></a></section><section class="experience section" id="experience"><div class="experience__copy"><p class="eyebrow"><?= e($page['story']['kicker']) ?></p><h2><?= e($page['story']['title']) ?></h2><p class="section-copy"><?= e($page['story']['body']) ?></p></div><div class="experience__art"><img src="<?= e($page['image']) ?>" alt="" loading="lazy" decoding="async"><span class="experience__art-tag"><?= e($page['story']['tag']) ?></span><span class="experience__art-note"><?= e($page['story']['note']) ?></span></div></section><section class="features section"><div class="section-heading"><div><p class="eyebrow"><?= e($page['features']['kicker']) ?></p><h2><?= e($page['features']['title']) ?></h2></div><span class="section-heading__mark" aria-hidden="true">✳</span></div><div class="feature-grid"><?php foreach ($page['features']['cards'] as $i => $card): ?><article class="feature-card"><span class="feature-card__index">0<?= $i + 1 ?></span><h3><?= e($card['title']) ?></h3><p><?= e($card['body']) ?></p><span class="feature-card__rule" aria-hidden="true"></span></article><?php endforeach; ?></div><p class="risk-note"><?= e($page['features']['disclaimer']) ?></p></section><section class="loop-section section" id="loop"><div class="loop-section__intro"><p class="eyebrow"><?= e($page['loop']['kicker']) ?></p><h2><?= e($page['loop']['title']) ?></h2></div><div class="step-grid"><?php foreach ($page['loop']['steps'] as $i => $step): ?><article class="step-card"><span class="step-card__index">0<?= $i + 1 ?></span><div><h3><?= e($step['title']) ?></h3><p><?= e($step['body']) ?></p></div></article><?php endforeach; ?></div></section><section class="closing-section"><div class="closing-section__content"><p class="eyebrow"><?= e($page['closing']['kicker']) ?></p><h2><?= e($page['closing']['title']) ?></h2><p><?= e($page['closing']['body']) ?></p><a class="button button--primary" href="<?= e($page['closing']['cta']['href']) ?>"><?= e($page['closing']['cta']['label']) ?><span aria-hidden="true"><?= e($page['closing']['cta']['icon']) ?></span></a></div></section></main><footer class="site-footer"><a class="brand" href="#top"><span class="brand__mark" aria-hidden="true"><i></i><i></i><i></i></span><span><?= e($page['brand']['name']) ?><span class="brand__light"><?= e($page['brand']['light']) ?></span></span></a><p><?= e($page['footer']['text']) ?></p><a href="<?= e($page['footer']['link']['href']) ?>"><?= e($page['footer']['link']['label']) ?></a></footer></div>
  <script src="<?= e($page['script']) ?>" defer></script>
</body>
</html>
