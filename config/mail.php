<?php
declare(strict_types=1);

/* Local mail configuration. Fill these in only when Gmail OTP email is needed. */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'yourgmail@gmail.com');
define('MAIL_PASS', 'your-16-char-app-password');
define('MAIL_FROM', MAIL_USER);
define('MAIL_FROM_NAME', 'Smart Invoice');

define('OTP_COOLDOWN_SECONDS', 30);
define('OTP_EXPIRY_SECONDS', 300);
define('OTP_RESEND_SECONDS', 60);
