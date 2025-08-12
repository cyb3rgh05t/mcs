<?php

/**
 * Mobile Car Service - Backend Konfiguration
 * Angepasst für PHP Development Server (php -S localhost:8000)
 */

// Error Reporting (für Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setzen
date_default_timezone_set('Europe/Berlin');

// App-Konfiguration - ANGEPASST für Development Server
define('APP_NAME', 'Mobile Car Service');
define('APP_VERSION', '1.0.0');

// WICHTIG: URL für PHP Development Server
if ($_SERVER['SERVER_NAME'] === 'localhost' && $_SERVER['SERVER_PORT'] == '8000') {
    define('APP_URL', 'http://localhost:8000');
} else {
    define('APP_URL', 'http://localhost/mobile-car-service');
}

// Datenbank-Konfiguration
define('DB_PATH', __DIR__ . '/data/database.sqlite');
define('DB_BACKUP_PATH', __DIR__ . '/data/backups/');

// Pfade
define('BASE_PATH', __DIR__);
define('LOGS_PATH', __DIR__ . '/logs/');
define('UPLOADS_PATH', __DIR__ . '/uploads/');

// API-Konfiguration - ANGEPASST für Development Server
define('API_VERSION', 'v1');
define('API_PREFIX', '/backend/api.php');

// CORS-Einstellungen - WICHTIG für Development
define('CORS_ORIGIN', '*'); // Für Development OK
define('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// E-Mail-Konfiguration
define('SMTP_HOST', 'localhost');
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
define('SESSION_TIMEOUT', 3600);
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
define('CACHE_DURATION', 3600);
define('CACHE_ENABLED', true);

/**
 * Development Server Helper
 */
function isDevelopmentServer()
{
    return $_SERVER['SERVER_NAME'] === 'localhost' && $_SERVER['SERVER_PORT'] == '8000';
}

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
                'debug' => $this->isDebugMode(),
                'development_server' => isDevelopmentServer()
            ],
            'database' => [
                'path' => DB_PATH,
                'backup_path' => DB_BACKUP_PATH
            ],
            'cors' => [
                'origin' => CORS_ORIGIN,
                'methods' => CORS_METHODS,
                'headers' => CORS_HEADERS
            ],
            'business' => [
                'hours_start' => BUSINESS_HOURS_START,
                'hours_end' => BUSINESS_HOURS_END,
                'days_advance' => BUSINESS_DAYS_ADVANCE,
                'travel_cost_per_km' => TRAVEL_COST_PER_KM,
                'free_distance_km' => FREE_DISTANCE_KM
            ],
            'company' => [
                'name' => COMPANY_NAME,
                'address' => COMPANY_ADDRESS,
                'phone' => COMPANY_PHONE,
                'email' => COMPANY_EMAIL
            ]
        ];
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
            isset($_GET['debug']) ||
            isDevelopmentServer()
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

function config($key, $default = null)
{
    return Config::getInstance()->get($key, $default);
}

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

function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');

    // CORS Headers für Development Server
    if (isDevelopmentServer()) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }

    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

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

function setCorsHeaders()
{
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: ' . CORS_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_HEADERS);
    header('Access-Control-Max-Age: 86400');

    // Preflight-Request behandeln
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function getJsonInput()
{
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

function generateId($prefix = '')
{
    return $prefix . uniqid() . '-' . random_int(1000, 9999);
}

/**
 * Development Server spezifische Funktionen
 */
function getClientIp()
{
    if (isDevelopmentServer()) {
        return '127.0.0.1'; // Development Server
    }

    return $_SERVER['HTTP_X_FORWARDED_FOR'] ??
        $_SERVER['HTTP_X_REAL_IP'] ??
        $_SERVER['REMOTE_ADDR'] ??
        'unknown';
}

/**
 * Database Helper-Funktion
 */
if (!function_exists('db')) {
    function db()
    {
        return Database::getInstance();
    }
}
