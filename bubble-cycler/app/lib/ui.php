<?php
/**
 * UI building blocks shared by the views: inline SVG icons, bubble spheres,
 * badges, avatars, pagination and navigation definitions.
 */
declare(strict_types=1);

function icon(string $name, string $class = ''): string
{
    static $icons = [
        'grid'        => '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
        'bubble'      => '<circle cx="12" cy="12" r="8.5"/><path d="M8.2 10.2a4.2 4.2 0 0 1 3-3.1"/>',
        'bubbles'     => '<circle cx="9.5" cy="13.5" r="6.5"/><path d="M6.4 11.6a3.4 3.4 0 0 1 2.3-2.4"/><circle cx="18.5" cy="6" r="2.8"/><circle cx="19.5" cy="15.5" r="1.8"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'minus'       => '<path d="M5 12h14"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'megaphone'   => '<path d="M4 10.5v3a1.5 1.5 0 0 0 1.5 1.5H8l7.5 4.5v-15L8 9H5.5A1.5 1.5 0 0 0 4 10.5z"/><path d="m8 15 1.2 4.5h2.3L10.6 15"/><path d="M18.5 9.5a3.5 3.5 0 0 1 0 5"/>',
        'download'    => '<path d="M12 4v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M5 20h14"/>',
        'upload'      => '<path d="M12 16V5"/><path d="M7.5 9.5 12 5l4.5 4.5"/><path d="M5 20h14"/>',
        'list'        => '<path d="M9 6.5h11M9 12h11M9 17.5h11"/><circle cx="4.8" cy="6.5" r=".9"/><circle cx="4.8" cy="12" r=".9"/><circle cx="4.8" cy="17.5" r=".9"/>',
        'users'       => '<circle cx="9" cy="8.5" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M15.5 5.2a3.5 3.5 0 0 1 0 6.6"/><path d="M17.5 14.3A6 6 0 0 1 21 20"/>',
        'user'        => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'shield'      => '<path d="M12 3 5 6v5.5c0 4.4 3 8.2 7 9.5 4-1.3 7-5.1 7-9.5V6l-7-3z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'sliders'     => '<path d="M4 6.5h9M17 6.5h3M4 12h3M11 12h9M4 17.5h11M19 17.5h1"/><circle cx="15" cy="6.5" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="17.5" r="2"/>',
        'logout'      => '<path d="M9.5 4.5H6.5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h3"/><path d="m15.5 16 4-4-4-4"/><path d="M19.5 12H9.5"/>',
        'check'       => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'x'           => '<path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
        'copy'        => '<rect x="8.5" y="8.5" width="11.5" height="11.5" rx="2.5"/><path d="M15.5 8.5V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7.5a2 2 0 0 0 2 2h2.5"/>',
        'eye'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'pointer'     => '<path d="m5 4 6.5 16 2.2-6.5L20 11.3 5 4z"/>',
        'external'    => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>',
        'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'sparkles'    => '<path d="M11 3.5 12.6 8l4.4 1.6-4.4 1.6L11 15.7l-1.6-4.5L5 9.6 9.4 8 11 3.5z"/><path d="M18 14.5l.8 2.1 2.2.8-2.2.8-.8 2.3-.8-2.3-2.2-.8 2.2-.8.8-2.1z"/>',
        'lock'        => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
        'unlock'      => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 6.8-1.2"/>',
        'trending'    => '<path d="m3.5 16.5 5.5-5.5 4 4 7.5-7.5"/><path d="M15 7.5h5.5V13"/>',
        'coins'       => '<ellipse cx="9.5" cy="7" rx="5.5" ry="2.8"/><path d="M4 7v4.5c0 1.5 2.5 2.8 5.5 2.8"/><path d="M15 7v1.5"/><ellipse cx="14.5" cy="13.5" rx="5.5" ry="2.8"/><path d="M9 13.5V18c0 1.5 2.5 2.8 5.5 2.8S20 19.5 20 18v-4.5"/>',
        'wallet'      => '<path d="M18 7.5V6a1.5 1.5 0 0 0-1.5-1.5h-11A2.5 2.5 0 0 0 3 7v10a2.5 2.5 0 0 0 2.5 2.5h13A2.5 2.5 0 0 0 21 17v-7a2.5 2.5 0 0 0-2.5-2.5H5.5"/><circle cx="16.5" cy="13.5" r="1.2"/>',
        'gift'        => '<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v7a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-7"/><path d="M12 8v12.5"/><path d="M12 8C11 5.5 9.8 4 8.2 4.2 6.3 4.5 6.7 8 9 8h3zm0 0c1-2.5 2.2-4 3.8-3.8 1.9.3 1.5 3.8-.8 3.8h-3z"/>',
        'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.2"/>',
        'alert'       => '<path d="M12 4 3 19.5h18L12 4z"/><path d="M12 10v4.5"/><path d="M12 17.2v.2"/>',
        'trash'       => '<path d="M4.5 7h15"/><path d="M9.5 7V4.5h5V7"/><path d="m6.5 7 .9 12.2a1.5 1.5 0 0 0 1.5 1.3h6.2a1.5 1.5 0 0 0 1.5-1.3L17.5 7"/><path d="M10 11v5.5M14 11v5.5"/>',
        'edit'        => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/>',
        'pause'       => '<rect x="6.5" y="5" width="3.5" height="14" rx="1"/><rect x="14" y="5" width="3.5" height="14" rx="1"/>',
        'play'        => '<path d="M7.5 5v14l11-7-11-7z"/>',
        'search'      => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'bank'        => '<path d="M3.5 9.5 12 4.5l8.5 5"/><path d="M4 20h16"/><path d="M6.5 11v6M10 11v6M14 11v6M17.5 11v6"/>',
        'image'       => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.7"/><path d="m20.5 16-5-5-9.5 8.5"/>',
        'zap'         => '<path d="M13 3 5 13.5h6.5L11 21l8-10.5h-6.5L13 3z"/>',
        'activity'    => '<path d="M3 12h4l2.5-6.5 5 13 2.5-6.5h4"/>',
        'droplet'     => '<path d="M12 3.5s6.5 6.8 6.5 11a6.5 6.5 0 0 1-13 0c0-4.2 6.5-11 6.5-11z"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'arrow-left'  => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
        'arrow-up'    => '<path d="M12 19V5"/><path d="m6 11 6-6 6 6"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left'  => '<path d="m15 6-6 6 6 6"/>',
        'home'        => '<path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5H15v-6H9v6H5.5A1.5 1.5 0 0 1 4 19v-8.5z"/>',
        'layers'      => '<path d="M12 3.5 3 8.5l9 5 9-5-9-5z"/><path d="m3 12.5 9 5 9-5"/><path d="m3 16.5 9 5 9-5"/>',
        'target'      => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r=".8"/>',
        'refresh'     => '<path d="M19.5 10A7.8 7.8 0 0 0 5.8 7.2L4 9"/><path d="M4 4.5V9h4.5"/><path d="M4.5 14a7.8 7.8 0 0 0 13.7 2.8L20 15"/><path d="M20 19.5V15h-4.5"/>',
        'key'         => '<circle cx="8" cy="15.5" r="4"/><path d="m10.9 12.6 8.6-8.6"/><path d="m16.5 7 2.5 2.5"/><path d="m14 9.5 2 2"/>',
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 6 8.2-6"/>',
        'ban'         => '<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
        'file'        => '<path d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5z"/><path d="M14 3.5V8h4.5"/><path d="M9 13h6M9 16.5h4"/>',
        'card'        => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="M6.5 15h4"/>',
        'crown'       => '<path d="m3.5 8 4.5 4 4-6.5 4 6.5 4.5-4-1.8 10.5H5.3L3.5 8z"/>',
        'percent'     => '<path d="M18.5 5.5l-13 13"/><circle cx="7" cy="7" r="2.3"/><circle cx="17" cy="17" r="2.3"/>',
        'globe'       => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.4 3.4 5.2 3.4 8.5s-1.1 6.1-3.4 8.5c-2.3-2.4-3.4-5.2-3.4-8.5S9.7 5.9 12 3.5z"/>',
        'link'        => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l2.8-2.8a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-2.8 2.8a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2"/>',
        'burst'       => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><circle cx="12" cy="12" r="2.5"/>',
        'hourglass'   => '<path d="M6.5 3.5h11M6.5 20.5h11"/><path d="M7.5 3.5c0 4.5 4.5 5.5 4.5 8.5s-4.5 4-4.5 8.5M16.5 3.5c0 4.5-4.5 5.5-4.5 8.5s4.5 4 4.5 8.5"/>',
        'chart'       => '<path d="M4 20V4"/><path d="M4 20h16"/><rect x="7.5" y="12" width="3" height="5" rx=".8"/><rect x="12.5" y="8" width="3" height="9" rx=".8"/><rect x="17.5" y="5" width="3" height="12" rx=".8"/>',
    ];
    $svg = $icons[$name] ?? $icons['bubble'];
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $svg . '</svg>';
}

