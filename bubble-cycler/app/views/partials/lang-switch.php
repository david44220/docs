<?php
/**
 * Language switch: the same page in the other language (a plain link, so it
 * works without JavaScript). The choice is remembered in a cookie and on the
 * member's account.
 *
 * @var string $variant  'pill' (one link, like the landing's button) or 'segmented' (EN | FR)
 */
$variant ??= 'pill';
$link = static function (string $code): string {
    $query = $_GET;
    $query['lang'] = $code;
    return '?' . http_build_query($query);
};
?>
<?php if ($variant === 'segmented'): ?>
<nav class="lang-choice" aria-label="<?= e(t('Language')) ?>">
    <?php foreach (LANGUAGES as $code => $name): ?>
        <a class="lang-choice__item<?= $code === lang() ? ' is-active' : '' ?>" href="<?= e($link($code)) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" title="<?= e($name) ?>"<?= $code === lang() ? ' aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?></a>
    <?php endforeach; ?>
</nav>
<?php else: ?>
<a class="lang-switch" href="<?= e($link(lang_other())) ?>" hreflang="<?= e(lang_other()) ?>" lang="<?= e(lang_other()) ?>" aria-label="<?= e(LANGUAGES[lang_other()]) ?>"><?= e(strtoupper(lang_other())) ?><span aria-hidden="true">↗</span></a>
<?php endif; ?>
