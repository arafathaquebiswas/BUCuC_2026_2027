<?php

// ─────────────────────────────────────────────────────────────────────────────
// Gmail SMTP Configuration
//
// IMPORTANT: Gmail requires a 16-character App Password (not your normal login).
// To regenerate:
//   1. Go to https://myaccount.google.com/security
//   2. Enable 2-Step Verification (must be ON)
//   3. Search "App passwords" → create one for "Mail"
//   4. Paste the 16-char code below (no spaces)
// ─────────────────────────────────────────────────────────────────────────────

define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USERNAME', 'hr.bucuc@gmail.com');
define('SMTP_PASSWORD', 'acfg asud cszi vpdp'); // <-- Replace with fresh App Password if auth fails
define('FROM_EMAIL',    'hr.bucuc@gmail.com');
define('FROM_NAME',     'BRAC University Cultural Club');

// Path to the dedicated email error log (relative to this file)
define('EMAIL_LOG_PATH', __DIR__ . '/../logs/email.log');