/**
 * A glossy bubble sphere.
 *
 * Options: size (xs|sm|md|lg|xl), state (filling|rising|expired|idle),
 * fill (0–100 liquid level), rise (0–100 queue progress ring), label, sub,
 * mine (highlight the member's own bubble), float (idle animation), delay.
 */
function bubble_html(array $o = []): string
{
    $size = $o['size'] ?? 'md';
    $state = $o['state'] ?? 'idle';
    // An expired bubble received its whole target: it is always drawn full (gold).
    $fill = $state === 'expired' ? 100.0 : max(0.0, min(100.0, (float) ($o['fill'] ?? 0)));
    $rise = max(0.0, min(100.0, (float) ($o['rise'] ?? 0)));
    $classes = ['bubble', 'bubble--' . $size, 'is-' . $state];
    if (!empty($o['mine'])) {
        $classes[] = 'is-mine';
    }
    if (!isset($o['float']) || $o['float']) {
        $classes[] = 'is-floating';
    }
    if ($fill <= 0.0) {
        $classes[] = 'is-empty';
    }
    $style = sprintf('--fill:%s;--rise:%s;--delay:%ss', round($fill, 2), round($rise, 2), $o['delay'] ?? '0');
    $attrs = '';
    foreach (($o['attrs'] ?? []) as $name => $value) {
        $attrs .= ' ' . e($name) . '="' . e($value) . '"';
    }

    $html = '<div class="' . implode(' ', $classes) . '" style="' . $style . '"' . $attrs . '>';
    $html .= '<span class="bubble__liquid" aria-hidden="true"></span>';
    $html .= '<span class="bubble__gloss" aria-hidden="true"></span>';
    if ($state === 'expired') {
        // Expired bubbles show a check mark instead of their label.
        $html .= '<span class="bubble__burst" aria-hidden="true">' . icon('check') . '</span>';
    } elseif (isset($o['label']) || isset($o['sub'])) {
        $html .= '<span class="bubble__text">';
        if (isset($o['label'])) {
            $html .= '<strong>' . e($o['label']) . '</strong>';
        }
        if (isset($o['sub'])) {
            $html .= '<small>' . e($o['sub']) . '</small>';
        }
        $html .= '</span>';
    }
    return $html . '</div>';
}

