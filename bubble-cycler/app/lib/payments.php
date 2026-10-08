<?php
/**
 * Manual payments. Admins define deposit and withdrawal methods (crypto
 * wallets, bank transfer, mobile money, …); members submit requests that an
 * admin reviews by hand.
 */
declare(strict_types=1);

function payment_methods(string $type, bool $activeOnly = true): array
{
    return rows(
        'SELECT * FROM payment_methods WHERE type = ?' . ($activeOnly ? " AND status = 'active'" : '')
        . ' ORDER BY sort_order ASC, id ASC',
        [$type]
    );
}

function payment_method(int $id, string $type, bool $activeOnly = true): ?array
{
    return row(
        'SELECT * FROM payment_methods WHERE id = ? AND type = ?' . ($activeOnly ? " AND status = 'active'" : ''),
        [$id, $type]
    );
}

/** Validate & save a method from the admin form. Returns its id. */
function payment_method_save(int $adminId, ?int $id, string $type, array $input): int
{
    if (!in_array($type, ['deposit', 'withdrawal'], true)) {
        throw new AppError(t('Unknown method type.'));
    }
    $name = trim((string) ($input['name'] ?? ''));
    $currency = strtoupper(trim((string) ($input['currency'] ?? ''))) ?: 'USD';
    $logo = trim((string) ($input['logo_url'] ?? ''));
    $color = trim((string) ($input['color'] ?? '#dfaaff'));
    $accountLabel = trim((string) ($input['account_label'] ?? ''));
    $accountValue = trim((string) ($input['account_value'] ?? ''));
    $instructions = trim((string) ($input['instructions'] ?? ''));
    $min = to_payment_units((string) ($input['min_amount'] ?? '0') ?: '0');
    $max = to_payment_units((string) ($input['max_amount'] ?? '0') ?: '0');
    $feeFixed = to_payment_units((string) ($input['fee_fixed'] ?? '0') ?: '0');
    $feePercent = to_bp((string) ($input['fee_percent'] ?? '0'));
    $sort = (int) ($input['sort_order'] ?? 0);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        throw new AppError(t('Method name must be 2 to 80 characters long.'));
    }
    if (!preg_match('/^[A-Z0-9._\- ]{1,20}$/', $currency)) {
        throw new AppError(t('Currency label: up to 20 letters or digits (e.g. USD, USDT, BTC).'));
    }
    if ($logo !== '' && !valid_http_url($logo, true)) {
        throw new AppError(t('Logo must be an https:// image URL, or leave it empty.'));
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#dfaaff';
    }
    if ($min === null || $max === null || $feeFixed === null || $feePercent === null) {
        throw new AppError(t('Limits and fees must be valid amounts, e.g. 10 or 2.50.'));
    }
    if ($max > 0 && $max < $min) {
        throw new AppError(t('Maximum amount must be greater than the minimum.'));
    }
    if ($type === 'deposit' && $accountValue === '') {
        throw new AppError(t('Enter the account, wallet address or payment details members must send money to.'));
    }
    if ($type === 'withdrawal' && $accountLabel === '') {
        $accountLabel = 'Your account / wallet address';
    }
    if (mb_strlen($accountLabel) > 80 || mb_strlen($accountValue) > 500 || mb_strlen($instructions) > 5000) {
        throw new AppError(t('One of the fields is too long.'));
    }

    $data = [
        'type'           => $type,
        'name'           => $name,
        'currency'       => $currency,
        'logo_url'       => $logo !== '' ? $logo : null,
        'color'          => strtolower($color),
        'account_label'  => $accountLabel,
        'account_value'  => $accountValue,
        'instructions'   => $instructions,
        'min_amount'     => $min,
        'max_amount'     => $max,
        'fee_fixed'      => $feeFixed,
        'fee_percent_bp' => $feePercent,
        'require_proof'  => !empty($input['require_proof']) ? 1 : 0,
        'status'         => ($input['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
        'sort_order'     => max(-9999, min(9999, $sort)),
        'updated_at'     => now(),
    ];

    if ($id === null) {
        $id = insert('payment_methods', $data + ['created_at' => now()]);
        admin_log($adminId, 'method.create', sprintf('Created %s method #%d “%s”', $type, $id, $name));
    } else {
        if (payment_method($id, $type, false) === null) {
            throw new AppError(t('Payment method not found.'));
        }
        update_row('payment_methods', $id, $data);
        admin_log($adminId, 'method.update', sprintf('Updated %s method #%d “%s”', $type, $id, $name));
    }
    return $id;
}

function payment_method_delete(int $adminId, int $id): void
{
    $method = row('SELECT * FROM payment_methods WHERE id = ?', [$id]);
    if ($method === null) {
        throw new AppError(t('Payment method not found.'));
    }
    q('DELETE FROM payment_methods WHERE id = ?', [$id]);
    admin_log($adminId, 'method.delete', sprintf('Deleted %s method #%d “%s”', $method['type'], $id, $method['name']));
}

function check_amount_limits(array $method, int $amount): void
{
    $min = (int) $method['min_amount'];
    $max = (int) $method['max_amount'];
    if ($amount <= 0) {
        throw new AppError(t('Enter a valid amount.'));
    }
    if ($amount < $min) {
        throw new AppError(t('The minimum for {method} is {amount}.', ['method' => $method['name'], 'amount' => money($min)]));
    }
    if ($max > 0 && $amount > $max) {
        throw new AppError(t('The maximum for {method} is {amount}.', ['method' => $method['name'], 'amount' => money($max)]));
    }
}

/* -------------------------------------------------------------------------
 * Deposits
 * ---------------------------------------------------------------------- */

function deposit_create(int $userId, int $methodId, string $amountText, string $reference, string $sender, ?array $file): int
{
    $method = payment_method($methodId, 'deposit');
    if ($method === null) {
        throw new AppError(t('Please choose an available deposit method.'));
    }
    $amount = to_payment_units($amountText);
    if ($amount === null) {
        throw new AppError(t('Enter the amount you sent, e.g. 25 or 25.50.'));
    }
    check_amount_limits($method, $amount);
    $fee = method_fee($method, $amount);
    if ($fee >= $amount) {
        throw new AppError(t('This amount does not cover the method fee.'));
    }
    if (mb_strlen($reference) < 4 || mb_strlen($reference) > 190) {
        throw new AppError(t('Enter the transaction ID / payment reference (at least 4 characters).'));
    }
    if (mb_strlen($sender) > 190) {
        throw new AppError(t('Sender details are too long.'));
    }

    $proof = store_proof_upload($file);
    if ($proof === null && (int) $method['require_proof'] === 1) {
        throw new AppError(t('Please attach a screenshot of your payment.'));
    }

    try {
        $id = tx(function () use ($userId, $methodId, $method, $amount, $fee, $reference, $sender, $proof): int {
            // One submission at a time per member keeps the pending limit exact.
            row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $pendingLimit = max(1, setting_int('max_pending_deposits'));
            $pending = (int) val("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'pending'", [$userId]);
            if ($pending >= $pendingLimit) {
                throw new AppError(tn('You already have {n} pending deposit waiting for review. Please wait until it is processed.', 'You already have {n} pending deposits waiting for review. Please wait until they are processed.', $pending));
            }
            if (deposit_reference_taken($methodId, $reference)) {
                throw new AppError(t('This transaction reference has already been submitted.'));
            }
            return insert('deposits', [
                'user_id'       => $userId,
                'method_id'     => $methodId,
                'method_name'   => $method['name'],
                'amount'        => $amount,
                'fee'           => $fee,
                'credit_amount' => $amount - $fee,
                'reference'     => $reference,
                'sender'        => $sender,
                'proof_file'    => $proof,
                'status'        => 'pending',
                'created_at'    => now(),
            ]);
        });
    } catch (Throwable $e) {
        if ($proof !== null) {
            @unlink(proof_dir() . '/' . $proof);
        }
        throw $e;
    }

    $username = (string) val('SELECT username FROM users WHERE id = ?', [$userId]);
    notify_admins(static fn (): array => [
        'subject' => t('Deposit #{id} to review', ['id' => $id]),
        'title'   => t('A deposit is waiting for review'),
        'lines'   => [
            t('{user} declared a deposit of {amount} via {method} (reference {reference}).', ['user' => $username, 'amount' => money($amount), 'method' => $method['name'], 'reference' => $reference]),
            t('Check that the payment arrived before approving it.'),
        ],
        'button'  => [t('Review deposit'), 'admin/deposits.php?review=' . $id],
    ]);
    return $id;
}

/** A reference already used by a pending or approved deposit of this method (optionally ignoring one deposit). */
function deposit_reference_taken(int $methodId, string $reference, int $exceptId = 0): bool
{
    return val(
        "SELECT id FROM deposits WHERE method_id = ? AND reference = ? AND status IN ('pending','approved') AND id <> ? LIMIT 1",
        [$methodId, $reference, $exceptId]
    ) !== null;
}

/** Approve a pending deposit; $credit overrides the amount credited (e.g. partial payment received). */
function deposit_approve(int $adminId, int $depositId, ?int $credit, string $note = ''): void
{
    $deposit = tx(function () use ($adminId, $depositId, $credit, $note): array {
        $deposit = row('SELECT * FROM deposits WHERE id = ? FOR UPDATE', [$depositId]);
        if ($deposit === null || $deposit['status'] !== 'pending') {
            throw new AppError(t('This deposit is no longer pending.'));
        }
        $twin = $deposit['method_id'] === null ? null : val(
            "SELECT id FROM deposits WHERE method_id = ? AND reference = ? AND status = 'approved' AND id <> ? LIMIT 1",
            [(int) $deposit['method_id'], $deposit['reference'], $depositId]
        );
        if ($twin !== null) {
            throw new AppError(t('Deposit #{id} with the same reference was already approved. Reject this one.', ['id' => (int) $twin]));
        }
        $credit ??= (int) $deposit['credit_amount'];
        if ($credit <= 0) {
            throw new AppError(t('The credited amount must be greater than zero.'));
        }
        $userId = (int) $deposit['user_id'];
        wallet_move($userId, 'purchase', $credit, 'deposit', sprintf('Deposit #%d via %s approved', $depositId, $deposit['method_name']), 'deposit', $depositId);
        q('UPDATE users SET total_deposited = total_deposited + ? WHERE id = ?', [$credit, $userId]);
        q(
            "UPDATE deposits SET status = 'approved', credit_amount = ?, admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?",
            [$credit, $note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $depositId]
        );
        admin_log($adminId, 'deposit.approve', sprintf('Approved deposit #%d, credited %s', $depositId, stored_money($credit)));
        return ['user_id' => $userId, 'credit' => $credit, 'method' => $deposit['method_name'], 'note' => $note];
    });
    notify_member($deposit['user_id'], static fn (): array => [
        'subject' => t('Deposit #{id} approved', ['id' => $depositId]),
        'title'   => t('Your deposit was approved'),
        'lines'   => array_values(array_filter([
            t('{amount} from your deposit via {method} was added to your purchase balance.', ['amount' => money($deposit['credit']), 'method' => $deposit['method']]),
            $deposit['note'] !== '' ? t('Note from the team: {note}', ['note' => $deposit['note']]) : '',
        ])),
        'button'  => [t('Buy bubbles'), 'buy.php'],
    ]);
}

function deposit_reject(int $adminId, int $depositId, string $note): void
{
    $deposit = tx(function () use ($adminId, $depositId, $note): array {
        $deposit = row('SELECT * FROM deposits WHERE id = ? FOR UPDATE', [$depositId]);
        if ($deposit === null || $deposit['status'] !== 'pending') {
            throw new AppError(t('This deposit is no longer pending.'));
        }
        q(
            "UPDATE deposits SET status = 'rejected', admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?",
            [$note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $depositId]
        );
        admin_log($adminId, 'deposit.reject', sprintf('Rejected deposit #%d%s', $depositId, $note !== '' ? ' — ' . $note : ''));
        return $deposit;
    });
    notify_member((int) $deposit['user_id'], static fn (): array => [
        'subject' => t('Deposit #{id} was not approved', ['id' => $depositId]),
        'title'   => t('Your deposit was not approved'),
        'lines'   => array_values(array_filter([
            t('We could not confirm your deposit of {amount} via {method} (reference {reference}).', ['amount' => money($deposit['amount']), 'method' => $deposit['method_name'], 'reference' => $deposit['reference']]),
            $note !== '' ? t('Reason: {note}', ['note' => $note]) : '',
            t('If you think this is a mistake, reply to support with your payment details.'),
        ])),
        'button'  => [t('View my deposits'), 'deposit.php'],
    ]);
}

/** Admin adds money for a member directly (cash received outside the site, promotions…). */
function deposit_manual(int $adminId, int $userId, int $amount, string $note): int
{
    if ($amount <= 0) {
        throw new AppError(t('Enter a positive amount.'));
    }
    return tx(function () use ($adminId, $userId, $amount, $note): int {
        if (row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]) === null) {
            throw new AppError(t('Member not found.'));
        }
        $id = insert('deposits', [
            'user_id'       => $userId,
            'method_id'     => null,
            'method_name'   => 'Manual (admin)',
            'amount'        => $amount,
            'fee'           => 0,
            'credit_amount' => $amount,
            'reference'     => mb_substr($note !== '' ? $note : 'Added by admin', 0, 190),
            'status'        => 'approved',
            'admin_note'    => $note !== '' ? mb_substr($note, 0, 255) : null,
            'processed_by'  => $adminId,
            'processed_at'  => now(),
            'created_at'    => now(),
        ]);
        wallet_move($userId, 'purchase', $amount, 'deposit', sprintf('Manual deposit #%d added by admin', $id), 'deposit', $id);
        q('UPDATE users SET total_deposited = total_deposited + ? WHERE id = ?', [$amount, $userId]);
        admin_log($adminId, 'deposit.manual', sprintf('Manual deposit #%d of %s for member #%d. %s', $id, stored_money($amount), $userId, $note));
        return $id;
    });
}

