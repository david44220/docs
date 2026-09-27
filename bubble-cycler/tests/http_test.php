<?php
/**
 * End-to-end HTTP test: starts PHP's built-in web server on the test
 * database and drives every page, form and permission check the way a
 * browser does — cookies, CSRF tokens, redirects, file uploads, the ad
 * countdown, two-factor codes, emails and CSV exports.
 *
 *   BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/http_test.php
 */
require __DIR__ . '/bootstrap.php';

if (!function_exists('curl_init')) {
    fwrite(STDERR, "The curl extension is required for the HTTP test.\n");
    exit(2);
}

/** A cookie-keeping HTTP client that follows redirects and remembers the CSRF token. */
final class Browser
{
    public int $status = 0;
    public string $body = '';
    public string $url = '';
    public string $location = '';
    public string $token = '';
    /** @var array<string, list<string>> */
    public array $headers = [];
    /** @var non-empty-string */
    private string $jar;

    public function __construct(private readonly string $base)
    {
        $jar = tempnam(sys_get_temp_dir(), 'jar');
        if ($jar === false) {
            throw new RuntimeException('Cannot create a cookie jar.');
        }
        $this->jar = $jar;
    }

    /** Path of the last URL (without the query string). */
    public function path(): string
    {
        return (string) parse_url($this->url, PHP_URL_PATH);
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function get(string $path, bool $follow = true): self
    {
        return $this->send('GET', $path, null, $follow);
    }

    public function post(string $path, array $fields, bool $follow = true): self
    {
        $fields += ['_token' => $this->token];
        return $this->send('POST', $path, $fields, $follow);
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)][0] ?? '';
    }

    public function has(string $text): bool
    {
        return str_contains($this->body, $text);
    }

    /** @param list<string> $extraHeaders */
    public function send(string $method, string $path, ?array $fields, bool $follow = true, array $extraHeaders = []): self
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . '/' . ltrim($path, '/');
        for ($hop = 0; $hop < 10; $hop++) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_COOKIEJAR      => $this->jar,
                CURLOPT_COOKIEFILE     => $this->jar,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => $extraHeaders,
            ]);
            if ($method === 'POST') {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $fields ?? []);
            }
            $raw = (string) curl_exec($curl);
            $this->status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            curl_close($curl);

            $this->url = $url;
            $this->body = substr($raw, $size);
            $this->headers = [];
            foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $this->headers[strtolower(trim($name))][] = trim($value);
                }
            }
            $this->location = $this->header('location');
            if (!$follow || $this->location === '' || !in_array($this->status, [301, 302, 303], true)) {
                break;
            }
            $url = str_starts_with($this->location, 'http') ? $this->location : $this->base . $this->location;
            if (!str_starts_with($url, $this->base)) {
                break; // never leave the test server
            }
            $method = 'GET';
            $fields = null;
        }
        if (preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})">/', $this->body, $m)) {
            $this->token = $m[1];
        }
        return $this;
    }

    public function login(string $login, string $password, string $next = ''): self
    {
        $this->get('login.php');
        return $this->post('login.php', ['login' => $login, 'password' => $password, 'next' => $next]);
    }

    public function logout(): self
    {
        return $this->post('logout.php', []);
    }

    /** Values of every field of the first form containing $marker (for forms with many fields). */
    public function formValues(string $marker): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $this->body);
        libxml_clear_errors();
        foreach ($dom->getElementsByTagName('form') as $form) {
            if (!str_contains((string) $dom->saveHTML($form), $marker)) {
                continue;
            }
            $values = [];
            foreach ((new DOMXPath($dom))->query('.//input|.//select|.//textarea', $form) ?: [] as $field) {
                if (!$field instanceof DOMElement || $field->getAttribute('name') === '' || $field->hasAttribute('disabled')) {
                    continue;
                }
                $name = $field->getAttribute('name');
                $type = strtolower($field->getAttribute('type'));
                if ($field->tagName === 'select') {
                    foreach ($field->getElementsByTagName('option') as $option) {
                        if ($option->hasAttribute('selected')) {
                            $values[$name] = $option->getAttribute('value');
                        }
                    }
                } elseif ($field->tagName === 'textarea') {
                    $values[$name] = $field->textContent;
                } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                    if ($field->hasAttribute('checked')) {
                        $values[$name] = $field->getAttribute('value') ?: 'on';
                    }
                } elseif ($type !== 'submit' && $type !== 'file') {
                    $values[$name] = $field->getAttribute('value');
                }
            }
            return $values;
        }
        throw new RuntimeException("No form containing $marker");
    }
}

