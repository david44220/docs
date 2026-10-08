<?php
/** Streams an uploaded payment screenshot to admins only. */
require __DIR__ . '/../../app/bootstrap.php';

require_admin();

$deposit = row('SELECT proof_file FROM deposits WHERE id = ?', [query_int('id')]);
$path = proof_path($deposit['proof_file'] ?? null);
if ($path === null) {
    abort(404, t('This proof file does not exist.'));
}

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$extension = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="deposit-' . query_int('id') . '.' . $extension . '"');
header('Cache-Control: private, max-age=3600');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
readfile($path);
