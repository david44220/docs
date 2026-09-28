<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

capture_referral();

// The Cosmic Loop landing is a complete page of its own (no app layout).
echo view_capture('public/landing', ['page' => landing_page(landing_language(), current_user())]);
