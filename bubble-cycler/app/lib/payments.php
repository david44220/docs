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
        throw new AppError('Unknown method type.');
    }
    $name = trim((string) ($input['name'] ?? ''));
    $currency = strtoupper(trim((string) ($input['currency'] ?? ''))) ?: 'USD';
    $logo = trim((string) ($input['logo_url'] ?? ''));
    $color = trim((string) ($input['color'] ?? '#8b5cf6'));
    $accountLabel = trim((string) ($input['account_label'] ?? ''));
    $accountValue = trim((string) ($input['account_value'] ?? ''));
    $instructions = trim((string) ($input['instructions'] ?? ''));
    $min = to_payment_units((string) ($input['min_amount'] ?? '0') ?: '0');
    $max = to_payment_units((string) ($input['max_amount'] ?? '0') ?: '0');
    $feeFixed = to_payment_units((string) ($input['fee_fixed'] ?? '0') ?: '0');
    $feePercent = to_bp((string) ($input['fee_percent'] ?? '0'));
    $sort = (int) ($input['sort_order'] ?? 0);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        throw new AppError('Method name must be 2 to 80 characters long.');
    }
    if (!preg_match('/^[A-Z0-9._\- ]{1,20}$/', $currency)) {
        throw new AppError('Currency label: up to 20 letters or digits (e.g. USD, USDT, BTC).');
    }
    if ($logo !== '' && !valid_http_url($logo, true)) {
        throw new AppError('Logo must be an https:// image URL, or leave it empty.');
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#8b5cf6';
    }
    if ($min === null || $max === null || $feeFixed === null || $feePercent === null) {
        throw new AppError('Limits and fees must be valid amounts, e.g. 10 or 2.50.');
    }
    if ($max > 0 && $max < $min) {
        throw new AppError('Maximum amount must be greater than the minimum.');
    }
    if ($type === 'deposit' && $accountValue === '') {
        throw new AppError('Enter the account, wallet address or payment details members must send money to.');
    }
    if ($type === 'withdrawal' && $accountLabel === '') {
        $accountLabel = 'Your account / wallet address';
    }
    if (mb_strlen($accountLabel) > 80 || mb_strlen($accountValue) > 500 || mb_strlen($instructions) > 5000) {
        throw new AppError('One of the fields is too long.');
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
            throw new AppError('Payment method not found.');
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
        throw new AppError('Payment method not found.');
    }
    q('DELETE FROM payment_methods WHERE id = ?', [$id]);
    admin_log($adminId, 'method.delete', sprintf('Deleted %s method #%d “%s”', $method['type'], $id, $method['name']));
}

function check_amount_limits(array $method, int $amount): void
{
    $min = (int) $method['min_amount'];
    $max = (int) $method['max_amount'];
    if ($amount <= 0) {
        throw new AppError('Enter a valid amount.');
    }
    if ($amount < $min) {
        throw new AppError(sprintf('The minimum for %s is %s.', $method['name'], money($min)));
    }
    if ($max > 0 && $amount > $max) {
        throw new AppError(sprintf('The maximum for %s is %s.', $method['name'], money($max)));
    }
}

/* -------------------------------------------------------------------------
 * Deposits
 * ---------------------------------------------------------------------- */

