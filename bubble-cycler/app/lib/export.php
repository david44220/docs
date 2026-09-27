<?php
/**
 * CSV exports for the admin panel. Rows are streamed straight from the
 * database, and cells a spreadsheet would run as a formula are neutralised.
 */
declare(strict_types=1);

/** Make a value safe to open in Excel / LibreOffice / Google Sheets (formula injection). */
function csv_cell(mixed $value): string
{
    $text = (string) ($value ?? '');
    if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text) === 1 && preg_match('/^-\d+(\.\d+)?$/', $text) !== 1) {
        return "'" . $text;
    }
    return $text;
}

/** Money for spreadsheets: "1234.50", no symbol, no thousands separator. */
function csv_money(int|string|null $units): string
{
    return units_to_input($units);
}

/**
 * Send the result of $sql as a CSV download and stop.
 *
 * @param array<string, callable(array): (string|int|null)> $columns heading => cell value
 */
function csv_export(string $filename, string $sql, array $params, array $columns): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) . '-' . gmdate('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        throw new RuntimeException('Cannot open the output stream.');
    }
    fwrite($out, "\xEF\xBB\xBF"); // byte order mark: Excel then reads UTF-8 correctly
    fputcsv($out, array_keys($columns), ',', '"', '');

    // Unbuffered: rows go out as they are read, whatever the table size.
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $statement = q($sql, $params);
    while (($record = $statement->fetch()) !== false) {
        fputcsv($out, array_map(static fn (callable $cell): string => csv_cell($cell($record)), $columns), ',', '"', '');
    }
    $statement->closeCursor();
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    fclose($out);
    exit;
}

/** Export link for the current admin list, keeping its filters. */
function csv_link(string $page, array $query): string
{
    return '<a class="btn btn--secondary btn--sm" href="' . e(url($page, $query + ['export' => 'csv'])) . '" download>'
        . icon('download') . ' Export CSV</a>';
}
