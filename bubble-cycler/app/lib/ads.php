<?php
/**
 * Advertising.
 *
 *  - Every bubble bought gives the buyer `ad_credits_per_bubble` credits.
 *  - Members spend credits on campaigns; 1 credit = 1 completed ad view.
 *  - Before each purchase the buyer watches a sponsored message for
 *    `ad_seconds` seconds. Member campaigns rotate first (least recently
 *    shown wins); house ads fill in when no member campaign is running.
 *  - A completed, unused view unlocks exactly one purchase. The timer is
 *    enforced on the server, the browser countdown is only a convenience.
 */
declare(strict_types=1);

const CAMPAIGN_STATUSES = ['pending', 'active', 'paused', 'completed', 'rejected'];

/** Pick the next campaign to show to $viewerId (never their own). */
function ad_pick_campaign(int $viewerId, bool $touch = true): ?array
{
    $campaign = row(
        "SELECT * FROM ad_campaigns
          WHERE status = 'active' AND is_house = 0 AND credits_remaining > 0 AND user_id <> ?
          ORDER BY last_shown_at ASC, id ASC LIMIT 1",
        [$viewerId]
    );
    $campaign ??= row(
        "SELECT * FROM ad_campaigns WHERE status = 'active' AND is_house = 1 ORDER BY last_shown_at ASC, id ASC LIMIT 1"
    );
    if ($campaign !== null && $touch) {
        q('UPDATE ad_campaigns SET last_shown_at = ? WHERE id = ?', [now(), (int) $campaign['id']]);
    }
    return $campaign;
}

/**
 * The ad the buy page should show. Re-uses the member's latest unused view
 * while it is still valid, so refreshing the page does not restart the timer.
 *
 * @return array{view: array, campaign: ?array, remaining: int}|null  null when there is no ad to show
 */
function ad_start_view(int $userId): ?array
{
    $seconds = max(0, setting_int('ad_seconds'));
    $ttl = max(60, setting_int('ad_view_ttl'));
    $cutoff = gmdate('Y-m-d H:i:s', time() - $ttl);

    $view = row(
        'SELECT * FROM ad_views WHERE user_id = ? AND used_at IS NULL AND started_at >= ? ORDER BY id DESC LIMIT 1',
        [$userId, $cutoff]
    );
    if ($view !== null) {
        $campaign = row('SELECT * FROM ad_campaigns WHERE id = ?', [(int) $view['campaign_id']]);
        $remaining = max(0, $seconds - (time() - utc_ts($view['started_at'])));
        return ['view' => $view, 'campaign' => $campaign, 'remaining' => $remaining];
    }

    $campaign = ad_pick_campaign($userId);
    if ($campaign === null) {
        return null;
    }
    $token = bin2hex(random_bytes(16));
    $id = insert('ad_views', [
        'campaign_id' => (int) $campaign['id'],
        'user_id'     => $userId,
        'token'       => $token,
        'started_at'  => now(),
        'ip'          => PHP_SAPI === 'cli' ? null : client_ip(),
    ]);
    $view = row_required('SELECT * FROM ad_views WHERE id = ?', [$id]);
    return ['view' => $view, 'campaign' => $campaign, 'remaining' => $seconds];
}

/**
 * Validate the ad view a purchase relies on. Returns null when no ad could
 * be shown at all (no campaigns running), in which case buying is allowed.
 */
function ad_view_for_purchase(int $userId, string $token): ?array
{
    if ($token === '') {
        if (ad_pick_campaign($userId, false) === null) {
            return null;
        }
        throw new AppError('Please watch the sponsored message first — it unlocks your purchase.');
    }
    $view = row('SELECT * FROM ad_views WHERE token = ? AND user_id = ? FOR UPDATE', [$token, $userId]);
    if ($view === null) {
        throw new AppError('We could not find your ad session. Please watch the sponsored message again.');
    }
    if ($view['used_at'] !== null) {
        throw new AppError('That sponsored message already unlocked a purchase. Please watch the next one.');
    }
    $elapsed = time() - utc_ts($view['started_at']);
    $seconds = max(0, setting_int('ad_seconds'));
    if ($elapsed < $seconds) {
        throw new AppError(sprintf('Please keep watching — your purchase unlocks in %ds.', $seconds - $elapsed));
    }
    if ($elapsed > max(60, setting_int('ad_view_ttl'))) {
        throw new AppError('Your ad session expired. Please watch the sponsored message again.');
    }
    return $view;
}

