<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!current_user()) {
    redirect('auth/login.php');
}

$company = settings();
$dest = $_GET['to'] ?? 'dashboard/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loading — <?= e($company['company_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Georgia&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-width;
    }

    body {
        min-height: 100vh;
        background: #0c0c14;
        font-family: 'Inter', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .loader-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    .word {
        display: inline-flex;
        flex-direction: row;
        align-items: center;
        gap: 2px;
    }

    .letter {
        font-family: 'Georgia', serif;
        font-weight: bold;
        font-size: 42px;
        letter-spacing: 2px;
        color: #f2f0ff;
        display: inline-block;
        transform-origin: center top;
        animation: bounceOpacity 1.4s ease-in-out infinite;
    }

    .letter:nth-child(1) { animation-delay: 0s; }
    .letter:nth-child(2) { animation-delay: 0.07s; }
    .letter:nth-child(3) { animation-delay: 0.14s; }
    .letter:nth-child(4) { animation-delay: 0.21s; }
    .letter:nth-child(5) { animation-delay: 0.28s; }
    .letter:nth-child(6) { animation-delay: 0.35s; }
    .letter:nth-child(7) { animation-delay: 0.42s; }

    @keyframes bounceOpacity {
        0%, 100% {
            transform: translateY(0);
            opacity: 0.4;
        }
        50% {
            transform: translateY(-14px);
            opacity: 1.0;
        }
    }

    .caption {
        color: #55536e;
        font-family: 'Georgia', serif;
        font-size: 12px;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        opacity: 0.8;
    }
</style>
</head>
<body>
<div class="loader-wrap">
    <div class="word">
        <span class="letter">L</span>
        <span class="letter">O</span>
        <span class="letter">A</span>
        <span class="letter">D</span>
        <span class="letter">I</span>
        <span class="letter">N</span>
        <span class="letter">G</span>
    </div>
    <div class="caption">Just a moment</div>
</div>
<script>
    setTimeout(function() {
        window.location.href = '<?= BASE_URL . $dest ?>';
    }, 2000);
</script>
</body>
</html>