<?php
/**
 * Translation check. Lists every English text the app can show (the first
 * argument of t(), tn() and t_html(), plus the texts that reach t() through
 * constants and lists) and fails when app/lang/fr.php misses one, or when a
 * view still holds untranslated text.
 *
 *   php tools/i18n-check.php            report, exit 1 on any problem
 *   php tools/i18n-check.php --missing  print the missing keys as PHP array lines
 */
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
require ROOT . '/app/bootstrap.php';

/** @return list<string> every .php file under the given directories */
function php_files(string ...$dirs): array
{
    $files = [];
    foreach ($dirs as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/' . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);
    return $files;
}

/** The value of a PHP string literal token. */
function literal(string $token): string
{
    return (string) eval('return ' . $token . ';');
}

/**
 * Keys passed as literals to t() / tn() / t_html().
 *
 * @return array<string, list<string>> key => places
 */
function extract_keys(array $files): array
{
    $keys = [];
    foreach ($files as $file) {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            static fn ($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], ['t', 'tn', 't_html', 'tc'], true)) {
                continue;
            }
            $before = $tokens[$i - 1] ?? null;
            if (is_array($before) && in_array($before[0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }
            if (($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }
            $place = str_replace(ROOT . '/', '', $file) . ':' . $token[2];
            $first = $tokens[$i + 2] ?? null;
            if ($token[1] === 'tc' && ($tokens[$i + 3] ?? null) === ',') {
                $first = $tokens[$i + 4] ?? null; // tc(context, text): the text is the key
            }
            if (is_array($first) && $first[0] === T_CONSTANT_ENCAPSED_STRING) {
                $keys[literal($first[1])][] = $place;
                if ($token[1] === 'tn' && ($tokens[$i + 3] ?? null) === ',') {
                    $second = $tokens[$i + 4] ?? null;
                    if (is_array($second) && $second[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $keys[literal($second[1])][] = $place;
                    }
                }
            }
        }
    }
    return $keys;
}

$sources = php_files('app', 'public');
$keys = extract_keys($sources);

// Texts that reach t() through constants, lists and stored data.
$dynamic = array_merge(
    array_values(TX_TYPES),
    array_values(WALLET_LABELS),
    array_values(STATUS_LABELS),
    JS_TEXTS,
    [SETTING_DEFAULTS['disclaimer']],
    ['Cancelled by member', 'Manual (admin)', 'All', 'Admins'],
);
foreach (STORED_TEXT_PATTERNS as $template) {
    foreach ((array) $template as $text) {
        $dynamic[] = $text;
    }
}
// CSV column headings: the keys of the arrays given to csv_export().
foreach (php_files('public/admin') as $file) {
    if (preg_match_all("/^\\s*'([^']+)'\\s*=>\\s*static fn/m", (string) file_get_contents($file), $m)) {
        array_push($dynamic, ...$m[1]);
    }
}
foreach ($dynamic as $text) {
    $keys[$text][] = '(dynamic)';
}

$fr = require ROOT . '/app/lang/fr.php';
$problems = 0;

$missing = array_diff_key($keys, $fr);
if (in_array('--missing', $argv, true)) {
    foreach (array_keys($missing) as $key) {
        echo '    ' . var_export($key, true) . " => '',\n";
    }
    exit($missing === [] ? 0 : 1);
}

foreach ($missing as $key => $places) {
    echo "MISSING  ", json_encode($key, JSON_UNESCAPED_UNICODE), '  (', $places[0], ")\n";
    $problems++;
}
foreach ($fr as $key => $value) {
    if (!is_string($value) || trim($value) === '') {
        echo "EMPTY    ", json_encode($key, JSON_UNESCAPED_UNICODE), "\n";
        $problems++;
        continue;
    }
    // Every {placeholder} of the English text must survive in French.
    preg_match_all('/\{(\w+)\}/', $key, $a);
    preg_match_all('/\{(\w+)\}/', $value, $b);
    $wanted = $a[1];
    $given = $b[1];
    sort($wanted);
    sort($given);
    if ($wanted !== $given) {
        echo "PLACEHOLDERS  ", json_encode($key, JSON_UNESCAPED_UNICODE), ' → ', json_encode($value, JSON_UNESCAPED_UNICODE), "\n";
        $problems++;
    }
}
$unused = array_filter(array_diff_key($fr, $keys), static fn ($key): bool => !str_contains((string) $key, '|'), ARRAY_FILTER_USE_KEY);
foreach (array_keys($unused) as $key) {
    echo "unused   ", json_encode($key, JSON_UNESCAPED_UNICODE), "\n";
}

// Views: visible text and attributes written straight into the HTML. Each view
// is read as one HTML stream where every PHP block becomes a marker.
$allowed = '/^(?:[\s#×—–·•…→←↗↓✓✕+\-\/|:%()&;,.0-9]|&[a-z]+;|EN|FR|CSV|IP|ID|URL|2FA|USDT|TRC20|PayPal|English|Français|https?:\/\/\S*|[\w.+-]+@example\.com|smtp\.example\.com|USD)*$/u';
foreach (php_files('app/views') as $file) {
    $name = str_replace(ROOT . '/', '', $file);
    if (str_ends_with($name, 'views/public/landing.php')) {
        continue; // every text comes from app/lang/landing.php
    }
    $html = '';
    $inPhp = false;
    foreach (token_get_all((string) file_get_contents($file)) as $token) {
        $id = is_array($token) ? $token[0] : null;
        if ($id === T_INLINE_HTML) {
            $html .= $token[1];
        } elseif ($id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO) {
            $html .= "\u{2063}";
            $inPhp = true;
        } elseif ($id === T_CLOSE_TAG) {
            $inPhp = false;
            if (str_ends_with($token[1], "\n")) {
                $html .= "\n";
            }
        }
    }
    $html = (string) preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html);
    $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
    // Attributes people read.
    if (preg_match_all('/<[^>]+>/', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            if (preg_match_all('/\b(?:placeholder|title|alt|aria-label|data-confirm|data-default)="([^"]*)"/', $tag, $attrs)) {
                foreach ($attrs[1] as $value) {
                    foreach (explode("\u{2063}", $value) as $part) {
                        if (preg_match('/\p{L}{2,}/u', $part) && !preg_match($allowed, trim($part))) {
                            echo "VIEW ATTR  $name  ", json_encode($part, JSON_UNESCAPED_UNICODE), "\n";
                            $problems++;
                        }
                    }
                }
            }
        }
    }
    // Text between tags.
    foreach (preg_split('/<[^>]*>/', $html) ?: [] as $text) {
        foreach (explode("\u{2063}", $text) as $part) {
            $part = trim((string) preg_replace('/\s+/u', ' ', $part));
            if ($part !== '' && preg_match('/\p{L}{2,}/u', $part) && !preg_match($allowed, $part)) {
                echo "VIEW TEXT  $name  ", json_encode($part, JSON_UNESCAPED_UNICODE), "\n";
                $problems++;
            }
        }
    }
}

// PHP code: sentences handed to flash(), AppError, abort() or a view without t().
foreach ($sources as $file) {
    $name = str_replace(ROOT . '/', '', $file);
    if (preg_match('#^app/lang/#', $name)) {
        continue;
    }
    $lines = file($file) ?: [];
    foreach ($lines as $n => $line) {
        if (preg_match("/(?:flash\\([^,]+,|new AppError\\(|abort\\(\\d+,|'(?:title|eyebrow|lead|subject)'\\s*=>)\\s*(['\"])(?=[^'\"]*\\p{L}{2})/u", $line)) {
            echo 'CODE     ', $name, ':', $n + 1, '  ', trim($line), "\n";
            $problems++;
        }
    }
}

printf("\n%d keys, %d translated, %d problem(s)\n", count($keys), count($keys) - count($missing), $problems);
exit($problems === 0 ? 0 : 1);