/** Mark a view as used by a purchase and bill the advertiser if not done yet. */
function ad_view_consume(array $view, int $purchaseId): void
{
    $now = now();
    q(
        'UPDATE ad_views SET used_at = ?, purchase_id = ?, completed_at = COALESCE(completed_at, ?) WHERE id = ?',
        [$now, $purchaseId, $now, (int) $view['id']]
    );
    if ($view['completed_at'] === null) {
        ad_charge_view((int) $view['campaign_id']);
    }
}

/** Browser reports the countdown finished (AJAX). Idempotent. */
function ad_view_complete(int $userId, string $token): bool
{
    return tx(function () use ($userId, $token): bool {
        $view = row('SELECT * FROM ad_views WHERE token = ? AND user_id = ? FOR UPDATE', [$token, $userId]);
        if ($view === null) {
            return false;
        }
        if ($view['completed_at'] !== null) {
            return true;
        }
        if (time() - utc_ts($view['started_at']) < max(0, setting_int('ad_seconds'))) {
            return false;
        }
        q('UPDATE ad_views SET completed_at = ? WHERE id = ?', [now(), (int) $view['id']]);
        ad_charge_view((int) $view['campaign_id']);
        return true;
    });
}

/** One completed view: +1 view, −1 credit (house ads are free and unlimited). */
function ad_charge_view(int $campaignId): void
{
    $campaign = row('SELECT id, is_house, credits_remaining, status FROM ad_campaigns WHERE id = ? FOR UPDATE', [$campaignId]);
    if ($campaign === null) {
        return;
    }
    if ((int) $campaign['is_house'] === 1) {
        q('UPDATE ad_campaigns SET views = views + 1 WHERE id = ?', [$campaignId]);
        return;
    }
    $remaining = (int) $campaign['credits_remaining'];
    if ($remaining <= 0) {
        return;
    }
    $remaining--;
    $status = $remaining === 0 && $campaign['status'] === 'active' ? 'completed' : $campaign['status'];
    q(
        'UPDATE ad_campaigns SET views = views + 1, credits_remaining = ?, status = ?, updated_at = ? WHERE id = ?',
        [$remaining, $status, now(), $campaignId]
    );
}

/** Record a click on the ad behind $token and return where to send the member. */
function ad_click(int $userId, string $token): ?string
{
    return tx(function () use ($userId, $token): ?string {
        $view = row('SELECT * FROM ad_views WHERE token = ? AND user_id = ? FOR UPDATE', [$token, $userId]);
        if ($view === null) {
            return null;
        }
        $campaign = row('SELECT id, url FROM ad_campaigns WHERE id = ?', [(int) $view['campaign_id']]);
        if ($campaign === null) {
            return null;
        }
        if ((int) $view['clicked'] === 0) {
            q('UPDATE ad_views SET clicked = 1 WHERE id = ?', [(int) $view['id']]);
            q('UPDATE ad_campaigns SET clicks = clicks + 1 WHERE id = ?', [(int) $campaign['id']]);
        }
        return (string) $campaign['url'];
    });
}

/* -------------------------------------------------------------------------
 * Campaign management
 * ---------------------------------------------------------------------- */

