<?php
/**
 * @var array $tabs
 * @var string $status
 * @var array $campaigns
 * @var array $pager
 * @var ?array $review
 * @var array $counts
 * @var ?array $houseForm
 * @var ?string $houseError
 */
$creative = static function (array $c): string {
    $media = !empty($c['image_url'])
        ? '<span class="adcreative__media"><img src="' . e($c['image_url']) . '" alt="" referrerpolicy="no-referrer"></span>'
        : '<span class="adcreative__media adcreative__media--placeholder"><span class="adcreative__initial">' . e(mb_strtoupper(mb_substr(url_host($c['url']), 0, 1))) . '</span></span>';
    return '<div class="adcreative adcreative--preview">' . $media . '<span class="adcreative__body">'
        . '<strong class="adcreative__title">' . e($c['title']) . '</strong>'
        . ($c['description'] !== '' ? '<span class="adcreative__text">' . e($c['description']) . '</span>' : '')
        . '<a class="adcreative__domain" href="' . e($c['url']) . '" target="_blank" rel="noopener noreferrer">' . icon('external') . ' ' . e($c['url']) . '</a>'
        . '<span class="btn btn--secondary btn--sm adcreative__cta">' . e($c['cta_label'] ?: t('Visit site')) . '</span>'
        . '</span></div>';
};
$actionForm = static function (int $id, string $action, string $label, string $class, string $iconName, string $confirm = ''): string {
    return '<form method="post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn ' . $class . ' btn--sm" type="submit" aria-label="' . e($label !== '' ? $label : t('Delete')) . '">'
        . icon($iconName) . ($label !== '' ? ' ' . e($label) : '') . '</button></form>';
};
?>
<div class="toolbar">
    <?= filter_tabs($tabs, $status, 'status', $counts) ?>
</div>

<?php if ($review !== null): ?>
    <section class="card card--glow review">
        <header class="card__head">
            <div>
                <span class="eyebrow"><?= e((int) $review['is_house'] === 1 ? t('House ad #{id}', ['id' => num($review['id'])]) : t('Campaign #{id}', ['id' => num($review['id'])])) ?></span>
                <h2 class="card__title"><?= e($review['title']) ?></h2>
                <p class="card__sub"><?= $review['username'] ? t_html('By {user}', ['user' => '<a href="' . e(url('admin/user.php', ['id' => $review['user_id']])) . '">' . e($review['username']) . '</a>']) . ' · ' : '' ?><?= e(t('created {ago}', ['ago' => time_ago($review['created_at'])])) ?></p>
            </div>
            <div class="row">
                <?= status_badge($review['status'], null, 'campaign') ?>
                <a class="icon-btn" href="<?= e(url('admin/ads.php', ['status' => $status])) ?>" aria-label="<?= e(t('Close')) ?>"><?= icon('x') ?></a>
            </div>
        </header>
        <div class="review__grid">
            <?= $creative($review) ?>
            <dl class="dl dl--rows">
                <div><dt><?= e(t('Credits')) ?></dt><dd><?= e((int) $review['is_house'] === 1 ? t('Unlimited') : t('{left} of {total} left', ['left' => num($review['credits_remaining']), 'total' => num($review['credits_total'])])) ?></dd></div>
                <div><dt><?= e(t('Views · clicks')) ?></dt><dd><?= e(num($review['views'])) ?> · <?= e(num($review['clicks'])) ?> (<?= e(campaign_ctr($review)) ?>)</dd></div>
                <div><dt><?= e(t('Destination')) ?></dt><dd class="break"><?= e($review['url']) ?></dd></div>
                <?php if ($review['admin_note']): ?><div><dt><?= e(t('Note')) ?></dt><dd><?= e($review['admin_note']) ?></dd></div><?php endif; ?>
            </dl>
        </div>
        <?php if ((int) $review['is_house'] === 0): ?>
            <div class="review__actions">
                <?php if (in_array($review['status'], ['pending', 'rejected', 'paused'], true)): ?>
                    <?= $actionForm((int) $review['id'], 'approve', t('Approve'), 'btn--success', 'check') ?>
                <?php endif; ?>
                <?php if ($review['status'] !== 'rejected'): ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                        <input class="input input--sm" name="note" maxlength="255" placeholder="<?= e(t('Reason (shown to the advertiser)')) ?>" required>
                        <button class="btn btn--danger btn--sm" type="submit"><?= icon('x') ?> <?= e(t('Reject')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($status === 'house' && $houseForm !== null): ?>
    <form method="post" class="card form house-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="house">
        <input type="hidden" name="id" value="<?= (int) $houseForm['id'] ?>">
        <header class="card__head">
            <div>
                <h2 class="card__title"><?= e((int) $houseForm['id'] > 0 ? t('Edit house ad') : t('New house ad')) ?></h2>
                <p class="card__sub"><?= e(t('House ads are free and unlimited. They play only when no member campaign with credits is available.')) ?></p>
            </div>
            <?php if ((int) $houseForm['id'] > 0): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('admin/ads.php', ['status' => 'house'])) ?>"><?= icon('plus') ?> <?= e(t('New')) ?></a><?php endif; ?>
        </header>
        <?php if ($houseError): ?><div class="alert alert--danger"><?= icon('alert') ?><div><?= e($houseError) ?></div></div><?php endif; ?>
        <div class="field-row">
            <label class="field"><span class="field__label"><?= e(t('Headline')) ?></span><input class="input" name="title" maxlength="80" value="<?= e($houseForm['title']) ?>" required></label>
            <label class="field"><span class="field__label"><?= e(t('Destination URL')) ?></span><input class="input" type="url" name="url" maxlength="500" value="<?= e($houseForm['url']) ?>" required></label>
        </div>
        <label class="field"><span class="field__label"><?= e(t('Description')) ?></span><textarea class="textarea" name="description" maxlength="220" rows="2"><?= e($houseForm['description']) ?></textarea></label>
        <div class="field-row field-row--3">
            <label class="field"><span class="field__label"><?= e(t('Banner image URL')) ?> <small class="muted"><?= e(t('https, optional')) ?></small></span><input class="input" type="url" name="image_url" maxlength="500" value="<?= e((string) $houseForm['image_url']) ?>"></label>
            <label class="field"><span class="field__label"><?= e(t('Button label')) ?></span><input class="input" name="cta_label" maxlength="30" value="<?= e($houseForm['cta_label']) ?>"></label>
            <label class="field"><span class="field__label"><?= e(t('Status')) ?></span>
                <select class="select" name="status">
                    <option value="active"<?= $houseForm['status'] === 'active' ? ' selected' : '' ?>><?= e(t('Active')) ?></option>
                    <option value="paused"<?= $houseForm['status'] === 'paused' ? ' selected' : '' ?>><?= e(t('Paused')) ?></option>
                </select>
            </label>
        </div>
        <div class="form__actions"><button class="btn btn--primary" type="submit"><?= icon('check') ?> <?= e(t('Save house ad')) ?></button></div>
    </form>
