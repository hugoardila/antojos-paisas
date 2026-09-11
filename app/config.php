<?php
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
const APP_TIMEZONE = 'America/Bogota';
const DB_TIMEZONE_OFFSET = '-05:00';

date_default_timezone_set(APP_TIMEZONE);

function env_or_default(string $key, string $default): string {
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = env_or_default('ANTOJOS_DB_HOST', DB_HOST);
        $name = env_or_default('ANTOJOS_DB_NAME', DB_NAME);
        $user = env_or_default('ANTOJOS_DB_USER', DB_USER);
        $pass = env_or_default('ANTOJOS_DB_PASS', DB_PASS);
        $port = env_or_default('ANTOJOS_DB_PORT', '3306');
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
    }
    return $pdo;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function money($value): string { return '$' . number_format((float)$value, 0, ',', '.'); }
function setting(string $key, string $fallback = ''): string { $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?'); $stmt->execute([$key]); $value = $stmt->fetchColumn(); return $value === false ? $fallback : (string)$value; }