/* -------------------------------------------------------------------------
 * Withdrawals
 * ---------------------------------------------------------------------- */

function withdrawal_create(int $userId, int $methodId, string $amountText, string $account): int
{
    $method = payment_method($methodId, 'withdrawal');
    if ($method === null) {
        throw new AppError(t('Please choose an available withdrawal method.'));
    }
    $amount = to_payment_units($amountText);
    if ($amount === null) {
        throw new AppError(t('Enter a valid amount, e.g. 5 or 5.50.'));
    }
    $globalMin = setting_int('min_withdrawal');
    if ($amount < $globalMin) {
        throw new AppError(t('The minimum withdrawal is {amount}.', ['amount' => money($globalMin)]));
    }
    check_amount_limits($method, $amount);
    $fee = method_fee($method, $amount);
    if ($fee >= $amount) {
        throw new AppError(t('This amount does not cover the withdrawal fee.'));
    }
    if (mb_strlen($account) < 4 || mb_strlen($account) > 500) {
        throw new AppError($method['account_label'] !== '' ? t('Enter your {field}.', ['field' => mb_strtolower($method['account_label'])]) : t('Enter your account details.'));
    }

    $id = tx(function () use ($userId, $method, $amount, $fee, $account): int {
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        $limit = max(1, setting_int('max_pending_withdrawals'));
        $pending = (int) val("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'pending'", [$userId]);
        if ($pending >= $limit) {
            throw new AppError(t('You already have withdrawals waiting to be processed.'));
        }
        $id = insert('withdrawals', [
            'user_id'       => $userId,
            'method_id'     => (int) $method['id'],
            'method_name'   => $method['name'],
            'amount'        => $amount,
            'fee'           => $fee,
            'payout_amount' => $amount - $fee,
            'account'       => $account,
            'status'        => 'pending',
            'created_at'    => now(),
        ]);
        wallet_move($userId, 'cash', -$amount, 'withdrawal', sprintf('Withdrawal #%d via %s requested', $id, $method['name']), 'withdrawal', $id);
        return $id;
    });
    $username = (string) val('SELECT username FROM users WHERE id = ?', [$userId]);
    notify_admins(static fn (): array => [
        'subject' => t('Withdrawal #{id} to pay', ['id' => $id]),
        'title'   => t('A withdrawal is waiting'),
        'lines'   => [t('{user} asked for {amount} via {method} ({net} after fees).', ['user' => $username, 'amount' => money($amount), 'method' => $method['name'], 'net' => money($amount - $fee)])],
        'button'  => [t('Review withdrawal'), 'admin/withdrawals.php?review=' . $id],
    ]);
    return $id;
}

