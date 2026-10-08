<?php
/**
 * Landing page data (Cosmic Loop, edition 08). Builds the array that
 * app/views/public/landing.php renders — the same shape as the mockup
 * fixtures in fixtures/landing.php — from the copy in app/lang/landing.php,
 * the settings and the visitor's state.
 */
declare(strict_types=1);

/** "160 %" in French, "160%" in English (one decimal when needed). */
function landing_percent(int $part, int $whole, string $lang): string
{
    $value = $whole > 0 ? $part / $whole * 100 : 0.0;
    $decimals = abs($value - round($value)) < 0.05 ? 0 : 1;
    return $lang === 'fr'
        ? number_format($value, $decimals, ',', ' ') . ' %'
        : number_format($value, $decimals) . '%';
}

/** "1,60 $" in French, "$1.60" in English. */
function landing_money(int $units, string $lang): string
{
    return with_lang($lang, static fn (): string => money($units));
}

/** The brand as the mockup draws it: first word bold, the rest light ("Bubble" + " Cycler"). */
function landing_brand(string $site): array
{
    $space = strpos($site, ' ');
    return $space === false
        ? ['name' => $site, 'light' => '']
        : ['name' => substr($site, 0, $space), 'light' => substr($site, $space)];
}

/** "BC" for "Bubble Cycler". */
function landing_initials(string $site): string
{
    $initials = '';
    foreach (preg_split('/\s+/u', trim($site)) ?: [] as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return mb_substr($initials, 0, 3);
}

/** Icons, share card and font preload for the <head> (trusted HTML: every value is escaped here). */
function landing_head_tags(string $lang, string $title, string $description): array
{
    $origin = mail_base_url() !== '' ? (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', mail_base_url()) : '';
    $tags = [
        '<link rel="icon" href="' . e(asset('img/favicon.svg')) . '" type="image/svg+xml">',
        '<link rel="apple-touch-icon" href="' . e(asset('img/apple-touch-icon.png')) . '">',
        '<link rel="manifest" href="' . e(url('manifest.php')) . '">',
        // Same URL as the @font-face rule in fonts.css, or the font downloads twice.
        '<link rel="preload" href="' . e(url('assets/fonts/inter-latin-wght-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>',
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="' . e(site_name()) . '">',
        '<meta property="og:title" content="' . e($title) . '">',
        '<meta property="og:description" content="' . e($description) . '">',
        '<meta property="og:locale" content="' . ($lang === 'fr' ? 'fr_FR' : 'en_US') . '">',
        '<meta name="twitter:card" content="summary_large_image">',
    ];
    if ($origin !== '') {
        $tags[] = '<meta property="og:url" content="' . e($origin . url('index.php')) . '">';
        $tags[] = '<meta property="og:image" content="' . e($origin . asset('img/og.jpg')) . '">';
        $tags[] = '<meta property="og:image:width" content="1200">';
        $tags[] = '<meta property="og:image:height" content="630">';
        foreach (array_keys(LANGUAGES) as $alternate) {
            $tags[] = '<link rel="alternate" hreflang="' . $alternate . '" href="' . e($origin . url('index.php', ['lang' => $alternate])) . '">';
        }
    }
    return $tags;
}

/** The edition labels the design prints on the hero (eyebrow and captions). */
function cosmic_edition(): array
{
    return ['name' => 'COSMIC LOOP', 'number' => '08 / 10', 'code' => landing_initials(site_name()) . ' / 08', 'palette' => t('VIOLET · NEBULA')];
}

/** Everything the landing template shows, for one language. */
function landing_page(string $lang, ?array $user): array
{
    return with_lang($lang, static fn (): array => landing_page_copy($lang, $user));
}

/** landing_page() in the page's language (so t() and money() follow $lang). */
function landing_page_copy(string $lang, ?array $user): array
{
    $copy = (require APP_DIR . '/lang/landing.php')[$lang];
    $site = site_name();
    $price = setting_int('bubble_price');
    $target = setting_int('bubble_target');
    $vars = [
        '{site}'    => $site,
        '{roi}'     => landing_percent($target, $price, $lang),
        '{target}'  => landing_money($target, $lang),
        '{credits}' => number_format(setting_int('ad_credits_per_bubble'), 0, $lang === 'fr' ? ',' : '.', $lang === 'fr' ? ' ' : ','),
    ];
    // Resolve every placeholder in the copy, recursively.
    $fill = static function (mixed $value) use (&$fill, $vars): mixed {
        return is_array($value) ? array_map($fill, $value) : (is_string($value) ? strtr($value, $vars) : $value);
    };
    $copy = $fill($copy);

    $signedIn = $user !== null;
    $cta = $signedIn ? $copy['member'] : $copy['guest'];
    $home = url($signedIn ? 'dashboard.php' : 'login.php');
    $title = $site . ' — COSMIC LOOP';

    return [
        'lang'        => $lang,
        'title'       => $title,
        'description' => $copy['description'],
        'head'        => landing_head_tags($lang, $title, $copy['description']),
        'styles'      => [asset('css/fonts.css'), asset('css/cosmic.css')],
        'script'      => asset('js/landing.js'),
        'image'       => asset('img/hero-08.webp'),
        'brand'       => landing_brand($site) + ['label' => $copy['brand_label']],
        'edition'     => cosmic_edition(),
        'nav_label'   => $copy['nav_label'],
        'nav'         => [
            ['label' => $copy['nav'][0], 'href' => '#experience'],
            ['label' => $copy['nav'][1], 'href' => '#loop'],
            ['label' => $cta['nav'], 'href' => $home],
        ],
        'switch'      => $copy['switch'],
        'hero'        => [
            'alt'       => $copy['alt'],
            'lines'     => $copy['lines'],
            'lead'      => $copy['lead'],
            'intro'     => $copy['intro'],
            'primary'   => ['label' => $copy['explore'], 'href' => '#experience', 'icon' => '↘'],
            'link'      => ['label' => $cta['link'], 'href' => $home],
            'secondary' => ['label' => $cta['secondary'], 'href' => url($signedIn ? 'buy.php' : 'register.php'), 'icon' => '↗'],
            'scroll'    => $copy['scroll'],
        ],
        'story'       => $copy['story'],
        'features'    => $copy['features'],
        'loop'        => $copy['loop'],
        'closing'     => $copy['closing'] + [
            'cta' => ['label' => $cta['cta'], 'href' => url($signedIn ? 'dashboard.php' : 'register.php'), 'icon' => '↗'],
        ],
        'footer'      => [
            'text' => $copy['footer']['text'],
            'link' => ['label' => $copy['footer']['link'], 'href' => url('terms.php')],
        ],
    ];
}
