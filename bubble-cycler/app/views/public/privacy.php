<?php
/** Privacy policy. Custom text from Admin → Settings replaces the default one. */
$custom = setting_text('privacy_text');
$site = site_name();
$contact = setting('support_email');
$mail = $contact !== '' ? '<a href="mailto:' . e($contact) . '">' . e($contact) . '</a>' : '';
?>
<section class="section doc">
    <aside class="doc__aside">
        <dl class="dl dl--rows doc__facts">
            <div><dt><?= e(t('Cookies')) ?></dt><dd><?= e(t('Session, language and referral only')) ?></dd></div>
            <div><dt><?= e(t('Your data')) ?></dt><dd><?= e(t('Never sold')) ?></dd></div>
            <div><dt><?= e(t('Contact')) ?></dt><dd><?= $contact !== '' ? $mail : e(t('Support, from your account')) ?></dd></div>
        </dl>
    </aside>

    <article class="prose">
        <?php if ($custom !== ''): ?>
            <?php foreach (preg_split('/\R{2,}/', $custom) as $paragraph): ?>
                <p><?= nl2br(e(trim($paragraph))) ?></p>
            <?php endforeach; ?>
        <?php else: ?>
            <h2><?= e(t('Data we keep')) ?></h2>
            <p><?= e(t('Your username, email address and password (stored only as a one-way hash); your balances, bubbles, deposits, withdrawals, advertising campaigns and the full history of movements on your account; the IP address used to register and to sign in; the payment references, sender details and screenshots you submit with deposits; the account details you give for withdrawals; your two-factor authentication settings if you turn them on; and your preferred language.')) ?></p>

            <h2><?= e(t('Why we keep it')) ?></h2>
            <p><?= e(t('To run your account and the bubble queue, to verify deposits and send withdrawals, to prevent fraud and abuse (for example multiple accounts), to secure your sign-in, and to meet our legal and accounting obligations. We do not sell your data.')) ?></p>

            <h2><?= e(t('Cookies')) ?></h2>
            <p><?= e(t('We use one session cookie that keeps you signed in, a language cookie (1 year) that remembers the language you picked, and a referral cookie (30 days) that remembers who invited you. There are no advertising or analytics cookies. Sponsored messages are shown by our own servers; banner images may be loaded from the advertiser’s website.')) ?></p>

            <h2><?= e(t('Language')) ?></h2>
            <p><?= e(t('The site is shown in English, or in French when your browser asks for French or you visit from France. To choose the language we read your browser’s language preference and, when our hosting provider supplies it, the country of your connection; neither is stored.')) ?></p>

            <h2><?= e(t('Emails')) ?></h2>
            <p><?= e(t('We send you emails about your account only: password resets, security notices and the status of your deposits and withdrawals.')) ?></p>

            <h2><?= e(t('Retention')) ?></h2>
            <p><?= e(t('Account and transaction records are kept while your account exists and afterwards for as long as the law requires for financial records. Payment screenshots can be deleted once a deposit has been processed.')) ?></p>

            <h2><?= e(t('Your rights')) ?></h2>
            <p><?= $contact !== ''
                ? t_html('You can ask for a copy of your data, its correction or its deletion (within the limits of legal record-keeping obligations) by writing to {email}.', ['email' => $mail])
                : e(t('You can ask for a copy of your data, its correction or its deletion (within the limits of legal record-keeping obligations) by contacting support.')) ?></p>
        <?php endif; ?>
    </article>
</section>
