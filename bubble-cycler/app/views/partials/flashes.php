<?php
/** Toast notifications. Expects $flashes = [['type' => ..., 'message' => ...]]. */
$icons = ['success' => 'check', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info', 'pop' => 'sparkles'];
?>
<div class="toasts" data-toasts aria-live="polite">
    <?php foreach ($flashes as $flash): $type = isset($icons[$flash['type']]) ? $flash['type'] : 'info'; ?>
        <div class="toast toast--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"<?= $type === 'pop' ? ' data-celebrate' : '' ?>>
            <span class="toast__icon"><?= icon($icons[$type]) ?></span>
            <p class="toast__text"><?= e($flash['message']) ?></p>
            <button class="toast__close" type="button" data-toast-close aria-label="<?= e(t('Dismiss')) ?>"><?= icon('x') ?></button>
        </div>
    <?php endforeach; ?>
</div>
