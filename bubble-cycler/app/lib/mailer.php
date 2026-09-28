<?php
/**
 * Outgoing email without external libraries.
 *
 * Transports (Admin → Settings → Email):
 *   off  — never send (password reset falls back to "contact support")
 *   smtp — any SMTP server (STARTTLS on 587, SSL on 465, or plain), AUTH PLAIN/LOGIN
 *   mail — PHP's mail() / the server's sendmail
 *   log  — write messages to storage/logs/mail.log (development)
 *
 * Links inside emails are built from 'base_url' in config.php, never from the
 * request's Host header, so reset links cannot be poisoned.
 */
declare(strict_types=1);

function mail_enabled(): bool
{
    return in_array(setting('mail_transport', 'off'), ['smtp', 'mail', 'log'], true)
        && filter_var(setting('mail_from'), FILTER_VALIDATE_EMAIL) !== false
        && mail_base_url() !== '';
}

/** Public site URL used in emails (config 'base_url'), '' when not configured. */
function mail_base_url(): string
{
    $base = rtrim(trim((string) config('base_url', '')), '/');
    return valid_http_url($base) ? $base : '';
}

function mail_link(string $path, array $query = []): string
{
    $origin = (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', mail_base_url());
    return $origin . url($path, $query);
}

/** Remove anything that could break out of a header line. */
function mail_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $value));
}

/** RFC 2047 encoding (folded into 75-character words) when the value is not plain ASCII. */
function mail_encode_header(string $value): string
{
    $value = mail_header_value($value);
    return preg_match('/[^\x20-\x7E]/', $value) ? mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n") : $value;
}

/** Display name of an address: encoded when not ASCII, quoted when it has special characters. */
function mail_display_name(string $name): string
{
    $name = mail_header_value($name);
    if (preg_match('/[^\x20-\x7E]/', $name)) {
        return mail_encode_header($name);
    }
    return preg_match('/[()<>\[\]:;@\\\\,."]/', $name) ? '"' . addcslashes($name, '"\\') . '"' : $name;
}

/**
 * Build a multipart/alternative message.
 *
 * @return array{headers: array<string, string>, body: string}
 */
function mail_build(string $to, string $subject, string $text, string $html): array
{
    $from = mail_header_value(setting('mail_from'));
    $fromName = setting('mail_from_name') !== '' ? setting('mail_from_name') : site_name();
    $domain = substr((string) strrchr($from, '@'), 1) ?: 'localhost';
    $boundary = 'b1_' . bin2hex(random_bytes(12));

    $headers = [
        'Date'         => date(DATE_RFC2822),
        'From'         => mail_display_name($fromName) . ' <' . $from . '>',
        'To'           => '<' . mail_header_value($to) . '>',
        'Subject'      => mail_encode_header($subject),
        'Message-ID'   => '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
    ];
    $part = static fn (string $type, string $content): string => "--$boundary\r\n"
        . "Content-Type: $type; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($content), 76, "\r\n")) . "\r\n";
    $body = $part('text/plain', $text) . $part('text/html', $html) . "--$boundary--\r\n";

    return ['headers' => $headers, 'body' => $body];
}

/** Send an email or throw RuntimeException. */
function send_mail(string $to, string $subject, string $text, ?string $html = null): void
{
    if (!mail_enabled()) {
        throw new RuntimeException('Email is not configured (Admin → Settings → Email, and base_url in config.php).');
    }
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Invalid recipient address.');
    }
    $message = mail_build($to, $subject, $text, $html ?? nl2br(e($text)));
    $transport = setting('mail_transport');

    if ($transport === 'log') {
        $raw = implode("\r\n", array_map(static fn ($k, $v) => "$k: $v", array_keys($message['headers']), $message['headers']));
        $entry = "=== " . gmdate('c') . " ===\r\n" . $raw . "\r\n\r\n" . $message['body'] . "\r\n";
        if (@file_put_contents(STORAGE_DIR . '/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Could not write storage/logs/mail.log');
        }
        return;
    }

    if ($transport === 'mail') {
        $headers = $message['headers'];
        $subjectHeader = $headers['Subject'];
        unset($headers['To'], $headers['Subject']);
        $lines = implode("\r\n", array_map(static fn ($k, $v) => "$k: $v", array_keys($headers), $headers));
        $from = setting('mail_from');
        $ok = mail($to, $subjectHeader, $message['body'], $lines, '-f' . $from);
        if (!$ok) {
            throw new RuntimeException('PHP mail() refused the message.');
        }
        return;
    }

    $raw = implode("\r\n", array_map(static fn ($k, $v) => "$k: $v", array_keys($message['headers']), $message['headers']))
        . "\r\n\r\n" . $message['body'];
    smtp_send(setting('mail_from'), $to, $raw);
}

