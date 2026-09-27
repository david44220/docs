<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$error = null;
$form = null;

if (is_post()) {
    $action = post('action');
    $id = (int) post('id');
    $input = [
        'title'       => post('title'),
        'description' => post('description'),
        'url'         => post('url'),
        'image_url'   => post('image_url'),
        'cta_label'   => post('cta_label'),
    ];
    try {
        switch ($action) {
            case 'create':
                campaign_create($uid, $input, (int) post('credits'));
                flash('success', setting_bool('campaign_approval')
                    ? 'Campaign created — it goes live as soon as an admin approves it.'
                    : 'Campaign created and live. Members will see it before their next purchase.');
                redirect(url('advertise.php'));
            case 'update':
                campaign_update($uid, $id, $input);
                flash('success', setting_bool('campaign_approval') ? 'Changes saved — the campaign is back in review.' : 'Changes saved.');
                redirect(url('advertise.php'));
            case 'fund':
                campaign_add_credits($uid, $id, (int) post('credits'));
                flash('success', 'Credits added to your campaign.');
                redirect(url('advertise.php'));
            case 'toggle':
                $status = campaign_toggle($uid, $id);
                flash('success', $status === 'paused' ? 'Campaign paused.' : 'Campaign resumed.');
                redirect(url('advertise.php'));
            case 'delete':
                $refund = campaign_delete($uid, $id);
                flash('success', 'Campaign deleted' . ($refund > 0 ? ' — ' . plural($refund, 'credit') . ' returned to your account.' : '.'));
                redirect(url('advertise.php'));
            default:
                throw new AppError('Unknown action.');
        }
    } catch (AppError $e) {
        if (in_array($action, ['create', 'update'], true)) {
            $error = $e->getMessage();
            $form = $input + ['id' => $id, 'credits' => post('credits')];
        } else {
            flash('error', $e->getMessage());
            redirect(url('advertise.php'));
        }
    }
}

$editing = null;
if (query_int('edit') > 0) {
    try {
        $editing = member_campaign($uid, query_int('edit'));
    } catch (AppError) {
        $editing = null;
    }
}
if ($form === null) {
    $form = $editing !== null
        ? ['id' => (int) $editing['id']] + array_intersect_key($editing, array_flip(['title', 'description', 'url', 'image_url', 'cta_label'])) + ['credits' => '']
        : ['id' => 0, 'title' => '', 'description' => '', 'url' => '', 'image_url' => '', 'cta_label' => 'Visit site', 'credits' => (string) max(setting_int('min_campaign_credits'), min(100, (int) $user['ad_credits']))];
}

$campaigns = rows('SELECT * FROM ad_campaigns WHERE user_id = ? AND is_house = 0 ORDER BY id DESC', [$uid]);
$totals = ['views' => 0, 'clicks' => 0, 'live' => 0];
foreach ($campaigns as $c) {
    $totals['views'] += (int) $c['views'];
    $totals['clicks'] += (int) $c['clicks'];
    $totals['live'] += (int) $c['credits_remaining'];
}

render('user/advertise', [
    'title'     => 'Advertise',
    'eyebrow'   => 'Your ad network',
    'page'      => 'advertise',
    'user'      => current_user(true),
    'campaigns' => $campaigns,
    'totals'    => $totals,
    'form'      => $form,
    'error'     => $error,
]);
