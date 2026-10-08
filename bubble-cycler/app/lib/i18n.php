<?php
/**
 * Languages: English (the default) and French.
 *
 * Every text shown to people goes through t() with its English wording as the
 * key; app/lang/fr.php maps each English text to its French translation, and
 * tools/i18n-check.php makes sure none is missing. Placeholders look like
 * {name}. Numbers, money and dates follow the language too (num(), money(),
 * fmt_date()).
 *
 * The language of a request, first match wins:
 *   1. ?lang=en|fr — remembered in a cookie and on the member's account;
 *   2. the cookie;
 *   3. the signed-in member's saved language;
 *   4. French for visitors from France, when the server or CDN tells the
 *      visitor's country (Cloudflare, CloudFront, App Engine, GeoIP module);
 *   5. the browser's preferred languages (Accept-Language);
 *   6. English.
 */
declare(strict_types=1);

const LANGUAGES = ['en' => 'English', 'fr' => 'Français'];
const LANG_COOKIE = 'bubble_lang';
/** Country codes that mean "from France": metropolitan France, overseas France and Monaco. */
const FRENCH_COUNTRIES = ['FR', 'GP', 'MQ', 'GF', 'RE', 'YT', 'PM', 'BL', 'MF', 'NC', 'PF', 'WF', 'MC'];
/** Request headers / server variables that carry the visitor's country. */
const COUNTRY_HEADERS = ['HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE', 'HTTP_X_GEOIP_COUNTRY'];

/** Language of the current request (or of the email being written). */
function lang(): string
{
    return $GLOBALS['__lang'] ?? 'en';
}

function lang_set(?string $lang): void
{
    $GLOBALS['__lang'] = $lang !== null && isset(LANGUAGES[$lang]) ? $lang : 'en';
}

/**
 * Run $fn in another language, e.g. to write an email in the recipient's
 * language while an admin who reads French approves their deposit.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function with_lang(?string $lang, callable $fn): mixed
{
    $previous = lang();
    lang_set($lang);
    try {
        return $fn();
    } finally {
        lang_set($previous);
    }
}

/** The other language, for the language switch. */
function lang_other(): string
{
    return lang() === 'fr' ? 'en' : 'fr';
}

