<?php
/**
 * SMTP client test against tests/fake_smtp_server.php: plain and STARTTLS
 * connections, AUTH PLAIN and LOGIN, dot-stuffing, and clear errors that
 * never contain the password.
 *
 *   BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/smtp_test.php
 */
$testConfigExtra = ['mail_verify_peer' => false]; // the fake server uses a self-signed certificate
require __DIR__ . '/bootstrap.php';

echo "== fake SMTP server\n";
fresh_install();
$dir = STORAGE_DIR . '/smtp';
mkdir($dir);
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$csr = $key !== false ? openssl_csr_new(['commonName' => '127.0.0.1'], $key) : false;
$cert = $csr !== false ? openssl_csr_sign($csr, null, $key, 1) : false;
if ($key === false || $cert === false || !openssl_x509_export($cert, $certPem) || !openssl_pkey_export($key, $keyPem)) {
    fwrite(STDERR, "OpenSSL could not create a test certificate.\n");
    exit(2);
}
file_put_contents($dir . '/server.pem', $certPem . $keyPem);
$probe = stream_socket_server('tcp://127.0.0.1:0');
if ($probe === false) {
    fwrite(STDERR, "Cannot reserve a local port.\n");
    exit(2);
}
$port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$server = proc_open([PHP_BINARY, __DIR__ . '/fake_smtp_server.php', (string) $port, $dir, $dir . '/server.pem'], [2 => ['file', $dir . '/server.log', 'a']], $pipes);
if ($server === false) {
    fwrite(STDERR, "Could not start the fake SMTP server.\n");
    exit(2);
}
register_shutdown_function(static function () use ($server): void {
    proc_terminate($server);
    proc_close($server);
});
for ($i = 0; $i < 50 && !is_file($dir . '/ready'); $i++) {
    usleep(100000);
}
check(is_file($dir . '/ready'), "fake server listening on port $port");

/** Messages received by the fake server, oldest first. */
$received = static function () use ($dir): array {
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    return array_map(static fn (string $f): array => (array) json_decode((string) file_get_contents($f), true), $files);
};
$settings = [
    'mail_transport' => 'smtp', 'mail_from' => 'noreply@bubbles.test', 'mail_from_name' => 'Bubbles',
    'smtp_host' => '127.0.0.1', 'smtp_port' => (string) $port, 'smtp_username' => 'mailer', 'smtp_password' => seal_secret('secret pass'),
];

echo "== plain connection, AUTH PLAIN\n";
settings_save($settings + ['smtp_encryption' => 'none']);
send_mail('member@example.com', 'Plain test', "Hello over plain SMTP.\n.line starting with a dot");
$mail = $received()[0] ?? [];
eq($mail['user'] ?? null, 'mailer', 'authenticated with the sealed password');
eq($mail['rcpt'] ?? null, ['member@example.com'], 'recipient');
eq($mail['from'] ?? null, 'noreply@bubbles.test', 'envelope sender');
check(!($mail['tls'] ?? true), 'no TLS when encryption is "none"');
check(str_contains((string) ($mail['data'] ?? ''), "Subject: Plain test\r\n"), 'headers delivered with CRLF line endings');
check(str_contains(mail_decode_parts((string) ($mail['data'] ?? '')), "\n.line starting with a dot"), 'body intact');

echo "== STARTTLS\n";
settings_save(['smtp_encryption' => 'tls']);
send_mail('member@example.com', 'TLS test', 'Hello over STARTTLS.');
$mail = $received()[1] ?? [];
check((bool) ($mail['tls'] ?? false), 'connection upgraded with STARTTLS');
eq($mail['user'] ?? null, 'mailer', 'authenticated after STARTTLS');

echo "== dot-stuffing\n";
smtp_send('noreply@bubbles.test', 'member@example.com', "Subject: dots\r\n\r\n.hidden\r\n..double\r\nnormal");
$mail = $received()[2] ?? [];
check(str_contains((string) ($mail['data'] ?? ''), "\r\n..hidden\r\n...double\r\n"), 'lines starting with a dot are doubled on the wire');

echo "== errors\n";
settings_save(['smtp_password' => seal_secret('wrong-password-123')]);
try {
    send_mail('member@example.com', 'Should fail', 'x');
    check(false, 'wrong password rejected');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'authentication failed') && str_contains($e->getMessage(), '535'), 'wrong password reported: ' . $e->getMessage());
    check(!str_contains($e->getMessage(), 'wrong-password') && !str_contains($e->getMessage(), base64_encode("\0mailer\0wrong-password-123")), 'the password never appears in the error');
}
check(!notify_email('member@example.com', null, static fn (): array => ['subject' => 'x', 'title' => 'x', 'lines' => ['x']]), 'notifications fail softly (logged, no exception)');
settings_save(['smtp_password' => seal_secret('secret pass'), 'smtp_port' => '1']);
try {
    send_mail('member@example.com', 'Should fail', 'x');
    check(false, 'unreachable server reported');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'Cannot connect'), 'unreachable server reported: ' . $e->getMessage());
}
eq(count($received()), 3, 'only the successful messages were delivered');

finish();
