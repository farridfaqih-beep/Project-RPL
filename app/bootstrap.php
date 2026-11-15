<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = require BASE_PATH . '/config/config.php';
date_default_timezone_set($GLOBALS['config']['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('lensa_studio_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require BASE_PATH . '/app/Core/Database.php';
require BASE_PATH . '/app/Support/helpers.php';
require BASE_PATH . '/app/Support/BookingRules.php';
require BASE_PATH . '/app/Repositories.php';
require BASE_PATH . '/app/Services.php';
require BASE_PATH . '/app/Controllers.php';
