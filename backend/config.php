<?php

/**
 * Mobile Car Service - Backend Konfiguration
 * Zentrale Einstellungen für das gesamte Backend
 */

// Error Reporting (für Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setzen
date_default_timezone_set('Europe/Berlin');

// App-Konfiguration
define('APP_NAME', 'Mobile Car Service');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/mobile-car-service');

// Datenbank-Konfiguration
define('DB_PATH', __DIR__ . '/data/database.sqlite');
define('DB_BACKUP_PATH', __DIR__ . '/data/backups/');

// Pfade
define('BASE_PATH', __DIR__);
define('LOGS_PATH', __DIR__ . '/logs/');
define('UPLOADS_PATH', __DIR__ . '/uploads/');

// API-Konfiguration
define('API_VERSION', 'v1');
define('API_PREFIX', '/backend/api.php');

// CORS-Einstellungen
define('CORS_ORIGIN', '*'); // In Produktion spezifische Domain setzen
define('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// E-Mail-Konfiguration
define('SMTP_HOST', 'localhost'); // Für lokalen Server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@mobile-car-service.de');
define('SMTP_FROM_NAME', 'Mobile Car Service');

// Business-Konfiguration
define('COMPANY_NAME', 'Mobile Car Service');
define('COMPANY_ADDRESS', 'Industriestraße 15, 48431 Rheine');
define('COMPANY_PHONE', '+49 (0) 1234 567890');
define('COMPANY_EMAIL', 'info@mobile-car-service.de');

// Preisberechnung
define('TRAVEL_COST_PER_KM', 1.50);
define('FREE_DISTANCE_KM', 10);

// Geschäftszeiten
define('BUSINESS_HOURS_START', 8);
define('BUSINESS_HOURS_END', 18);
define('BUSINESS_DAYS_ADVANCE', 21);

// Sicherheits-Einstellungen
define('SESSION_TIMEOUT', 3600); // 1 Stunde
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);

// Validierungs-Regeln
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);

// Logging-Level
define('LOG_LEVEL_ERROR', 1);
define('LOG_LEVEL_WARNING', 2);
define('LOG_LEVEL_INFO', 3);
define('LOG_LEVEL_DEBUG', 4);
define('CURRENT_LOG_LEVEL', LOG_LEVEL_INFO);

// Cache-Einstellungen
define('CACHE_DURATION', 3600); // 1 Stunde
define('CACHE_ENABLED', true);

/**
 * Umgebungsabhängige Konfiguration
 */
class Config
{
    private static $instance = null;
    private $config = [];

    private function __construct()
    {
        $this->loadConfig();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig()
    {
        // Basis-Konfiguration
        $this->config = [
            'app' => [
                'name' => APP_NAME,
                'version' => APP_VERSION,
                'url' => APP_URL,
                'debug' => $this->isDebugMode()
            ],
            'database' => [
                'path' => DB_PATH,
                'backup_path' => DB_BACKUP_PATH
            ],
            'email' => [
                'host' => SMTP_HOST,
                'port' => SMTP_PORT,
                'username' => SMTP_USERNAME,
                'password' => SMTP_PASSWORD,
                'from_email' => SMTP_FROM_EMAIL,
                'from_name' => SMTP_FROM_NAME
            ],
            'business' => [
                'travel_cost_per_km' => TRAVEL_COST_PER_KM,
                'free_distance_km' => FREE_DISTANCE_KM,
                'hours_start' => BUSINESS_HOURS_START,
                'hours_end' => BUSINESS_HOURS_END,
                'days_advance' => BUSINESS_DAYS_ADVANCE
            ]
        ];

        // Umgebungsabhängige Einstellungen laden
        $this->loadEnvironmentConfig();
    }

    private function loadEnvironmentConfig()
    {
        // .env Datei laden falls vorhanden
        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $this->setNestedConfig(trim($key), trim($value, '"\''));
                }
            }
        }
    }

    private function setNestedConfig($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $config = $this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                return $default;
            }
            $config = $config[$k];
        }

        return $config;
    }

    public function set($key, $value)
    {
        $this->setNestedConfig($key, $value);
    }

    private function isDebugMode()
    {
        return (
            $_SERVER['SERVER_NAME'] === 'localhost' ||
            $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
            isset($_GET['debug'])
        );
    }

    public function all()
    {
        return $this->config;
    }
}

/**
 * Helper-Funktionen
 */

/**
 * Konfigurationswert abrufen
 */
function config($key, $default = null)
{
    return Config::getInstance()->get($key, $default);
}

/**
 * Debug-Information ausgeben
 */
function debug($data, $die = false)
{
    if (config('app.debug')) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';

        if ($die) {
            die();
        }
    }
}

/**
 * JSON-Response senden
 */
function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Error-Response senden
 */
function errorResponse($message, $status = 400, $code = null)
{
    jsonResponse([
        'success' => false,
        'error' => [
            'message' => $message,
            'code' => $code,
            'timestamp' => date('c')
        ]
    ], $status);
}

/**
 * Success-Response senden
 */
function successResponse($data = [], $message = null)
{
    $response = [
        'success' => true,
        'data' => $data
    ];

    if ($message) {
        $response['message'] = $message;
    }

    jsonResponse($response);
}

/**
 * CORS-Headers setzen
 */
function setCorsHeaders()
{
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: ' . CORS_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_HEADERS);
    header('Access-Control-Max-Age: 86400'); // 24 Stunden

    // Preflight-Request behandeln
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Request-Body als JSON parsen
 */
function getJsonInput()
{
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Eindeutige ID generieren
 */
function generateId($prefix = '')
{
    return $prefix . strtoupper(uniqid());
}

/**
 * Sichere Zufallsstring generieren
 */
function generateRandomString($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Request-IP abrufen
 */
function getClientIp()
{
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * User-Agent abrufen
 */
function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Request-Daten loggen
 */
function logRequest()
{
    if (config('app.debug')) {
        $logData = [
            'timestamp' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => getClientIp(),
            'user_agent' => getUserAgent()
        ];

        error_log('REQUEST: ' . json_encode($logData));
    }
}

// CORS-Headers automatisch setzen
setCorsHeaders();

// Request loggen
logRequest();