function secret_from_page(string $html): string
{
    if (!preg_match('/secret=([A-Z2-7]{16,})/', $html, $m)) {
        throw new RuntimeException('No two-factor secret on the page.');
    }
    return $m[1];
}

function flash_seen(Browser $b, string $text): bool
{
    return str_contains($b->body, e($text));
}

/* -------------------------------------------------------------------------
 * Test server
 * ---------------------------------------------------------------------- */

echo "== install & web server\n";
$adminId = fresh_install(['max_registrations_per_ip' => '0', 'ad_seconds' => '2', 'min_withdrawal' => (string) u('1')]);
touch(STORAGE_DIR . '/installed.lock');
$probe = stream_socket_server('tcp://127.0.0.1:0');
if ($probe === false) {
    fwrite(STDERR, "Cannot reserve a local port.\n");
    exit(2);
}
$port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$serverLog = STORAGE_DIR . '/logs/server.log';
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', PUBLIC_DIR], [1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes);
if ($server === false) {
    fwrite(STDERR, "Could not start the PHP web server.\n");
    exit(2);
}
register_shutdown_function(static function () use ($server): void {
    proc_terminate($server);
    proc_close($server);
});
$base = "http://127.0.0.1:$port";
$anon = new Browser($base);
for ($i = 0; $i < 50 && $anon->get('health.php')->status !== 200; $i++) {
    usleep(100000);
}
eq($anon->status, 200, "web server answers on port $port");

/* -------------------------------------------------------------------------
 * Public site
 * ---------------------------------------------------------------------- */

echo "== public pages\n";
foreach (['index.php', 'terms.php', 'privacy.php', 'login.php', 'register.php'] as $page) {
    eq($anon->get($page)->status, 200, "$page loads");
}
$anon->get('index.php');
check($anon->has('TestBubbles'), 'home page shows the site name');
check(str_contains($anon->header('content-security-policy'), "default-src 'self'"), 'Content-Security-Policy sent');
eq($anon->header('x-frame-options'), 'DENY', 'clickjacking protection');
eq($anon->header('x-content-type-options'), 'nosniff', 'MIME sniffing disabled');
check($anon->header('referrer-policy') !== '', 'Referrer-Policy sent');
eq($anon->header('x-powered-by'), '', 'PHP version not advertised');
check($anon->has('property="og:image"') && $anon->has('og.jpg'), 'share image announced on public pages');
$anon->get('login.php');
$cookie = implode(' ', $anon->headers['set-cookie'] ?? []);
check($cookie === '' || (str_contains($cookie, 'HttpOnly') && str_contains($cookie, 'SameSite=Lax')), 'session cookie is HttpOnly + SameSite');
check($anon->has('noindex'), 'sign-in page hidden from search engines');
check(str_contains($anon->get('robots.txt')->body, 'Disallow: /'), 'robots.txt served');
$manifest = json_decode($anon->get('manifest.php')->body, true);
eq($manifest['name'] ?? null, 'TestBubbles', 'web app manifest');
foreach ($manifest['icons'] ?? [] as $icon) {
    $anon->get((string) $icon['src']);
    check($anon->status === 200 && str_starts_with($anon->header('content-type'), 'image/'), 'manifest icon ' . basename($anon->path()) . ' exists');
}
$anon->get('health.php');
eq(json_decode($anon->body, true)['ok'] ?? null, true, 'health check reports ok');
eq($anon->headers['set-cookie'] ?? [], [], 'health check opens no session');
$feed = json_decode($anon->get('api.php?a=pool')->body, true);
check(is_array($feed) && isset($feed['balance'], $feed['queue']), 'live pool feed');
eq($anon->get('api.php?a=nope')->status, 404, 'unknown feed action is a 404');
$anon->get('dashboard.php', false);
check($anon->status === 302 && str_contains($anon->location, 'login.php?next=%2Fdashboard.php'), 'member pages redirect to sign-in');
$anon->get('admin/index.php', false);
check($anon->status === 302 && str_contains($anon->location, 'login.php'), 'admin pages redirect to sign-in');
$anon->get('forgot.php');
check(str_ends_with($anon->path(), '/login.php'), 'password reset hidden while email is off');

/* -------------------------------------------------------------------------
 * Registration & sign-in
 * ---------------------------------------------------------------------- */

