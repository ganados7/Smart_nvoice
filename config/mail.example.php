<?php
declare(strict_types=1);

/* ============================================================
   EMAIL (Gmail SMTP) CONFIGURATION
   Copy this file to config/mail.php and fill in your credentials.
   MAIL_PASS must be an App Password (not your normal Gmail password).
   How to create one: Google Account -> Security -> 2-Step Verification ->
   App passwords -> generate one for "Mail".
   ============================================================ */

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'yourgmail@gmail.com');      // sender Gmail address
define('MAIL_PASS', 'your-16-char-app-password'); // Gmail app password
define('MAIL_FROM', MAIL_USER);
define('MAIL_FROM_NAME', 'Smart Invoice');

/* OTP behavior */
define('OTP_COOLDOWN_SECONDS', 30);  // skip OTP if verified within this many seconds
define('OTP_EXPIRY_SECONDS', 300);   // code valid for 5 minutes
define('OTP_RESEND_SECONDS', 60);    // min seconds before resend allowed