function withdrawal_mark_paid(int $adminId, int $withdrawalId, string $txid, string $note = ''): void
{
    $withdrawal = tx(function () use ($adminId, $withdrawalId, $txid, $note): array {
        $withdrawal = row('SELECT user_id FROM withdrawals WHERE id = ?', [$withdrawalId]);
        if ($withdrawal === null) {
            throw new AppError(t('Withdrawal not found.'));
        }
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [(int) $withdrawal['user_id']]); // member row first
        $withdrawal = row_required('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE', [$withdrawalId]);
        if ($withdrawal['status'] !== 'pending') {
            throw new AppError(t('This withdrawal is no longer pending.'));
        }
        q(
            "UPDATE withdrawals SET status = 'paid', txid = ?, admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?",
            [$txid !== '' ? mb_substr($txid, 0, 190) : null, $note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $withdrawalId]
        );
        q('UPDATE users SET total_withdrawn = total_withdrawn + ? WHERE id = ?', [(int) $withdrawal['amount'], (int) $withdrawal['user_id']]);
        admin_log($adminId, 'withdrawal.paid', sprintf('Paid withdrawal #%d (%s)', $withdrawalId, stored_money($withdrawal['payout_amount'])));
        return $withdrawal;
    });
    notify_member((int) $withdrawal['user_id'], static fn (): array => [
        'subject' => t('Withdrawal #{id} sent', ['id' => $withdrawalId]),
        'title'   => t('Your withdrawal was sent'),
        'lines'   => array_values(array_filter([
            t('We sent {amount} via {method} to {account}.', ['amount' => money($withdrawal['payout_amount']), 'method' => $withdrawal['method_name'], 'account' => str_limit($withdrawal['account'], 80)]),
            $txid !== '' ? t('Transaction ID: {txid}', ['txid' => $txid]) : '',
            $note !== '' ? t('Note from the team: {note}', ['note' => $note]) : '',
        ])),
        'button'  => [t('View my withdrawals'), 'withdraw.php'],
    ]);
}