echo "== registration\n";
$reg = static fn (string $username, string $email, string $password, array $extra = []): array => $extra + [
    'username' => $username, 'email' => $email, 'password' => $password, 'password_confirm' => $password, 'terms' => '1', 'website' => '',
];
$alice = new Browser($base);
$alice->get('register.php');
$alice->post('register.php', $reg('alice', 'alice@example.com', TEST_PASSWORD, ['website' => 'http://spam.example']));
check($alice->has('Registration failed') && val("SELECT id FROM users WHERE username = 'alice'") === null, 'honeypot stops form-filling bots');
$alice->post('register.php', $reg('alice', 'alice@example.com', TEST_PASSWORD, ['_token' => 'forged']));
eq($alice->status, 419, 'forged CSRF token refused');
$alice->post('register.php', $reg('alice', 'alice@example.com', 'password123'));
check($alice->has('too easy to guess'), 'common password refused');
$alice->post('register.php', $reg('alice', 'alice@example.com', TEST_PASSWORD, ['terms' => '']));
check($alice->has('confirm you have read the terms'), 'terms must be accepted');
$alice->post('register.php', $reg('alice', 'alice@example.com', TEST_PASSWORD));
check(str_ends_with($alice->url, '/dashboard.php') && $alice->status === 200, 'registration signs the member in');
$aliceId = (int) val("SELECT id FROM users WHERE username = 'alice'");
eq(val('SELECT register_ip FROM users WHERE id = ?', [$aliceId]), '127.0.0.1', 'sign-up IP recorded');

$bob = new Browser($base);
$bob->get('register.php?ref=alice');
check($bob->has('invited by'), 'referral link recognised');
$bob->post('register.php', $reg('bob_1', 'bob@example.com', TEST_PASSWORD));
$bobId = (int) val("SELECT id FROM users WHERE username = 'bob_1'");
eq((int) val('SELECT referrer_id FROM users WHERE id = ?', [$bobId]), $aliceId, 'referrer stored');

echo "== sign-in\n";
$alice->logout();
check(str_ends_with($alice->path(), '/login.php'), 'sign-out lands on the sign-in page');
$alice->get('logout.php');
check(!str_ends_with($alice->url, '/logout.php'), 'GET does not sign anyone out');
$alice->login('alice', 'wrong-password');
check($alice->has('Incorrect username/email or password.'), 'wrong password refused');
$alice->login('alice', TEST_PASSWORD, '//evil.example/steal');
check(str_starts_with($alice->url, $base) && str_ends_with($alice->url, '/dashboard.php'), 'external "next" ignored (no open redirect)');
$alice->logout();
$alice->login('ALICE@example.com', TEST_PASSWORD, '/deposit.php');
check(str_ends_with($alice->url, '/deposit.php'), 'email sign-in returns to the requested page');

/* -------------------------------------------------------------------------
 * Admin two-factor
 * ---------------------------------------------------------------------- */

echo "== admin two-factor\n";
$admin = new Browser($base);
$admin->login('boss', 'supersecret1');
check(str_ends_with($admin->url, '/two-factor.php'), 'admin must set up two-factor first');
$secret = secret_from_page($admin->body);
check($admin->has('data-qr="otpauth://totp/'), 'QR code data on the setup page');
$admin->post('two-factor.php', ['action' => 'enable', 'code' => '000000']);
check($admin->has('That code is not valid'), 'wrong setup code refused');
$step = intdiv(time(), 30);
$admin->post('two-factor.php', ['action' => 'enable', 'code' => totp_code($secret, $step)]);
check(str_contains($admin->url, 'codes=1') && preg_match_all('/\b[A-Z2-7]{4}-[A-Z2-7]{4}\b/', $admin->body, $codes) >= 10, 'recovery codes shown once');
$recovery = $codes[0] ?? [];
check(!str_contains($admin->get('two-factor.php?codes=1')->body, $recovery[0] ?? '-'), 'recovery codes are not shown twice');
eq($admin->get('admin/index.php')->status, 200, 'admin panel opens after setup');
$admin->logout();
$admin->login('boss', 'supersecret1');
check(str_contains($admin->url, 'step=code'), 'sign-in asks for the code');
$admin->post('login.php?step=code', ['code' => totp_code($secret, $step)]);
check($admin->has('That code is not valid'), 'setup code cannot be replayed at sign-in');
$admin->post('login.php?step=code', ['code' => totp_code($secret, $step + 1)]);
check(str_ends_with($admin->url, '/admin/index.php'), 'next code signs the admin in');
$admin->logout();
$admin->login('boss', 'supersecret1');
$admin->post('login.php?step=code', ['code' => strtolower((string) ($recovery[0] ?? ''))]);
check(str_ends_with($admin->url, '/admin/index.php'), 'recovery code signs in');
eq(recovery_codes_left(member($adminId)), 9, 'recovery code consumed');

