# BubbleCycle — bubble game cycler with a built-in ad network

A complete bubble cycler written in **pure PHP 8** (no framework, no Composer, no build step) on MySQL / MariaDB.
Members buy **$1.00 bubbles**, **$0.80** of each purchase goes into a first-in-first-out pool, and every bubble
**expires at $1.60**. Each bubble also comes with **advertising credits**, and a **sponsored message plays before every
purchase**. Deposits and withdrawals use **manual payment methods that you manage from the admin panel**.

![Landing page](docs/screenshots/landing.jpg)

| Member dashboard | Buy page with the ad gate |
|---|---|
| ![Dashboard](docs/screenshots/dashboard.jpg) | ![Buy](docs/screenshots/buy.jpg) |

| Admin overview | Manual payment methods |
|---|---|
| ![Admin](docs/screenshots/admin.jpg) | ![Methods](docs/screenshots/methods.jpg) |

---

## Features

**Members**
- Buy 1–25 bubbles at a time (configurable) with the purchase balance or, optionally, the cash balance.
- Live pool: the bubble at the front of the queue fills up in real time; every bubble shows its queue position,
  how far it has risen and an estimate of how many new sales it still needs.
- Advertising credits with every bubble, and campaigns (headline, text, link, optional banner) with views,
  clicks and CTR. Campaigns can be paused, topped up, edited or deleted (unused credits are refunded).
- Deposits through the manual methods you define (with an optional payment screenshot), withdrawals from the cash
  balance, a full ledger, referral link with commissions, and account security settings.
- Celebration when bubbles expire, toasts, keyboard-accessible UI, works on phones.

**Admin panel**
- Overview: members, pool, platform revenue, pending work, a 14-day chart of bubbles bought/expired, a money
  overview (where every cent sits) and the **queue gap** (money the pool still needs to expire every active bubble).
- **Deposits**: review queue with the proof screenshot, approve (you can change the credited amount) or reject with a reason.
- **Withdrawals**: mark as paid with a payment reference, or reject (the amount is refunded).
- **Payment methods**: create deposit and withdrawal methods (crypto wallet, bank transfer, mobile money, PayPal…)
  with your payment details, instructions, min/max, fixed and percentage fees, and a "screenshot required" switch.
- **Members**: search, manual deposit, credit/debit any wallet, ban, admin rights, password reset.
- **Pool & queue**: live state, full queue and history, and pool top-ups (promotions) that pay the queue in order.
- **Ad campaigns**: moderation (approve / reject / pause / delete) and free, unlimited house ads.
- **Settings**: every number of the economy, ad timer and credits, limits, registrations, maintenance mode,
  timezone, currency symbol, risk disclaimer and terms.
- **Audit log** of every admin action.

**Design** — dark "glass" interface with iridescent accents, CSS-only liquid-filled bubbles, self-hosted Inter and
Sora fonts, responsive down to 320 px, respects `prefers-reduced-motion`.

---

## How the cycler works

| Setting (Admin → Settings) | Default | Meaning |
|---|---|---|
| Bubble price | $1.00 | What a member pays per bubble |
| Credited to the pool | $0.80 | Part of the price that enters the pool |
| Expires at | $1.60 | A bubble expires once the pool has paid it this much |
| Referral commission | $0.05 | Paid to the buyer's referrer, out of the platform share |
| Ad credits per bubble | 50 | 1 credit = 1 completed ad view |

Bubbles wait in **one queue, in purchase order**. The pool only ever pays the bubble at the front. As soon as the
pool holds that bubble's target, the bubble **expires**, its owner's cash balance is credited with the full $1.60,
and the next bubble moves up. With the defaults every **2 new bubbles** expire one bubble ($1.60 ÷ $0.80).

```
Alice buys 1  → pool $0.80   bubble #1 is 50 % full
Bob buys 1    → pool $1.60   #1 expires → Alice +$1.60, pool $0.00, #2 moves to the front
Carol buys 3  → pool $2.40   #2 expires → Bob +$1.60, pool $0.80 fills #3 to 50 %
```

