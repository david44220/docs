<?php
/**
 * @var array $filters
 * @var string $filter
 * @var string $sort
 * @var string $search
 * @var array $users
 * @var array $pager
 */
?>
<div class="toolbar">
    <?= filter_tabs($filters, $filter, 'filter') ?>
    <form class="search" method="get">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <select class="select select--sm" name="sort" aria-label="Sort" data-autosubmit>
            <?php foreach (['newest' => 'Newest', 'balance' => 'Highest balance', 'bubbles' => 'Most bubbles', 'earned' => 'Top earners'] as $key => $label): ?>
                <option value="<?= $key ?>"<?= $key === $sort ? ' selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <?= icon('search') ?><input class="input input--sm" type="search" name="q" value="<?= e($search) ?>" placeholder="Username, email or IP">
    </form>
    <?= csv_link('admin/users.php', ['filter' => $filter, 'q' => $search !== '' ? $search : null]) ?>
</div>

<section class="card card--flush">
    <?php if ($users === []): ?>
        <?= empty_state('users', 'No members found', 'Try another search or filter.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Member</th><th class="num">Purchase</th><th class="num">Cash</th><th class="num">Ad credits</th><th class="num">Bubbles</th><th class="num">Earned</th><th>Referrer</th><th>Joined</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="is-link">
                        <td>
                            <a class="cell-user" href="<?= e(url('admin/user.php', ['id' => $u['id']])) ?>"><?= user_avatar($u['username'], 'sm') ?>
                                <span><strong><?= e($u['username']) ?></strong><small class="muted"><?= e($u['email']) ?></small></span>
                            </a>
                        </td>
                        <td class="num"><?= e(money($u['purchase_balance'])) ?></td>
                        <td class="num"><?= e(money($u['cash_balance'])) ?></td>
                        <td class="num"><?= number_format((int) $u['ad_credits']) ?></td>
                        <td class="num"><?= number_format((int) $u['active_bubbles']) ?><small class="muted"> / <?= number_format((int) $u['bubbles_bought']) ?></small></td>
                        <td class="num"><?= e(money($u['total_earned'])) ?></td>
                        <td><?= $u['referrer_name'] ? e($u['referrer_name']) : '<span class="muted">—</span>' ?></td>
                        <td class="nowrap muted"><?= e(fmt_date($u['created_at'], 'M j, Y')) ?></td>
                        <td><?= $u['role'] === 'admin' ? status_badge('admin', 'Admin') . ' ' : '' ?><?= status_badge($u['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