/** Minimal SMTP client (RFC 5321) with STARTTLS / implicit TLS and AUTH. */
function smtp_send(string $from, string $to, string $data): void
{
    $host = setting('smtp_host');
    $port = setting_int('smtp_port') ?: 587;
    $encryption = setting('smtp_encryption', 'tls');
    $username = setting('smtp_username');
    $password = open_secret(setting('smtp_password')) ?? '';
    if ($host === '') {
        throw new RuntimeException('SMTP host is not set.');
    }

    $verify = (bool) config('mail_verify_peer', true);
    $context = stream_context_create(['ssl' => [
        'verify_peer'       => $verify,
        'verify_peer_name'  => $verify,
        'allow_self_signed' => !$verify,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    ]]);
    $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
    $socket = @stream_socket_client("$scheme://$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if ($socket === false) {
        throw new RuntimeException("Cannot connect to SMTP server $host:$port ($errstr)");
    }
    stream_set_timeout($socket, 20);

    $read = static function () use ($socket): array {
        $response = '';
        while (($line = fgets($socket, 2048)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($response === '') {
            throw new RuntimeException('The SMTP server closed the connection.');
        }
        return [(int) substr($response, 0, 3), $response];
    };
    $send = static function (string $line, array $expect, string $label) use ($socket, $read): string {
        fwrite($socket, $line . "\r\n");
        [$code, $response] = $read();
        if (!in_array($code, $expect, true)) {
            // Never echo the command itself: it may contain credentials.
            throw new RuntimeException("SMTP $label failed: " . trim($response));
        }
        return $response;
    };

    try {
        [$code, $greeting] = $read();
        if ($code !== 220) {
            throw new RuntimeException('Unexpected SMTP greeting: ' . trim($greeting));
        }
        $ehloName = parse_url(mail_base_url(), PHP_URL_HOST) ?: 'localhost';
        $capabilities = $send("EHLO $ehloName", [250], 'EHLO');
        if ($encryption === 'tls') {
            $send('STARTTLS', [220], 'STARTTLS');
            $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            if (@stream_socket_enable_crypto($socket, true, $crypto) !== true) {
                throw new RuntimeException('STARTTLS handshake failed (check the certificate or use SSL on port 465).');
            }
            $capabilities = $send("EHLO $ehloName", [250], 'EHLO');
        }
        if ($username !== '') {
            if (preg_match('/AUTH[ =][^\r\n]*PLAIN/i', $capabilities)) {
                $send('AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), [235], 'authentication');
            } else {
                $send('AUTH LOGIN', [334], 'authentication');
                $send(base64_encode($username), [334], 'authentication');
                $send(base64_encode($password), [235], 'authentication');
            }
        }
        $send('MAIL FROM:<' . $from . '>', [250], 'MAIL FROM');
        $send('RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
        $send('DATA', [354], 'DATA');
        // Dot-stuffing: a line starting with "." gets an extra one.
        $payload = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $data));
        $send(str_replace("\n", "\r\n", (string) $payload) . "\r\n.", [250], 'message transfer');
        fwrite($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}

/**
 * Branded HTML email in the Cosmic Loop style (inline styles for mail
 * clients): deep-space background, square hairline panel, violet pill button.
 */
function mail_html(string $title, string $bodyHtml, ?string $buttonUrl = null, ?string $buttonLabel = null): string
{
    $brand = landing_brand(site_name());
    $font = "font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $button = $buttonUrl !== null
        ? '<p style="margin:30px 0 6px"><a href="' . e($buttonUrl) . '" style="display:inline-block;padding:15px 24px;border-radius:999px;'
          . 'background:#dfaaff;color:#081018;font-size:13px;font-weight:600;letter-spacing:.02em;text-decoration:none">'
          . e((string) $buttonLabel) . ' &#8599;</a></p>'
        : '';
    return '<!doctype html><html><body style="margin:0;padding:0;background:#07090f">'
        . '<div style="max-width:560px;margin:0 auto;padding:36px 20px;' . $font . ';color:#f7f5f0">'
        . '<p style="font-size:17px;margin:0 0 26px;color:#f7f5f0;letter-spacing:-.03em"><strong style="font-weight:700">' . e($brand['name'])
        . '</strong><span style="font-weight:400">' . e($brand['light']) . '</span></p>'
        . '<div style="background:#0e0c16;border:1px solid #332a40;padding:30px 28px">'
        . '<h1 style="font-size:26px;line-height:1.15;font-weight:600;letter-spacing:-.04em;margin:0 0 16px;color:#f7f5f0">' . e($title) . '</h1>'
        . '<div style="font-size:15px;line-height:1.65;color:#b8bdc9">' . $bodyHtml . '</div>' . $button
        . '</div><p style="font-size:12px;line-height:1.6;color:#858a9b;margin:20px 2px 0">' . e(setting('disclaimer')) . '</p>'
        . '</div></body></html>';
}

/**
 * Send a notification: never throws (failures are logged) so that business
 * actions are not undone because an email could not be delivered.
 */
function notify_email(string $to, string $subject, string $title, array $paragraphs, ?string $buttonPath = null, ?string $buttonLabel = null): bool
{
    if (!mail_enabled() || $to === '') {
        return false;
    }
    $buttonUrl = $buttonPath !== null ? mail_link($buttonPath) : null;
    $text = $title . "\n\n" . implode("\n\n", $paragraphs) . ($buttonUrl !== null ? "\n\n$buttonLabel: $buttonUrl" : '')
        . "\n\n— " . site_name();
    $html = mail_html($title, implode('', array_map(static fn (string $p): string => '<p style="margin:0 0 12px">' . e($p) . '</p>', $paragraphs)), $buttonUrl, $buttonLabel);
    try {
        send_mail($to, $subject, $text, $html);
        return true;
    } catch (Throwable $e) {
        log_error($e);
        return false;
    }
}

/** Email a member (respects the member notification setting). */
function notify_member(int $userId, string $subject, string $title, array $paragraphs, ?string $buttonPath = null, ?string $buttonLabel = null, bool $security = false): bool
{
    if (!$security && !setting_bool('notify_members')) {
        return false;
    }
    $email = (string) val('SELECT email FROM users WHERE id = ?', [$userId]);
    return notify_email($email, $subject, $title, $paragraphs, $buttonPath, $buttonLabel);
}

/** Email the operator (support email) about work waiting in the admin panel. */
function notify_admins(string $subject, string $title, array $paragraphs, string $buttonPath, string $buttonLabel): bool
{
    if (!setting_bool('notify_admins')) {
        return false;
    }
    return notify_email(setting('support_email'), $subject, $title, $paragraphs, $buttonPath, $buttonLabel);
}