Each dollar splits into $0.80 pool / $0.05 referrer / $0.15 platform (or $0.20 platform when the buyer has no
referrer). Changing the economics later only affects new bubbles: each bubble keeps the target it was bought with.

Money is stored as integers (1.00 = 1 000 000), every balance change goes through a single ledger function inside a
database transaction, and every purchase locks the pool row, so concurrent buyers can never double-spend or break the
queue order. The test suite checks that **every cent is accounted for** after each scenario.

> **Be honest with your members.** Bubbles are paid only from new purchases (and any amount you add to the pool).
> If purchases slow down, bubbles wait longer and some may never expire. The default disclaimer, FAQ and terms say
> this plainly — keep them. Schemes where earlier participants are paid from later participants' money are regulated
> or prohibited in many countries: check the law where you operate before accepting real money.

---

## Advertising

- Every purchase is preceded by a **sponsored message** shown for `ad_seconds` (default 10 s). The timer is enforced
  on the server; the countdown in the browser is only a convenience. One completed view unlocks **one** purchase.
- Refreshing the page does not restart the countdown (the view is reused for 30 minutes).
- Rotation: member campaigns with credits first, least recently shown first, never the viewer's own campaign.
  House ads fill in when no member campaign is running; if nothing is active, purchases are unlocked automatically.
- A credit is charged only when the countdown completes; clicks are counted once per view.
- Member campaigns can require admin approval (on by default). Banner images must be `https://` URLs.

---

## Manual deposits & withdrawals

1. **Admin → Payment methods → Add a deposit method**: name, your wallet address / bank details, instructions,
   limits, fees, and whether a payment screenshot is required. Activate it.
2. The member picks the method on **Deposit**, sends the money, and submits the amount, the transaction reference and
   optionally a screenshot. Duplicate references are refused.
3. **Admin → Deposits**: open the request, check the payment, then **Approve & credit** (the amount is editable, e.g.
   if less was received) or **Reject** with a reason. Screenshots are stored outside the web root and only admins
   can open them.
4. You can also credit money received any other way: **Admin → Members → member → Manual deposit**.

Withdrawals are taken from the cash balance as soon as they are requested. The admin sends the payment by hand and
clicks **Mark as paid** (with the payment reference), or **Reject** to refund it. Members can cancel pending requests.
All payment amounts are in whole cents.

---

## Requirements

- PHP **8.1+** with `pdo_mysql`, `mbstring` and `fileinfo`
- MySQL **5.7+ / 8.x** or MariaDB **10.4+**
- Apache (the `.htaccess` files are included) or nginx

## Installation

1. Upload the `bubble-cycler/` folder and create an empty database (`utf8mb4`) plus a user with full rights on it.
2. Point the web server's **document root at `bubble-cycler/public/`**.
   On shared hosting where you cannot change it, upload the folder as-is: the root `.htaccess` routes every request
   into `public/` and blocks `app/`, `database/`, `storage/` and `tests/`.
3. Make `app/` (for `config.php`) and `storage/` writable by PHP.
4. Open `https://your-domain/install.php` and fill in the database, the site name and your admin account.
   The installer creates the tables, writes `app/config.php` and locks itself with `storage/installed.lock`.
5. Delete `public/install.php` (optional, it is already locked).

