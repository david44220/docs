# Production-readiness audit

**Scope:** the whole application — 60+ PHP files, views, CSS/JS, schema, installer.
**Method:** code review (security, money handling, concurrency, error paths), static analysis with PHPStan at
level 8, five automated suites, browser tests in Chromium, a web-installer run, a v1 → v2 migration run, and a
load test with **20 000 members, 400 000 bubbles and 1 000 000 ledger lines**.

## Results at a glance

| Check | Result |
|---|---|
| PHPStan level 8 (app, pages, CLI, tests) | **0 errors** (about 70 findings before the audit, all fixed) |
| `tests/cycler_test.php` — core logic, 2FA, resets, rate limits, migrations | **279 / 279** |
| `tests/stress_test.php` — 13 parallel processes (≈570 purchases, ≈1 400 payouts per run) | **15 / 15**, 0 errors |
| `tests/http_test.php` — every page and form through a real web server | **190 / 190**, no PHP warnings |
| `tests/smtp_test.php` — SMTP client against a fake server (plain + STARTTLS) | **15 / 15** |
| `tests/load_test.php` — HTTP load: 30 members + admin + visitors on a real web server for 45 s | **32 / 32** — 21 000 requests, 0 errors, 1 057 purchases, invariants hold |
| Same load test, spike of 80 simultaneous members | **32 / 32** — 14 258 requests, 0 errors, 1 730 purchases |
| Responsive audit (Chromium): 41 page variants × 14 widths (320–1920 px), mobile menu, landscape phones, interactive states | **0 issues**: no sideways scroll, nothing off-screen, no overlapping text or controls, no clipped text, tap targets ≥ 24 px |
| Browser tests (Chromium): countdown, calculators, copy, QR code, 2FA sign-in, landing language switch, phone layout | **39 / 39**, no console errors or CSP violations |
| Landing page vs the Cosmic Loop mockup (DOM signature, French and English) | **0 differences**; stylesheet byte-identical |
| Web installer on an empty database, then locked | pass |
| Upgrade of a version-1 database | schema identical to a fresh install |

Every suite also checks, after each scenario, that **no money was created or lost**, that **every ledger line carries
the right running balance**, that bubbles expired **strictly in queue order** and that each expired bubble was
**paid exactly once**.

---

## Design integration — Cosmic Loop (edition 08)

The design package is frozen in `mockups/cosmic-loop/` and never edited. The landing page
(`app/views/public/landing.php`) renders, on the server, exactly the markup the mockup's script builds in the browser;
the mockup's stylesheet is served unchanged (`public/assets/css/cosmic.css`).

| Check | Result |
|---|---|
| DOM signature (every element, id, class and text) of the template rendered with the mockup's texts (`fixtures/landing.php`) vs the mockup, French and English | **0 differences** |
| Attributes (links, `aria-*`, image attributes) with the same texts | identical |
| Pixels, template + assets vs mockup, 1440 × 900 and 390 × 844, full page, both languages (same font) | **0.00 %** different |
| `cosmic.css` vs the mockup's `styles.css` | byte-identical (checked by `tests/http_test.php`) |
| Browser console on the live landing, both languages | no errors, no CSP violations |

**Deliberate differences on the live page** (the structure and classes stay identical):

- *Links*: the mockup points at the design studio (edition gallery, "download this edition", "back to editions").
  The live page links to sign-in, account creation, the member area (for signed-in members) and the terms.