/* -------------------------------------------------------------------------
 * Payment methods, deposits
 * ---------------------------------------------------------------------- */

echo "== payment methods\n";
$method = static fn (string $type, array $fields): array => $fields + [
    'action' => 'save', 'type' => $type, 'id' => '0', 'currency' => 'USD', 'logo_url' => '', 'color' => '#7c3aed',
    'account_label' => '', 'account_value' => '', 'instructions' => '', 'max_amount' => '', 'fee_fixed' => '0',
    'fee_percent' => '0', 'require_proof' => '0', 'status' => 'active', 'sort_order' => '1',
];
$admin->get('admin/methods.php?type=deposit');
$admin->post('admin/methods.php', $method('deposit', [
    'name' => 'Test USDT', 'currency' => 'USDT', 'account_label' => 'Wallet address', 'account_value' => 'TTEST123WALLET',
    'instructions' => 'Send exactly the amount you declare.', 'min_amount' => '5', 'max_amount' => '1000', 'fee_percent' => '1', 'require_proof' => '1',
]));
$depositMethod = (int) val("SELECT id FROM payment_methods WHERE name = 'Test USDT' AND status = 'active'");
check($depositMethod > 0, 'admin creates an active deposit method');
$admin->post('admin/methods.php', $method('withdrawal', ['name' => 'PayPal', 'account_label' => 'PayPal email', 'min_amount' => '1', 'fee_fixed' => '0.10']));
$withdrawMethod = (int) val("SELECT id FROM payment_methods WHERE name = 'PayPal' AND type = 'withdrawal'");
check($withdrawMethod > 0, 'admin creates a withdrawal method');

