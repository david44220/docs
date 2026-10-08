<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$aid = (int) $admin['id'];
$type = (is_post() ? post('type') : query('type')) === 'withdrawal' ? 'withdrawal' : 'deposit';
$fields = ['name', 'currency', 'logo_url', 'color', 'account_label', 'account_value', 'instructions',
    'min_amount', 'max_amount', 'fee_fixed', 'fee_percent', 'require_proof', 'status', 'sort_order'];
$error = null;
$form = null;

if (is_post()) {
    $action = post('action');
    $id = (int) post('id');
    $input = [];
    try {
        if ($action === 'save') {
            foreach ($fields as $field) {
                $input[$field] = post($field);
            }
            payment_method_save($aid, $id > 0 ? $id : null, $type, $input);
            flash('success', $id > 0 ? t('Method updated.') : ($input['status'] === 'inactive' ? t('Method created. It is inactive until you enable it.') : t('Method created. Members can use it right away.')));
            redirect(url('admin/methods.php', ['type' => $type]));
        }
        $method = payment_method($id, $type, false);
        if ($method === null) {
            throw new AppError(t('Payment method not found.'));
        }
        if ($action === 'toggle') {
            $next = $method['status'] === 'active' ? 'inactive' : 'active';
            q('UPDATE payment_methods SET status = ?, updated_at = ? WHERE id = ?', [$next, now(), $id]);
            admin_log($aid, 'method.' . ($next === 'active' ? 'enable' : 'disable'), sprintf('%s method #%d “%s”', ucfirst($type), $id, $method['name']));
            flash('success', $next === 'active' ? t('“{name}” is now active.', ['name' => $method['name']]) : t('“{name}” is now inactive.', ['name' => $method['name']]));
        } elseif ($action === 'delete') {
            payment_method_delete($aid, $id);
            flash('success', t('“{name}” deleted. Past requests keep their history.', ['name' => $method['name']]));
        }
    } catch (AppError $e) {
        if ($action === 'save') {
            $error = $e->getMessage();
            $form = $input + ['id' => $id];
        } else {
            flash('error', $e->getMessage());
        }
    }
    if ($error === null) {
        redirect(url('admin/methods.php', ['type' => $type]));
    }
}

$methods = payment_methods($type, false);
$usageTable = $type === 'deposit' ? 'deposits' : 'withdrawals';
$usage = [];
foreach (rows("SELECT method_id, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM $usageTable WHERE method_id IS NOT NULL GROUP BY method_id") as $r) {
    $usage[(int) $r['method_id']] = ['n' => (int) $r['n'], 'total' => (int) $r['total']];
}

$editId = query_int('edit');
if ($form === null && $editId > 0 && ($method = payment_method($editId, $type, false)) !== null) {
    $form = [
        'id'            => (int) $method['id'],
        'name'          => $method['name'],
        'currency'      => $method['currency'],
        'logo_url'      => (string) $method['logo_url'],
        'color'         => $method['color'],
        'account_label' => $method['account_label'],
        'account_value' => $method['account_value'],
        'instructions'  => (string) $method['instructions'],
        'min_amount'    => units_to_input($method['min_amount']),
        'max_amount'    => (int) $method['max_amount'] > 0 ? units_to_input($method['max_amount']) : '',
        'fee_fixed'     => units_to_input($method['fee_fixed']),
        'fee_percent'   => bp_to_input($method['fee_percent_bp']),
        'require_proof' => (string) $method['require_proof'],
        'status'        => $method['status'],
        'sort_order'    => (string) $method['sort_order'],
    ];
}
$form ??= [
    'id' => 0, 'name' => '', 'currency' => 'USD', 'logo_url' => '', 'color' => '#dfaaff',
    'account_label' => $type === 'deposit' ? 'Send to' : 'Your account / wallet address',
    'account_value' => '', 'instructions' => '', 'min_amount' => $type === 'deposit' ? '5.00' : '2.00', 'max_amount' => '',
    'fee_fixed' => '0.00', 'fee_percent' => '0', 'require_proof' => '0', 'status' => 'active', 'sort_order' => '0',
];

render('admin/methods', [
    'title'      => t('Payment methods'),
    'eyebrow'    => t('Manual deposits & withdrawals'),
    'page'       => 'admin-methods',
    'admin_area' => true,
    'type'       => $type,
    'methods'    => $methods,
    'usage'      => $usage,
    'form'       => $form,
    'error'      => $error,
]);
