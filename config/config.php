<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $values = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
    foreach ($values as $key => $value) {
        if (getenv((string) $key) === false) {
            putenv($key . '=' . trim((string) $value, "\"'"));
            $_ENV[$key] = trim((string) $value, "\"'");
        }
    }
}

return [
    'app_name' => getenv('APP_NAME') ?: 'Lensa Studio',
    'app_env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Makassar',
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_name' => getenv('DB_NAME') ?: 'fotostudio',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'upload_max_mb' => max(1, (int) (getenv('UPLOAD_MAX_MB') ?: 12)),
];