/** $context lets the label agree with what it describes in French: 'bubble', 'campaign'. */
function status_badge(string $status, ?string $label = null, string $context = ''): string
{
    $tone = match ($status) {
        'pending'                              => 'amber',
        'approved', 'paid', 'active', 'filling' => 'green',
        'rejected', 'banned'                   => 'red',
        'expired', 'completed', 'admin'        => 'violet',
        'rising'                               => 'blue',
        default                                => 'slate',
    };
    return '<span class="badge badge--' . $tone . '"><i></i>' . e($label ?? status_label($status, $context)) . '</span>';
}

/** Status names (English source texts, translated by status_label()). */
const STATUS_LABELS = [
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'paid'      => 'Paid',
    'active'    => 'Active',
    'filling'   => 'Filling',
    'rejected'  => 'Rejected',
    'banned'    => 'Banned',
    'expired'   => 'Expired',
    'completed' => 'Completed',
    'admin'     => 'Admin',
    'rising'    => 'Rising',
    'cancelled' => 'Cancelled',
    'paused'    => 'Paused',
    'inactive'  => 'Inactive',
    'user'      => 'Member',
];

/** A status in the current language; $context picks the French agreement ('bubble', 'campaign'). */
function status_label(string $status, string $context = ''): string
{
    $label = STATUS_LABELS[$status] ?? null;
    if ($label === null) {
        return ucfirst($status);
    }
    return $context !== '' ? tc($context, $label) : t($label);
}

function method_avatar(array $method, string $size = ''): string
{
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($method['color'] ?? '')) ? $method['color'] : '#dfaaff';
    $class = 'avatar' . ($size !== '' ? ' avatar--' . $size : '');
    if (!empty($method['logo_url'])) {
        return '<span class="' . $class . '" style="--c:' . e($color) . '"><img src="' . e($method['logo_url'])
            . '" alt="" loading="lazy" referrerpolicy="no-referrer"></span>';
    }
    $words = preg_split('/[\s()\-_\/]+/', (string) $method['name'], -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
    return '<span class="' . $class . '" style="--c:' . e($color) . '">' . e($initials) . '</span>';
}

function user_avatar(string $username, string $size = ''): string
{
    $hue = hexdec(substr(md5(strtolower($username)), 0, 2)) / 255 * 360;
    return '<span class="avatar avatar--user' . ($size !== '' ? ' avatar--' . $size : '') . '" style="--h:' . (int) $hue . '">'
        . e(mb_strtoupper(mb_substr($username, 0, 1))) . '</span>';
}

