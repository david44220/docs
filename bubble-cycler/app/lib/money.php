<?php
/**
 * Money helpers. Amounts are integers in micro-units (1.00 = 1 000 000) so
 * no float ever touches a balance.
 */
declare(strict_types=1);

const MONEY_SCALE = 1000000;

/**
 * Format micro-units for display in the current language:
 * 1600000 → "$1.60" (English) / "1,60 $" (French); 12500 → "$0.0125".
 */
function money(int|string|null $units, bool $symbol = true): string
{
    $units = (int) $units;
    $negative = $units < 0;
    $scaled = intdiv(abs($units) + 50, 100); // round half up to 4 decimals
    $whole = intdiv($scaled, 10000);
    $fraction = rtrim(str_pad((string) ($scaled % 10000), 4, '0', STR_PAD_LEFT), '0');
    $fraction = str_pad($fraction, 2, '0');

    if (lang() === 'fr') {
        $text = number_format($whole, 0, ',', "\u{202F}") . ',' . $fraction . ($symbol ? "\u{00A0}" . setting('currency_symbol', '$') : '');
    } else {
        $text = ($symbol ? setting('currency_symbol', '$') : '') . number_format($whole) . '.' . $fraction;
    }
    return ($negative ? '−' : '') . $text;
}

/** Signed display for ledgers: "+$1.60" / "−$1.00". */
function money_signed(int|string $units): string
{
    $units = (int) $units;
    return ($units > 0 ? '+' : '') . money($units);
}

/**
 * Parse a user-typed amount ("1", "0.80", "0,80", "1,250.50") into
 * micro-units. Returns null when the text is not a valid positive amount.
 */
function to_units(string|int|null $value): ?int
{
    $text = trim((string) $value);
    $text = str_replace([' ', "\u{00A0}", "\u{202F}", '$', '€', '£'], '', $text);
    if (str_contains($text, ',') && !str_contains($text, '.')) {
        $text = str_replace(',', '.', $text);
    } else {
        $text = str_replace(',', '', $text);
    }
    if (!preg_match('/^(\d{0,12})(?:\.(\d{0,6}))?$/', $text, $m)) {
        return null;
    }
    $whole = $m[1];
    $fraction = $m[2] ?? '';
    if ($whole === '' && $fraction === '') {
        return null;
    }
    return (int) ($whole === '' ? 0 : $whole) * MONEY_SCALE + (int) str_pad($fraction, 6, '0');
}

/** Like to_units(), but only whole cents are accepted (payments, adjustments). */
function to_payment_units(string|int|null $value): ?int
{
    $units = to_units($value);
    return $units !== null && $units % 10000 === 0 ? $units : null;
}

/** Micro-units → plain decimal for form fields: 800000 → "0.80". */
function units_to_input(int|string|null $units): string
{
    $units = (int) $units;
    $whole = intdiv(abs($units), MONEY_SCALE);
    $fraction = rtrim(str_pad((string) (abs($units) % MONEY_SCALE), 6, '0', STR_PAD_LEFT), '0');
    return ($units < 0 ? '-' : '') . $whole . '.' . str_pad($fraction, 2, '0');
}

/** "2.5" → 250 basis points (0–100 %). */
function to_bp(string $value): ?int
{
    $text = str_replace([',', '%', ' '], ['.', '', ''], trim($value));
    if ($text === '') {
        return 0;
    }
    if (!preg_match('/^(\d{1,3})(?:\.(\d{0,2}))?$/', $text, $m)) {
        return null;
    }
    $bp = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    return $bp <= 10000 ? $bp : null;
}

function bp_to_input(int|string $bp): string
{
    $bp = (int) $bp;
    $fraction = rtrim(str_pad((string) ($bp % 100), 2, '0', STR_PAD_LEFT), '0');
    return intdiv($bp, 100) . ($fraction !== '' ? '.' . $fraction : '');
}

/** Fee for a payment method: fixed part + percentage rounded half up to the cent. */
function method_fee(array $method, int $amount): int
{
    $cents = intdiv($amount, 10000);
    $percent = intdiv($cents * (int) $method['fee_percent_bp'] + 5000, 10000) * 10000;
    return (int) $method['fee_fixed'] + $percent;
}

function method_fee_label(array $method): string
{
    $parts = [];
    if ((int) $method['fee_percent_bp'] > 0) {
        $parts[] = percent((int) $method['fee_percent_bp'] / 100, 2);
    }
    if ((int) $method['fee_fixed'] > 0) {
        $parts[] = money($method['fee_fixed']);
    }
    return $parts ? implode(' + ', $parts) : t('No fee');
}

/** Share as a percentage string in the current language: pct(1, 3) → "33.3%" / "33,3 %". */
function pct(int|float $part, int|float $whole, int $decimals = 1): string
{
    return percent($whole <= 0 ? 0 : $part / $whole * 100, $decimals);
}