/** Reject (admin) or cancel (member) a pending withdrawal and refund it. */
function withdrawal_refund(int $withdrawalId, string $status, ?int $adminId, string $note = '', ?int $ownerId = null): void
{
    $withdrawal = tx(function () use ($withdrawalId, $status, $adminId, $note, $ownerId): array {
        $withdrawal = row('SELECT * FROM withdrawals WHERE id = ?', [$withdrawalId]);
        if ($withdrawal === null || ($ownerId !== null && (int) $withdrawal['user_id'] !== $ownerId)) {
            throw new AppError(t('Withdrawal not found.'));
        }
        $userId = (int) $withdrawal['user_id'];
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        $withdrawal = row_required('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE', [$withdrawalId]);
        if ($withdrawal['status'] !== 'pending') {
            throw new AppError(t('This withdrawal is no longer pending.'));
        }
        q(
            'UPDATE withdrawals SET status = ?, admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?',
            [$status, $note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $withdrawalId]
        );
        $reason = $status === 'cancelled' ? 'cancelled' : 'rejected';
        wallet_move($userId, 'cash', (int) $withdrawal['amount'], 'withdrawal_refund', sprintf('Withdrawal #%d %s — refunded', $withdrawalId, $reason), 'withdrawal', $withdrawalId);
        if ($adminId !== null) {
            admin_log($adminId, 'withdrawal.reject', sprintf('Rejected withdrawal #%d%s', $withdrawalId, $note !== '' ? ' — ' . $note : ''));
        }
        return $withdrawal;
    });
    if ($status === 'rejected') {
        notify_member((int) $withdrawal['user_id'], static fn (): array => [
            'subject' => t('Withdrawal #{id} was not sent', ['id' => $withdrawalId]),
            'title'   => t('Your withdrawal was declined'),
            'lines'   => array_values(array_filter([
                t('Your withdrawal of {amount} via {method} was declined and the full amount is back in your cash balance.', ['amount' => money($withdrawal['amount']), 'method' => $withdrawal['method_name']]),
                $note !== '' ? t('Reason: {note}', ['note' => $note]) : '',
            ])),
            'button'  => [t('View my withdrawals'), 'withdraw.php'],
        ]);
    }
}