/** Validate campaign fields coming from a form. */
function campaign_clean(array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $url = trim((string) ($input['url'] ?? ''));
    $image = trim((string) ($input['image_url'] ?? ''));
    $cta = trim((string) ($input['cta_label'] ?? '')) ?: 'Visit site';

    if (mb_strlen($title) < 3 || mb_strlen($title) > 80) {
        throw new AppError('The headline must be 3 to 80 characters long.');
    }
    if (mb_strlen($description) > 220) {
        throw new AppError('The description can be at most 220 characters long.');
    }
    if (!valid_http_url($url)) {
        throw new AppError('Enter a valid destination URL starting with http:// or https://');
    }
    if ($image !== '' && !valid_http_url($image, true)) {
        throw new AppError('The banner image must be an https:// URL (JPG, PNG, WebP or GIF).');
    }
    if (mb_strlen($cta) > 30) {
        throw new AppError('The button label can be at most 30 characters long.');
    }
    return [
        'title'       => $title,
        'description' => $description,
        'url'         => $url,
        'image_url'   => $image !== '' ? $image : null,
        'cta_label'   => $cta,
    ];
}

function member_campaign(int $userId, int $campaignId, bool $lock = false): array
{
    $campaign = row(
        'SELECT * FROM ad_campaigns WHERE id = ? AND user_id = ? AND is_house = 0' . ($lock ? ' FOR UPDATE' : ''),
        [$campaignId, $userId]
    );
    if ($campaign === null) {
        throw new AppError('Campaign not found.');
    }
    return $campaign;
}

function campaign_create(int $userId, array $input, int $credits): int
{
    $data = campaign_clean($input);
    $minimum = max(1, setting_int('min_campaign_credits'));
    if ($credits < $minimum) {
        throw new AppError(sprintf('Fund your campaign with at least %s.', plural($minimum, 'credit')));
    }
    return tx(function () use ($userId, $data, $credits): int {
        $status = setting_bool('campaign_approval') ? 'pending' : 'active';
        $id = insert('ad_campaigns', $data + [
            'user_id'           => $userId,
            'is_house'          => 0,
            'credits_total'     => $credits,
            'credits_remaining' => $credits,
            'status'            => $status,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        wallet_move($userId, 'ads', -$credits, 'campaign_fund', sprintf('Funded campaign “%s”', $data['title']), 'campaign', $id);
        return $id;
    });
}

function campaign_update(int $userId, int $campaignId, array $input): void
{
    $data = campaign_clean($input);
    tx(function () use ($userId, $campaignId, $data): void {
        $campaign = member_campaign($userId, $campaignId, true);
        $status = $campaign['status'];
        if (setting_bool('campaign_approval') && $status !== 'completed') {
            $status = 'pending'; // edited creatives are reviewed again
        } elseif ($status === 'rejected') {
            $status = 'active';
        }
        update_row('ad_campaigns', $campaignId, $data + ['status' => $status, 'admin_note' => null, 'updated_at' => now()]);
    });
}

function campaign_add_credits(int $userId, int $campaignId, int $credits): void
{
    if ($credits < 1) {
        throw new AppError('Enter how many credits to add.');
    }
    tx(function () use ($userId, $campaignId, $credits): void {
        $campaign = member_campaign($userId, $campaignId, false);
        wallet_move($userId, 'ads', -$credits, 'campaign_fund', sprintf('Added credits to “%s”', $campaign['title']), 'campaign', $campaignId);
        $campaign = member_campaign($userId, $campaignId, true);
        $status = $campaign['status'] === 'completed' ? 'active' : $campaign['status'];
        q(
            'UPDATE ad_campaigns SET credits_total = credits_total + ?, credits_remaining = credits_remaining + ?,
                    status = ?, updated_at = ? WHERE id = ?',
            [$credits, $credits, $status, now(), $campaignId]
        );
    });
}

function campaign_toggle(int $userId, int $campaignId): string
{
    return tx(function () use ($userId, $campaignId): string {
        $campaign = member_campaign($userId, $campaignId, true);
        $next = match ($campaign['status']) {
            'active' => 'paused',
            'paused' => 'active',
            default  => throw new AppError('Only running or paused campaigns can be paused or resumed.'),
        };
        q('UPDATE ad_campaigns SET status = ?, updated_at = ? WHERE id = ?', [$next, now(), $campaignId]);
        return $next;
    });
}

/** Delete a member campaign and refund its unused credits. */
function campaign_delete(int $userId, int $campaignId): int
{
    return tx(function () use ($userId, $campaignId): int {
        $campaign = member_campaign($userId, $campaignId, false);
        $owner = (int) $campaign['user_id'];
        row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$owner]); // member row before campaign row
        $campaign = member_campaign($userId, $campaignId, true);
        $refund = (int) $campaign['credits_remaining'];
        if ($refund > 0) {
            wallet_move($owner, 'ads', $refund, 'campaign_refund', sprintf('Unused credits from “%s”', $campaign['title']), 'campaign', $campaignId);
        }
        q('DELETE FROM ad_campaigns WHERE id = ?', [$campaignId]);
        return $refund;
    });
}