/** French when a trusted country header says the visitor is in France; null when unknown or elsewhere. */
function lang_from_country(array $server): ?string
{
    foreach (COUNTRY_HEADERS as $key) {
        $country = strtoupper(trim((string) ($server[$key] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $country)) {
            return in_array($country, FRENCH_COUNTRIES, true) ? 'fr' : null;
        }
    }
    return null;
}

/** The supported language the browser prefers ("de-DE,de;q=0.9,fr;q=0.8" → fr), or null. */
function lang_from_browser(string $acceptLanguage): ?string
{
    $best = null;
    foreach (explode(',', $acceptLanguage) as $i => $part) {
        $bits = explode(';', trim($part));
        $code = strtolower(substr(trim($bits[0]), 0, 2));
        $quality = 1.0;
        foreach (array_slice($bits, 1) as $param) {
            if (preg_match('/^\s*q\s*=\s*([0-9.]+)/', $param, $m)) {
                $quality = (float) $m[1];
            }
        }
        if ($quality > 0 && isset(LANGUAGES[$code]) && ($best === null || $quality > $best[0])) {
            $best = [$quality, $code];
        }
    }
    return $best[1] ?? null;
}

/** Decide the language of this request (see the order at the top of this file). */
function lang_detect(?array $user): string
{
    $asked = $_GET['lang'] ?? null;
    if (is_string($asked) && isset(LANGUAGES[$asked])) {
        return $asked;
    }
    $cookie = $_COOKIE[LANG_COOKIE] ?? null;
    if (is_string($cookie) && isset(LANGUAGES[$cookie])) {
        return $cookie;
    }
    $saved = $user['lang'] ?? null;
    if (is_string($saved) && isset(LANGUAGES[$saved])) {
        return $saved;
    }
    return lang_from_country($_SERVER)
        ?? lang_from_browser((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))
        ?? 'en';
}

/** Pick the request language, remember an explicit choice, and tell caches the page depends on it. */
function lang_boot(?array $user): void
{
    lang_set(lang_detect($user));
    $asked = $_GET['lang'] ?? null;
    if (is_string($asked) && isset(LANGUAGES[$asked])) {
        if (!headers_sent()) {
            setcookie(LANG_COOKIE, $asked, [
                'expires'  => time() + 365 * 86400,
                'path'     => base_path() === '' ? '/' : base_path() . '/',
                'secure'   => is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if ($user !== null && ($user['lang'] ?? null) !== $asked) {
            q('UPDATE users SET lang = ? WHERE id = ?', [$asked, $user['id']]);
        }
    }
    if (!headers_sent()) {
        header('Content-Language: ' . lang());
        header('Vary: Accept-Language, Cookie', false);
    }
}

/** Translations of the current language (English is the source: no file). */
function lang_table(string $lang): array
{
    static $tables = [];
    if ($lang === 'en') {
        return [];
    }
    return $tables[$lang] ??= (array) require APP_DIR . '/lang/' . $lang . '.php';
}

/**
 * Translate an English text and fill its {placeholders}.
 *
 * @param array<string, string|int|float> $vars
 */
function t(string $text, array $vars = []): string
{
    $translated = lang_table(lang())[$text] ?? $text;
    if ($vars === []) {
        return $translated;
    }
    $pairs = [];
    foreach ($vars as $key => $value) {
        $pairs['{' . $key . '}'] = (string) $value;
    }
    return strtr($translated, $pairs);
}

/**
 * A text whose translation depends on what it describes (French agrees in
 * gender): tc('campaign', 'Rejected') looks up "campaign|Rejected" first,
 * then falls back to t('Rejected').
 *
 * @param array<string, string|int|float> $vars
 */
function tc(string $context, string $text, array $vars = []): string
{
    $key = $context . '|' . $text;
    $table = lang_table(lang());
    return isset($table[$key]) ? t($key, $vars) : t($text, $vars);
}

/**
 * Singular / plural: tn('{n} bubble', '{n} bubbles', 3). {n} is the formatted
 * number. French uses the singular for 0 and 1, English only for 1.
 *
 * @param array<string, string|int|float> $vars
 */
function tn(string $one, string $many, int $n, array $vars = []): string
{
    $singular = lang() === 'fr' ? abs($n) <= 1 : abs($n) === 1;
    $vars += ['n' => num($n)];
    if (!$singular && $one === $many) {
        // Same words in English ("3 expired"), not always in French: "plural|…" holds the plural.
        return tc('plural', $many, $vars);
    }
    return t($singular ? $one : $many, $vars);
}

/** A number in the current language: 1,234.5 / 1 234,5 (French uses a narrow no-break space). */
function num(int|float|string|null $number, int $decimals = 0): string
{
    $number = (float) $number;
    return lang() === 'fr'
        ? number_format($number, $decimals, ',', "\u{202F}")
        : number_format($number, $decimals);
}

/** A number with at most $decimals decimals, trailing zeros dropped: 2.50 → "2.5" / "2,5". */
function decimal_trim(int|float $value, int $decimals): string
{
    $text = num($value, $decimals);
    $mark = lang() === 'fr' ? ',' : '.';
    return str_contains($text, $mark) ? rtrim(rtrim($text, '0'), $mark) : $text;
}

/** A percentage: "12.5%" / "12,5 %" (trailing zero decimals dropped). */
function percent(int|float $value, int $decimals = 0): string
{
    $text = decimal_trim($value, $decimals);
    return lang() === 'fr' ? $text . "\u{202F}%" : $text . '%';
}

/** French month and day names for dates formatted with PHP's English names. */
const FR_DATE_WORDS = [
    'January' => 'janvier', 'February' => 'février', 'March' => 'mars', 'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
    'July' => 'juillet', 'August' => 'août', 'September' => 'septembre', 'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre',
    'Jan' => 'janv.', 'Feb' => 'févr.', 'Mar' => 'mars', 'Apr' => 'avr.', 'Jun' => 'juin', 'Jul' => 'juil.', 'Aug' => 'août',
    'Sep' => 'sept.', 'Oct' => 'oct.', 'Nov' => 'nov.', 'Dec' => 'déc.',
    'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche',
    'Mon' => 'lun.', 'Tue' => 'mar.', 'Wed' => 'mer.', 'Thu' => 'jeu.', 'Fri' => 'ven.', 'Sat' => 'sam.', 'Sun' => 'dim.',
];

/** French order for the date formats the app uses ("Oct 8, 2026" → "8 oct. 2026"). */
const FR_DATE_FORMATS = [
    'M j, Y · H:i' => 'j M Y · H:i',
    'M j, Y'       => 'j M Y',
    'F j, Y'       => 'j F Y',
    'D, M j'       => 'D j M',
    'M j'          => 'j M',
];

/** Format a date in the current language (English pattern in, localised text out). */
function date_local(DateTimeInterface $date, string $format): string
{
    if (lang() !== 'fr') {
        return $date->format($format);
    }
    return strtr($date->format(FR_DATE_FORMATS[$format] ?? $format), FR_DATE_WORDS);
}

/**
 * A translated text containing HTML fragments: the text is escaped, the
 * fragments (already escaped by the caller) go into its {placeholders}.
 * t_html('Sign in as {name} to open the admin panel.', ['name' => '<strong>' . e($user) . '</strong>'])
 *
 * @param array<string, string> $html
 */
function t_html(string $text, array $html = []): string
{
    $pairs = [];
    foreach ($html as $key => $fragment) {
        $pairs['{' . $key . '}'] = $fragment;
    }
    return strtr(e(t($text)), $pairs);
}

/*
 * Texts stored in the database (ledger lines, audit log) are always written in
 * English, whatever the language of the person who caused them, and are
 * translated when shown: tx_description(), log_details().
 */

/** Money for a stored text: always "$1.60". */
function stored_money(int|string|null $units): string
{
    return with_lang('en', static fn (): string => money($units));
}

/** A count for a stored text: "3 bubbles". */
function stored_count(int $n, string $one, string $many): string
{
    return with_lang('en', static fn (): string => tn($one, $many, $n));
}

/**
 * Stored English sentences → translated sentences. Each pattern names its
 * parts; the template is the English text, translated through t().
 */
const STORED_TEXT_PATTERNS = [
    '/^Bubble #(?<id>[\d,]+) bought$/u' => 'Bubble #{id} bought',
    '/^Bubbles #(?<first>[\d,]+)–#(?<last>[\d,]+) bought$/u' => 'Bubbles #{first}–#{last} bought',
    '/^Advertising credits included with (?<n>[\d,]+) bubbles?$/u' => ['Advertising credits included with {n} bubble', 'Advertising credits included with {n} bubbles'],
    '/^(?<user>\S+) bought (?<n>[\d,]+) bubbles?$/u' => ['{user} bought {n} bubble', '{user} bought {n} bubbles'],
    '/^Bubble #(?<id>[\d,]+) expired at (?<amount>.+)$/u' => 'Bubble #{id} expired at {amount}',
    '/^Funded campaign “(?<title>.*)”$/u' => 'Funded campaign “{title}”',
    '/^Added credits to “(?<title>.*)”$/u' => 'Added credits to “{title}”',
    '/^Unused credits from “(?<title>.*)”$/u' => 'Unused credits from “{title}”',
    '/^Deposit #(?<id>\d+) via (?<method>.+) approved$/u' => 'Deposit #{id} via {method} approved',
    '/^Manual deposit #(?<id>\d+) added by admin$/u' => 'Manual deposit #{id} added by admin',
    '/^Withdrawal #(?<id>\d+) via (?<method>.+) requested$/u' => 'Withdrawal #{id} via {method} requested',
    '/^Withdrawal #(?<id>\d+) cancelled — refunded$/u' => 'Withdrawal #{id} cancelled — refunded',
    '/^Withdrawal #(?<id>\d+) rejected — refunded$/u' => 'Withdrawal #{id} rejected — refunded',
    '/^Adjustment: (?<note>.*)$/us' => 'Adjustment: {note}',
    // Audit log
    '/^Added (?<amount>\S+) to the pool, (?<n>[\d,]+) bubbles? expired\. (?<note>.+)$/us' => ['Added {amount} to the pool, {n} bubble expired. {note}', 'Added {amount} to the pool, {n} bubbles expired. {note}'],
    '/^Added (?<amount>\S+) to the pool, (?<n>[\d,]+) bubbles? expired\.$/u' => ['Added {amount} to the pool, {n} bubble expired.', 'Added {amount} to the pool, {n} bubbles expired.'],
    '/^Campaign #(?<id>\d+) “(?<title>.*)” — (?<note>.*)$/us' => 'Campaign #{id} “{title}” — {note}',
    '/^Campaign #(?<id>\d+) “(?<title>.*)”$/us' => 'Campaign #{id} “{title}”',
    '/^Deleted campaign #(?<id>\d+) “(?<title>.*)”$/us' => 'Deleted campaign #{id} “{title}”',
    '/^Created house ad #(?<id>\d+) “(?<title>.*)”$/us' => 'Created house ad #{id} “{title}”',
    '/^Updated house ad #(?<id>\d+) “(?<title>.*)”$/us' => 'Updated house ad #{id} “{title}”',
    '/^Created deposit method #(?<id>\d+) “(?<name>.*)”$/us' => 'Created deposit method #{id} “{name}”',
    '/^Created withdrawal method #(?<id>\d+) “(?<name>.*)”$/us' => 'Created withdrawal method #{id} “{name}”',
    '/^Updated deposit method #(?<id>\d+) “(?<name>.*)”$/us' => 'Updated deposit method #{id} “{name}”',
    '/^Updated withdrawal method #(?<id>\d+) “(?<name>.*)”$/us' => 'Updated withdrawal method #{id} “{name}”',
    '/^Deleted deposit method #(?<id>\d+) “(?<name>.*)”$/us' => 'Deleted deposit method #{id} “{name}”',
    '/^Deleted withdrawal method #(?<id>\d+) “(?<name>.*)”$/us' => 'Deleted withdrawal method #{id} “{name}”',
    '/^Deposit method #(?<id>\d+) “(?<name>.*)”$/us' => 'Deposit method #{id} “{name}”',
    '/^Withdrawal method #(?<id>\d+) “(?<name>.*)”$/us' => 'Withdrawal method #{id} “{name}”',
    '/^Approved deposit #(?<id>\d+), credited (?<amount>\S+)$/u' => 'Approved deposit #{id}, credited {amount}',
    '/^Rejected deposit #(?<id>\d+) — (?<note>.*)$/us' => 'Rejected deposit #{id} — {note}',
    '/^Rejected deposit #(?<id>\d+)$/u' => 'Rejected deposit #{id}',
    '/^Manual deposit #(?<id>\d+) of (?<amount>\S+) for member #(?<member>\d+)\. ?(?<note>.*)$/us' => 'Manual deposit #{id} of {amount} for member #{member}. {note}',
    '/^Paid withdrawal #(?<id>\d+) \((?<amount>\S+)\)$/u' => 'Paid withdrawal #{id} ({amount})',
    '/^Rejected withdrawal #(?<id>\d+) — (?<note>.*)$/us' => 'Rejected withdrawal #{id} — {note}',
    '/^Rejected withdrawal #(?<id>\d+)$/u' => 'Rejected withdrawal #{id}',
    '/^Credited (?<shown>.+?) to member #(?<member>\d+) \((?<wallet>[^)]+)\) — (?<note>.*)$/us' => 'Credited {shown} to member #{member} ({wallet}) — {note}',
    '/^Debited (?<shown>.+?) from member #(?<member>\d+) \((?<wallet>[^)]+)\) — (?<note>.*)$/us' => 'Debited {shown} from member #{member} ({wallet}) — {note}',
    '/^Member #(?<id>\d+) set to banned$/u' => 'Member #{id} set to banned',
    '/^Member #(?<id>\d+) set to active$/u' => 'Member #{id} set to active',
    '/^Member #(?<id>\d+) role set to admin$/u' => 'Member #{id} role set to admin',
    '/^Member #(?<id>\d+) role set to user$/u' => 'Member #{id} role set to user',
    '/^Reset the password of member #(?<id>\d+)$/u' => 'Reset the password of member #{id}',
    '/^Turned off two-factor authentication of member #(?<id>\d+)$/u' => 'Turned off two-factor authentication of member #{id}',
    '/^Changed the email of member #(?<id>\d+) from (?<old>\S+) to (?<new>\S+)$/u' => 'Changed the email of member #{id} from {old} to {new}',
    '/^Changed: (?<keys>.+)$/us' => 'Changed: {keys}',
    '/^Saved without changes$/u' => 'Saved without changes',
    '/^Exported deposits \((?<filter>[^,]+), search “(?<q>.*)”\)$/us' => 'Exported deposits ({filter}, search “{q}”)',
    '/^Exported deposits \((?<filter>[^)]+)\)$/u' => 'Exported deposits ({filter})',
    '/^Exported withdrawals \((?<filter>[^,]+), search “(?<q>.*)”\)$/us' => 'Exported withdrawals ({filter}, search “{q}”)',
    '/^Exported withdrawals \((?<filter>[^)]+)\)$/u' => 'Exported withdrawals ({filter})',
    '/^Exported members \((?<filter>[^,]+), search “(?<q>.*)”\)$/us' => 'Exported members ({filter}, search “{q}”)',
    '/^Exported members \((?<filter>[^)]+)\)$/u' => 'Exported members ({filter})',
    '/^Exported the ledger \(filtered\)$/u' => 'Exported the ledger (filtered)',
    '/^Exported the ledger$/u' => 'Exported the ledger',
];

/** A stored English sentence in the current language (unknown sentences are shown as written). */
function stored_text(string $text): string
{
    if (lang() === 'en') {
        return $text;
    }
    foreach (STORED_TEXT_PATTERNS as $pattern => $template) {
        if (!preg_match($pattern, $text, $m)) {
            continue;
        }
        $vars = [];
        foreach ($m as $key => $value) {
            if (is_string($key)) {
                // Numbers and amounts were written the English way: show them the local way.
                $vars[$key] = match (true) {
                    in_array($key, ['id', 'member', 'first', 'last', 'n'], true) && preg_match('/^[\d,]+$/', $value) === 1 => num((int) str_replace(',', '', $value)),
                    in_array($key, ['amount', 'shown'], true) && ($units = to_units($value)) !== null => money($units),
                    $key === 'shown' && preg_match('/^([\d,]+) credits?$/', $value, $c) === 1 => tn('{n} credit', '{n} credits', (int) str_replace(',', '', $c[1])),
                    in_array($key, ['wallet', 'filter'], true) => t($value),
                    default => $value,
                };
            }
        }
        if (is_array($template)) {
            // Plural templates come from patterns that capture the count as "n".
            $count = isset($m['n']) ? (int) str_replace(',', '', $m['n']) : 0;
            return trim(tn($template[0], $template[1], $count, $vars));
        }
        return trim(t($template, $vars));
    }
    return $text;
}

/** Texts the browser script needs (assets/js/app.js reads them from the #i18n block). */
const JS_TEXTS = [
    'Choose an image', 'Copied!', '{n}s', 'Thanks for watching — your purchase is unlocked.',
    '{n} more sale', '{n} more sales', 'Buy {n} bubble · {amount}', 'Buy {n} bubbles · {amount}',
    '{amount}  ·  fee {fee}', 'Below the fee', 'Your headline', 'Your description appears here.',
    'Visit site', 'yoursite.com', 'Bought', 'Expired',
];

/** The #i18n data block: the language and the translations of JS_TEXTS. */
function js_i18n(): array
{
    $strings = [];
    foreach (JS_TEXTS as $text) {
        $translated = t($text);
        if ($translated !== $text) {
            $strings[$text] = $translated;
        }
    }
    return ['lang' => lang(), 'strings' => $strings];
}