<?php endif; ?>

<section class="card card--flush">
    <?php if ($campaigns === []): ?>
        <?= empty_state('megaphone', $status === 'pending' ? t('Nothing to review') : t('No campaigns'), $status === 'pending' ? t('New member campaigns appear here for approval.') : t('Nothing matches this filter.')) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('Campaign')) ?></th><th><?= e(t('Advertiser')) ?></th><th class="num"><?= e(t('Credits left')) ?></th><th class="num"><?= e(t('Views')) ?></th><th class="num"><?= e(t('Clicks')) ?></th><th class="num"><?= e(t('CTR')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($campaigns as $c): $house = (int) $c['is_house'] === 1; ?>
                    <tr<?= $review !== null && (int) $review['id'] === (int) $c['id'] ? ' class="is-selected"' : '' ?>>
                        <td>
                            <a class="cell-ad" href="<?= e(url('admin/ads.php', ['status' => $status, 'review' => $c['id']])) ?>">
                                <span class="cell-ad__thumb"><?= !empty($c['image_url']) ? '<img src="' . e($c['image_url']) . '" alt="" loading="lazy" referrerpolicy="no-referrer">' : e(mb_strtoupper(mb_substr(url_host($c['url']), 0, 1))) ?></span>
                                <span><strong><?= e(str_limit($c['title'], 40)) ?></strong><small class="muted"><?= e(url_host($c['url'])) ?></small></span>
                            </a>
                        </td>
                        <td><?= $house ? '<span class="muted">' . e(t('House')) . '</span>' : '<a href="' . e(url('admin/user.php', ['id' => $c['user_id']])) . '">' . e((string) $c['username']) . '</a>' ?></td>
                        <td class="num"><?= $house ? '∞' : e(num($c['credits_remaining'])) ?></td>
                        <td class="num"><?= e(num($c['views'])) ?></td>
                        <td class="num"><?= e(num($c['clicks'])) ?></td>
                        <td class="num"><?= e(campaign_ctr($c)) ?></td>
                        <td><?= status_badge($c['status'], $c['status'] === 'pending' ? t('In review') : null, 'campaign') ?></td>
                        <td class="num">
                            <div class="row row--end">
                                <?php if ($house): ?>
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/ads.php', ['status' => 'house', 'edit' => $c['id']])) ?>"><?= icon('edit') ?> <?= e(t('Edit')) ?></a>
                                <?php elseif ($c['status'] === 'pending'): ?>
                                    <a class="btn btn--primary btn--sm" href="<?= e(url('admin/ads.php', ['status' => $status, 'review' => $c['id']])) ?>"><?= e(t('Review')) ?></a>
                                <?php elseif ($c['status'] === 'active'): ?>
                                    <?= $actionForm((int) $c['id'], 'pause', t('Pause'), 'btn--ghost', 'pause') ?>
                                <?php elseif ($c['status'] === 'paused'): ?>
                                    <?= $actionForm((int) $c['id'], 'resume', t('Resume'), 'btn--ghost', 'play') ?>
                                <?php endif; ?>
                                <?= $actionForm((int) $c['id'], 'delete', '', 'btn--ghost btn--danger-text', 'trash', $house ? t('Delete this house ad?') : t('Delete this campaign? Unused credits go back to the advertiser.')) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
