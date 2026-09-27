<?php
/**
 * @var array $user
 * @var array $campaigns
 * @var array $totals
 * @var array $form
 * @var ?string $error
 */
$isEdit = (int) $form['id'] > 0;
$minCredits = max(1, setting_int('min_campaign_credits'));
$approval = setting_bool('campaign_approval');
?>
<section class="hub card card--glow">
    <div class="hub__intro">
        <span class="eyebrow"><?= icon('megaphone') ?> Advertising hub</span>
        <h2>Reach members at the moment they buy.</h2>
        <p class="muted">Every bubble includes <?= number_format(setting_int('ad_credits_per_bubble')) ?> ad credits. One credit pays for one completed <?= (int) setting_int('ad_seconds') ?>-second view in front of the purchase button.</p>
    </div>
    <dl class="hub__stats">
        <div><dt>Available credits</dt><dd class="text-pink"><?= number_format((int) $user['ad_credits']) ?></dd></div>
        <div><dt>Credits in campaigns</dt><dd><?= number_format($totals['live']) ?></dd></div>
        <div><dt>Total views</dt><dd><?= number_format($totals['views']) ?></dd></div>
        <div><dt>Click-through</dt><dd><?= pct($totals['clicks'], $totals['views']) ?></dd></div>
    </dl>
</section>