function deposit_create(int $userId, int $methodId, string $amountText, string $reference, string $sender, ?array $file): int
{
    $method = payment_method($methodId, 'deposit');
    if ($method === null) {
        throw new AppError('Please choose an available deposit method.');
    }
    $amount = to_payment_units($amountText);
    if ($amount === null) {
        throw new AppError('Enter the amount you sent, e.g. 25 or 25.50.');
    }
    check_amount_limits($method, $amount);
    $fee = method_fee($method, $amount);
    if ($fee >= $amount) {
        throw new AppError('This amount does not cover the method fee.');
    }
    if (mb_strlen($reference) < 4 || mb_strlen($reference) > 190) {
        throw new AppError('Enter the transaction ID / payment reference (at least 4 characters).');
    }
    if (mb_strlen($sender) > 190) {
        throw new AppError('Sender details are too long.');
    }

    $pendingLimit = max(1, setting_int('max_pending_deposits'));
    $pending = (int) val("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'pending'", [$userId]);
    if ($pending >= $pendingLimit) {
        throw new AppError(sprintf('You already have %s waiting for review. Please wait until they are processed.', plural($pending, 'pending deposit')));
    }
    $duplicate = val(
        "SELECT id FROM deposits WHERE method_id = ? AND reference = ? AND status IN ('pending','approved') LIMIT 1",
        [$methodId, $reference]
    );
    if ($duplicate !== null) {
        throw new AppError('This transaction reference has already been submitted.');
    }

    $proof = store_proof_upload($file);
    if ($proof === null && (int) $method['require_proof'] === 1) {
        throw new AppError('Please attach a screenshot of your payment.');
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
}

/** Approve a pending deposit; $credit overrides the amount credited (e.g. partial payment received). */
function deposit_approve(int $adminId, int $depositId, ?int $credit, string $note = ''): void
{
    tx(function () use ($adminId, $depositId, $credit, $note): void {
        $deposit = row('SELECT * FROM deposits WHERE id = ? FOR UPDATE', [$depositId]);
        if ($deposit === null || $deposit['status'] !== 'pending') {
            throw new AppError('This deposit is no longer pending.');
        }
        $credit ??= (int) $deposit['credit_amount'];
        if ($credit <= 0) {
            throw new AppError('The credited amount must be greater than zero.');
        }
        $userId = (int) $deposit['user_id'];
        wallet_move($userId, 'purchase', $credit, 'deposit', sprintf('Deposit #%d via %s approved', $depositId, $deposit['method_name']), 'deposit', $depositId);
        q('UPDATE users SET total_deposited = total_deposited + ? WHERE id = ?', [$credit, $userId]);
        q(
            "UPDATE deposits SET status = 'approved', credit_amount = ?, admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?",
            [$credit, $note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $depositId]
        );
        admin_log($adminId, 'deposit.approve', sprintf('Approved deposit #%d, credited %s', $depositId, money($credit)));
    });
}

function deposit_reject(int $adminId, int $depositId, string $note): void
{
    tx(function () use ($adminId, $depositId, $note): void {
        $deposit = row('SELECT * FROM deposits WHERE id = ? FOR UPDATE', [$depositId]);
        if ($deposit === null || $deposit['status'] !== 'pending') {
            throw new AppError('This deposit is no longer pending.');
        }
        q(
            "UPDATE deposits SET status = 'rejected', admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?",
            [$note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $depositId]
        );
        admin_log($adminId, 'deposit.reject', sprintf('Rejected deposit #%d%s', $depositId, $note !== '' ? ' — ' . $note : ''));
    });
}

/** Admin adds money for a member directly (cash received outside the site, promotions…). */
function deposit_manual(int $adminId, int $userId, int $amount, string $note): int
{
    if ($amount <= 0) {
        throw new AppError('Enter a positive amount.');
    }
    return tx(function () use ($adminId, $userId, $amount, $note): int {
        if (row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]) === null) {
            throw new AppError('Member not found.');
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
        admin_log($adminId, 'deposit.manual', sprintf('Manual deposit #%d of %s for member #%d. %s', $id, money($amount), $userId, $note));
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
        throw new AppError('Please choose an available withdrawal method.');
    }
    $amount = to_payment_units($amountText);
    if ($amount === null) {
        throw new AppError('Enter a valid amount, e.g. 5 or 5.50.');
    }
    $globalMin = setting_int('min_withdrawal');
    if ($amount < $globalMin) {
        throw new AppError(sprintf('The minimum withdrawal is %s.', money($globalMin)));
    }
    check_amount_limits($method, $amount);
    $fee = method_fee($method, $amount);
    if ($fee >= $amount) {
        throw new AppError('This amount does not cover the withdrawal fee.');
    }
    if (mb_strlen($account) < 4 || mb_strlen($account) > 500) {
        throw new AppError(sprintf('Enter your %s.', mb_strtolower($method['account_label'] ?: 'account details')));
    }

    return tx(function () use ($userId, $method, $amount, $fee, $account): int {
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        $limit = max(1, setting_int('max_pending_withdrawals'));
        $pending = (int) val("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'pending'", [$userId]);
        if ($pending >= $limit) {
            throw new AppError('You already have withdrawals waiting to be processed.');
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
}

function withdrawal_mark_paid(int $adminId, int $withdrawalId, string $txid, string $note = ''): void
{
    tx(function () use ($adminId, $withdrawalId, $txid, $note): void {
        $withdrawal = row('SELECT user_id FROM withdrawals WHERE id = ?', [$withdrawalId]);
        if ($withdrawal === null) {
            throw new AppError('Withdrawal not found.');
        }
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [(int) $withdrawal['user_id']]); // member row first
        $withdrawal = row('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE', [$withdrawalId]);
        if ($withdrawal['status'] !== 'pending') {
            throw new AppError('This withdrawal is no longer pending.');
        }
        q(
            "UPDATE withdrawals SET status = 'paid', txid = ?, admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?",
            [$txid !== '' ? mb_substr($txid, 0, 190) : null, $note !== '' ? mb_substr($note, 0, 255) : null, $adminId, now(), $withdrawalId]
        );
        q('UPDATE users SET total_withdrawn = total_withdrawn + ? WHERE id = ?', [(int) $withdrawal['amount'], (int) $withdrawal['user_id']]);
        admin_log($adminId, 'withdrawal.paid', sprintf('Paid withdrawal #%d (%s)', $withdrawalId, money($withdrawal['payout_amount'])));
    });
}

/** Reject (admin) or cancel (member) a pending withdrawal and refund it. */
function withdrawal_refund(int $withdrawalId, string $status, ?int $adminId, string $note = '', ?int $ownerId = null): void
{
    tx(function () use ($withdrawalId, $status, $adminId, $note, $ownerId): void {
        $withdrawal = row('SELECT * FROM withdrawals WHERE id = ?', [$withdrawalId]);
        if ($withdrawal === null || ($ownerId !== null && (int) $withdrawal['user_id'] !== $ownerId)) {
            throw new AppError('Withdrawal not found.');
        }
        $userId = (int) $withdrawal['user_id'];
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        $withdrawal = row('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE', [$withdrawalId]);
        if ($withdrawal['status'] !== 'pending') {
            throw new AppError('This withdrawal is no longer pending.');
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
    });
}