echo "== deposits\n";
$png = STORAGE_DIR . '/proof.png';
$image = imagecreatetruecolor(64, 40);
imagepng($image, $png);
$fake = STORAGE_DIR . '/fake.png';
file_put_contents($fake, '<?php echo "not an image";');
$big = STORAGE_DIR . '/big.png';
file_put_contents($big, str_repeat('A', (int) (proof_max_bytes() * 1.2)));
$deposit = static fn (string $amount, string $reference, ?string $file): array => [
    'method_id' => (string) $depositMethod, 'amount' => $amount, 'reference' => $reference, 'sender' => 'my wallet',
] + ($file !== null ? ['proof' => new CURLFile($file, 'image/png', basename($file))] : []);
$alice->get('deposit.php?method=' . $depositMethod);
check($alice->has('TTEST123WALLET'), 'deposit page shows the payment address');
$alice->post('deposit.php', $deposit('50', 'TX-ALICE-0001', null));
check($alice->has('Please attach a screenshot'), 'proof required by the method');
$alice->post('deposit.php', $deposit('50', 'TX-ALICE-0001', $fake));
check($alice->has('Upload a JPG, PNG, WebP or GIF image.'), 'non-image upload refused');
$alice->post('deposit.php', $deposit('50', 'TX-ALICE-0001', $big));
check($alice->has('too large'), 'oversized upload refused');
$alice->post('deposit.php', $deposit('2', 'TX-ALICE-0001', $png));
check($alice->has('minimum'), 'amount below the method minimum refused');
$alice->post('deposit.php', $deposit('50', 'TX-ALICE-0001', $png));
$aliceDeposit = (int) val("SELECT id FROM deposits WHERE reference = 'TX-ALICE-0001'");
check($aliceDeposit > 0 && val('SELECT proof_file FROM deposits WHERE id = ?', [$aliceDeposit]) !== null, 'deposit with screenshot submitted');
eq((int) val("SELECT COUNT(*) FROM deposits WHERE reference = 'TX-ALICE-0001'"), 1, 'failed attempts left no rows');
$bob->post('deposit.php', $deposit('50', 'TX-ALICE-0001', $png));
check($bob->has('already been submitted'), 'duplicate transaction reference refused');
$bob->post('deposit.php', $deposit('20', '=HYPERLINK("http://evil.example","x")', $png));
$bobDeposit = (int) val("SELECT id FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$bobId]);
check($bobDeposit > 0, 'second member submits a deposit');

$alice->get('admin/proof.php?id=' . $aliceDeposit);
eq($alice->status, 403, 'members cannot open payment screenshots');
$admin->get('admin/proof.php?id=' . $aliceDeposit);
check($admin->status === 200 && $admin->header('content-type') === 'image/png', 'admin sees the screenshot');
$admin->get('admin/deposits.php?review=' . $aliceDeposit);
check($admin->has('TX-ALICE-0001'), 'review panel shows the reference');
$admin->post('admin/deposits.php', ['id' => (string) $aliceDeposit, 'action' => 'approve', 'credit' => '', 'note' => 'Received']);
eq(val('SELECT status FROM deposits WHERE id = ?', [$aliceDeposit]), 'approved', 'admin approves the deposit');
eq((int) member($aliceId)['purchase_balance'], u('49.50'), 'member credited amount minus the 1% fee');
$admin->post('admin/deposits.php', ['id' => (string) $aliceDeposit, 'action' => 'approve', 'credit' => '', 'note' => '']);
eq((int) member($aliceId)['purchase_balance'], u('49.50'), 'double approval has no effect');
$admin->post('admin/deposits.php', ['id' => (string) $bobDeposit, 'action' => 'reject', 'note' => 'Not received']);
eq(val('SELECT status FROM deposits WHERE id = ?', [$bobDeposit]), 'rejected', 'admin rejects a deposit');
$alice->get('deposit.php');
check($alice->has('TX-ALICE-0001'), 'member sees the deposit in the history');

/* -------------------------------------------------------------------------
 * Buying bubbles behind the ad gate
 * ---------------------------------------------------------------------- */

echo "== buying bubbles\n";
$form = static function (Browser $b): array {
    preg_match('/name="ad_token" value="([a-f0-9]*)"/', $b->body, $t);
    preg_match('/name="nonce" value="([a-f0-9]{32})"/', $b->body, $n);
    return ['ad_token' => $t[1] ?? '', 'nonce' => $n[1] ?? ''];
};
$alice->get('buy.php');
$fields = $form($alice);
check(strlen($fields['ad_token']) === 32 && $alice->has('data-ad-gate'), 'buy page shows the sponsored message');
$alice->post('buy.php', $fields + ['quantity' => '2', 'wallet' => 'purchase']);
check($alice->has('Please keep watching'), 'buying before the countdown ends is refused');
eq((int) val('SELECT COUNT(*) FROM bubbles WHERE user_id = ?', [$aliceId]), 0, 'nothing bought');
$alice->send('POST', 'ad.php?a=complete', ['token' => $fields['ad_token']], false, ['X-CSRF-Token: ' . $alice->token]);
eq($alice->status, 409, 'countdown cannot be skipped by calling the API early');
sleep(3);
$alice->send('POST', 'ad.php?a=complete', ['token' => $fields['ad_token']], false, ['X-CSRF-Token: ' . $alice->token]);
eq(json_decode($alice->body, true)['ok'] ?? null, true, 'countdown completed');
$alice->get('buy.php');
$fields = $form($alice);
$alice->post('buy.php', $fields + ['quantity' => '2', 'wallet' => 'purchase']);
check(str_contains($alice->url, 'bubbles.php?new='), 'purchase goes through after the ad');
eq((int) val('SELECT COUNT(*) FROM bubbles WHERE user_id = ?', [$aliceId]), 2, 'two bubbles in the queue');
eq((int) member($aliceId)['ad_credits'], 2 * setting_int('ad_credits_per_bubble'), 'ad credits included');
$alice->post('buy.php', $fields + ['quantity' => '2', 'wallet' => 'purchase']);
check($alice->has('already submitted'), 'resubmitted purchase form refused');
eq((int) val('SELECT COUNT(*) FROM bubbles WHERE user_id = ?', [$aliceId]), 2, 'no double purchase');
$alice->get('buy.php');
$alice->post('buy.php', ['ad_token' => $fields['ad_token'], 'quantity' => '1', 'wallet' => 'purchase'] + $form($alice));
check($alice->has('already unlocked a purchase'), 'one ad view unlocks one purchase');
// 2 x 0.80 reached #1's 1.60 target at once: #1 expired, #2 waits.
check($alice->get('bubbles.php')->has('#2') && !$alice->has('>#1<'), 'my bubbles lists the waiting bubble');
check($alice->get('bubbles.php?tab=expired')->has('#1'), 'expired tab lists the paid bubble');
foreach (['dashboard.php', 'transactions.php', 'referrals.php', 'account.php', 'advertise.php', 'withdraw.php', 'bubbles.php?tab=expired'] as $page) {
    eq($alice->get($page)->status, 200, "$page loads for members");
}

/* -------------------------------------------------------------------------
 * Pool top-up, withdrawals
 * ---------------------------------------------------------------------- */

echo "== pool & withdrawals\n";
$admin->post('admin/bubbles.php', ['action' => 'inject', 'amount' => '10', 'note' => 'launch boost']);
eq((int) val("SELECT COUNT(*) FROM bubbles WHERE user_id = ? AND status = 'expired'", [$aliceId]), 2, 'admin top-up expires the queued bubbles');
eq((int) member($aliceId)['cash_balance'], u('3.20') + u('0'), 'payouts reach the cash balance');
$alice->get('withdraw.php');
$alice->post('withdraw.php', ['method_id' => (string) $withdrawMethod, 'amount' => '0.50', 'account' => 'alice@paypal.test']);
check($alice->has('minimum withdrawal'), 'minimum withdrawal enforced');
$alice->post('withdraw.php', ['method_id' => (string) $withdrawMethod, 'amount' => '2', 'account' => 'alice@paypal.test']);
$w1 = (int) val("SELECT id FROM withdrawals WHERE user_id = ? AND status = 'pending'", [$aliceId]);
check($w1 > 0 && (int) member($aliceId)['cash_balance'] === u('1.20'), 'withdrawal requested and held');
$bob->post('withdraw.php', ['action' => 'cancel', 'id' => (string) $w1]);
eq(val('SELECT status FROM withdrawals WHERE id = ?', [$w1]), 'pending', "members cannot cancel someone else's withdrawal");
$admin->post('admin/withdrawals.php', ['id' => (string) $w1, 'action' => 'reject', 'note' => '']);
eq(val('SELECT status FROM withdrawals WHERE id = ?', [$w1]), 'pending', 'rejection needs a reason');
$admin->post('admin/withdrawals.php', ['id' => (string) $w1, 'action' => 'paid', 'txid' => 'PP-0001', 'note' => '']);
eq(val('SELECT status FROM withdrawals WHERE id = ?', [$w1]), 'paid', 'admin marks the withdrawal paid');
$alice->post('withdraw.php', ['method_id' => (string) $withdrawMethod, 'amount' => '1', 'account' => 'alice@paypal.test']);
$w2 = (int) val("SELECT id FROM withdrawals WHERE user_id = ? AND status = 'pending'", [$aliceId]);
$alice->post('withdraw.php', ['action' => 'cancel', 'id' => (string) $w2]);
check(val('SELECT status FROM withdrawals WHERE id = ?', [$w2]) === 'cancelled' && (int) member($aliceId)['cash_balance'] === u('1.20'), 'member cancels and is refunded');

/* -------------------------------------------------------------------------
 * Advertising
 * ---------------------------------------------------------------------- */

echo "== advertising\n";
$campaign = ['action' => 'create', 'title' => 'Alice soap shop', 'description' => 'Handmade soap', 'url' => 'https://alice.example/shop', 'image_url' => '', 'cta_label' => 'Visit', 'credits' => '50'];
$alice->post('advertise.php', ['url' => 'javascript:alert(1)'] + $campaign);
check($alice->has('valid destination URL'), 'javascript: URLs refused');
$alice->post('advertise.php', $campaign);
$campaignId = (int) val('SELECT id FROM ad_campaigns WHERE user_id = ?', [$aliceId]);
eq(val('SELECT status FROM ad_campaigns WHERE id = ?', [$campaignId]), 'pending', 'campaign waits for approval');
$admin->post('admin/ads.php', ['action' => 'approve', 'id' => (string) $campaignId, 'note' => '']);
eq(val('SELECT status FROM ad_campaigns WHERE id = ?', [$campaignId]), 'active', 'admin approves the campaign');
$admin->get('admin/user.php?id=' . $bobId);
$admin->post('admin/user.php', ['id' => (string) $bobId, 'action' => 'deposit', 'amount' => '20', 'note' => 'promo']);
eq((int) member($bobId)['purchase_balance'], u('20'), 'admin adds a manual deposit');
$bob->get('buy.php');
check($bob->has('Alice soap shop'), "members see other members' campaigns");
$bobFields = $form($bob);
$bob->get('ad.php?a=click&t=' . $bobFields['ad_token'], false);
eq($bob->location, 'https://alice.example/shop', 'ad click goes to the advertiser');
eq((int) val('SELECT clicks FROM ad_campaigns WHERE id = ?', [$campaignId]), 1, 'click counted');
$alice->get('buy.php');
check(!$alice->has('Alice soap shop'), 'advertisers never see their own campaign');
$alice->post('advertise.php', ['action' => 'toggle', 'id' => (string) $campaignId]);
eq(val('SELECT status FROM ad_campaigns WHERE id = ?', [$campaignId]), 'paused', 'advertiser pauses the campaign');
$bob->post('advertise.php', ['action' => 'delete', 'id' => (string) $campaignId]);
check((int) val('SELECT COUNT(*) FROM ad_campaigns WHERE id = ?', [$campaignId]) === 1, "members cannot delete someone else's campaign");

/* -------------------------------------------------------------------------
 * Account security
 * ---------------------------------------------------------------------- */

echo "== account security\n";
$alice->get('account.php');
$alice->post('account.php', ['action' => 'password', 'current_password' => 'nope', 'new_password' => 'Fresh-bubbly-26', 'new_password_confirm' => 'Fresh-bubbly-26']);
check($alice->has('current password is incorrect'), 'password change needs the current password');
$phone = new Browser($base);
$phone->login('alice', TEST_PASSWORD);
check(str_ends_with($phone->url, '/dashboard.php'), 'second device signed in');
$alice->post('account.php', ['action' => 'password', 'current_password' => TEST_PASSWORD, 'new_password' => 'Fresh-bubbly-26', 'new_password_confirm' => 'Fresh-bubbly-26']);
check(flash_seen($alice, 'Password changed. Other devices have been signed out.'), 'password changed');
$phone->get('dashboard.php');
check(str_contains($phone->url, 'login.php'), 'other devices are signed out');
eq($alice->get('dashboard.php')->status, 200, 'current device stays signed in');
$alice->get('two-factor.php');
$aliceSecret = secret_from_page($alice->body);
$alice->post('two-factor.php', ['action' => 'enable', 'code' => totp_code($aliceSecret, intdiv(time(), 30))]);
check(user_has_2fa(member($aliceId)), 'member turns on two-factor');
$admin->post('admin/user.php', ['id' => (string) $aliceId, 'action' => '2fa']);
check(!user_has_2fa(member($aliceId)), 'admin resets a member’s two-factor');
$admin->post('admin/user.php', ['id' => (string) $aliceId, 'action' => 'email', 'email' => 'alice.new@example.com']);
eq(member($aliceId)['email'], 'alice.new@example.com', 'admin changes a member’s email');

/* -------------------------------------------------------------------------
 * Email & password reset
 * ---------------------------------------------------------------------- */

echo "== email & password reset\n";
$admin->get('admin/settings.php');
$settings = $admin->formValues('name="site_name"');
$admin->post('admin/settings.php', ['mail_transport' => 'log', 'mail_from' => 'noreply@bubbles.test', 'action' => 'test_email'] + $settings);
check(setting('mail_transport') === 'log' || settings_all(true)['mail_transport'] === 'log', 'admin switches email on');
check(str_contains(mail_log_text(), 'can send emails'), 'test email sent');
$admin->post('admin/settings.php', ['bubble_price' => 'abc'] + $settings);
check($admin->has('must be an amount'), 'invalid settings refused');
$anon = new Browser($base);
$anon->get('forgot.php');
eq($anon->status, 200, 'reset form available once email works');
$anon->post('forgot.php', ['email' => 'bob@example.com']);
check($anon->has('If an account uses'), 'same answer whether or not the account exists');
check((bool) preg_match('~reset\.php\?token=([a-f0-9]{64})~', mail_log_text(), $m), 'reset link emailed');
$anon->get('reset.php?token=' . ($m[1] ?? ''));
eq($anon->status, 200, 'reset page opens');
$anon->post('reset.php', ['token' => $m[1] ?? '', 'password' => 'Reset-bubbly-26', 'password_confirm' => 'Reset-bubbly-26']);
check(str_contains($anon->url, 'login.php'), 'new password saved');
$anon->login('bob_1', 'Reset-bubbly-26');
check(str_ends_with($anon->url, '/dashboard.php'), 'member signs in with the new password');
$anon->logout();
$anon->get('reset.php?token=' . ($m[1] ?? ''));
check($anon->has('invalid or has expired') || !$anon->has('name="password"'), 'reset link works only once');

/* -------------------------------------------------------------------------
 * Admin panel
 * ---------------------------------------------------------------------- */

echo "== admin pages\n";
foreach ([
    'admin/index.php', 'admin/deposits.php?status=all', 'admin/withdrawals.php?status=all', 'admin/methods.php?type=deposit',
    'admin/methods.php?type=withdrawal', 'admin/transactions.php', 'admin/bubbles.php', 'admin/bubbles.php?tab=expired',
    'admin/ads.php?status=all', 'admin/ads.php?status=house', 'admin/users.php', 'admin/users.php?filter=admin',
    'admin/user.php?id=' . $aliceId, 'admin/settings.php', 'admin/logs.php',
] as $page) {
    eq($admin->get($page)->status, 200, "$page loads");
}
$admin->get('admin/users.php?q=127.0.0.1');
check($admin->has('alice') && $admin->has('bob_1'), 'members can be searched by IP');
$admin->get('admin/user.php?id=' . $bobId);
check($admin->has('Same IP address as the referrer'), 'self-referral warning on the member page');

echo "== CSV exports\n";
foreach (['admin/deposits.php?status=all', 'admin/withdrawals.php?status=all', 'admin/transactions.php', 'admin/users.php'] as $page) {
    $admin->get($page . (str_contains($page, '?') ? '&' : '?') . 'export=csv');
    check($admin->status === 200 && str_starts_with($admin->header('content-type'), 'text/csv') && str_starts_with($admin->body, "\xEF\xBB\xBF"), "$page exports CSV");
    if (str_contains($page, 'deposits')) {
        check(str_contains($admin->body, "\"'=HYPERLINK("), 'formula in a reference is neutralised');
        check(str_contains($admin->body, 'TX-ALICE-0001,"my wallet"') || str_contains($admin->body, 'TX-ALICE-0001,my wallet'), 'deposit rows exported');
    }
}
check((int) val("SELECT COUNT(*) FROM admin_logs WHERE action LIKE 'export.%'") === 4, 'exports are audit-logged');
$alice->get('admin/deposits.php?export=csv');
eq($alice->status, 403, 'members cannot export');

/* -------------------------------------------------------------------------
 * Permissions & site switches
 * ---------------------------------------------------------------------- */

echo "== permissions & site switches\n";
eq($alice->get('admin/index.php')->status, 403, 'members are kept out of the admin panel');
$admin->post('admin/user.php', ['id' => (string) $aliceId, 'action' => 'role', 'role' => 'admin', '_token' => 'forged']);
eq($admin->status, 419, 'admin actions need a valid CSRF token');
eq(member($aliceId)['role'], 'user', 'forged request changed nothing');
$admin->post('admin/user.php', ['id' => (string) $bobId, 'action' => 'status', 'status' => 'banned']);
$bob->get('dashboard.php');
check(str_contains($bob->url, 'login.php'), 'banned member is signed out');
$bob->login('bob_1', 'Reset-bubbly-26');
check($bob->has('suspended'), 'banned member cannot sign in');
settings_save(['maintenance_mode' => '1']);
eq((new Browser($base))->get('index.php')->status, 503, 'maintenance page for visitors');
eq($admin->get('admin/index.php')->status, 200, 'admins keep access during maintenance');
eq((new Browser($base))->get('health.php')->status, 200, 'health check stays up during maintenance');
settings_save(['maintenance_mode' => '0', 'max_registrations_per_ip' => '3']);
$carol = new Browser($base);
$carol->get('register.php');
$carol->post('register.php', $reg('carol', 'carol@example.com', TEST_PASSWORD));
check($carol->has('Too many accounts'), 'sign-ups per IP are limited');
settings_save(['registration_open' => '0']);
$carol->get('register.php');
check($carol->has('Registrations are closed'), 'registrations can be closed');

check((string) file_get_contents($serverLog) === '' || !preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/', (string) file_get_contents($serverLog)), 'no PHP warnings in the server log');
check(!is_file(STORAGE_DIR . '/logs/app.log') || !str_contains((string) file_get_contents(STORAGE_DIR . '/logs/app.log'), 'Error'), 'no application errors logged');
if (getenv('KEEP_SERVER_LOG')) { copy($serverLog, (string) getenv('KEEP_SERVER_LOG')); }
check_invariants('after the HTTP run');
finish();
