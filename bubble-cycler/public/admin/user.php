<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$aid = (int) $admin['id'];
$id = is_post() ? (int) post('id') : query_int('id');
$member = row('SELECT * FROM users WHERE id = ?', [$id]);
if ($member === null) {
    abort(404, 'Member not found.');
}

if (is_post()) {
    try {
        switch (post('action')) {
            case 'deposit':
                $amount = to_payment_units(post('amount'));
                if ($amount === null) {
                    throw new AppError('Enter a valid amount, e.g. 25 or 25.50.');
                }
                $depositId = deposit_manual($aid, $id, $amount, post('note'));
                flash('success', sprintf('Manual deposit #%d: %s added to %s’s purchase balance.', $depositId, money($amount), $member['username']));
                break;
            case 'adjust':
                admin_adjust_balance($aid, $id, post('wallet'), post('direction'), post('amount'), post('note'));
                flash('success', 'Balance adjusted.');
                break;
            case 'status':
                admin_set_user_status($aid, $id, post('status'));
                flash('success', post('status') === 'banned' ? 'Member banned — they are signed out and cannot sign in.' : 'Member re-activated.');
                break;
            case 'role':
                admin_set_user_role($aid, $id, post('role'));
                flash('success', post('role') === 'admin' ? 'Member promoted to admin.' : 'Admin rights removed.');
                break;
            case 'password':
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                admin_reset_password($aid, $id, $password);
                flash('success', 'Password reset. Share it with the member through a secure channel.');
                break;
            default:
                throw new AppError('Unknown action.');
        }
    } catch (AppError $e) {
        flash('error', $e->getMessage());
    }
    redirect(url('admin/user.php', ['id' => $id]));
}

render('admin/user', [
    'title'       => $member['username'],
    'eyebrow'     => 'Member #' . $id,
    'page'        => 'admin-users',
    'admin_area'  => true,
    'member'      => $member,
    'isSelf'      => $id === $aid,
    'referrer'    => $member['referrer_id'] ? row('SELECT id, username FROM users WHERE id = ?', [(int) $member['referrer_id']]) : null,
    'referrals'   => (int) val('SELECT COUNT(*) FROM users WHERE referrer_id = ?', [$id]),
    'stats'       => member_bubble_stats($id),
    'pool'        => pool_state(),
    'bubbles'     => rows("SELECT * FROM bubbles WHERE user_id = ? ORDER BY status = 'active' DESC, id ASC LIMIT 12", [$id]),
    'deposits'    => rows('SELECT * FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 6', [$id]),
    'withdrawals' => rows('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 6', [$id]),
    'ledger'      => rows('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 12', [$id]),
    'campaigns'   => rows('SELECT id, title, status, credits_remaining, views, clicks FROM ad_campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$id]),
]);
