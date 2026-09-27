<?php
/**
 * @var string $type
 * @var string $wallet
 * @var string $userFilter
 * @var array $items
 * @var array $pager
 */
?>
<form class="toolbar filters" method="get">
    <label class="field field--inline"><span class="field__label">Type</span>
        <select class="select select--sm" name="type" data-autosubmit>
            <option value="">All types</option>
            <?php foreach (TX_TYPES as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $type ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label class="field field--inline"><span class="field__label">Wallet</span>
        <select class="select select--sm" name="wallet" data-autosubmit>
            <option value="">All wallets</option>
            <?php foreach (WALLET_LABELS as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $wallet ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </label>
    <div class="search"><?= icon('search') ?><input class="input input--sm" type="search" name="user" value="<?= e($userFilter) ?>" placeholder="Exact username"></div>
    <?php if ($type !== '' || $wallet !== '' || $userFilter !== ''): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('admin/transactions.php')) ?>">Reset</a><?php endif; ?>
    <?= csv_link('admin/transactions.php', ['type' => $type !== '' ? $type : null, 'wallet' => $wallet !== '' ? $wallet : null, 'user' => $userFilter !== '' ? $userFilter : null]) ?>
</form>

<section class="card card--flush">
    <?php if ($items === []): ?>
        <?= empty_state('list', 'No transactions', 'Nothing matches these filters.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Date</th><th>Member</th><th>Type</th><th>Details</th><th>Wallet</th><th class="num">Amount</th><th class="num">Balance after</th></tr></thead>
                <tbody>
                <?php foreach ($items as $tx): ?>
                    <tr>
                        <td class="muted"><?= (int) $tx['id'] ?></td>
                        <td class="nowrap muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                        <td><a href="<?= e(url('admin/user.php', ['id' => $tx['user_id']])) ?>"><?= e($tx['username']) ?></a></td>
                        <td class="nowrap"><?= e(tx_label($tx['type'])) ?></td>
                        <td><?= e($tx['description']) ?></td>
                        <td><span class="wallet-tag wallet-tag--<?= e($tx['wallet']) ?>"><?= e(WALLET_LABELS[$tx['wallet']] ?? $tx['wallet']) ?></span></td>
                        <td class="num nowrap <?= (int) $tx['amount'] > 0 ? 'text-green' : '' ?>"><?= e(ledger_amount($tx)) ?></td>
                        <td class="num nowrap muted"><?= e(ledger_balance($tx)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