<div class="grid grid--main grid--top">
    <section class="stack">
        <header class="section-title">
            <h2>Your campaigns</h2>
            <?php if ($isEdit): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('advertise.php')) ?>"><?= icon('plus') ?> New campaign</a><?php endif; ?>
        </header>

        <?php if ($campaigns === []): ?>
            <div class="card"><?= empty_state('megaphone', 'No campaigns yet', 'Create your first campaign with the form — it takes less than a minute.') ?></div>
        <?php endif; ?>

        <?php foreach ($campaigns as $c):
            $used = (int) $c['credits_total'] - (int) $c['credits_remaining'];
            $usedPct = (int) $c['credits_total'] > 0 ? $used / (int) $c['credits_total'] * 100 : 0;
        ?>
            <article class="campaign glass<?= (int) $c['id'] === (int) $form['id'] ? ' is-editing' : '' ?>">
                <div class="campaign__media">
                    <?php if (!empty($c['image_url'])): ?>
                        <img src="<?= e($c['image_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <span><?= e(mb_strtoupper(mb_substr(url_host($c['url']), 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="campaign__body">
                    <div class="campaign__top">
                        <h3><?= e($c['title']) ?></h3>
                        <?= status_badge($c['status'], match ($c['status']) {
                            'pending' => 'In review', 'active' => 'Live', 'completed' => 'Out of credits', default => ucfirst($c['status']),
                        }) ?>
                    </div>
                    <?php if ($c['description'] !== ''): ?><p class="campaign__text"><?= e($c['description']) ?></p><?php endif; ?>
                    <a class="campaign__url" href="<?= e($c['url']) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(url_host($c['url'])) ?></a>

                    <?php if ($c['status'] === 'rejected' && $c['admin_note']): ?>
                        <div class="alert alert--danger alert--compact"><?= icon('alert') ?><div>Rejected: <?= e($c['admin_note']) ?></div></div>
                    <?php endif; ?>

                    <dl class="campaign__stats">
                        <div><dt>Credits left</dt><dd><?= number_format((int) $c['credits_remaining']) ?><small> / <?= number_format((int) $c['credits_total']) ?></small></dd></div>
                        <div><dt>Views</dt><dd><?= number_format((int) $c['views']) ?></dd></div>
                        <div><dt>Clicks</dt><dd><?= number_format((int) $c['clicks']) ?></dd></div>
                        <div><dt>CTR</dt><dd><?= campaign_ctr($c) ?></dd></div>
                    </dl>
                    <div class="progress progress--thin" title="<?= round($usedPct) ?>% of credits used"><span class="progress__bar" style="width: <?= round($usedPct, 2) ?>%"></span></div>

                    <div class="campaign__actions">
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="fund">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <input class="input input--sm" type="number" name="credits" min="1" max="<?= max(1, (int) $user['ad_credits']) ?>" placeholder="Credits" aria-label="Credits to add" required<?= (int) $user['ad_credits'] < 1 ? ' disabled' : '' ?>>
                            <button class="btn btn--secondary btn--sm" type="submit"<?= (int) $user['ad_credits'] < 1 ? ' disabled' : '' ?>><?= icon('plus') ?> Add</button>
                        </form>
                        <?php if (in_array($c['status'], ['active', 'paused'], true)): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn--ghost btn--sm" type="submit"><?= $c['status'] === 'active' ? icon('pause') . ' Pause' : icon('play') . ' Resume' ?></button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('advertise.php', ['edit' => $c['id']])) ?>"><?= icon('edit') ?> Edit</a>
                        <form method="post" data-confirm="Delete this campaign? Unused credits (<?= number_format((int) $c['credits_remaining']) ?>) will be returned to you.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn--ghost btn--sm btn--danger-text" type="submit"><?= icon('trash') ?> Delete</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <aside class="stack sticky">
        <form method="post" class="card form" data-ad-editor>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
            <header class="card__head">
                <div>
                    <h2 class="card__title"><?= $isEdit ? 'Edit campaign' : 'New campaign' ?></h2>
                    <p class="card__sub"><?= $approval ? 'Campaigns are reviewed before they go live.' : 'Campaigns go live immediately.' ?></p>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert alert--danger"><?= icon('alert') ?><div><?= e($error) ?></div></div>
            <?php endif; ?>

            <label class="field">
                <span class="field__label">Headline</span>
                <input class="input" type="text" name="title" maxlength="80" value="<?= e($form['title']) ?>" placeholder="What should members notice?" required data-preview="title">
            </label>
            <label class="field">
                <span class="field__label">Description <small class="muted" data-count-for="description">0/220</small></span>
                <textarea class="textarea" name="description" maxlength="220" rows="3" placeholder="One or two sentences about your offer." data-preview="description" data-count="description"><?= e($form['description']) ?></textarea>
            </label>
            <label class="field">
                <span class="field__label">Destination URL</span>
                <input class="input" type="url" name="url" maxlength="500" value="<?= e($form['url']) ?>" placeholder="https://yoursite.com" required data-preview="url">
            </label>
            <label class="field">
                <span class="field__label">Banner image URL <small class="muted">optional · https · 2:1 works best</small></span>
                <input class="input" type="url" name="image_url" maxlength="500" value="<?= e((string) ($form['image_url'] ?? '')) ?>" placeholder="https://yoursite.com/banner.jpg" data-preview="image">
            </label>
            <div class="field-row">
                <label class="field">
                    <span class="field__label">Button label</span>
                    <input class="input" type="text" name="cta_label" maxlength="30" value="<?= e($form['cta_label'] ?: 'Visit site') ?>" data-preview="cta">
                </label>
                <?php if (!$isEdit): ?>
                    <label class="field">
                        <span class="field__label">Credits <small class="muted">min <?= $minCredits ?></small></span>
                        <input class="input" type="number" name="credits" min="<?= $minCredits ?>" max="<?= max($minCredits, (int) $user['ad_credits']) ?>" value="<?= e((string) $form['credits']) ?>" required>
                    </label>
                <?php endif; ?>
            </div>

            <div class="preview">
                <span class="preview__label">Live preview</span>
                <div class="adcreative adcreative--preview">
                    <span class="adcreative__media adcreative__media--placeholder" data-preview-media>
                        <img src="<?= e((string) ($form['image_url'] ?? '')) ?>" alt=""<?= empty($form['image_url']) ? ' hidden' : '' ?> referrerpolicy="no-referrer" data-preview-img>
                        <span class="adcreative__initial" data-preview-initial><?= e(mb_strtoupper(mb_substr(url_host($form['url'] ?: 'A'), 0, 1))) ?></span>
                    </span>
                    <span class="adcreative__body">
                        <strong class="adcreative__title" data-preview-title><?= e($form['title'] ?: 'Your headline') ?></strong>
                        <span class="adcreative__text" data-preview-description><?= e($form['description'] ?: 'Your description appears here.') ?></span>
                        <span class="adcreative__domain"><?= icon('globe') ?> <span data-preview-domain><?= e($form['url'] !== '' ? url_host($form['url']) : 'yoursite.com') ?></span></span>
                        <span class="btn btn--secondary btn--sm adcreative__cta" data-preview-cta><?= e($form['cta_label'] ?: 'Visit site') ?></span>
                    </span>
                </div>
            </div>

            <?php if (!$isEdit && (int) $user['ad_credits'] < $minCredits): ?>
                <div class="alert alert--info"><?= icon('info') ?><div>You need at least <?= plural($minCredits, 'credit') ?>. <a href="<?= e(url('buy.php')) ?>">Buy a bubble</a> to get <?= number_format(setting_int('ad_credits_per_bubble')) ?>.</div></div>
            <?php endif; ?>

            <div class="form__actions">
                <?php if ($isEdit): ?><a class="btn btn--ghost" href="<?= e(url('advertise.php')) ?>">Cancel</a><?php endif; ?>
                <button class="btn btn--primary" type="submit"<?= !$isEdit && (int) $user['ad_credits'] < $minCredits ? ' disabled' : '' ?>><?= icon($isEdit ? 'check' : 'sparkles') ?> <?= $isEdit ? 'Save changes' : 'Launch campaign' ?></button>
            </div>
            <p class="field__hint">No illegal, adult or misleading content, malware or other cycler programs. Campaigns breaking the rules are removed.</p>
        </form>
    </aside>
</div>