**nginx**

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    root /var/www/bubble-cycler/public;
    index index.php;

    location / { try_files $uri $uri/ =404; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\. { deny all; }
    client_max_body_size 6m;
}
```

**Local development**

```bash
php -S 127.0.0.1:8080 -t public
# then open http://127.0.0.1:8080/install.php
```

## Going live checklist

1. **Admin → Payment methods**: replace the example details with your real ones and activate at least one deposit
   and one withdrawal method (the examples are created inactive on purpose).
2. **Admin → Settings**: review the economics, the ad timer, limits, timezone, support email and the legal texts.
3. Create or edit the **house ad** (Admin → Ad campaigns → House ads).
4. Serve the site over HTTPS. Behind Cloudflare or a proxy, set `ip_header` / `trust_proxy` in `app/config.php`.

## Configuration (`app/config.php`)

| Key | Purpose |
|---|---|
| `db` | Database host (or socket path), port, name, user, password |
| `base_url` | Public URL, e.g. `https://example.com`. Leave empty to auto-detect |
| `debug` | Show error details — never on a live site. Errors are logged to `storage/logs/app.log` |
| `csp` | Send the Content-Security-Policy header (turn off only if you add third-party scripts) |
| `ip_header`, `trust_proxy` | Real client IP / HTTPS detection behind a proxy (e.g. `HTTP_CF_CONNECTING_IP`) |

Set the `BUBBLE_CONFIG` environment variable to load the configuration from another path.
No cron job is needed: the queue is processed inside each purchase.

---

## Project structure

```
bubble-cycler/
├── app/
│   ├── bootstrap.php          loaded by every page
│   ├── config.sample.php      config template (the installer writes config.php)
│   ├── lib/                   plain PHP functions
│   │   ├── cycler.php         FIFO pool, purchases, expirations, queue maths
│   │   ├── ads.php            ad gate, rotation, campaigns, house ads
│   │   ├── payments.php       manual methods, deposits, withdrawals
│   │   ├── ledger.php         wallets & transactions (the only way balances change)
│   │   ├── auth.php, admin.php, settings.php, money.php, db.php, ui.php, uploads.php, helpers.php
│   │   └── installer.php
│   └── views/                 layouts, partials and page templates
├── database/schema.sql        tables (run automatically by the installer)
├── public/                    web root: one PHP file per page, admin/, assets/
├── storage/                   logs and uploaded payment screenshots (private)
└── tests/                     CLI test suites
```

Pages follow the same pattern: `public/<page>.php` handles the request and calls
`render('<folder>/<view>', $data)`, which renders `app/views/<folder>/<view>.php` inside a layout.

## Security

- PDO prepared statements everywhere, `password_hash()` passwords, CSRF token on every form, sessions regenerated at
  sign-in and invalidated on password change, login throttling, strict Content-Security-Policy (no inline scripts),
  `X-Frame-Options`, escaped output.
- Every balance change is a locked, transactional ledger write that refuses to go negative; deadlocks are retried.
- Uploads are validated (type, size, real image), renamed randomly and stored outside the web root.
- Ad destinations must be `http(s)` URLs, redirects after sign-in stay on the site, members' names are masked in
  public feeds, and every admin action is written to the audit log.

## Tests

The suites run against a throw-away database whose name **must end with `_test`** (all its tables are dropped):

```bash
BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/cycler_test.php
BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/stress_test.php
```

`cycler_test.php` covers the FIFO maths, the ad gate, deposits, withdrawals, campaigns, pool top-ups and money
formatting (150+ checks). `stress_test.php` runs 13 processes buying, withdrawing and approving in parallel, then
verifies that no money was created or lost and that the queue stayed in strict order.

---

## Résumé en français

Script de « bubble cycler » complet en **PHP pur** + MySQL : bulle à 1 $, 0,80 $ versés dans un pool FIFO, chaque
bulle expire à 1,60 $, crédits publicitaires offerts à chaque achat, publicité obligatoire (10 s, vérifiée côté serveur)
avant chaque achat, méthodes de dépôt/retrait manuelles gérées depuis le panneau admin, validation des dépôts avec
capture d'écran, design premium sombre. Installation : pointer la racine web sur `public/`, créer une base MySQL,
ouvrir `/install.php`. Pensez à activer vos méthodes de paiement réelles et à vérifier la législation de votre pays.

Fonts: [Inter](https://github.com/rsms/inter) and [Sora](https://github.com/sora-xor/sora-font), SIL Open Font License
(see `public/assets/fonts/`).