- *Accuracy*: two mockup sentences describe another product ("a defined term", "returns depend on advertising
  revenue"). Here bubbles have no fixed term — they wait in a first-in-first-out queue — and the pool is funded only by
  new purchases. Those sentences say what the app does; the rest of the copy is the mockup's, word for word.
- *Numbers from the settings*: target ROI (160 %), expiry amount and ad credits per bubble follow the admin settings.
- *Language*: rendered on the server from `?lang=` (remembered in an HttpOnly cookie), then the cookie, then the
  browser language — the mockup's rule (English for English browsers, French otherwise). The mockup's `app.js` is
  replaced by a small script for the language button; everything else is rendered on the server and works without
  JavaScript.
- *Head*: icons, web-app manifest, share tags, font preload, and `fonts.css` (self-hosted Inter) before the mockup's
  stylesheet — the mockup relied on locally installed fonts.

**The rest of the app** (sign-in pages, member area, admin panel, terms, privacy, errors, installer, emails, icons and
share image) now follows the same art direction through `public/assets/css/app.css`, loaded after the mockup's
stylesheet: Inter only, tight negative tracking on headlines, micro uppercase labels with the accent rule, pill
buttons with arrow glyphs, square hairline panels with 01/02/03 indexes and 34 px accent rules, circles for icons, the
violet accent on deep space, and the nebula artwork on the sign-in, legal, error and install pages. Colors keep WCAG
AA contrast (tertiary text ≥ 5.5 : 1); chart colors (violet, gold, blue) were validated for color-vision deficiency
(worst adjacent ΔE 27). The old Sora font, glass effects and ambient bubbles were removed.

---

## Findings and fixes

Severity reflects the impact on a live site handling real money.

### Money and concurrency

| # | Severity | Finding | Fix |
|---|---|---|---|
| M1 | **High** | With ads switched off, a resubmitted buy form (back button, retry, or a double click with JavaScript disabled) bought the bubbles again: only the ad token prevented replays, and only while ads were on. | One-time purchase token in the buy form; a replayed form is refused and the member is sent to *My bubbles*. |
| M2 | Medium | The pool paid one bubble per loop (about 8 queries each): measured 1.4 s per 1 000 bubbles, so a top-up paying ~20 000 bubbles needed ~28 s — at PHP's 30 s limit, after which the whole top-up rolls back. | Batch payouts: 500 bubbles per round with multi-row ledger inserts — 5 000 bubbles in 0.9 s (was 7.0 s), **20 000 in 2.9 s using 12 MB**. The transaction aborts if the queue data were ever inconsistent. |
| M3 | Medium | Deposit submission checked the pending limit and duplicate references before inserting, without a lock: two quick submissions could both pass. | Checks and insert now run in one transaction under the member's row lock. Approving a deposit whose reference was already approved is refused. |
| M4 | Low | A payment screenshot stayed on disk when the deposit could not be saved. | Deleted on failure. |
| M5 | Info | SQL behaviour depended on the host's `sql_mode`. | Every connection sets strict mode (`STRICT_ALL_TABLES`, `ONLY_FULL_GROUP_BY`, …); all suites pass under it. |

### Security

| # | Severity | Finding | Fix |
|---|---|---|---|
| S1 | **High** | **Open redirect after sign-in**: `next=/%09/evil.example` passed the "local path" check and browsers followed it off-site. | Control characters, whitespace, backslashes and `//` are refused; regression tests added. |
| S2 | Medium | **Account enumeration by timing** on PHP 8.1–8.3: unknown accounts were checked against a cost-12 dummy hash (~4× slower than real cost-10 hashes). | The dummy hash now matches the server's own algorithm and cost and follows PHP upgrades. |
| S3 | Medium | No second factor for admins who control all the money; no way to reset a forgotten password. | TOTP two-factor authentication (verified against the RFC 6238 test vectors), replay protection, 10 hashed recovery codes; **required for admins by default**. Password reset by email: single-use links, valid 1 h, only the SHA-256 hash stored, rate-limited, same answer whether or not the account exists, links built from `base_url` only (no Host-header poisoning). |
| S4 | Medium | Sessions lived in the system temp folder (shared with other sites on shared hosting) and their lifetime depended on the host's clean-up settings — from 24 minutes to never. | Private `storage/sessions` folder (0700), a 2 h idle timeout enforced by the app, sign-out everywhere after a password change. |
| S5 | Low | Sign-in throttling was per IP only (distributed guessing on one account was possible). | Also throttled per account; `php bin/admin.php unlock` clears a lock-out. |
| S6 | Low | Current-password checks (account page) and 2FA management codes had no attempt limit. | Rate-limited. |
| S7 | Low | Two submissions of the same reset link at the same instant could both succeed. | The token row is locked during the reset. |
| S8 | Low | Changing the email did not warn the previous address. | Security notice to the old address; notices for password and 2FA changes too. |
| S9 | Low | Sign-up abuse: no bot trap, no per-IP limit, common passwords accepted. | Honeypot field, sign-ups per IP per day (setting), common-password denylist, passwords containing the username refused. |
| S10 | Info | The new CSV exports contain member-typed text (e.g. a deposit reference `=HYPERLINK(…)`) that spreadsheets would run as a formula. | Exported cells starting with `= + - @` are neutralised (numbers kept); tested over HTTP. |
| S11 | Low | Email headers: sender names with commas or quotes produced an invalid `From:`; long non-ASCII subjects were not folded. | RFC 5322 quoting and RFC 2047 encoded-word folding; header injection neutralised. |
| S12 | Low | Missing `Strict-Transport-Security`, `Cross-Origin-Opener-Policy`; `X-Powered-By` leaked the PHP version. | Added / removed. The installer turns HTTPS redirects on when installed over HTTPS. |
| S13 | Info | The new two-factor secrets and SMTP password needed protection at rest. | Encrypted with libsodium using `app_key` (generated by the installer). |
| S14 | Low | The shared-hosting `.htaccess` did not block the new `bin/` and `docs/` folders. | Blocked (the CLI tool also refuses web requests). |
| S15 | Info | The JSON feed, health check and manifest opened a session on every request (one file per crawler or monitor hit). | These endpoints are stateless. |

Verified and already sound: prepared statements everywhere, CSRF on every form, escaped output, strict CSP without
inline scripts, uploads stored outside the web root with random names and real-image checks, proofs only readable
by admins, members cannot touch each other's withdrawals, campaigns or files (tested over HTTP), banned members are
signed out immediately, the installer locks itself (403) after use.

### Reliability and user experience

| # | Finding | Fix |
|---|---|---|
| R1 | 70+ PHPStan level-8 findings: database rows used without checking they exist, an undefined variable on an error path, float/int mix-ups in the base32 code. | `row_required()`, bit-level base32, typed fixes — 0 findings. |
| R2 | The deposit form promised 4 MB screenshots, but PHP's default `upload_max_filesize` is 2 MB: larger images failed with a confusing error. | The limit follows `php.ini` and is shown on the form. |
| R3 | Fonts were downloaded twice: the preload URLs carried `?v=` but the CSS did not (found by the browser test). | Same URLs; no duplicate downloads, no console warnings. |
| R4 | Icons, the share image and the manifest referenced by the pages did not exist (404s). | Generated (PNG icons, 32 KB share image), web-app manifest, `robots.txt`, `health.php`, privacy page. |
| R5 | No way to change the database schema safely on update. | Versioned migrations, applied once under a database lock on the first request or with `php bin/admin.php migrate`. |
| R6 | No operator tooling. | `bin/admin.php`: `check`, `stats`, `migrate`, `housekeeping`, `unlock`, `reset-2fa`, `set-password`. |
| R7 | Without JavaScript the buy button only unlocked after a page reload, and nothing said so. | A `<noscript>` hint explains to reload when the time is up (the server enforces the timer either way). |
| R8 | Small glitches on phones (2FA key split mid-group) and a truncated admin placeholder. | Fixed. |

---

## Load test

Dataset: 20 000 members, 100 000 purchases, 400 000 bubbles (200 000 waiting), 1 000 000 ledger lines, 60 000
deposits, 20 000 withdrawals, 200 000 ad views. MariaDB 10.11, PHP 8.4, single small container.

| Page / operation | Before | After |
|---|---|---|
| Home page (public, most visited) | 365 ms | **5 ms** — the Cosmic Loop landing now reads only the settings: ~2 ms |
| Admin dashboard | 718 ms | **149 ms** |
| Admin ledger, page 1 | 2 654 ms | **109 ms** |
| Admin ledger, page 20 000 | 3 654 ms | **397 ms** |
| Admin ledger filtered by type | 2 685 ms | **55 ms** |
| Admin pool & queue | 480 ms | **58 ms** |
| Member pages (dashboard, buy, bubbles, history, deposit…) | 2–5 ms | 2–5 ms |
| Buy 5 bubbles with 200 000 in the queue | — | 6.5 ms median, 10 ms p95 |
| Buy 1 000 bubbles (pays 500) | — | 88 ms |
| Pool top-up paying 20 000 bubbles | — | 2.9 s, 12 MB |
| Ledger CSV export, 1 000 000 rows | — | 3.4 s, **2 MB** peak memory (streamed) |

Cause of the slow pages: for `JOIN users … ORDER BY … LIMIT`, MariaDB chose to read all members first and sort every
matching row. Fixed with FIFO id ranges (the latest expired bubbles are the ids just below the expired counter),
`STRAIGHT_JOIN` where the main table must drive, and "deferred joins" (page through ids on an index, then fetch 40
rows). Queue positions stay O(1) thanks to the stored running targets.

### Concurrent HTTP load (`tests/load_test.php`)

A real web server (PHP's built-in server, 8 workers, 4 CPU cores — production would use PHP-FPM) on a fresh test
database. Each simulated member signs in and, in a loop, buys 1–3 bubbles through the buy form, browses member pages,
declares deposits, requests withdrawals and signs out and in again. An admin approves deposits, pays withdrawals and
tops up the pool at the same time, and visitors hit the public pages with 16 parallel connections.

| Run | Requests | Throughput | Purchases | Errors | Purchase latency p50 / p95 |
|---|---|---|---|---|---|
| 30 members, 45 s | 21 023 | 460 req/s | 1 057 | **0** | 93 ms / 183 ms |
| 80 members, 40 s (spike) | 14 258 | 343 req/s | 1 730 | **0** | 329 ms / 607 ms |

After each run: no 5xx, no failed CSRF check, no PHP warning, no application error, and every accounting invariant
holds (money in = money held, ledger = wallets, strict FIFO order, one payout per expired bubble). Sign-in is the
slowest step under load by design (password hashing is deliberately expensive).

---

## Responsive and overlap audit

Every screen was rendered in Chromium at 320, 360, 390, 414, 480, 600, 768, 900, 1024, 1100, 1280, 1366, 1440 and
1920 px (visitor, member, advertiser and admin pages: 41 variants), with the mobile menu open, in landscape phone
and tablet sizes, and in interactive states (sign-in and sign-up errors, deposit error, ad countdown finished, a real
purchase with its notices, the "bubbles expired" banner). A script measured, on each render: sideways scrolling,
content beyond the screen edges, overlapping text runs and controls, text clipped without an ellipsis, labels
overflowing their buttons, and tap targets under 24 px (WCAG 2.5.8). The detector was verified on planted defects.

Fixed during the audit:

| Screen | Problem | Fix |
|---|---|---|
| My bubbles, 320 px | The status badge ran out of the card (6 px sideways scroll). | Smaller bubble and tighter card on phones; the title row wraps. |
| Admin settings, ≤ 414 px | The "before going live" warning (long config values) pushed the page 160 px wide. | Alert text breaks long values. |
| Admin settings, phones | The floating save bar took a third of the screen. | Compact bar: full-width button only. |
| Withdrawal / campaign review, 320 px | Status badge and close button squeezed the title into a narrow column. | They move above the title on small screens. |
| Two-factor setup, 320 px | "Turn on two-factor authentication" overflowed its button. | Long button labels wrap on phones. |
| Notices (toasts), all sizes | Floating notices covered the top of the page on phones. | Notices now sit in the page flow above the content and fold away smoothly when dismissed. |
| "Bubbles expired" banner, phones | The headline was squeezed between the bubble and the button, one word per line. | The button moves under the text; the expired bubble is drawn full and gold. |
| Admin chart, 1280 px | Day labels ("Sep 25") were cut. | The month is named only on the first day and when it changes; labels thin out with the chart width. |

---

## Economics of the default settings

This is what the requested design ($1 bubble, $0.80 to the pool, expiry at $1.60) implies. Operators should
understand it before going live.

- **Two sales pay one bubble.** Every $1.60 payout needs two new $0.80 contributions. Without pool top-ups, at most
  about **half of all bubbles ever sold can expire**; the other half is always waiting in the queue.
- **Waiting time grows with the site's age.** With a constant sales rate, a bubble bought on day *t* after launch
  expires around day *2t*: a bubble bought after one month waits about a month, one bought after a year waits about
  a year. If sales slow down, waits grow longer; if they stop, every waiting bubble stays unpaid.
- **The queue gap** — shown on the admin dashboard and by `php bin/admin.php stats` — is the money still needed to
  pay every waiting bubble (`$1.60 × waiting − pool balance`). It only grows while sales continue.
- Platform share: $0.15 per bubble ($0.20 without a referrer); referral $0.05. Top-ups from the admin are recorded
  separately and paid to the queue in order.

The site says this plainly (risk disclaimer in every footer, FAQ, terms), and every member-facing number (position,
sales still needed) is real. **Paying earlier participants with later participants' money is regulated or
prohibited in many countries, and handling deposits may bring payment, KYC/AML and tax obligations. Check the law
where you and your members are before accepting real money.**

---

## Known limits and recommendations

- **Payments are manual by design** (no payment gateway): an admin must verify every deposit and send every
  withdrawal. Keep the per-member pending limits low and review large amounts carefully.
- **Emails are sent during the request.** An admin action that notifies a member waits for the SMTP server (usually
  well under a second). If volume grows, move sending to a queue processed by cron.
- **Sessions are files.** Behind several web servers, use sticky sessions or put `BUBBLE_STORAGE` on shared storage.
- **Keep `app/config.php` backed up**: losing `app_key` means every two-factor user must set it up again
  (`reset-2fa`) and the SMTP password must be re-entered.
- **Server clock**: two-factor codes depend on it; `php bin/admin.php check` compares the PHP and database clocks.
- **Not covered by automated tests**: the SMTP client's `AUTH LOGIN` fallback (the fake server offers `AUTH PLAIN`,
  which is what the client prefers), PHP's `mail()` transport, and real payment providers. The browser tests were
  run during the audit with Playwright but are not shipped, to keep the project pure PHP.
- Backups, monitoring (`/health.php`) and the cron job are documented in the README but must be set up on the server.

## Reproduce

```bash
export BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret
php tests/cycler_test.php && php tests/stress_test.php && php tests/http_test.php && php tests/smtp_test.php
php tests/load_test.php 30 45   # members, seconds
php bin/admin.php check
```

---

## Résumé (FR)

Audit complet avant mise en production : analyse statique (PHPStan niveau 8 : 0 erreur), 6 suites de tests
automatiques (279 + 15 + 190 + 15 + 32 vérifications), tests navigateur (39), audit responsive de chaque écran sur
14 largeurs (320 à 1920 px : aucun débordement, aucun chevauchement), test de charge HTTP (jusqu'à 80 membres
simultanés : 0 erreur, comptabilité intacte), test de charge avec 20 000 membres,
400 000 bulles et 1 million de lignes de grand livre. Corrigés : redirection ouverte après connexion, double achat
si le formulaire est renvoyé, paiement du pool bulle par bulle (désormais par lots : 20 000 bulles en 2,9 s au lieu
d'environ 28 s), fuite d'information par le temps de réponse à la connexion, sessions non isolées, pages lentes sur
de gros volumes (page d'accueil 365 → 5 ms, grand livre admin 2,7 s → 0,1 s), double téléchargement des polices,
limite d'envoi de fichiers incorrecte. Ajoutés : double
authentification (obligatoire pour les admins), réinitialisation du mot de passe par email, notifications, exports
CSV, migrations, outil `bin/admin.php`. Rappel : avec les réglages par défaut, deux ventes paient une bulle — au
plus la moitié des bulles peut expirer sans apport extérieur ; vérifiez la législation de votre pays.
