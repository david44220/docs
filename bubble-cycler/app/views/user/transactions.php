<?php
/**
 * @var string $wallet
 * @var array $items
 * @var array $pager
 */
?>
<div class="toolbar">
    <?= filter_tabs(['' => 'All', 'purchase' => 'Purchase balance', 'cash' => 'Cash balance', 'ads' => 'Ad credits'], $wallet, 'wallet') ?>
</div>

<section class="card card--flush">
    <?php if ($items === []): ?>
        <?= empty_state('list', 'Nothing here yet', 'Deposits, bubbles, payouts and withdrawals will appear in this ledger.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Type</th><th>Details</th><th>Wallet</th><th class="num">Amount</th><th class="num">Balance after</th></tr></thead>
                <tbody>
                <?php foreach ($items as $tx): $positive = (int) $tx['amount'] > 0; ?>
                    <tr>
                        <td class="nowrap muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                        <td class="nowrap"><strong><?= e(tx_label($tx['type'])) ?></strong></td>
                        <td><?= e($tx['description']) ?></td>
                        <td class="nowrap"><span class="wallet-tag wallet-tag--<?= e($tx['wallet']) ?>"><?= e(WALLET_LABELS[$tx['wallet']] ?? $tx['wallet']) ?></span></td>
                        <td class="num nowrap <?= $positive ? 'text-green' : '' ?>"><?= e(ledger_amount($tx)) ?></td>
                        <td class="num nowrap muted"><?= e(ledger_balance($tx)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
