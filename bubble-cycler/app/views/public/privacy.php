<?php
/** Privacy policy. Custom text from Admin → Settings replaces the default one. */
$custom = setting('privacy_text');
$site = site_name();
$contact = setting('support_email');
?>
<section class="section doc">
    <aside class="doc__aside">
        <dl class="dl dl--rows doc__facts">
            <div><dt>Cookies</dt><dd>Session and referral only</dd></div>
            <div><dt>Your data</dt><dd>Never sold</dd></div>
            <div><dt>Contact</dt><dd><?= $contact !== '' ? '<a href="mailto:' . e($contact) . '">' . e($contact) . '</a>' : 'Support, from your account' ?></dd></div>
        </dl>
    </aside>

    <article class="prose">
        <?php if ($custom !== ''): ?>
            <?php foreach (preg_split('/\R{2,}/', $custom) as $paragraph): ?>
                <p><?= nl2br(e(trim($paragraph))) ?></p>
            <?php endforeach; ?>
        <?php else: ?>
            <h2>Data we keep</h2>
            <p>Your username, email address and password (stored only as a one-way hash); your balances, bubbles, deposits, withdrawals, advertising campaigns and the full history of movements on your account; the IP address used to register and to sign in; the payment references, sender details and screenshots you submit with deposits; the account details you give for withdrawals; and your two-factor authentication settings if you turn them on.</p>

            <h2>Why we keep it</h2>
            <p>To run your account and the bubble queue, to verify deposits and send withdrawals, to prevent fraud and abuse (for example multiple accounts), to secure your sign-in, and to meet our legal and accounting obligations. We do not sell your data.</p>

            <h2>Cookies</h2>
            <p>We use one session cookie that keeps you signed in, and a referral cookie (30 days) that remembers who invited you. There are no advertising or analytics cookies. Sponsored messages are shown by our own servers; banner images may be loaded from the advertiser's website.</p>

            <h2>Emails</h2>
            <p>We send you emails about your account only: password resets, security notices and the status of your deposits and withdrawals.</p>

            <h2>Retention</h2>
            <p>Account and transaction records are kept while your account exists and afterwards for as long as the law requires for financial records. Payment screenshots can be deleted once a deposit has been processed.</p>

            <h2>Your rights</h2>
            <p>You can ask for a copy of your data, its correction or its deletion (within the limits of legal record-keeping obligations)<?= $contact !== '' ? ' by writing to <a href="mailto:' . e($contact) . '">' . e($contact) . '</a>' : ' by contacting support' ?>.</p>
        <?php endif; ?>
    </article>
</section>
