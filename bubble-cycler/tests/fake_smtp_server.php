<?php
/**
 * Minimal ESMTP server for tests/smtp_test.php: STARTTLS with a self-signed
 * certificate, AUTH PLAIN / LOGIN (user "mailer", password "secret pass"),
 * and every message saved as JSON in the output folder.
 *
 *   php tests/fake_smtp_server.php <port> <output dir> <pem file>
 */
declare(strict_types=1);

[, $port, $outDir, $pem] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $pem, 'allow_self_signed' => true, 'verify_peer' => false]]);
$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "Cannot listen on $port: $errstr\n");
    exit(1);
}
touch($outDir . '/ready');

while (($conn = @stream_socket_accept($server, 120)) !== false) {
    $say = static fn (string $line) => fwrite($conn, $line . "\r\n");
    $session = ['tls' => false, 'user' => null, 'from' => '', 'rcpt' => []];
    $say('220 fake.smtp.test ESMTP ready');
    while (($line = fgets($conn)) !== false) {
        $command = rtrim($line, "\r\n");
        $upper = strtoupper($command);
        if (str_starts_with($upper, 'EHLO ')) {
            $say('250-fake.smtp.test');
            if (!$session['tls']) {
                $say('250-STARTTLS');
            }
            $say('250-AUTH PLAIN LOGIN');
            $say('250 8BITMIME');
        } elseif ($upper === 'STARTTLS') {
            $say('220 Ready to start TLS');
            $session['tls'] = stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_SERVER) === true;
        } elseif (str_starts_with($upper, 'AUTH PLAIN ')) {
            $parts = explode("\0", (string) base64_decode(substr($command, 11)));
            $ok = ($parts[1] ?? '') === 'mailer' && ($parts[2] ?? '') === 'secret pass';
            $session['user'] = $ok ? 'mailer' : null;
            $say($ok ? '235 2.7.0 Authentication successful' : '535 5.7.8 Authentication credentials invalid');
        } elseif ($upper === 'AUTH LOGIN') {
            $say('334 VXNlcm5hbWU6');
            $user = base64_decode(rtrim((string) fgets($conn), "\r\n"));
            $say('334 UGFzc3dvcmQ6');
            $pass = base64_decode(rtrim((string) fgets($conn), "\r\n"));
            $ok = $user === 'mailer' && $pass === 'secret pass';
            $session['user'] = $ok ? 'mailer' : null;
            $say($ok ? '235 2.7.0 Authentication successful' : '535 5.7.8 Authentication credentials invalid');
        } elseif (str_starts_with($upper, 'MAIL FROM:')) {
            $session['from'] = trim(substr($command, 10), '<> ');
            $say('250 2.1.0 Sender ok');
        } elseif (str_starts_with($upper, 'RCPT TO:')) {
            $session['rcpt'][] = trim(substr($command, 8), '<> ');
            $say('250 2.1.5 Recipient ok');
        } elseif ($upper === 'DATA') {
            $say('354 End data with <CR><LF>.<CR><LF>');
            $data = '';
            while (($row = fgets($conn)) !== false && rtrim($row, "\r\n") !== '.') {
                $data .= $row;
            }
            file_put_contents($outDir . '/' . microtime(true) . '.json', json_encode($session + ['data' => $data]));
            $say('250 2.0.0 Queued');
        } elseif ($upper === 'QUIT') {
            $say('221 2.0.0 Bye');
            break;
        } else {
            $say('502 5.5.2 Command not recognised');
        }
    }
    fclose($conn);
}