function empty_state(string $iconName, string $title, string $text = '', ?string $ctaUrl = null, ?string $ctaLabel = null): string
{
    $html = '<div class="empty">' . bubble_html(['size' => 'sm', 'state' => 'idle']) . '<span class="empty__icon">' . icon($iconName) . '</span>';
    $html .= '<h3>' . e($title) . '</h3>';
    if ($text !== '') {
        $html .= '<p>' . e($text) . '</p>';
    }
    if ($ctaUrl !== null && $ctaLabel !== null) {
        $html .= '<a class="btn btn--primary btn--sm" href="' . e($ctaUrl) . '">' . e($ctaLabel) . '</a>';
    }
    return $html . '</div>';
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $page = $p['page'];
    $html = '<nav class="pager" aria-label="' . e(t('Pagination')) . '">';
    $html .= $page > 1
        ? '<a class="pager__btn" href="' . e(current_url_with(['page' => $page - 1])) . '" aria-label="' . e(t('Previous page')) . '">' . icon('chevron-left') . '</a>'
        : '<span class="pager__btn is-disabled">' . icon('chevron-left') . '</span>';

    $window = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $p['pages']], fn ($n) => $n >= 1 && $n <= $p['pages']));
    sort($window);
    $previous = 0;
    foreach ($window as $n) {
        if ($n - $previous > 1) {
            $html .= '<span class="pager__gap">…</span>';
        }
        $html .= $n === $page
            ? '<span class="pager__num is-current" aria-current="page">' . e(num($n)) . '</span>'
            : '<a class="pager__num" href="' . e(current_url_with(['page' => $n])) . '">' . e(num($n)) . '</a>';
        $previous = $n;
    }

    $html .= $page < $p['pages']
        ? '<a class="pager__btn" href="' . e(current_url_with(['page' => $page + 1])) . '" aria-label="' . e(t('Next page')) . '">' . icon('chevron-right') . '</a>'
        : '<span class="pager__btn is-disabled">' . icon('chevron-right') . '</span>';
    return $html . '<span class="pager__info">' . e(t('{n} total', ['n' => num($p['total'])])) . '</span></nav>';
}

/** Filter tabs: [key => label], the active key and the query parameter they set. */
function filter_tabs(array $tabs, string $active, string $param = 'status', array $counts = []): string
{
    $html = '<nav class="tabs" aria-label="' . e(t('Filter')) . '">';
    foreach ($tabs as $key => $label) {
        $count = isset($counts[$key]) ? ' <span class="tabs__count">' . e(num($counts[$key])) . '</span>' : '';
        $html .= '<a class="tabs__item' . ((string) $key === $active ? ' is-active' : '') . '" href="'
            . e(current_url_with([$param => $key, 'page' => null, 'review' => null, 'edit' => null])) . '">' . e($label) . $count . '</a>';
    }
    return $html . '</nav>';
}

/** Sidebar navigation for members: sections of [key, file, label, icon]. */
function user_nav(): array
{
    return [
        t('Play') => [
            ['dashboard', 'dashboard.php', t('Dashboard'), 'grid'],
            ['buy', 'buy.php', t('Buy bubbles'), 'plus-circle'],
            ['bubbles', 'bubbles.php', t('My bubbles'), 'bubbles'],
        ],
        t('Grow') => [
            ['advertise', 'advertise.php', t('Advertise'), 'megaphone'],
            ['referrals', 'referrals.php', t('Referrals'), 'users'],
        ],
        t('Wallet') => [
            ['deposit', 'deposit.php', t('Deposit'), 'download'],
            ['withdraw', 'withdraw.php', t('Withdraw'), 'upload'],
            ['transactions', 'transactions.php', t('History'), 'list'],
        ],
        t('Profile') => [
            ['account', 'account.php', t('Account'), 'user'],
        ],
    ];
}

function admin_nav(): array
{
    $counts = admin_pending_counts();
    return [
        t('Overview') => [
            ['admin', 'admin/index.php', t('Dashboard'), 'grid'],
        ],
        t('Money') => [
            ['admin-deposits', 'admin/deposits.php', t('Deposits'), 'download', $counts['deposits']],
            ['admin-withdrawals', 'admin/withdrawals.php', t('Withdrawals'), 'upload', $counts['withdrawals']],
            ['admin-methods', 'admin/methods.php', t('Payment methods'), 'card'],
            ['admin-ledger', 'admin/transactions.php', t('Ledger'), 'list'],
        ],
        t('Game') => [
            ['admin-pool', 'admin/bubbles.php', t('Pool & queue'), 'bubbles'],
            ['admin-ads', 'admin/ads.php', t('Ad campaigns'), 'megaphone', $counts['campaigns']],
            ['admin-users', 'admin/users.php', t('Members'), 'users'],
        ],
        t('System') => [
            ['admin-settings', 'admin/settings.php', t('Settings'), 'sliders'],
            ['admin-logs', 'admin/logs.php', t('Audit log'), 'file'],
        ],
    ];
}
