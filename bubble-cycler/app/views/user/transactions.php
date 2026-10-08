<?php
/**
 * @var string $wallet
 * @var array $items
 * @var array $pager
 */
?>
<div class="toolbar">
    <?= filter_tabs(['' => t('All'), 'purchase' => t('Purchase balance'), 'cash' => t('Cash balance'), 'ads' => t('Ad credits')], $wallet, 'wallet') ?>
</div>

<section class="card card--flush">
    <?php if ($items === []): ?>
        <?= empty_state('list', t('Nothing here yet'), t('Deposits, bubbles, payouts and withdrawals will appear in this ledger.')) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('Date')) ?></th><th><?= e(t('Type')) ?></th><th><?= e(t('Details')) ?></th><th><?= e(t('Wallet')) ?></th><th class="num"><?= e(t('Amount')) ?></th><th class="num"><?= e(t('Balance after')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($items as $tx): $positive = (int) $tx['amount'] > 0; ?>
                    <tr>
                        <td class="nowrap muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                        <td class="nowrap"><strong><?= e(tx_label($tx['type'])) ?></strong></td>
                        <td><?= e(stored_text($tx['description'])) ?></td>
                        <td class="nowrap"><span class="wallet-tag wallet-tag--<?= e($tx['wallet']) ?>"><?= e(wallet_label($tx['wallet'])) ?></span></td>
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
