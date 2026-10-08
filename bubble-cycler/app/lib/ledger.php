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
        throw new AppError(t('Member not found.'));
    }
    $balance = (int) $current['balance'] + $amount;
    if ($balance < 0) {
        throw new AppError(match ($wallet) {
            'purchase' => t('Insufficient purchase balance.'),
            'cash'     => t('Insufficient cash balance.'),
            default    => t('Not enough ad credits.'),
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

/**
 * Credit many ledger lines in one go (bubble payouts). Same bookkeeping as
 * wallet_move(): member rows are locked (in id order), every line gets its
 * own ledger row with the running balance. Must run inside tx().
 *
 * @param list<array{0:int, 1:int, 2:string, 3:?string, 4:?int}> $lines [member id, amount > 0, description, ref type, ref id]
 */
function wallet_credit_many(string $wallet, string $type, array $lines): void
{
    if ($lines === []) {
        return;
    }
    if (!db()->inTransaction()) {
        throw new LogicException('wallet_credit_many() must run inside tx().');
    }
    $column = WALLETS[$wallet] ?? throw new InvalidArgumentException('Unknown wallet: ' . $wallet);

    $ids = array_values(array_unique(array_column($lines, 0)));
    sort($ids);
    $balances = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(', ', array_fill(0, count($chunk), '?'));
        foreach (rows("SELECT id, `$column` AS balance FROM users WHERE id IN ($marks) ORDER BY id FOR UPDATE", $chunk) as $member) {
            $balances[(int) $member['id']] = (int) $member['balance'];
        }
    }
    $before = $balances;

    $now = now();
    $values = [];
    foreach ($lines as [$userId, $amount, $description, $refType, $refId]) {
        if (!isset($balances[$userId])) {
            throw new AppError(t('Member not found.'));
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('wallet_credit_many() only takes positive amounts.');
        }
        $balances[$userId] += $amount;
        array_push($values, $userId, $wallet, $type, $amount, $balances[$userId], mb_substr($description, 0, 255), $refType, $refId, $now);
    }

    foreach ($balances as $userId => $balance) {
        if ($balance !== $before[$userId]) {
            q("UPDATE users SET `$column` = ? WHERE id = ?", [$balance, $userId]);
        }
    }
    foreach (array_chunk($values, 9 * 400) as $chunk) {
        q('INSERT INTO transactions (user_id, wallet, type, amount, balance_after, description, ref_type, ref_id, created_at) VALUES '
            . implode(', ', array_fill(0, intdiv(count($chunk), 9), '(?, ?, ?, ?, ?, ?, ?, ?, ?)')), $chunk);
    }
}

/** Ledger amount for display, money or credits depending on the wallet. */
function ledger_amount(array $tx): string
{
    $amount = (int) $tx['amount'];
    if ($tx['wallet'] === 'ads') {
        return ($amount > 0 ? '+' : ($amount < 0 ? '−' : '')) . t('{n} cr', ['n' => num(abs($amount))]);
    }
    return money_signed($amount);
}

function ledger_balance(array $tx): string
{
    return $tx['wallet'] === 'ads' ? t('{n} cr', ['n' => num($tx['balance_after'])]) : money($tx['balance_after']);
}

function tx_label(string $type): string
{
    return isset(TX_TYPES[$type]) ? t(TX_TYPES[$type]) : ucfirst(str_replace('_', ' ', $type));
}

function wallet_label(string $wallet): string
{
    return isset(WALLET_LABELS[$wallet]) ? t(WALLET_LABELS[$wallet]) : $wallet;
}
