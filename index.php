<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

redirect(current_user() ? 'dashboard/index.php' : 'auth/login.php');