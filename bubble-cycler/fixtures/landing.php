<?php
/**
 * Fixtures for app/views/public/landing.php: the exact values shown by the
 * Cosmic Loop mockup (mockups/cosmic-loop, edition 08), per language. Rendering
 * the template with these must give 0 differences against
 * mockups/landing.{fr,en}.html (see tools/render-landing.php).
 */
declare(strict_types=1);

$studio = 'https://bubble-cycler-studio.david44220.chatgpt.site/';
$download = $studio . 'downloads/bubble-cycler-edition-08-cosmic-loop.zip';

$shared = [
    'brand'   => ['name' => 'Bubble', 'light' => ' Cycler', 'label' => 'Bubble Cycler — accueil'],
    'edition' => ['name' => 'COSMIC LOOP', 'number' => '08 / 10', 'code' => 'BC / 08', 'palette' => 'VIOLET · NÉBULEUSE'],
    'image'   => 'assets/hero-08.webp',
    'script'  => 'app.js',
    'styles'  => ['styles.css'],
];

return [
    'fr' => $shared + [
        'lang'        => 'fr',
        'title'       => 'Bubble Cycler — COSMIC LOOP',
        'description' => 'Dix univers visuels pour Bubble Cycler, en français et en anglais.',
        'nav_label'   => 'Navigation principale',
        'nav'         => [
            ['label' => 'L’application', 'href' => '#experience'],
            ['label' => 'Le cycle', 'href' => '#loop'],
            ['label' => 'Les éditions', 'href' => $studio . '#editions'],
        ],
        'switch'      => ['text' => 'EN', 'label' => 'English'],
        'hero'        => [
            'alt'       => 'Bulles planétaires en orbite dans une nébuleuse violette',
            'lines'     => ['Une autre', 'orbite.'],
            'lead'      => 'Les bulles entrent dans leur cycle publicitaire.',
            'intro'     => 'Achetez une bulle, suivez sa durée et sa diffusion publicitaire, puis consultez le ROI cible annoncé de 160 % à son expiration.',
            'primary'   => ['label' => 'Comprendre le cycle', 'href' => '#experience', 'icon' => '↘'],
            'link'      => ['label' => 'Explorer les éditions', 'href' => $studio . '#editions'],
            'secondary' => ['label' => 'Télécharger la landing', 'href' => $download, 'icon' => '↓', 'download' => 'bubble-cycler-edition-08-cosmic-loop.zip'],
            'scroll'    => 'Faire défiler',
        ],
        'story'       => [
            'kicker' => 'Bubble Cycler / 08',
            'title'  => 'Chaque échéance marque un nouveau point.',
            'body'   => 'Chaque bulle achetée déclenche la diffusion de publicité pendant un cycle à durée définie. À son expiration, le ROI cible annoncé est de 160 %. Le résultat réel dépend des revenus publicitaires générés; aucun rendement n’est garanti et un risque de perte existe.',
            'tag'    => 'COSMIC LOOP',
            'note'   => 'CYCLE PUBLICITAIRE',
        ],
        'features'    => [
            'kicker'     => 'Le fonctionnement',
            'title'      => 'Une bulle. Une durée. Une échéance.',
            'cards'      => [
                ['title' => 'Bulle achetée', 'body' => 'Chaque bulle achetée déclenche la diffusion de publicité pendant son cycle.'],
                ['title' => 'Durée définie', 'body' => 'Chaque bulle suit une durée déterminée et expire au terme de son cycle.'],
                ['title' => 'ROI cible : 160 %', 'body' => 'Cible annoncée à l’échéance. Le résultat réel dépend des revenus publicitaires et n’est pas garanti.'],
            ],
            'disclaimer' => 'Les 160 % sont une cible indicative, pas un rendement garanti. Les revenus publicitaires peuvent varier et le capital est exposé à un risque de perte partielle ou totale.',
        ],
        'loop'        => [
            'kicker' => 'Étapes du cycle',
            'title'  => 'De l’achat à l’expiration, suivez chaque étape.',
            'steps'  => [
                ['title' => 'Achetez une bulle', 'body' => 'Consultez le prix, la durée et les conditions du cycle avant l’achat.'],
                ['title' => 'La publicité est diffusée', 'body' => 'Chaque bulle achetée est associée à de la publicité pendant son cycle.'],
                ['title' => 'La bulle expire', 'body' => 'Le ROI cible affiché est de 160 %. Le résultat réel peut différer; aucun rendement n’est garanti.'],
            ],
        ],
        'closing'     => [
            'kicker' => 'Avant d’acheter',
            'title'  => 'Comprendre le cycle, c’est essentiel.',
            'body'   => 'Consultez la durée, les modalités publicitaires et les conditions de calcul avant tout achat.',
            'cta'    => ['label' => 'Retour aux 10 éditions', 'href' => $studio . '#editions', 'icon' => '↗'],
        ],
        'footer'      => [
            'text' => 'Bubble Cycler · application de bulles et de cycles publicitaires.',
            'link' => ['label' => 'Explorer les éditions ↑', 'href' => $studio . '#editions'],
        ],
    ],
    'en' => $shared + [
        'lang'        => 'en',
        'title'       => 'Bubble Cycler — COSMIC LOOP',
        'description' => 'Dix univers visuels pour Bubble Cycler, en français et en anglais.',
        'nav_label'   => 'Main navigation',
        'nav'         => [
            ['label' => 'The app', 'href' => '#experience'],
            ['label' => 'The cycle', 'href' => '#loop'],
            ['label' => 'Editions', 'href' => $studio . '#editions'],
        ],
        'switch'      => ['text' => 'FR', 'label' => 'Français'],
        'hero'        => [
            'alt'       => 'Planet-like bubbles orbiting through a violet nebula',
            'lines'     => ['A different', 'orbit.'],
            'lead'      => 'Bubbles enter their advertising cycle.',
            'intro'     => 'Buy a bubble, follow its term and ad delivery, then view the stated 160% target ROI at expiry.',
            'primary'   => ['label' => 'Understand the cycle', 'href' => '#experience', 'icon' => '↘'],
            'link'      => ['label' => 'Explore editions', 'href' => $studio . '#editions'],
            'secondary' => ['label' => 'Download landing page', 'href' => $download, 'icon' => '↓', 'download' => 'bubble-cycler-edition-08-cosmic-loop.zip'],
            'scroll'    => 'Scroll',
        ],
        'story'       => [
            'kicker' => 'Bubble Cycler / 08',
            'title'  => 'Every expiry marks a new point.',
            'body'   => 'Every purchased bubble triggers advertising delivery during a defined cycle. At expiry, the stated target ROI is 160%. Actual results depend on advertising revenue; no return is guaranteed and losses are possible.',
            'tag'    => 'COSMIC LOOP',
            'note'   => 'ADVERTISING CYCLE',
        ],
        'features'    => [
            'kicker'     => 'How it works',
            'title'      => 'One bubble. One term. One expiry.',
            'cards'      => [
                ['title' => 'Purchased bubble', 'body' => 'Each purchased bubble triggers advertising delivery during its cycle.'],
                ['title' => 'Defined term', 'body' => 'Each bubble follows a defined term and expires at the end of its cycle.'],
                ['title' => 'Target ROI: 160%', 'body' => 'Stated target at expiry. Actual results depend on advertising revenue and are not guaranteed.'],
            ],
            'disclaimer' => '160% is an indicative target, not a guaranteed return. Advertising revenue can vary, and capital is subject to partial or total loss.',
        ],
        'loop'        => [
            'kicker' => 'Cycle steps',
            'title'  => 'Follow each step, from purchase to expiry.',
            'steps'  => [
                ['title' => 'Buy a bubble', 'body' => 'Review the price, term and cycle conditions before purchase.'],
                ['title' => 'Advertising is delivered', 'body' => 'Every purchased bubble is associated with advertising during its cycle.'],
                ['title' => 'The bubble expires', 'body' => 'The stated target ROI is 160%. Actual results may differ; no return is guaranteed.'],
            ],
        ],
        'closing'     => [
            'kicker' => 'Before you buy',
            'title'  => 'Understand the cycle first.',
            'body'   => 'Review the duration, advertising terms and calculation rules before making a purchase.',
            'cta'    => ['label' => 'Back to all 10 editions', 'href' => $studio . '#editions', 'icon' => '↗'],
        ],
        'footer'      => [
            'text' => 'Bubble Cycler · bubbles and advertising cycles.',
            'link' => ['label' => 'Explore editions ↑', 'href' => $studio . '#editions'],
        ],
    ],
];