/* -------------------------------------------------------------------------
 * Admin moderation & house ads
 * ---------------------------------------------------------------------- */

function admin_campaign_set_status(int $adminId, int $campaignId, string $status, string $note = ''): void
{
    if (!in_array($status, ['active', 'paused', 'rejected'], true)) {
        throw new AppError('Unknown campaign status.');
    }
    tx(function () use ($adminId, $campaignId, $status, $note): void {
        $campaign = row('SELECT * FROM ad_campaigns WHERE id = ? FOR UPDATE', [$campaignId]);
        if ($campaign === null) {
            throw new AppError('Campaign not found.');
        }
        if ($status === 'active' && (int) $campaign['is_house'] === 0 && (int) $campaign['credits_remaining'] <= 0) {
            $status = 'completed';
        }
        q(
            'UPDATE ad_campaigns SET status = ?, admin_note = ?, updated_at = ? WHERE id = ?',
            [$status, $note !== '' ? mb_substr($note, 0, 255) : null, now(), $campaignId]
        );
        admin_log($adminId, 'campaign.' . $status, sprintf('Campaign #%d “%s”%s', $campaignId, $campaign['title'], $note !== '' ? ' — ' . $note : ''));
    });
}

function admin_campaign_delete(int $adminId, int $campaignId): void
{
    tx(function () use ($adminId, $campaignId): void {
        $campaign = row('SELECT * FROM ad_campaigns WHERE id = ?', [$campaignId]);
        if ($campaign === null) {
            throw new AppError('Campaign not found.');
        }
        if ((int) $campaign['is_house'] === 0 && $campaign['user_id'] !== null) {
            campaign_delete((int) $campaign['user_id'], $campaignId);
        } else {
            q('DELETE FROM ad_campaigns WHERE id = ?', [$campaignId]);
        }
        admin_log($adminId, 'campaign.delete', sprintf('Deleted campaign #%d “%s”', $campaignId, $campaign['title']));
    });
}

function house_ad_save(int $adminId, ?int $campaignId, array $input, string $status): int
{
    $data = campaign_clean($input);
    $status = in_array($status, ['active', 'paused'], true) ? $status : 'active';
    if ($campaignId === null) {
        $id = insert('ad_campaigns', $data + [
            'user_id' => null, 'is_house' => 1, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        admin_log($adminId, 'house_ad.create', sprintf('Created house ad #%d “%s”', $id, $data['title']));
        return $id;
    }
    $existing = row('SELECT id FROM ad_campaigns WHERE id = ? AND is_house = 1', [$campaignId]);
    if ($existing === null) {
        throw new AppError('House ad not found.');
    }
    update_row('ad_campaigns', $campaignId, $data + ['status' => $status, 'updated_at' => now()]);
    admin_log($adminId, 'house_ad.update', sprintf('Updated house ad #%d “%s”', $campaignId, $data['title']));
    return $campaignId;
}

function campaign_ctr(array $campaign): string
{
    return pct((int) $campaign['clicks'], (int) $campaign['views']);
}
