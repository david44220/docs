<?php
/**
 * Landing page copy (Cosmic Loop, edition 08), French and English.
 *
 * Taken word for word from the mockup (mockups/cosmic-loop/app.js), except
 * where the mockup describes a different product: bubbles here have no fixed
 * term (they wait in a first-in-first-out queue) and payouts come only from
 * new purchases, not from advertising revenue. Those sentences say what the
 * app actually does. Placeholders: {site} site name, {roi} target ROI,
 * {target} expiry amount, {credits} ad credits per bubble.
 */
declare(strict_types=1);

return [
    'fr' => [
        'description' => '{site} — achetez une bulle, suivez sa place dans la file et ses crédits publicitaires. ROI cible indicatif de {roi}, non garanti.',
        'brand_label' => '{site} — accueil',
        'nav_label'   => 'Navigation principale',
        'nav'         => ['L’application', 'Le cycle'],
        'switch'      => ['text' => 'EN', 'label' => 'English'],
        'alt'         => 'Bulles planétaires en orbite dans une nébuleuse violette',
        'lines'       => ['Une autre', 'orbite.'],
        'lead'        => 'Les bulles entrent dans leur cycle publicitaire.',
        'intro'       => 'Achetez une bulle, suivez sa place dans la file et ses crédits publicitaires, puis consultez le ROI cible annoncé de {roi} à son expiration.',
        'explore'     => 'Comprendre le cycle',
        'scroll'      => 'Faire défiler',
        'guest'       => ['nav' => 'Se connecter', 'link' => 'Se connecter', 'secondary' => 'Créer un compte', 'cta' => 'Créer un compte'],
        'member'      => ['nav' => 'Mon espace', 'link' => 'Mon espace', 'secondary' => 'Acheter des bulles', 'cta' => 'Ouvrir mon espace'],
        'story'       => [
            'kicker' => '{site} / 08',
            'title'  => 'Chaque échéance marque un nouveau point.',
            'body'   => 'Chaque bulle achetée rejoint la file dans l’ordre d’achat et inclut des crédits publicitaires. Elle expire quand le pool, alimenté uniquement par les nouveaux achats, réunit {target} : le ROI cible annoncé est de {roi}. Aucun rendement n’est garanti et un risque de perte existe.',
            'tag'    => 'COSMIC LOOP',
            'note'   => 'CYCLE PUBLICITAIRE',
        ],
        'features'    => [
            'kicker'     => 'Le fonctionnement',
            'title'      => 'Une bulle. Une file. Une échéance.',
            'cards'      => [
                ['title' => 'Bulle achetée', 'body' => 'Chaque bulle achetée inclut {credits} crédits publicitaires pour diffuser vos propres annonces.'],
                ['title' => 'Ordre d’achat', 'body' => 'Les bulles sont payées une par une, dans l’ordre d’achat, dès que le pool réunit {target}.'],
                ['title' => 'ROI cible : {roi}', 'body' => 'Cible annoncée à l’échéance. Le pool n’est alimenté que par les nouveaux achats : rien n’est garanti.'],
            ],
            'disclaimer' => 'Les {roi} sont une cible indicative, pas un rendement garanti. Si les achats ralentissent, une bulle peut attendre longtemps ou ne jamais expirer, et le capital est exposé à un risque de perte partielle ou totale.',
        ],
        'loop'        => [
            'kicker' => 'Étapes du cycle',
            'title'  => 'De l’achat à l’expiration, suivez chaque étape.',
            'steps'  => [
                ['title' => 'Achetez une bulle', 'body' => 'Consultez le prix, votre place dans la file et les conditions du cycle avant l’achat.'],
                ['title' => 'La publicité est diffusée', 'body' => 'Un message sponsorisé précède chaque achat, et chaque bulle vous donne des crédits pour vos propres annonces.'],
                ['title' => 'La bulle expire', 'body' => 'Le ROI cible affiché est de {roi}. Le résultat réel peut différer; aucun rendement n’est garanti.'],
            ],
        ],
        'closing'     => [
            'kicker' => 'Avant d’acheter',
            'title'  => 'Comprendre le cycle, c’est essentiel.',
            'body'   => 'Consultez la file d’attente, les modalités publicitaires et les conditions de calcul avant tout achat.',
        ],
        'footer'      => ['text' => '{site} · application de bulles et de cycles publicitaires.', 'link' => 'Conditions et risques ↗'],
    ],
    'en' => [
        'description' => '{site} — buy a bubble, follow its place in the queue and its ad credits. Indicative {roi} target ROI, not guaranteed.',
        'brand_label' => '{site} — home',
        'nav_label'   => 'Main navigation',
        'nav'         => ['The app', 'The cycle'],
        'switch'      => ['text' => 'FR', 'label' => 'Français'],
        'alt'         => 'Planet-like bubbles orbiting through a violet nebula',
        'lines'       => ['A different', 'orbit.'],
        'lead'        => 'Bubbles enter their advertising cycle.',
        'intro'       => 'Buy a bubble, follow its place in the queue and its ad credits, then view the stated {roi} target ROI at expiry.',
        'explore'     => 'Understand the cycle',
        'scroll'      => 'Scroll',
        'guest'       => ['nav' => 'Sign in', 'link' => 'Sign in', 'secondary' => 'Create an account', 'cta' => 'Create an account'],
        'member'      => ['nav' => 'My account', 'link' => 'My account', 'secondary' => 'Buy bubbles', 'cta' => 'Open my account'],
        'story'       => [
            'kicker' => '{site} / 08',
            'title'  => 'Every expiry marks a new point.',
            'body'   => 'Every purchased bubble joins the queue in purchase order and includes advertising credits. It expires when the pool, funded only by new purchases, reaches {target}: the stated target ROI is {roi}. No return is guaranteed and losses are possible.',
            'tag'    => 'COSMIC LOOP',
            'note'   => 'ADVERTISING CYCLE',
        ],
        'features'    => [
            'kicker'     => 'How it works',
            'title'      => 'One bubble. One queue. One expiry.',
            'cards'      => [
                ['title' => 'Purchased bubble', 'body' => 'Each purchased bubble includes {credits} advertising credits to run your own ads.'],
                ['title' => 'Purchase order', 'body' => 'Bubbles are paid one by one, in purchase order, as soon as the pool holds {target}.'],
                ['title' => 'Target ROI: {roi}', 'body' => 'Stated target at expiry. The pool is funded only by new purchases: nothing is guaranteed.'],
            ],
            'disclaimer' => '{roi} is an indicative target, not a guaranteed return. If purchases slow down, a bubble can wait a long time or never expire, and capital is subject to partial or total loss.',
        ],
        'loop'        => [
            'kicker' => 'Cycle steps',
            'title'  => 'Follow each step, from purchase to expiry.',
            'steps'  => [
                ['title' => 'Buy a bubble', 'body' => 'Review the price, your place in the queue and the cycle conditions before purchase.'],
                ['title' => 'Advertising is delivered', 'body' => 'A sponsored message plays before every purchase, and every bubble gives you credits for your own ads.'],
                ['title' => 'The bubble expires', 'body' => 'The stated target ROI is {roi}. Actual results may differ; no return is guaranteed.'],
            ],
        ],
        'closing'     => [
            'kicker' => 'Before you buy',
            'title'  => 'Understand the cycle first.',
            'body'   => 'Review the queue, advertising terms and calculation rules before making a purchase.',
        ],
        'footer'      => ['text' => '{site} · bubbles and advertising cycles.', 'link' => 'Terms and risks ↗'],
    ],
];
