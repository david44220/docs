<?php
/**
 * Wallets & ledger. Every balance change goes through wallet_move(), which
 * locks the member row, refuses to go negative and writes a ledger line.
 */
declare(strict_types=1);

const WALLETS = [
    'purchase' => 'purchase_balance',
    'cash'     => 'cash_balance',
    'ads'      => 'ad_credits',
];

const TX_TYPES = [
    'deposit'           => 'Deposit',
    'bubble_purchase'   => 'Bubble purchase',
    'bubble_payout'     => 'Bubble expired',
    'referral'          => 'Referral commission',
    'withdrawal'        => 'Withdrawal',
    'withdrawal_refund' => 'Withdrawal refund',
    'ad_credits'        => 'Ad credits bonus',
    'campaign_fund'     => 'Campaign funding',
    'campaign_refund'   => 'Campaign refund',
    'admin_credit'      => 'Admin credit',
    'admin_debit'       => 'Admin debit',
];

const WALLET_LABELS = [
    'purchase' => 'Purchase balance',
    'cash'     => 'Cash balance',
    'ads'      => 'Ad credits',
];

/**
 * Move $amount (micro-units, or credits for the "ads" wallet) in or out of a
 * wallet. Must run inside tx(). Returns the new balance.
 */
function wallet_move(
    int $userId,
    string $wallet,
    int $amount,
    string $type,
    string $description,
    ?string $refType = null,
    ?int $refId = null
): int {
    if (!db()->inTransaction()) {
        throw new LogicException('wallet_move() must run inside tx().');
    }
    $column = WALLETS[$wallet] ?? throw new InvalidArgumentException('Unknown wallet: ' . $wallet);

    $current = row("SELECT `$column` AS balance FROM users WHERE id = ? FOR UPDATE", [$userId]);
    if ($current === null) {
        throw new AppError('Member not found.');
    }
    $balance = (int) $current['balance'] + $amount;
    if ($balance < 0) {
        throw new AppError(match ($wallet) {
            'purchase' => 'Insufficient purchase balance.',
            'cash'     => 'Insufficient cash balance.',
            default    => 'Not enough ad credits.',
        });
    }

    q("UPDATE users SET `$column` = ? WHERE id = ?", [$balance, $userId]);
    insert('transactions', [
        'user_id'       => $userId,
        'wallet'        => $wallet,
        'type'          => $type,
        'amount'        => $amount,
        'balance_after' => $balance,
        'description'   => mb_substr($description, 0, 255),
        'ref_type'      => $refType,
        'ref_id'        => $refId,
        'created_at'    => now(),
    ]);
    return $balance;
}

/** Ledger amount for display, money or credits depending on the wallet. */
function ledger_amount(array $tx): string
{
    $amount = (int) $tx['amount'];
    if ($tx['wallet'] === 'ads') {
        return ($amount > 0 ? '+' : ($amount < 0 ? '−' : '')) . number_format(abs($amount)) . ' cr';
    }
    return money_signed($amount);
}

function ledger_balance(array $tx): string
{
    return $tx['wallet'] === 'ads' ? number_format((int) $tx['balance_after']) . ' cr' : money($tx['balance_after']);
}

function tx_label(string $type): string
{
    return TX_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
