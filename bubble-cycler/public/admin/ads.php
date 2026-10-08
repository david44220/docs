<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$aid = (int) $admin['id'];
$tabs = ['pending' => t('In review'), 'active' => t('Live'), 'paused' => t('Paused'), 'completed' => t('Completed'), 'rejected' => t('Rejected'), 'house' => t('House ads'), 'all' => t('All')];
$status = array_key_exists(query('status'), $tabs) ? query('status') : 'pending';
$houseForm = null;
$houseError = null;

if (is_post()) {
    $action = post('action');
    $id = (int) post('id');
    try {
        switch ($action) {
            case 'approve':
            case 'resume':
                admin_campaign_set_status($aid, $id, 'active', post('note'));
                flash('success', $action === 'approve' ? t('Campaign approved — it is now in rotation.') : t('Campaign resumed.'));
                break;
            case 'reject':
                if (post('note') === '') {
                    throw new AppError(t('Tell the advertiser why the campaign was rejected.'));
                }
                admin_campaign_set_status($aid, $id, 'rejected', post('note'));
                flash('success', t('Campaign rejected. The advertiser can edit it or delete it to get the credits back.'));
                break;
            case 'pause':
                admin_campaign_set_status($aid, $id, 'paused', post('note'));
                flash('success', t('Campaign paused.'));
                break;
            case 'delete':
                admin_campaign_delete($aid, $id);
                flash('success', t('Campaign deleted. Unused credits were returned to the advertiser.'));
                break;
            case 'house':
                $input = [
                    'title' => post('title'), 'description' => post('description'), 'url' => post('url'),
                    'image_url' => post('image_url'), 'cta_label' => post('cta_label'),
                ];
                house_ad_save($aid, $id > 0 ? $id : null, $input, post('status'));
                flash('success', t('House ad saved.'));
                redirect(url('admin/ads.php', ['status' => 'house']));
            default:
                throw new AppError(t('Unknown action.'));
        }
    } catch (AppError $e) {
        if ($action === 'house') {
            $houseError = $e->getMessage();
            $houseForm = ($input ?? []) + ['id' => $id, 'status' => post('status')];
            $status = 'house';
        } else {
            flash('error', $e->getMessage());
        }
    }
    if ($houseError === null) {
        redirect(url('admin/ads.php', ['status' => $status]));
    }
}

$where = [];
$params = [];
if ($status === 'house') {
    $where[] = 'c.is_house = 1';
} elseif ($status !== 'all') {
    $where[] = 'c.status = ? AND c.is_house = 0';
    $params[] = $status;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$pager = paginate((int) val("SELECT COUNT(*) FROM ad_campaigns c $sqlWhere", $params), 20);
$campaigns = rows(
    "SELECT c.*, u.username FROM ad_campaigns c LEFT JOIN users u ON u.id = c.user_id $sqlWhere
      ORDER BY c.id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    $params
);

$review = query_int('review') > 0
    ? row('SELECT c.*, u.username FROM ad_campaigns c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?', [query_int('review')])
    : null;

if ($houseForm === null && $status === 'house') {
    $editHouse = query_int('edit') > 0 ? row('SELECT * FROM ad_campaigns WHERE id = ? AND is_house = 1', [query_int('edit')]) : null;
    $houseForm = $editHouse !== null
        ? array_intersect_key($editHouse, array_flip(['id', 'title', 'description', 'url', 'image_url', 'cta_label', 'status']))
        : ['id' => 0, 'title' => '', 'description' => '', 'url' => absolute_url('advertise.php'), 'image_url' => '', 'cta_label' => t('Learn more'), 'status' => 'active'];
}

$counts = ['house' => 0, 'all' => 0];
foreach (rows('SELECT status, is_house, COUNT(*) AS n FROM ad_campaigns GROUP BY status, is_house') as $r) {
    if ((int) $r['is_house'] === 1) {
        $counts['house'] += (int) $r['n'];
    } else {
        $counts[$r['status']] = ($counts[$r['status']] ?? 0) + (int) $r['n'];
    }
    $counts['all'] += (int) $r['n'];
}

render('admin/ads', [
    'title'      => t('Ad campaigns'),
    'eyebrow'    => t('Moderation & house ads'),
    'page'       => 'admin-ads',
    'admin_area' => true,
    'tabs'       => $tabs,
    'status'     => $status,
    'campaigns'  => $campaigns,
    'pager'      => $pager,
    'review'     => $review,
    'counts'     => $counts,
    'houseForm'  => $houseForm,
    'houseError' => $houseError,
]);
